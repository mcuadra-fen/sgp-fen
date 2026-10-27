<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Cada año puede tener un presupuesto inicial y una proyección (desde julio). Lo ya cargado queda como inicial. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->string('version', 12)->default('inicial')->after('anio');
            $table->index(['anio', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropIndex(['anio', 'version']);
            $table->dropColumn('version');
        });
    }
};
