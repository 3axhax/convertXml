<?php

/**
 * Класс для работы с входом выходом в сервис
 */
class Session extends CCS {

    /**
     * @var string $ct_hash
     */
    public $ct_hash = null;

    /**
     * @var string $login_by_website
     */
    public $login_by_website = null;

	function __construct() {
		parent::__construct();
	}

    /**
      * Функция показа страницы
      *
      * @return void
      */

	function show_page() {
		$this->ccs_init();
		$this->show_main_menu = false;
		$this->user_ip = $_SERVER["REMOTE_ADDR"];

        $password_auth = isset($_GET['password_auth']) && $_GET['password_auth'] == 1 ? true : false;
        $password_auth = $this->token_user ? true : false;

		switch ( $this->link->id ){
			case 3: {
                $this->page_info['bsdesign'] = true;
                $this->logout();
                break;
            }
			case 7: {
                $this->check_authorize();
                $this->page_info['bsdesign'] = true;
                $this->reset_password();
                break;
            }
			case 11:
			    if (isset($_GET['changed'])) {
			        setcookie('user_token', null, -1, '/');
                    //apc_delete($this->account_label);
                    unset($this->user_info['read_only']);
                }
			    if (!isset($_GET['reset_token'])) $this->check_authorize();
			    $this->new_password();
                break;
			case 22:
                $this->check_authorize();
                $this->get_lang($this->ct_lang,'FirstPage');
                $this->delete_account();
                break;
			default:
                if(!$this->app_mode && $this->check_access() && !$password_auth){
                    $this->url_redirect('');
                    return true;
                }
                if (isset($this->lang)) {
                    $this->page_info['head']['title'] = $this->lang['l_dashboard_login'];
                    $this->page_info['password_notice'] = $this->lang['l_look_password_at_email'];
                }
                $this->page_info['jsf_focus_on_field'] = 'login';

                // Если есть логин и хеш пароля в куках, то работаем с ними
                $from_cooks = false;
                $info = null;
                if (isset($_COOKIE['ct_email']) && isset($_COOKIE['ct_hash']) && !$this->app_mode){
                    $info['login'] = $_COOKIE['ct_email'];
                    $info['password'] = $_COOKIE['ct_hash'];
                    $from_cooks = true;
                }
                if (cfg::debug) {
                    error_log(sprintf("User auth %s, ct_hash %s, cookie_domain %s.",
                        $info['login'],
                        $info['password'],
                        $this->cookie_domain
                    ));
                }
                if ($this->app_mode)
                    $this->app_return['success'] = 0;

                $redirect_url = '';

                if (isset($_GET['back_url']))
                  {

                    $uri_expl = explode('back_url=',$_SERVER['REQUEST_URI']);
                    $back_url = $uri_expl[1];
                  }
                else {
                  $back_url = '';
                }

                if (preg_match("/^[a-z\/\-\_0-9\=\?\.\@\%\&]+$/i", $back_url)) {
                    if (preg_match("/^\//", $back_url)) {
                        $redirect_url = $back_url;
                    } else {
                        $redirect_url = "/" . $back_url;
                    }
                }

                // Дабы работали редиректы в NOC
                $redirect_prefix = '/my';
                if (preg_match("/^\/noc/i", $back_url)) {
                    $redirect_prefix = '';
                }
                if (preg_match("/^\/spambots-check/i", $back_url)) {
                    $redirect_prefix = '';
                }

                $redirect_url = $redirect_prefix . $redirect_url;

                $this->page_info['redirect_url'] = $redirect_url;

                if (isset($_POST['login']) && isset($_POST['password']) || ($from_cooks && !$password_auth)) {
                    // Javascript проверка
                    if (count($_POST) && !$this->app_mode) {
                        if ((!isset($_POST['year']) || $_POST['year'] != gmdate('Y'))
                            || (!isset($_POST['month']) || ($_POST['month'] + 1) != gmdate('n'))
                            ) {
                            $this->url_redirect('');
                            exit;
                        }
                    }
                    $logged = false;
                    // Если авторизуемся не из кук, тогда берем данные из $_POST
                    if (!$from_cooks) {
                        $info = $this->safe_vars($_POST);
                    }
                    if ($this->login($info, $from_cooks)) {
                        $logged = true;
                        $login = addslashes($info['login']);
                        $login = trim($login);
                        if ($this->login_by_website) {
                            $login = $this->login_by_website;
                        }
                    }

                    if ($this->app_mode) {
                        $this->app_return['success'] = (int) $logged;
                        if ($logged) {
                            $app_session_id = md5($this->options['app_session_id_prefix'] . ':' . $login);
                            if ($app_session_id) {
                                $this->app_return['app_session_id'] = $app_session_id;
                                $this->db->run(sprintf('update users set app_session_id = %s where email = %s;',
                                        $this->stringToDB($app_session_id), $this->stringToDB($login)));
                            }

                            //
                            // Apple unique token to push notifcations
                            //
                            if (isset($info['app_device_token']) && preg_match("/^[a-f0-9]{64}$/", $info['app_device_token'])) {
                                $sql = sprintf('update users set app_device_token = null where app_device_token = %s;',
                                    $this->stringToDB($info['app_device_token'])
                                );
                                $this->db->run($sql);
                                $sql = sprintf('update users set app_device_token = %s where email = %s;',
                                    $this->stringToDB($info['app_device_token']),
                                    $this->stringToDB($login)
                                );
                                $this->db->run($sql);
                            }

                            //
                            // Android unique sender id
                            //
                            if (isset($info['app_sender_id']) && preg_match("/^[a-z0-9\-\_\:]+$/i", $info['app_sender_id'])) {
                                $sql = sprintf('update users set app_sender_id = null where app_sender_id = %s;',
                                    $this->stringToDB($info['app_sender_id'])
                                );
                                $this->db->run($sql);
                                $sql = sprintf('update users set app_sender_id = %s where email = %s;',
                                    $this->stringToDB($info['app_sender_id']),
                                    $this->stringToDB($login)
                                );
                                $this->db->run($sql);
                            }
//                            error_log(print_r($info, true));
                        }
                    }
                    if (!$this->app_mode) {
                        if ($logged) {
                            // Устанавливаем новый ct_email
                            setcookie('ct_email', $login, time() + 3600 * 24 * 30, '/', $this->cookie_domain);

                            if (!$from_cooks) {
                                // Записываем хеш пароля, дабы включился механизм авторизации через куки
                                setcookie('ct_hash', $this->ct_hash, time() + 3600 * 24 * 30, '/', $this->cookie_domain);
                            }
                            // Удаляем токен, дабы была авторизация по паролю.
                            if ($this->token_user) {
                                setcookie('user_token', '', -1, '/', $this->cookie_domain);
                            }
                            // Показ промо-страницы
                            if ($this->ct_lang != 'ru' && !$from_cooks) {
                                setcookie('cp_promo', $redirect_url, 0, '/', $this->cookie_domain);
                            }
                            header("Location:" . $redirect_url);
                            exit;
                        } else {
                            // Очищаем поле пароля, т.к. содержит md5 хеш, а кука уже не действительна
                            if ($from_cooks){
                                $info['password'] = '';
                                setcookie('ct_hash', '', time() - 3600, '/', $this->cookie_domain);
                            }
                        }
                    }
                }

                // Автоматически заполняем поле email дабы пользователю было проще войти в Панель управления
                if (isset($_GET['email']) && $this->valid_email($_GET['email']) && $info === null)
                    $this->page_info['info']['login'] = $_GET['email'];
/*
                $welcome_notice = false;
                if ($this->token_user || isset($_SESSION['user_info']['id'])) {
                    $welcome_notice = true;
                }
                if ((isset($_GET['password_login']) || isset($_GET['back_url'])) && !isset($_SESSION['user_info']['id'])) {
                    $welcome_notice = false;
		            $this->page_info['jsf_focus_on_field'] = 'password';
                }
                if (isset($_GET['welcome_notice'])) {
                    $welcome_notice = true;
                }
                if ($welcome_notice) {
                    $this->page_info['welcome_notice'] = $welcome_notice;
                    $this->page_info['redirect_notice'] = sprintf($this->lang['l_redirect_notice'], $redirect_url);
		            $this->page_info['header']['refresh'] = "1;url=$redirect_url";

                }
*/
                // Если авторизацию по токену, то удаляем информацию о пользователе со страницы.
                if ($this->token_user) {
                    $this->page_info['show_auth_notice'] = true;
                }

                // Если в apc лежит одноразовый пароль то принудительно показываем
                // форму ввода одноразового пароля
                if (isset($_COOKIE['show_onetime_code']) && $_COOKIE['show_onetime_code'] == 1)
                    $this->page_info['show_onetime_code'] = true;

                $this->page_info['bsdesign'] = true;

                break;
		}

		$this->display();
	}

