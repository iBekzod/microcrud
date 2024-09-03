<?php

namespace Microcrud\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    public function rules()
    {
        return [
            'user_id' => 'required|integer|exists:' . config('schema.user') . '.users,id',
        ];
    }
}
