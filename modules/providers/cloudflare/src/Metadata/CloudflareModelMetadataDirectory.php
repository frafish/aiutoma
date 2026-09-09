<?php
declare(strict_types=1);
namespace Aiutoma\CloudflareAiProvider\Metadata;

if ( ! defined( 'ABSPATH' ) ) exit;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use Aiutoma\CloudflareAiProvider\Provider\CloudflareProvider;

/**
 * Class for the Cloudflare model metadata directory.
 *
 * @since 1.0.0
 */
class CloudflareModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
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
        
        $url = 'https://api.cloudflare.com/client/v4/accounts/' . $accountId . '/ai/models/search';

        return new Request(
            $method,
            $url,
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        $responseData = $response->getData();
        if (!isset($responseData['result']) || empty($responseData['result'])) {
            throw ResponseException::fromMissingData('Cloudflare', 'result');
        }

        $baseTextOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            // Function calling may vary by model but adding support here
            new SupportedOption(OptionEnum::functionDeclarations()),
        ];

        $modelsData = (array) $responseData['result'];

        $models = [];

        foreach ($modelsData as $modelData) {
            $modelId = $modelData['name'];

            // Skip non-text models if possible, but cloudflare v1/models might just list supported chat models.
            // Actually, we'll just include them all and set basic text capabilities.
            if (str_contains($modelId, 'whisper') || str_contains($modelId, 'text-to-image') || str_contains($modelId, 'translation')) {
                continue;
            }

            $options = $baseTextOptions;
            
            $inputModalities = [ModalityEnum::text()];
            if (str_contains($modelId, 'vision') || str_contains($modelId, 'llava') || str_contains($modelId, 'qwen-vl')) {
                $inputModalities[] = ModalityEnum::image();
            }
            $options[] = new SupportedOption(OptionEnum::inputModalities(), [$inputModalities]);

            $displayName = str_replace('@cf/', '', $modelId);
            $displayName = str_replace(['-', '/'], ' ', $displayName);
            $displayName = ucwords($displayName);

            $models[] = new ModelMetadata(
                $modelId,
                $displayName,
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $options
            );
        }

        return $models;
    }
}
