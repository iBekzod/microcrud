<?php

namespace Microcrud\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function rules()
    {
        return [
            'search' => 'sometimes|string',
        ];
    }
}
