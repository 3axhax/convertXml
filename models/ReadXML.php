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
            if (isset($xml_file->file->function)) {
                $i = 0;
                foreach ($xml_file->file->function as $fun) {
                    $file_data['function'][$i]['name'] = (string)$fun->name;
                    $i++;
                }
            }
            if (isset($xml_file->file->class)) {
                $i = 0;
                foreach ($xml_file->file->class as $class) {
                    $file_data['class'][$i]['name'] = (string)$class->name;
                    if (isset($class->property)) {
                        $j = 0;
                        foreach ($class->property as $property) {
                            $file_data['class'][$i]['property'][$j]['name'] = (string)$property->name;
                            $file_data['class'][$i]['property'][$j]['description'] = (string)$property->docblock->tag['description'];
                            $j++;
                        }
                    }
                    if (isset($class->method)) {
                        $j = 0;
                        foreach ($class->method as $method) {
                            $arguments = '';
                            $return = '';
                            if (isset($method->argument)) {
                                $arguments = [];
                                foreach ($method->argument as $argument) {
                                    $arguments[] = ((isset($argument->type)) ? (string)$argument->type.' ' : '').
                                        (string)$argument->name.
                                        ((isset($argument->default) && (string)$argument->default != '') ? ' = '.(string)$argument->default.' ' : '');
                                }
                                $arguments = implode(', ', $arguments);
                            }
                            if (isset($method->docblock->tag)) {
                                foreach ($method->docblock->tag as $tag) {
                                    if (($tag['name'] == 'return') && isset($tag['type']) && (string)$tag['type'] != '') $return = ' : '.$tag['type'];
                                }
                            }
                            $file_data['class'][$i]['method'][$j] = (string)$method->name.'('.$arguments.')' . $return;
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
}