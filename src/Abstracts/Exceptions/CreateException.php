<?php

namespace Microcrud\Abstracts\Exceptions;

use Exception;

/**
 * CreateException
 *
 * Exception thrown when record creation fails.
 *
 * Common scenarios:
 * - Database constraint violations (unique, foreign key, etc.)
 * - Invalid data that passes validation but fails at database level
 * - Database connection errors during INSERT
 * - Model event failures (e.g., creating observer throws exception)
 *
 * Enhanced Features:
 * - Store failed data array for debugging
 * - Track model class name
 * - Store database error details
 * - Convert to array for API responses
 *
 * @package Microcrud\Abstracts\Exceptions
 */
class CreateException extends Exception
{
    /**
     * The data that failed to be created
     *
     * @var array
     */
    protected $data = [];

    /**
     * The model class name that failed to be created
     *
     * @var string|null
     */
    protected $modelClass = null;

    /**
     * Database error details (e.g., constraint name, column)
     *
     * @var array
     */
    protected $databaseError = [];

    /**
     * Create a new CreateException instance.
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 400 Bad Request)
     * @param \Exception|null $previous Previous exception for chaining
     * @param array $data Data that failed to be created
     * @param string|null $modelClass Model class name
     * @param array $databaseError Database error details
     */
    public function __construct($message = "", $code = 400, ?\Exception $previous = null, array $data = [], ?string $modelClass = null, array $databaseError = [])
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
        $this->modelClass = $modelClass;
        $this->databaseError = $databaseError;
    }

    /**
     * Get the data that failed to be created.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the data that failed to be created.
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Get the model class name.
     *
     * @return string|null
     */
    public function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * Set the model class name.
     *
     * @param string $modelClass
     * @return $this
     */
    public function setModelClass(string $modelClass)
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * Get database error details.
     *
     * @return array
     */
    public function getDatabaseError()
    {
        return $this->databaseError;
    }

    /**
     * Set database error details.
     *
     * @param array $databaseError
     * @return $this
     */
    public function setDatabaseError(array $databaseError)
    {
        $this->databaseError = $databaseError;
        return $this;
    }

    /**
     * Convert exception to array for API responses.
     *
     * @param bool $includeData Include failed data in response (be careful with sensitive data)
     * @return array
     */
    public function toArray($includeData = false)
    {
        $result = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];

        if ($this->modelClass) {
            $result['model'] = $this->modelClass;
        }

        if (!empty($this->databaseError)) {
            $result['database_error'] = $this->databaseError;
        }

        if ($includeData && !empty($this->data)) {
            $result['data'] = $this->data;
        }

        return $result;
    }

    /**
     * Convert exception to JSON for API responses.
     *
     * @param bool $includeData Include failed data in response
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson($includeData = false, $options = 0)
    {
        return json_encode($this->toArray($includeData), $options);
    }
}
