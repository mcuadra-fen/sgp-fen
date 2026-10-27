<?php

use App\Http\Controllers\DatosController;
use App\Http\Controllers\PartidaController;
use App\Http\Controllers\ResumenController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/resumen');

Route::get('/resumen', ResumenController::class)->name('resumen');

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
