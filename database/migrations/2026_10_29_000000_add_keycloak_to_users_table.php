<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Los usuarios entran por el SSO de la FEN (Keycloak): la clave la maneja Keycloak, no el sistema. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('keycloak_id')->nullable()->unique()->after('id'); // claim «sub»
            $table->string('username')->nullable()->after('name'); // preferred_username
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['keycloak_id']);
            $table->dropColumn(['keycloak_id', 'username']);
        });
    }
};
