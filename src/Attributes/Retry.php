<?php

declare(strict_types=1);

namespace Marmol89\Cauce\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Retry
{
    public string $strategy;
    public int $max;
    public int $base;
    public int $cap;
    public int $cooldown;
    public int $threshold;
    public ?string $key;

    public function __construct(
        string $strategy,
        int $max = 5,
        int $base = 1,
        int $cap = 300,
        int $cooldown = 60,
        int $threshold = 5,
        ?string $key = null,
    ) {
        $this->strategy = $strategy;
        $this->max = max(1, $max);
        $this->base = max(1, $base);
        $this->cap = max(1, $cap);
        $this->cooldown = max(1, $cooldown);
        $this->threshold = max(1, $threshold);
        $this->key = $key;
    }
}
