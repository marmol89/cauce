<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = config('cauce.storage.database.connection') ?? config('database.default');

        Schema::connection($connection)->create('cauce_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('connection')->index();
            $table->string('queue')->index();
            $table->timestamp('minute')->index();
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->double('runtime_sum_ms')->default(0);
            $table->unsignedInteger('runtime_count')->default(0);
            $table->double('throughput')->default(0);
            $table->timestamps();

            $table->unique(['connection', 'queue', 'minute']);
        });
    }

    public function down(): void
    {
        $connection = config('cauce.storage.database.connection') ?? config('database.default');

        Schema::connection($connection)->dropIfExists('cauce_metrics');
    }
};
