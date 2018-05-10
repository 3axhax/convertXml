<?php

use components\Report;
use models\Book;
use models\ReadXML;
use controllers\SiteController;

class BookController extends SiteController
{
    public function actionList()
    {
        $books = Book::getBookList();
        $this->setTitle('Список книг');
        return $this->render('books/list', ['books' => $books]);
    }
    public function actionAddFile()
    {
        if ($_REQUEST)
        {
            if ($_FILES['importfile']['error'] == 0) {
                $file = $_FILES['importfile']['tmp_name'];
                $xml = new ReadXML($file);
                $xml->getBooksFromFile();
            }
            else $ans = 'Error download file code: ' . $_FILES['importfile']['error'];
        }
        else {$ans = true; $xml_file = '';}
        $this->setTitle('Добавить файл данных');
        return $this->render('books/add_file', ['ans' => $ans]);
    }
}