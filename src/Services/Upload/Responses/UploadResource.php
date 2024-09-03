<?php

namespace Microcrud\Services\Upload\Responses;

use Illuminate\Http\Resources\Json\JsonResource;

class UploadResource extends JsonResource
{

    public static $wrap = false;

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'path' => $this->path_aws,
            'name' => $this->name,
            'size' => $this->size,
            'extension' => $this->extension,
        ];
    }
}
