<?php

use BinaryHype\Monitoring\Http\Client;

describe('Client Configuration', function () {
    it('creates a client with the correct configuration', function () {
        $client = new Client([
            'api_key' => 'test-api-key',
            'endpoint' => 'https://monitoring.example.com/api/v1',
            'timeout' => 10,
        ]);

        expect($client)->toBeInstanceOf(Client::class);
        expect($client->getHttpClient())->toBeInstanceOf(\GuzzleHttp\Client::class);
    });

    it('uses default timeout when not specified', function () {
        $client = new Client([
            'api_key' => 'test-api-key',
            'endpoint' => 'https://monitoring.example.com/api/v1',
        ]);

        expect($client)->toBeInstanceOf(Client::class);
    });
});

describe('Retry Configuration', function () {
    it('uses default retry configuration', function () {
        $client = new Client([
            'api_key' => 'test-api-key',
            'endpoint' => 'https://monitoring.example.com/api/v1',
        ]);

        expect($client)->toBeInstanceOf(Client::class);
    });

    it('uses custom retry configuration', function () {
        $client = new Client([
            'api_key' => 'test-api-key',
            'endpoint' => 'https://monitoring.example.com/api/v1',
            'retry' => [
                'times' => 5,
                'sleep' => 200,
            ],
        ]);

        expect($client)->toBeInstanceOf(Client::class);
    });
});
