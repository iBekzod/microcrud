<?php

namespace Microcrud\Interfaces;

use Illuminate\Http\Request;

interface CrudBaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request  $request
     *
     */
    function index(Request $request);
    /**
     * Show resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     */
    function show(Request $request);

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     */
    function create(Request $request);

    /**
     * Update resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     */
    function update(Request $request);

    /**
     * Delete resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     */
    function delete(Request $request);
}
