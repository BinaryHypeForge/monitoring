<?php

use BinaryHype\Monitoring\Monitor;

beforeEach(function () {
    $this->monitor = new Monitor([
        'api_key' => 'test-key',
        'endpoint' => 'https://test.example.com/api/v1',
        'enabled' => true,
        'environment' => 'testing',
        'ignored_environments' => [],
        'sample_rate' => 1.0,
    ]);
});

describe('Monitor Configuration', function () {
    it('is enabled with valid configuration', function () {
        expect($this->monitor->isEnabled())->toBeTrue();
    });

    it('is disabled when enabled is false', function () {
        $monitor = new Monitor([
            'api_key' => 'test-key',
            'enabled' => false,
        ]);

        expect($monitor->isEnabled())->toBeFalse();
    });

    it('is disabled when api key is missing', function () {
        $monitor = new Monitor([
            'enabled' => true,
        ]);

        expect($monitor->isEnabled())->toBeFalse();
    });

    it('is disabled for ignored environments', function () {
        $monitor = new Monitor([
            'api_key' => 'test-key',
            'enabled' => true,
            'environment' => 'local',
            'ignored_environments' => ['local', 'testing'],
        ]);

        expect($monitor->isEnabled())->toBeFalse();
    });
});

describe('Context Management', function () {
    it('can set and get user context', function () {
        $user = ['id' => 1, 'email' => 'test@example.com'];

        $this->monitor->setUser($user);

        expect($this->monitor->getUser())->toBe($user);
    });

    it('can set and get custom context', function () {
        $this->monitor->setContext('order', ['id' => 123, 'total' => 99.99]);

        expect($this->monitor->getContext())->toHaveKey('order');
        expect($this->monitor->getContext()['order'])->toBe(['id' => 123, 'total' => 99.99]);
    });

    it('can set and get tags', function () {
        $this->monitor->setTags(['feature' => 'checkout', 'version' => '1.0.0']);

        expect($this->monitor->getTags())->toBe(['feature' => 'checkout', 'version' => '1.0.0']);
    });

    it('merges tags when set multiple times', function () {
        $this->monitor->setTags(['feature' => 'checkout']);
        $this->monitor->setTags(['version' => '1.0.0']);

        expect($this->monitor->getTags())->toBe(['feature' => 'checkout', 'version' => '1.0.0']);
    });
});

describe('Sampling', function () {
    it('always samples when rate is 1.0', function () {
        $monitor = new Monitor([
            'api_key' => 'test-key',
            'sample_rate' => 1.0,
        ]);

        $samples = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($monitor->shouldSample()) {
                $samples++;
            }
        }

        expect($samples)->toBe(100);
    });

    it('never samples when rate is 0.0', function () {
        $monitor = new Monitor([
            'api_key' => 'test-key',
            'sample_rate' => 0.0,
        ]);

        $samples = 0;
        for ($i = 0; $i < 100; $i++) {
            if ($monitor->shouldSample()) {
                $samples++;
            }
        }

        expect($samples)->toBe(0);
    });

    it('samples approximately at the configured rate', function () {
        $monitor = new Monitor([
            'api_key' => 'test-key',
            'sample_rate' => 0.5,
        ]);

        $samples = 0;
        $iterations = 1000;

        for ($i = 0; $i < $iterations; $i++) {
            if ($monitor->shouldSample()) {
                $samples++;
            }
        }

        $rate = $samples / $iterations;

        expect($rate)->toBeGreaterThan(0.4);
        expect($rate)->toBeLessThan(0.6);
    });
});

describe('Exception Handling', function () {
    it('returns null when disabled', function () {
        $monitor = new Monitor([
            'enabled' => false,
        ]);

        $result = $monitor->captureException(new \Exception('Test'));

        expect($result)->toBeNull();
    });

    it('returns null for ignored exceptions', function () {
        $monitor = new Monitor([
            'api_key' => 'test-key',
            'enabled' => true,
            'ignored_environments' => [],
            'ignored_exceptions' => [\InvalidArgumentException::class],
        ]);

        $result = $monitor->captureException(new \InvalidArgumentException('Test'));

        expect($result)->toBeNull();
    });
});