    /**
      * Функция удаления аккаунта из базы данных
      *
      * @return bool
      */

    function delete_account() {
		if (!$this->check_access())
			$this->url_redirect('session', null, true);

        $this->page_info['show_free_offer'] = 0;
        if ($this->user_info['first_pay_id'] === null && $this->user_info['tariff']['cost'] > 0)
            $this->page_info['show_free_offer'] = 1;

        $notice = '';
        if (isset($_POST['notice']) && $_POST['notice'] != '') {
            $this->page_info['notice'] = $_POST['notice'];
            $notice = addslashes(strip_tags($_POST['notice']));
		    $this->page_info['notice'] = $notice;
        }

        $password_error = false;
        if (isset($_POST['password'])) {
            $password = $_POST['password'];
			if (md5(cfg::password_prefix . $password) === $this->user_info['password']) {
				//	
				// Switching to accounts language.
				// https://basecamp.com/2889811/projects/8701471/todos/302321229
				//
				$this->get_lang(
					in_array($this->user_info['lang'], $this->ct_langs) ? $this->user_info['lang'] : $this->options['default_lang'], 
					$this->link->class
				);
                $this->db->run(sprintf("insert into users_deleted (user_id, email, created, fk_tariff, deleted, notice) 
                                            values(%d, %s, %s, %d, now(), %s);",
                                $this->user_id,
                                $this->stringToDB($this->user_info['email']),
                                $this->stringToDB($this->user_info['created']),
                                $this->user_info['fk_tariff'],
                                $this->stringToDB($notice)));

                $this->db->run(sprintf("delete from users where user_id = %d", $this->user_id));
                $this->db->run(sprintf("delete from services where user_id = %d", $this->user_id));
                $this->db->run(sprintf("delete from requests_stat where user_id = %d", $this->user_id));
                $this->db->run(sprintf("delete from requests_stat_services where user_id = %d", $this->user_id));
                $this->db->run(sprintf("delete from requests where user_id = %d", $this->user_id));
                $this->db->run(sprintf("delete from ips where user_id = %d", $this->user_id));
                $this->post_log(sprintf("Удален акаунт %s (%d), причина удаления \"%s\".",
                        $this->user_info['email'], $this->user_id, $notice));

				$this->get_lang(
					in_array($this->user_info['lang'], $this->ct_langs) ? $this->user_info['lang'] : $this->options['default_lang'], 
					$this->link->class
				);

                $email_title = sprintf($this->lang['l_acount_deleted_email_title'], $this->user_info['email']);
                $email_body = sprintf($this->lang['l_acount_deleted_email_body'],
                                        $this->user_info['email'], date("H:m:s"), $this->remote_addr, $notice);

                $this->send_email($this->user_info['email'], $email_title, $email_body);

                // Если установлена кука с хешем пароля, то удаляем ее дабы можно было переавторизоваться
                if (isset($_COOKIE['ct_hash'])) {
                    setcookie('ct_hash', '', time() - 3600, '/', $this->cookie_domain);
                }
                setcookie('ct_email', '', time() - 3600, '/', $this->cookie_domain);

		        session_destroy();

                header("Location:/good-luck");
                exit;
            }
            $password_error = true;
        }

        if ($password_error) {
            $this->page_info['password_error'] = $this->lang['l_password_error'];
            sleep(cfg::fail_timeout);
        }
        $this->page_info['delete_account_title'] = sprintf($this->lang['l_delete_account_title'], $this->user_info['email']);
        $this->page_info['head']['title'] = $this->page_info['delete_account_title'];
		$this->page_info['jsf_focus_on_field'] = 'password';
        $this->page_info['trial'] = $this->user_info['trial'];
        $this->page_info['bsdesign'] = true;

        /*
            Хостинги с бесплатным CleanTalk
        */
        if ($this->user_info['trial'] == 1) {
            $this->show_free_hostings();
        }

        return true;
    }

