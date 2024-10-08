<?php

namespace Microcrud\Responses;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Microcrud\Services\Merchant\Responses\MerchantResource;
use Microcrud\Services\Upload\Responses\UploadResource;
use Microcrud\Services\User\Responses\UserResource;

class ItemResource extends JsonResource
{

    public static $wrap = false;
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return $this->forModel();
    }

    public function forModel($with = [])
    {
        if (isset($with))
            $this->load($with);

        $attributes = $this->resource->toArray();
        foreach ($attributes as $key => $value) {
            $key = str_replace('_id', '', $key);
            $method = Str::camel($key);
            if (
                method_exists($this->resource, $method) &&
                isset($this->{$method}) &&
                $this->resource->{$method}() instanceof \Illuminate\Database\Eloquent\Relations\Relation
            ) {
                unset($attributes[$key . '_id']);
                $attributes[$key] = new ItemResource($this->{$method});
            }
            switch ($key) {
                case 'created_at':
                case 'updated_at':
                case 'deleted_at':
                case 'start_date':
                case 'end_date':
                case 'from_date':
                case 'to_date':
                case 'date':
                    if (isset($value)) {
                        if (is_int($value)) {
                            $attributes[$key] = Carbon::parse(date("Y-m-d H:i:s", $value))->format('Y-m-d H:i:s');
                        } else {
                            $attributes[$key] = Carbon::parse($attributes[$key])->format('Y-m-d H:i:s');
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $attributes;
    }
}
