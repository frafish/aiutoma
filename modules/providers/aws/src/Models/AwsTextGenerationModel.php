<?php
declare(strict_types=1);
namespace Aiutoma\AwsAiProvider\Models;
if ( ! defined( 'ABSPATH' ) ) exit;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use Aiutoma\AwsAiProvider\Provider\AwsProvider;
use Aiutoma\AwsAiProvider\Provider\AwsSigV4;

/**
 * Class for an AWS text generation model.
 *
 * @since 1.0.0
 */
class AwsTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    private ?\WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface $awsTransporter = null;

    /**
     * Override getHttpTransporter to inject our Bedrock SigV4 Transporter Decorator.
     *
     * @return \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface
     */
    public function getHttpTransporter(): \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface
    {
        if ($this->awsTransporter === null) {
            $inner = parent::getHttpTransporter();
            $this->awsTransporter = new \Aiutoma\AwsAiProvider\Provider\AwsBedrockTransporter($inner);
        }
        return $this->awsTransporter;
    }

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
            AwsProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
