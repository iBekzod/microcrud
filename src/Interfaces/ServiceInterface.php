<?php

namespace Microcrud\Interfaces;

/**
 * Interface ServiceInterface
 *
 * Contract for service classes handling business logic and data operations.
 * Implementations should encapsulate all CRUD and related logic for a model.
 */
interface ServiceInterface
{
    /**
     * Set the primary key name for the model.
     * @param string $private_key_name
     * @return $this
     */
    public function setPrivateKeyName(string $private_key_name);

    /**
     * Get the current model instance.
     * @return mixed
     */
    public function get();

    /**
     * Set the current model instance.
     * @param mixed $model
     * @return $this
     */
    public function set($model);

    /**
     * Set the model by primary key from data.
     * @param array $data
     * @return $this
     */
    public function setById(array $data);

    /**
     * Get the query builder for the model.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQuery();

    /**
     * Get the validation rules for the service.
     * @return array
     */
    public function getRules();

    /**
     * Get the current data payload.
     * @return array
     */
    public function getData();

    /**
     * Set the data payload for the service.
     * @param array $data
     * @return $this
     */
    public function setData(array $data);

    /**
     * Hook before creating a model.
     * @return $this
     */
    public function beforeCreate();

    /**
     * Dispatch a job to create a model.
     * @param array $data
     * @return $this
     */
    public function createJob(array $data);

    /**
     * Create a new model instance.
     * @param array $data
     * @return $this
     */
    public function create(array $data);

    /**
     * Hook after creating a model.
     * @return $this
     */
    public function afterCreate();

    /**
     * Hook before updating a model.
     * @return $this
     */
    public function beforeUpdate();

    /**
     * Dispatch a job to update a model.
     * @param array $data
     * @return $this
     */
    public function updateJob(array $data);

    /**
     * Update the current model instance.
     * @param array $data
     * @return $this
     */
    public function update(array $data);

    /**
     * Hook after updating a model.
     * @return $this
     */
    public function afterUpdate();

    /**
     * Create or update a model based on data.
     * @param array $data
     * @return $this
     */
    public function createOrUpdate(array $data);

    /**
     * Hook before deleting a model.
     * @return $this
     */
    public function beforeDelete();

    /**
     * Delete the current model instance.
     * @return $this
     */
    public function delete();

    /**
     * Hook after deleting a model.
     * @return $this
     */
    public function afterDelete();
}