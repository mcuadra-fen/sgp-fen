<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Solicitudes «Pago a proveedores» de la UDT traídas desde Webdox (copia local; Webdox es la fuente). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_webdox', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webdox_id')->unique();
            $table->string('titulo');
            $table->string('estado', 24)->index();
            $table->string('formulario')->nullable();
            $table->dateTime('creada_en')->index();
            $table->string('solicitante')->nullable();
            $table->string('asignado')->nullable();
            $table->string('aprobador')->nullable();
            $table->string('departamento')->nullable();
            $table->string('correlativo', 32)->nullable();
            $table->date('fecha_certificado')->nullable();
            $table->unsignedInteger('cantidad_documentos')->nullable();
            $table->string('fecha_pago_requerida')->nullable();
            $table->text('link_antecedentes')->nullable();
            $table->unsignedInteger('n_adjuntos')->default(0);
            $table->json('atributos')->nullable(); // todos los campos del formulario, tal como vienen
            $table->dateTime('sincronizado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_webdox');
    }
};
