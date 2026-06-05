<?php

declare(strict_types=1);

namespace Marmol89\Cauce;

use Illuminate\Contracts\Foundation\Application;

class Cauce
{
    public function __construct(
        protected Application $app,
    ) {
    }

    public function version(): string
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            return (string) (\Composer\InstalledVersions::getVersion('marmol89/cauce') ?: '0.2.0');
        }

        return '0.2.0';
    }

    public function path(): string
    {
        return (string) $this->app['config']->get('cauce.path', 'cauce');
    }

    public function isEnabled(): bool
    {
        return (bool) $this->app['config']->get('cauce.enabled', true);
    }

    public function routesEnabled(): bool
    {
        return $this->isEnabled() && $this->app['config']->get('cauce.path') !== null;
    }
}
