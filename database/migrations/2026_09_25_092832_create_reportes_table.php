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
        // Anonymous reports: no column may identify the visitor (no IP, user agent, email or session).
        Schema::create('reportes', function (Blueprint $table) {
            $table->id();
            // One report per analysis: the unique index enforces deduplication even under concurrent requests.
            $table->foreignId('analisis_id')->unique()->constrained('analisis')->cascadeOnDelete();
            $table->text('comentario')->nullable();
            $table->enum('estado', ['pendiente', 'confirmado', 'descartado'])->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
