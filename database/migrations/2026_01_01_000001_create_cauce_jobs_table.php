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

        Schema::connection($connection)->create('cauce_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('uuid')->index();
            $table->string('connection')->index();
            $table->string('queue')->index();
            $table->string('name')->index();
            $table->string('status')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('runtime_ms')->nullable();
            $table->json('payload')->nullable();
            $table->text('exception')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['connection', 'queue', 'status']);
            $table->index('finished_at');
        });
    }

    public function down(): void
    {
        $connection = config('cauce.storage.database.connection') ?? config('database.default');

        Schema::connection($connection)->dropIfExists('cauce_jobs');
    }
};
