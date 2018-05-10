<?php

namespace models;

use components\Db;

class Series
{
    static protected function tableName()
    {
        return 'series';
    }
    
    static public function getSeriesId ($series)
    {
        $id = self::getSeriesByName($series)['id'];
        if ($id) return $id;
        else return self::addSeries($series);
    }
    
    static protected function getSeriesByName ($name)
    {
        $db = Db::getConnection();
        $sql = 'SELECT * FROM '. self::tableName() .' WHERE name = \''.$name.'\'';
        $result = $db->query($sql);

        if (!$result) return false;
        return $result->fetch();
    }

    static public function getSeriesById ($id)
    {
        $db = Db::getConnection();
        $sql = 'SELECT * FROM '. self::tableName() .' WHERE id = \''.$id.'\'';
        $result = $db->query($sql);

        if (!$result) return false;
        return $result->fetch();
    }

    static protected function addSeries ($name)
    {
        $db = Db::getConnection();
        $sql = 'INSERT INTO '. self::tableName() .'  (`id`, `name`) VALUES (NULL, \''.$name.'\')';
        $result = $db->query($sql);
        return $db->lastInsertId();
    }
}