<?php

namespace models;
use components\Report;
use SimpleXMLElement;

class ReadXML
{
    public $filePath;
    public $report = null;
    
    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }
    public function getXMLElements()
    {
        libxml_use_internal_errors(true);
        $xml_file = '';
        try {
            $xml_file = new SimpleXMLElement(file_get_contents($this->filePath));
        }
        catch (\Exception $e)
        {
            $rep = Report::instance();
            $rep->addErrorMessage('Ошибка загрузки файла: '.$e->getMessage());
            return false;
        }
        return $xml_file;
    }
    public function getFileData()
    {
        if($xml_file = $this->getXMLElements()){
            $file_data = [];
            if (isset($xml_file->file['path'])) {
                    $file_data['path'] = $xml_file->file['path'];
                }
            if (isset($xml_file->file->function)) {
                $i = 0;
                foreach ($xml_file->file->function as $fun) {
                    $file_data['function'][$i]['name'] = (string)$fun->name;
                    $file_data['function'][$i]['full_name'] = (string)$fun->full_name;
                    $file_data['function'][$i]['description'] = (string)$fun->docblock->description;
                    if (isset($fun->docblock->tag)) {
                        $j = 0;
                        foreach ($fun->docblock->tag as $tag) {
                            if ($tag['name'] == 'param') {
                                $file_data['function'][$i]['param'][$j]['type'] = (string)$tag['type'];
                                $file_data['function'][$i]['param'][$j]['name'] = (string)$tag['variable'];
                                $file_data['function'][$i]['param'][$j]['description'] = mbereg_replace('(<p>|</p>)', '', (string)$tag['description']);
                                $j++;
                            }
                            if ($tag['name'] == 'return') {
                                $file_data['function'][$i]['return'] = (string)$tag['type'];
                            }
                        }
                    }
                    $i++;
                }
            }
            if (isset($xml_file->file->class)) {
                $i = 0;
                foreach ($xml_file->file->class as $class) {
                    $file_data['class'][$i]['full_name'] = (string)$class->full_name;
                    $file_data['class'][$i]['long-description'] = (isset($class->docblock->{"long-description"})) ? (string)$class->docblock->{"long-description"} : '';
                    if (isset($class->property)) {
                        $j = 0;
                        foreach ($class->property as $property) {
                            $file_data['class'][$i]['property'][$j]['full_name'] = (string)$property->full_name;
                            $file_data['class'][$i]['property'][$j]['type'] = (string)$property->docblock->tag['type'];
                            $file_data['class'][$i]['property'][$j]['description'] = mbereg_replace('(<p>|</p>)', '', (string)$property->docblock->tag['description']);
                            $j++;
                        }
                    }
                    if (isset($class->method)) {
                        $j = 0;
                        foreach ($class->method as $method) {
                            $file_data['class'][$i]['method'][$j]['full_name'] = (string)$method->full_name;
                            $file_data['class'][$i]['method'][$j]['name'] = (string)$method->name;
                            $file_data['class'][$i]['method'][$j]['description'] = (isset($method->docblock->description)) ? mbereg_replace('(<p>|</p>|\n)', ' ', (string)$method->docblock->description) : '';
                            $file_data['class'][$i]['method'][$j]['long-description'] = (isset($method->docblock->{"long-description"})) ? (string)$method->docblock->{"long-description"} : '';
                            if (isset($fun->docblock->tag)) {
                                $k = 0;
                                foreach ($method->docblock->tag as $tag) {
                                    if ($tag['name'] == 'param') {
                                        $file_data['class'][$i]['method'][$j]['param'][$k]['type'] = (string)$tag['type'];
                                        $file_data['class'][$i]['method'][$j]['param'][$k]['name'] = (string)$tag['variable'];
                                        $file_data['class'][$i]['method'][$j]['param'][$k]['description'] = mbereg_replace('(<p>|</p>)', '', (string)$tag['description']);
                                        $k++;
                                    }
                                    if ($tag['name'] == 'return') {
                                        $file_data['class'][$i]['method'][$j]['return'] = (string)$tag['type'];
                                    }
                                }
                            }
                            $j++;
                        }
                    }
                    $i++;
                }
            }
            $file_data['all'] = $xml_file;
            return $file_data;
        }
        else {
            Report::instance()->addErrorMessage('Convert file fail');
            return false;
        }
    }
    public function createWikiFile($path, $data)
    {
        $file = fopen($path, 'w');

        if (isset($data['path'])) {
            fwrite($file, '====== Файл ======'.PHP_EOL);
            fwrite($file, 'includes/'.$data['path'].PHP_EOL);
        }
        if (isset($data['function'])) {
            fwrite($file, '====== Функции ======'.PHP_EOL);
            foreach ($data['function'] as $fun) {
                fwrite($file, '===='.$fun['full_name'].'===='.PHP_EOL);
                $fun_name = '';
                if (isset($fun['return'])) $fun_name .= '//'.$fun['return'].':// ';
                if (isset($fun['param'])) {
                    $fun_name .= '**'.$fun['name'].'**(';
                    $params = [];
                    foreach ($fun['param'] as $param) $params[] = $param['name'];
                    $params = implode(', ', $params);
                    $fun_name .= $params . ')';
                    fwrite($file, ''.$fun_name.''.PHP_EOL);
                    fwrite($file, '  ***Описание:** '.$fun['description'].''.PHP_EOL);
                    fwrite($file, '  ***Аргументы:**'.PHP_EOL);
                    foreach ($fun['param'] as $param) {
                            fwrite($file, '    *//'.$param['type'].':// '.$param['name'].', '.$param['description'].''.PHP_EOL);
                    }
                }
                else {
                    fwrite($file, '  ***Описание:** '.$fun['description'].''.PHP_EOL);
                }
            }
        }
        if (isset($data['class'])) {
            fwrite($file, '====== Классы ======'.PHP_EOL);
            foreach ($data['class'] as $class) {
                fwrite($file, '===== '.$class['full_name'].'====='.PHP_EOL);
                if (isset($class['long-description']))fwrite($file, $class['long-description'].PHP_EOL);
                if (isset($class['property'])) {
                    fwrite($file, '==== Свойства ===='.PHP_EOL);
                    fwrite($file, '^Имя ^Тип ^Описание^'.PHP_EOL);
                    foreach ($class['property'] as $property) {
                        $type = (isset($property['type'])) ? '//'.$property['type'].'//' : ' ';
                        $description = (isset($property['description'])) ? $property['description'] : ' ';
                        fwrite($file, '|'.$property['full_name'].'|'.$type.'|'.$description.'|'.PHP_EOL);
                    }
                }
                if (isset($class['method'])) {
                    fwrite($file, '==== Методы ===='.PHP_EOL);
                    foreach ($class['method'] as $method) {
                        fwrite($file, '==='.$method['full_name'].'==='.PHP_EOL);
                        $method_name = '';
                        if (isset($method['return']) && $method['return'] != '') $method_name .= '//'.$method['return'].':// ';
                        $method_name .= '**<nowiki>' . $method['name'] . '</nowiki>**(';
                        if (isset($method['param'])) {
                            $params = [];
                            foreach ($method['param'] as $param) $params[] = $param['name'];
                            $params = implode(', ', $params);
                            $method_name .= $params . ')';
                            fwrite($file, '' . $method_name . '' . PHP_EOL);
                            fwrite($file, '  ***Описание:** <nowiki>' . $method['description'] . '. '.$method['long-description'].'</nowiki>' . PHP_EOL);
                            fwrite($file, '  ***Аргументы:**' . PHP_EOL);
                            foreach ($method['param'] as $param) {
                                fwrite($file, '    *//' . $param['type'] . ':// ' . $param['name'] . ', ' . $param['description'] . '' . PHP_EOL);
                            }
                        }
                        else {
                            $method_name .= ')';
                            fwrite($file, '' . $method_name . '' . PHP_EOL);
                            fwrite($file, '  ***Описание:** ' . $method['description'] . '' . PHP_EOL);
                        }
                        fwrite($file,''.PHP_EOL);
                        fwrite($file,' ---- '.PHP_EOL);
                    }
                }
            }
        }
        fclose($file);
    }
}