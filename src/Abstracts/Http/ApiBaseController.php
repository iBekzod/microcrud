<?php

namespace Microcrud\Abstracts\Http;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Microcrud\Abstracts\CrudService;
use Microcrud\Interfaces\ApiController;
use Microcrud\Responses\ItemResource;

/**
 * ApiBaseController
 *
 * Base controller for API responses with standardized JSON formatting.
 * Provides methods for success/error responses, pagination, and resource transformations.
 *
 * @package Microcrud\Abstracts\Http
 */
abstract class ApiBaseController implements ApiController
{
    /**
     * Service instance for data operations.
     *
     * @var \Microcrud\Abstracts\Service
     */
    protected $service;

    /**
     * Class constructor.
     *
     * @param string $model Model class name
     * @param string|null $service Service class name
     * @param string|null $resource Resource class name
     */
    public function __construct($model, $service = null, $resource = null)
    {
        $this->service = $service ? new $service() : new CrudService();
        $this->service->model = new $model();
        $this->service->setItemResource($resource ?? ItemResource::class);
    }
    /**
     * Return a created response (201).
     *
     * @param string|null $resource Resource class name
     * @param mixed $item Item to transform
     * @return \Illuminate\Http\JsonResponse
     */
    public function created($resource = null, $item = null)
    {
        $resource = $resource ?? $this->service->getItemResource();
        return $this->singleItem($resource, $item, 201);
    }

    /**
     * Return an accepted response (202).
     *
     * @param string|null $resource Resource class name
     * @param mixed $item Item to transform
     * @return \Illuminate\Http\JsonResponse
     */
    public function accepted($resource = null, $item = null)
    {
        $resource = $resource ?? $this->service->getItemResource();
        return $this->singleItem($resource, $item, 202);
    }

    /**
     * Return a single item response with resource transformation.
     *
     * @param string|null $resource Resource class name
     * @param mixed $item Item to transform
     * @param int $status_code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public function singleItem($resource = null, $item = null, $status_code = 200)
    {
        $resource = $resource ?? $this->service->getItemResource();

        return response()->json([
            'data' => new $resource($item),
            'extra_data' => $this->service->getExtraData(),
        ], $status_code);
    }

    /**
     * Return a paginated response with metadata.
     *
     * @param string|null $resource Resource class name
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $items Paginated items
     * @param int $status_code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public function paginated($resource = null, $items, $status_code = 200)
    {
        $resource = $resource ?? $this->service->getItemResource();

        return response()->json([
            'pagination' => [
                'current' => $items->currentPage(),
                'previous' => $items->currentPage() > 1 ? $items->currentPage() - 1 : 0,
                'next' => $items->hasMorePages() ? $items->currentPage() + 1 : 0,
                'perPage' => $items->perPage(),
                'totalPage' => $items->lastPage(),
                'totalItem' => $items->total(),
            ],
            'data' => $resource::collection($items->items()),
            'extra_data' => $this->service->getExtraData(),
        ], $status_code);
    }

    /**
     * Paginate a query and return formatted response.
     *
     * @param string|null $resource Resource class name
     * @param \Illuminate\Database\Eloquent\Builder|null $modelQuery Query builder instance
     * @param string $modelTableName Table name for caching
     * @param int $status_code HTTP status code
     * @param bool $is_cacheable Whether to cache results
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginateQuery($resource = null, $modelQuery = null, $modelTableName = '', $status_code = 200, $is_cacheable = false)
    {
        $items = $this->service->getPaginated($modelQuery, $modelTableName, $is_cacheable);
        $resource = $resource ?? $this->service->getItemResource();

        return $this->paginated($resource, $items, $status_code);
    }

    /**
     * Return a success response with message.
     *
     * @param string $message Success message
     * @param int $status_code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public function success($message = 'Success', $status_code = 200)
    {
        return response()->json([
            'message' => Lang::get($message),
        ], $status_code);
    }

    /**
     * Return a failure response with message.
     *
     * @param string $message Failure message
     * @param int $status_code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public function failure($message = 'Failed', $status_code = 400)
    {
        return $this->success($message, $status_code);
    }

    /**
     * Return a no content response (204).
     *
     * @return \Illuminate\Http\Response
     */
    public function noContent()
    {
        return response()->noContent();
    }

    /**
     * Return an error response with optional exception details.
     *
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param \Exception|null $exception Exception instance for logging
     * @return \Illuminate\Http\JsonResponse
     */
    public function error($message, $status_code = 400, $exception = null)
    {
        $result = [
            'message' => $message,
        ];

        if ($exception) {
            // Include stack trace in debug mode
            if (Config::get('app.debug', false)) {
                $result['error'] = $exception->getTraceAsString();
            }

            Log::error('API Error', [
                'message' => $message,
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        } else {
            Log::error('API Error', ['message' => $message]);
        }

        return response()->json($result, $status_code);
    }

    /**
     * Return a not found error (404).
     *
     * @param string $message Error message
     * @param \Exception|null $exception Exception instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorNotFound($message = 'Not Found', $exception = null)
    {
        return $this->error($message, 404, $exception);
    }

    /**
     * Return a bad request error (400).
     *
     * @param string $message Error message
     * @param \Exception|null $exception Exception instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorBadRequest($message = 'Bad Request', $exception = null)
    {
        return $this->error($message, 400, $exception);
    }

    /**
     * Return a forbidden error (403).
     *
     * @param string $message Error message
     * @param \Exception|null $exception Exception instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorForbidden($message = 'Forbidden', $exception = null)
    {
        return $this->error($message, 403, $exception);
    }

    /**
     * Return an unauthorized error (401).
     *
     * @param string $message Error message
     * @param \Exception|null $exception Exception instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorUnauthorized($message = 'Unauthorized', $exception = null)
    {
        return $this->error($message, 401, $exception);
    }

    /**
     * Translate a key with fallback support.
     *
     * Priority: microcrud_translations > validation > direct translation
     *
     * @param string $key Translation key
     * @return string Translated string
     */
    public function translate(string $key)
    {
        // Try package translations first
        if (Lang::has('microcrud_translations::validation.' . $key)) {
            return Lang::get('microcrud_translations::validation.' . $key);
        }

        // Fallback to Laravel validation translations
        if (Lang::has('validation.' . $key)) {
            return Lang::get('validation.' . $key);
        }

        // Fallback to direct translation
        return Lang::get($key);
    }

    /**
     * Return raw data with extra_data.
     *
     * @param mixed $data Data to return
     * @param int $status_code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public function get($data, $status_code = 200)
    {
        return response()->json([
            'data' => $data,
            'extra_data' => $this->service->getExtraData(),
        ], $status_code);
    }

    /**
     * Return a resource collection with optional items.
     *
     * @param string|null $resource Resource class name
     * @param mixed|null $items Items to transform (fetches all if null)
     * @param int $status_code HTTP status code
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResource($resource = null, $items = null, $status_code = 200)
    {
        $resource = $resource ?? $this->service->getItemResource();
        $items = $items ?? $this->service->getAll();

        return $this->get($resource::collection($items), $status_code);
    }
}
