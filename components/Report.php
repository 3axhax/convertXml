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
    static private $errorCount = 0;
    static private $instance;
    
    static public function instance() {
        if (! isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addErrorMessage($message) {
        self::$errorMessage .= 'Error! '.$message.'<br>';
        self::$errorCount++;
    }

    public function addMessage($message) {
        self::$errorMessage .= $message.'<br>';
    }

    public function getCountError() {
        return self::$errorCount;
    }

    public function getErrorMessage() {
        return self::$errorMessage;
    }
}