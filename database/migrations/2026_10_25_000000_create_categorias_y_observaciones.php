<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categorías propias para clasificar partidas (una por partida, comunes a todos los años)
     * y observaciones (varias por partida).
     */
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });

        // Sin restricción de clave foránea: SQLite no la agrega a una tabla existente.
        // Al eliminar una categoría, Categoria::booted() deja sus partidas sin categoría.
        Schema::table('partidas', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_id')->nullable()->index()->after('anio');
        });

        Schema::create('observaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $table->text('texto');
            $table->timestamps();
        });

        $ahora = now();
        DB::table('categorias')->insert([
            ['nombre' => 'Licencias transversales', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Proyectos', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('observaciones');
        Schema::table('partidas', function (Blueprint $table) {
            $table->dropIndex(['categoria_id']);
            $table->dropColumn('categoria_id');
        });
        Schema::dropIfExists('categorias');
    }
};
