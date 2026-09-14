<?php
declare(strict_types=1);
namespace Aiutoma\AwsAiProvider\Provider;

if (!defined('ABSPATH')) {
    exit;
}

use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

class AwsBedrockTransporter implements HttpTransporterInterface
{
    private HttpTransporterInterface $innerTransporter;

    public function __construct(HttpTransporterInterface $innerTransporter)
    {
        $this->innerTransporter = $innerTransporter;
    }

    public function sendRequest(Request $request, ?RequestOptions $options = null): Response
    {
        $data = $request->getData();
        if (is_array($data)) {
            // Translate OpenAI payload to Bedrock Converse API format
            $bedrockPayload = $this->translateRequest($data);
            
            // Re-create Request with translated payload
            $request = new Request(
                $request->getMethod(),
                $request->getUrl(), // This url is the old /chat/completions url, we must fix it below
                $request->getHeaders(),
                $bedrockPayload,
                $request->getOptions()
            );
        }

        // Apply AWS SigV4
        $request = $this->applySigV4($request);

        // Send via inner transporter
        $response = $this->innerTransporter->sendRequest($request, $options);

        // Translate Bedrock response back to OpenAI format
        $responseBody = $response->getBody();
        if (is_array($responseBody) && isset($responseBody['output'])) {
            $openAiBody = $this->translateResponse($responseBody);
            
            // Wait, Response doesn't have setters. We have to create a new Response.
            $response = new Response(
                $response->getStatusCode(),
                $response->getHeaders(),
                wp_json_encode($openAiBody),
                $openAiBody
            );
        }

        return $response;
    }

