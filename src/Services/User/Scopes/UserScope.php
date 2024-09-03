<?php

namespace Microcrud\Services\User\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class UserScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model)
    {
        // builder is the query
        $user_id = request()->user_id ?? null;
        if ($user_id) {
            $builder->where('id', $user_id);
        }
    }
}
