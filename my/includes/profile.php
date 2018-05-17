<?php
require_once __DIR__.'/../libs/ImageResize.php'; // Ресайз и кроп графики, для аватарок
require_once __DIR__.'/../../includes/helpers/s3.php'; // S3 для аватарок
use \Eventviva\ImageResize;
/**
 * Класс для работы с профилем пользователя
 */
class Profile extends CCS {
	function __construct() {
		parent::__construct();
		$this->ccs_init();
		if (!$this->check_access()) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }
	}

    /**
      * Функция отображения страницы
      *
      * @return void
      */

	function show_page(){
		switch($this->link->id){
			// Редактирование профиля
			case 19:
			    $this->check_authorize();
				$this->show_profile();
				break;
			case 23:
				$this->page_info['switched_to_free'] = sprintf($this->lang['l_switched_to_free'], $this->user_info['email']);
                // Выводим страницу и завершаем работу функции
                if (isset($_GET['switched']))
                    break;
               // Если пользователь ранее оплатил сервис, либо уже имеет бесплатный акаунт, то запрещяем ползоваться страницой
                if ($this->user_info['first_pay_id'] !== null || $this->user_info['tariff']['cost'] == 0) {
                    $this->url_redirect('main');
                    return false;
                }

                if (isset($_GET['switch'])) {
                    $this->db->run(sprintf("update users set fk_tariff = %d, max_pm = max_pm + %d where user_id = %d;", cfg::free_tariff_id, $this->tariffs[cfg::free_tariff_id]['pmi'], $this->user_id));
                    $this->post_log($this->page_info['switched_to_free']);
                    $message = sprintf("
                        Пользователь: %s\n<br />
                        Дата регистрации: %s\n<br />
                        Предыдущий тариф: %s\n<br /><br />

                        Переключился на бесплатный тариф - %s (%s).
                    ",
                        $this->user_info['email'],
                        $this->user_info['created'],
                        $this->tariffs[$this->user_info['fk_tariff']]['name'],
                        $this->tariffs[cfg::free_tariff_id]['info'],
                        $this->tariffs[cfg::free_tariff_id]['name']
                        );
                    $this->send_email(cfg::noc_email, 'Переход на бесплатную подписку ' . $this->user_info['email'], $message);
                    $this->url_redirect('get-free-account?switched=1');
                }
				$this->page_info['switch_to_free'] = sprintf($this->lang['l_switch_to_free'], $this->user_info['email']);
				$this->page_info['tariff_offer'] = $this->tariffs[cfg::free_tariff_id];
			    
                $this->page_info['head']['title'] = $this->page_info['switch_to_free'];
                break;
			// Смена email
			case 24:
			    $this->check_authorize();
                $this->page_info['bsdesign'] = true;
                $this->page_info['head']['title'] = $this->lang['l_change_email'];
                $this->page_info['jsf_focus_on_field'] = 'password';
			    $this->get_lang($this->ct_lang, 'Session');
                
                if (isset($_GET['new_email']) && $this->valid_email(urldecode($_GET['new_email']))) {
                    $this->page_info['jsf_focus_on_field'] = 'activation_code';
                    $this->page_info['new_email'] = urldecode($_GET['new_email']);
                    $this->page_info['activation_code_sent'] = sprintf($this->lang['l_activation_code_sent'], urldecode($_GET['new_email']));
                }
                if (isset($_GET['email_changed'])) {
                    $this->page_info['changed_email'] = sprintf($this->lang['l_changed_email']);
                    break;
                }

                $change_email_code = $this->generatePassword();
                if (isset($_POST['activation_code']))
                    $change_email_code = addslashes($_POST['activation_code']);

                $change_email_label = 'change_email' . '_' . $this->user_id . '_' . $change_email_code;

                $tools = new CleanTalkTools();
                $form_token = $tools->get_form_token();
                $this->page_info['form_token'] = $form_token;
                
                if (count($_POST) && isset($_POST['password'])) {
                    $info = $this->safe_vars($_POST);

                    $errors = array();

                    // CSRF проверка

                    if (!isset($info['form_token']) || $info['form_token'] != $form_token) {
                        $message = $this->lang['l_security_breach'];
                        $errors[] = $message;
                        $this->post_log(strip_tags($message) . ' ' . __FILE__ . ' ' . __LINE__);
                    }

                    if (count($errors)){
                        $this->page_info['errors'] = &$errors;
                        $this->page_info['info'] = &$info;
                        $this->page_info['bsdesign'] = true;
                        $this->page_info['jsf_focus_on_field'] = $focus_on_field;
                        break;
                    }

                    if ($this->user_info['password'] !== md5(cfg::password_prefix . $info['password'])) {
                        $errors[] = $this->lang['l_wrong_password'];
                    }
                    if ($this->valid_email($info['new_email']) !== true) {
                        $errors[] = $this->lang['l_wrong_email'];
                    } else {
                        $email_check = $this->db->select(sprintf("select user_id from users where email = %s;", $this->stringToDB($info['new_email'])));
                        if (isset($email_check['user_id']))
                            $errors[] = $this->lang['l_email_exists'];
                    }
                    if (count($errors)){
                        $this->page_info['errors'] = &$errors;
                        $this->page_info['info'] = &$info;
                        sleep(cfg::fail_timeout);
                        break;
                    }
                    
                    // Если новый E-mail прошел проверки, то сохраняем его в кеше с секретным ключем
                    $this->mc->set($change_email_label, $info['new_email'], null, cfg::memcache_store_static_data);
                    $message = sprintf($this->lang['l_change_email_activation_code_body'], $change_email_code);
                    $this->send_email($info['new_email'], $this->lang['l_change_email_activation_code_title'], $message);

                    header(sprintf("Location:?activation_code_sent=1&new_email=%s", urlencode($info['new_email'])));
                }
                if (count($_POST) && isset($_POST['activation_code'])) {
                    $change_email_mc = $this->mc->get($change_email_label);
                    if ($change_email_mc === false) {
                        $this->page_info['errors'][] = $this->lang['l_wrong_activation_code'];
                        sleep(cfg::fail_timeout);
                        break;
                    }

                    $this->db->run(sprintf("update users set email = %s where user_id = %d;", $this->stringToDB($change_email_mc), $this->user_id));
                    $_SESSION['user_info']['email'] = $change_email_mc;
                    $this->post_log(sprintf(messages::changed_email_admin, $this->user_info['email'], $this->user_id, $change_email_mc));
                    
                    // Удаляем информацию об акаунте из кеша.
                    apc_delete($this->account_label);

                    header("Location:?email_changed=1");
                }
				break;
			default: break;
		}
		$this->display();
	}

    /**
      * Функция вывода профиля пользователя
      *
      * @return void
      */

	function show_profile(){
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'antispam/profile.html';
        $this->page_info['scripts'] = array('/my/js/crop/cropper.min.js','/my/js/avatar.js');
        $this->page_info['container_fluid'] = true;

        $this->get_addon('keep_history_45_days');

        require_once('GoogleAuthenticator.php');
        $ga = new PHPGangsta_GoogleAuthenticator();
        
        $tools = new CleanTalkTools();
        $form_token = $tools->get_form_token();
        $this->page_info['form_token'] = $form_token;
        
        $this->page_info['bsdesign'] = true;

   		if (isset($this->lang['l_profile_title']))
			$this->page_info['head']['title'] = $this->lang['l_profile_title'];
		
		$this->page_info['enable_token_auth_hint'] = sprintf($this->lang['l_enable_token_auth_hint']);

        $profile = $this->db->select(sprintf("select first_name, last_name, org, phone, js_test_enable, rss_enable, sms_enable, profile_notice, profile_updated, org_inn, org_ogrn, org_address, org_ceo, enable_token_auth, days_2_keep_requests, signature, google_auth_secret, country, avatar from users where user_id = %d;", $this->user_info['user_id']));
        if($profile['avatar']){
            $avatar_parts = explode('/', $profile['avatar']);
            if($avatar_parts && count($avatar_parts)){
                $profile['avatar_filename']=$avatar_parts[count($avatar_parts)-1];    
            }            
        }
        if (isset($_COOKIE['profile_updated']) && $_COOKIE['profile_updated'] == 1) {
            /*
            // Код стимулирования заполнения анкет, включу его спустя некоторое время после запуска анкеты
            //
            if ((isset($profile['first_name']) || isset($profile['last_name']) && isset($profile['phone']) && $profile['phone'] != '') {
                $this->page_info['show_promo'] = true;
                $promo = $this->db->select(sprintf("select promo_id, expire, discount from promo where promokey = '%s'", cfg::profile_promo_key));
                $this->send_email($this->user_info['email'], $this->lang['l_promo_message_title'], $message);
            }
            */
            setcookie('profile_updated', 0, null);
        }

        $show_notice = false;
        if (isset($_POST['need_info']))
            $show_notice = true;

        /*$tzs = $this->get_timezones();
        $this->page_info['tzs'] = &$tzs;*/
        $this->page_info['timezones_list'] = $this->timezones_generator();
        $this->page_info['ct_langs'] = &$this->ct_langs;
        
        $this->page_info['countries'] = $this->countries;

        $this->page_info['keep_history'] = sprintf($this->lang['l_keep_history'], $this->options['days_2_keep_requests_extended']);

        $this->page_info['keep_history_checked'] = $profile['days_2_keep_requests']==7?0:1;

        // Вывод QR кода если поставлена 2-х факторная авторизация
        $this->page_info['gais_admin'] = $this->is_admin;
        // только для команды пока
        if ($this->is_admin){
            if ($profile['google_auth_secret'] != ''){
                $this->page_info['hasga2f'] = 1;
                $this->page_info['ga_qrcode'] = $ga->getQRCodeGoogleUrl('cleantalk.org', $profile['google_auth_secret']);
                $this->page_info['ga_code'] = $profile['google_auth_secret'];
            }
            else {
                $this->page_info['hasga2f'] = 0;
                $this->page_info['ga_qrcode'] = '';
            }
        }

        if ($this->user_info['hoster_api_key']) {
            
            $hoster_api_key_a = '';
            for ($i = 1; $i <= strlen($this->user_info['hoster_api_key']); $i++) {
                $hoster_api_key_a .= '*';
            }
            $this->page_info['hoster_api_key_a'] = $hoster_api_key_a;

        }

        if (count($_POST)) {
            $info = $this->safe_vars($_POST);
            
            // Урезаем длинну строк до значений поддерживаемых базой
            $info['first_name'] = mb_substr($info['first_name'], 0, 64);
            $info['last_name'] = mb_substr($info['last_name'], 0, 64);
            $info['org'] = mb_substr($info['org'], 0, 1024);
//            $info['profile_notice'] = mb_substr($info['profile_notice'], 0, 4096);
            $info['profile_notice'] = null;
            $errors = array();

            if (isset($info['org_inn']) && $info['org_inn'] != '' && !preg_match("/^\d{1,12}$/", $info['org_inn'])) {
					$errors[] = $this->lang['l_wrong_inn'];
			}
			if (isset($info['org_ogrn']) && $info['org_ogrn'] != '' && !preg_match("/^\d{1,15}$/", $info['org_ogrn'])) {
					var_dump($info['org_ogrn']);
				$errors[] = $this->lang['l_wrong_ogrn'];
            }
            
            if (!isset($info['country']) || !preg_match('/^[A-Z]{2}$/', $info['country'])) {
                $info['country'] = $profile['country'];
            }
            
            // Приводим телефонный номер к стандартному формату
            $phone = preg_replace("/[\ \+\(\)]+/", "", $info['phone']);
      
            // Преобразуем значение переключателей в цифровое значение
            foreach ($profile as $k => $v){
                if (preg_match("/_enable$/", $k)) {
                    if (isset($info[$k]) && $info[$k] === "on")
                        $info[$k] = 1;
                    else
                        $info[$k] = 0;
                }
            }

            $focus_on_field = null;
            if (!$tools->test_input($info['phone'], '/^[0-9\ \- \(\)\+]+$/', null, 64)) {
                $errors[] = $this->lang['l_wrong_phone'];
				$focus_on_field = 'phone';
				echo 123;exit;
            }
            
            if ($phone != '' && false) {
                $row = $this->db->select(sprintf("select user_id from users where phone = %s;", $this->stringToDB($phone)));

                if (isset($row['user_id']) && $row['user_id'] != $this->user_info['user_id']) {
                    $errors[] = $this->lang['l_phone_exist'];
                    $focus_on_field = 'phone';
                }
                $info['phone'] = $this->phoneToHuman($phone);
            }
            
            if (!isset($info['form_token']) || $info['form_token'] != $form_token) {
                $message = $this->lang['l_security_breach'];
                $errors[] = $message;
                $this->post_log(strip_tags($message) . ' ' . __FILE__ . ' ' . __LINE__);
            }

            if (count($errors)){
                $this->page_info['errors'] = &$errors;
                $this->page_info['info'] = &$info;
				$this->page_info['jsf_focus_on_field'] = $focus_on_field;
                return false;
            }

            // Проверка 2-х факторной авторизации
            // Если поставлена галочка и не было раньше кода google_auth_secret в базе
            // только для команды пока
            if ($this->is_admin){
                if (isset($info['ga2f']) && $info['ga2f'] == 'on' && $info['hasga2f'] == 0){
                    $google_auth_secret = $ga->createSecret();
                    $this->db->run(sprintf("update users set google_auth_secret = %s where user_id = %d",
                                            $this->stringToDB($google_auth_secret),
                                            $this->user_info['user_id']));
                    // Устанавливаем куку чтобы код не спрашивало в течение текущей сессии
                    $useremailkey = md5('ga2f'.str_replace(array('.','@','_','-'),'',$_SESSION['user_info']['email']).'f2ag');
                    setcookie('gaath', $useremailkey, time() + 7 * 24 * 60 * 60, '/', $this->cookie_domain);
                }

                if (!isset($info['ga2f'])){
                    $this->db->run(sprintf("update users set google_auth_secret = NULL where user_id = %d",
                                            $this->user_info['user_id']));
                }
            }

            $this->db->run(sprintf("update users set first_name = %s, last_name = %s, org = %s, profile_notice = %s, 
                                        rss_enable = %d, sms_enable = %d,
                                        org_inn = %s,
                                        org_ogrn = %s,
                                        org_address = %s,
                                        org_ceo = %s,
                                        profile_updated = now(),
                                        days_2_keep_requests = %d,
                                        signature = %s,
                                        country = %s
                                        where user_id = %d;",
                                    $this->stringToDB($info['first_name']),
                                    $this->stringToDB($info['last_name']),
                                    $this->stringToDB($info['org']),
                                    $this->stringToDB($info['profile_notice']),
                                    $info['rss_enable'],
                                    $info['sms_enable'],
                                    $this->intToDB(isset($info['org_inn']) ? $info['org_inn'] : null),
                                    $this->intToDB(isset($info['org_ogrn']) ? $info['org_ogrn'] : null),
                                    $this->stringToDB($info['org_address']),
                                    $this->stringToDB($info['org_ceo']),
                                    $this->intToDB(isset($info['keep_history'])?($info['keep_history']=='on'?$this->options['days_2_keep_requests_extended']:7):7),
                                    $this->stringToDB($info['signature']),
                                    $this->stringToDB($info['country']),
                                    $this->user_info['user_id']
                                    ));
            
            // Обновляем телефонный номер только в случаи если введенный номер отличается от сохранненого в БД
            if (((!isset($profile['phone'])) || $phone != $profile['phone'])) {
                $update_phone = true;

                // Если телефонный номер заполнен, то делаем его верификацию через SMS код
                if ($phone != '' && $this->ct_lang == 'ru' && false) {
                    // Подключается к Memcache серверу
                    $this->memcache = new Memcache;
                    $this->memcache->addServer(cfg::memcache_host, cfg::memcache_port);
                    $stats = @$this->memcache->getExtendedStats();
                    $this->mc_online = (bool) ($stats[cfg::memcache_host . ":" . cfg::memcache_port] && @$this->memcache->connect(cfg::memcache_host, cfg::memcache_port));

                    $this->page_info['show_v_code'] = true;
                    $v_code_prefix = 'v_code:' . $phone;
                    $v_code = $this->memcache->get($v_code_prefix);

                    if ($v_code === false) { // Ранее код не был установлен
                        $update_phone = false;
                        
                        $v_code = $this->generateVCode(5);
                        $v_code_set = $this->memcache->set($v_code_prefix, $v_code, null, cfg::memcache_store_timeout_mid);

                        $sms_result = null;
                        if ($v_code_set !== false) {
                            $sms = 'Phone number verification code: ' . $v_code;
                            $sms_request = sprintf(cfg::sms_url, urlencode($sms), $phone, 'CleanTalk', cfg::sms_api_key);
                            $sms_result = file_get_contents($sms_request);
                        }

                        // Если запрос на отсылку SMS успешно выполнен, показываем поле ввода кода
                        if ($sms_result !== null && preg_match('/^SUCCESS=SMS SENT/', $sms_result)) {
                            $this->page_info['info'] = &$info;
                            $this->page_info['jsf_focus_on_field'] = 'v_code';
                            return false;

                        // Если запрос выполнить не удалось, то записываем событие в журнал, телефонный номер обновляем без проверки
                        } else {
                            $update_phone = true;
                            $this->page_info['show_v_code'] = false;
                            $this->memcache->delete($v_code_prefix);
                            $this->post_log(sprintf("Не удалось отправить SMS код подтверждения телефонного номера! Запрос %s, ответ %s", $sms_request, $sms_result));
                        }
                    } else {
                        if (isset($info['v_code']) && $v_code == $info['v_code']) {
                            $this->page_info['show_v_code'] = false;
                            $this->memcache->delete($v_code_prefix);
                        } else {
                            $update_phone = false;
                            $this->page_info['wrong_v_code'] = $this->lang['l_wrong_v_code'];
                            $this->page_info['info'] = &$info;
                            $this->page_info['jsf_focus_on_field'] = 'v_code';
                            return false;
                        }
                    }
                }
                if ($update_phone == true) {
                    $this->db->run(sprintf("update users set phone = %s, profile_updated = now() where user_id = %d;",
                                            $this->stringToDB($phone),
                                            $this->user_info['user_id']
									));
				}
            }
            $subscribe_week_report = isset($info['subscribe_week_report']) ? 1 : 0;
            $subscribe_news = isset($info['subscribe_news']) ? 1 : 0;
            $subscribe_account = isset($info['subscribe_account']) ? 1 : 0;
            $this->db->run(sprintf("update postman_tasks set enable = %d, enable_updated = now() where task_id = %d and user_id = %d and enable <> %d;",
                $subscribe_week_report,
                1,
                $this->user_id,
                $subscribe_week_report
            ));
            $this->db->run(sprintf("update postman_tasks set enable = %d, enable_updated = now() where task_id = %d and user_id = %d and enable <> %d;",
                $subscribe_news,
                3,
                $this->user_id,
                $subscribe_news
            ));
            $this->db->run(sprintf("update postman_tasks set enable = %d, enable_updated = now() where task_id = %d and user_id = %d and enable <> %d;",
                $subscribe_account,
                4,
                $this->user_id,
                $subscribe_account
            ));
            
            if (isset($info['timezone'])) {
                foreach ($this->page_info['timezones_list'] as $tz) {
                    if ($tz['value'] == $info['timezone']) {
                        $this->db->run(sprintf("update users_info set timezone = %s where user_id = %d;", $this->stringToDB($info['timezone']), $this->user_id));
                        break;
                    }
                }
            }

            if (isset($info['lang']) && isset($this->ct_langs[$info['lang']])) {
                $this->db->run(sprintf("update users_info set lang = %s where user_id = %d;", $this->stringToDB($info['lang']), $this->user_id));
                $this->db->run(sprintf("update services set response_lang = %s where user_id = %d;", $this->stringToDB($info['lang']), $this->user_id));
            }
            
            $enable_token_auth = (int)$info['enable_token_auth'];
            if ($enable_token_auth != $profile['enable_token_auth']) {
                $this->db->run(sprintf("update users set enable_token_auth = %d where user_id = %d;", $enable_token_auth, $this->user_id));
            }
            
            // Аватарка
            if(is_uploaded_file($_FILES['avatar']['tmp_name'])){
                $avatar_image = new ImageResize($_FILES['avatar']['tmp_name']);
                $avatar_image = $avatar_image->freecrop( // Делаем обрезку по отметкам пользователя
                    $info['crop-w'], 
                    $info['crop-h'], 
                    $info['crop-x'], 
                    $info['crop-y']
                );
                if(intval($info['crop-w'])>600 || intval($info['crop-h'])>600){ // Уменьшаем большие файлы
                    $avatar_image = ImageResize::createFromString($avatar_image->getImageAsString());
                    $avatar_image = $avatar_image->resizeToLongSide(600);
                }
                // загружаем на S3
                $s3key = $this->user_id.'_'.$_FILES['avatar']['name']; // Префик у имени файла user_id
                $s3 = new S3Storage('avatars/');
                $avatar_url = $s3->replace($avatar_image->getImageAsString(),$s3key); // Загружаем с заменой, если имя файла не изменилось

                // Сохраняем ссылку, если она изменилась
                if ($avatar_url != $profile['avatar']) {
                    if(!empty($profile['avatar'])){ // Удаляем старую аватарку с S3, если пользователь изменил имя файла
                        $s3->deleteByURL($profile['avatar']);
                    }
                    $this->db->run(sprintf("update users set avatar = %s where user_id = %d;", $this->stringToDB($avatar_url), $this->user_id));
                }

            }else{
                if(!empty($profile['avatar']) && empty($info['avatar_filename'])){ // Удаление аватарки
                    $s3 = new S3Storage('avatars/');
                    $s3->deleteByURL($profile['avatar']);
                    $this->db->run(sprintf("update users set avatar = NULL where user_id = %d;", $this->user_id));
                }
            }

            

            $this->post_log(sprintf("Обновлен профиль пользователя %s.", $this->user_info['email']));

            setcookie('profile_updated', 1, null);
            
            $get_options = '';
            if ($show_notice)
                $get_options = '?show_notice=1';
            
            // Удаляем информацию об акаунте из кеша.
            apc_delete($this->account_label);
            
            $this->url_redirect('profile' . $get_options);
        } else {
            $info = $profile;
//            $info['phone'] = $this->phoneToHuman($info['phone']);
            
            if ($info['profile_updated'] !== null)
                $this->page_info['profile_updated'] = sprintf($this->lang['l_profile_updated_time'], $info['profile_updated']);
            
            $task = $this->db->select(sprintf("select enable from postman_tasks where task_id = %d and user_id = %d;", 1, $this->user_id));
            if (isset($task['enable']))
                $info['subscribe_week_report'] = $task['enable'];
            else
                $info['subscribe_week_report'] = 0;
            
            $task = $this->db->select(sprintf("select enable from postman_tasks where task_id = %d and user_id = %d;", 3, $this->user_id));
            if (isset($task['enable']))
                $info['subscribe_news'] = $task['enable'];
            else
                $info['subscribe_news'] = 0;
            
            $task = $this->db->select(sprintf("select enable from postman_tasks where task_id = %d and user_id = %d;", 4, $this->user_id));
            if (isset($task['enable']))
                $info['subscribe_account'] = $task['enable'];
            else
                $info['subscribe_account'] = 0;
            
            $info['timezone'] = $this->user_info['timezone'];
            $info['lang'] = $this->user_info['lang'];
            
            $this->page_info['include_js_md5_lib'] = true;

            $this->page_info['info'] = &$info;
            $this->page_info['jsf_focus_on_field'] = 'first_name';
        }

		return true;
	}
    
    /**
      * Функция для перевода телефонного номера из формата базы в удобно читаемый вид
      *
      * @param string $phone Номер телефона
      *
      * @return string
      */

    function phoneToHuman($phone){
        return preg_replace("/(\d{1,3})(\d{3})(\d{3})(\d{2})(\d{2})$/", "$1 $2 $3 $4 $5", $phone);
    }
}


?>
