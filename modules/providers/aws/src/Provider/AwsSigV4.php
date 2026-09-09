<?php
declare(strict_types=1);
namespace Aiutoma\AwsAiProvider\Provider;

if (!defined('ABSPATH')) {
    exit;
}

class AwsSigV4
{
    private string $accessKey;
    private string $secretKey;
    private string $region;
    private string $service;
    private string $algorithm = 'AWS4-HMAC-SHA256';

    public function __construct(string $accessKey, string $secretKey, string $region, string $service = 'bedrock')
    {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region;
        $this->service = $service;
    }

    public function signRequest(string $method, string $url, array $headers, string $payload = ''): array
    {
        $parsedUrl = wp_parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        $path = $parsedUrl['path'] ?? '/';
        $query = $parsedUrl['query'] ?? '';

        $amzDate = gmdate('Ymd\THis\Z');
        $dateStamp = gmdate('Ymd');

        $headers['Host'] = $host;
        $headers['X-Amz-Date'] = $amzDate;

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeaders = '';
        
        foreach ($headers as $k => $v) {
            $kLower = strtolower($k);
            $canonicalHeaders .= $kLower . ':' . trim((string)$v) . "\n";
            $signedHeaders .= $kLower . ';';
        }
        $signedHeaders = rtrim($signedHeaders, ';');

        $payloadHash = hash('sha256', $payload);

        $canonicalRequest = "$method\n$path\n$query\n$canonicalHeaders\n$signedHeaders\n$payloadHash";

        $credentialScope = "$dateStamp/{$this->region}/{$this->service}/aws4_request";
        $stringToSign = "{$this->algorithm}\n$amzDate\n$credentialScope\n" . hash('sha256', $canonicalRequest);

        $signingKey = $this->getSignatureKey($this->secretKey, $dateStamp, $this->region, $this->service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "{$this->algorithm} Credential={$this->accessKey}/$credentialScope, SignedHeaders=$signedHeaders, Signature=$signature";
        $headers['Authorization'] = $authorizationHeader;

        return $headers;
    }

    private function getSignatureKey(string $key, string $dateStamp, string $regionName, string $serviceName): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $key, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
