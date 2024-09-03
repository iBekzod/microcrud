<?php

namespace Microcrud\Requests;

use Microcrud\Abstracts\Http\FormRequest;

class RestoreRequest extends FormRequest
{
    public function validations()
    {
        return [
            'id' => 'required',
        ];
    }
}
