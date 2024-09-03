<?php

namespace Microcrud\Services\User\Requests;

use Microcrud\Abstracts\Http\FormRequest;

class UserBalanceRequest extends FormRequest
{
    protected $is_user_request = true;
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function validations()
    {
        return [
            'user_id' => 'required|integer|exists:' . config('schema.user') . '.users,id'
        ];
    }
}
