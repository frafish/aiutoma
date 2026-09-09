<?php
declare(strict_types=1);
namespace Aiutoma\GithubAiProvider\Models;
if ( ! defined( 'ABSPATH' ) ) exit;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use Aiutoma\GithubAiProvider\Provider\GithubProvider;

/**
 * Class for a Github text generation model.
 *
 * @since 1.0.0
 */
class GithubTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
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
            $data,
            $this->getRequestOptions()
        );
    }
}
