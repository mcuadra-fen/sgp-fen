<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una solicitud «Pago a proveedores» de Webdox (ver App\Support\SincronizarPagos). */
class PagoWebdox extends Model
{
    protected $table = 'pagos_webdox';

    protected $guarded = ['id'];

    /** Estados de Webdox que ya no cambian: no hace falta volver a consultarlos */
    public const FINALES = ['finished', 'canceled', 'rejected'];

    public const ESTADOS = [
        'requested' => 'Solicitada', 'taken' => 'En curso', 'in_review' => 'En revisión',
        'under_modification' => 'En modificación', 'finished' => 'Finalizada', 'canceled' => 'Cancelada', 'rejected' => 'Rechazada',
    ];

    protected $casts = [
        'webdox_id' => 'integer',
        'creada_en' => 'datetime',
        'fecha_certificado' => 'date',
        'cantidad_documentos' => 'integer',
        'n_adjuntos' => 'integer',
        'adjuntos' => 'array',
        'atributos' => 'array',
        'sincronizado_en' => 'datetime',
    ];

    public function paraInterfaz(): array
    {
        return [
            'id' => $this->id,
            'webdox_id' => $this->webdox_id,
            'titulo' => $this->titulo,
            'estado' => $this->estado,
            'creada' => $this->creada_en?->format('Y-m-d'),
            'solicitante' => $this->solicitante,
            'asignado' => $this->asignado,
            'aprobador' => $this->aprobador,
            'correlativo' => $this->correlativo,
            'fecha_certificado' => $this->fecha_certificado?->format('Y-m-d'),
            'cantidad_documentos' => $this->cantidad_documentos,
            'fecha_pago_requerida' => $this->fecha_pago_requerida,
            'link_antecedentes' => $this->link_antecedentes,
            'n_adjuntos' => $this->n_adjuntos,
            'adjuntos' => $this->adjuntos ?? [],
            'atributos' => $this->atributos ?? [],
        ];
    }
}
