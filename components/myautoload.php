<?php
function myautoload($name){
    $path = ROOT.'\\'.$name.".php";
    if(file_exists($path)){
        require_once $path;
    }else{
        throw new Exception("Класс $name по пути $path не обнаружен");
    }
}
spl_autoload_register('myautoload');