<?php

namespace App\Services\Analisis;

final readonly class VeredictoVirusTotal
{
    public function __construct(
        public bool $encontrado,
        public int $maliciosos = 0,
        public int $sospechosos = 0,
    ) {}

    public static function noEncontrado(): self
    {
        return new self(encontrado: false);
    }
}
