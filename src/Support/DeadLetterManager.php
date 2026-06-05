<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Support;

use Illuminate\Support\Facades\Queue;

class DeadLetterManager
{
    public static function shouldSend(): bool
    {
        return (bool) config('cauce.dead_letter.enabled', false);
    }

    public static function send(object $row): void
    {
        $payload = $row->payload;

        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        if (! is_array($payload) || empty($payload['data']['commandName'])) {
            return;
        }

        $connection = config('cauce.dead_letter.connection');
        $queue = config('cauce.dead_letter.queue', 'dead-letter');

        $payload['cauce_dead_letter'] = true;
        $payload['cauce_original_id'] = $row->id ?? null;
        $payload['cauce_failed_at'] = now()->toIso8601String();
        $payload['cauce_exception'] = $row->exception ?? null;

        Queue::connection($connection)
            ->pushRaw(json_encode($payload, JSON_UNESCAPED_UNICODE), $queue);
    }
}
