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

		Schema::connection($connection)->table('cauce_jobs', function (Blueprint $table): void {
			$table->string('batch_id', 36)->nullable()->after('uuid')->index();
			$table->uuid('chain_id')->nullable()->after('batch_id')->index();
		});
	}

	public function down(): void
	{
		$connection = config('cauce.storage.database.connection') ?? config('database.default');

		Schema::connection($connection)->table('cauce_jobs', function (Blueprint $table): void {
			$table->dropIndex('cauce_jobs_chain_id_index');
			$table->dropIndex('cauce_jobs_batch_id_index');
		});

		Schema::connection($connection)->table('cauce_jobs', function (Blueprint $table): void {
			$table->dropColumn('chain_id');
		});

		Schema::connection($connection)->table('cauce_jobs', function (Blueprint $table): void {
			$table->dropColumn('batch_id');
		});
	}
};
