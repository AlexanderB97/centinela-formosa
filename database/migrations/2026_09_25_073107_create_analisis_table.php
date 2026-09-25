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
        Schema::create('analisis', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['texto', 'link', 'qr']);
            $table->text('contenido');
            $table->enum('nivel', ['seguro', 'dudoso', 'riesgo']);
            $table->json('razones');
            $table->text('explicacion');
            $table->boolean('explicacion_generada_por_ia')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analisis');
    }
};
