<?php

namespace Microcrud\Traits;

trait ParentChildTrait
{
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function allChildren()
    {
        return $this->hasMany(self::class, 'parent_id')->with('allChildren');
    }
    public function getAllChildren($model = null, $children_collection = null)
    {
        if (is_null($model)) {
            $model = $this;
        }

        if (is_null($children_collection)) {
            $children_collection = collect([]);
        }
        $this->collectChildren($model->children, $children_collection);
        return $children_collection;
    }

    public function getRootWithChildren()
    {
        $model = $this;
        while($model->parent){
            $model = $model->parent;
        }
        $children_collection = collect([$model]);
        return $this->getAllChildren($model, $children_collection);
    }

    public function collectChildren($children, &$children_collection){
        foreach($children as $child){
            $children_collection->push($child);
            $this->collectChildren($child->children, $children_collection);
        }
    }
    public function getAllDescendantIds()
    {
        $descendantIds = $this->getAllChildren()->pluck('id')->flatten()->toArray();
        $descendantIds[] = $this->id; // Include the ID of the current category
        return $descendantIds;
    }

    public static function bootParentChildTrait()
    {
        static::deleting(function ($model) {
            $model->allChildren()->delete();
        });
    }

}
