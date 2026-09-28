<?php

namespace App\Services;

use App\Enums\NivelRiesgo;
use App\Enums\TipoContenido;
use App\Models\Analisis;
use App\Models\CasoConfirmado;
use App\Services\Analisis\Huella;
use App\Services\Analisis\ResultadoAnalisis;
use App\Services\Analisis\VirusTotalNoDisponible;
use InvalidArgumentException;
use Throwable;

/**
 * File scanner by hash only. The SHA-256 is computed in the visitor's browser: the file never
 * reaches this server nor VirusTotal. Only the hash is looked up and stored (as the analysis
 * contenido); never the file name, extension or size.
 */
class EscanerDeArchivos
{
    public const RAZON_CASO_CONFIRMADO = 'Coincide con un archivo ya confirmado como estafa por nuestro equipo.';

    public const RAZON_SIN_COINCIDENCIAS = 'VirusTotal no tiene registros de este archivo.';

    /**
     * Shown as is when VirusTotal does not know the hash: an unknown file is not a safe file.
     */
    public const EXPLICACION_SIN_COINCIDENCIAS = 'No encontramos este archivo en la base de VirusTotal. Esto no garantiza que sea seguro: puede ser un archivo nuevo o armado específicamente para vos. Si no esperabas recibirlo, no lo abras y confirmá con quien te lo mandó por otro medio.';

    public function __construct(
        private readonly VirusTotalClient $virusTotal,
        private readonly GeminiClient $gemini,
    ) {}

    public static function esHuellaValida(string $sha256): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $sha256);
    }

    /**
     * @throws InvalidArgumentException when the hash is not 64 lowercase hex characters.
     * @throws VirusTotalNoDisponible when VirusTotal is needed and cannot be consulted.
     */
    public function analizar(string $sha256): ResultadoAnalisis
    {
        if (! self::esHuellaValida($sha256)) {
            throw new InvalidArgumentException('La huella del archivo no es un SHA-256 válido.');
        }

        [$nivel, $razones] = $this->evaluar($sha256);

        if ($razones === [self::RAZON_SIN_COINCIDENCIAS]) {
            $explicacion = self::EXPLICACION_SIN_COINCIDENCIAS;
            $generadaPorIa = false;
        } else {
            $explicacionIa = $this->gemini->redactarExplicacion($nivel, $razones);
            $explicacion = $explicacionIa ?? self::explicacionDeRespaldo($nivel);
            $generadaPorIa = $explicacionIa !== null;
        }

        return new ResultadoAnalisis(
            nivel: $nivel,
            razones: $razones,
            explicacion: $explicacion,
            explicacionGeneradaPorIa: $generadaPorIa,
            analisisId: $this->guardar($sha256, $nivel, $razones, $explicacion, $generadaPorIa),
        );
    }

    /**
     * @return array{0: NivelRiesgo, 1: list<string>}
     */
    private function evaluar(string $sha256): array
    {
        // A hash confirmed by staff is conclusive: VirusTotal is not consulted.
        if (CasoConfirmado::query()->where('huella', Huella::de($sha256))->exists()) {
            return [NivelRiesgo::Riesgo, [self::RAZON_CASO_CONFIRMADO]];
        }

        $veredicto = $this->virusTotal->consultarArchivo($sha256)
            ?? throw new VirusTotalNoDisponible('No se pudo consultar VirusTotal.');

        return match (true) {
            ! $veredicto->encontrado => [NivelRiesgo::Dudoso, [self::RAZON_SIN_COINCIDENCIAS]],
            $veredicto->maliciosos >= 2 => [NivelRiesgo::Riesgo, ["{$veredicto->maliciosos} servicios de seguridad de VirusTotal marcan el archivo como malicioso."]],
            $veredicto->maliciosos === 1 || $veredicto->sospechosos > 0 => [NivelRiesgo::Dudoso, ['Algún servicio de seguridad de VirusTotal marca el archivo como sospechoso.']],
            default => [NivelRiesgo::Seguro, ['VirusTotal conoce este archivo y ningún servicio de seguridad lo marca como peligroso.']],
        };
    }

    /**
     * File-specific fixed explanation, used when the AI explanation is unavailable.
     */
    public static function explicacionDeRespaldo(NivelRiesgo $nivel): string
    {
        return match ($nivel) {
            NivelRiesgo::Seguro => 'Este archivo ya es conocido y ningún servicio de seguridad lo marca como peligroso. Igual, si no esperabas recibirlo, confirmá con quien te lo mandó antes de abrirlo.',
            NivelRiesgo::Dudoso => 'Hay señales que piden precaución con este archivo. No lo abras hasta confirmar por otro medio que quien te lo mandó es quien dice ser.',
            NivelRiesgo::Riesgo => 'Este archivo tiene señales claras de ser malicioso. No lo abras, borralo y avisá a quien te lo mandó por otro medio, por si le robaron la cuenta.',
        };
    }

    /**
     * @param  list<string>  $razones
     */
    private function guardar(string $sha256, NivelRiesgo $nivel, array $razones, string $explicacion, bool $generadaPorIa): ?int
    {
        try {
            return Analisis::create([
                'tipo' => TipoContenido::Archivo,
                'contenido' => $sha256,
                'nivel' => $nivel,
                'razones' => $razones,
                'explicacion' => $explicacion,
                'explicacion_generada_por_ia' => $generadaPorIa,
            ])->id;
        } catch (Throwable $e) {
            // Failing to log the analysis must not deny the visitor their result.
            report($e);

            return null;
        }
    }
}
