<?php

namespace App\Services\Analisis;

use RuntimeException;

/**
 * VirusTotal could not be consulted (not configured, unreachable or out of quota). The file
 * scanner has no rules of its own, so without VirusTotal there is no result to give.
 */
final class VirusTotalNoDisponible extends RuntimeException {}
