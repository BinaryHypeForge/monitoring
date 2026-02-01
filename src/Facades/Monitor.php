<?php

namespace BinaryHype\Monitoring\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null captureException(\Throwable $exception, array $context = [])
 * @method static string|null captureMessage(string $message, string $level = 'info', array $context = [])
 * @method static \BinaryHype\Monitoring\Monitor setUser(array $user)
 * @method static array getUser()
 * @method static \BinaryHype\Monitoring\Monitor setContext(string $key, array $data)
 * @method static array getContext()
 * @method static \BinaryHype\Monitoring\Monitor setTags(array $tags)
 * @method static array getTags()
 * @method static void flush()
 * @method static bool isEnabled()
 *
 * @see \BinaryHype\Monitoring\Monitor
 */
class Monitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'monitor';
    }
}
