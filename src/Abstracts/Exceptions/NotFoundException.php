<?php

namespace Microcrud\Abstracts\Exceptions;

use Exception;

/**
 * NotFoundException
 *
 * Exception thrown when a required record or resource is not found.
 *
 * Common scenarios:
 * - Record not found by primary key (setById)
 * - Model instance not set when trying to get()
 * - Data not set when trying to getData()
 * - Required parameter missing from request
 * - Relationship not found
 *
 * Enhanced Features:
 * - Track resource type that wasn't found (model, data, parameter, etc.)
 * - Store search criteria used
 * - Track record ID that wasn't found
 * - Store model class name
 * - Convert to array for API responses
 *
 * @package Microcrud\Abstracts\Exceptions
 */
class NotFoundException extends Exception
{
    /**
     * The type of resource that wasn't found
     *
     * @var string|null
     */
    protected $resourceType = null;

    /**
     * The search criteria used to find the resource
     *
     * @var array
     */
    protected $searchCriteria = [];

    /**
     * The record ID that wasn't found
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
     * Create a new NotFoundException instance.
     *
     * @param string $message Error message
     * @param int $code HTTP status code (default: 404 Not Found)
     * @param \Exception|null $previous Previous exception for chaining
     * @param string|null $resourceType Type of resource (e.g., 'record', 'model', 'data', 'parameter')
     * @param array $searchCriteria Criteria used to search
     * @param mixed $recordId Record ID that wasn't found
     * @param string|null $modelClass Model class name
     */
    public function __construct(
        $message = "",
        $code = 404,
        ?\Exception $previous = null,
        ?string $resourceType = null,
        array $searchCriteria = [],
        $recordId = null,
        ?string $modelClass = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->resourceType = $resourceType;
        $this->searchCriteria = $searchCriteria;
        $this->recordId = $recordId;
        $this->modelClass = $modelClass;
    }

    /**
     * Get the resource type that wasn't found.
     *
     * @return string|null
     */
    public function getResourceType()
    {
        return $this->resourceType;
    }

    /**
     * Set the resource type that wasn't found.
     *
     * @param string $resourceType
     * @return $this
     */
    public function setResourceType(string $resourceType)
    {
        $this->resourceType = $resourceType;
        return $this;
    }

    /**
     * Get the search criteria used.
     *
     * @return array
     */
    public function getSearchCriteria()
    {
        return $this->searchCriteria;
    }

    /**
     * Set the search criteria used.
     *
     * @param array $searchCriteria
     * @return $this
     */
    public function setSearchCriteria(array $searchCriteria)
    {
        $this->searchCriteria = $searchCriteria;
        return $this;
    }

    /**
     * Get the record ID that wasn't found.
     *
     * @return mixed
     */
    public function getRecordId()
    {
        return $this->recordId;
    }

    /**
     * Set the record ID that wasn't found.
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

        if ($this->resourceType) {
            $result['resource_type'] = $this->resourceType;
        }

        if ($this->recordId !== null) {
            $result['record_id'] = $this->recordId;
        }

        if ($this->modelClass) {
            $result['model'] = $this->modelClass;
        }

        if (!empty($this->searchCriteria)) {
            $result['search_criteria'] = $this->searchCriteria;
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
