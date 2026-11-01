<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué centros de costo puede ver cada persona de un área en «Mis cobros». Se asigna por correo FEN,
 * así la persona ya tiene acceso la primera vez que ingresa con Keycloak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_area', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index(); // en minúsculas
            $table->string('centro_costo', 20);
            $table->timestamps();
            $table->unique(['email', 'centro_costo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_area');
    }
};
