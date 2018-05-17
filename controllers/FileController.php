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
        $res = '';
        if ($_REQUEST)
        {
            if ($_FILES['importfile']['error'] == 0) {
                $file = $_FILES['importfile']['tmp_name'];
                $xml = new ReadXML($file);
                $res = $xml->getFileData();
                $xml->createWikiFile('../dokuwiki/data/pages/docs/ccs.txt', $res);
                Report::instance()->addMessage('Download success');
            }
            else Report::instance()->addErrorMessage('Error download file code: ' . $_FILES['importfile']['error']);
            $ans = Report::instance()->getErrorMessage();
        }
        else {$ans = true; $xml_file = '';}
        return $this->render('file/index', ['ans' => $ans, 'res' => $res]);
    }
}