<?php

namespace Microcrud\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaginationRequest extends FormRequest
{
    public function rules()
    {
        return [
            'page' => 'sometimes|numeric|min:1',
            'limit' => 'sometimes|numeric|min:1',
            'trashed_status'=>'sometimes|integer|in:-1,0,1',//here -1 only trashed, 0 or null as usual, 1 with trashed
        ];
    }
}
