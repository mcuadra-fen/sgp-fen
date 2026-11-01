<?php

use App\Http\Controllers\AccesosController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClasificarController;
use App\Http\Controllers\DatosController;
use App\Http\Controllers\EjecucionController;
use App\Http\Controllers\InformeController;
use App\Http\Controllers\MisCobrosController;
use App\Http\Controllers\PagosController;
use App\Http\Controllers\PartidaController;
use App\Http\Controllers\ResumenController;
use App\Http\Controllers\TraspasosController;
use App\Http\Middleware\SoloAdmin;
use Illuminate\Support\Facades\Route;

// Ingreso con el SSO de la FEN (Keycloak). CallbackPath y SignedOutCallbackPath como en la configuración del cliente
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'login'])->name('login');
    Route::get('login/keycloak', [AuthController::class, 'redirect'])->name('login.keycloak');
    Route::get('signin-oidc', [AuthController::class, 'callback'])->name('login.callback');
});
Route::get('signout-callback-oidc', [AuthController::class, 'signedOut'])->name('logout.callback');

// Todo el sistema exige haber ingresado
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    // Personas de las áreas: solo lo que la UDT les ha cobrado (sus centros de costo)
    Route::get('mis-cobros', MisCobrosController::class)->name('mis-cobros');

    // El resto del sistema, solo administradores (config sgp.admins)
    Route::middleware(SoloAdmin::class)->group(function () {
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

        Route::get('ejecucion', EjecucionController::class)->name('ejecucion');
        Route::get('pagos', [PagosController::class, 'index'])->name('pagos');
        Route::get('pagos/{webdoxId}', [PagosController::class, 'show'])->whereNumber('webdoxId')->name('pagos.show');
        Route::get('pagos/{webdoxId}/excel', [PagosController::class, 'excel'])->whereNumber('webdoxId')->name('pagos.excel');
        Route::post('pagos/sincronizar', [PagosController::class, 'sincronizar'])->name('pagos.sincronizar');
        Route::post('pagos/certificados', [PagosController::class, 'leerCertificados'])->name('pagos.certificados');

        Route::get('traspasos', [TraspasosController::class, 'index'])->name('traspasos');
        Route::post('traspasos/leer', [TraspasosController::class, 'leer'])->name('traspasos.leer');
        Route::get('traspasos/{webdoxId}', [TraspasosController::class, 'show'])->whereNumber('webdoxId')->name('traspasos.show');
        Route::get('traspasos/{webdoxId}/excel', [TraspasosController::class, 'excel'])->whereNumber('webdoxId')->name('traspasos.excel');

        Route::get('accesos', [AccesosController::class, 'index'])->name('accesos');
        Route::post('accesos', [AccesosController::class, 'store'])->name('accesos.store');
        Route::delete('accesos/{acceso}', [AccesosController::class, 'destroy'])->name('accesos.destroy');
    });
});
