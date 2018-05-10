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
            $rep->addCountError();
            $rep->addErrorMessage('Ошибка загрузки файла: '.$e->getMessage());
            return false;
        }
        return $xml_file;
    }
    public function getBooksFromFile()
    {
        libxml_use_internal_errors(true);
        try {
            $xml_file = new SimpleXMLElement(file_get_contents($this->filePath));
        }
        catch (\Exception $e)
        {
            $rep = Report::instance();
            $rep->addCountError();
            $rep->addErrorMessage('Ошибка загрузки файла: '.$e->getMessage());
            return false;
        }
        $books = array();
        foreach ($xml_file->book as $book)
        {
            $books[] = new Book((string) $book->isbn, (string) $book->name, (string) $book->description, (string) $book->price, (string) $book->language, $this->getSeries($book), (int) $book['id']);
        }
        return $books;
    }
    private function getSeries($book)
    {
        foreach ($book->param as $param)
        {
            if ((string) $param['name'] == 'Серия') return (string) $param;
        }
        return 'Series don\'t set';
    }
}