    /**
      * Функция входа в сервис
      *
      * @param array $info Массив логин и пароль
      *
      * @param bool $from_cooks признак логина с помощью кук
      *
      * @return bool
      */

	function login($info = null, $from_cooks = false) {

        if (!isset($this->lang)) {
            $this->lang = null;
        }
        $result = false;

        $login = $info['login'];
        $login = addslashes($login);
        $login = htmlentities($login);
        $login = trim($login);

        $call_return = false;
        if (!$this->valid_email($login)) {
            $call_return = true;
        }

        if (!$call_return && !$this->check_email($login)){
            $call_return = true;
        }

        /*
            Логика авторизации по вебсайту
        */
        $website_auth = false;
        $website_hostname = null;
        if ($call_return) {
            $tools = new CleanTalkTools();
            $website_hostname = $tools->get_domain($login);
        }
        if ($call_return && $website_hostname){
            $sql = sprintf("
                select s.user_id, u.email from services s left join users u on u.user_id = s.user_id where s.hostname = %s;
                ",
                $this->stringToDb($website_hostname)
                );

            $rows = $this->db->select($sql, true);

            $accounts_count = 0;
            $email_by_website = null;
            foreach ($rows as $v) {
                if (isset($v['email'])) {
                    $accounts_count++;
                    $email_by_website = $v['email'];
                }
            }

            // Авторизацию по сайту делаем только для уникальных сайтов
            if ($accounts_count == 1) {
                $login = $email_by_website;
                $call_return = false;
                $website_auth = true;
            }
        }

        if ($call_return && !$this->app_mode){
            $this->page_info['login_failed'] = $this->lang['l_login_failed'];
            $this->page_info['info'] = &$info;
            $this->post_log(sprintf("Ошибка авторизации в ПУ, неизвестный пользователь \'%s\'.", $login));
            return $result;
        }
        $db_user = (object) $this->db->select(sprintf(sql::find_user, strtolower($login)));

        $bad_password = 1;
        $password = $info['password'];
        // Если пароль не из кук значит его надо захешировать перед сравнением с паролем в базе
        if (!$from_cooks)
            $password = md5($password);

        // Сопоставляем полученный пароль с тем что есть в базе.
        if ($db_user && $db_user->password === $password)
            $bad_password = 0;

        // Второй раз делаем для авторизации по новой схеме с применением приставки безопасности
        if ($db_user && $bad_password){
            $password = md5(cfg::password_prefix . $info['password']);

            if ($db_user->password === $password)
                $bad_password = 0;
        }

        if ($db_user && !$bad_password) {
            $this->set_session_auth_params(json_decode(json_encode($db_user), true));

            if ($from_cooks)
                $this->post_log(sprintf(messages::user_logged_in_cooks, $_SESSION['user_info']['login'], $db_user->user_id));
            else
                $this->post_log(sprintf(messages::user_logged_in, $_SESSION['user_info']['login'], $db_user->user_id));

            // Записываем дату и время последнего посещения Панели управления
            $this->db->run(sprintf('update users_info set my_last_login = now(), ip = \'%s\' where user_id = %d;', $this->remote_addr, $db_user->user_id));

            $this->ct_hash = $password;

            $result = true;
        }

        if ($db_user && $website_auth) {
            $this->post_log(sprintf("Нашли пользователя %s (%d) по адресу вебсайта %s.",
                $login,
                $db_user->user_id,
                $website_hostname
            ));

            $this->login_by_website = $login;

            setcookie('ct_email', $login, strtotime("+30 day"), '/', $this->cookie_domain);
        }

        $user_token = null;
        if ($result == false && cfg::allow_token_login) {
            /*
                Авторизация по токену если найден логин
            */
            if ($db_user && $bad_password && !isset($_GET['password_login']) && !isset($_COOKIE['user_token'])) {
                $sql = sprintf("
                    select u.user_token from users u where u.email = %s;
                    ",
                    $this->stringToDb($login)
                    );

                $row = $this->db->select($sql);
                if (isset($row['user_token'])) {
                    $user_token = $row['user_token'];
                }
            }
        }

		$show_onetime_code = false;
		$bad_password_count = 0;
        $this->page_info['show_onetime_code'] = $show_onetime_code;

        //
        // Переходим к авторизации по одноразовому паролю.
        //
        if (isset($_POST['onetime_pass_code_switch'])) {
			if ($_POST['onetime_pass_code_switch'] == 1 && !$this->app_mode) {
            	$show_onetime_code = true;
			}
			if ($_POST['onetime_pass_code_switch'] == -1) {
            	$bad_password_count = -1;
				setcookie('show_onetime_code', 0, -1, '/', $this->cookie_domain);
				header("Location:/my/session");
				exit;
			}
		}
        if (($bad_password && $bad_password_count >= 0) || $show_onetime_code) {
            $bad_password_count_label = 'bad_password_count:' . $login;
            $bad_password_count = apc_fetch($bad_password_count_label);

            if (!$show_onetime_code) {

                if ($bad_password_count)
                    $bad_password_count++;
                else
                    $bad_password_count = 1;
                apc_store($bad_password_count_label, $bad_password_count, 300);
            }

            $onetime_code = null;
            if (($bad_password_count >= cfg::switch_to_onetime_code && cfg::switch_to_onetime_code > 0)
                || $show_onetime_code
                || isset($info['onetime_code'])) {
                $onetime_code_label = 'onetime_code:' . $login;
				$onetime_code = apc_fetch($onetime_code_label);
                if (!$onetime_code) {
                    $onetime_code = $this->generateVCode(cfg::onetime_code_length);

                    $onetime_code_email_message = sprintf($this->lang['l_onetime_code_email_message'], $onetime_code);

                    if ($this->send_email($db_user->email, $this->lang['l_onetime_code_email_title'], $onetime_code_email_message)) {
                        $this->post_log(sprintf("Отправили пользователю %s (%d) одноразовый пароль.",
                            $login,
                            $db_user->user_id
                        ));
                    }

                    apc_store($onetime_code_label, $onetime_code, cfg::apc_cache_lifetime_long);
                }
//		var_dump($show_onetime_code, $bad_password_count, $_POST['onetime_pass_code_switch'], $onetime_code);
				$this->page_info['show_onetime_code'] = true;
                if (!isset($_COOKIE['show_onetime_code']))
                    setcookie('show_onetime_code', 1, time() + 300, '/', $this->cookie_domain);
                $this->page_info['jsf_focus_on_field'] = 'onetime_code';
                $this->page_info['onetime_code_notice'] = $this->lang['l_onetime_code_notice'];
                $this->page_info['onetime_code_length'] = cfg::onetime_code_length;


                $onetime_code_user = null;
                if (isset($info['onetime_code']) && preg_match("/^\d+$/", $info['onetime_code'])) {
                    $onetime_code_user = $info['onetime_code'];

                    $onetime_code_count_lable= 'onetime_code_count';

                    if ($onetime_code == $onetime_code_user) {
                        $this->post_log(sprintf("Пользователь %s (%d) авторизовался по one-time pass.",
                            $login,
                            $db_user->user_id
                        ));
                        $result = true;

                        $this->set_session_auth_params(json_decode(json_encode($db_user), true), true);

                        apc_delete($bad_password_count_label);
                        apc_delete($onetime_code_label);
                    } else {
                        $onetime_code_count = 0 ;
                        $onetime_code_count = apc_fetch($onetime_code_count_lable);
                        if ($onetime_code_count || cfg::onetime_code_fail_count == 1) {
                            $onetime_code_count++;
                            if ($onetime_code_count >= cfg::onetime_code_fail_count) {
                                $onetime_code_count = 0;
                                apc_delete($bad_password_count_label);
                                apc_delete($onetime_code_label);
                                $info['password'] = '';
                            }
                        } else {
                            $onetime_code_count = 1;
                        }

                        $this->post_log(sprintf("Ошибка авторизации пользователя %s (%d) по one-time pass.",
                            $login,
                            $db_user->user_id
                        ));
                        apc_store($onetime_code_count_lable, $onetime_code_count);
                        $this->page_info['onetime_code_fail_notice'] = $this->lang['l_onetime_code_fail_notice'];
                    }
                } else {
                    if (isset($info['onetime_code'])) {
                        $this->page_info['onetime_code_fail_notice'] = $this->lang['l_onetime_code_wrong'];
                    }
                }
            }
            if (!$result) {
                $this->post_log(sprintf(messages::login_failed_sys, $login));
                sleep(cfg::fail_timeout);
            }
        }

        if ((isset($_GET['password_login']) || isset($_COOKIE['user_token']) || $bad_password)
            && $this->page_info['show_onetime_code'] == false
            && $result === false
            && !$this->app_mode
            ) {
            $this->page_info['password_notice'] = sprintf("<span class=\"red\">%s</span>", $this->lang['l_password_missmatch']);
            $this->page_info['jsf_focus_on_field'] = 'password';
        }

       //
        // Переходим на новую страницу для активации авторизации по токену.
        //
        if ($user_token) {
            $back_url = '';
            if (isset($_GET['back_url'])) {
                $back_url = "&back_url=" . urlencode($_GET['back_url']);
            }
            header(sprintf("Location:/my/session?welcome_notice=1&user_token=%s%s",
                $user_token,
                $back_url
            ));
            exit;
        }
        $this->page_info['info'] = $info;

        return $result;
	}


