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
        // Optional context of the reported message (App\Enums\Departamento and MedioRecepcion).
        // Plain strings instead of database enums, so the lists can grow without a migration;
        // the values are guaranteed by Rule::enum and the model casts.
        Schema::table('reportes', function (Blueprint $table) {
            $table->string('departamento', 32)->nullable()->after('comentario');
            $table->string('medio', 32)->nullable()->after('departamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn(['departamento', 'medio']);
        });
    }
};
