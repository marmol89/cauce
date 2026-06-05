<?php

declare(strict_types=1);

namespace Marmol89\Cauce;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Marmol89\Cauce\Console\AlertCommand;
use Marmol89\Cauce\Console\ClearCommand;
use Marmol89\Cauce\Console\DlqReplayCommand;
use Marmol89\Cauce\Console\InstallCommand;
use Marmol89\Cauce\Console\PruneCommand;
use Marmol89\Cauce\Console\RetryCommand;
use Marmol89\Cauce\Console\StatusCommand;
use Marmol89\Cauce\Contracts\JobRepository;
use Marmol89\Cauce\Contracts\MetricsRepository;
use Marmol89\Cauce\Http\Livewire\Dashboard;
use Marmol89\Cauce\Http\Livewire\FailedJobsTable;
use Marmol89\Cauce\Http\Livewire\JobsTable;
use Marmol89\Cauce\Http\Livewire\MetricsChart;
use Marmol89\Cauce\Http\Livewire\QueueStats;
use Marmol89\Cauce\Listeners\RecordJobExceptionOccurred;
use Marmol89\Cauce\Listeners\RecordJobFailed;
use Marmol89\Cauce\Listeners\RecordJobProcessed;
use Marmol89\Cauce\Listeners\RecordJobProcessing;
use Marmol89\Cauce\Listeners\RecordJobQueued;
use Marmol89\Cauce\Repositories\DatabaseJobRepository;
use Marmol89\Cauce\Repositories\DatabaseMetricsRepository;
use Marmol89\Cauce\Retry\RetryManager;
use Marmol89\Cauce\Support\AlertManager;
use Marmol89\Cauce\Support\ConfigValidator;
use Marmol89\Cauce\Support\JobPayloadExtractor;

class CauceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cauce.php',
            'cauce',
        );

        $this->app->singleton(Cauce::class, function (Application $app): Cauce {
            return new Cauce($app);
        });

        $this->app->alias(Cauce::class, 'cauce');

        $this->app->singleton(ConfigValidator::class);

        $this->app->singleton(JobPayloadExtractor::class);

        $this->app->singleton(AlertManager::class);

        $this->app->singleton(RetryManager::class, function (Application $app): RetryManager {
            return new RetryManager($app);
        });

        $this->app->singleton(JobRepository::class, function (Application $app): JobRepository {
            return new DatabaseJobRepository(
                $this->resolveConnection($app),
                'cauce_jobs',
            );
        });

        $this->app->singleton(MetricsRepository::class, function (Application $app): MetricsRepository {
            return new DatabaseMetricsRepository(
                $this->resolveConnection($app),
                'cauce_metrics',
            );
        });
    }

    public function boot(): void
    {
        $this->bootConfigValidation();
        $this->bootPublishing();
        $this->bootRoutes();
        $this->bootViews();
        $this->bootCommands();
        $this->bootEventListeners();
        $this->bootGate();
    }

    public function provides(): array
    {
        return [
            Cauce::class,
            'cauce',
        ];
    }

    protected function bootConfigValidation(): void
    {
        $validator = $this->app->make(ConfigValidator::class);

        $validator->validate();
    }

    protected function bootPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/cauce.php' => $this->app->configPath('cauce.php'),
        ], 'cauce-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'cauce-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/cauce'),
        ], 'cauce-views');

        $this->publishes([
            __DIR__ . '/../resources/css' => $this->app->publicPath('vendor/cauce'),
        ], 'cauce-assets');

        $this->publishes([
            __DIR__ . '/../tailwind.config.js' => $this->app->basePath('tailwind.cauce.js'),
        ], 'cauce-tailwind');
    }

    protected function bootRoutes(): void
    {
        if (! $this->app['config']->get('cauce.enabled', true)) {
            return;
        }

        Route::group([
            'domain' => $this->app['config']->get('cauce.domain'),
            'prefix' => $this->app['config']->get('cauce.path', 'cauce'),
            'middleware' => $this->app['config']->get('cauce.middleware', ['web']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });

        Route::group([
            'domain' => $this->app['config']->get('cauce.domain'),
            'prefix' => $this->app['config']->get('cauce.path', 'cauce') . '/api/v1',
            'middleware' => ['api', \Marmol89\Cauce\Http\Middleware\Authorize::class, 'throttle:120,1'],
        ], function (): void {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });

        Route::group([
            'domain' => $this->app['config']->get('cauce.domain'),
            'prefix' => $this->app['config']->get('cauce.path', 'cauce') . '/api',
            'middleware' => ['api', 'throttle:30,1'],
        ], function (): void {
            Route::get('health', [\Marmol89\Cauce\Http\Controllers\ApiController::class, 'health']);
        });
    }

    protected function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cauce');

        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\LivewireManager::class)) {
            return;
        }

        $manager = $this->app->make(\Livewire\LivewireManager::class);

        $manager->component('cauce-dashboard', Dashboard::class);
        $manager->component('cauce-jobs-table', JobsTable::class);
        $manager->component('cauce-failed-jobs-table', FailedJobsTable::class);
        $manager->component('cauce-metrics-chart', MetricsChart::class);
        $manager->component('cauce-queue-stats', QueueStats::class);
    }

    protected function bootCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            AlertCommand::class,
            ClearCommand::class,
            DlqReplayCommand::class,
            InstallCommand::class,
            PruneCommand::class,
            RetryCommand::class,
            StatusCommand::class,
        ]);
    }

    protected function bootEventListeners(): void
    {
        // Listeners are registered once at boot and remain active for the
        // entire application lifecycle. Toggling cauce.enabled at runtime
        // does not remove already-registered listeners; a full restart is
        // required.
        if (! $this->app['config']->get('cauce.enabled', true)) {
            return;
        }

        Event::listen(JobQueued::class, RecordJobQueued::class);
        Event::listen(JobProcessing::class, RecordJobProcessing::class);
        Event::listen(JobProcessed::class, RecordJobProcessed::class);
        Event::listen(JobFailed::class, RecordJobFailed::class);
        Event::listen(JobExceptionOccurred::class, RecordJobExceptionOccurred::class);
    }

    protected function bootGate(): void
    {
        $allowed = function ($user = null): bool {
            $envAllowed = $this->app->environment('local', 'testing', 'staging', 'development')
                || $this->app['config']->get('cauce.allow_production', false);

            if (! $envAllowed) {
                return false;
            }

            if ($this->app['config']->get('cauce.require_authentication', false) && $user === null) {
                return false;
            }

            $callback = $this->app['config']->get('cauce.authorization_callback');

            if ($user !== null && is_callable($callback)) {
                return (bool) $callback($user);
            }

            return true;
        };

        Gate::define('viewCauce', $allowed);

        Gate::define('mutateCauce', function ($user = null) use ($allowed): bool {
            if (! $allowed($user)) {
                return false;
            }

            if ($this->app->environment('local', 'testing', 'staging', 'development')) {
                return true;
            }

            return $this->app['config']->get('cauce.allow_production_mutate', false) !== false;
        });
    }

    protected function resolveConnection(Application $app): ConnectionInterface
    {
        $name = $app['config']->get('cauce.storage.database.connection');

        /** @var ConnectionResolverInterface $resolver */
        $resolver = $app['db'];

        return $resolver->connection($name);
    }

    /**
     * Cleanup after the request lifecycle.
     */
    public function terminate(): void
    {
        // Reserved for future cleanup: flush caches, close connections, etc.
    }
}
