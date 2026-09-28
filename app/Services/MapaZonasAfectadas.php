<?php

namespace App\Services;

use App\Enums\Barrio;
use App\Enums\EstadoReporte;
use App\Enums\TipoContenido;
use App\Models\Reporte;

/**
 * Public map of affected neighborhoods of Formosa Capital: how many reports mention each barrio.
 * Counted by the database in one grouped query; no individual report is loaded.
 *
 * Privacy: a neighborhood only appears with at least MINIMO_REPORTES reports. The threshold is
 * higher than the rankings' (3) because a neighborhood is a much smaller area than a department:
 * a few reports there say more about who might have sent them.
 * Reports without a barrio do not count, reports of file scans do not count either, and
 * discarded reports (staff decided they were not a scam) are excluded. Each point is an
 * approximate reference location of the neighborhood, never where a report came from.
 */
class MapaZonasAfectadas
{
    public const MINIMO_REPORTES = 5;

    /**
     * Margin around the outermost neighborhoods when framing the map, in degrees (~1 km).
     */
    public const MARGEN_LIMITES = 0.01;

    /**
     * Approximate reference point of each neighborhood. None of them is verified against an
     * official cadastre: they are orientative, and the page says so prominently.
     *
     * @var array<string, array{lat: float, lng: float}>
     */
    public const BARRIOS = [
        'centro' => ['lat' => -26.1848, 'lng' => -58.1731],
        'don_bosco' => ['lat' => -26.1775, 'lng' => -58.169],
        'san_martin' => ['lat' => -26.183, 'lng' => -58.183],
        'independencia' => ['lat' => -26.192, 'lng' => -58.181],
        'san_francisco' => ['lat' => -26.173, 'lng' => -58.189],
        'fontana' => ['lat' => -26.17, 'lng' => -58.177],
        'guadalupe' => ['lat' => -26.195, 'lng' => -58.196],
        'militar' => ['lat' => -26.191, 'lng' => -58.165],
        'parque_urbano' => ['lat' => -26.193, 'lng' => -58.189],
        'simon_bolivar' => ['lat' => -26.128, 'lng' => -58.188],
        'ocho_de_octubre' => ['lat' => -26.131, 'lng' => -58.201],
        'eva_peron' => ['lat' => -26.136, 'lng' => -58.192],
        'republica_argentina' => ['lat' => -26.123, 'lng' => -58.197],
        'el_porvenir' => ['lat' => -26.11, 'lng' => -58.205],
        'antenor_gauna' => ['lat' => -26.118, 'lng' => -58.201],
        'juan_domingo_peron' => ['lat' => -26.13321, 'lng' => -58.17889],
        'stella_maris' => ['lat' => -26.115, 'lng' => -58.175],
        'las_orquideas' => ['lat' => -26.134, 'lng' => -58.165],
        'veinte_de_julio' => ['lat' => -26.125, 'lng' => -58.182],
        'siete_de_mayo' => ['lat' => -26.112, 'lng' => -58.192],
        'seis_de_enero' => ['lat' => -26.119, 'lng' => -58.186],
        'san_miguel' => ['lat' => -26.166, 'lng' => -58.182],
        'san_pedro' => ['lat' => -26.162, 'lng' => -58.192],
        'belgrano' => ['lat' => -26.168, 'lng' => -58.204],
        'san_jose_obrero' => ['lat' => -26.175, 'lng' => -58.198],
        'obrero' => ['lat' => -26.182, 'lng' => -58.203],
        'mariano_moreno' => ['lat' => -26.191, 'lng' => -58.212],
        'laguna_siam' => ['lat' => -26.196, 'lng' => -58.205],
        'la_pilar' => ['lat' => -26.188, 'lng' => -58.194],
        'san_juan_bautista' => ['lat' => -26.199, 'lng' => -58.218],
        'san_jorge' => ['lat' => -26.205, 'lng' => -58.225],
        'villa_lourdes' => ['lat' => -26.158, 'lng' => -58.187],
        'facundo_quiroga' => ['lat' => -26.169, 'lng' => -58.221],
        'ricardo_balbin' => ['lat' => -26.173, 'lng' => -58.232],
        'diecisiete_de_octubre' => ['lat' => -26.208, 'lng' => -58.215],
        'fleming' => ['lat' => -26.179, 'lng' => -58.228],
        'la_nueva_formosa' => ['lat' => -26.241, 'lng' => -58.243],
        'villa_del_carmen' => ['lat' => -26.235, 'lng' => -58.285],
        'virgen_del_rosario' => ['lat' => -26.215, 'lng' => -58.222],
        'san_antonio' => ['lat' => -26.218, 'lng' => -58.204],
        'itati' => ['lat' => -26.202, 'lng' => -58.185],
        'liborsi' => ['lat' => -26.225, 'lng' => -58.211],
        'colluccio' => ['lat' => -26.198, 'lng' => -58.209],
        'vial' => ['lat' => -26.186, 'lng' => -58.213],
        'la_floresta' => ['lat' => -26.163, 'lng' => -58.201],
        'el_resguardo' => ['lat' => -26.155, 'lng' => -58.195],
        'sagrado_corazon' => ['lat' => -26.207, 'lng' => -58.232],
        'lujan' => ['lat' => -26.222, 'lng' => -58.239],
        'bernardino_rivadavia_lote_4' => ['lat' => -26.203, 'lng' => -58.158],
        'lote_111' => ['lat' => -26.255, 'lng' => -58.252],
        'urbanizacion_espana' => ['lat' => -26.23, 'lng' => -58.192],
        'dos_de_abril' => ['lat' => -26.199, 'lng' => -58.188],
        'doce_de_octubre' => ['lat' => -26.212, 'lng' => -58.193],
        'juan_manuel_de_rosas' => ['lat' => -26.142, 'lng' => -58.183],
        'la_paz' => ['lat' => -26.204, 'lng' => -58.179],
        'san_agustin' => ['lat' => -26.159, 'lng' => -58.174],
        'fray_salvador_gurrieri' => ['lat' => -26.148, 'lng' => -58.181],
        'illia' => ['lat' => -26.201, 'lng' => -58.222],
        'incone' => ['lat' => -26.196, 'lng' => -58.227],
        'malvinas' => ['lat' => -26.203, 'lng' => -58.173],
    ];

