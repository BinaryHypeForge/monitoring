<?php

use BinaryHype\Monitoring\Facades\Monitor;

describe('Monitor Facade', function () {
    it('can set user context via facade', function () {
        Monitor::setUser(['id' => 1, 'email' => 'test@example.com']);

        expect(Monitor::getUser())->toBe(['id' => 1, 'email' => 'test@example.com']);
    });

    it('can set custom context via facade', function () {
        Monitor::setContext('order', ['id' => 123]);

        expect(Monitor::getContext())->toHaveKey('order');
    });

    it('can set tags via facade', function () {
        Monitor::setTags(['feature' => 'checkout']);

        expect(Monitor::getTags())->toHaveKey('feature');
    });

    it('reports enabled status', function () {
        expect(Monitor::isEnabled())->toBeTrue();
    });
});
