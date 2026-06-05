<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string version()
 * @method static string path()
 * @method static bool isEnabled()
 * @method static bool routesEnabled()
 *
 * @see \Marmol89\Cauce\Cauce
 */
class Cauce extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Marmol89\Cauce\Cauce::class;
    }
}
