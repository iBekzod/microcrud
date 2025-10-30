<?php

namespace Microcrud\Abstracts\Exceptions;

use Exception;

/**
 * ValidationException
 *
 * Exception thrown when request data fails validation.
 *
 * Common scenarios:
 * - Required fields missing
 * - Invalid data format (e.g., invalid email, non-numeric value)
 * - Failed validation rules (e.g., unique constraint, exists check)
 * - Custom validation failures
 * - Bulk action validation (missing bulk_action parameter)
 *
 * Enhanced Features:
 * - Store validation errors array for multiple field errors
 * - Track specific field that failed validation
 * - Convert to array for API responses
 * - Field-specific error retrieval
 *
 * @package Microcrud\Abstracts\Exceptions
 */
class ValidationException extends Exception
{
    /**
     * Validation errors array (field => message or nested arrays)
     *
     * @var array
     */
    protected $errors = [];

    /**
     * The field name that caused the validation failure
     *
     * @var string|null
     */
    protected $field = null;

    /**
     * Create a new ValidationException instance.
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 422 Unprocessable Entity)
     * @param \Exception|null $previous Previous exception for chaining
     * @param array $errors Validation errors array
     * @param string|null $field Field name that failed validation
     */
    public function __construct($message = "", $code = 422, ?\Exception $previous = null, array $errors = [], ?string $field = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
        $this->field = $field;
    }

    /**
     * Get all validation errors.
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set validation errors.
     *
     * Useful for adding errors after exception creation.
     *
     * @param array $errors Validation errors array
     * @return $this
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
        return $this;
    }

    /**
     * Get the field name that failed validation.
     *
     * @return string|null
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * Set the field name that failed validation.
     *
     * @param string $field Field name
     * @return $this
     */
    public function setField($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * Check if exception has validation errors.
     *
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }

    /**
     * Get error message for a specific field.
     *
     * @param string $field Field name
     * @return string|array|null
     */
    public function getErrorForField($field)
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Add a validation error for a specific field.
     *
     * @param string $field Field name
     * @param string $error Error message
     * @return $this
     */
    public function addError($field, $error)
    {
        $this->errors[$field] = $error;
        return $this;
    }

    /**
     * Convert exception to array for API responses.
     *
     * Useful for standardized error responses in controllers.
     *
     * @return array
     */
    public function toArray()
    {
        $data = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];

        if ($this->hasErrors()) {
            $data['errors'] = $this->errors;
        }

        if ($this->field) {
            $data['field'] = $this->field;
        }

        return $data;
    }

    /**
     * Convert exception to JSON for API responses.
     *
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
}
