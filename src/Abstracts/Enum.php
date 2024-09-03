<?php

namespace Microcrud\Abstracts;

abstract class Enum
{
    static function getAll()
    {
        return [];
    }
    static function getSelections()
    {
        return [];
    }
    public static function get($selection = null, $types = [])
    {
        $result =  [];
        $selections = static::getSelections();
        if (empty($selections)) {
            if (empty($types)) {
                $types = static::getAll();
            }
            foreach ($types as $type) {
                $selections[$type] = $type;
            }
        }
        if (is_array($selections) && array_key_exists($selection, $selections)) {
            $result =  [
                'key' => $selection,
                'value' => $selections[$selection]
            ];
        }
        return $result;
    }
    public static function collectAll($types = [])
    {
        if (empty($types)) {
            $types = static::getAll();
        }
        $result = [];
        foreach ($types as $type) {
            $result[] = static::get($type);
        }
        return $result;
    }
}
