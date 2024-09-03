<?php

namespace Microcrud\Requests;

use Microcrud\Abstracts\Http\FormRequest;

class DestroyRequest extends FormRequest
{
    public function validations()
    {
        return [
            'id' => 'required',
            'is_force_destroy' => 'sometimes|boolean'
        ];
    }
}
