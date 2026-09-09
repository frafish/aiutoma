<?php
declare(strict_types=1);
namespace Aiutoma\AwsAiProvider\Provider;

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
use Aiutoma\AwsAiProvider\Metadata\AwsModelMetadataDirectory;
use Aiutoma\AwsAiProvider\Models\AwsTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use Aiutoma\AwsAiProvider\Provider\AwsProviderAvailability;

class AwsProvider extends AbstractApiProvider
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
                $auth = \WordPress\AiClient\AiClient::defaultRegistry()->getProviderRequestAuthentication('aws');
                if ($auth instanceof \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication) {
                    $apiKeyOpt = $auth->getApiKey();
                }
            } catch (\Throwable $e) {
                // Ignore
            }
        }
        if (empty($apiKeyOpt) && defined('AWS_API_KEY')) {
            $apiKeyOpt = constant('AWS_API_KEY');
        } elseif (empty($apiKeyOpt) && getenv('AWS_API_KEY')) {
            $apiKeyOpt = getenv('AWS_API_KEY');
        }
        
        $parts = explode('|', $apiKeyOpt);
        $region = $parts[2] ?? 'us-east-1'; // Fallback a us-east-1 se non specificata
        
        return 'https://bedrock-runtime.' . $region . '.amazonaws.com/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new AwsTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'aws',
            'Amazon AWS AI',
            ProviderTypeEnum::cloud(),
            'https://console.aws.amazon.com/iam/home?#/security_credentials',
            RequestAuthenticationMethod::apiKey(),
            'Amazon Bedrock / AWS AI',
            dirname(__DIR__, 2) . '/assets/images/aws.svg'
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new AwsProviderAvailability();
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new AwsModelMetadataDirectory();
    }
}
