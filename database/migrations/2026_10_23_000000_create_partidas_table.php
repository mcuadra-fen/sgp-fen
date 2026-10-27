<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas', function (Blueprint $table) {
            $table->id();
            $table->string('dpto')->default('');
            $table->string('unidad')->index();
            $table->string('actividad');
            $table->string('subactividad')->default('');
            $table->string('sigma')->index();
            $table->string('cuenta');
            for ($i = 1; $i <= 12; $i++) {
                $table->unsignedBigInteger('m' . $i)->default(0); // m1 = enero ... m12 = diciembre
            }
            $table->string('dep_codigo', 20)->nullable();
            $table->string('un_codigo', 20)->nullable();
            $table->boolean('es_ejemplo')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas');
    }
};
