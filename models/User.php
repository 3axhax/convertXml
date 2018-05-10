<?php

namespace models;

use components\Db;
use PDO;

const ADMIN = 'a';
const USER = 'u';

class User
{

    public $report = true;
    public $id;
    public $login;
    public $password;
    public $name;
    public $email;
    public $role;
    public $sortrules = ['id' => 'ASC'];
    public $filter = [  'name' => '',
                        'login' => '',
                        'email' => '',
                        'role' => ''];
    
    public function roleFull()
    {
        switch ($this->role)
        {
            case ADMIN:
                return 'Администратор';
                break;
            case USER:
                return 'Пользователь';
                break;
            default:
                return $this->role;
        }
    }
            
    public function getUserList()
    {
        $db = Db::getConnection();
        $userList = array();
        $sql = 'SELECT * FROM user WHERE ';
        foreach ($this->filter as $field => $value)
        {
            $sql .= '`'.$field . '` LIKE "%' . $value . '%" AND ';
        }
        $sql = substr($sql,0,-4);
        $sql .= 'ORDER BY '.key($this->sortrules).' '.current($this->sortrules);
        $result = $db->query($sql);

        $i = 0;
        while($row = $result->fetch()) {
            $userList[$i]['id'] = $row['id'];
            $userList[$i]['login'] = $row['login'];
            $userList[$i]['password'] = $row['password'];
            $userList[$i]['name'] = $row['name'];
            $userList[$i]['email'] = $row['email'];
            $userList[$i]['role'] = $row['role'];
            $i++;
        }

        return $userList;
    }

    public function getUserById($id)
    {
        $id = intval($id);
        if ($id) {
            $db = Db::getConnection();
            $result = $db->query('SELECT * FROM user WHERE id=' . $id);

            $result->setFetchMode(PDO::FETCH_ASSOC);

            $userItem = $result->fetch();
            
            $this->id = $userItem['id'];
            $this->login = $userItem['login'];
            $this->password = $userItem['password'];
            $this->name = $userItem['name'];
            $this->email = $userItem['email'];
            $this->role = $userItem['role'];

            return $this;
        }
    }

    public static function checkUsersAuth($userlogin)
    {
        $db = Db::getConnection();
        $result = $db->query('SELECT * FROM user WHERE login="'.$userlogin['login'].'" AND password="'.$userlogin['password'].'"');
        $result->setFetchMode(PDO::FETCH_ASSOC);
        return ($_SESSION['user'] = $result->fetch()) ? false : 'Неверный логин/пароль';
    }

    public static function setUserLogout()
    {
        if (isset($_SESSION['user']))
        {
            unset($_SESSION['user']);
        }
    }

    public function validateUser($request)
    {
        if ($this->uniqueUser($request['login'])) {
            if ($request['password'] == $request['confirmpassword']) {
                if (filter_var($request['email'], FILTER_VALIDATE_EMAIL)) {
                    if ($this->allFieldsFill($request)) return true;
                    else {
                        $this->report = 'Не все поля заполнены';
                        return false;
                    }
                } else {
                    $this->report = 'Введён некорректный E-mail';
                    return false;
                }
            } else {
                $this->report = 'Подтверждение пароля не совпадает';
                return false;
            }
        } else {
            $this->report = 'Пользователь "' . $request['login'] . '" уже существует';
            return false;
        }
    }

    private function uniqueUser($login)
    {
        if (isset($this->login) && ($this->login == $login)) return true;
        $db = Db::getConnection();
        $result = $db->query('SELECT * FROM user WHERE login="'.$login.'"');
        return !($result = $result->fetch());
    }

    private function allFieldsFill($request)
    {
        if (isset($request['login']) && isset($request['password']) && isset($request['confirmpassword']) && isset($request['name']) && isset($request['email']) && isset($request['role']))
        {
            if ((trim($request['login']) != '') && (trim($request['password']) != '') && (trim($request['confirmpassword']) != '') && (trim($request['name']) != '') && (trim($request['email']) != '') && (trim($request['role']) != ''))
            {
                return true;
            }
            else return false;
        }
        else return false;
    }

    public function createUser($request)
    {
        $db = Db::getConnection();
        return $result = $db->query('INSERT INTO `user` (`id`, `login`, `password`, `name`, `email`, `role`) VALUES (NULL, "'.$request['login'].'", "'.$request['password'].'", "'.$request['name'].'", "'.$request['email'].'", "'.$request['role'].'");');
    }

    public function editUser($request)
    {
        $db = Db::getConnection();
        $this->login = $request['login'];
        $this->password = $request['password'];
        $this->name = $request['name'];
        $this->email = $request['email'];
        $this->role = $request['role'];
        return $result = $db->query('UPDATE `user` SET `login` = "'.$this->login.'", `password` = "'.$this->password.'", `name` = "'.$this->name.'", `email` = "'.$this->email.'", `role` = "'.$this->role.'" WHERE `user`.`id` = '.$this->id.';');
    }

    public static function deleteUser($id)
    {
        $db = Db::getConnection();
        return $result = $db->query('DELETE FROM `user` WHERE `user`.`id` = '.$id.'');
    }

    public function setUserSort($par)
    {
        unset($this->sortrules);
        $par = explode('_', $par);
        $this->sortrules = [$par[0] => ($par[1] == 'up') ? 'ASC' : 'DESC'];
        return $this->sortrules;
    }
}