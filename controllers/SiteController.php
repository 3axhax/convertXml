<?php

namespace controllers;

class SiteController
{
    public static $title = 'Заголовок';
    
    public static function render($views, $params=[])
    {
        extract($params);
        include_once(ROOT. '/components/headlink.php');
        include_once(ROOT. '/views/main/head.php');
        include_once(ROOT. '/views/'.$views.'.php');
        include_once(ROOT. '/components/footerlink.php');
        return true;
    }
    public static function setTitle($title)
    {
        static::$title = $title;
    }
    public function action404(){
        self::render('main/404');
    }
}