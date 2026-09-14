<?php

/**
 * Plugin Name: AIutoma – The AIO Autonomous AI Assistant
 * Description: Autonomous AI assistant and agentic automation suite.
 * Version: 1.0.1
 * Author: frapesce
 * Text Domain: aiutoma
 * Requires at least: 7.0
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AIUTOMA_FILE', __FILE__);
define('AIUTOMA_PATH', plugin_dir_path(__FILE__));
define('AIUTOMA_URL', plugin_dir_url(__FILE__));
define('AIUTOMA_VERSION', '1.0.1');

add_action('plugins_loaded', function () {

    if (file_exists(AIUTOMA_PATH . 'vendor/autoload.php')) {
        require_once AIUTOMA_PATH . 'vendor/autoload.php';
    }

    spl_autoload_register(function ($class) {
        $prefix = 'Aiutoma\\';
        $base_dir = AIUTOMA_PATH;
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $path = str_replace('\\', '/', $relative_class);
        $file = $base_dir . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $path)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
    $active_modules = get_option('aiutoma_active_modules', [
        'providers' => 1,
        'editor'  => 1,
        'chatbot' => 0,
        'seo'     => 0,
        'mcp'     => 0,
        'wpml'    => 0,
    ]);

    \Aiutoma\Modules\Ai\Ai::instance();
    if (!empty($active_modules['providers'])) {
        new \Aiutoma\Modules\Providers\Providers();
    }
    \Aiutoma\Modules\Playground\Playground::instance();
    new \Aiutoma\Modules\Markdown\Markdown();

    if (!empty($active_modules['mcp'])) {
        new \Aiutoma\Modules\Mcp\Mcp();
    }

    add_action('init', function () use ($active_modules) {
        $has_models = \Aiutoma\Modules\Ai\Ai::instance()->has_ai_models();

        if ($has_models) {
            if (!empty($active_modules['editor'])) {
                new \Aiutoma\Modules\Editor\Editor();
            }

            if (!empty($active_modules['chatbot'])) {
                new \Aiutoma\Modules\Chatbot\Chatbot();
            }

            if (!empty($active_modules['seo'])) {
                new \Aiutoma\Modules\Seo\Seo();
            }

            if (!empty($active_modules['wpml'])) {
                new \Aiutoma\Modules\Wpml\Wpml();
            }
        }
    }, 25);
});

register_deactivation_hook(AIUTOMA_FILE, 'aiutoma_deactivate');
function aiutoma_deactivate()
{
    do_action('aiutoma_deactivated');
}
