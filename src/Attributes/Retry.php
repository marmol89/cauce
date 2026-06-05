<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Retry
{
    public function __construct(
        public string $strategy,
        public int $max = 5,
        public int $base = 1,
        public int $cap = 300,
        public int $cooldown = 60,
        public int $threshold = 5,
        public ?string $key = null,
    ) {
    }
}
