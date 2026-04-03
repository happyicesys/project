<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTradeSignalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'symbol'          => ['required', 'string'],
            'direction'       => ['required', 'string', 'in:LONG,SHORT'],
            'entry_price'     => ['required', 'numeric', 'gt:0'],
            'stop_loss'       => ['required', 'numeric', 'gt:0'],
            'take_profit'     => ['required', 'numeric', 'gt:0'],
            'risk_percentage' => ['required', 'numeric', 'gt:0'],
            'agent_id'        => ['required', 'string'],
        ];
    }
}
