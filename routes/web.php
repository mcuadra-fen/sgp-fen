<?php

use App\Http\Controllers\ClasificarController;
use App\Http\Controllers\DatosController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\PartidaController;
use App\Http\Controllers\ResumenController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/resumen');

Route::get('/resumen', ResumenController::class)->name('resumen');
Route::get('/informe', InformeController::class)->name('informe');

Route::get('clasificar', [ClasificarController::class, 'index'])->name('clasificar');
Route::post('clasificar/asignar', [ClasificarController::class, 'asignar'])->name('clasificar.asignar');
Route::post('categorias', [ClasificarController::class, 'crearCategoria'])->name('categorias.store');
Route::patch('categorias/{categoria}', [ClasificarController::class, 'renombrarCategoria'])->name('categorias.update');
Route::delete('categorias/{categoria}', [ClasificarController::class, 'eliminarCategoria'])->name('categorias.destroy');
Route::post('partidas/{partida}/observaciones', [ClasificarController::class, 'agregarObservacion'])->name('observaciones.store');
Route::delete('observaciones/{observacion}', [ClasificarController::class, 'eliminarObservacion'])->name('observaciones.destroy');

Route::post('partidas/eliminar', [PartidaController::class, 'eliminarVarias'])->name('partidas.eliminar-varias');
Route::post('partidas/restaurar-varias', [PartidaController::class, 'restaurarVarias'])->name('partidas.restaurar-varias');
Route::resource('partidas', PartidaController::class)->except(['show']);
Route::patch('partidas/{partida}/mes', [PartidaController::class, 'mes'])->name('partidas.mes');
Route::post('partidas/{partida}/duplicar', [PartidaController::class, 'duplicar'])->name('partidas.duplicar');
Route::post('partidas/{id}/restaurar', [PartidaController::class, 'restaurar'])->name('partidas.restaurar');

Route::get('datos', [DatosController::class, 'index'])->name('datos');
Route::post('datos/revisar', [DatosController::class, 'revisar'])->name('datos.revisar');
Route::post('datos/importar', [DatosController::class, 'importar'])->name('datos.importar');
Route::get('datos/exportar', [DatosController::class, 'exportar'])->name('datos.exportar');
Route::post('datos/quitar-ejemplos', [DatosController::class, 'quitarEjemplos'])->name('datos.quitar-ejemplos');
Route::post('datos/reiniciar', [DatosController::class, 'reiniciar'])->name('datos.reiniciar');
Route::delete('datos/anios/{anio}', [DatosController::class, 'eliminarAnio'])->whereNumber('anio')->name('datos.anio.eliminar');
Route::post('datos/anios/{anio}/restaurar', [DatosController::class, 'restaurarAnio'])->whereNumber('anio')->name('datos.anio.restaurar');

Route::post('anio', [DatosController::class, 'elegirAnio'])->name('anio');
