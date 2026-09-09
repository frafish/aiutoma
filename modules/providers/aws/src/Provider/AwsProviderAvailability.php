<?php
declare(strict_types=1);
namespace Aiutoma\AwsAiProvider\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

class AwsProviderAvailability implements ProviderAvailabilityInterface, WithRequestAuthenticationInterface
{
    use WithRequestAuthenticationTrait;

    public function isConfigured(): bool
    {
        $apiKeyOpt = '';
        try {
            $auth = $this->getRequestAuthentication();
            if ($auth instanceof ApiKeyRequestAuthentication) {
                $apiKeyOpt = $auth->getApiKey();
            }
        } catch (\Throwable $e) {
            // Not set via trait yet
        }

        if (empty($apiKeyOpt) && class_exists('\WordPress\AiClient\AiClient')) {
            try {
                $auth = \WordPress\AiClient\AiClient::defaultRegistry()->getProviderRequestAuthentication('aws');
                if ($auth instanceof ApiKeyRequestAuthentication) {
                    $apiKeyOpt = $auth->getApiKey();
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }

        if (empty($apiKeyOpt)) {
            if (defined('AWS_API_KEY')) {
                $apiKeyOpt = constant('AWS_API_KEY');
            } elseif (getenv('AWS_API_KEY')) {
                $apiKeyOpt = getenv('AWS_API_KEY');
            }
        }

        $parts = explode('|', $apiKeyOpt);
        
        return count($parts) >= 3 && !empty($parts[0]) && !empty($parts[1]) && !empty($parts[2]);
    }
}
