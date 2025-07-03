<?php

namespace Microcrud\Interfaces;

/**
 * Interface for API response formatting.
 */
interface ApiController
{
    /**
     * Return a 201 Created response.
     * @param mixed $resource
     * @param mixed $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function created($resource, $item);

    /**
     * Return a 202 Accepted response.
     * @param mixed $resource
     * @param mixed $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function accepted($resource, $item);

    /**
     * Return a single resource item.
     * @param mixed $resource
     * @param mixed $item
     * @param int $status_code
     * @return \Illuminate\Http\JsonResponse
     */
    public function singleItem($resource, $item, $status_code);

    /**
     * Return a paginated resource collection.
     * @param mixed $resource
     * @param mixed $items
     * @param int $status_code
     * @return \Illuminate\Http\JsonResponse
     */
    public function paginated($resource, $items, $status_code);

    /**
     * Return a 204 No Content response.
     * @return \Illuminate\Http\JsonResponse
     */
    public function noContent();

    /**
     * Return a generic error response.
     * @param string $message
     * @param int $status_code
     * @return \Illuminate\Http\JsonResponse
     */
    public function error($message, $status_code);

    /**
     * Return a 404 Not Found error response.
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorNotFound($message);

    /**
     * Return a 400 Bad Request error response.
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorBadRequest($message);

    /**
     * Return a 403 Forbidden error response.
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorForbidden($message);

    /**
     * Return a 401 Unauthorized error response.
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    public function errorUnauthorized($message);    
}