<?php

namespace Microcrud\Abstracts;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Microcrud\Responses\ItemResource;
use Illuminate\Support\Facades\Schema;
use Microcrud\Abstracts\Jobs\StoreJob;
use Microcrud\Abstracts\Jobs\UpdateJob;
use Illuminate\Support\Facades\Validator;
use Microcrud\Interfaces\ServiceInterface;
use Illuminate\Database\Eloquent\SoftDeletes;
use Microcrud\Abstracts\Exceptions\CreateException;
use Microcrud\Abstracts\Exceptions\UpdateException;
use Microcrud\Abstracts\Exceptions\NotFoundException;
use Microcrud\Abstracts\Exceptions\ValidationException;

/**
 * Service
 *
 * Abstract service class providing comprehensive CRUD operations with advanced features:
 * - Database transactions with configurable control
 * - Intelligent caching with Redis/Memcached tagging support
 * - Background job processing for async operations
 * - Dynamic filtering and ordering (search_by_*, order_by_*)
 * - Soft delete support
 * - Multi-database support (MySQL, PostgreSQL, SQLite, SQL Server)
 * - Automatic validation rule generation based on model columns
 * - Bulk operations (create, update, delete, restore)
 * - Hook system (before/after operations)
 * - Automatic cache invalidation on data changes
 *
 * @package Microcrud\Abstracts
 */
abstract class Service implements ServiceInterface
{
    /**
     * The Eloquent model instance being operated on
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * Request data array for current operation
     *
     * @var array
     */
    protected $data = [];

    /**
     * Primary key column name (default: 'id')
     *
     * @var string
     */
    protected $private_key_name = 'id';

    /**
     * Flag indicating if current operation is running as background job
     *
     * @var bool
     */
    protected $is_job = false;

    /**
     * Custom query builder instance
     *
     * @var \Illuminate\Database\Eloquent\Builder|null
     */
    protected $query = null;

    /**
     * API resource class for response formatting
     *
     * @var string
     */
    protected $resource = null;

    /**
     * Enable/disable caching for queries
     *
     * @var bool
     */
    protected $is_cacheable = false;

    /**
     * Enable/disable automatic database transactions
     *
     * @var bool
     */
    protected $is_transaction_enabled = true;

    /**
     * Enable/disable pagination for index queries
     *
     * @var bool
     */
    protected $is_paginated = true;

    /**
     * Cache expiration time (Carbon instance)
     *
     * @var \Illuminate\Support\Carbon|null
     */
    protected $cache_expires_at = null;

    /**
     * Flag to replace or merge custom validation rules
     *
     * @var bool
     */
    protected $is_replace_rules = false;

    /**
     * Custom validation rules
     *
     * @var array
     */
    protected $rules = [];

    /**
     * Additional data to include in responses (e.g., counts, metadata)
     *
     * @var array
     */
    protected $extra_data = [];

    /**
     * Collection of items (paginated or all)
     *
     * @var \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator|null
     */
    protected $items;

    /**
     * Static cache for database column types to avoid repeated queries
     *
     * @var array
     */
    protected static $columnTypesCache = [];

    /**
     * Service constructor.
     *
     * Initializes service with model and resource class.
     * Sets default cache expiration to 1 day.
     *
     * @param \Illuminate\Database\Eloquent\Model|null $model The Eloquent model instance
     * @param string|null $resource API resource class for formatting responses
     */
    public function __construct($model = null, $resource = null)
    {
        $this->model = $model;
        $this->cache_expires_at = Carbon::now()->addDay();
        $this->resource = $resource ?? ItemResource::class;
    }
    /**
     * Set the primary key column name.
     *
     * @param string $private_key_name Column name (e.g., 'id', 'uuid')
     * @return $this
     */
    public function setPrivateKeyName($private_key_name)
    {
        $this->private_key_name = $private_key_name;
        return $this;
    }

