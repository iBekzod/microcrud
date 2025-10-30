<?php

namespace Microcrud\Abstracts\Exceptions;

use Exception;

/**
 * DeleteException
 *
 * Exception thrown when record deletion fails.
 *
 * Common scenarios:
 * - Foreign key constraint violations (can't delete parent with children)
 * - Record not found for deletion
 * - Database connection errors during DELETE
 * - Model event failures (e.g., deleting observer throws exception)
 * - Soft delete failures
 *
 * Enhanced Features:
 * - Track record ID that failed to delete
 * - Store model class name
 * - Track if it was soft delete or force delete
 * - Store related records that prevent deletion
 * - Convert to array for API responses
 *
 * @package Microcrud\Abstracts\Exceptions
 */
class DeleteException extends Exception
{
    /**
     * The ID of the record that failed to delete
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
     * Whether this was a force delete attempt
     *
     * @var bool
     */
    protected $isForceDelete = false;

    /**
     * Related records that prevent deletion (foreign key constraints)
     *
     * @var array
     */
    protected $relatedRecords = [];

    /**
     * Database error details
     *
     * @var array
     */
    protected $databaseError = [];

    /**
     * Create a new DeleteException instance.
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 400 Bad Request)
     * @param \Exception|null $previous Previous exception for chaining
     * @param mixed $recordId Record ID that failed to delete
     * @param string|null $modelClass Model class name
     * @param bool $isForceDelete Whether it was a force delete
     * @param array $relatedRecords Related records preventing deletion
     * @param array $databaseError Database error details
     */
    public function __construct(
        $message = "",
        $code = 400,
        ?\Exception $previous = null,
        $recordId = null,
        ?string $modelClass = null,
        bool $isForceDelete = false,
        array $relatedRecords = [],
        array $databaseError = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->recordId = $recordId;
        $this->modelClass = $modelClass;
        $this->isForceDelete = $isForceDelete;
        $this->relatedRecords = $relatedRecords;
        $this->databaseError = $databaseError;
    }

    /**
     * Get the record ID that failed to delete.
     *
     * @return mixed
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * Set the record ID that failed to delete.
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
     * Check if this was a force delete attempt.
     *
     * @return bool
     */
    public function isForceDelete()
    {
        return $this->isForceDelete;
    }

    /**
     * Set whether this was a force delete attempt.
     *
     * @param bool $isForceDelete
     * @return $this
     */
    public function setIsForceDelete(bool $isForceDelete)
    {
        $this->isForceDelete = $isForceDelete;
        return $this;
    }

    /**
     * Get related records that prevent deletion.
     *
     * @return array
     */
    public function getRelatedRecords()
    {
        return $this->relatedRecords;
    }

    /**
     * Set related records that prevent deletion.
     *
     * @param array $relatedRecords
     * @return $this
     */
    public function setRelatedRecords(array $relatedRecords)
    {
        $this->relatedRecords = $relatedRecords;
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
     * Check if deletion failed due to related records.
     *
     * @return bool
     */
    public function hasRelatedRecords()
    {
        return !empty($this->relatedRecords);
    }

    /**
     * Convert exception to array for API responses.
     *
     * @return array
     */
    public function toArray()
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

        if ($this->isForceDelete) {
            $result['force_delete'] = true;
        }

        if (!empty($this->relatedRecords)) {
            $result['related_records'] = $this->relatedRecords;
        }

        if (!empty($this->databaseError)) {
            $result['database_error'] = $this->databaseError;
        }

        return $result;
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
