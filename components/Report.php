<?php
/**
 * Created by PhpStorm.
 * User: ТиМ
 * Date: 19.02.2018
 * Time: 22:44
 */

namespace components;


class Report
{
    static private $errorMessage = '';
    static private $instance;
    
    static public function instance()
    {
        if (! isset(self::$instance))
        {
            self::$instance = new self();
        }
        return self::$instance;
    }
    public function addErrorMessage($message)
    {
        self::$errorMessage .= $message.'<br>';
    }
    public function getErrorMessage()
    {
        return self::$errorMessage;
    }
}