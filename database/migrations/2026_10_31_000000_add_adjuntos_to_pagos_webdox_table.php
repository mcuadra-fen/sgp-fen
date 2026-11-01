<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nombre y tipo de los adjuntos de cada solicitud de Webdox (la API no permite descargarlos). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_webdox', function (Blueprint $table) {
            $table->json('adjuntos')->nullable(); // null = aún no se consultan (la próxima sincronización trae el detalle)
        });
    }

    public function down(): void
    {
        Schema::table('pagos_webdox', fn (Blueprint $table) => $table->dropColumn('adjuntos'));
    }
};
