<?php

use App\Services\Analisis\Huella;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('analisis', function (Blueprint $table) {
            // Same normalized fingerprint as casos_confirmados, so repeated content can be grouped in SQL.
            $table->char('huella', 64)->nullable()->after('contenido');
            $table->index(['tipo', 'huella']);
        });

        $this->rellenarHuellas();
    }

    /**
     * Backfill existing rows in chunks, without loading the whole table into memory.
     * Public so the backfill can be tested on its own.
     */
    public function rellenarHuellas(): void
    {
        DB::table('analisis')
            ->whereNull('huella')
            ->select(['id', 'contenido'])
            ->chunkById(500, function ($filas) {
                foreach ($filas as $fila) {
                    DB::table('analisis')->where('id', $fila->id)->update(['huella' => Huella::de($fila->contenido)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analisis', function (Blueprint $table) {
            $table->dropIndex(['tipo', 'huella']);
            $table->dropColumn('huella');
        });
    }
};
