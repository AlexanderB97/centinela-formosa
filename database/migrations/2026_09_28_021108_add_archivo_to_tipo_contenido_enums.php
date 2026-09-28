<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // File scanner: an analysis of a file stores only its SHA-256 hash as contenido.
        foreach (['analisis', 'casos_confirmados'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->enum('tipo', ['texto', 'link', 'qr', 'archivo'])->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Rows of type "archivo" must be removed first, or the column change fails.
     */
    public function down(): void
    {
        foreach (['analisis', 'casos_confirmados'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->enum('tipo', ['texto', 'link', 'qr'])->change();
            });
        }
    }
};
