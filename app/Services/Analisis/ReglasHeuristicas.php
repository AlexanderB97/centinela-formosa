<?php

namespace App\Services\Analisis;

use Illuminate\Support\Str;

/**
 * Own deterministic rules over the text and the links it contains.
 */
final class ReglasHeuristicas
{
    /**
     * Text rules: reason => [weight, regex over the lowercased ASCII text].
     */
    private const REGLAS_DE_TEXTO = [
        'Pide datos sensibles como claves o códigos.' => [2, '~\b(clave|contrasena|password|pin|token|cvv|cvc|codigo de (verificacion|seguridad)|numero de (tu )?tarjeta|datos de (tu )?tarjeta)\b~'],
        'Pide verificar, reactivar o actualizar una cuenta.' => [2, '~\b(verifica(r)? (tu )?cuenta|reactiva(r)? (tu )?cuenta|actualiza(r)? (tus )?datos|confirma(r)? (tus )?datos|cuenta (fue )?(suspendida|bloqueada)|fue (suspendida|bloqueada)|sera (suspendida|bloqueada))\b~'],
        'Menciona una entidad bancaria o datos de cuenta.' => [1, '~\b(banco|cbu|cvu|alias|homebanking|home banking|tarjeta de (credito|debito))\b~'],
        'Genera sensación de urgencia para que actúes rápido.' => [1, '~\b(urgente|inmediato|inmediatamente|ultimo aviso|dentro de las 24|en las proximas (24 )?horas|hoy mismo|de lo contrario)\b~'],
        'Promete un premio, sorteo o beneficio.' => [1, '~\b(ganaste|ganador|premio|sorteo|reintegro|regalo|beneficio exclusivo)\b~'],
    ];

    private const ACORTADORES = [
        'bit.ly', 'tinyurl.com', 'cutt.ly', 't.co', 'is.gd', 'goo.gl', 'ow.ly', 'rebrand.ly', 'shorturl.at', 'acortar.link', 'tiny.cc', 's.id',
    ];

    private const TLDS_SOSPECHOSOS = ['xyz', 'top', 'click', 'icu', 'buzz', 'tk', 'ml', 'ga', 'cf', 'gq', 'rest', 'cam'];

    /**
     * Brands commonly impersonated => their official domains.
     */
    private const MARCAS = [
        'mercadopago' => ['Mercado Pago', ['mercadopago.com', 'mercadopago.com.ar']],
        'mercadolibre' => ['Mercado Libre', ['mercadolibre.com', 'mercadolibre.com.ar']],
        'anses' => ['ANSES', ['anses.gob.ar']],
        'afip' => ['AFIP/ARCA', ['afip.gob.ar', 'afip.gov.ar']],
        'bancogalicia' => ['Banco Galicia', ['bancogalicia.com', 'bancogalicia.com.ar']],
        'santander' => ['Santander', ['santander.com.ar', 'santander.com']],
        'bbva' => ['BBVA', ['bbva.com.ar', 'bbva.com']],
        'paypal' => ['PayPal', ['paypal.com']],
        'netflix' => ['Netflix', ['netflix.com']],
        'whatsapp' => ['WhatsApp', ['whatsapp.com', 'whatsapp.net']],
    ];

    /**
     * @return list<Senal>
     */
    public function evaluar(string $contenido): array
    {
        $senales = [];
        $texto = Str::lower(Str::ascii($contenido));

        foreach (self::REGLAS_DE_TEXTO as $razon => [$peso, $patron]) {
            if (preg_match($patron, $texto)) {
                $senales[$razon] = new Senal($razon, $peso);
            }
        }

        foreach (ExtractorDeUrls::extraer($contenido) as $url) {
            foreach ($this->evaluarUrl($url) as $senal) {
                $senales[$senal->razon] = $senal;
            }
        }

        return array_values($senales);
    }

    /**
     * @return list<Senal>
     */
    private function evaluarUrl(string $url): array
    {
        $host = ExtractorDeUrls::host($url);
        $sinWww = Str::after($host, 'www.');
        $senales = [];

        if (in_array($sinWww, self::ACORTADORES, true)) {
            $senales[] = new Senal('Usa un acortador de enlaces que oculta el destino real.', 1);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $senales[] = new Senal('El enlace usa una dirección IP en lugar de un nombre de dominio.', 2);
        } else {
            if (preg_match_all('~\d~', $host) >= 4) {
                $senales[] = new Senal('El dominio tiene muchos números, algo poco habitual en sitios legítimos.', 1);
            }

            if (substr_count($host, '-') >= 3) {
                $senales[] = new Senal('El dominio tiene muchos guiones, algo común en sitios que imitan a otros.', 1);
            }

            if (in_array(Str::afterLast($host, '.'), self::TLDS_SOSPECHOSOS, true)) {
                $senales[] = new Senal('El dominio usa una terminación muy usada en estafas.', 1);
            }
        }

        if (str_contains($host, 'xn--')) {
            $senales[] = new Senal('El dominio usa caracteres especiales que pueden imitar a otro sitio.', 2);
        }

        if (Str::startsWith(Str::lower($url), 'http://')) {
            $senales[] = new Senal('El enlace no usa conexión segura (https).', 1);
        }

        $hostPlano = str_replace(['-', '.'], '', $host);

        foreach (self::MARCAS as $clave => [$nombre, $oficiales]) {
            if (str_contains($hostPlano, $clave) && ! $this->perteneceA($host, $oficiales)) {
                $senales[] = new Senal("El enlace usa el nombre de {$nombre} pero no es su sitio oficial.", 2);
            }
        }

        return $senales;
    }

    /**
     * @param  list<string>  $dominios
     */
    private function perteneceA(string $host, array $dominios): bool
    {
        foreach ($dominios as $dominio) {
            if ($host === $dominio || Str::endsWith($host, '.'.$dominio)) {
                return true;
            }
        }

        return false;
    }
}
