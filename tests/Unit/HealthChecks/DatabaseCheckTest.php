<?php

use BinaryHype\Monitoring\HealthChecks\DatabaseCheck;
use Illuminate\Support\Facades\DB;

describe('DatabaseCheck', function () {
    it('has the correct name', function () {
        $check = new DatabaseCheck;

        expect($check->name())->toBe('database');
    });

    it('returns true when database is connected', function () {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andReturn(new \stdClass);

        $check = new DatabaseCheck;

        expect($check->check())->toBeTrue();
        expect($check->message())->toBeNull();
    });

    it('returns false when database connection fails', function () {
        DB::shouldReceive('connection->getPdo')
            ->once()
            ->andThrow(new \Exception('Connection refused'));

        $check = new DatabaseCheck;

        expect($check->check())->toBeFalse();
        expect($check->message())->toBe('Connection refused');
    });
});
