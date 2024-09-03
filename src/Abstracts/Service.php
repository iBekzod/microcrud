<?php

namespace Microcrud\Abstracts;

use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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

abstract class Service implements ServiceInterface
{
    public $model;
    protected array $data = [];
    protected $private_key_name = 'id';

    protected $is_job = false;

    protected $query = null;
    protected $resource = null;
    protected $is_cacheable = false;
    protected $is_transaction_enabled = true;
    protected $is_paginated = true;
    protected $cache_expires_at = null;
    protected $is_replace_rules = false;

    protected array $rules = [];
    protected $items = [];
    /**
     * Class constructor.
     */
    public function __construct($model = null, $resource = null)
    {
        $this->model = $model;
        $this->cache_expires_at = Carbon::now()->addDay();
        $this->resource = (isset($resource)) ? $resource : ItemResource::class;
    }
    public function setPrivateKeyName($private_key_name)
    {
        $this->private_key_name = $private_key_name;
        return $this;
    }

    public function getPrivateKeyName()
    {
        return $this->private_key_name;
    }

    public function getIsCacheable()
    {
        return $this->is_cacheable;
    }

    public function setIsCacheable($is_cacheable = true)
    {
        $this->is_cacheable = $is_cacheable;
        return $this;
    }

    public function getIsTransactionEnabled()
    {
        return $this->is_transaction_enabled;
    }

    public function setIsTransactionEnabled($is_transaction_enabled = true)
    {
        $this->is_transaction_enabled = $is_transaction_enabled;
        return $this;
    }
    public function getIsPaginated()
    {
        return $this->is_paginated;
    }

    public function setIsPaginated($is_paginated = true)
    {
        $this->is_paginated = $is_paginated;
        return $this;
    }


    public function getCacheExpiresAt()
    {
        return $this->cache_expires_at;
    }

    public function setCacheExpiresAt($time)
    {
        $this->cache_expires_at = $time;
        return $this;
    }

    /**
     * @throws NotFoundException
     */
    public function get()
    {
        if (!isset($this->model)) {
            throw new NotFoundException();
        }
        return $this->model;
    }

    public function getQuery()
    {
        return ($this->query) ? $this->query : $this->model::query();
    }

    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }
    public function getRules()
    {
        return $this->rules;
    }

    public function setRules($rules, $is_replace_rules = false)
    {
        $this->rules = $rules;
        $this->is_replace_rules = $is_replace_rules;
        return $this;
    }

    /**
     * @throws NotFoundException
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
     * @throws NotFoundException
     */
    public function setById($data = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }
        if (array_key_exists($this->getPrivateKeyName(), $data)) {
            if ($this->getIsCacheable()) {
                ksort($data);
                $item_key = $this->getModelTableName() . ':' . serialize($data);
                $model = Cache::tags([$this->getModelTableName()])
                    ->remember(
                        $item_key,
                        $this->getCacheExpiresAt(),
                        function () use ($data) {
                            return $this->withoutScopes()->getQuery()->where($this->getPrivateKeyName(), $data[$this->getPrivateKeyName()])->first();
                        }
                    );
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
     * @throws NotFoundException
     */
    public function getData()
    {
        if (!isset($this->data)) {
            throw new NotFoundException();
        }
        return $this->data;
    }
    /**
     * @throws NotFoundException
     */
    public function setData(array $data)
    {
        if (isset($data)) {
            Log::info("Set data:");
            Log::info($data);
            $this->data = $data;
        } else {
            throw new NotFoundException();
        }
        return $this;
    }


    public function getItems()
    {
        return $this->items;
    }
    public function setItems($items)
    {
        $this->items = $items;
        return $this;
    }
    private function getRelationRule($key, $rule)
    {
        $relation_key_types = ['id', 'uuid'];
        foreach ($relation_key_types as $relation_key_type) {
            if (str_ends_with($key, '_' . $relation_key_type)) {
                $relation_table = Str::plural(str_replace('_' . $relation_key_type, '', $key));
                $relation = Str::camel(str_replace('_' . $relation_key_type, '', $key));
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
                } else if (Schema::hasTable($relation_table)) {
                    $rule = $rule . "|exists:{$relation_table},{$relation_key_type}";
                }
            }
        }
        return $rule;
    }
    /**
     * @throws ValidationException
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

    public function withoutScopes(array $scopes = [])
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
        $keys = $model->getConnection()->getSchemaBuilder()->getColumnListing($this->getModelTableName($model));
        return $keys;
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
        return $this;
    }

    public function afterIndex()
    {
        return $this;
    }
    public function getAll()
    {
        $items = $this->getQuery()->get();
        $this->setItems($items)->afterIndex();
        return $this->getItems();
    }

    public function getPaginated($modelQuery = null, $modelTableName = null, $is_cacheable = false)
    {   
        $data = request()->all();
        if(!isset(request()->page)){
            request()->merge([
                'page'=> 1
            ]);
        }
        if(!isset(request()->limit)){
            request()->merge([
                'limit'=> 10
            ]);
        }
        if(!$modelQuery){
            $modelQuery = $this->getQuery();
        }

        if(!$modelTableName){
            $modelTableName = $this->getModelTableName();
        }
        $limit = request()->limit ?? 10;
        if($this->getIsCacheable()){
            ksort($data);
            $item_key = request()->path() . ":" . $modelTableName . ":" . serialize($data);
            $items = Cache::tags([$modelTableName])
                ->remember(
                    $item_key,
                    $this->getCacheExpiresAt(),
                    function () use ($modelQuery, $limit) {
                        return $modelQuery->paginate($limit);
                    }
                );
        }else{
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
        StoreJob::dispatchSync($data, $this);
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
                throw new CreateException($message);
            }
        }
        if ($this->getIsTransactionEnabled() || $this->is_job)
            DB::commit();
        $this->afterCreate();
        return $this;
    }
    public function afterCreate()
    {
        if ($this->getIsCacheable()) {
            Cache::tags($this->getModelTableName())->flush();
        }
        return $this;
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
        UpdateJob::dispatchSync($data, $this);
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
                throw new UpdateException($message);
            }
        }
        if ($this->getIsTransactionEnabled() || $this->is_job)
            DB::commit();
        $this->afterUpdate();
        return $this;
    }
    public function afterUpdate()
    {
        if ($this->getIsCacheable()) {
            Cache::tags($this->getModelTableName())->flush();
        }
        return $this;
    }
    public function createOrUpdate($data = [], $conditions = [])
    {
        if (empty($data)) {
            $data = $this->getData();
        }
        if(count($conditions)){
            if ($model = $this->getQuery()->where($conditions)->first()){
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
        if ($this->getIsCacheable()) {
            Cache::tags($this->getModelTableName())->flush();
        }
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
        if ($this->getIsCacheable()) {
            Cache::tags($this->getModelTableName())->flush();
        }
        return $this;
    }
    public function indexRules($rules = [], $replace = false)
    {
        if ($replace) {
            return $rules;
        } else {
            $rules = array_merge([
                'trashed_status' => 'sometimes|integer|in:-1,0,1',
                'is_all' => 'sometimes|boolean',
                'search' => 'sometimes|string',
                'updated_at' => 'sometimes|date',
            ], $rules);
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
}
