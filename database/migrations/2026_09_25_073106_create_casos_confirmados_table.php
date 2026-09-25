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
        Schema::create('casos_confirmados', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['texto', 'link', 'qr']);
            $table->text('contenido');
            // sha256 del contenido normalizado: permite buscar coincidencias exactas sin comparar textos largos.
            $table->char('huella', 64)->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('casos_confirmados');
    }
};