    /**
      * Функция выхода из сервиса
      *
      * @return void
      */
	function logout() {
		if (!$this->check_access(null, true)) {
            return null;
	    }
		if (isset($this->lang['l_logged_out']))
			$this->page_info['head']['title'] = $this->lang['l_logged_out'];

		// Если установлена кука с хешем пароля, то удаляем ее дабы можно было переавторизоваться
		setcookie('ct_hash', '', time() - 3600, '/', $this->cookie_domain);

        // Удаляем токен пользователя
		setcookie('user_token', '', time() - 3600, '/', $this->cookie_domain);

        // Удаляем куку двухфакторной авторизации
        setcookie('gaath', '', time() - 3600, '/', $this->cookie_domain);

        // Удаляем куку для принудительного показа
        // формы одноразового пароля
        setcookie('show_onetime_code', 1, time() - 300, '/', $this->cookie_domain);

        // Удаляем данные в кэше
        if (isset($_SESSION['user_info']['id'])) {
            $apcid = $_SESSION['user_info']['id'];
            apc_delete($apcid);
            apc_delete($apcid . '_token');
        }

		$this->page_info['header']['refresh'] = "5;url=/";

        if (isset($_GET['authorize'])) {
            $this->smarty_template = 'includes/authorize.html';
            $this->page_info['header']['refresh'] = "2;url=/my/session?email=".$this->user_info['email'];
        }

        if (isset($_SESSION['user_info']['email'])) {
            $this->post_log(sprintf(messages::user_logged_out, $this->user_info['email']));
            session_destroy();
        }

        return null;
	}

