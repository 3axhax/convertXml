<?php

use controllers\SiteController;
use models\ReadXML;
use components\Report;

class FileController extends SiteController
{
    public function action404(){
        $this->render('main/404');
    }
    public function actionIndex()
    {

        $this->setTitle('Загрузка XML файла');
        $ans = '';
        if ($_REQUEST)
        {
            if ($_FILES['importfile']['error'] == 0) {
                $file = $_FILES['importfile']['tmp_name'];
                print_r($_FILES);
                $xml = new ReadXML($file);
                $ans = $xml->getXMLElements();
            }
            else $ans = 'Error download file code: ' . $_FILES['importfile']['error'];
            //$ans = Report::instance()->getReportMessage();
        }
        else {$ans = true; $xml_file = '';}
        return $this->render('file/index', ['ans' => $ans]);
    }
    public function actionAddFile()
    {
        if ($_REQUEST)
        {
            $file = $_FILES['importfile']['tmp_name'];
            $xml = new ReadXML($file);
            $xml->getBooksFromFile();
            $ans = Report::instance()->getReportMessage();
        }
        else {$ans = true; $xml_file = '';}
        $this->setTitle('Добавить файл данных');
        return $this->render('books/add_file', ['ans' => $ans]);
    }
}