    /**
     * Get the primary key column name.
     *
     * @return string
     */
    public function getPrivateKeyName()
    {
        return $this->private_key_name;
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    public function getIsCacheable()
    {
        return $this->is_cacheable;
    }

    /**
     * Enable or disable caching for this service.
     *
     * @param bool $is_cacheable
     * @return $this
     */
    public function setIsCacheable($is_cacheable = true)
    {
        $this->is_cacheable = $is_cacheable;
        return $this;
    }

    /**
     * Get extra metadata to include in API responses.
     *
     * @return array
     */
    public function getExtraData()
    {
        return $this->extra_data;
    }

    /**
     * Set extra metadata for API responses (e.g., counts, statistics).
     *
     * @param array $extra_data
     * @return $this
     */
    public function setExtraData($extra_data = [])
    {
        $this->extra_data = $extra_data;
        return $this;
    }

    /**
     * Check if automatic database transactions are enabled.
     *
     * @return bool
     */
    public function getIsTransactionEnabled()
    {
        return $this->is_transaction_enabled;
    }

    /**
     * Enable or disable automatic database transactions.
     *
     * @param bool $is_transaction_enabled
     * @return $this
     */
    public function setIsTransactionEnabled($is_transaction_enabled = true)
    {
        $this->is_transaction_enabled = $is_transaction_enabled;
        return $this;
    }

    /**
     * Check if pagination is enabled for index queries.
     *
     * @return bool
     */
    public function getIsPaginated()
    {
        return $this->is_paginated;
    }

    /**
     * Enable or disable pagination for index queries.
     *
     * @param bool $is_paginated
     * @return $this
     */
    public function setIsPaginated($is_paginated = true)
    {
        $this->is_paginated = $is_paginated;
        return $this;
    }

    /**
     * Get cache expiration time.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function getCacheExpiresAt()
    {
        return $this->cache_expires_at;
    }

    /**
     * Set cache expiration time.
     *
     * @param \Illuminate\Support\Carbon $time
     * @return $this
     */
    public function setCacheExpiresAt($time)
    {
        $this->cache_expires_at = $time;
        return $this;
    }

    /**
     * Check if the current cache driver supports tagging.
     * Only Redis, Memcached, and DynamoDB support cache tagging.
     *
     * @return bool
     */
    protected function cacheSupportsTagging()
    {
        try {
            $driver = Config::get('cache.default');
            $store = Cache::getStore();

            // Check if the store has the tags method and the driver supports it
            return method_exists($store, 'tags') &&
                   in_array($driver, ['redis', 'memcached', 'dynamodb']);
        } catch (\Exception $e) {
            Log::warning("Failed to check cache tagging support: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Validate and potentially auto-disable caching if misconfigured.
     *
     * @return void
     */
    protected function validateCacheConfiguration()
    {
        if (!$this->getIsCacheable()) {
            return;
        }

        try {
            // Test if cache is available
            Cache::get('__microcrud_test__');

            // Check if tagging is required but not supported
            if (!$this->cacheSupportsTagging()) {
                $driver = Config::get('cache.default');
                $message = "MicroCRUD: Cache driver '{$driver}' doesn't support tagging. ";

                if (Config::get('microcrud.cache.auto_disable_on_error', true)) {
                    $message .= "Caching has been auto-disabled.";
                    $this->setIsCacheable(false);
                } else {
                    $message .= "Cache operations may fail.";
                }

                Log::warning($message);
            }
        } catch (\Exception $e) {
            $message = "MicroCRUD: Cache is not available: {$e->getMessage()}. ";

            if (Config::get('microcrud.cache.auto_disable_on_error', true)) {
                $message .= "Caching has been auto-disabled.";
                $this->setIsCacheable(false);
            }

            Log::warning($message);
        }
    }

    /**
     * Flush cache for the model, handling both tagging and non-tagging drivers.
     *
     * @param string|null $tag Optional tag to flush (defaults to model table name)
     * @return void
     */
    protected function flushModelCache($tag = null)
    {
        if (!$this->getIsCacheable()) {
            return;
        }

        try {
            $tag = $tag ?? $this->getModelTableName();

            if ($this->cacheSupportsTagging()) {
                Cache::tags($tag)->flush();
            } else {
                // For non-tagging drivers, we can't selectively flush
                // Log a warning but don't flush everything
                Log::debug("MicroCRUD: Cache flush requested but driver doesn't support tagging. Skipping flush.");
            }
        } catch (\Exception $e) {
            Log::warning("MicroCRUD: Failed to flush cache: {$e->getMessage()}");
        }
    }

    /**
     * Validate queue configuration before dispatching jobs.
     *
     * @return bool
     */
    protected function validateQueueConfiguration()
    {
        if (!Config::get('microcrud.queue.validate', true)) {
            return true; // Skip validation if disabled
        }

        try {
            $connection = Config::get('queue.default');

            if (!$connection || $connection === 'null') {
                Log::warning("MicroCRUD: Queue connection is not configured.");
                return false;
            }

            if ($connection === 'sync') {
                Log::info("MicroCRUD: Queue connection is 'sync'. Jobs will run synchronously.");
            }

            return true;
        } catch (\Exception $e) {
            Log::error("MicroCRUD: Failed to validate queue configuration: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Get the current model instance.
     *
     * @return \Illuminate\Database\Eloquent\Model
     * @throws NotFoundException If model is not set
     */
    public function get()
    {
        if (!isset($this->model)) {
            throw new NotFoundException();
        }
        return $this->model;
    }

    /**
     * Get the query builder instance.
     *
     * Returns custom query if set, otherwise creates new query from model.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getQuery()
    {
        return $this->query ?? $this->model::query();
    }

    /**
     * Set a custom query builder instance.
     *
     * Useful for applying custom scopes or joins before operations.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    /**
     * Get custom validation rules.
     *
     * @return array
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * Set custom validation rules.
     *
     * @param array $rules Validation rules array
     * @param bool $is_replace_rules If true, replaces default rules; if false, merges with defaults
     * @return $this
     */
    public function setRules($rules, $is_replace_rules = false)
    {
        $this->rules = $rules;
        $this->is_replace_rules = $is_replace_rules;
        return $this;
    }

    /**
     * Set the model instance for current operation.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return $this
     * @throws NotFoundException If model is null
     */
    public function set($model)
    {
        if (isset($model)) {
            $this->model = $model;
        } else {
            throw new NotFoundException();
        }
        return $this;
    }

    /**
     * Load a model by its primary key with optional caching.
     *
     * Fetches model from database or cache based on primary key value in $data.
     * Supports cache tagging for Redis/Memcached and fallback for other drivers.
     *
     * @param array $data Data array containing primary key (e.g., ['id' => 1])
     * @return $this
     * @throws NotFoundException If primary key not found in data or model not found in database
     */
    public function setById($data = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }
        if (array_key_exists($this->getPrivateKeyName(), $data)) {
            if ($this->getIsCacheable()) {
                $this->validateCacheConfiguration();

                if ($this->getIsCacheable()) { // Re-check after validation
                    ksort($data);
                    $item_key = $this->getModelTableName() . ':' . serialize($data);

                    if ($this->cacheSupportsTagging()) {
                        $model = Cache::tags([$this->getModelTableName()])
                            ->remember(
                                $item_key,
                                $this->getCacheExpiresAt(),
                                function () use ($data) {
                                    return $this->withoutScopes()->getQuery()->where($this->getPrivateKeyName(), $data[$this->getPrivateKeyName()])->first();
                                }
                            );
                    } else {
                        // Fallback for drivers that don't support tagging
                        $model = Cache::remember(
                            $item_key,
                            $this->getCacheExpiresAt(),
                            function () use ($data) {
                                return $this->withoutScopes()->getQuery()->where($this->getPrivateKeyName(), $data[$this->getPrivateKeyName()])->first();
                            }
                        );
                    }
                } else {
                    $model = $this->withoutScopes()->getQuery()->where($this->getPrivateKeyName(), $data[$this->getPrivateKeyName()])->first();
                }
            } else {
                $model = $this->withoutScopes()->getQuery()->where($this->getPrivateKeyName(), $data[$this->getPrivateKeyName()])->first();
            }
            if ($model) {
                $this->set($model);
            } else {
                throw new NotFoundException("Not found with {$this->getPrivateKeyName()}:" . $data[$this->getPrivateKeyName()]);
            }
        } else {
            throw new NotFoundException('Request key not found with name:' . $this->getPrivateKeyName());
        }
        return $this;
    }

    /**
     * Get the current request data array.
     *
     * @return array
     * @throws NotFoundException If data is not set
     */
    public function getData()
    {
        if (!isset($this->data)) {
            throw new NotFoundException();
        }
        return $this->data;
    }

    /**
     * Set request data for current operation.
     *
     * @param array $data Request data (e.g., from request()->all())
     * @return $this
     * @throws NotFoundException If data is null
     */
    public function setData($data)
    {
        if (isset($data)) {
            Log::info("Set data:", $data);
            $this->data = $data;
        } else {
            throw new NotFoundException();
        }
        return $this;
    }

    /**
     * Get the collection of items (from index/paginated queries).
     *
     * @return \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator|null
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set the collection of items.
     *
     * @param \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator $items
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }
    /**
     * Automatically add 'exists' validation rule for foreign key columns.
     *
     * Detects columns ending with '_id' or '_uuid' and adds exists validation
     * by checking if a relationship method exists on the model.
     *
     * @param string $key Column name (e.g., 'user_id', 'post_uuid')
     * @param string $rule Existing validation rule
     * @return string Enhanced validation rule with exists check
     */
    private function getRelationRule($key, $rule)
    {
        $relation_key_types = ['id', 'uuid'];

        foreach ($relation_key_types as $relation_key_type) {
            if (str_ends_with($key, '_' . $relation_key_type)) {
                $relation_table = Str::plural(str_replace('_' . $relation_key_type, '', $key));
                $relation = Str::camel(str_replace('_' . $relation_key_type, '', $key));

                // Check if model has a relationship method
                if (
                    method_exists($this->model, $relation)
                    && $this->model->{$relation}() instanceof \Illuminate\Database\Eloquent\Relations\Relation
                ) {
                    $relation_model = (new $this->model)->{$relation}()->getRelated();
                    $tableName = $this->getModelTableName($relation_model);
                    $schema = $relation_model->getConnectionName();
                    $relation_keys = $this->getModelColumns($relation_model);

                    if (in_array($key, $relation_keys)) {
                        $rule = $rule . "|exists:{$schema}.{$tableName},{$key}";
                    } else {
                        $rule = $rule . "|exists:{$schema}.{$tableName},{$relation_key_type}";
                    }
                } else {
                    // Fallback: Check if table exists by convention
                    try {
                        if (Schema::hasTable($relation_table)) {
                            $rule = $rule . "|exists:{$relation_table},{$relation_key_type}";
                        }
                    } catch (\Exception $e) {
                        Log::warning("MicroCRUD: Could not check table existence for {$relation_table}: {$e->getMessage()}");
                    }
                }
            }
        }

        return $rule;
    }

    /**
     * Validate data against rules using Laravel validator.
     *
     * Merges custom rules with provided rules and validates data.
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array Validated data
     * @throws ValidationException If validation fails
     */
    public function globalValidation($data, $rules = [])
    {
        $custom_rules = $this->getRules();
        if (!empty($custom_rules)) {
            if ($this->is_replace_rules) {
                $rules = $custom_rules;
            } else {
                $rules = array_merge($custom_rules, $rules);
            }
        }
        if (count($rules)) {
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                throw new ValidationException($validator->errors()->first(), 422);
            }
            $data = $validator->validated();
        }
        return $data;
    }

    public function withoutScopes($scopes = [])
    {
        $new_query = $this->getQuery();
        if (count($scopes) > 0) {
            foreach ($scopes as $scope) {
                $new_query = $new_query->withoutGlobalScope($scope);
            }
        } else {
            $new_query->withoutGlobalScopes();
        }
        $this->setQuery($new_query);
        return $this;
    }

    public function getModelColumns($model = null)
    {
        if (!$model) {
            $model = $this->model;
        }

        try {
            $keys = $model->getConnection()->getSchemaBuilder()->getColumnListing($this->getModelTableName($model));
            return $keys;
        } catch (\Exception $e) {
            $message = "MicroCRUD: Failed to get model columns: {$e->getMessage()}. ";
            $message .= "Ensure your database is configured and accessible.";
            Log::error($message);

            if (Config::get('microcrud.features.strict_mode', false)) {
                throw new \RuntimeException($message, 0, $e);
            }

            // Return empty array in non-strict mode
            return [];
        }
    }
    public function getModelTableName($model = null)
    {
        if (!$model) {
            $model = $this->model;
        }
        return $model->getTable();
    }

    public function is_soft_delete()
    {
        return in_array(SoftDeletes::class, array_keys((new \ReflectionClass($this->model))->getTraits()));
    }

    public function getItemResource()
    {
        return (isset($this->resource) ? $this->resource : ItemResource::class);
    }
    public function setItemResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }
    public function beforeIndex()
    {
        $data = $this->getData();
        $query = $this->getQuery();
        $query = $this->applyDynamicFilters($query, $data);
        $this->setQuery($query);
        return $this;
    }

    public function afterIndex()
    {
        return $this;
    }
    public function getAll()
    {
        if(!$items = $this->getItems()) {
            $items = $this->getQuery()->get();
            $this->setItems($items);
        }
        $this->afterIndex();
        return $this->getItems();
    }

    public function getPaginated($modelQuery = null, $modelTableName = null, $is_cacheable = false)
    {
        $data = request()->all();
        if (!isset(request()->page)) {
            request()->merge([
                'page' => 1
            ]);
        }
        if (!isset(request()->limit)) {
            request()->merge([
                'limit' => 10
            ]);
        }
        if (!$modelQuery) {
            $modelQuery = $this->getQuery();
        }

        if (!$modelTableName) {
            $modelTableName = $this->getModelTableName();
        }
        $limit = request()->limit ?? 10;
        if ($this->getIsCacheable()) {
            $this->validateCacheConfiguration();

            if ($this->getIsCacheable()) { // Re-check after validation
                ksort($data);
                $item_key = request()->path() . ":" . $modelTableName . ":" . serialize($data);

                if ($this->cacheSupportsTagging()) {
                    $items = Cache::tags([$modelTableName])
                        ->remember(
                            $item_key,
                            $this->getCacheExpiresAt(),
                            function () use ($modelQuery, $limit) {
                                return $modelQuery->paginate($limit);
                            }
                        );
                } else {
                    // Fallback for non-tagging drivers
                    $items = Cache::remember(
                        $item_key,
                        $this->getCacheExpiresAt(),
                        function () use ($modelQuery, $limit) {
                            return $modelQuery->paginate($limit);
                        }
                    );
                }
            } else {
                $items = $modelQuery->paginate($limit);
            }
        } else {
            $items =  $modelQuery->paginate($limit);
        }
        $this->setItems($items)->afterIndex();
        return $this->getItems();
    }
    public function beforeShow()
    {
        return $this;
    }

    public function afterShow()
    {
        return $this;
    }
    public function beforeCreate()
    {
        return $this;
    }
    public function createJob($data = [])
    {
        $this->is_job = true;
        if (empty($data)) {
            $data = $this->getData();
        }

        if (!$this->validateQueueConfiguration()) {
            if (Config::get('microcrud.queue.auto_disable_on_error', true)) {
                Log::warning("MicroCRUD: Queue validation failed. Running create synchronously instead.");
                return $this->create($data);
            }
        }

        try {
            // Use Queue facade to push the job
            Queue::push(new StoreJob($data, $this));
        } catch (\Exception $e) {
            Log::error("MicroCRUD: Failed to dispatch create job: {$e->getMessage()}");

            if (Config::get('microcrud.queue.auto_disable_on_error', true)) {
                Log::warning("MicroCRUD: Falling back to synchronous create.");
                return $this->create($data);
            }

            throw $e;
        }

        return $this;
    }
    /**
     * @throws CreateException
     */
    public function create($data = [])
    {
        if ($this->getIsTransactionEnabled())
            DB::beginTransaction();
        try {
            if (!empty($data)) {
                $this->setData($data);
            }
            $this->beforeCreate();
            $data = $this->getData();
            $keys = $this->getModelColumns();
            $filtered_data = array_intersect_key($data, array_flip($keys));
            $model = $this->model::create($filtered_data);
            $this->set($model);
            Log::info("Model created:");
            Log::info($model);
        } catch (\Exception $exception) {
            if ($this->getIsTransactionEnabled())
                DB::rollBack();
            $message = "Cannot create. ERROR:{$exception->getMessage()}. TRACE: {$exception->getTraceAsString()}";
            if ($this->is_job) {
                Log::error($message);
            } else {
                throw new CreateException($exception->getMessage(), (is_int($exception->getCode())) ? $exception->getCode() : 400, $exception);
            }
        }
        if ($this->getIsTransactionEnabled() || $this->is_job)
            DB::commit();
        $this->afterCreate();
        return $this;
    }
    public function afterCreate()
    {
        $this->flushModelCache();
        return $this;
    }

    public function composeItems(array $data = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }
        $new_items = [];
        $values = $data;
        if (array_key_exists('items', $data)) {
            $items = $values['items'];
            unset($values['items']);
            foreach ($items as $item) {
                $new_items[] = array_merge($values, $item);
            }
        } else {
            $new_items[] = $values;
        }
        return $new_items;
    }
    public function beforeUpdate()
    {
        return $this;
    }
    public function updateJob($data = [])
    {
        $this->is_job = true;
        if (empty($data)) {
            $data = $this->getData();
        }

        if (!$this->validateQueueConfiguration()) {
            if (Config::get('microcrud.queue.auto_disable_on_error', true)) {
                Log::warning("MicroCRUD: Queue validation failed. Running update synchronously instead.");
                return $this->update($data);
            }
        }

        try {
            // Use Queue facade to push the job
            Queue::push(new UpdateJob($data, $this));
        } catch (\Exception $e) {
            Log::error("MicroCRUD: Failed to dispatch update job: {$e->getMessage()}");

            if (Config::get('microcrud.queue.auto_disable_on_error', true)) {
                Log::warning("MicroCRUD: Falling back to synchronous update.");
                return $this->update($data);
            }

            throw $e;
        }

        return $this;
    }
    /**
     * @throws UpdateException
     */
    public function update($data = [])
    {
        if ($this->getIsTransactionEnabled())
            DB::beginTransaction();
        try {
            if (!empty($data)) {
                $this->setData($data);
            }
            $this->beforeUpdate();
            $data = $this->getData();
            $keys = $this->getModelColumns();
            $filtered_data = array_intersect_key($data, array_flip($keys));
            $this->get()->update($filtered_data);
            $this->get()->refresh();
        } catch (\Exception $exception) {
            if ($this->getIsTransactionEnabled())
                DB::rollBack();
            $message = "Cannot update. ERROR:{$exception->getMessage()}. TRACE: {$exception->getTraceAsString()}";
            if ($this->is_job) {
                Log::error($message);
            } else {
                throw new UpdateException($exception->getMessage(), (is_int($exception->getCode())) ? $exception->getCode() : 400, $exception);
            }
        }
        if ($this->getIsTransactionEnabled() || $this->is_job)
            DB::commit();
        $this->afterUpdate();
        return $this;
    }
    public function afterUpdate()
    {
        $this->flushModelCache();
        return $this;
    }

    public function beforeBulkAction()
    {
        return $this;
    }
    public function bulkActionJob($data = [])
    {
        $items = $this->composeItems();
        if (isset($data["bulk_action"]) && in_array($data["bulk_action"], ['create', 'update'])) {
            switch ($data["bulk_action"]) {
                case 'create':
                    foreach ($items as $item) {
                        $this->createJob($item);
                    }
                    break;
                case 'update':
                    foreach ($items as $item) {
                        $this->updateJob($item);
                    }
                    break;
                default:
                    break;
            }
        } else {
            throw new ValidationException("bulk_action parameter required with one of values:update or create for job!");
        }

        return $this;
    }
    /**
     * @throws \Exception
     */
    public function bulkAction($data = [])
    {
        if ($this->getIsTransactionEnabled())
            DB::beginTransaction();
        try {
            if (!empty($data)) {
                $this->setData($data);
            }
            $this->beforeBulkAction();
            $total_count = 1;
            $success_count = 0;
            $items = $this->composeItems();
            $changed_items = collect();
            foreach ($items as $item) {
                $this->setData($item);
                if (isset($item["bulk_action"]) && in_array($item["bulk_action"], ['create', 'update', 'show', 'delete', 'restore'])) {
                    switch ($item["bulk_action"]) {
                        case 'create':
                            $model = $this->create()->get();
                            break;
                        case 'update':
                            $model = $this->setById()->update()->get();
                            break;
                        case 'show':
                            $model = $this->beforeShow()->setById()->afterShow()->get();
                            break;
                        case 'delete':
                            $model = $this->setById()->get();
                            $this->delete();
                            break;
                        case 'restore':
                            $model = $this->setById()->restore()->get();
                            break;
                        default:
                            break;
                    }                    
                    $changed_items->push($model);
                }else{
                    throw new ValidationException("bulk_action parameter required with one of values:update or create for job!");
                }
                $success_count++;
            }
            $this->setData($data);
            $this->setItems($changed_items);
            $this->setExtraData(array_merge($this->getExtraData(), [
                'total_count' => $total_count,
                'success_count' => $success_count,
            ]));
        } catch (\Exception $exception) {
            if ($this->getIsTransactionEnabled())
                DB::rollBack();
            $message = "Cannot update. ERROR:{$exception->getMessage()}. TRACE: {$exception->getTraceAsString()}";
            if ($this->is_job) {
                Log::error($message);
            } else {
                throw new \Exception($exception->getMessage(), (is_int($exception->getCode())) ? $exception->getCode() : 400, $exception);
            }
        }
        if ($this->getIsTransactionEnabled() || $this->is_job)
            DB::commit();
        $this->afterBulkAction();
        return $this;
    }
    public function afterBulkAction()
    {
        $this->flushModelCache();
        return $this;
    }
    public function createOrUpdate($data = [], $conditions = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }
        if (count($conditions)) {    
            $keys = $this->getModelColumns();
            $conditions = array_intersect_key($conditions, array_flip($keys));
            if ($model = $this->getQuery()->where($conditions)->first()) {
                $this->set($model)->update($data);
                return $this;
            }
        }
        if (array_key_exists($this->getPrivateKeyName(), $data)) {
            if ($model = $this->getQuery()->where($this->getPrivateKeyName(), $data[$this->getPrivateKeyName()])->first()) {
                $this->set($model)->update($data);
                return $this;
            }
        }
        $this->create($data);
        return $this;
    }
    public function beforeDelete()
    {
        return $this;
    }
    public function delete()
    {
        $this->beforeDelete();
        $data = $this->getData();
        if (array_key_exists('is_force_destroy', $data) && $data['is_force_destroy'] && $this->is_soft_delete()) {
            $this->get()->forceDelete();
        } else {
            $this->get()->delete();
        }
        $this->afterDelete();
        return $this;
    }
    public function afterDelete()
    {
        $this->flushModelCache();
        return $this;
    }
    public function beforeRestore()
    {
        return $this;
    }
    public function restore()
    {
        if ($this->is_soft_delete()) {
            $this->beforeRestore();
            $this->get()->restore();
        }
        $this->afterRestore();
        return $this;
    }
    public function afterRestore()
    {
        $this->flushModelCache();
        return $this;
    }
    public function indexRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            $base_rules = [
                'trashed_status' => 'sometimes|integer|in:-1,0,1',
                'is_all' => 'sometimes|boolean',
                'search' => 'sometimes|string',
                'updated_at' => 'sometimes',
            ];
            $model_columns = $this->getModelColumns();
            $column_types = $this->getColumnTypes();
            foreach ($model_columns as $column) {
                $column_type = $column_types[$column] ?? 'string';

                switch ($column_type) {
                    case 'integer':
                        $base_rules["search_by_{$column}"] = 'sometimes|integer';
                        $base_rules["search_by_{$column}_min"] = 'sometimes|integer';
                        $base_rules["search_by_{$column}_max"] = 'sometimes|integer';
                        break;
                    case 'numeric':
                        $base_rules["search_by_{$column}"] = 'sometimes|numeric';
                        $base_rules["search_by_{$column}_min"] = 'sometimes|numeric';
                        $base_rules["search_by_{$column}_max"] = 'sometimes|numeric';
                        break;
                    case 'date':
                        $base_rules["search_by_{$column}"] = 'sometimes|date';
                        $base_rules["search_by_{$column}_from"] = 'sometimes|date';
                        $base_rules["search_by_{$column}_to"] = 'sometimes|date';
                        break;
                    case 'boolean':
                        $base_rules["search_by_{$column}"] = 'sometimes|boolean';
                        break;
                    default:
                        $base_rules["search_by_{$column}"] = 'sometimes|nullable';
                        break;
                }

                $base_rules["order_by_{$column}"] = 'sometimes|in:asc,desc';
            }
            $rules = array_merge($base_rules, $rules);
            if ($this->getIsPaginated()) {
                return array_merge([
                    'page' => 'sometimes|numeric|min:1',
                    'limit' => 'sometimes|numeric|min:1',
                ], $rules);
            } else {
                return $rules;
            }
        }
    }
    public function showRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            return array_merge($this->getIdRule(), $rules);
        }
    }
    public function createRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            $keys = $this->getModelColumns();
            $model_rules = [];
            $required_fields = [];
            $exceptional_fields = [$this->getPrivateKeyName(), 'updated_at', 'created_at', 'deleted_at'];
            foreach ($keys as $key) {
                // $type = Schema::getColumnType($table, $key);
                // $model_rules[$key]='required|'.$type;
                if (in_array($key, $exceptional_fields)) {
                    //skip
                    continue;
                }
                $rule = 'required';
                if (in_array($key, $required_fields)) {
                    $rule = 'required';
                }
                $rule = $this->getRelationRule($key, $rule);
                $model_rules[$key] = $rule;
            }
            $model_rules['is_job'] = 'sometimes|boolean';
            return array_merge($model_rules, $rules);
        }
    }
    public function updateRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            $keys = $this->getModelColumns();
            $model_rules = [];
            $required_fields = [$this->getPrivateKeyName()];
            $exceptional_fields = ['updated_at', 'created_at', 'deleted_at'];
            foreach ($keys as $key) {
                // $type = Schema::getColumnType($table, $key);
                // $model_rules[$key]='required|'.$type;
                if (in_array($key, $exceptional_fields)) {
                    continue;
                }
                $rule = 'sometimes';
                if (in_array($key, $required_fields)) {
                    $tableName = $this->getModelTableName();
                    $schema = $this->model->getConnectionName();
                    $rule = "required|exists:{$schema}.{$tableName},{$key}";
                }
                $rule = $this->getRelationRule($key, $rule);
                $model_rules[$key] = $rule;
            }
            $model_rules['is_job'] = 'sometimes|boolean';
            return array_merge($model_rules, $rules);
        }
    }

    public function bulkActionRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        }
        $action = '';
        if (request()->has('bulk_action') && in_array(request()->bulk_action, ['create', 'update', 'delete', 'restore', 'show'])) {
            $action =  request()->bulk_action;
        } else {
            throw new ValidationException('bulk_action parameter must be one of theese actions: create, update, show, delete, restore', 422);
        }
        $action_rules = [];
        switch ($action) {
            case 'create':
                $action_rules = $this->createRules();
                break;
            case 'update':
                $action_rules = $this->updateRules();
                break;
            case 'show':
                $action_rules = $this->showRules();
                break;
            case 'delete':
                $action_rules = $this->deleteRules();
                break;
            case 'restore':
                $action_rules = $this->restoreRules();
                break;
            default:
                break;
        }
        $bulk_rules = [
            'bulk_action' => 'sometimes|in:create,update,delete,restore,show',
            'items.*.bulk_action' => 'sometimes|in:create,update,delete,restore,show',
            'items' => 'sometimes|array',
        ];
        foreach ($action_rules as $field => $rule_string) {
            $is_array = false;
            if (is_array($rule_string)) {
                $is_array = true;
                $rules_array = $rule_string;
            } else {
                $rules_array = explode('|', $rule_string);
            }
            $has_required = in_array('required', $rules_array);
            $other_rules = array_filter($rules_array, function ($rule) {
                return !in_array($rule, ['required', 'sometimes']);
            });
            $is_primary_key = ($field === $this->getPrivateKeyName());
            if ($is_primary_key) {
                if ($is_array) {
                    $bulk_rules["items.*.$field"] = array_merge(["required"], $other_rules);
                } else {
                    $other_rules_string = !empty($other_rules) ? '|' . implode('|', $other_rules) : '';
                    $bulk_rules["items.*.$field"] = "required" . $other_rules_string;
                }
            } else {
                if ($is_array) {
                    if ($has_required) {
                        $bulk_rules[$field] = array_merge(["sometimes"], $other_rules);
                        $bulk_rules["items.*.$field"] = array_merge(["required_without:$field"], $other_rules);
                    } else {
                        $bulk_rules[$field] = array_merge(["sometimes"], $other_rules);
                        $bulk_rules["items.*.$field"] = array_merge(["sometimes"], $other_rules);
                    }
                } else {
                    $other_rules_string = !empty($other_rules) ? '|' . implode('|', $other_rules) : '';
                    if ($has_required) {
                        $bulk_rules[$field] = "sometimes" . $other_rules_string;
                        $bulk_rules["items.*.$field"] = "required_without:$field" . $other_rules_string;
                    } else {
                        $bulk_rules[$field] = "sometimes" . $other_rules_string;
                        $bulk_rules["items.*.$field"] = "sometimes" . $other_rules_string;
                    }
                }
            }
        }
        return array_merge($bulk_rules, $rules);
    }
    public function deleteRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            $model_rules = array_merge($this->getIdRule(), [
                'is_force_destroy' => 'sometimes|boolean'
            ]);
            return array_merge($model_rules, $rules);
        }
    }

    public function restoreRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            return array_merge($this->getIdRule(), $rules);
        }
    }

    public function getIdRule($model = null, $key = null)
    {
        if (!$model) {
            $model = $this->model;
        }
        if (!$key) {
            $key = $this->getPrivateKeyName();
        }
        $tableName = $this->getModelTableName($model);
        $schema = $model->getConnectionName();
        return [
            $key => "required|exists:{$schema}.{$tableName}," . $key
        ];
    }

    public function getColumnTypes($model = null, $useCache = true)
    {
        if (!$model) {
            $model = $this->model;
        }
        
        $tableName = $this->getModelTableName($model);
        $cacheKey = get_class($model) . '_' . $tableName . '_column_types';
        
        // Return cached version if available
        if ($useCache && isset(self::$columnTypesCache[$cacheKey])) {
            return self::$columnTypesCache[$cacheKey];
        }
        
        // Use Laravel cache with tags (consistent with your service)
        if ($this->getIsCacheable()) {
            $this->validateCacheConfiguration();

            if ($this->getIsCacheable()) { // Re-check after validation
                if ($this->cacheSupportsTagging()) {
                    return Cache::tags([$tableName])
                        ->remember(
                            $cacheKey,
                            $this->getCacheExpiresAt(),
                            function () use ($model, $cacheKey, $useCache) {
                                $types = $this->fetchColumnTypesFromDatabase($model);

                                // Also store in memory cache
                                if ($useCache) {
                                    self::$columnTypesCache[$cacheKey] = $types;
                                }

                                return $types;
                            }
                        );
                } else {
                    // Fallback for non-tagging drivers
                    return Cache::remember(
                        $cacheKey,
                        $this->getCacheExpiresAt(),
                        function () use ($model, $cacheKey, $useCache) {
                            $types = $this->fetchColumnTypesFromDatabase($model);

                            // Also store in memory cache
                            if ($useCache) {
                                self::$columnTypesCache[$cacheKey] = $types;
                            }

                            return $types;
                        }
                    );
                }
            }
        }
        
        // Fallback to direct database query
        $types = $this->fetchColumnTypesFromDatabase($model);
        
        if ($useCache) {
            self::$columnTypesCache[$cacheKey] = $types;
        }
        
        return $types;
    }

    private function fetchColumnTypesFromDatabase($model)
    {
        $connection = $model->getConnection();
        $driver = $connection->getDriverName();
        $tableName = $this->getModelTableName($model);
        
        try {
            switch ($driver) {
                case 'mysql':
                    return $this->getMySQLColumnTypes($connection, $tableName);
                case 'pgsql':
                    return $this->getPostgreSQLColumnTypes($connection, $tableName);
                case 'sqlite':
                    return $this->getSQLiteColumnTypes($connection, $tableName);
                case 'sqlsrv':
                    return $this->getSQLServerColumnTypes($connection, $tableName);
                default:
                    Log::warning("MicroCRUD: Unsupported database driver: {$driver}. Using fallback type detection.");
                    $columns = $this->getModelColumns($model);
                    return array_fill_keys($columns, 'string');
            }
        } catch (\Exception $e) {
            Log::warning("MicroCRUD: Failed to fetch column types: {$e->getMessage()}. Using fallback.");
            // Fallback: return basic string types for all columns
            $columns = $this->getModelColumns($model);
            return array_fill_keys($columns, 'string');
        }
    }

    private function getMySQLColumnTypes($connection, $tableName)
    {
        $columns = $connection->select("DESCRIBE `{$tableName}`");
        $types = [];
        
        foreach ($columns as $column) {
            $types[$column->Field] = $this->parseColumnType($column->Type);
        }
        
        return $types;
    }

    private function getPostgreSQLColumnTypes($connection, $tableName)
    {
        $columns = $connection->select("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = ? 
            ORDER BY ordinal_position
        ", [$tableName]);
        
        $types = [];
        foreach ($columns as $column) {
            $types[$column->column_name] = $this->parsePostgreSQLType($column->data_type);
        }
        
        return $types;
    }

    private function getSQLiteColumnTypes($connection, $tableName)
    {
        $columns = $connection->select("PRAGMA table_info(`{$tableName}`)");
        $types = [];
        
        foreach ($columns as $column) {
            $types[$column->name] = $this->parseSQLiteType($column->type);
        }
        
        return $types;
    }

    private function getSQLServerColumnTypes($connection, $tableName)
    {
        $databaseName = $connection->getDatabaseName();
        $columns = $connection->select("
            SELECT COLUMN_NAME, DATA_TYPE 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_CATALOG = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ", [$databaseName, $tableName]);
        
        $types = [];
        foreach ($columns as $column) {
            $types[$column->COLUMN_NAME] = $this->parseSQLServerType($column->DATA_TYPE);
        }
        
        return $types;
    }

    private function parseColumnType($fullType)
    {
        $type = strtolower($fullType);
        
        if (strpos($type, 'int') !== false) return 'integer';
        if (strpos($type, 'varchar') !== false) return 'string';
        if (strpos($type, 'text') !== false) return 'string';
        if (strpos($type, 'char') !== false) return 'string';
        if (strpos($type, 'decimal') !== false) return 'numeric';
        if (strpos($type, 'float') !== false) return 'numeric';
        if (strpos($type, 'double') !== false) return 'numeric';
        if (strpos($type, 'date') !== false) return 'date';
        if (strpos($type, 'time') !== false) return 'date';
        if (strpos($type, 'json') !== false) return 'json';
        if (strpos($type, 'tinyint(1)') !== false) return 'boolean';
        
        return 'string';
    }

    private function parsePostgreSQLType($type)
    {
        $type = strtolower($type);
        
        if (in_array($type, ['integer', 'bigint', 'smallint'])) return 'integer';
        if (in_array($type, ['character varying', 'varchar', 'text', 'char'])) return 'string';
        if (in_array($type, ['decimal', 'numeric', 'real', 'double precision'])) return 'numeric';
        if (in_array($type, ['date', 'timestamp', 'time'])) return 'date';
        if ($type === 'boolean') return 'boolean';
        if ($type === 'json' || $type === 'jsonb') return 'json';
        
        return 'string';
    }

    private function parseSQLiteType($type)
    {
        $type = strtolower($type);
        
        if (strpos($type, 'int') !== false) return 'integer';
        if (strpos($type, 'text') !== false) return 'string';
        if (strpos($type, 'varchar') !== false) return 'string';
        if (strpos($type, 'char') !== false) return 'string';
        if (strpos($type, 'real') !== false) return 'numeric';
        if (strpos($type, 'float') !== false) return 'numeric';
        if (strpos($type, 'decimal') !== false) return 'numeric';
        if (strpos($type, 'date') !== false) return 'date';
        if (strpos($type, 'time') !== false) return 'date';
        if (strpos($type, 'boolean') !== false) return 'boolean';
        
        return 'string';
    }

    private function parseSQLServerType($type)
    {
        $type = strtolower($type);
        
        if (in_array($type, ['int', 'bigint', 'smallint', 'tinyint'])) return 'integer';
        if (in_array($type, ['varchar', 'nvarchar', 'text', 'ntext', 'char'])) return 'string';
        if (in_array($type, ['decimal', 'numeric', 'float', 'real', 'money'])) return 'numeric';
        if (in_array($type, ['date', 'datetime', 'datetime2', 'time'])) return 'date';
        if ($type === 'bit') return 'boolean';
        
        return 'string';
    }

    // Clear column types cache using tags (consistent with your afterCreate, afterUpdate, etc.)
    public function clearColumnTypesCache($model = null)
    {
        if ($model) {
            $tableName = $this->getModelTableName($model);
            $cacheKey = get_class($model) . '_' . $tableName . '_column_types';
            
            // Clear memory cache
            unset(self::$columnTypesCache[$cacheKey]);

            // Clear Laravel cache
            $this->flushModelCache($tableName);
        } else {
            // Clear all memory cache
            self::$columnTypesCache = [];
            
            // Note: Be careful with flushing all cache tags
            // Cache::flush(); // This would clear ALL cache
        }
    }
    public function applyDynamicOrderFilters($query, $data = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }
        
        $model_columns = $this->getModelColumns();
        
        // Apply order_by filters
        foreach ($data as $key => $value) {
            if (strpos($key, 'order_by_') === 0 && !empty($value)) {
                $column = str_replace('order_by_', '', $key);
                if (in_array($column, $model_columns) && in_array(strtolower($value), ['asc', 'desc'])) {
                    $query = $query->when(!empty($value), function($q) use ($column, $value) {
                        return $q->orderBy($column, $value);
                    });
                }
            }
        }
        
        return $query;
    }
    public function applyDynamicSearchFilters($query, $data = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }

        $model_columns = $this->getModelColumns();
        $column_types = $this->getColumnTypes();

        foreach ($data as $key => $value) {
            if (empty($value) && $value !== '0' && $value !== 0 && $value !== false) continue;

            // Handle different search patterns based on column type
            if (strpos($key, 'search_by_') === 0) {
                $column = str_replace('search_by_', '', $key);

                if (!in_array($column, $model_columns)) continue;

                $column_type = $column_types[$column] ?? 'string';

                // Apply type-specific search logic
                switch ($column_type) {
                    case 'string':
                        $query = $query->when(!empty($value), function($q) use ($column, $value) {
                            return $q->where($column, 'like', '%' . $value . '%');
                        });
                        break;

                    case 'integer':
                    case 'numeric':
                        $query = $query->when(!empty($value), function($q) use ($column, $value) {
                            return $q->where($column, '=', $value);
                        });
                        break;

                    case 'boolean':
                        $query = $query->when(isset($value), function($q) use ($column, $value) {
                            return $q->where($column, '=', $value);
                        });
                        break;

                    case 'date':
                        $query = $query->when(!empty($value), function($q) use ($column, $value) {
                            return $q->whereDate($column, '=', $value);
                        });
                        break;

                    default:
                        $query = $query->when(!empty($value), function($q) use ($column, $value) {
                            return $q->where($column, '=', $value);
                        });
                        break;
                }
            }

            // Handle range searches for numeric and date fields
            elseif (preg_match('/^search_by_(.+)_(min|max|from|to)$/', $key, $matches)) {
                $column = $matches[1];
                $range_type = $matches[2];

                if (!in_array($column, $model_columns)) continue;

                $column_type = $column_types[$column] ?? 'string';

                if (in_array($column_type, ['integer', 'numeric'])) {
                    switch ($range_type) {
                        case 'min':
                            $query = $query->when(!empty($value), function($q) use ($column, $value) {
                                return $q->where($column, '>=', $value);
                            });
                            break;
                        case 'max':
                            $query = $query->when(!empty($value), function($q) use ($column, $value) {
                                return $q->where($column, '<=', $value);
                            });
                            break;
                    }
                }

                if ($column_type === 'date') {
                    switch ($range_type) {
                        case 'from':
                            $query = $query->when(!empty($value), function($q) use ($column, $value) {
                                return $q->whereDate($column, '>=', $value);
                            });
                            break;
                        case 'to':
                            $query = $query->when(!empty($value), function($q) use ($column, $value) {
                                return $q->whereDate($column, '<=', $value);
                            });
                            break;
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Apply grouped pagination using window functions (ROW_NUMBER).
     *
     * This method allows paginating within each group while maintaining group structure.
     * Uses ROW_NUMBER() OVER (PARTITION BY group_column ORDER BY sort_column).
     *
     * Example:
     * - Get first 10 apartments per block
     * - Get top 5 products per category
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $groupConfig Configuration: ['column' => 'block_id', 'limit' => 10, 'order_by' => 'created_at']
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyGroupedPagination($query, $groupConfig)
    {
        if (empty($groupConfig['column']) || empty($groupConfig['limit'])) {
            return $query;
        }

        $groupColumn = $groupConfig['column'];
        $limit = $groupConfig['limit'];
        $orderBy = $groupConfig['order_by'] ?? 'id';
        $orderDirection = $groupConfig['order_direction'] ?? 'asc';
        $modelTableName = $this->getModelTableName();

        // Build subquery with ROW_NUMBER
        $subQuery = $this->model::selectRaw(
            "{$modelTableName}.*, ROW_NUMBER() OVER (PARTITION BY {$modelTableName}.{$groupColumn} ORDER BY {$modelTableName}.{$orderBy} {$orderDirection}) as rn"
        );

        // Wrap in outer query to filter by row number
        $query->fromSub($subQuery, $modelTableName)
              ->where('rn', '<=', $limit);

        return $query;
    }

    /**
     * Apply GROUP BY clause dynamically based on group_bies parameter.
     *
     * Supports multiple syntax formats:
     *
     * 1. Simple grouping (array of strings):
     *    group_bies = ['object_id', 'status']
     *    group_bies = ['block.manager_id']
     *
     * 2. Grouped pagination (array with config):
     *    group_bies = [
     *        'object_id' => ['page' => 1, 'limit' => 10, 'search' => 'object1'],
     *        'status'
     *    ]
     *
     * 3. Relation-based grouped pagination:
     *    group_bies = [
     *        'block.manager_id' => ['page' => 1, 'limit' => 5, 'is_all' => false]
     *    ]
     *
     * Features:
     * - Validates all columns and relations exist
     * - Automatically applies LEFT JOIN for relation columns
     * - Automatically eager loads relations to prevent N+1 queries
     * - Supports nested relations (e.g., 'block.manager.department_id')
     * - Supports pagination within groups
     * - Supports filtering within specific groups
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $data Request data containing group_bies parameter
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyDynamicGroupBy($query, $data = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }

        // Check if group_bies parameter exists
        if (empty($data['group_bies']) || !is_array($data['group_bies'])) {
            return $query;
        }

        $groupBies = $data['group_bies'];
        $modelColumns = $this->getModelColumns();
        $modelTableName = $this->getModelTableName();
        $groupByColumns = [];
        $relationsToLoad = [];
        $groupFilters = []; // Store filters/pagination for specific groups

        // Parse group_bies to separate simple groups from configured groups
        foreach ($groupBies as $key => $value) {
            $groupColumn = null;
            $groupConfig = null;

            // Determine if this is simple syntax or config syntax
            if (is_numeric($key)) {
                // Simple syntax: ['object_id', 'status']
                $groupColumn = $value;
                $groupConfig = null;
            } else {
                // Config syntax: ['object_id' => ['page' => 1, 'limit' => 10]]
                $groupColumn = $key;
                $groupConfig = is_array($value) ? $value : null;
            }

            // Store config for later if provided
            if ($groupConfig !== null) {
                $groupFilters[$groupColumn] = $groupConfig;
            }

            // Check if it's a relation column (contains dot notation like 'block.manager_id')
            if (strpos($groupColumn, '.') !== false) {
                $parts = explode('.', $groupColumn);

                // Support nested relations (e.g., 'block.manager.department_id')
                if (count($parts) >= 2) {
                    $relationPath = [];
                    $currentModel = $this->model;
                    $isValidRelation = true;

                    // Validate each relation in the path
                    for ($i = 0; $i < count($parts) - 1; $i++) {
                        $relationName = $parts[$i];
                        $relationMethod = Str::camel($relationName);

                        // Check if relation method exists
                        if (!method_exists($currentModel, $relationMethod)) {
                            Log::warning("MicroCRUD: Relation method '{$relationMethod}' does not exist on model " . get_class($currentModel));
                            $isValidRelation = false;
                            break;
                        }

                        // Get relation instance
                        try {
                            $relation = (new $currentModel)->{$relationMethod}();

                            if (!($relation instanceof \Illuminate\Database\Eloquent\Relations\Relation)) {
                                Log::warning("MicroCRUD: Method '{$relationMethod}' is not a valid Eloquent relation on model " . get_class($currentModel));
                                $isValidRelation = false;
                                break;
                            }

                            $relationPath[] = $relationName;
                            $currentModel = $relation->getRelated();

                        } catch (\Exception $e) {
                            Log::warning("MicroCRUD: Failed to load relation '{$relationMethod}': {$e->getMessage()}");
                            $isValidRelation = false;
                            break;
                        }
                    }

                    if ($isValidRelation && !empty($relationPath)) {
                        // Get the column name (last part)
                        $columnName = end($parts);

                        // Validate column exists on related model
                        $relatedColumns = $this->getModelColumns($currentModel);
                        if (!in_array($columnName, $relatedColumns)) {
                            Log::warning("MicroCRUD: Column '{$columnName}' does not exist on related model " . get_class($currentModel));
                            continue;
                        }

                        // Build the full relation path for eager loading
                        $fullRelationPath = implode('.', $relationPath);
                        $relationsToLoad[] = $fullRelationPath;

                        // For joins, we need to handle the last relation
                        $lastRelation = end($relationPath);
                        $lastRelationMethod = Str::camel($lastRelation);

                        try {
                            // Get relation instance to determine join details
                            $relationInstance = null;
                            $tempModel = $this->model;

                            // Navigate through nested relations
                            foreach ($relationPath as $relName) {
                                $relMethod = Str::camel($relName);
                                $relationInstance = (new $tempModel)->{$relMethod}();
                                $tempModel = $relationInstance->getRelated();
                            }

                            if ($relationInstance) {
                                $relatedTable = $relationInstance->getRelated()->getTable();
                                $foreignKey = $relationInstance->getForeignKeyName();
                                $ownerKey = $relationInstance->getOwnerKeyName();

                                // Apply join if not already joined
                                $joins = collect($query->getQuery()->joins ?? []);
                                $alreadyJoined = $joins->contains(function ($join) use ($relatedTable) {
                                    return $join->table === $relatedTable;
                                });

                                if (!$alreadyJoined) {
                                    // Handle BelongsTo vs HasMany/HasOne differently
                                    if ($relationInstance instanceof \Illuminate\Database\Eloquent\Relations\BelongsTo) {
                                        $query->leftJoin(
                                            $relatedTable,
                                            "{$modelTableName}.{$foreignKey}",
                                            '=',
                                            "{$relatedTable}.{$ownerKey}"
                                        );
                                    } else {
                                        // HasMany, HasOne, etc.
                                        $query->leftJoin(
                                            $relatedTable,
                                            "{$relatedTable}.{$foreignKey}",
                                            '=',
                                            "{$modelTableName}.{$ownerKey}"
                                        );
                                    }
                                }

                                // Add qualified column to GROUP BY
                                $groupByColumns[] = "{$relatedTable}.{$columnName}";
                            }
                        } catch (\Exception $e) {
                            Log::warning("MicroCRUD: Failed to apply join for relation '{$fullRelationPath}': {$e->getMessage()}");
                        }
                    }
                }
            } else {
                // Direct column on the model
                if (in_array($groupColumn, $modelColumns)) {
                    // Use qualified column name to avoid ambiguity
                    $groupByColumns[] = "{$modelTableName}.{$groupColumn}";
                } else {
                    Log::warning("MicroCRUD: Column '{$groupColumn}' does not exist on model table '{$modelTableName}'");
                }
            }
        }

        // Apply group-specific filters (search/pagination within groups)
        if (!empty($groupFilters)) {
            // Check if we need window function-based pagination (limit per group)
            $hasWithinGroupPagination = false;
            $groupPaginationConfig = null;

            foreach ($groupFilters as $columnName => $config) {
                // Get qualified column name
                $qualifiedColumn = strpos($columnName, '.') !== false
                    ? str_replace('.', '.', $columnName)
                    : "{$modelTableName}.{$columnName}";

                // Apply search filter if provided
                if (!empty($config['search'])) {
                    $searchValue = $config['search'];
                    $query->where($qualifiedColumn, 'LIKE', "%{$searchValue}%");
                }

                // Check if this group needs within-group pagination
                if (!empty($config['limit']) && empty($config['page'])) {
                    // This is "top N per group" scenario - use window functions
                    $hasWithinGroupPagination = true;
                    $groupPaginationConfig = [
                        'column' => $columnName,
                        'limit' => $config['limit'],
                        'order_by' => $config['order_by'] ?? 'id',
                        'order_direction' => $config['order_direction'] ?? 'asc',
                        'is_all' => $config['is_all'] ?? false,
                    ];
                    break; // Only support one window function group at a time
                } elseif (!empty($config['page']) || !empty($config['limit'])) {
                    // This is regular pagination but on grouped results
                    Log::info("MicroCRUD: Regular pagination on grouped data for '{$columnName}'. Groups will be paginated normally.");
                }
            }

            // If we need window function pagination, clear GROUP BY and use window functions instead
            if ($hasWithinGroupPagination && $groupPaginationConfig) {
                // Clear the group by since we're using window functions
                $groupByColumns = [];

                // Apply the grouped pagination
                return $this->applyGroupedPagination($query, $groupPaginationConfig);
            }
        }

        // Apply eager loading for relations
        if (!empty($relationsToLoad)) {
            $query->with(array_unique($relationsToLoad));
        }

        // Apply GROUP BY if we have valid columns
        if (!empty($groupByColumns)) {
            // Check if user wants specific aggregate selection (first, last, max, min)
            // Support both top-level parameters and inline syntax within group configs
            $groupAggregate = $data['group_aggregate'] ?? null;
            $groupOrderBy = $data['group_order_by'] ?? null;
            $groupOrderDirection = $data['group_order_direction'] ?? 'asc';

            // Check for inline ordering syntax in group configs (e.g., 'order_by_created_at': 'desc')
            if (!$groupOrderBy && !empty($groupFilters)) {
                foreach ($groupFilters as $columnName => $config) {
                    if (is_array($config)) {
                        foreach ($config as $configKey => $configValue) {
                            // Check if key matches pattern 'order_by_*'
                            if (strpos($configKey, 'order_by_') === 0) {
                                $orderColumnName = substr($configKey, 9); // Remove 'order_by_' prefix
                                $groupOrderBy = $orderColumnName;
                                $groupOrderDirection = strtolower($configValue); // 'asc' or 'desc'
                                break 2; // Exit both foreach loops
                            }
                        }
                    }
                }
            }

            // For deterministic GROUP BY results, we need to use window functions or subqueries
            // to select specific records (first, last, max, min) per group
            if ($groupAggregate || $groupOrderBy) {
                // Use subquery with ROW_NUMBER to select specific record per group
                $orderColumn = $groupOrderBy ?? $this->model->getKeyName();
                $orderDirection = $groupOrderDirection;

                // Determine which row to select based on aggregate
                if ($groupAggregate === 'last' || $groupAggregate === 'max') {
                    $orderDirection = 'desc';
                }

                // Build subquery with ROW_NUMBER
                $partitionColumns = implode(', ', $groupByColumns);

                $subQuery = $this->model::selectRaw(
                    "{$modelTableName}.*, ROW_NUMBER() OVER (PARTITION BY {$partitionColumns} ORDER BY {$modelTableName}.{$orderColumn} {$orderDirection}) as rn"
                );

                // Apply existing query conditions to subquery
                $bindings = $query->getBindings();
                $wheres = $query->getQuery()->wheres;

                if (!empty($wheres)) {
                    foreach ($wheres as $where) {
                        if ($where['type'] === 'Basic') {
                            $subQuery->where($where['column'], $where['operator'], $where['value']);
                        }
                    }
                }

                // Wrap in outer query to filter by row number = 1
                $query = $this->model::fromSub($subQuery, $modelTableName)
                    ->where('rn', '=', 1)
                    ->select("{$modelTableName}.*");

                // Re-apply eager loading on the new query
                if (!empty($relationsToLoad)) {
                    $query->with(array_unique($relationsToLoad));
                }

                $this->setQuery($query);
                return $query;
            }

            // Default GROUP BY behavior (first row encountered - non-deterministic)
            $query->groupBy($groupByColumns);

            // When grouping, we typically want to select the grouped columns
            // and optionally aggregate functions. Let's select the main model columns
            // and the grouped columns to avoid SQL errors
            $selectColumns = ["{$modelTableName}.*"];

            // Add relation columns to select
            foreach ($groupByColumns as $groupCol) {
                if (strpos($groupCol, '.') !== false) {
                    $selectColumns[] = $groupCol;
                }
            }

            $query->select($selectColumns);
        }

        return $query;
    }

    public function applyDynamicFilters($query, $data = [])
    {
        $query = $this->applyDynamicSearchFilters($query, $data);
        $query = $this->applyDynamicOrderFilters($query, $data);
        $query = $this->applyDynamicGroupBy($query, $data);
        return $query;
    }

    /**
     * Build hierarchical grouped response structure.
     *
     * Transforms flat grouped data into nested parent-child structure based on group columns.
     *
     * Example: group_bies = ['block.manager_id', 'block_id']
     * Creates nested structure: Managers → Blocks → Apartments
     *
     * @param array $items Flat grouped items
     * @param array $groupColumns Group column configuration
     * @param array $options Options: ['hierarchical' => bool, 'paginate' => bool, 'per_page' => int, 'exclude_relations' => array]
     * @return array Hierarchical grouped structure or flat data
     */
    public function buildHierarchicalGroupedResponse($items, $groupColumns = [], $options = [])
    {
        if (empty($groupColumns)) {
            $data = $this->getData();
            $groupColumns = $data['group_bies'] ?? [];
        }

        if (empty($groupColumns)) {
            return $items;
        }

        $useHierarchical = $options['hierarchical'] ?? false;

        // If not hierarchical, return flat grouped data
        if (!$useHierarchical) {
            return $items;
        }

        $paginate = $options['paginate'] ?? false;
        $perPage = $options['per_page'] ?? 10;

        // NEW: Auto-exclude parent relations by default, unless include_relations is specified
        $includeRelations = $options['include_relations'] ?? [];
        $autoExcludeRelations = [];

        // Parse group columns to determine hierarchy and auto-detect relations to exclude
        $groupHierarchy = [];
        foreach ($groupColumns as $key => $value) {
            $column = is_numeric($key) ? $value : $key;
            $config = is_numeric($key) ? null : (is_array($value) ? $value : null);

            $groupHierarchy[] = ['column' => $column, 'config' => $config];

            // Auto-detect relation names from group columns
            if (strpos($column, '.') !== false) {
                $parts = explode('.', $column);
                // Extract base relation name (e.g., 'object' from 'object.manager_id')
                $baseRelation = $parts[0];

                // Only exclude if not explicitly included
                if (!in_array($baseRelation, $includeRelations)) {
                    $autoExcludeRelations[] = $baseRelation;
                }
            } else {
                // For direct columns, check if there's a matching relation
                // (e.g., 'object_id' → 'object' relation)
                if (str_ends_with($column, '_id')) {
                    $potentialRelation = str_replace('_id', '', $column);

                    // Only exclude if not explicitly included
                    if (!in_array($potentialRelation, $includeRelations)) {
                        $autoExcludeRelations[] = $potentialRelation;
                    }
                }
            }
        }

        // Remove duplicates
        $excludeRelations = array_unique($autoExcludeRelations);

        // Build hierarchical structure
        return $this->buildNestedGroups($items, $groupHierarchy, 0, $excludeRelations, $paginate, $perPage);
    }

    /**
     * Recursively build nested group structure.
     *
     * @param \Illuminate\Support\Collection|array $items Items to group
     * @param array $groupHierarchy Group column hierarchy
     * @param int $level Current hierarchy level
     * @param array $excludeRelations Relations to exclude from leaf resources
     * @param bool $paginate Whether to paginate at leaf level
     * @param int $perPage Items per page
     * @return array Nested structure
     */
    protected function buildNestedGroups($items, $groupHierarchy, $level, $excludeRelations, $paginate, $perPage)
    {
        // If no more levels, return leaf data
        if ($level >= count($groupHierarchy)) {
            $leafData = $this->excludeRelationsFromItems($items, $excludeRelations);

            // Only add pagination if requested
            if ($paginate) {
                $collection = collect($items);
                $page = request()->input("level_{$level}_page", request()->input('page', 1));
                $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                    $collection->forPage($page, $perPage),
                    $collection->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url()]
                );

                return [
                    'pagination' => [
                        'current' => $paginated->currentPage(),
                        'previous' => $paginated->currentPage() > 1 ? $paginated->currentPage() - 1 : 0,
                        'next' => $paginated->hasMorePages() ? $paginated->currentPage() + 1 : 0,
                        'perPage' => $paginated->perPage(),
                        'totalPage' => $paginated->lastPage(),
                        'totalItem' => $paginated->total(),
                    ],
                    'data' => $this->excludeRelationsFromItems($paginated->items(), $excludeRelations),
                ];
            }

            // No pagination - return data directly
            return $leafData;
        }

        $currentGroup = $groupHierarchy[$level];
        $groupColumn = $currentGroup['column'];
        $groupConfig = $currentGroup['config'];

        // Extract relation name for grouping
        $relationName = null;
        $columnName = $groupColumn;

        if (strpos($groupColumn, '.') !== false) {
            $parts = explode('.', $groupColumn);
            $columnName = end($parts);
            $relationPath = implode('.', array_slice($parts, 0, -1));
            $relationName = $relationPath;
        }

        // Group items by the current column
        $grouped = collect($items)->groupBy(function ($item) use ($groupColumn, $relationName, $columnName) {
            if ($relationName) {
                // Navigate through nested relations
                $value = $item;
                foreach (explode('.', $relationName) as $rel) {
                    $value = $value->{$rel} ?? null;
                    if (!$value) return null;
                }
                return $value->{$columnName} ?? null;
            }
            return $item->{$groupColumn} ?? null;
        })->filter(function ($value, $key) {
            return $key !== null;
        });

        // Build result array with group metadata
        $result = [];
        foreach ($grouped as $groupValue => $groupItems) {
            // Extract group metadata (parent relation data)
            $groupData = $this->extractGroupData($groupItems->first(), $groupColumn, $relationName);

            $groupNode = [
                'group' => $groupData,
            ];

            // Determine if we should paginate at this level
            $shouldPaginateThisLevel = false;
            $thisLevelPerPage = $perPage;

            if ($groupConfig && isset($groupConfig['page'])) {
                // Group-specific pagination config
                $shouldPaginateThisLevel = true;
                $thisLevelPerPage = $groupConfig['limit'] ?? $perPage;
            }

            // Recursively process next level
            $childData = $this->buildNestedGroups(
                $groupItems,
                $groupHierarchy,
                $level + 1,
                $excludeRelations,
                $shouldPaginateThisLevel,
                $thisLevelPerPage
            );

            // If child data has pagination, it's already structured
            if (is_array($childData) && isset($childData['pagination'])) {
                $groupNode['pagination'] = $childData['pagination'];
                $groupNode['data'] = $childData['data'];
            } else {
                $groupNode['data'] = $childData;
            }

            // Add aggregations if configured
            if ($groupConfig && !empty($groupConfig['aggregations'])) {
                $groupNode['aggregations'] = $this->calculateAggregations($groupItems, $groupConfig['aggregations']);
            }

            $result[] = $groupNode;
        }

        // Apply global pagination at this level if requested (and not at leaf level)
        if ($level === 0 && $paginate && count($groupHierarchy) > 1) {
            // This is top-level pagination of groups
            $collection = collect($result);
            $page = request()->input('page', 1);
            $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                $collection->forPage($page, $perPage),
                $collection->count(),
                $perPage,
                $page,
                ['path' => request()->url()]
            );

            return [
                'pagination' => [
                    'current' => $paginated->currentPage(),
                    'previous' => $paginated->currentPage() > 1 ? $paginated->currentPage() - 1 : 0,
                    'next' => $paginated->hasMorePages() ? $paginated->currentPage() + 1 : 0,
                    'perPage' => $paginated->perPage(),
                    'totalPage' => $paginated->lastPage(),
                    'totalItem' => $paginated->total(),
                ],
                'data' => $paginated->items(),
            ];
        }

        return $result;
    }

    /**
     * Extract group metadata from an item.
     *
     * @param mixed $item Source item
     * @param string $groupColumn Group column name
     * @param string|null $relationName Relation name if grouping by relation
     * @return array Group metadata
     */
    protected function extractGroupData($item, $groupColumn, $relationName)
    {
        if ($relationName) {
            // Get the relation data
            $relationData = $item;
            foreach (explode('.', $relationName) as $rel) {
                $relationData = $relationData->{$rel} ?? null;
                if (!$relationData) break;
            }

            if ($relationData) {
                // Return relation data as array
                $resource = $this->getItemResource();
                return (new $resource($relationData))->toArray(request());
            }
        }

        // For direct column grouping, return just the value
        return [
            $groupColumn => $item->{$groupColumn} ?? null,
        ];
    }

    /**
     * Exclude specified relations from items to prevent duplication.
     *
     * @param array|\Illuminate\Support\Collection $items Items
     * @param array $excludeRelations Relations to exclude
     * @return array Items without excluded relations
     */
    protected function excludeRelationsFromItems($items, $excludeRelations)
    {
        if (empty($excludeRelations)) {
            $resource = $this->getItemResource();
            return collect($items)->map(function ($item) use ($resource) {
                return (new $resource($item))->toArray(request());
            })->toArray();
        }

        $resource = $this->getItemResource();
        return collect($items)->map(function ($item) use ($excludeRelations, $resource) {
            $data = (new $resource($item))->toArray(request());

            // Remove excluded relations
            foreach ($excludeRelations as $relation) {
                unset($data[$relation]);
            }

            return $data;
        })->toArray();
    }

    /**
     * Calculate aggregations for a group.
     *
     * @param \Illuminate\Support\Collection $items Items in group
     * @param array $aggregations Aggregation config: ['count' => true, 'sum' => ['price'], 'avg' => ['price']]
     * @return array Calculated aggregations
     */
    protected function calculateAggregations($items, $aggregations)
    {
        $result = [];

        if (!empty($aggregations['count'])) {
            $result['count'] = $items->count();
        }

        if (!empty($aggregations['sum'])) {
            foreach ($aggregations['sum'] as $column) {
                $result["sum_{$column}"] = $items->sum($column);
            }
        }

        if (!empty($aggregations['avg'])) {
            foreach ($aggregations['avg'] as $column) {
                $result["avg_{$column}"] = $items->avg($column);
            }
        }

        if (!empty($aggregations['max'])) {
            foreach ($aggregations['max'] as $column) {
                $result["max_{$column}"] = $items->max($column);
            }
        }

        if (!empty($aggregations['min'])) {
            foreach ($aggregations['min'] as $column) {
                $result["min_{$column}"] = $items->min($column);
            }
        }

        return $result;
    }
}
