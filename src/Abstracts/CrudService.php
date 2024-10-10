<?php

namespace Microcrud\Abstracts;



class CrudService extends Service
{
    public $model;
    protected $data;
    protected $private_key_name = 'id';

    protected $is_job = false;

    protected $query = null;
}