    private function translateRequest(array $data): array
    {
        $bedrockMessages = [];
        $system = [];
        
        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                if ($msg['role'] === 'system') {
                    // Bedrock system prompt is a list of objects
                    $system[] = ['text' => $msg['content']];
                } elseif ($msg['role'] === 'tool') {
                    // OpenAI tool response -> Bedrock toolResult (wrapped in user role)
                    $bedrockMessages[] = [
                        'role' => 'user',
                        'content' => [
                            [
                                'toolResult' => [
                                    'toolUseId' => $msg['tool_call_id'],
                                    'content' => [
                                        ['text' => is_string($msg['content']) ? $msg['content'] : wp_json_encode($msg['content'])]
                                    ],
                                    'status' => 'success'
                                ]
                            ]
                        ]
                    ];
                } else {
                    $contentBlocks = [];
                    if (!empty($msg['content'])) {
                        $contentBlocks[] = ['text' => is_string($msg['content']) ? $msg['content'] : wp_json_encode($msg['content'])];
                    }
                    
                    if (!empty($msg['tool_calls'])) {
                        foreach ($msg['tool_calls'] as $tc) {
                            if ($tc['type'] === 'function') {
                                $args = is_string($tc['function']['arguments']) ? json_decode($tc['function']['arguments'], true) : $tc['function']['arguments'];
                                $contentBlocks[] = [
                                    'toolUse' => [
                                        'toolUseId' => $tc['id'],
                                        'name' => $tc['function']['name'],
                                        'input' => $args ?: new \stdClass()
                                    ]
                                ];
                            }
                        }
                    }
                    
                    // If content is empty and no tool calls (shouldn't happen), add empty text to avoid validation error
                    if (empty($contentBlocks)) {
                        $contentBlocks[] = ['text' => ''];
                    }

                    $bedrockMessages[] = [
                        'role' => $msg['role'] === 'assistant' ? 'assistant' : 'user',
                        'content' => $contentBlocks
                    ];
                }
            }
        }
        
        $conversePayload = [
            'messages' => $bedrockMessages
        ];
        
        if (!empty($system)) {
            $conversePayload['system'] = $system;
        }
        
        $inferenceConfig = [];
        if (isset($data['temperature'])) $inferenceConfig['temperature'] = (float)$data['temperature'];
        if (isset($data['max_tokens'])) $inferenceConfig['maxTokens'] = (int)$data['max_tokens'];
        if (isset($data['top_p'])) $inferenceConfig['topP'] = (float)$data['top_p'];
        
        if (!empty($inferenceConfig)) {
            $conversePayload['inferenceConfig'] = $inferenceConfig;
        }

        if (!empty($data['tools'])) {
            $bedrockTools = [];
            foreach ($data['tools'] as $tool) {
                if ($tool['type'] === 'function') {
                    $fn = $tool['function'];
                    $bedrockTools[] = [
                        'toolSpec' => [
                            'name' => $fn['name'],
                            'description' => $fn['description'] ?? '',
                            'inputSchema' => [
                                'json' => $fn['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()]
                            ]
                        ]
                    ];
                }
            }
            if (!empty($bedrockTools)) {
                $conversePayload['toolConfig'] = [
                    'tools' => $bedrockTools
                ];
            }
        }
        
        return $conversePayload;
    }

    private function translateResponse(array $bedrockResponse): array
    {
        $content = '';
        $toolCalls = [];
        
        if (isset($bedrockResponse['output']['message']['content'])) {
            foreach ($bedrockResponse['output']['message']['content'] as $block) {
                if (isset($block['text'])) {
                    $content .= $block['text'];
                } elseif (isset($block['toolUse'])) {
                    $toolCalls[] = [
                        'id' => $block['toolUse']['toolUseId'],
                        'type' => 'function',
                        'function' => [
                            'name' => $block['toolUse']['name'],
                            'arguments' => wp_json_encode($block['toolUse']['input'] ?? new \stdClass())
                        ]
                    ];
                }
            }
        }

        $stopReason = $bedrockResponse['stopReason'] ?? 'stop';
        if ($stopReason === 'end_turn') {
            $stopReason = 'stop';
        } elseif ($stopReason === 'tool_use') {
            $stopReason = 'tool_calls';
        }

        $messageData = [
            'role' => 'assistant',
            'content' => $content
        ];
        
        if (!empty($toolCalls)) {
            $messageData['tool_calls'] = $toolCalls;
        }

        $openAiResponse = [
            'choices' => [
                [
                    'message' => $messageData,
                    'finish_reason' => $stopReason
                ]
            ],
            'usage' => [
                'prompt_tokens' => $bedrockResponse['usage']['inputTokens'] ?? 0,
                'completion_tokens' => $bedrockResponse['usage']['outputTokens'] ?? 0,
                'total_tokens' => $bedrockResponse['usage']['totalTokens'] ?? 0,
            ]
        ];

        return $openAiResponse;
    }

    private function applySigV4(Request $request): Request
    {
        $apiKeyOpt = '';
        foreach ($request->getHeaders() as $k => $v) {
            if (strtolower($k) === 'authorization') {
                $val = is_array($v) ? ($v[0] ?? '') : $v;
                if (stripos((string)$val, 'Bearer ') === 0) {
                    $apiKeyOpt = trim(substr((string)$val, 7));
                    break;
                }
            }
        }
        if (empty($apiKeyOpt) && class_exists('\WordPress\AiClient\AiClient')) {
            try {
                $auth = \WordPress\AiClient\AiClient::defaultRegistry()->getProviderRequestAuthentication('aws');
                if ($auth instanceof \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication) {
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
        $accessKey = $parts[0] ?? '';
        $secretKey = $parts[1] ?? '';
        $region = $parts[2] ?? 'us-east-1';

        $payloadData = $request->getData();
        $payloadStr = '';
        if ($payloadData !== null) {
            $payloadStr = is_string($payloadData) ? $payloadData : wp_json_encode($payloadData);
        }

        // Clean headers (remove Bearer auth)
        $cleanHeaders = [];
        foreach ($request->getHeaders() as $k => $v) {
            if (strtolower($k) !== 'authorization') {
                $cleanHeaders[$k] = $v;
            }
        }
        $cleanHeaders['Content-Type'] = 'application/json';
        $cleanHeaders['Accept'] = 'application/json';

        // Extract Model ID from the payload (OpenAI format uses 'model')
        $modelId = is_array($payloadData) && isset($payloadData['model']) ? $payloadData['model'] : 'anthropic.claude-3-sonnet-20240229-v1:0';
        
        // Build the correct Converse API URL
        // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- AWS Bedrock API endpoint.
        $url = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$modelId}/converse";

        if (!empty($accessKey) && !empty($secretKey)) {
            $sigV4 = new AwsSigV4($accessKey, $secretKey, $region, 'bedrock');
            $cleanHeaders = $sigV4->signRequest($request->getMethod()->value, $url, $cleanHeaders, $payloadStr);
        }

        return new Request(
            $request->getMethod(),
            $url,
            $cleanHeaders,
            $payloadData,
            $request->getOptions()
        );
    }
}
