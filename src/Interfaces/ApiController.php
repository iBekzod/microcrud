<?php

namespace Microcrud\Interfaces;

interface ApiController
{
    public function created($resource, $item);

    public function accepted($resource, $item);

    public function singleItem($resource, $item, $status_code);

    public function paginated($resource, $items, $status_code);

    public function noContent();

    public function error($message, $status_code);

    public function errorNotFound($message);

    public function errorBadRequest($message);

    public function errorForbidden($message);

    public function errorUnauthorized($message);

    public function translate(String $key);
}
