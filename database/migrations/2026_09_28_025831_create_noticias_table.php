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
        // News written by staff. The body is plain text: it is always rendered escaped, never as HTML.
        Schema::create('noticias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->text('cuerpo');
            // Server-generated path on the private "local" disk (never the original file name).
            $table->string('imagen_ruta')->nullable();
            $table->enum('estado', ['borrador', 'publicada'])->default('borrador');
            // Set on the first publication and kept when the news is unpublished and published again.
            $table->timestamp('publicada_en')->nullable();
            $table->foreignId('usuario_staff_id')->nullable()->constrained('usuarios_staff')->nullOnDelete();
            $table->timestamps();

            $table->index(['estado', 'publicada_en']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('noticias');
    }
};
