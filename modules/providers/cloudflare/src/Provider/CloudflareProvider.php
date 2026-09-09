<?php
declare(strict_types=1);
namespace Aiutoma\CloudflareAiProvider\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use Aiutoma\CloudflareAiProvider\Metadata\CloudflareModelMetadataDirectory;
use Aiutoma\CloudflareAiProvider\Models\CloudflareTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;

class CloudflareProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return static::baseUrl() . '/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
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
        
        return 'https://api.cloudflare.com/client/v4/accounts/' . $accountId . '/ai/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new CloudflareTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'cloudflare',
            'Cloudflare Workers AI',
            ProviderTypeEnum::cloud(),
            'https://dash.cloudflare.com/',
            RequestAuthenticationMethod::apiKey(),
            'Fast and secure AI models on Cloudflare global network',
            dirname(__DIR__, 2) . '/assets/images/cloudflare.svg'
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new CloudflareModelMetadataDirectory();
    }
}
