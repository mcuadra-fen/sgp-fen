<?php

namespace App\Http\Requests;

use App\Support\Presupuesto;
use Illuminate\Foundation\Http\FormRequest;

class PartidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Los montos llegan formateados ("1.234.567"); se convierten a enteros antes de validar. */
    protected function prepareForValidation(): void
    {
        $m = array_slice(array_values((array) $this->input('meses', [])), 0, 12);
        $m = array_pad(array_map([Presupuesto::class, 'parseMonto'], $m), 12, 0);
        $this->merge(['meses' => $m]);
    }

    public function rules(): array
    {
        return [
            'dpto' => ['nullable', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:255'],
            'actividad' => ['required', 'string', 'max:255'],
            'subactividad' => ['nullable', 'string', 'max:255'],
            'sigma' => ['required', 'string', 'max:255'],
            'cuenta' => ['required', 'string', 'max:255'],
            'dep_codigo' => ['nullable', 'digits_between:1,20'],
            'un_codigo' => ['nullable', 'digits_between:1,20'],
            'meses' => ['required', 'array', 'size:12'],
            'meses.*' => ['integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'dpto' => 'DPTO',
            'unidad' => 'UNIDAD',
            'actividad' => 'ACTIVIDAD',
            'subactividad' => 'SUBACTIVIDAD',
            'sigma' => 'CLASIFICACIÓN SIGMA',
            'cuenta' => 'CUENTA',
            'dep_codigo' => 'dep_codigo',
            'un_codigo' => 'un_codigo',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Falta completar :attribute.',
            'digits_between' => ':attribute debe contener solo números.',
            'max' => ':attribute no puede superar :max caracteres.',
        ];
    }

    /** Atributos listos para Partida::create() / fill(). */
    public function datos(): array
    {
        $v = $this->validated();
        $out = [
            'dpto' => $v['dpto'] ?? '',
            'unidad' => $v['unidad'],
            'actividad' => $v['actividad'],
            'subactividad' => $v['subactividad'] ?? '',
            'sigma' => $v['sigma'],
            'cuenta' => $v['cuenta'],
            'dep_codigo' => $v['dep_codigo'] ?? null,
            'un_codigo' => $v['un_codigo'] ?? null,
        ];
        foreach ($v['meses'] as $i => $monto) {
            $out['m' . ($i + 1)] = (int) $monto;
        }

        return $out;
    }
}
