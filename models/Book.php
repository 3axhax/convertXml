<?php

namespace models;

use components\Db;
use PDO;
use components\Report;

class Book
{
    public $id;
    public $isbn;
    public $ean;
    public $name;
    public $description;
    public $netto;
    public $brutto;
    public $language;
    public $series;
    public $code;

    static protected function tableName()
    {
        return 'books';
    }

    public function __construct($isbn, $name, $description, $netto, $language, $series, $code)
    {
        $this->isbn = $this->setIsbn($isbn);
        $this->ean = $this->setEan($isbn);
        $this->name = $name;
        $this->description = $description;
        $this->netto = $netto;
        $this->brutto = $this->setBrutto($netto);
        $this->language = (int) $this->setLanguage(trim($language));
        $this->series = (int) $this->setSeries(trim($series));
        $this->code = $code;
        $this->checkSummEAN($this->ean);
        $this->checkBookEan($this->ean);
    }

    protected function setBrutto($netto)
    {
        return round($netto*1.1);
    }

    protected function setIsbn($isbn)
    {
        return preg_replace('/[^\d-]/','',$isbn);
    }

    protected function setEan($isbn)
    {
        return preg_replace('/[^\d]/','',$isbn);;
    }

    protected function checkSummEAN($ean)
    {
        $check = 3*($ean[1] + $ean[3] + $ean[5] + $ean[7] + $ean[9] + $ean[11]) + $ean[0] + $ean[2] + $ean[4] + $ean[6] + $ean[8] + $ean[10];
        $check = $check % 10;
        if ($check != 0) $check = 10 - $check;
        if ($this->ean[12] != $check)
        {
            $this->ean[12] = $check;
            $this->isbn = substr_replace($this->isbn, $check , -1 , 1);
        }
        return $check;
    }

    protected function setLanguage($language)
    {
        return Language::getLanguageId($language);
    }

    protected function setSeries($series)
    {
        return Series::getSeriesId($series);
    }

    protected function getBookByEan($ean)
    {
        $db = Db::getConnection();
        $sql = 'SELECT * FROM '. self::tableName() .' WHERE ean = \''.$ean.'\'';
        $result = $db->query($sql);

        if (!$result) return false;
        return $result->fetch(PDO::FETCH_ASSOC);
    }

    protected function updateBook($book)
    {
        $dif = array_diff_assoc((array) $this, $book);
        array_shift($dif);
        if ($dif)
        {
            $db = Db::getConnection();
            $sql = 'UPDATE `' . self::tableName() . '` SET ';
            foreach ($dif as $key => $value)
            {
                $sql .= '`'.$key.'` = \''.$value.'\', ';
            }
            $sql = substr_replace($sql, '', -2, 2);
            $sql .= ' WHERE `' . self::tableName() . '`.`ean` = \'' . $this->ean . '\'';
            $db->query($sql);
            $rep = Report::instance();
            $rep->addCountUpdate();
        }
    }

    protected function addBook()
    {
        if ($this->validateBook())
        {
            $db = Db::getConnection();
            $sql = 'INSERT INTO `' . self::tableName() . '` (';
            $sql_values = 'VALUES (';
            foreach ((array)$this as $key => $value) {
                $sql .= '`' . $key . '`, ';
                $sql_values .= '\'' . $value . '\', ';
            }
            $sql = substr_replace($sql, '', -2, 2);
            $sql_values = substr_replace($sql_values, '', -2, 2);
            $sql .= ') ';
            $sql_values .= ')';
            $sql .= $sql_values;
            $db->query($sql);
            $rep = Report::instance();
            $rep->addCountAdd();
        }
        else
        {
            $rep = Report::instance();
            $rep->addCountError();
            $rep->addErrorMessage('Ошибка: неверный формат данных');
        }
    }

    protected function checkBookEan($ean)
    {
        if (($book = $this->getBookByEan($ean))) $this->updateBook($book);
        else $this->addBook();
    }

    protected function validateBook()
    {
        if (count($this->ean) == 13) return true;
        else return false;
    }

    static public function getBookList()
    {
        $db = Db::getConnection();
        $bookList = array();
        $sql = 'SELECT * FROM '. self::tableName();
        $result = $db->query($sql);
        
        $i = 0;
        while($row = $result->fetch()) {
            $bookList[$i]['id'] = $row['id'];
            $bookList[$i]['isbn'] = $row['isbn'];
            $bookList[$i]['ean'] = $row['ean'];
            $bookList[$i]['name'] = $row['name'];
            $bookList[$i]['description'] = $row['description'];
            $bookList[$i]['netto'] = $row['netto'];
            $bookList[$i]['brutto'] = $row['brutto'];
            $bookList[$i]['language'] = Language::getLanguageById($row['language'])['name'];
            $bookList[$i]['series'] = Series::getSeriesById($row['series'])['name'];
            $bookList[$i]['code'] = $row['code'];
            $i++;
        }

        return $bookList;
    }
}