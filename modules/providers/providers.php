<?php
namespace Aiutoma\Modules\Providers;

if (!defined('ABSPATH')) {
    exit;
}

class Providers
{

    public function __construct()
    {
        add_action('init', [$this, 'register_subplugins_providers'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // The AI module discovers models during plugins_loaded, before init fires.
        $this->register_subplugins_providers();
    }

    public function enqueue_admin_scripts($hook)
    {
        global $pagenow;
        if ($pagenow !== 'options-connectors.php') {
            return;
        }

        wp_enqueue_script(
            'aiutoma-provider-instructions',
            AIUTOMA_URL . 'modules/providers/assets/js/provider-instructions.js',
            [],
            AIUTOMA_VERSION,
            true
        );
    }

    public function register_subplugins_providers()
    {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return;
        }

        if (file_exists(AIUTOMA_PATH . 'modules/providers/groq/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/groq/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/openrouter/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/openrouter/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/huggingface/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/huggingface/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/github/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/github/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/mistral/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/mistral/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/cohere/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/cohere/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/cloudflare/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/cloudflare/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/aws/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/aws/src/autoload.php';
        }
        if (file_exists(AIUTOMA_PATH . 'modules/providers/ollama/src/autoload.php')) {
            require_once AIUTOMA_PATH . 'modules/providers/ollama/src/autoload.php';
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();

        $custom_providers = [
            'groq' => '\Aiutoma\GroqAiProvider\Provider\GroqProvider',
            'huggingface' => '\Aiutoma\HuggingFaceAiProvider\Provider\HuggingFaceProvider',
            'openrouter' => '\Aiutoma\OpenRouterAiProvider\Provider\OpenRouterProvider',
            'github' => '\Aiutoma\GithubAiProvider\Provider\GithubProvider',
            'mistral' => '\Aiutoma\MistralAiProvider\Provider\MistralProvider',
            'cohere' => '\Aiutoma\CohereAiProvider\Provider\CohereProvider',
            'cloudflare' => '\Aiutoma\CloudflareAiProvider\Provider\CloudflareProvider',
            'aws' => '\Aiutoma\AwsAiProvider\Provider\AwsProvider'
        ];

        foreach ($custom_providers as $id => $class) {
            if (class_exists($class) && !$registry->hasProvider($class)) {
                $registry->registerProvider($class);
            }
        }

        // Ollama Provider integration has been delegated to the official ai-provider-for-ollama plugin
        // to avoid conflicts and simplify configuration for the user.
    }
}
