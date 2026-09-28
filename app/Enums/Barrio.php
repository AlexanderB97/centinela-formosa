<?php

namespace App\Enums;

/**
 * Neighborhood of Formosa Capital where a reported message was received. Optional context of
 * the message, never data about the visitor. Only meaningful when departamento is formosa_capital.
 *
 * The cases are declared grouped by zone, in the order they are offered in the report form.
 */
enum Barrio: string
{
    case Centro = 'centro';
    case DonBosco = 'don_bosco';
    case SanMartin = 'san_martin';
    case Independencia = 'independencia';
    case SanFrancisco = 'san_francisco';
    case Fontana = 'fontana';
    case Guadalupe = 'guadalupe';
    case Militar = 'militar';
    case ParqueUrbano = 'parque_urbano';
    case SimonBolivar = 'simon_bolivar';
    case OchoDeOctubre = 'ocho_de_octubre';
    case EvaPeron = 'eva_peron';
    case RepublicaArgentina = 'republica_argentina';
    case ElPorvenir = 'el_porvenir';
    case AntenorGauna = 'antenor_gauna';
    case JuanDomingoPeron = 'juan_domingo_peron';
    case StellaMaris = 'stella_maris';
    case LasOrquideas = 'las_orquideas';
    case VeinteDeJulio = 'veinte_de_julio';
    case SieteDeMayo = 'siete_de_mayo';
    case SeisDeEnero = 'seis_de_enero';
    case SanMiguel = 'san_miguel';
    case SanPedro = 'san_pedro';
    case Belgrano = 'belgrano';
    case SanJoseObrero = 'san_jose_obrero';
    case Obrero = 'obrero';
    case MarianoMoreno = 'mariano_moreno';
    case LagunaSiam = 'laguna_siam';
    case LaPilar = 'la_pilar';
    case SanJuanBautista = 'san_juan_bautista';
    case SanJorge = 'san_jorge';
    case VillaLourdes = 'villa_lourdes';
    case FacundoQuiroga = 'facundo_quiroga';
    case RicardoBalbin = 'ricardo_balbin';
    case DiecisieteDeOctubre = 'diecisiete_de_octubre';
    case Fleming = 'fleming';
    case LaNuevaFormosa = 'la_nueva_formosa';
    case VillaDelCarmen = 'villa_del_carmen';
    case VirgenDelRosario = 'virgen_del_rosario';
    case SanAntonio = 'san_antonio';
    case Itati = 'itati';
    case Liborsi = 'liborsi';
    case Colluccio = 'colluccio';
    case Vial = 'vial';
    case LaFloresta = 'la_floresta';
    case ElResguardo = 'el_resguardo';
    case SagradoCorazon = 'sagrado_corazon';
    case Lujan = 'lujan';
    case BernardinoRivadaviaLote4 = 'bernardino_rivadavia_lote_4';
    case Lote111 = 'lote_111';
    case UrbanizacionEspana = 'urbanizacion_espana';
    case DosDeAbril = 'dos_de_abril';
    case DoceDeOctubre = 'doce_de_octubre';
    case JuanManuelDeRosas = 'juan_manuel_de_rosas';
    case LaPaz = 'la_paz';
    case SanAgustin = 'san_agustin';
    case FraySalvadorGurrieri = 'fray_salvador_gurrieri';
    case Illia = 'illia';
    case Incone = 'incone';
    case Malvinas = 'malvinas';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Centro => 'Centro',
            self::DonBosco => 'Don Bosco',
            self::SanMartin => 'San Martín',
            self::Independencia => 'Independencia',
            self::SanFrancisco => 'San Francisco',
            self::Fontana => 'Fontana',
            self::Guadalupe => 'Guadalupe',
            self::Militar => 'Militar',
            self::ParqueUrbano => 'Parque Urbano',
            self::SimonBolivar => 'Simón Bolívar',
            self::OchoDeOctubre => '8 de Octubre',
            self::EvaPeron => 'Eva Perón',
            self::RepublicaArgentina => 'República Argentina',
            self::ElPorvenir => 'El Porvenir',
            self::AntenorGauna => 'Antenor Gauna',
            self::JuanDomingoPeron => 'Juan Domingo Perón',
            self::StellaMaris => 'Stella Maris',
            self::LasOrquideas => 'Las Orquídeas',
            self::VeinteDeJulio => '20 de Julio',
            self::SieteDeMayo => '7 de Mayo',
            self::SeisDeEnero => '6 de Enero',
            self::SanMiguel => 'San Miguel',
            self::SanPedro => 'San Pedro',
            self::Belgrano => 'Belgrano',
            self::SanJoseObrero => 'San José Obrero',
            self::Obrero => 'Obrero',
            self::MarianoMoreno => 'Mariano Moreno',
            self::LagunaSiam => 'Laguna Siam',
            self::LaPilar => 'La Pilar',
            self::SanJuanBautista => 'San Juan Bautista',
            self::SanJorge => 'San Jorge',
            self::VillaLourdes => 'Villa Lourdes',
            self::FacundoQuiroga => 'Facundo Quiroga',
            self::RicardoBalbin => 'Ricardo Balbín',
            self::DiecisieteDeOctubre => '17 de Octubre',
            self::Fleming => 'Fleming',
            self::LaNuevaFormosa => 'La Nueva Formosa',
            self::VillaDelCarmen => 'Villa del Carmen',
            self::VirgenDelRosario => 'Virgen del Rosario',
            self::SanAntonio => 'San Antonio',
            self::Itati => 'Itatí',
            self::Liborsi => 'Liborsi',
            self::Colluccio => 'Colluccio',
            self::Vial => 'Vial',
            self::LaFloresta => 'La Floresta',
            self::ElResguardo => 'El Resguardo',
            self::SagradoCorazon => 'Sagrado Corazón',
            self::Lujan => 'Luján',
            self::BernardinoRivadaviaLote4 => 'Bernardino Rivadavia (Lote 4)',
            self::Lote111 => 'Lote 111',
            self::UrbanizacionEspana => 'Urbanización España',
            self::DosDeAbril => '2 de Abril',
            self::DoceDeOctubre => '12 de Octubre',
            self::JuanManuelDeRosas => 'Juan Manuel de Rosas',
            self::LaPaz => 'La Paz',
            self::SanAgustin => 'San Agustín',
            self::FraySalvadorGurrieri => 'Fray Salvador Gurrieri',
            self::Illia => 'Illia',
            self::Incone => 'Incone',
            self::Malvinas => 'Malvinas',
        };
    }

    /**
     * Zone of the city the neighborhood belongs to, used to group the form's select.
     */
    public function zona(): string
    {
        return match ($this) {
            self::Centro,
            self::DonBosco,
            self::SanMartin,
            self::Independencia,
            self::SanFrancisco,
            self::Fontana,
            self::Guadalupe,
            self::Militar,
            self::ParqueUrbano => 'Centro y Alrededores',
            self::SimonBolivar,
            self::OchoDeOctubre,
            self::EvaPeron,
            self::RepublicaArgentina,
            self::ElPorvenir,
            self::AntenorGauna,
            self::JuanDomingoPeron,
            self::StellaMaris,
            self::LasOrquideas,
            self::VeinteDeJulio,
            self::SieteDeMayo,
            self::SeisDeEnero => 'Zona Norte y Circuito 5',
            self::SanMiguel,
            self::SanPedro,
            self::Belgrano,
            self::SanJoseObrero,
            self::Obrero,
            self::MarianoMoreno,
            self::LagunaSiam,
            self::LaPilar,
            self::SanJuanBautista,
            self::SanJorge,
            self::VillaLourdes,
            self::FacundoQuiroga,
            self::RicardoBalbin,
            self::DiecisieteDeOctubre,
            self::Fleming => 'Zona Oeste y Sudoeste',
            self::LaNuevaFormosa,
            self::VillaDelCarmen,
            self::VirgenDelRosario,
            self::SanAntonio,
            self::Itati,
            self::Liborsi,
            self::Colluccio,
            self::Vial,
            self::LaFloresta,
            self::ElResguardo,
            self::SagradoCorazon,
            self::Lujan,
            self::BernardinoRivadaviaLote4,
            self::Lote111,
            self::UrbanizacionEspana => 'Zona Sur y Expansión',
            self::DosDeAbril,
            self::DoceDeOctubre,
            self::JuanManuelDeRosas,
            self::LaPaz,
            self::SanAgustin,
            self::FraySalvadorGurrieri,
            self::Illia,
            self::Incone,
            self::Malvinas => 'Otros Sectores',
        };
    }

    /**
     * Neighborhoods grouped by zone, keeping the declaration order of both zones and cases.
     *
     * @return array<string, list<self>>
     */
    public static function porZona(): array
    {
        $grupos = [];

        foreach (self::cases() as $barrio) {
            $grupos[$barrio->zona()][] = $barrio;
        }

        return $grupos;
    }
}
