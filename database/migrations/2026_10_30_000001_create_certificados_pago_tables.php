<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Excel «ID####_Certificado de Pago.xlsx» de la carpeta compartida: un certificado por solicitud de Webdox
 * (mismo ID) y una línea por factura pagada a un proveedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_pago', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webdox_id')->unique(); // ID de la solicitud en Webdox (referencia)
            $table->string('numero', 32)->nullable(); // «024/2026»
            $table->date('fecha')->nullable();
            $table->string('archivo');
            $table->text('ruta');
            $table->unsignedBigInteger('modificado'); // fecha del archivo: si cambia, se vuelve a leer
            $table->timestamps();
        });

        Schema::create('lineas_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificado_id')->constrained('certificados_pago')->cascadeOnDelete();
            $table->unsignedBigInteger('webdox_id')->index();
            $table->unsignedSmallInteger('n')->nullable();
            $table->string('proveedor');
            $table->string('rut', 20)->nullable()->index();
            $table->string('factura', 40)->nullable();
            $table->bigInteger('neto')->default(0);
            $table->bigInteger('iva')->default(0);
            $table->bigInteger('exento')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('cuenta_contable', 40)->nullable()->index();
            $table->string('unidad', 60)->nullable();
            $table->string('centro_costo', 60)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineas_pago');
        Schema::dropIfExists('certificados_pago');
    }
};
