<?php
/**
 * Created by PhpStorm.
 * User: ТиМ
 * Date: 19.02.2018
 * Time: 20:41
 */

namespace models;

use components\Db;

class Language
{
    static protected function tableName()
    {
        return 'language';
    }

    static public function getLanguageId ($language)
    {
        $id = self::getLanguageByName($language)['id'];
        if ($id) return $id;
        else return self::addLanguage($language);
    }

    static protected function getLanguageByName ($name)
    {
        $db = Db::getConnection();
        $sql = 'SELECT * FROM '. self::tableName() .' WHERE name = \''.$name.'\'';
        $result = $db->query($sql);

        if (!$result) return false;
        return $result->fetch();
    }
    
    static public function getLanguageById ($id)
    {
        $db = Db::getConnection();
        $sql = 'SELECT * FROM '. self::tableName() .' WHERE id = \''.$id.'\'';
        $result = $db->query($sql);

        if (!$result) return false;
        return $result->fetch();
    }

    static protected function addLanguage ($name)
    {
        $db = Db::getConnection();
        $sql = 'INSERT INTO '. self::tableName() .'  (`id`, `name`) VALUES (NULL, \''.$name.'\')';
        $result = $db->query($sql);
        return $db->lastInsertId();
    }
}