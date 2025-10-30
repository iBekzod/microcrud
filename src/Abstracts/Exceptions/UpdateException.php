<?php

namespace Microcrud\Abstracts\Exceptions;

use Exception;

/**
 * UpdateException
 *
 * Exception thrown when record update fails.
 *
 * Common scenarios:
 * - Database constraint violations (unique, foreign key, etc.)
 * - Record not found for update
 * - Concurrent modification conflicts (optimistic locking)
 * - Database connection errors during UPDATE
 * - Model event failures (e.g., updating observer throws exception)
 *
 * Enhanced Features:
 * - Store failed update data
 * - Track record ID that failed to update
 * - Store model class name
 * - Store old values for rollback/comparison
 * - Get changed fields comparison
 * - Convert to array for API responses
 *
 * @package Microcrud\Abstracts\Exceptions
 */
class UpdateException extends Exception
{
    /**
     * The data that failed to update the record
     *
     * @var array
     */
    protected $data = [];

    /**
     * The ID of the record that failed to update
     *
     * @var mixed
     */
    protected $recordId = null;

    /**
     * The model class name
     *
     * @var string|null
     */
    protected $modelClass = null;

    /**
     * Old values before update attempt (for comparison/rollback)
     *
     * @var array
     */
    protected $oldValues = [];

    /**
     * Database error details
     *
     * @var array
     */
    protected $databaseError = [];

    /**
     * Create a new UpdateException instance.
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 400 Bad Request)
     * @param \Exception|null $previous Previous exception for chaining
     * @param array $data Data that failed to update
     * @param mixed $recordId Record ID that failed
     * @param string|null $modelClass Model class name
     * @param array $oldValues Old values before update
     * @param array $databaseError Database error details
     */
    public function __construct(
        $message = "",
        $code = 400,
        ?\Exception $previous = null,
        array $data = [],
        $recordId = null,
        ?string $modelClass = null,
        array $oldValues = [],
        array $databaseError = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
        $this->recordId = $recordId;
        $this->modelClass = $modelClass;
        $this->oldValues = $oldValues;
        $this->databaseError = $databaseError;
    }

    /**
     * Get the data that failed to update.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set the data that failed to update.
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
     * Get the record ID that failed to update.
     *
     * @return mixed
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * Set the record ID that failed to update.
     *
     * @param mixed $recordId
     * @return $this
     */
    public function setRecordId($recordId)
    {
        $this->recordId = $recordId;
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
     * Get old values before update attempt.
     *
     * @return array
     */
    public function getOldValues()
    {
        return $this->oldValues;
    }

    /**
     * Set old values before update attempt.
     *
     * @param array $oldValues
     * @return $this
     */
    public function setOldValues(array $oldValues)
    {
        $this->oldValues = $oldValues;
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
     * Get changed fields by comparing old values with new data.
     *
     * @return array Array of ['field' => ['old' => value, 'new' => value]]
     */
    public function getChangedFields()
    {
        if (empty($this->oldValues) || empty($this->data)) {
            return [];
        }

        $changed = [];
        foreach ($this->data as $key => $value) {
            if (isset($this->oldValues[$key]) && $this->oldValues[$key] !== $value) {
                $changed[$key] = [
                    'old' => $this->oldValues[$key],
                    'new' => $value,
                ];
            }
        }

        return $changed;
    }

    /**
     * Convert exception to array for API responses.
     *
     * @param bool $includeData Include failed data in response
     * @param bool $includeOldValues Include old values in response
     * @return array
     */
    public function toArray($includeData = false, $includeOldValues = false)
    {
        $result = [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];

        if ($this->recordId !== null) {
            $result['record_id'] = $this->recordId;
        }

        if ($this->modelClass) {
            $result['model'] = $this->modelClass;
        }

        if (!empty($this->databaseError)) {
            $result['database_error'] = $this->databaseError;
        }

        if ($includeData && !empty($this->data)) {
            $result['data'] = $this->data;
        }

        if ($includeOldValues && !empty($this->oldValues)) {
            $result['old_values'] = $this->oldValues;
            $result['changed_fields'] = $this->getChangedFields();
        }

        return $result;
    }

    /**
     * Convert exception to JSON for API responses.
     *
     * @param bool $includeData Include failed data
     * @param bool $includeOldValues Include old values
     * @param int $options JSON encode options
     * @return string
     */
    public function toJson($includeData = false, $includeOldValues = false, $options = 0)
    {
        return json_encode($this->toArray($includeData, $includeOldValues), $options);
    }
}
