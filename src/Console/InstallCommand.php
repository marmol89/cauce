<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'cauce:install
                            {--no-migrate : Skip running migrations}
                            {--force : Overwrite existing files}';

    protected $description = 'Install the Cauce dashboard, publish assets and run migrations.';

    public function handle(Filesystem $files): int
    {
        $this->components->info('Installing Cauce...');

        $this->publishConfig($files);
        $this->publishMigrations($files);
        $this->publishViews($files);
        $this->runMigrations();
        $this->displayOutro();

        return self::SUCCESS;
    }

    protected function publishConfig(Filesystem $files): void
    {
        $configPath = config_path('cauce.php');

        if ($files->exists($configPath) && ! $this->option('force')) {
            $this->components->warn("Config file already exists at [$configPath].");
            $this->components->twoColumnDetail(
                'Use --force to overwrite.',
                'Or run: php artisan vendor:publish --tag=cauce-config',
            );

            return;
        }

        $this->call('vendor:publish', [
            '--tag' => 'cauce-config',
            '--force' => $this->option('force'),
        ]);

        $this->components->info('Published config/cauce.php');
    }

    protected function publishMigrations(Filesystem $files): void
    {
        $migrationsPath = database_path('migrations');

        if (! $files->isDirectory($migrationsPath)) {
            $files->makeDirectory($migrationsPath, 0755, true);
        }

        $this->call('vendor:publish', [
            '--tag' => 'cauce-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->components->info('Published database/migrations/cauce_*');
    }

    protected function publishViews(Filesystem $files): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'cauce-views',
            '--force' => $this->option('force'),
        ]);

        $this->components->info('Published resources/views/vendor/cauce');
    }

    protected function runMigrations(): void
    {
        if ($this->option('no-migrate')) {
            $this->components->warn('Skipping migrations (--no-migrate).');

            return;
        }

        if (! $this->components->confirm('Run migrations now?', true)) {
            $this->components->warn('Skipped migrations. Run them later with `php artisan migrate`.');

            return;
        }

        $this->components->task('Running migrations', function () {
            return Artisan::call('migrate') === 0;
        });
    }

    protected function displayOutro(): void
    {
        $this->newLine();
        $this->components->info('Cauce installed successfully.');
        $this->newLine();

        $this->components->twoColumnDetail(
            '<fg=cyan>Dashboard URL</>  /'.config('cauce.path', 'cauce'),
            '<fg=cyan>Documentation</>   https://github.com/marmol89/cauce',
        );

        $this->newLine();
        $this->components->warn('Make sure to register the Gate "viewCauce" in your AuthServiceProvider to control access.');
    }
}
