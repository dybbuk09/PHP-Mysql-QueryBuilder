<?php

namespace Hraw\DBQB;

use Hraw\DBQB\QueryBuilder;

class DB
{
    public function __call($method, $arguments)
    {
        return $this->call($method, $arguments);
    }

    public static function __callStatic($method, $arguments)
    {
        return (new static)->call($method, $arguments);
    }

    public function call($method, $arguments)
    {
        $instance = new QueryBuilder;
        return $instance->$method(...$arguments);
    }
}