    /**
     * Initial map frame covering every neighborhood in BARRIOS plus a margin, as Leaflet bounds
     * [[south, west], [north, east]]. Derived from the list, so it never goes out of date.
     *
     * @return array{0: array{0: float, 1: float}, 1: array{0: float, 1: float}}
     */
    public static function limites(): array
    {
        $latitudes = array_column(self::BARRIOS, 'lat');
        $longitudes = array_column(self::BARRIOS, 'lng');

        return [
            [round(min($latitudes) - self::MARGEN_LIMITES, 4), round(min($longitudes) - self::MARGEN_LIMITES, 4)],
            [round(max($latitudes) + self::MARGEN_LIMITES, 4), round(max($longitudes) + self::MARGEN_LIMITES, 4)],
        ];
    }

    /**
     * @return list<array{barrio: string, etiqueta: string, lat: float, lng: float, total: int}>
     */
    public function obtener(): array
    {
        $zonas = [];

        $totales = Reporte::query()
            ->toBase()
            ->select('barrio')
            ->selectRaw('count(*) as total')
            ->whereNotNull('barrio')
            // Reports of file scans are not part of the map in this first version.
            ->whereNotIn('analisis_id', fn ($consulta) => $consulta->select('id')->from('analisis')->where('tipo', TipoContenido::Archivo->value))
            ->where('estado', '<>', EstadoReporte::Descartado->value)
            ->groupBy('barrio')
            ->havingRaw('count(*) >= ?', [self::MINIMO_REPORTES])
            ->orderByDesc('total')
            ->orderBy('barrio')
            ->get();

        foreach ($totales as $fila) {
            $barrio = Barrio::tryFrom((string) $fila->barrio);

            if ($barrio === null) {
                continue;
            }

            $zonas[] = [
                'barrio' => $barrio->value,
                'etiqueta' => $barrio->etiqueta(),
                'lat' => self::BARRIOS[$barrio->value]['lat'],
                'lng' => self::BARRIOS[$barrio->value]['lng'],
                'total' => (int) $fila->total,
            ];
        }

        return $zonas;
    }
}
