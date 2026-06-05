<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Console;

use Illuminate\Console\Command;
use Marmol89\Cauce\Support\AlertManager;

class AlertCommand extends Command
{
	protected $signature = 'cauce:alerts';
	protected $description = 'Check and send Cauce alerts.';

	public function handle(AlertManager $alerts): int
	{
		$alerts->checkFailedJobThreshold();

		if ($this->option('verbose')) {
			$this->info('Cauce alerts checked.');
		}

		return self::SUCCESS;
	}
}
