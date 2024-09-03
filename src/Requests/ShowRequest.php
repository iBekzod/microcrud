<?php

namespace Microcrud\Requests;

use Microcrud\Abstracts\Http\FormRequest;

class ShowRequest extends FormRequest
{
    public function validations()
    {
        return [
            'id' => 'required'
        ];
    }
}