    /**
      * Функция установки нового пароля
      *
      * @return bool
      */

	function new_password(){
		if(!$this->check_access(false, true)){
			$this->url_redirect('session', null, 'new_password');
			return 1;
		}

		if (isset($this->lang['l_newpass_title']))
			$this->page_info['head']['title'] = $this->lang['l_newpass_title'];

		$this->page_info['jsf_focus_on_field'] = 'password';

        // Логика доступа к интерфейсу смены пароля.
        $user_sql = 'select email, user_token from users where email = %s;';
        $reset_password_mode = false;
        if (isset($_GET['reset_token']) && preg_match("/^\w+$/", $_GET['reset_token'])) {
            $reset_token = $_GET['reset_token'];
            $reset_token_label = 'reset_token:' . $reset_token;
            $email = apc_fetch($reset_token_label);
            if ($email) {
                $user = $this->db->select(sprintf($user_sql, $this->stringToDB($email)));
                if (isset($user['email'])) {
                    $reset_password_mode = true;
                }
            } else {
                $this->post_log(sprintf("Неизвестный токен для сброса пароля %s.", __FILE__));
                sleep(cfg::fail_timeout);
            }
        }

        /*if (isset($_SESSION['user_info']['onetime_code_change_password_allow']) && $_SESSION['user_info']['onetime_code_change_password_allow'] == true) {
           $reset_password_mode = true;
        }*/

        if ($reset_password_mode) {
            $this->page_info['reset_password_mode'] = $reset_password_mode;
            $this->page_info['reset_password_notice'] = sprintf($this->lang['l_reset_password_notice'], $this->user_info['email']);
            $this->page_info['jsf_focus_on_field'] = 'new_password';
        }

        $tools = new CleanTalkTools();
        $form_token = $tools->get_form_token();
        $this->page_info['form_token'] = $form_token;

        if (isset($_POST['password']) && isset($_POST['new_password'])){
			$info = $this->safe_vars($_POST);

            // CSRF проверка

            if (!isset($info['form_token']) || $info['form_token'] != $form_token) {
                $message = $this->lang['l_security_breach'];
                $errors[] = $message;
                $this->post_log(strip_tags($message) . ' ' . __FILE__ . ' ' . __LINE__);
            }

            if (isset($errors) && count($errors)){
                $this->page_info['errors'] = &$errors;
                $this->page_info['info'] = &$info;
                $this->page_info['bsdesign'] = true;
                $this->page_info['jsf_focus_on_field'] = $focus_on_field;
                return false;
            }

			$password = $_POST['password'];
			$new_password = $_POST['new_password'];
			$bad_password = 1;
			if ($this->user_info['password'] === md5($password))
				$bad_password = 0;
			// Второй раз сравниваем введенный пароль с использованием приставки
			if ($bad_password && $this->user_info['password'] === md5(cfg::password_prefix . $password))
				$bad_password = 0;

            if ($reset_password_mode)
                $bad_password = 0;

			if ($bad_password){
				$this->page_info['info'] = &$info;
				$this->page_info['password_error'] = $this->lang['l_password_unknown_notice'];
				$this->post_log($this->page_info['password_error']);
                $this->page_info['bsdesign'] = true;
				sleep(cfg::fail_timeout);
				return false;
			}

            if (strlen($new_password) < cfg::min_password_length){
				$this->page_info['info'] = &$info;
				$this->page_info['new_password_error'] = sprintf($this->lang['l_min_len_notice'], cfg::min_password_length);
				$this->post_log($this->page_info['new_password_error']);
				$this->page_info['jsf_focus_on_field'] = 'new_password';
                $this->page_info['bsdesign'] = true;
				return false;
			}


			$new_password = md5(cfg::password_prefix . $new_password);
			if ($this->db->run(sprintf("update users set password = '%s' where user_id = %d;", $new_password, $this->user_info['user_id']))){
				$this->post_log(sprintf("Пользователь %s (%d) изменил пароль.", $this->user_info['email'], $this->user_id));
				if (!$this->db->run(sprintf("update users_info set password_changed = now() where user_id = %d;", $this->user_info['user_id'])))
					return false;

				$message = sprintf($this->lang['new_password_message'], cfg::domain);
				if (!$this->send_email($this->user_info['email'], $this->lang['l_password_changed'], $message)){
					$this->page_error(messages::email_failed, null);
					$this->post_log(sprintf(messages::reset_email_not_sent, $this->user_info['email']));
					$this->page_info['info'] = &$info;
					return false;
				};

                // Сбрасываем флаг смены пароля
                setcookie('setup_new_password', 0, null, '/', $this->cookie_domain);

                if ($reset_password_mode && isset($reset_token_label)) {
                    apc_delete($reset_token_label);
                }

                if (isset($_SESSION['user_info']['onetime_code_change_password_allow'])) {
                    $_SESSION['user_info']['onetime_code_change_password_allow'] = false;
                }

                // Удаляем информацию об акаунте из кеша.
                apc_delete($this->account_label);

				$this->url_redirect('new_password?changed=1');
				return true;
			}else{
				return false;
			}
		}
		$this->page_info['info'] = &$info;
        $this->page_info['bsdesign'] = true;
		return true;
	}

