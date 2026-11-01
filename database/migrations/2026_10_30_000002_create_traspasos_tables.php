<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traspasos de recursos (cobros de la UDT a otras áreas): comprobantes contables «T2026…pdf» (o los .txt del
 * asiento) de la carpeta compartida, con una línea por centro de costo. Además el catálogo de centros de costo
 * (nombres desde el tarifario de traspasos fijos) y el tipo de solicitud de Webdox (pago o traspaso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_webdox', function (Blueprint $table) {
            $table->string('tipo', 12)->default('pago')->index(); // pago | traspaso
        });

        Schema::create('traspasos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webdox_id')->nullable()->index(); // ID de la solicitud en Webdox (carpeta ID####)
            $table->string('comprobante', 20)->nullable(); // N° del comprobante contable; null si viene de un .txt
            $table->date('fecha')->nullable();
            $table->text('glosa')->nullable();
            $table->string('sentido', 12); // cobro (la UDT recibe) | pago (la UDT paga) | otro
            $table->bigInteger('total')->default(0);
            $table->string('archivo');
            $table->text('ruta');
            $table->unsignedBigInteger('modificado');
            $table->timestamps();
        });

        Schema::create('lineas_traspaso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traspaso_id')->constrained('traspasos')->cascadeOnDelete();
            $table->unsignedBigInteger('webdox_id')->nullable()->index();
            $table->string('centro_costo', 20)->index(); // 15 dígitos, sin puntos
            $table->string('cuenta', 12); // 520214067 (cargo) | 620309067 (abono)
            $table->string('lado', 6); // cargo | abono
            $table->bigInteger('monto');
            $table->timestamps();
        });

        Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('area')->nullable();
            $table->string('nombre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costo');
        Schema::dropIfExists('lineas_traspaso');
        Schema::dropIfExists('traspasos');
        Schema::table('pagos_webdox', fn (Blueprint $table) => $table->dropColumn('tipo'));
    }
};
