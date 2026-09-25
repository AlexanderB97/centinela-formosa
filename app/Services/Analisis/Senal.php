<?php

namespace App\Services\Analisis;

/**
 * A deterministic signal found in the content. The weight feeds the risk level;
 * weight 0 signals are informative only.
 */
final readonly class Senal
{
    public function __construct(
        public string $razon,
        public int $peso,
    ) {}
}
