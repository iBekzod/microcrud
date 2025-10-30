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

    public function applyDynamicFilters($query, $data = [])
    {
        $query = $this->applyDynamicSearchFilters($query, $data);
        $query = $this->applyDynamicOrderFilters($query, $data);
        return $query;
    }
}
