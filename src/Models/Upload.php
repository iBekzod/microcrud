<?php

namespace Microcrud\Models;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Microcrud\Abstracts\Model;

class Upload extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->connection = config('schema.upload');
    }
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'integer',
        'path' => 'string'
    ];


    public function getPathAwsAttribute()
    {
        if (Cache::has('upload_' . $this->id)) {
            return Cache::get('upload_' . $this->id);
        } else {
            $url = Storage::temporaryUrl($this->path, now()->addDay());
            Cache::put('upload_' . $this->id, $url, now()->addDay());
            return $url;
        }
    }
}
