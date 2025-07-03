<?php

namespace Microcrud\Interfaces;

use Illuminate\Http\Request;

/**
 * Interface for basic CRUD controller actions.
 * 
 * Child controllers may override methods and use custom FormRequest classes.
 */
interface CrudBaseController
{
    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request);

    /**
     * Show a single resource.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request);

    /**
     * Store a new resource.
     * Override in child controller to use a custom FormRequest.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request);

    /**
     * Update a resource.
     * Override in child controller to use a custom FormRequest.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request);

    /**
     * Delete a resource.
     * Override in child controller to use a custom FormRequest.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request);

    /**
     * Restore a soft-deleted resource.
     * Override in child controller to use a custom FormRequest.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function restore(Request $request);

    /**
     * Force delete a resource.
     * Override in child controller to use a custom FormRequest.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forceDelete(Request $request);
}