<?php
declare(strict_types=1);
namespace Aiutoma\CloudflareAiProvider\Models;

if ( ! defined( 'ABSPATH' ) ) exit;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use Aiutoma\CloudflareAiProvider\Provider\CloudflareProvider;

/**
 * Class for a Cloudflare text generation model.
 *
 * @since 1.0.0
 */
class CloudflareTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        $accountId = '';
        
        $accountId = '';
        
        try {
            $auth = $this->getRequestAuthentication();
            if ($auth instanceof \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication) {
                $apiKeyOpt = $auth->getApiKey();
                $parts = explode('|', $apiKeyOpt);
                if (count($parts) > 1) {
                    $accountId = $parts[0];
                    $this->setRequestAuthentication(
                        new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication($parts[1] ?? '')
                    );
                }
            }
        } catch (\Throwable $e) {
            // Ignore if not set
        }
        
        // Fallback if not in header
        if (empty($accountId)) {
            $apiKeyOpt = '';
            if (class_exists('\WordPress\AiClient\AiClient')) {
                try {
                    $auth = \WordPress\AiClient\AiClient::defaultRegistry()->getProviderRequestAuthentication('cloudflare');
                    if ($auth instanceof \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication) {
                        $apiKeyOpt = $auth->getApiKey();
                    }
                } catch (\Throwable $e) {
                    // Ignore
                }
            }
            if (empty($apiKeyOpt) && defined('CLOUDFLARE_API_KEY')) {
                $apiKeyOpt = constant('CLOUDFLARE_API_KEY');
            } elseif (empty($apiKeyOpt) && getenv('CLOUDFLARE_API_KEY')) {
                $apiKeyOpt = getenv('CLOUDFLARE_API_KEY');
            }
            $parts = explode('|', $apiKeyOpt);
            $accountId = $parts[0] ?? '';
        }
        
        $url = 'https://api.cloudflare.com/client/v4/accounts/' . $accountId . '/ai/v1/' . ltrim($path, '/');

        return new Request(
            $method,
            $url,
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
