<?php

namespace App\Services\Analisis;

use App\Enums\NivelRiesgo;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Result of RiskAnalyzer::analizar(). toArray() returns the exact
 * POST /analizar contract.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ResultadoAnalisis implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $razones
     */
    public function __construct(
        public NivelRiesgo $nivel,
        public array $razones,
        public string $explicacion,
        public bool $explicacionGeneradaPorIa,
    ) {}

    /**
     * @return array{nivel: string, razones: list<string>, explicacion: string, explicacion_generada_por_ia: bool}
     */
    public function toArray(): array
    {
        return [
            'nivel' => $this->nivel->value,
            'razones' => $this->razones,
            'explicacion' => $this->explicacion,
            'explicacion_generada_por_ia' => $this->explicacionGeneradaPorIa,
        ];
    }

    /**
     * @return array{nivel: string, razones: list<string>, explicacion: string, explicacion_generada_por_ia: bool}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
