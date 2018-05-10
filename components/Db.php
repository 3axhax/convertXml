<?php

namespace components;

use PDO;

class Db
{
    private static $db;
    public static function getConnection()
    {
        if (!isset(self::$db)) 
        {
            $paramsPath = ROOT . '/config/db_conf.php';
            $params = include($paramsPath);


            $dsn = "{$params['type']}:host={$params['host']};dbname={$params['dbname']}";
            self::$db = new PDO($dsn, $params['user'], $params['password']);
        }

        return self::$db;
    }
}