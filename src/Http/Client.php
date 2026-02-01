<?php

namespace BinaryHype\Monitoring\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

class Client
{
    protected GuzzleClient $httpClient;

    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->httpClient = new GuzzleClient([
            'base_uri' => rtrim($config['endpoint'] ?? '', '/'),
            'timeout' => $config['timeout'] ?? 5,
            'headers' => [
                'Authorization' => 'Bearer '.($config['api_key'] ?? ''),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function sendError(array $payload): ?string
    {
        return $this->send('/errors', $payload);
    }

    public function sendLog(array $payload): ?string
    {
        return $this->send('/logs', $payload);
    }

    public function sendLogs(array $logs): ?string
    {
        return $this->send('/logs', ['logs' => $logs]);
    }

    protected function send(string $endpoint, array $payload): ?string
    {
        $retryTimes = $this->config['retry']['times'] ?? 3;
        $retrySleep = $this->config['retry']['sleep'] ?? 100;

        $lastException = null;

        for ($attempt = 1; $attempt <= $retryTimes; $attempt++) {
            try {
                $response = $this->httpClient->post($endpoint, [
                    RequestOptions::JSON => $payload,
                ]);

                $body = json_decode($response->getBody()->getContents(), true);

                return $body['id'] ?? null;
            } catch (GuzzleException $e) {
                $lastException = $e;

                if ($attempt < $retryTimes) {
                    usleep($retrySleep * 1000);
                }
            }
        }

        if ($lastException !== null) {
            error_log('Monitor: Failed to send report after '.$retryTimes.' attempts: '.$lastException->getMessage());
        }

        return null;
    }

    public function testConnection(): array
    {
        try {
            $response = $this->httpClient->get('/health');

            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'message' => 'Connection successful',
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'status_code' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function getHttpClient(): GuzzleClient
    {
        return $this->httpClient;
    }
}