    /**
      * Функция восстановления пароля
      *
      * @return bool
      */

	function reset_password(){
		$cookie_name = 'reset_email';
        $user_sql = 'select email, user_token from users where email = %s;';

        if (!isset($_COOKIE[$cookie_name])){
			$this->page_info['jsf_focus_on_field'] = 'email';
		}

        if (isset($_GET['forgotten_email'])){
			$this->page_info['jsf_focus_on_field'] = 'auth_key';
		}

		if (isset($this->lang['l_resetpass_title']))
			$this->page_info['head']['title'] = $this->lang['l_resetpass_title'];

        if (isset($_GET['password_reseted'])) {
            $this->page_info['password_reseted'] = true;
        }

		$password_reset_timeout_label = 'password_reset_timeout';
		if (isset($_COOKIE['ct_email'])) {
        	$password_reset_timeout_label .= ':' . $_COOKIE['ct_email'];
		}

        $password_reset_timeout = apc_fetch($password_reset_timeout_label);
        if ($password_reset_timeout) {
            $this->page_info['password_reseted'] = true;
            return true;
        }

		if (count($_POST)){
			$info = $this->safe_vars($_POST);
			$email = $info['email'];
			$email = addslashes($email);

			$email_found = false;

			if ($this->valid_email($email) && $this->check_email($email)) {
                $user = $this->db->select(sprintf($user_sql, $this->stringToDB($email)));

                if (isset($user['email'])) {
				    $email_found = true;
			    } else {
                    $this->post_log(sprintf(messages::password_reset_failed, $email));
                    $this->page_info['email_error'] = $this->lang['l_email_unknown'];
                }
            }
            if (!$email_found && isset($info['auth_key']) && $info['auth_key'] != '') {
                $user = null;
                if (preg_match("/^\w{8,12}$/", $info['auth_key']))
                    $user = $this->db->select(sprintf("select u.email, u.user_token from services s left join users u on u.user_id = s.user_id where s.auth_key = %s;",
                            $this->stringToDB($info['auth_key'])));

                if (isset($user['email'])) {
                    $email = $user['email'];
                    $email_found = true;
                } else {
                    $this->page_info['email_error'] = null;
                    $this->page_info['auth_key_error'] = $this->lang['l_auth_key_unknown'];
			        $this->page_info['jsf_focus_on_field'] = 'auth_key';
                }
            }

			if (!$email_found){
				$this->page_info['info'] = &$info;
                $this->page_info['error'] = $this->lang['l_email_unknown'];
				sleep(cfg::fail_timeout);
				return false;
			}

            $reset_token = $this->generatePassword(16, 7);
            $reset_token_label = 'reset_token:' . $reset_token;

            apc_store($reset_token_label, $email, 3600);

            $hostname = $this->get_website_name();
            $change_password_link = sprintf("https://%s/my/new_password?reset_token=%s&user_token=%s",
                $hostname,
                $reset_token,
                $user['user_token']
            );

			$message = sprintf($this->lang['reset_password_messagel_link'], $change_password_link, $change_password_link);

			if ($this->send_email($email, $this->lang['l_reset_password_confirmation'], $message)){

                $this->post_log(sprintf("Пользователь %s запросил новый пароль.", $email));
				setcookie($cookie_name, $email, time() + 3600, '/', $this->cookie_domain);

                setcookie('ct_email', $email, strtotime("+30 day"), '/', $this->cookie_domain);

                apc_store($password_reset_timeout_label, time(), cfg::password_reset_timeout);

                // Удаляем информацию об акаунте из кеша.
                if(isset($this->account_label) && is_string($this->account_label))apc_delete($this->account_label);

                $this->url_redirect('reset_password?password_reseted=1');
			}else{
				$this->page_error(messages::email_failed, null);
				$this->post_log(sprintf(messages::reset_email_not_sent, $email));
				$this->page_info['info'] = &$info;
				return false;
			}
		}
		return true;
	}
}
?>
