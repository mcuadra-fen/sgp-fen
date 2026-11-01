<?php

return [
    // Correos FEN con acceso completo al sistema (separados por coma). El resto solo entra si tiene centros de costo asignados.
    'admins' => array_values(array_filter(array_map(
        fn ($e) => strtolower(trim($e)),
        explode(',', (string) env('SGP_ADMINS', 'mcuadra@fen.uchile.cl'))
    ))),
];
