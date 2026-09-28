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
        // Optional neighborhood of Formosa Capital (App\Enums\Barrio), same pattern as departamento/medio.
        Schema::table('reportes', function (Blueprint $table) {
            $table->string('barrio', 32)->nullable()->after('departamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropColumn('barrio');
        });
    }
};
