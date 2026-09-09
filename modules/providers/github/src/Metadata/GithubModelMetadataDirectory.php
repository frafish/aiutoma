<?php
declare(strict_types=1);
namespace Aiutoma\GithubAiProvider\Metadata;
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
use Aiutoma\GithubAiProvider\Provider\GithubProvider;

/**
 * Class for the Github model metadata directory.
 *
 * @since 1.0.0
 */
class GithubModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
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
        return new Request(
            $method,
            GithubProvider::url($path),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function sendListModelsRequest(): array
    {
        // Ensure authentication check
        $this->getRequestAuthentication();

        $baseTextOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::functionDeclarations()),
        ];

        $hardcodedModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'Meta-Llama-3.1-405B-Instruct',
            'Meta-Llama-3.1-8B-Instruct',
            'Llama-3.3-70B-Instruct',
            'AI21-Jamba-1.5-Large',
            'AI21-Jamba-1.5-Mini',
            'Cohere-command-r',
            'Cohere-command-r-plus',
            'mistral-large-2411',
            'Mistral-Nemo',
            'mistral-small-2503'
        ];

        $modelsList = $hardcodedModels;

        // Try to fetch dynamic list from the Github catalog API
        try {
            $httpTransporter = $this->getHttpTransporter();
            $request = new Request(
                HttpMethodEnum::GET(),
                'https://models.github.ai/catalog/models',
                [
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2024-03-10'
                ]
            );
            $request = $this->getRequestAuthentication()->authenticateRequest($request);
            $response = $httpTransporter->send($request);
            
            $apiModels = json_decode($response->getBody(), true);
            if (is_array($apiModels) && !empty($apiModels)) {
                $modelsList = [];
                foreach ($apiModels as $apiModel) {
                    // Filter out models that don't support text output (like embeddings only)
                    if (isset($apiModel['supported_output_modalities']) && in_array('text', $apiModel['supported_output_modalities'], true)) {
                        $urlParts = explode('/', $apiModel['html_url']);
                        $modelsList[] = end($urlParts);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fallback to the hardcoded list if the catalog API is unreachable or rate limited
        }

        $modelMetadataMap = [];
        foreach ($modelsList as $modelId) {
            $modelMetadataMap[$modelId] = new ModelMetadata(
                $modelId,
                $modelId,
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $baseTextOptions
            );
        }

        return $modelMetadataMap;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        return [];
    }
}
