<?php

namespace Microcrud\Responses;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

    /**
     * Transform the model into an array with optional eager loaded relationships.
     *
     * NOTE: To prevent N+1 queries, ensure you eager load relationships in your service:
     * Example: $this->model::with(['relation'])->get()
     *
     * @param array $with Optional relationships to load if not already loaded
     * @return array
     */
    public function forModel($with = [])
    {
        if (!empty($with)) {
            $this->load($with);
        }

        $attributes = $this->resource->toArray();

        // Process each attribute for automatic relationship and date formatting
        foreach ($attributes as $key => $value) {
            // Automatically convert loaded relationships (e.g., user_id -> user)
            $relationKey = str_replace('_id', '', $key);
            $method = Str::camel($relationKey);

            if (
                $key !== $relationKey && // Only check fields ending with _id
                method_exists($this->resource, $method) &&
                isset($this->{$method}) &&
                $this->resource->{$method}() instanceof \Illuminate\Database\Eloquent\Relations\Relation
            ) {
                unset($attributes[$key . '_id']);
                $attributes[$relationKey] = new ItemResource($this->{$method});
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
