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

        Schema::connection($connection)->create('cauce_circuit_breakers', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('state')->default('closed');
            $table->unsignedSmallInteger('failures')->default(0);
            $table->unsignedSmallInteger('threshold');
            $table->unsignedInteger('cooldown');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('half_open_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $connection = config('cauce.storage.database.connection') ?? config('database.default');

        Schema::connection($connection)->dropIfExists('cauce_circuit_breakers');
    }
};
