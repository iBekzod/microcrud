<?php

namespace Microcrud\Abstracts\Http;

use Microcrud\Abstracts\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Lang;
use Microcrud\Abstracts\CrudService;
use Microcrud\Responses\ItemResource;
use Microcrud\Interfaces\ApiController;

abstract class ApiBaseController implements ApiController
{

    protected Service $service;
    /**
     * Class constructor.
     */
    public function __construct($model, $service = null, $resource = null)
    {
        $this->service = (isset($service)) ? new $service : new CrudService();
        $this->service->model = new $model;
        $this->service->setItemResource((isset($resource)) ? $resource : ItemResource::class);
    }
    public function created($resource = null, $item = null)
    {
        if(!$resource){
            $resource = $this->service->getItemResource();
        }
        return $this->singleItem($resource, $item, 201);
    }

    public function accepted($resource = null, $item = null)
    {
        if(!$resource){
            $resource = $this->service->getItemResource();
        }
        return $this->singleItem($resource, $item, 202);
    }

    public function singleItem($resource = null, $item = null, $status_code = 200)
    {
        if(!$resource){
            $resource = $this->service->getItemResource();
        }
        return response()->json([
            "data" => new $resource($item)
        ], $status_code);
    }

    public function paginated($resource = null, $items, $status_code = 200)
    {
        if(!$resource){
            $resource = $this->service->getItemResource();
        }
        return response()->json([
            'pagination' => [
                'current' => $items->currentPage(),
                'previous' => $items->currentPage() > 1 ? $items->currentPage() - 1 : 0,
                'next' => $items->hasMorePages() ? $items->currentPage() + 1 : 0,
                'perPage' => $items->perPage(),
                'totalPage' => $items->lastPage(),
                'totalItem' => $items->total(),
            ],
            'data' => $resource::collection($items->items())
        ], $status_code);
    }

    protected function paginateQuery($resource=null, $modelQuery=null, $modelTableName = '', $status_code = 200, $is_cacheable = false)
    {
        $items = $this->service->getPaginated($modelQuery, $modelTableName, $is_cacheable = false);
        if(!$resource){
            $resource = $this->service->getItemResource();
        }
        return $this->paginated($resource, $items, $status_code);
    }

    public function success($message = 'Success', $status_code = 200)
    {
        return response()->json([
            'message' => __($message)
        ], $status_code);
    }


    public function failure($message = 'Failed', $status_code = 400)
    {
        return $this->success($message, $status_code);
    }

    public function noContent()
    {
        return response()->noContent();
    }

    public function error($message, $status_code = 400, $exception = null)
    {
        if ($exception) {
            Log::error('MESSAGE: ' . $message . ' ERROR: ' . $exception->getMessage() . ' TRACE: ' . $exception->getTraceAsString());
        } else {
            Log::error('MESSAGE: ' . $message);
        }
        if (Lang::has('microcrud_translations::errors.' . $message) || Lang::has('microcrud_translations::errors.' . $message)) {
            return response()->json([
                'message'   => $this->translate('errors.' . $message),
            ], $status_code);
        }
        return response()->json([
            'message'   => $message,
        ], $status_code);
    }

    public function errorNotFound($message = 'Not Found', $exception = null)
    {
        return $this->error($message, 404, $exception);
    }

    public function errorBadRequest($message = 'Bad Request', $exception = null)
    {
        return $this->error($message, 400, $exception);
    }

    public function errorForbidden($message = 'Forbidden', $exception = null)
    {
        return $this->error($message, 403, $exception);
    }

    public function errorUnauthorized($message = 'Unauthorized', $exception = null)
    {
        return $this->error($message, 401, $exception);
    }

    public function translate(String $key)
    {
        return trans('microcrud_translations::' . $key);
    }

    public function get($data, $status_code = 200)
    {
        return response()->json([
            'data' => $data
        ], $status_code);
    }
    public function getResource($resource = null, $items = null, $status_code = 200)
    {
        if(!$resource){
            $resource = $this->service->getItemResource();
        }
        if(!isset($items)){
            $items = $this->service->getAll();
        }
        return $this->get($resource::collection($items), $status_code);
    }
}
