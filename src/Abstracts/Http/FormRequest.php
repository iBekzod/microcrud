<?php

namespace Microcrud\Abstracts\Http;

use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
use Microcrud\Requests\PaginationRequest;
use Microcrud\Requests\SearchRequest;
use Microcrud\Requests\UserRequest;

abstract class FormRequest extends BaseFormRequest
{
    protected $is_user_request = false;
    protected $is_pagination_request = false;
    protected $is_search_request = false;

    public $extra_rules = [];

    /**
     * Collection of rules
     * @return array
     */
    abstract public function validations();
    /**;
     *
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return config('microcrud.authorize', false);
    }

    protected function specialRules()
    {
        if ($this->is_user_request) {
            $this->extra_rules = array_merge($this->extra_rules, (new UserRequest)->rules());
        }
        if ($this->is_pagination_request) {
            $this->extra_rules = array_merge($this->extra_rules, (new PaginationRequest)->rules());
        }
        if ($this->is_search_request) {
            $this->extra_rules = array_merge($this->extra_rules, (new SearchRequest)->rules());
        }
        return $this->extra_rules;
    }

    public function rules()
    {
        return array_merge($this->specialRules(), $this->validations());
    }

    /**
     * If validator fails return the exception in json form
     * @param Validator $validator
     * @return array
     */
    protected function failedValidation(Validator $validator)
    {
        $response = new Response([
            'message' => trans('microcrud_translations::' . $validator->errors()->first())
        ], 422);
        throw new ValidationException($validator, $response);
    }
}
