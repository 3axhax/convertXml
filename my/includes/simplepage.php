<?php

/**
* Класс для работы со страницами Simple
*
*/

class SimplePage extends CCS {

    /**
     * @var array $transfers_type Массив с описанием каналов вывода денег с партнерского счета
     */
    private $transfers_type = array('ct' => 'CleanTalk', 'ym' => 'Яндекс.Деньги');

    /**
     * @var resource $resp Экземпляр класс для работы с Яндекс.Деньги
     */
    private $resp = null;

    /**
      * Конструктор
      *
      * @return
      */

	function __construct(){
		parent::__construct();
		$this->ccs_init();
	}

    /**
      * Функция показа страницы
      *
      * @return void
      */

	function show_page($ClassNotFound) {
        if ($this->cp_product_id == cfg::product_ssl) {
            include cfg::includes_dir . 'ssl.php';
            $class = new Ssl($this);
            return $class->show_page();
        }
		$this->page_info['platforms'] = &$this->platforms;
		switch($this->link->id){
			// Страница авторизации
			case 5:
                if (isset($this->lang['l_need_auth_title']))
                    $this->page_info['head']['title'] = $this->lang['l_need_auth_title'];

                break;
			// Успешное завершение платежа
			case 8:
                if (!$this->check_access(null, true)) {
                    $this->url_redirect('session', null, true);
                }

                // Сохраняем информацию о только что проведенному платеже в куку, если этот флаг выставлен, то запрещаем повторную подписку
                if (!isset($_COOKIE['extended']))
                    setcookie('extended', 1, time() + 600);

                // После оплаты возвращаем пользователя к инструкции на устновку плагина
                if (isset($_COOKIE['return_url']) && preg_match("/^[a-z0-9\=\-\?\&\_\/]+$/i", $_COOKIE['return_url'])) {
                    setcookie('return_url', "", time() - 3600);
                    header("Location:" . $_COOKIE['return_url']);
                }

                $this->page_info['head']['title'] = $this->lang['l_pay_success_title'];

                if(!empty($_GET['bill_id']) || !empty($_GET['MNT_TRANSACTION_ID'])){
                    $get_bill_id = (!empty($_GET['bill_id'])) ? intval($_GET['bill_id']) : intval($_GET['MNT_TRANSACTION_ID']);
                    $sql = sprintf('SELECT bill_id, paid FROM bills WHERE bill_id = %d AND fk_user = %d', $get_bill_id, $this->user_id);
                    $row = $this->db->select($sql);
                    if(!empty($row) && $row['paid']==0){
                        $this->page_info['payment_processed'] = true;
                        $this->page_info['get_bill_id'] = $get_bill_id;
                    }
                }

                /*$language = $this->ct_lang;
                if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match("/^(\w{2})/", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches)) {
                    $language = $matches[1];
                }*/
                if ($this->ct_lang == 'ru') {
                    $language = 'ru';
                } else {
                    $language = 'en';
                }
                if ($this->cp_mode == 'security') {
                    $this->get_lang($language, 'Security');
                } else {
                    $this->get_lang($language, 'Antispam');
                }

                // Сообщение о бесплатных месяцах
                $bill = $this->db->select(sprintf("select bill_id, free_months from bills where fk_user = %d order by date desc limit 1;", $this->user_id));
                if (isset($bill['free_months']) && $bill['free_months'] > 0) {
                    $this->page_info['free_months'] = sprintf($this->lang['l_free_months'], $bill['free_months']);
                }

                // Логика выдачи сообщения об отзыве
                $has_wordpress = $this->db->select(sprintf("SELECT service_id FROM services WHERE engine = 'wordpress' AND product_id = %d", $this->cp_product_id));
                if ($language == 'ru' || $this->ct_lang == 'ru') $has_wordpress = false;
                if ($this->cp_mode == 'security' && $has_wordpress) {
                    $review_bonus = $this->db->select(sprintf("SELECT bonus_id FROM users_bonuses WHERE user_id = %d AND bonus_name = 'review'", $this->user_id));
                    if (!$review_bonus) {
                        $this->page_info['show_security_review_bonus'] = true;
                        $review_bonus = $this->db->select(sprintf("SELECT * FROM bonuses WHERE bonus_name = 'review'"));
                        if ($review_bonus) {
                        	$this->page_info['review_bonus_description'] = sprintf($this->lang['l_need_review_no_bonus2'], 'wordpress.org');
                            $this->page_info['review_bonus_button'] = $this->lang['l_bonuses_bonus_button_review'];
                            $this->page_info['review_bonus_link'] = $this->lang['l_bonuses_bonus_link_review'];
                            if (isset($this->lang['l_bonuses_bonus_notice_review'])) $this->page_info['review_bonus_notice'] = $this->lang['l_bonuses_bonus_notice_review'];
                        }
                    }
                } else if ($this->user_info['trial'] != -1 && $has_wordpress) {
                    $this->show_review();
                    if ($this->page_info['show_review'] && $has_wordpress) {
                        unset($this->page_info['show_review']);
                        $this->page_info['show_security_review_bonus'] = true;
                        $review_bonus = $this->db->select(sprintf("SELECT * FROM bonuses WHERE bonus_name = 'review'"));
                        if ($review_bonus) {
                            if ($language == 'ru') {
                                $this->page_info['review_bonus_title'] = sprintf($this->lang['l_bonuses_bonus_title'],
                                    $review_bonus['free_months'],
                                    $this->number_lng($review_bonus['free_months'], array('месяц', 'месяца', 'месяцев'))
                                );
                            } else {
                                $this->page_info['review_bonus_title'] = sprintf($this->lang['l_bonuses_bonus_title'],
                                    $review_bonus['free_months'],
                                    $this->number_lng($review_bonus['free_months'], array('month', 'months'))
                                );
                            }
                            $this->page_info['review_bonus_description'] = $this->lang['l_bonuses_bonus_description_review'];
                            $this->page_info['review_bonus_button'] = $this->lang['l_bonuses_bonus_button_review'];
                            $this->page_info['review_bonus_link'] = $this->page_info['review_link'];
                            $this->page_info['review_bonus_notice'] = $this->lang['l_bonuses_bonus_notice_review'];
                            /*if (true || isset($this->page_info['no_bonus']) && $this->page_info['no_bonus']) {
                                $this->page_info['review_bonus_notice'] = $this->lang['l_bonuses_bonus_notice_review'];
                            }
                            if (!isset($this->page_info['bonuses']['review']['title'])) {
                                $this->page_info['review_bonus_title'] = $this->lang['l_help_other_know'];
                            }*/
                        }
                    }
                }

                // Автоматический редирект на /spambots-check
                if (
                    isset($_COOKIE['payment_redirect']) &&
                    $_COOKIE['payment_redirect'] == 'database_api:spambots_check' &&
                    $this->cp_mode == 'api'
                ) {
                    setcookie('payment_redirect', 'database_api:spambots_check', time(), '/', $this->cookie_domain);
                    $this->page_info['header']['refresh'] = '10;url=/spambots-check';
                    $this->page_info['payment_redirect'] = $this->lang['l_spambots_check_redirect'];
                }

                $this->page_info['bsdesign'] = true;
                $this->show_setup_hint(false);

                break;
			// Баннеры
			case 10:
				$this->show_banners();
				break;
			// Почтовые рассылки
			case 14:
                if (!$this->check_access())
                        $this->url_redirect('session', null, true);
				$this->show_postman();
				break;
			// Партнерская программа
			case 18:
                if (!$this->check_access())
                        $this->url_redirect('session', null, true);
				$this->show_partners();
				break;

            // Переход на новый тариф
			case 20:
                if (!$this->check_access())
                        $this->url_redirect('session', null, true);
                $this->get_tariffs2();
				break;
            // Настройки сервиса
			case 21:
                $this->page_info['bsdesign'] = true;
                $this->page_info['head']['title'] = $this->lang['l_new_site'];

                if (!$this->check_access()) {
                    $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
                }

                $this->check_authorize();

                switch ($this->cp_mode) {
                    case 'security':
                        $this->show_security_service_edit();
                        break;
                    case 'ssl':
                        $this->show_ssl_service_edit();
                        break;
                    default:
                        $this->show_service_edit();
                        break;
                }
				break;
            // Ознакомительный срок закончился
			case 29:
                $this->fill_pay_method_params();
                $this->page_info['head']['title'] = $this->lang['l_trial_expired'];
                $this->page_info['pay_button'] = $this->lang['l_renew_1year'];
                $this->page_info['bsdesign'] = true;
   				break;
            // Бонусы, /my/bonuses
			case 30:
                $this->page_info['bsdesign'] = true;

                // Обработка бонусов при наличии лицензии antispam
                if (isset($_POST['action']) && in_array($_POST['action'], array('linkedin', 'bns')) && isset($this->user_info['licenses']['antispam'])) {
                    $message = '';
                    $bonus_name = $_POST['action'] == 'linkedin' ? 'linkedin' : 'facebook';
                    $hasBonus = $this->db->select(sprintf(
                        "SELECT bonus_name FROM users_bonuses WHERE bonus_name = %s AND license_id = %d AND user_id = %d",
                        $this->stringToDB($bonus_name),
                        $this->user_info['licenses']['antispam']['id'],
                        $this->user_info['user_id']
                    ));
                    if ($hasBonus) {
                        $message = ($this->ct_lang == 'en') ?
                            'This kind of bonus was already applied to your account.' :
                            'Этот бонус уже был Вам начислен.';
                    } else if ($this->user_info['licenses']['antispam']['trial']) {
                        $message = ($this->ct_lang == 'en') ?
                            'This kind of bonus not for trial users.' :
                            'Этот бонус не начисляется аккаунтам на ознакомительном сроке.';
                    } else {
                        $bonus = $this->db->select(sprintf("SELECT free_months FROM bonuses WHERE bonus_name = %s", $this->stringToDB($bonus_name)));
                        $valid_till = strtotime($this->user_info['licenses']['antispam']['valid_till']);
                        $paid_till = strtotime($this->user_info['licenses']['antispam']['paid_till']);
                        if ($paid_till > $valid_till) {
                            $valid_till = $paid_till;
                            $this->db->run(sprintf(
                                "UPDATE users_licenses SET valid_till = %s WHERE id = %d",
                                $this->stringToDB(date('Y-m-d', $valid_till)), $this->user_info['licenses']['antispam']['id']
                            ));
                        }
                        if( $valid_till < time() ){
                            $valid_till = time();
                        }
                        $valid_till = date('Y-m-d', strtotime(sprintf("+%d month", $bonus['free_months']), $valid_till));

                        if ($bonus_name == 'linkedin') {
                            $review_url = preg_replace('/[\']/i', '', $_POST['linkedin_link']);
                            $log_message = sprintf(
                                "Добавлен бонус %d месяц за шаринг LinkedIn (подписка до %s) пользователю %s (%d).",
                                $bonus['free_months'], $valid_till, $this->user_info['email'], $this->user_info['user_id']
                            );
                        } else {
                            $review_url = 'https://cleantalk.org/' . preg_replace('/[^0-9a-zA-Z_-]/i', '', $_POST['fb_seo_url']);
                            $log_message = sprintf(
                                "Добавлен бонус %d месяц за лайк Facebook (подписка до %s) пользователю %s (%d).",
                                $bonus['free_months'], $valid_till, $this->user_info['email'], $this->user_info['user_id']
                            );
                        }

                        $this->db->run(sprintf(
                            "INSERT INTO users_bonuses (bonus_name, user_id, free_months, review_url, activated, paid_till, license_id) VALUES (%s, %d, %d, %s, now(), %s, %d)",
                            $this->stringToDB($bonus_name), $this->user_info['user_id'], $bonus['free_months'],
                            $this->stringToDB($review_url), $this->stringToDB($valid_till), $this->user_info['licenses']['antispam']['id']
                        ));

                        $this->db->run(sprintf("UPDATE users_licenses SET valid_till = %s WHERE id = %d", $this->stringToDB($valid_till), $this->user_info['licenses']['antispam']['id']));

                        $this->db->run(sprintf(
                            "UPDATE users_bonuses SET paid_till = %s WHERE user_id = %d",
                            $this->stringToDB($valid_till),
                            $this->user_info['user_id']
                        ));

                        $this->post_log($log_message);
                        /*$this->post_log(sprintf(
                            "Пользователю %s (%d) продлена лицензия %d до %s",
                            $this->user_info['email'], $this->user_info['user_id'],
                            $this->user_info['licenses']['antispam']['id'],
                            $valid_till
                        ));*/

                        $message = ($this->ct_lang == 'en') ?
                            '+' . $bonus['free_months'] . ' month - subscription valid till ' . date('d.m.Y', strtotime($valid_till)) :
                            '+' . $bonus['free_months'] . ' месяц - подписка продлена до ' . date('d.m.Y', strtotime($valid_till));
                        $message .= '<br><a href="/my/bonuses">https://cleantalk.org/my/bonuses';

                        // Включаем лицензию, если valid_till > time() и moderate=0
                        if (!$this->user_info['licenses']['antispam']['moderate'] && strtotime($valid_till) > time()) {
                            $this->db->run(sprintf("UPDATE users_licenses SET moderate=1 WHERE id=%d", $this->user_info['licenses']['antispam']['id']));
                            $this->db->run(sprintf(
                                "UPDATE services SET moderate_service=1 WHERE product_id=%d AND user_id=%d",
                                cfg::product_antispam, $this->user_info['user_id']
                            ));
                        }
                    }

                    echo($message);
                    exit;
                }

                // Для действий загружаем лицензии, если они есть у пользователя
                // Если !isset($licenses) - лицензий нет
                // Еслии $allTrials - триал
                if (isset($_POST['action'])) {
                    $allTrials = $this->user_info['trial'] ? true : false;
                    $rows = $this->db->select(sprintf("SELECT * FROM users_licenses WHERE user_id = %d AND moderate = 1", $this->user_info['user_id']), true);
                    if (count($rows)) {
                        $allTrials = true;
                        foreach ($rows as $license) {
                            if ($license['trial'] == '0') $allTrials = false;
                        }
                        if (!$allTrials) {
                            $licenses = array();
                            foreach ($rows as $license) {
                                if (!$license['trial']) $licenses[] = $license;
                            }
                        }
                    }
                }

                // Бонус за шаринг в LinkedIn
                if (isset($_POST['action']) && isset($_POST['linkedin_link']) && ($_POST['action'] == 'linkedin')) {
                    $hasInBonus = $this->db->select(sprintf('select bonus_name from users_bonuses where bonus_name = %s and user_id = %d', $this->stringToDB('linkedin'), $this->user_info['user_id']));
                    if (!$hasInBonus && !$allTrials) {
                        $paid_till = $this->db->select(sprintf("select paid_till from users where user_id = %d", $this->user_info['user_id']));
                        $base_months = $this->db->select("select free_months from bonuses where bonus_name='linkedin'");
                        $base_months_val = (int) $base_months['free_months'];

                        $paid_till_new = date("Y-m-d", strtotime(sprintf("+%d month", $base_months_val), strtotime($paid_till['paid_till'])));

                        if (isset($licenses)) {
                            foreach ($licenses as $license) {
                                if ($license['id'] == cfg::product_antispam) {
                                    $valid_till = date("Y-m-d", strtotime(sprintf("+%d month", $base_months_val), strtotime($license['valid_till'])));
                                    $paid_till_new = $valid_till;
                                    $this->db->run(sprintf("UPDATE users_licenses SET valid_till = %s WHERE id = %d", $this->stringToDB($valid_till), $license['id']));
                                }
                            }
                        }

                        $this->db->run(sprintf("insert into users_bonuses (bonus_name, user_id, free_months, review_url, activated, paid_till) values (%s, %d, %d, %s, now(), %s);",
                            $this->stringToDB('linkedin'),
                            $this->user_info['user_id'],
                            $base_months_val,
                            $this->stringToDB($_POST['linkedin_link']),
                            $this->stringToDB($paid_till_new)
                        ));

                        $this->db->run(sprintf("update users set paid_till = %s, show_review = 0 where user_id = %d;",
                            $this->stringToDB($paid_till_new),
                            $this->user_info['user_id']
                        ));

                        $this->post_log(sprintf("Добавлен бонус %d месяц за шаринг LinkedIn (подписка до %s) пользователю %s (%d).",
                            $base_months_val,
                            $paid_till_new,
                            $this->user_info['email'],
                            $this->user_info['user_id']
                        ));

                        if ($this->ct_lang == 'en')
                            $echomessage =  '+'.$base_months_val.' month - subscription valid till '.date('d.m.Y',strtotime($paid_till_new));
                        else
                            $echomessage =  '+'.$base_months_val.' месяц - подписка продлена до '.date('d.m.Y',strtotime($paid_till_new));

                        $echomessage .= '<br><a href="/my/bonuses">https://cleantalk.org/my/bonuses';
                    } elseif ($allTrials) {
                        if ($this->ct_lang == 'en')
                            $echomessage =  'This kind of bonus not for trial users.';
                        else
                            $echomessage =  'Этот бонус не начисляется аккаунтам на ознакомительном сроке.';
                    } else {
                        if ($this->ct_lang == 'en')
                            $echomessage =  'This kind of bonus was already applied to your account.';
                        else
                            $echomessage =  'Этот бонус уже был Вам начислен.';
                    }

                    echo $echomessage;
                    exit();
                }

                // Бонус за лайк Facebook / шаринг LinkedIn
                if (isset($_POST['action']) && ($_POST['action'] == 'bns')) {
                    // Проверяем был ли уже начислен такой бонус
                    $hasfbbonus = $this->db->select(sprintf('select bonus_name 
                                                             from users_bonuses
                                                             where bonus_name = %s
                                                             and user_id = %d',
                                                             $this->stringToDB('facebook'),
                                                             $this->user_info['user_id']));

                    if ($hasfbbonus['bonus_name'] != 'facebook' && !$allTrials) {

                        $paid_till = $this->db->select(sprintf("select paid_till from users 
                                                                where user_id = %d;",$this->user_info['user_id']));

                        $base_months = $this->db->select("select free_months from bonuses
                                                                where bonus_name='facebook'");

                        $base_months_val = (int) $base_months['free_months'];

                        $paid_till_new = date("Y-m-d", strtotime(sprintf("+%d month", $base_months_val), strtotime($paid_till['paid_till'])));

                        if (isset($licenses)) {
                            foreach ($licenses as $license) {
                                if ($license['id'] == cfg::product_antispam) {
                                    $valid_till = date("Y-m-d", strtotime(sprintf("+%d month", $base_months_val), strtotime($license['valid_till'])));
                                    $paid_till_new = $valid_till;
                                    $this->db->run(sprintf("UPDATE users_licenses SET valid_till = %s WHERE id = %d", $this->stringToDB($valid_till), $license['id']));
                                }
                            }
                        }

                        // Страница на которой поставлен Like

                        $fbpage = 'https://cleantalk.org/'.preg_replace('/[^0-9a-zA-Z_-]/i', '', $_POST['fb_seo_url']);

                        $this->db->run(sprintf("insert into users_bonuses (bonus_name, user_id, free_months, review_url, activated, paid_till) values (%s, %d, %d, %s, now(), %s);",
                                                $this->stringToDB('facebook'),
                                                $this->user_info['user_id'],
                                                $base_months_val,
                                                $this->stringToDB($fbpage),
                                                $this->stringToDB($paid_till_new)
                                    ));

                        $this->db->run(sprintf("update users set paid_till = %s, show_review = 0 where user_id = %d;",
                                                $this->stringToDB($paid_till_new),
                                                $this->user_info['user_id']
                                      ));

                        $this->post_log(sprintf("Добавлен бонус %d месяц за лайк Facebook (подписка до %s) пользователю %s (%d).",
                                                $base_months_val,
                                                $paid_till_new,
                                                $this->user_info['email'],
                                                $this->user_info['user_id']
                                        ));
                        if ($this->ct_lang == 'en')
                            $echomessage =  '+'.$base_months_val.' month - subscription valid till '.date('d.m.Y',strtotime($paid_till_new));
                        else
                            $echomessage =  '+'.$base_months_val.' месяц - подписка продлена до '.date('d.m.Y',strtotime($paid_till_new));
                        $echomessage.= '<br><a href="/my/bonuses">https://cleantalk.org/my/bonuses';
                    }
                    elseif ($allTrials) {
                        if ($this->ct_lang == 'en')
                            $echomessage =  'This kind of bonus not for trial users.';
                        else
                            $echomessage =  'Этот бонус не начисляется аккаунтам на ознакомительном сроке.';
                    }
                    else{
                        if ($this->ct_lang == 'en')
                            $echomessage =  'This kind of bonus was already applied to your account.';
                        else
                            $echomessage =  'Этот бонус уже был Вам начислен.';
                    }
                    echo $echomessage;
                    exit();
                }

//                $this->check_trial();

                if (!$this->check_access(null, true)) {
                    $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
                }

                if ($this->cp_mode == 'security') {
                    $this->security_bonuses();
                } else {
                    $this->page_info['head']['title'] = $this->lang['l_bonuses_title'];

                    $this->get_bonuses_conditions();
                    $sql = sprintf("select bonus_id,bonus_name,user_id,activated,free_months,paid_till from users_bonuses where user_id = %d order by activated desc;",
                        $this->user_id
                    );
                    $bonuses = $this->db->select($sql, true);
                    foreach ($bonuses as $k => $v) {
                        $bonus_name_label = 'l_' . $v['bonus_name'] . '_label';
                        $v['bonus_name_display'] = $v['bonus_name'];
                        if (isset($this->lang[$bonus_name_label])) {
                            $v['bonus_name_display'] = $this->lang[$bonus_name_label];
                        }
                        $v['activated_display'] = date("M d Y", strtotime($v['activated']));
                        $v['paid_till_display'] = date("M d Y", strtotime($v['paid_till']));
                        $bonuses[$k] = $v;
                    }

                    $this->page_info['bonuses_activated'] = $bonuses;

                    // Количество бонусных дней из таблицы users_feedback_days

                    $ufbdays = $this->db->select(sprintf("select count(*) as bonus_days 
                                                      from users_feedback_days
                                                      where user_id = %d", $this->user_id));
                    $this->page_info['maxufbdays'] = $this->options['max_marks_to_get_bonus'];
                    $this->page_info['ufbdayspercent'] = floor(($ufbdays['bonus_days']/$this->options['max_marks_to_get_bonus'])*100);
                    $this->page_info['ufbdaystext'] = sprintf($this->lang['l_ufbdays'], $ufbdays['bonus_days']);
                    $this->page_info['l_ufbdays_hint'] = sprintf($this->lang['l_ufbdays_hintrough'],
                        $this->options['max_marks_to_get_bonus'],
                        $this->options['free_months_for_marks']);
                }

//                var_dump($bonuses);
   				break;
            // Делегирование
            case 37:
                if (!$this->check_access(null, true)) {
                    $this->url_redirect('session', null, true);
                }
                $this->check_authorize();
                $this->smarty_template = 'includes/general.html';
                $this->page_info['template']  = 'grants/index.html';
                $this->page_info['container_fluid'] = true;

                $this->get_addon('grants_addon');
                $tools = new CleanTalkTools();
                $form_token = $tools->get_form_token();
                $this->page_info['form_token'] = $form_token;
                if (isset($_GET['action'])) {
                    switch($_GET['action']){
                        case 'new' : {
                            // Сохранение разрешения
                            if (count($_POST)) {
                                $account_email = $_POST['account'];
                                $writesign = ($_POST['type'] == 2 ? 1 : 0);
                                $service_id = preg_replace('/[^0-9]/i', '', $_POST['service_id']);
                                $errors = array();

                                // CSRF проверка

                                if (!isset($_POST['form_token']) || $_POST['form_token'] != $form_token) {
                                    $message = $this->lang['l_security_breach'];
                                    $errors[] = $message;
                                    $this->post_log(strip_tags($message) . ' ' . __FILE__ . ' ' . __LINE__);
                                }

                                // Проверка валидности введенного email
                                if (!filter_var($account_email, FILTER_VALIDATE_EMAIL) || count($errors)) {
                                    setcookie('grant_email', $account_email, time() + 24*60*60, '/', $this->cookie_domain);
                                    setcookie('writesign', $writesign, time() + 24*60*60, '/', $this->cookie_domain);
                                    setcookie('grant_sid', $service_id, time() + 24*60*60, '/', $this->cookie_domain);
                                    if (count($errors))
                                        $this->set_message_cookie('wrong_message', $this->lang['l_security_breach_grant'], $_SERVER['HTTP_REFERER']);
                                    else
                                        $this->set_message_cookie('wrong_message', $this->lang['l_wrong_email'], $_SERVER['HTTP_REFERER']);
                                }

                                // Проверка есть ли в наличии пользователь которому
                                // предоставляется доступ
                                $existuser = $this->db->select(sprintf("select user_id, email
                                                                        from users 
                                                                        where email = %s",
                                                                        $this->stringToDB($account_email)));
                                if ($existuser['email'] == $account_email) {
                                    // Проверка что предлагаемый service_id действительно принадлежит текущему пользователю
                                    if (!$this->check_service_user($service_id, $this->user_id))
                                        $this->set_message_cookie('wrong_message', $this->lang['l_cant_save'],$_SERVER['HTTP_REFERER']);

                                    $user_id_granted = $existuser['user_id'];

                                    // Удаляем из apc список делегированных пользователю сайтов
                                    // используется далее в requests.php
                                    apc_delete($user_id_granted.'_granted_services');

                                    $grant_add_sql = sprintf('insert into services_grants
                                                    (grant_id, service_id, user_id, user_id_granted, granted, updated, grantread, grantwrite)
                                                    values 
                                                    (NULL, %d, %d, %d, %s, %s, %d, %d)',
                                                    $service_id,
                                                    $this->user_id,
                                                    $user_id_granted,
                                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                                    1,
                                                    $writesign);

                                    if ($this->db->run($grant_add_sql)){
                                        $permission = ($writesign == 1 ? 'Чтение и Запись' : 'Чтение');
                                        $this->post_log(sprintf("Пользователь %s (%d) дал разрешение %s на сайт %s (%d) пользователю %s (%d).",
                                                                $this->user_info['email'],
                                                                $this->user_id,
                                                                $permission,
                                                                $this->get_service_hostname($service_id),
                                                                $service_id,
                                                                $existuser['email'],
                                                                $user_id_granted
                                                                ));
                                        $this->set_message_cookie('success_message', sprintf($this->lang['l_grants_success'], $account_email),$_SERVER['HTTP_REFERER']);
                                    } else {
                                        setcookie('grant_email', $account_email, time() + 24*60*60, '/', $this->cookie_domain);
                                        setcookie('writesign', $writesign, time() + 24*60*60, '/', $this->cookie_domain);
                                        setcookie('grant_sid', $service_id, time() + 24*60*60, '/', $this->cookie_domain);
                                        $this->set_message_cookie('wrong_message', $this->lang['l_cant_save'],$_SERVER['HTTP_REFERER']);
                                    }

                                } else {
                                    setcookie('grant_email', $account_email, time() + 24*60*60, '/', $this->cookie_domain);
                                    setcookie('writesign', $writesign, time() + 24*60*60, '/', $this->cookie_domain);
                                    setcookie('grant_sid', $service_id, time() + 24*60*60, '/', $this->cookie_domain);
                                    $this->set_message_cookie('wrong_message', $this->lang['l_wrong_user'],$_SERVER['HTTP_REFERER']);
                                }
                            }

                            $this->page_info['template'] = 'grants/add.html';
                            $this->page_info['head']['title'] = $this->lang['l_grants_new'];
                            $this->page_info['container_fluid'] = false;
                            $services = $this->db->select(sprintf("select service_id, hostname
                                                                    from services
                                                                    where user_id = %d
                                                                    order by service_id asc",
                                                                    $this->user_id), true);
                            $this->page_info['services'] = $services;
                            $this->display_message_cookie('wrong_message');
                            $this->display_message_cookie('success_message');
                            $this->display_message_cookie('grant_email');
                            $this->display_message_cookie('grant_sid');
                            $this->display_message_cookie('writesign');
                            break;
                        }
                        case 'edit' : {
                            // Редактирование разрешения
                            if (count($_POST)) {

                                // Проверка валидности введенного email
                                if (!filter_var($_POST['account'], FILTER_VALIDATE_EMAIL))
                                    $this->set_message_cookie('wrong_message', $this->lang['l_wrong_email'], $_SERVER['HTTP_REFERER']);

                                $account_email = $_POST['account'];

                                $service_id = preg_replace('/[^0-9]/i', '', $_POST['service_id']);

                                $writesign = ($_POST['type'] == 2 ? 1 : 0);

                                $errors = array();

                                // CSRF проверка

                                if (!isset($_POST['form_token']) || $_POST['form_token'] != $form_token) {
                                    $message = $this->lang['l_security_breach'];
                                    $errors[] = $message;
                                    $this->post_log(strip_tags($message) . ' ' . __FILE__ . ' ' . __LINE__);
                                }

                                if (count($errors)){
                                    setcookie('grant_email', $account_email, time() + 24*60*60, '/', $this->cookie_domain);
                                    setcookie('writesign', $writesign, time() + 24*60*60, '/', $this->cookie_domain);
                                    setcookie('grant_sid', $service_id, time() + 24*60*60, '/', $this->cookie_domain);
                                    $this->set_message_cookie('wrong_message', $this->lang['l_security_breach_grant'], $_SERVER['HTTP_REFERER']);
                                }

                                // Проверка есть ли в наличии пользователь которому
                                // предоставляется доступ
                                $existuser = $this->db->select(sprintf("select user_id, email
                                                                        from users 
                                                                        where email = %s",
                                                                        $this->stringToDB($account_email)));
                                if ($existuser['email'] == $account_email) {

                                     // Проверка что предлагаемый service_id действительно принадлежит текущему пользователю

                                    if (!$this->check_service_user($service_id, $this->user_id))
                                        $this->set_message_cookie('wrong_message', $this->lang['l_cant_save'],$_SERVER['HTTP_REFERER']);

                                    $grant_id = preg_replace('/[^0-9]/i', '', $_GET['grant_id']);

                                    $user_id_granted = $existuser['user_id'];

                                    apc_delete($user_id_granted.'_granted_services');

                                    $readsign = 1;

                                    $grant_edit_sql = sprintf('update services_grants
                                                              set service_id = %d, user_id_granted = %d,
                                                              updated = %s, grantread = %d, grantwrite = %d
                                                              where grant_id = %d and user_id = %d',
                                                    $service_id,
                                                    $user_id_granted,
                                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                                    $readsign,
                                                    $writesign,
                                                    $grant_id,
                                                    $this->user_id);

                                    if ($this->db->run($grant_edit_sql)){
                                        $permission = ($writesign == 1 ? 'Чтение и Запись' : 'Чтение');
                                        $this->post_log(sprintf("Пользователь %s (%d) отредактировал разрешение %s на сайт %s (%d) пользователю %s (%d).",
                                                                $this->user_info['email'],
                                                                $this->user_id,
                                                                $permission,
                                                                $this->get_service_hostname($service_id),
                                                                $service_id,
                                                                $existuser['email'],
                                                                $user_id_granted
                                                                ));
                                        $this->set_message_cookie('success_message', sprintf($this->lang['l_grants_success'], $account_email),$_SERVER['HTTP_REFERER']);
                                    }
                                    else
                                        $this->set_message_cookie('wrong_message', $this->lang['l_cant_save'],$_SERVER['HTTP_REFERER']);

                                }
                                else
                                    $this->set_message_cookie('wrong_message', $this->lang['l_wrong_user'],$_SERVER['HTTP_REFERER']);
                            }
                            $grant_id = preg_replace('/[^0-9]/i', '', $_GET['grant_id']);
                            $grant_edit_sql = sprintf("select a.grant_id, a.service_id, a.grantread,
                                                              a.grantwrite, b.email
                                                       from services_grants a 
                                                       join users b 
                                                       on a.user_id_granted = b.user_id
                                                       where a.grant_id = %d
                                                       and a.user_id = %d",
                                                       $grant_id,
                                                       $this->user_id);

                            $grant_edit = $this->db->select($grant_edit_sql);
                            $this->page_info['template'] = 'grants/edit.html';
                            $this->page_info['container_fluid'] = false;
                            $this->page_info['grant'] = $grant_edit;
                            $this->page_info['head']['title'] = $this->lang['l_grants_title'];
                            $services = $this->db->select(sprintf("select service_id, hostname
                                                                    from services
                                                                    where user_id = %d
                                                                    order by service_id asc",
                                                                    $this->user_id), true);
                            $this->page_info['services'] = $services;
                            $this->display_message_cookie('wrong_message');
                            $this->display_message_cookie('success_message');
                            break;

                        }
                        case 'delete' : {
                            $grant_id = preg_replace('/[^0-9]/i', '', $_GET['grant_id']);
                            // Удаляем из apc список сайтов которые были делегированы пользователю
                            $user_apc = $this->db->select(sprintf("select a.service_id, a.user_id_granted, 
                                                                          a.grantwrite, b.email
                                                                          from services_grants a
                                                                          join users b 
                                                                          on a.user_id_granted = b.user_id   
                                                                          where a.grant_id = %d",
                                                                          $grant_id));
                            apc_delete($user_apc['user_id_granted'].'_granted_services');
                            $grant_delete_sql = sprintf("delete from services_grants 
                                                         where grant_id = %d
                                                         and user_id = %d",
                                                         $grant_id,
                                                         $this->user_id);
                            if ($this->db->run($grant_delete_sql)) {
                                $permission = ($user_apc['grantwrite'] == 1 ? 'Чтение и Запись' : 'Чтение');
                                $this->post_log(sprintf("Пользователь %s (%d) удалил разрешение %s на сайт %s (%d) для пользователя %s (%d).",
                                                         $this->user_info['email'],
                                                         $this->user_id,
                                                         $permission,
                                                         $this->get_service_hostname($user_apc['service_id']),
                                                         $user_apc['service_id'],
                                                         $user_apc['email'],
                                                         $user_apc['user_id_granted']
                                                    ));
                                $this->set_message_cookie('success_message', sprintf($this->lang['l_grants_delete_success'], $account_email),$_SERVER['HTTP_REFERER']);
                            }
                            else
                                $this->set_message_cookie('wrong_message', $this->lang['l_cant_delete'],$_SERVER['HTTP_REFERER']);
                            break;

                        }
                        case 'writeoff' : {
                            $grant_id = preg_replace('/[^0-9]/i', '', $_POST['grant_id']);
                            $grant_writeoff_sql = sprintf("update services_grants
                                                           set grantwrite = 0, updated = %s  
                                                           where grant_id = %d
                                                           and user_id = %d",
                                                           $this->stringToDB(date('Y-m-d H:i:s')),
                                                           $grant_id,
                                                           $this->user_id);
                            $grant_woff = $this->db->select(sprintf("select a.service_id, a.user_id_granted, b.email
                                                                            from services_grants a join users b
                                                                            on a.user_id_granted = b.user_id
                                                                            where a.grant_id = %d",
                                                                            $grant_id));
                            apc_delete($grant_woff['user_id_granted'].'_granted_services');
                            $woffresponse = array();
                            if ($this->db->run($grant_writeoff_sql)){
                                $this->post_log(sprintf("Пользователь %s (%d) отозвал разрешение Чтение и Запись на сайт %s (%d) для пользователя %s (%d).",
                                                         $this->user_info['email'],
                                                         $this->user_id,
                                                         $this->get_service_hostname($grant_woff['service_id']),
                                                         $grant_woff['service_id'],
                                                         $grant_woff['email'],
                                                         $grant_woff['user_id_granted']
                                                    ));
                                $woffresponse[] = 1;
                                $woffresponse[] = $this->lang['l_grants_writeoff_success'];
                                $woffresponse[] = $this->lang['l_grants_read'];
                            }
                            else{
                                $woffresponse[] = 0;
                                $woffresponse[] = $this->lang['l_grants_writeoff_fail'];
                            }
                            echo json_encode($woffresponse);
                            exit();
                        }
                        default: break;
                    }
                }
                else {
                    $this->page_info['head']['title'] = $this->lang['l_grants_title'];
                    $grants_sql = sprintf("select b.hostname, b.service_id, date_format(a.granted, '%%d.%%m.%%Y %%H:%%i:%%s') as grantdate,
                                                  a.grant_id, a.grantread, a.grantwrite,
                                                  c.email
                                                  from services_grants a join services b 
                                                  on a.service_id = b.service_id
                                                  join users c
                                                  on a.user_id_granted = c.user_id
                                                  where a.user_id = %d",
                                                  $this->user_id);
                    $grants = $this->db->select($grants_sql, true);
                    $this->page_info['grants'] = $grants;
                    $this->display_message_cookie('wrong_message');
                    $this->display_message_cookie('success_message');
                }
            // Уведомление о спам событиях на сайте
            /*case 62:
                include 'inc_notify.php';
                break;*/
            default: break;
		}

        if ($ClassNotFound) {
		    $this->page_info['template'] = 'messages/pagenotfound.html';
			$template = './templates/' . $this->page_url . '.html';
            if (file_exists($template)) {
                $this->get_lang($this->ct_lang, 'SimplePage');
				$this->page_info['template'] = $template;
				$this->page_info['allow_token_auth_by_sites'] = false; 
				if ($this->allow_token_auth_by_sites($this->user_id)) {
					$this->page_info['allow_token_auth_by_sites'] = true;
				}
            }
        }
		$this->smarty->assign($this->page_info);
		$this->display();
	}

	function security_bonuses() {
        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/bonuses.html';

        $this->page_info['head']['title'] = $this->lang['l_bonuses_head_title'];
        $this->page_info['container_fluid'] = true;

        $bonuses_ids = array('early_pay');
        $bonuses = array();
        $progress = array('max' => 0, 'current' => 0);

        $activated = array();
        if ($this->user_info['license'] && isset($this->user_info['license']['bonuses'])) {
            $activated = $this->user_info['license']['bonuses']['activated'];
            /*$rows = $this->db->select(sprintf("SELECT bonus_name, activated, free_months, paid_till FROM users_bonuses WHERE user_id = %d AND license_id = %d", $this->user_id, $this->user_info['license']['id']), true);
            foreach ($rows as $row) {
                $activated[$row['bonus_name']] = $row;
            }*/
        }

        $bonuses = $this->db->select(sprintf("SELECT * FROM bonuses WHERE bonus_name IN ('%s')", implode("', '", $bonuses_ids)), true);
        foreach($bonuses as $k => $v) {
            if ($this->ct_lang == 'ru') {
                $bonuses[$k]['title'] = sprintf($this->lang['l_bonuses_bonus_title'],
                    $v['free_months'],
                    $this->number_lng($v['free_months'], array('месяц', 'месяца', 'месяцев'))
                );
            } else {
                $bonuses[$k]['title'] = sprintf($this->lang['l_bonuses_bonus_title'],
                    $v['free_months'],
                    $this->number_lng($v['free_months'], array('month', 'months'))
                );
            }
            $bonuses[$k]['description'] = $this->lang['l_bonuses_bonus_description_' . $v['bonus_name']];
            $bonuses[$k]['button'] = $this->lang['l_bonuses_bonus_button_' . $v['bonus_name']];
            $bonuses[$k]['link'] = $this->lang['l_bonuses_bonus_link_' . $v['bonus_name']];
            if (isset($this->lang['l_bonuses_bonus_notice_' . $v['bonus_name']])) $bonuses[$k]['notice'] = $this->lang['l_bonuses_bonus_notice_' . $v['bonus_name']];

            $progress['max'] += $v['free_months'];
            if (isset($activated[$v['bonus_name']])) {
                $activated[$v['bonus_name']] = array_merge($v, $activated[$v['bonus_name']]);
                $activated[$v['bonus_name']]['activated'] = date('M d Y', strtotime($activated[$v['bonus_name']]['activated']));
                $activated[$v['bonus_name']]['paid_till'] = date('M d Y', strtotime($activated[$v['bonus_name']]['paid_till']));
                $progress['current'] += $v['free_months'];
                unset($bonuses[$k]);
            } else {
                if (isset($this->user_info['license']) && $this->user_info['license']['trial'] && !$v['show_before_paid']) {
                    unset($bonuses[$k]);
                }
                if (isset($this->user_info['license']) && !$this->user_info['license']['trial'] && !$v['show_after_paid']) {
                    unset($bonuses[$k]);
                }
            }
        }

        $progress['percent'] = round($progress['current'] / $progress['max'] * 100);
        if ($this->ct_lang == 'ru') {
            $progress['text'] = sprintf($this->lang['l_bonuses_progress'],
                $progress['current'], $this->number_lng($progress['current'], array('месяц', 'месяца', 'месяцев')));
        } else {
            $progress['text'] = sprintf($this->lang['l_bonuses_progress'],
                $progress['current'], $this->number_lng($progress['current'], array('month', 'months')));
        }

        $this->page_info['bonuses'] = array(
            'available' => $bonuses,
            'activated' => $activated,
            'progress' => $progress
        );
    }

    /**
      * Функция выводит информацию о бонусных программах
      *
      * @return void
      */

    function get_bonuses_conditions() {

        // Записываем пригласительный Twitter ключ
        $twitter_invite_key = $this->user_info['twitter_invite_key'];
        if (!$twitter_invite_key) {
            $twitter_invite_key = $this->generatePassword(8);

            $this->db->run(sprintf("update users set twitter_invite_key = %s where user_id = %d;",
                $this->stringToDB($twitter_invite_key),
                $this->user_id
            ));
        }

        // Записываем пригласительный ключ по программе приведи друга
        $friend_invite_key = $this->user_info['friend_invite_key'];
        if (!$friend_invite_key) {
            $friend_invite_key = $this->generatePassword(10);

            $this->db->run(sprintf("update users set friend_invite_key = %s where user_id = %d;",
                $this->stringToDB($friend_invite_key),
                $this->user_id
            ));
        }

        $this->show_review();

        $rows = $this->db->select(sprintf("select bonus_name, free_months, show_before_paid, show_after_paid from bonuses where show_before_paid = 1 or show_after_paid = 1;"), true);
        $bonuses = array();
        $free_months_total = 0;
        $free_months_activated = 0;
        $fm = null;
        $free_months_early_pay = 0;
        $free_months_early_activated = false;
        $bonuses_avaible = 0;
        foreach ($rows as $k => $v) {

            /*
                Выдаем дорогие бонусы только на акаунтах с небольшим количеством сайтов.
            */
            if (($this->tariffs[$this->user_info['fk_tariff']]['services'] > $this->options['max_services_for_bonus'] || $this->tariffs[$this->user_info['fk_tariff']]['services'] == 0) &&
                    $v['free_months'] > $this->options['max_months_for_bonus']
                ) {
                continue;
            }

            $sql = sprintf("select activated, free_months from users_bonuses where user_id = %d and bonus_name = %s;",
                $this->user_id,
                $this->stringToDB($v['bonus_name'])
            );
            $row = $this->db->select($sql);

            if ($row['activated'])  {
                $v['activated'] = sprintf($this->lang['l_bonus_activate_notice'], date("M d Y", strtotime($row['activated'])), $row['free_months']);
                $free_months_activated = $free_months_activated + $row['free_months'];
            } else {
                $v['activated'] = false;
                $bonuses_avaible++;
            }
			
			$v['title'] = sprintf($this->lang['l_bonus_title_free_months'], $v['free_months']);
			if ($v['bonus_name'] == 'review' && $this->no_bonus) {
				$v['title'] = sprintf($this->lang['l_help_other_know']);
			}
            $v['desc'] = sprintf($this->lang['l_bonus_' . $v['bonus_name'] . '_desc'], $v['free_months']);

            // Статистика по программе Приведи друга
            if ($v['bonus_name'] == 'friend') {
                $row = $this->db->select(sprintf("select count(*) as count from users where friend_id = %d;", $this->user_id));

                $signed_friends = $row['count'];
                $row = $this->db->select(sprintf("select count(*) as count from users_bonuses where user_id = %d and bonus_name = 'friend';", $this->user_id));
                $paid_friends = $row['count'];
                $v['stat'] = sprintf($this->lang['l_bonus_friend_stat'], $signed_friends, $paid_friends);
            }


            if (!isset($fm['months_total'][$v['bonus_name']])) {
                $fm['months_total'][$v['bonus_name']] = 0;
            }
            if (!isset($fm['months_activated'][$v['bonus_name']])) {
                $fm['months_activated'][$v['bonus_name']] = 0;
            }
            if ($this->user_info['first_pay_id']) {
                $free_months_total = $free_months_total + $v['free_months'];
                if ($v['bonus_name']!='review')
                  $fm['months_total'][$v['bonus_name']] =  $fm['months_total'][$v['bonus_name']] + $v['free_months'];
                elseif(!$this->no_bonus)
                  $fm['months_total'][$v['bonus_name']] =  $fm['months_total'][$v['bonus_name']] + $v['free_months'];
            } else {
                if ($v['show_before_paid']) {
                    $free_months_total = $free_months_total + $v['free_months'];
                    $fm['months_total'][$v['bonus_name']] =  $fm['months_total'][$v['bonus_name']] + $v['free_months'];
                }
            }
            if ($v['activated']) {
                //$free_months_activated = $free_months_activated + $v['free_months'];
                $fm['months_activated'][$v['bonus_name']] =  $fm['months_activated'][$v['bonus_name']] + $v['free_months'];
            }

            if ($v['bonus_name'] == 'early_pay') {
                $free_months_early_pay = $v['free_months'];

                if ($v['activated']) {
                    $free_months_early_activated = true;
                }
            }
            $v['show'] = $v['show_before_paid'] ? true : false;
            if ($v['activated'] == true) {
                $v['show'] = false;
            }
            if ($v['show_after_paid'] && $this->user_info['trial'] == 0 && $v['activated'] == false) {
                $v['show'] = true;
            }

            $bonuses[$v['bonus_name']] = $v;
        }

        $free_months_total = 0;
        foreach ($fm['months_total'] as $k => $v) {
            $free_months_total = $free_months_total + $v;
        }

        if (!$free_months_early_activated) {
            $free_months_total = $free_months_total - $free_months_early_pay;
        }

        /*$free_months_activated = 0;
        foreach ($fm['months_activated'] as $k => $v) {
            $free_months_activated = $free_months_activated + $v;
        }*/
        $free_months_percent = 0;
        if ($free_months_total) {
            $free_months_percent = $free_months_activated / $free_months_total;
        }

        $this->page_info['free_months_activated'] = $free_months_activated;
        $this->page_info['free_months_total'] = $free_months_total;
        $this->page_info['free_months_percent'] = sprintf("%d", $free_months_percent * 100);
        $this->page_info['free_months_activate_notice'] = sprintf($this->lang['l_free_months_activate_notice'], $free_months_activated);

        $this->page_info['bonuses'] = $bonuses;
        $this->page_info['bonuses_avaible'] = $bonuses_avaible;

        $this->page_info['share_url'] = sprintf('http://%s/friends/%s', $_SERVER['HTTP_HOST'], $friend_invite_key);
        $this->page_info['share_url_encoded'] = urlencode($this->page_info['share_url']);

        if (isset($bonuses['friend'])) {
            $this->page_info['head']['title'] = sprintf($this->lang['l_share_title'], $bonuses['friend']['free_months']);
            $this->page_info['share_title'] = urlencode(sprintf($this->lang['l_share_title'], $bonuses['friend']['free_months']));
        }

        $this->page_info['social_review_hint'] = sprintf($this->lang['l_social_review_hint'],
           cfg::contact_email
        );

        $share_host = 'https://cleantalk.org';
        $share_engine = 'wordpress';
        $row_e = $this->db->select(sprintf("select engine from services where user_id = %d;", $this->user_id), true);
        if (count($row_e)) {
            $engines = null;
            foreach ($row_e as $v) {
                if (isset($engines[$v['engine']])) {
                    $engines[$v['engine']]++;
                } else {
                    $engines[$v['engine']] = 1;
                }
            }
            $last_count = 0;
            foreach ($engines as $k => $c) {
                if ($c > $last_count) {
                    $share_engine = $k;
                }
                $last_count = $c;
            }

            if ($share_engine == 'joomla15') {
                $share_engine = 'joomla';
            }
            if (preg_match("/^(phpbb)/", $share_engine, $matches)) {
                $share_engine = $matches[0];
            }
        }

        if ($share_engine == 'unknown') {
            $share_engine = 'wordpress';
        }

        $twitter_title = sprintf($this->lang['l_share_title_twitter'], $twitter_invite_key);
        $userengine = $this->db->select(sprintf('select engine from services where user_id=%d limit 1',$this->user_info['user_id']));

        $engine = null;
        if (isset($userengine['engine'])) {
            $engine = $userengine['engine'];
        }
        if (!$engine || $engine == 'unknown') {
            $engine = 'wordpress';
        }

        $fblink = $this->db->select(sprintf("select seo_url 
                                             from links 
                                             where REPLACE(template,'.html','') = %s",
                                             $this->stringToDB($engine)));

        $this->page_info['fblink'] = $fblink['seo_url'];
        $this->page_info['inlink'] = $fblink['seo_url'];

        $pagetotwitter = $this->db->select(sprintf('select b.seo_url 
                                                    from platforms a 
                                                    join links b
                                                    on a.link_id = b.id
                                                    where a.engine=%s',$this->stringToDB($engine)));
        $this->page_info['pagetotwitter'] = $pagetotwitter['seo_url'];



        /*
            Вычисляем хостера клиента
        */
        /*
        #
        # Отклчил твиты хостерам, т.к. не эффективны и лучше продвигать посадочную страницу.:w
        #
        $sql = sprintf("select a.asn_id, a.services_count, a.twitter_username from services s left join asn a on a.asn_id = s.asn_id where s.user_id = %d and s.asn_id is not null and a.twitter_username is not null order by a.services_count desc limit 1;", $this->user_id);
        $hoster = $this->db->select($sql);

        if (isset($hoster['twitter_username'])) {
            $share_host = sprintf("%s/%s",
                $share_host,
                'hosting-antispam'
            );
            $twitter_title = sprintf($this->lang['l_share_title_twitter_hoster'],
                $hoster['twitter_username'],
                $twitter_invite_key
            );
        }
        */

        $this->page_info['share_url_twitter'] = $share_host;
        $this->page_info['share_title_twitter'] = $twitter_title;
        $this->page_info['share_engine_twitter'] = $share_engine;
//        var_dump($share_host, $this->page_info['share_title_twitter']);

        $this->page_info['friends_first'] = false;
        if ($this->user_info['first_pay_id'] && isset($bonuses['review']) && $bonuses['review']['activated']) {
            $this->page_info['friends_first'] = true;
        }
        $this->page_info['show_early_pay'] = true;
        $valid_till_ts = strtotime($this->user_info['license']['valid_till']) + 86400;
        if ($this->user_info['first_pay_id'] && !$bonuses['early_pay']['activated'] ||
            ($this->user_info['first_pay_id'] == null && $valid_till_ts < time())
        ) {
            $this->page_info['show_early_pay'] = false;
        }

        $this->page_info['show_bonuses_part'] = $this->show_bonuses_block($this->user_id);

        return null;
    }

    function show_ssl_service_edit() {
        $this->get_lang($this->ct_lang, 'Ssl');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'ssl/certificate/csr.html';
        $this->page_info['scripts'] = array('/my/js/validator.min.js');

        // Проверяем наличие свободной лицензии
        if (!isset($this->user_info['licenses']['ssl'])) $this->url_redirect('bill/ssl');
        $licenses = $this->user_info['licenses']['ssl'];
        $license = false;
        foreach ($licenses as $l) {
            if ($l['multiservice_id'] == 0) {
                $license = $l;
                break;
            }
        }
        if (!$license) $this->url_redirect('bill/ssl');

        $step = 1;
        $this->page_info['head']['title'] = 'Enter CSR';
        $errors = array();
        if (count($_POST) && isset($_POST['step'])) {
            $info = $this->safe_vars($_POST);
            switch ($_POST['step']) {
                // Первый шаг - ввод CSR
                case 1:
                    if (!isset($_POST['csr']) || empty($_POST['csr'])) {
                        $errors['csr'] = 'required field';
                    } else {
                        if ($csr = openssl_csr_get_subject($info['csr'], false)) {
                            $required = array('countryName', 'stateOrProvinceName', 'localityName', 'organizationName', 'commonName');
                            foreach ($required as $field) {
                                if (!isset($csr[$field]) || empty($field) || $csr[$field] == 'none') {
                                    $errors['csr'] = 'required "' . $field . '"';
                                }
                            }
                            if (!isset($this->countries[$csr['countryName']]) && $csr['countryName'] != 'EN') {
                                $errors['csr'] = 'unknown country: ' . $csr['countryName'];
                            }
                            if (substr($csr['commonName'], 0, 2) == '*.') {
                                $errors['csr'] = 'The CSR\'s Common Name may NOT contain a wildcard! Please fill Common name as single domain. For example: cleantalk.org.';
                            }
                            // проверка на дубль
                            if ($old = $this->db->select(sprintf("SELECT * FROM ssl_certs WHERE domains = %s", $this->stringToDB($csr['commonName'])))) {
                                if (!isset($old['ca_certificateID']) && $old['user_id'] == $this->user_info['user_id']) {
                                    $this->db->run(sprintf("DELETE FROM ssl_certs WHERE cert_id = %d", $old['cert_id']));
                                } else if (isset($old['ca_certificateID'])) {
                                    $errors['csr'] = 'Duplicate domains: ' . $csr['commonName'];
                                }
                            }
                            if (empty($errors)) {
                                $ca_product = 287;
                                $ca_name = 'Positive SSL';
                                if (isset($license['tariff']['ssl_type_id']) && $license['tariff']['ssl_type_id']) {
                                    if ($ssl_type = $this->db->select(sprintf("SELECT * FROM ssl_certs_types WHERE ssl_type_id = %d", $license['tariff']['ssl_type_id']))) {
                                        $ca_product = $ssl_type['ca_product'];
                                        $ca_name = $ssl_type['ca_name'];
                                    }
                                }
                                $years = 1;
                                if ($bill = $this->db->select(sprintf("SELECT period FROM bills WHERE bill_id = %d", $license['bill_id']))) {
                                    $years = $bill['period'];
                                }
                                $cert = array(
                                    'user_id' => $this->user_info['user_id'],
                                    'years' => $years,
                                    'domains' => $this->stringToDB($csr['commonName']),
                                    'ca_product_id' => $ca_product,
                                    'name' => $this->stringToDB($ca_name),
                                    'organizationName' => $this->stringToDB($csr['organizationName']),
                                    'streetAddress1' => "''",
                                    'streetAddress2' => "''",
                                    'streetAddress3' => "''",
                                    'localityName' => $this->stringToDB($csr['localityName']),
                                    'stateOrProvinceName' => $this->stringToDB($csr['stateOrProvinceName']),
                                    'postalCode' => "''",
                                    'countryName' => $this->stringToDB($csr['countryName']),
                                    'emailAddress' => $this->stringToDB($csr['emailAddress']),
                                    'dcvEmailAddress' => $this->stringToDB($csr['emailAddress']),
                                    'serverSoftware' => -1,
                                    'csr' => $this->stringToDB($info['csr'])
                                );
                                $cert_id = $this->db->run(sprintf(
                                    "INSERT INTO ssl_certs (`%s`) VALUES (%s)",
                                    implode('`, `', array_keys($cert)),
                                    implode(', ', array_values($cert))
                                ));
                                if ($cert_id) {
                                    $step = 2;
                                    $this->page_info['head']['title'] = 'Check if we\'ve got you right';
                                    $this->page_info['cert_id'] = $cert_id;
                                    $this->page_info['cert'] = array(
                                        'organizationName' => $csr['organizationName'],
                                        'localityName' => $csr['localityName'],
                                        'stateOrProvinceName' => $csr['stateOrProvinceName'],
                                        'countryName' => $csr['countryName'],
                                        'emailAddress' => $csr['emailAddress'],
                                        'commonName' => array($csr['commonName'])
                                    );
                                    if (substr($csr['commonName'], 0, 2) != '*.') {
                                        if (substr($csr['commonName'], 0, 4) != 'www.') {
                                            $this->page_info['cert']['commonName'][] = 'www.' . $csr['commonName'];
                                        } else {
                                            $this->page_info['cert']['commonName'][] = substr($csr['commonName'], 5);
                                        }
                                    }
                                }
                            }
                        } else {
                            $errors['csr'] = 'invalid CSR';
                        }
                    }
                    break;
                case 2:
                    if (!isset($info['cert_id'])) {
                        $step = -1;
                        $this->page_info['error'] = 'Internal Server Error';
                    } else {
                        $cert = $this->db->select(sprintf("SELECT * FROM ssl_certs WHERE cert_id = %d", $info['cert_id']));
                        if ($cert) {
                            $this->page_info['head']['title'] = 'Email validation';
                            $this->page_info['cert_id'] = $info['cert_id'];
                            $step = 3;

                            $domain = $cert['domains'];
                            if (substr($domain, 0, 2) == '*.') $domain = substr($domain, 2);

                            $context = stream_context_create(array(
                                'http' => array(
                                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                                    'method' => 'POST',
                                    'content' => http_build_query(array(
                                        'loginName' => cfg::comodo_login,
                                        'loginPassword' => cfg::comodo_password,
                                        'domainName' => $domain
                                    ))
                                )
                            ));
                            $response = @file_get_contents('https://secure.comodo.net/products/!GetDCVEmailAddressList', false, $context);
                            preg_match('/.+([0-9]{3}).+/', $http_response_header[0], $matches);
                            $response_code = $matches[1];
                            $this->db->run(sprintf(
                                "UPDATE ssl_certs SET ca_api_method = %s, ca_api_called = now(), ca_api_return = %s WHERE cert_id = %d",
                                $this->stringToDB('GetDCVEmailAddressList'),
                                $this->stringToDB(($response_code !== '200') ? '' : $response_code . "\n" . $response),
                                $info['cert_id']
                            ));
                            if ($response_code !== '200') {
                                $response = array(-1);
                            } else {
                                $response = explode("\n", $response);
                            }

                            if ($response[0] == '0') {
                                $emails = array();
                                for ($i = 1; $i < count($response); $i++) {
                                    $line = explode("\t", $response[$i]);
                                    if ($line[0] == 'whois_email' || $line[0] == 'level2_email') {
                                        $emails[] = $line[1];
                                    }
                                }

                                $this->page_info['emails'] = $emails;
                                $this->page_info['cert'] = $cert;
                            } else {
                                error_log('Comodo error ' . $response[1]);
                                $step = -1;
                                $this->page_info['error'] = 'Internal Server Error';
                            }
                        } else {
                            $step = -1;
                            $this->page_info['error'] = 'Internal Server Error';
                        }
                    }
                    break;
                case 3:
                    if (!isset($info['cert_id']) || !isset($info['email'])) {
                        $step = -1;
                        $this->page_info['error'] = 'Internal Server Error';
                    } else {
                        $cert = $this->db->select(sprintf("SELECT * FROM ssl_certs WHERE cert_id = %d", $info['cert_id']));
                        if ($cert) {
                            $this->db->run(sprintf("UPDATE ssl_certs SET dcvEmailAddress = %s, ca_api_called = NULL WHERE cert_id = %d", $this->stringToDB($info['email']), $cert['cert_id']));
                            $this->db->run(sprintf("UPDATE users_licenses SET multiservice_id = %d WHERE id = %d", $cert['cert_id'], $license['id']));
                            apc_delete($this->account_label);
                            $this->url_redirect('');
                        } else {
                            $step = -1;
                            $this->page_info['error'] = 'Internal Server Error';
                        }
                    }
                    break;
            }
        }
        $this->page_info['step'] = $step;
        $this->page_info['error_fields'] = $errors;
    }

    function show_security_service_edit() {
        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/service.html';
        $this->page_info['scripts'] = array('/my/js/bootstrap3-typeahead.min.js','/my/js/validator.min.js','/my/js/service_create.js?v8');

        $other_services = $this->db->select(sprintf('SELECT hostname, product_id, favicon_url FROM services WHERE user_id=%d AND product_id!=%d AND hostname IS NOT NULL ORDER BY hostname;', $this->user_id, cfg::product_security), true);
        if(!empty($other_services)){
            foreach ($other_services as &$s) {
                $product = '';
                if($s['product_id']==cfg::product_database_api){
                    $product = 'Database';
                }elseif ($s['product_id']==cfg::product_hosting_antispam) {
                    $product = 'Hosting';
                }elseif ($s['product_id']==cfg::product_antispam) {
                    $product = 'Anti-Spam';
                }elseif ($s['product_id']==cfg::product_ssl) {
                    $product = 'SSL';
                }
                $os[] = $s['hostname'] . '#' . $product . '#' .$s['favicon_url'];
            }
            $this->page_info['other_services'] = json_encode($os);
        }
        if(apc_exists('security_message_'.$this->user_id)){
            $hostname = apc_fetch('security_message_'.$this->user_id);
            $this->page_info['already_exists'] = sprintf($this->lang['l_already_exists'],$hostname);
            apc_delete('security_message_'.$this->user_id);
        }

        $sql = sprintf("SELECT %s FROM platforms p JOIN platforms_products pp ON pp.platform_id = p.platform_id AND pp.product_id = %d ORDER BY info",
            implode(', ', array('p.platform_id', 'p.engine', 'p.info', 'p.lang', 'p.type')),
            cfg::product_security);
        $rows = $this->db->select($sql, true);
        $platforms = array();
        foreach ($rows as $row) {
            if (in_array($this->ct_lang, explode(',', $row['lang']))) {
                $platforms[] = $row;
            }
        }
        $this->page_info['platforms'] = $platforms;

        $ct_in_list_db_count = apc_fetch('ct_in_list_db_count');
        if (!$ct_in_list_db_count) {
            $in_list = $this->db->select("SELECT COUNT(*) as c FROM bl_ips_security WHERE in_list=1");
            $in_list = $in_list ? $in_list['c'] : 0;
            $in_sfw = $this->db->select("SELECT COUNT(*) as c FROM bl_ips WHERE  in_sfw=1");
            $in_sfw = $in_sfw ? $in_sfw['c'] : 0;
            $ct_in_list_db_count = $in_list + $in_sfw;
            apc_store('ct_in_list_db_count', $ct_in_list_db_count);
        }
        $this->page_info['service_ct_in_list_db_hint'] = sprintf($this->lang['l_service_ct_in_list_db_hint'], $ct_in_list_db_count);

        $action = isset($_GET['action']) ? $_GET['action'] : null;
        $service_id = null;
        switch ($action) {
            case 'created':
                $this->page_info['hide_form'] = true;
            case 'updated':
            case 'edit':
            case 'delete':
                $this->page_info['panel_title'] = $this->lang['l_service_edit'];
                if (!isset($_REQUEST['service_id']) || !preg_match("/^\d+$/", $_REQUEST['service_id'])) {
                    $this->url_redirect();
                }
                $service_id = $_REQUEST['service_id'];
                $this->page_info['button_title'] = $this->lang['l_btn_update'];
                break;
            case 'new':
                $this->page_info['panel_title'] = $this->lang['l_service_add'];
                $this->page_info['button_title'] = $this->lang['l_btn_create'];
                break;
            case 'multiple':
                $this->page_info['template'] = 'security/services.html';
                $this->page_info['panel_title'] = $this->lang['l_services_add'];
                $this->page_info['button_title'] = $this->lang['l_btn_create'];
                break;
            case 'deleted':
                $this->page_info['info'] = $this->lang['l_service_deleted'];
                $this->page_info['hide_form'] = true;
                return;
            default:
                $this->url_redirect('');
        }

        $service = null;
        $allow_update = true;
        if ($service_id) {
            $service = $this->db->select(sprintf('SELECT * FROM services WHERE service_id=%d AND user_id=%d', $service_id, $this->user_id));
            if (!$service) {
                $service_granted = $this->db->select(sprintf("SELECT service_id, grantwrite FROM services_grants WHERE service_id = %d AND user_id_granted = %d", $service_id, $this->user_id));
                if (!$service_granted) $this->url_redirect('');
                $service = $this->db->select(sprintf('SELECT * FROM services WHERE service_id=%d', $service_id));
                if (!$service) $this->url_redirect('');
                if (!$service_granted['grantwrite']) $allow_update = false;
            }
            $this->page_info['service'] = array(
                'url' => $service['hostname'],
                'name' => $service['name'],
                'notify_admin_login' => $service['notify_admin_login'],
                'auto_whitelist_owner_ip' => $service['auto_whitelist_owner_ip'],
                'ct_in_list_db' => $service['ct_in_list_db'],
                'auto_update_app' => $service['auto_update_app']
            );
            foreach ($this->page_info['platforms'] as &$platform) {
                if ($platform['engine'] == $service['engine']) {
                    $platform['selected'] = true;
                    break;
                }
            }
            switch ($action) {
                case 'created':
                    $this->page_info['info'] = sprintf($this->lang['l_service_created'], $service['hostname'], $service['service_id'], $service['auth_key']);
                    if ($service['engine'] == 'wordpress') {
                        $this->page_info['info'] .= $this->lang['l_service_created_wordpress'];
                    }
                    break;
                case 'updated':
                    $this->page_info['info'] = $this->lang['l_security_updated'];
                    break;
            }
        } else {
            foreach ($this->page_info['platforms'] as &$platform) {
                if ('wordpress' == $platform['engine']) {
                    $platform['selected'] = true;
                    break;
                }
            }
        }
        $this->page_info['allow_update'] = $allow_update;

        $tools = new CleanTalkTools();
        switch ($action) {
            case 'multiple':
                $this->page_info['scripts'] = array(
                    '/my/js/bootstrap3-typeahead.min.js',
                    'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/1.5.16/clipboard.min.js',
                    '/my/js/service_create.js?v8'
                );
                if (!isset($this->user_info['license'])) {
                    $this->url_redirect('bill/security');
                    return;
                } else {
                    $services = $this->db->select(sprintf('SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d', $this->user_id, cfg::product_security), true);
                    $max_new_services = $this->user_info['tariff']['services'] - ($services ? count($services) : 0);
                    if ($max_new_services < 1 && !count($_POST)) {
                        $this->url_redirect('service?action=new');
                        return;
                    }
                    $this->page_info['service_urls_hint'] = sprintf($this->lang['l_service_urls_hint'], $max_new_services);

                    if (count($_POST)) {
                        $info = $this->safe_vars($_POST);
                        $tools = new CleanTalkTools();

                        if (!isset($_POST['services'])) {
                            if (!isset($info['service_urls']) || empty($info['service_urls'])) $this->url_redirect('service?action=multiple');

                            if (isset($this->user_info['license'])) {
                                $license = $this->user_info['license'];
                            } else {
                                $this->new_license_security($this->user_info, $this->user_info['licenses']);
                                if (isset($this->user_info['license'])) {
                                    $license = $this->user_info['license'];
                                } else {
                                    $license = array('moderate' => 0, 'product_id' => 4);
                                }
                            }

                            $service_moderate = $license['moderate'];
                            $service_product_id = $license['product_id'];
                            $service_engine = (isset($_POST['services_engine']) && $_POST['services_engine']) ? $_POST['services_engine'] : 'wordpress';

                            $urls = preg_split('/[\s,;]+/', $info['service_urls']);
                            $urls = array_unique($urls);
                            if(count($urls)>$max_new_services){
                                // превышено количество сервисов
                                $this->page_info['offer'] = array(
                                    'title' => $this->lang['l_offer_warning_title'],
                                    'text' => sprintf($this->lang['l_offer_warning_text'], count($services), (isset($this->user_info['tariff'])) ? $this->user_info['tariff']['services'] : 1),
                                    'services' => $services
                                );
                                // выбираем тариф на повышение
                                $new_tariff = $this->db->select(sprintf('SELECT
                                    tariff_id, services, cost_usd
                                    FROM tariffs 
                                    WHERE product_id = %d AND services > %d AND allow_subscribe = 1 ORDER BY services ASC LIMIT 1',
                                    cfg::product_security, count($urls)+count($services)
                                ));
                                if ($new_tariff) {
                                    $this->page_info['offer']['new'] = array(
                                        'title' => sprintf($this->lang['l_offer_new_package_title'], $new_tariff['services'], $new_tariff['cost_usd']),
                                        'services' => $new_tariff['services'],
                                        'link' => sprintf('/my/bill/security?product=%d&utm_source=cleantalk.org&utm_medium=upgrade_banner_button&utm_campaign=control_panel', $new_tariff['tariff_id'])
                                    );
                                }
                                $this->page_info['template'] = 'security/service.html';
                                return;
                            }
                            array_splice($urls, $max_new_services);

                            $services = array();
                            foreach ($urls as $url) {
                                $url = mb_substr(trim($url), 0, 1024);
                                if ($url) $url = $tools->get_domain($url);
                                if ($url) $url = idn_to_utf8($url);
                                if (!$url) continue;

                                $services[] = array(
                                    'hostname' => $url,
                                    'engine' => $service_engine,
                                    'name' => '',
                                    'auth_key' => $this->generatePassword($this->options['auth_key_length'], 4)
                                );
                            }

                            apc_delete($this->account_label);
                            $exists = array();
                            foreach ($services as $key=>&$service) {
                                $sql = sprintf("SELECT service_id FROM services WHERE user_id=%d AND hostname=%s AND product_id=%d",
                                    $this->user_id,
                                    $this->stringToDB($service['hostname']),
                                    cfg::product_security
                                );
                                if($this->db->select($sql)){
                                    $exists[] = $service['hostname'];
                                    unset($services[$key]);
                                    continue;
                                }
                                $service_id = $this->db->run(sprintf(
                                    "INSERT INTO services (%s) VALUES (%s)",
                                    implode(', ', array('user_id', 'name', 'hostname', 'engine', 'response_lang', 'auth_key', 'created', 'product_id', 'moderate_service')),
                                    implode(', ', array(
                                        $this->user_info['user_id'],
                                        'NULL',
                                        $this->stringToDB($service['hostname']),
                                        $this->stringToDB($service['engine']),
                                        $this->stringToDB('en'),
                                        $this->stringToDB($service['auth_key']),
                                        'now()',
                                        $service_product_id,
                                        $service_moderate
                                    ))
                                ));
                                $service['id'] = $service_id;
                                $this->post_log(sprintf('Добавлен security-сервис %s (%d) пользователя %s (%d)',$service['hostname'], $service_id, $this->user_info['email'], $this->user_id));
                            }

                            apc_delete($this->account_label);
                            apc_delete('security_services_' . $this->user_info['user_id']);
                            if(!empty($exists)){
                                $this->page_info['already_exists'] = sprintf($this->lang['l_already_exists'],implode(', ', $exists));
                            }
                            $this->page_info['services'] = $services;
                        } else {
                            $services = array();
                            foreach ($info['services'] as $service_id) {
                                $service_name = mb_substr($info['name'][$service_id], 0, 64);
                                $service_engine = $info['engine'][$service_id];

                                $this->db->run(sprintf(
                                    "UPDATE services SET name=%s, engine=%s WHERE service_id=%d AND user_id=%d",
                                    $service_name ? $this->stringToDB($service_name) : 'NULL',
                                    $this->stringToDB($service_engine),
                                    $service_id,
                                    $this->user_info['user_id']
                                ));
                                $services[] = array(
                                    'id' => $service_id,
                                    'hostname' => $info['hostname'][$service_id],
                                    'engine' => $service_engine,
                                    'name' => $service_name,
                                    'auth_key' => $info['auth_key'][$service_id]
                                );
                            }
                            apc_delete($this->account_label);
                            apc_delete('security_services_' . $this->user_info['user_id']);
                            $this->page_info['services'] = $services;
                            $this->page_info['info'] = $this->lang['l_security_updated'];
                        }
                    }
                }
                break;
            case 'new':
                // Логика показа баннера
                /*if (!isset($this->user_info['license'])) {
                    // нет лицензии - отправляем на страницу оплаты
                    $this->url_redirect('bill/security');
                    return;
                } else if (strtotime($this->user_info['license']['valid_till']) < time() || !$this->user_info['license']['moderate']) {
                    // истёк срок действия
                    $this->url_redirect('bill/security');
                    return;
                } else {*/
                /*$this->page_info['service'] = array(
                    'notify_admin_login' => true,
                    'auto_whitelist_owner_ip' => true,
                    'ct_in_list_db' => true
                );*/
                $services = $this->db->select(sprintf('SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d', $this->user_id, cfg::product_security), true);
                    if ($services && count($services) >= (isset($this->user_info['tariff']) ? $this->user_info['tariff']['services'] : 1)) {
                        // превышено количество сервисов
                        $this->page_info['offer'] = array(
                            'title' => $this->lang['l_offer_warning_title'],
                            'text' => sprintf($this->lang['l_offer_warning_text'], count($services), (isset($this->user_info['tariff'])) ? $this->user_info['tariff']['services'] : 1),
                            'services' => $services
                        );
                        // выбираем тариф на повышение
                        $new_tariff = $this->db->select(sprintf('SELECT
                            tariff_id, services, cost_usd
                            FROM tariffs 
                            WHERE product_id = %d AND services > %d AND allow_subscribe = 1 ORDER BY services ASC LIMIT 1',
                            cfg::product_security, count($services)
                        ));
                        if ($new_tariff) {
                            $this->page_info['offer']['new'] = array(
                                'title' => sprintf($this->lang['l_offer_new_package_title'], $new_tariff['services'], $new_tariff['cost_usd']),
                                'services' => $new_tariff['services'],
                                'link' => sprintf('/my/bill/security?product=%d&utm_source=cleantalk.org&utm_medium=upgrade_banner_button&utm_campaign=control_panel', $new_tariff['tariff_id'])
                            );
                        }
                        return;
                    } else if (isset($this->user_info['tariff'])) {
                        $max_new_services = $this->user_info['tariff']['services'] - ($services ? count($services) : 0);
                        if ($max_new_services > 1) $this->page_info['allow_multiple'] = $max_new_services;
                    }
                //}

                if (count($_POST) && $allow_update) {
                    $info = $this->safe_vars($_POST);

                    // $info['service_name'] = mb_substr($info['service_name'], 0, 64);
                    $info['service_url'] = mb_substr($info['service_url'], 0, 1024);
                    $info['service_url'] = $tools->get_domain($info['service_url']);
                    // $info['notify_admin_login'] = isset($info['notify_admin_login']) ? 'TRUE' : 'FALSE';
                    // $info['auto_whitelist_owner_ip'] = isset($info['auto_whitelist_owner_ip']) ? 1 : 0;
                    // $info['ct_in_list_db'] = isset($info['ct_in_list_db']) ? 1 : 0;

                    $info['service_name'] = '';
                    $info['notify_admin_login'] = 'TRUE';
                    $info['auto_whitelist_owner_ip'] = 1;
                    $info['ct_in_list_db'] = 1;

                    if (!isset($info['service_engine'])) $info['service_engine'] = 'wordpress';
                    if ($info['service_url'] && function_exists('idn_to_utf8')) $info['service_url'] = idn_to_utf8($info['service_url']);

                    if (!$info['service_url']) {
                        $this->page_info['error_field'] = 'service_url';
                        return;
                    }

                    if (!isset($this->user_info['license'])) {
                        $this->new_license_security($this->user_info, $this->user_info['licenses']);
                    }

                    $sql = sprintf("SELECT service_id FROM services WHERE user_id=%d AND hostname=%s AND product_id=%d",
                        $this->user_id,
                        $this->stringToDB($info['service_url']),
                        cfg::product_security
                    );
                    if($this->db->select($sql)){
                        apc_store('security_message_'.$this->user_id, $info['service_url']);
                        $this->url_redirect('service?action=new');
                        return;
                    }

                    // Добавляем сервис

                    $auth_key = $this->generatePassword($this->options['auth_key_length'], 4);
                    $service_id = $this->db->run(sprintf("INSERT INTO services (
                        user_id, name, hostname, engine, response_lang, stop_list_enable, sms_test_enable,
                        auth_key, created, allow_links_enable, move_to_spam_enable, product_id, moderate_service,
                        notify_admin_login, auto_whitelist_owner_ip, ct_in_list_db
                        ) values (
                        %d, %s, %s, %s, %s, 0, 0,
                        %s, now(), 0, 0, %d, %d, %s, %d, %d
                        )",
                        $this->user_id,
                        $this->stringToDB($info['service_name']),
                        $this->stringToDB($info['service_url']),
                        $this->stringToDB($info['service_engine']),
                        $this->stringToDB($this->user_info['lang']),
                        $this->stringToDB($auth_key),
                        cfg::product_security,
                        isset($this->user_info['license']) ? $this->user_info['license']['moderate'] : 0,
                        $info['notify_admin_login'],
                        $info['auto_whitelist_owner_ip'],
                        $info['ct_in_list_db']
                    ));

                    if ($service_id) {
                        apc_delete($this->account_label);
                        apc_delete('security_services_' . $this->user_info['user_id']);
                        $this->post_log(sprintf('Добавлен security-сервис %s (%d) пользователя %s (%d)', $info['service_url'], $service_id, $this->user_info['email'], $this->user_id));
                        $this->url_redirect('service?service_id=' . $service_id . '&action=created');
                    }
                }
                break;
            case 'edit':
            case 'updated':
                if (count($_POST) && $allow_update) {
                    $info = $this->safe_vars($_POST);

                    $info['service_name'] = mb_substr($info['service_name'], 0, 64);
                    $info['service_url'] = mb_substr($info['service_url'], 0, 1024);
                    $info['service_url'] = $tools->get_domain($info['service_url']);
                    $info['notify_admin_login'] = isset($info['notify_admin_login']) ? 'TRUE' : 'FALSE';
                    $info['auto_whitelist_owner_ip'] = isset($info['auto_whitelist_owner_ip']) ? 1 : 0;
                    $info['ct_in_list_db'] = isset($info['ct_in_list_db']) ? 1 : 0;
                    $info['auto_update_app'] = isset($info['auto_update_app']) ? 1 : 0;
                    

                    if (!isset($info['service_engine'])) $info['service_engine'] = 'wordpress';
                    if ($info['service_url']) $info['service_url'] = idn_to_utf8($info['service_url']);

                    if (!$info['service_url']) {
                        $this->page_info['error_field'] = 'service_url';
                        return;
                    }

                    // Обновляем сервис
                    $this->db->run(sprintf('UPDATE services
                        SET hostname = %s, name = %s, engine = %s, notify_admin_login = %s, auto_whitelist_owner_ip = %d , ct_in_list_db = %d, auto_update_app = %d
                        WHERE service_id = %d',
                        $this->stringToDB($info['service_url']),
                        $this->stringToDB($info['service_name']),
                        $this->stringToDB($info['service_engine']),
                        $info['notify_admin_login'],
                        $info['auto_whitelist_owner_ip'],
                        $info['ct_in_list_db'],
                        $info['auto_update_app'],
                        $service_id));

                    if(isset($info['auto_update_app_all'])){
                        $this->db->run(sprintf('UPDATE services s
                            LEFT JOIN services_apps sa ON s.service_id=sa.service_id
                            SET s.auto_update_app = %d WHERE s.user_id = %d and s.product_id = 4 and sa.app_id IS NOT NULL',
                            $info['auto_update_app'], $this->user_id));                        
                    }

                    apc_delete('security_services_' . $this->user_info['user_id']);
                    $this->post_log(sprintf('Обновлён security-сервис %s (%d) пользователя %s (%d)', $info['service_url'], $service_id, $this->user_info['email'], $this->user_id));
                    $this->url_redirect('service?service_id=' . $service_id . '&action=updated');
                }
                break;
            case 'delete':
                if ($service_id && $allow_update) {
                    $this->db->run(sprintf("DELETE FROM services WHERE service_id = %d", $service_id));
                    $this->db->run(sprintf("DELETE FROM services_security_log WHERE service_id = %d", $service_id));
                    apc_delete($this->account_label);
                    apc_delete('security_services_' . $this->user_info['user_id']);
                    $this->url_redirect('service?action=deleted');
                }
                break;
        }
    }

    /**
      * Вывод информации на редактирование настроек услуги
      *
      * @return bool
      */
    function show_service_edit() {
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template'] = 'antispam/service.html';
        $this->page_info['container_fluid'] = true;
        $this->page_info['show_dashboard_tour'] = $this->options['show_dashboard_tour'];
        $this->page_info['scripts'] = array(
            '/my/js/antispam-service.js',
            '/my/js/bootstrap3-typeahead.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/1.5.16/clipboard.min.js',
            '/my/js/service_create.js?v8'
        );
        if(defined('cfg::show_dashboard_tour')){
            $this->page_info['show_dashboard_tour'] = cfg::show_dashboard_tour;
        }

        if(apc_exists('antispam_message_'.$this->user_id)){
            $hostname = apc_fetch('antispam_message_'.$this->user_id);
            $this->page_info['already_exists'] = sprintf($this->lang['l_already_exists'],$hostname);
            apc_delete('antispam_message_'.$this->user_id);
        }

        $other_services = $this->db->select(sprintf('SELECT hostname, product_id, favicon_url FROM services WHERE user_id=%d AND product_id!=%d AND hostname IS NOT NULL ORDER BY hostname;', $this->user_id, cfg::product_antispam), true);
        if(!empty($other_services)){
            foreach ($other_services as &$s) {
                $product = '';
                if($s['product_id']==cfg::product_database_api){
                    $product = 'Database';
                }elseif ($s['product_id']==cfg::product_hosting_antispam) {
                    $product = 'Hosting';
                }elseif ($s['product_id']==cfg::product_security) {
                    $product = 'Security';
                }elseif ($s['product_id']==cfg::product_ssl) {
                    $product = 'SSL';
                }
                $os[] = $s['hostname'] . '#' . $product . '#' .$s['favicon_url'];
            }
            $this->page_info['other_services'] = json_encode($os);
        }

        // Предложение смены тарифа
        if(!empty($_POST['hostnames'])){
            $urls = preg_split('/[\s,;]+/', $_POST['hostnames']);
            $urls = array_unique($urls);
            $urls_count = count($urls);
        }elseif (!empty($_POST['hostname'])) {
            $urls_count = 1;
        }else{
            $urls_count = 0;
        }
        $services = $this->db->select(sprintf('SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d', $this->user_id, cfg::product_antispam), true);
        if (
            $services 
            && count($services)+$urls_count > (isset($this->user_info['tariff']) ? $this->user_info['tariff']['services'] : 1) 
            && !empty($this->user_info['tariff']['services']) 
            && (isset($_GET['action']) && $_GET['action']=='new')
        ) {
            $this->page_info['services_overlimit'] = sprintf($this->lang['l_services_overlimit'], $this->user_info['services'], $this->user_info['tariff']['services']);
            $offer_tariff_id = $this->show_upgrade_tariffs(true);

            $discount = $this->get_upgrade_discount($this->user_id, $offer_tariff_id);
            $this->show_offer(null, $offer_tariff_id, null, $discount);
            $this->page_info['l_upgrade_discount_title'] = mb_strtolower($this->page_info['l_upgrade_discount_title']);

            $this->page_info['websites'] = $this->get_websites($this->user_id);
            $this->page_info['pay_button'] = strtoupper($this->lang['l_upgrade_antispam']);
            $this->page_info['offer_tariff_id_param'] = sprintf("?tariff_id=%d", $offer_tariff_id);
            $this->page_info['offer_tariff_id_param'] .= '&utm_source=cleantalk.org&amp;utm_medium=upgrade_banner_button&amp;utm_campaign=control_panel';
            $this->page_info['choose_payment_service'] = true;

            // Готовим параметры для оплаты с банера
            $this->fill_pay_method_params();

            if ($this->options['show_currencies']) {
                $this->get_currencies();
            }

            $this->page_info['second_top_button'] = false;
            $this->page_info['hide_charge_form'] = true;
            $this->page_info['hide_charge_form'] = true;
            $this->page_info['hide_money_back'] = true;

            return false;
        }

        $platforms = $this->db->select(sprintf("select platform_id, engine, info, lang, type from platforms where type is not null order by info;"), true);
        foreach ($platforms as $k => $v) {
            $langs = explode(",", $v['lang']);
            if (in_array($this->ct_lang, $langs)) {
                if ($this->ct_lang == 'en'){
                    switch ($v['engine']) {
                        case 'other':
                            $v['info'] = "Another CMS";
                            break;
                        case 'unknown':
                            $v['info'] = "I don't know CMS name!";
                            break;
                    }
                }
                $sorted[$v['type']][] = $v;
            }
        }
        $this->page_info['sorted_platforms'] = $sorted;

        if (isset($_GET['action']) && in_array($_GET['action'], array('new', 'update')) && isset($_GET['multiple'])) {
            $this->page_info['template'] = 'antispam/services.html';

            $services_limit = $this->user_info['tariff']['services'] - $this->user_info['services'];
            if ($this->user_info['tariff']['services'] == 0) $services_limit = 100;
            $this->page_info['hostnames_hint'] = sprintf($this->lang['l_hostnames_hint'], $services_limit);

            if (count($_POST)) {
                $info = $this->safe_vars($_POST);
                $tools = new CleanTalkTools();
                

                if ($_GET['action'] == 'new') {
                    if (!isset($info['hostnames']) || empty($info['hostnames'])) return;

                    $service_moderate = $this->user_info['moderate'];
                    $service_product_id = 'NULL';
                    $service_engine = $info['engine'];
                    if (isset($this->user_info['licenses']) && isset($this->user_info['licenses']['antispam'])) {
                        $license = $this->user_info['licenses']['antispam'];
                        $service_moderate = $license['moderate'];
                        $service_product_id = $license['product_id'];
                    }

                    $urls = preg_split('/[\s,;]+/', $info['hostnames']);
                    $urls = array_unique($urls);
                    array_splice($urls, $services_limit);

                    $services = array();
                    foreach ($urls as $url) {
                        $url = mb_substr(trim($url), 0, 1024);
                        if ($url) $url = $tools->get_domain($url);
                        if ($url && function_exists('idn_to_utf8')) $url = idn_to_utf8($url);
                        if (!$url) continue;

                        $services[] = array(
                            'hostname' => $url,
                            'engine' => $service_engine,
                            'name' => '',
                            'auth_key' => $this->generatePassword($this->options['auth_key_length'], 4)
                        );
                    }

                    $services_label = sprintf('services:%d__%d', $this->user_id, $this->page_info['panel_version']);
                    $this->mc->delete($services_label);

                    apc_delete('number_sites_' . $this->user_id);
                    apc_delete($this->account_label);
                    $exists = array();
                    foreach ($services as $key=>&$service) {
                        $sql = sprintf("SELECT service_id FROM services WHERE user_id=%d AND hostname=%s AND product_id=%d AND engine=%s",
                            $this->user_id,
                            $this->stringToDB($service['hostname']),
                            cfg::product_antispam,
                            $this->stringToDB($service['engine'])
                        );
                        if($this->db->select($sql)){
                            $exists[] = $service['hostname'];
                            unset($services[$key]);
                            continue;
                        }
                        $service_id = $this->db->run(sprintf(
                            "INSERT INTO services (%s) VALUES (%s)",
                            implode(', ', array('user_id', 'name', 'hostname', 'engine', 'response_lang', 'auth_key', 'created', 'product_id', 'moderate_service')),
                            implode(', ', array(
                                $this->user_info['user_id'],
                                $service['name'] ? $this->stringToDB($service['name']) : 'NULL',
                                $this->stringToDB($service['hostname']),
                                $this->stringToDB($service['engine']),
                                $this->stringToDB('en'),
                                $this->stringToDB($service['auth_key']),
                                'now()',
                                $service_product_id,
                                $service_moderate
                            ))
                        ));
                        $service['id'] = $service_id;
                        $tools->store_servicedata_at_mc($service_id);
                        $this->post_log(sprintf("Добавлена услуга %s (%d) пользователя %s (%d).", $service['hostname'], $service_id, $this->user_info['email'],$this->user_id));
                    }
                    if(!empty($exists)){
                        $this->page_info['already_exists'] = sprintf($this->lang['l_already_exists'],implode(', ', $exists));
                    }

                    $this->page_info['services'] = $services;
                } else {
                    $services_label = sprintf('services:%d__%d', $this->user_id, $this->page_info['panel_version']);
                    $this->mc->delete($services_label);

                    $services = array();
                    foreach ($info['services'] as $service_id) {
                        $service_name = mb_substr($info['name'][$service_id], 0, 64);
                        $service_engine = (isset($info['engine'][$service_id]) && isset($this->platforms[$info['engine'][$service_id]])) ? $info['engine'][$service_id] : 'wordpress';
                        $this->db->run(sprintf(
                            "UPDATE services SET name=%s, engine=%s WHERE service_id=%d AND user_id=%d",
                            $service_name ? $this->stringToDB($service_name) : 'NULL',
                            $this->stringToDB($service_engine),
                            $service_id, $this->user_info['user_id']
                        ));
                        $services[] = array(
                            'id' => $service_id,
                            'hostname' => $info['hostname'][$service_id],
                            'engine' => $service_engine,
                            'name' => $service_name,
                            'auth_key' => $info['auth_key'][$service_id]
                        );
                        $tools->store_servicedata_at_mc($service_id);
                    }
                    $this->page_info['services'] = $services;
                }
            }
            return;
        }

        $this->get_addon('words_stop_list');
        $this->get_addon('server_response_addon');

        $this->page_info['server_response_notice'] = sprintf($this->lang['l_server_response_notice'],
            cfg::server_response_length,
            $this->user_info['email']
        );

        /*
            Функционал фильтрации по ссылкам доступен только ограниченному списку пользователей.
        */
        $show_allow_links_ids = array(1,14272);
        $this->page_info['show_allow_links'] = (in_array($this->user_id, $show_allow_links_ids) || $this->staff_mode) ? true : false;

        $action = isset($_GET['action']) ? $_GET['action'] : null;

        $rows = $this->db->select(sprintf("select service_id, name, hostname, engine from services where user_id = %d AND product_id = %d order by hostname;", $this->user_id, cfg::product_antispam), true);
        if (!count($rows)) $rows = $this->db->select(sprintf("select service_id, name, hostname, engine from services where user_id = %d AND product_id is null order by hostname;", $this->user_id), true);

        $service_id = null;
        if (isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
            $service_id = $_REQUEST['service_id'];
        } else {
            if ($action && $action != 'new') {
                foreach ($rows as $v) {
                    if (!$service_id) {
                        $service_id = $v['service_id'];
                    }
                }
            }
        }

        $service = null;
        $this->page_info['grantwrite'] = 1;
        if ($service_id) {
            $this->page_info['send_log_to_email_hint'] = sprintf($this->lang['l_send_log_to_email_hint'], $this->user_info['email']);
            // Проверяем входит ли сайт в список делегированных
            $service_granted = $this->db->select(sprintf("select service_id, grantwrite 
                                                          from services_grants
                                                          where service_id = %d
                                                          and user_id_granted = %d",
                                                          $service_id,
                                                          $this->user_id));
            if ($service_id == $service_granted['service_id']){
                $service = $this->db->select(sprintf("select service_id, name, hostname, engine, stop_list_enable, sms_test_enable, auth_key, response_lang, created, updated, allow_links_enable, move_to_spam_enable, send_log_to_email, offtop_enable, server_response, logging_restriction, auto_update_app from services where service_id = %d;", $service_id));
                $this->page_info['grantwrite'] = $service_granted['grantwrite'];
                // Признак что сайт - делегированный
                $this->page_info['main_user_site'] = 0;
            }
            else {
                $service = $this->db->select(sprintf("select service_id, name, hostname, engine, stop_list_enable, sms_test_enable, auth_key, response_lang, created, updated, allow_links_enable, move_to_spam_enable, send_log_to_email, offtop_enable, server_response, logging_restriction, auto_update_app from services where service_id = %d and user_id = %d;", $service_id, $this->user_id));
                $this->page_info['main_user_site'] = 1;
            }

            // Выбираем список делегированных сайтов

            $granted_services = $this->get_granted_services($this->user_id);

            $this->page_info['granted_services'] = $granted_services;
        }

        if (!isset($service['service_id']) && !($action == 'new' || $action == 'deleted')) {
			sleep(1);
            $this->url_redirect('');
            return false;
        }

        foreach ($rows as $k => $v) {
            $services[$v['service_id']]['service_name'] = $this->get_service_visible_name($v);
        }

        if ($service && count($services) > 1) {
            $this->page_info['services'] = $services;
        }

        if (isset($_COOKIE['service_updated']) && $_COOKIE['service_updated'] == 1) {
            setcookie('service_updated', 0, null);
        }

        $hostname = '';
        $hostname_created = ' ';
        $name = '';
        if (isset($service['hostname'])){
            $hostname = sprintf($this->lang['l_for_site'], $service['hostname']);
            $hostname_created = ' ' . $service['hostname'] . ' ';
        }
        if (isset($service['name'])) {
            $name = sprintf($this->lang['l_service_name'], $service['name']);
        }

        $check_trial = true;
        $check_readwrite_access = false;
        switch ($action) {
            case 'edit';
                $this->page_info['update_service'] = true;
                break;
            case 'new':
                $this->page_info['platforms'] = $this->db->select("SELECT p.platform_id, p.engine, p.info, ps.services_count FROM platforms p LEFT JOIN platforms_stat ps ON p.platform_id=ps.platform_id WHERE type='CMS' AND p.platform_id NOT IN (9,10) ORDER BY services_count DESC;",true);
                if ($this->show_site_offer) {
                    $this->page_info['services_overlimit'] = sprintf($this->lang['l_services_overlimit'], $this->user_info['services'], $this->user_info['tariff']['services']);
                    $offer_tariff_id = $this->show_upgrade_tariffs(true);

                    $discount = $this->get_upgrade_discount($this->user_id, $offer_tariff_id);
                    $this->show_offer(null, $offer_tariff_id, null, $discount);
                    $this->page_info['l_upgrade_discount_title'] = mb_strtolower($this->page_info['l_upgrade_discount_title']);

                    $this->page_info['websites'] = $this->get_websites($this->user_id);
                    $this->page_info['pay_button'] = strtoupper($this->lang['l_upgrade_antispam']);
                    $this->page_info['offer_tariff_id_param'] = sprintf("?tariff_id=%d", $offer_tariff_id);
                    $this->page_info['offer_tariff_id_param'] .= '&utm_source=cleantalk.org&amp;utm_medium=upgrade_banner_button&amp;utm_campaign=control_panel';
                    $this->page_info['choose_payment_service'] = true;

                    // Готовим параметры для оплаты с банера
                    $this->fill_pay_method_params();

                    if ($this->options['show_currencies']) {
                        $this->get_currencies();
                    }

                    $this->page_info['second_top_button'] = false;
                    $this->page_info['hide_charge_form'] = true;
                    $this->page_info['hide_charge_form'] = true;
                    $this->page_info['hide_money_back'] = true;

                    return false;
                }
                $this->page_info['update_service'] = true;
                $check_readwrite_access = true;
                break;
            case 'delete':
                $this->page_info['delete_service'] = sprintf($this->lang['l_service_delete'], $service_id, $name, $hostname);
                $check_trial = false;
                $check_readwrite_access = true;
                break;
            case 'created':
                $this->page_info['service_updated'] = sprintf($this->lang['l_service_created'], $hostname_created, $service_id, $name, $service['auth_key']);
                $manual_url = sprintf('<a href="/install?platform=%s" target="_blank" class="alert-link">%s</a>',
                    $service['engine'],
                    $this->lang['l_setup_key2']
                );
                if ($service['engine'] == 'php') {
                    $manual_url = sprintf('<a href="/register?show_manual=1&platform=%s&service_id=%d" target="_blank" class="alert-link">%s</a>',
                        $service['engine'],
                        $service['service_id'],
                        $this->lang['l_setup_key2']
                    );
                }
                $this->page_info['setup_key'] = sprintf($this->lang['l_setup_key'], $this->platforms[$service['engine']], $manual_url);
                break;
            case 'deleted':
                $this->page_info['service_updated'] = sprintf($this->lang['l_service_deleted']);
                $this->page_info['is_deleted'] = true;
                $this->page_info['header']['refresh'] = '3; /my';
                $check_trial = false;
                break;
            default: break;
        }

        if ($check_trial) {
            $this->check_trial();
        }

        //
        // Проверку прав на полноценный доступ делаем только для оплаченных акаунтов.
        //
        if ($check_readwrite_access && $this->user_info['trial'] != 0) {
            $check_readwrite_access = false;
        }
        /*
            Авторизованных по токену отправляем на форму авторизации.
        */
        if ($check_readwrite_access || count($_POST)) {
            $this->switch_to_password_auth();
        }

        $this->page_info['button_label'] = $this->lang['l_create'];
        if (!isset($service)) {
            $service['stop_list_enable'] = 0;
            $service['sms_test_enable'] = 0;
            $service['allow_links_enable'] = 1;
            $service['move_to_spam_enable'] = 4;
            //$service['move_to_spam_enable'] = 1;
            $service['offtop_enable'] = -1;
            $service['engine'] = 'wordpress';
            if (isset($_GET['platform']) && $_GET['platform'] == 'wordpress'){
                $service['show_move_to_spam'] = true;
                $service['auto_update_app'] = 1;
                
            }
            if (isset($_GET['hostname']) )
                $service['hostname'] = $_GET['hostname'];

            $service['response_lang'] = $this->user_info['lang'];
            $service['logging_restriction'] = 0;
        } else {
            if ($service['engine'] == 'wordpress')
                $service['show_move_to_spam'] = true;

            if (isset($service['service_id'])) {
                $this->page_info['head']['title'] = $this->lang['l_site_options'] . $service['service_id'];
                $this->page_info['stop_list_enable_hint'] = sprintf($this->lang['l_stop_list_enable_hint'], $service['service_id']);
            }

            $this->page_info['button_label'] = $this->lang['l_save'];
			
			$this->show_review_notice(1);

			if (isset($this->review_links[$service['engine']])) {
				$service['rate_url'] = $this->review_links[$service['engine']];
			}
        }
		$this->page_info['info'] = $service;
		$this->page_info['delete_service'] = sprintf($this->lang['l_service_delete_q'], $this->get_service_visible_name($service)); 

        // Если есть разрешение на запись у делегированного сайта
        // или это сайт основного пользователя
        if ((count($_POST) || (count($_GET) && $_GET['action'] == 'delete')) && $this->page_info['grantwrite']) {
            $tools = new CleanTalkTools();
            $info = $this->safe_vars(count($_POST) ? $_POST : $_GET);

            // Преобразуем значение переключателей в цифровое значение
            if ($service) {
                foreach ($service as $k => $v){
                    if (isset($service['service_id'])) {
                        if (preg_match("/_enable$/", $k) && $k != 'move_to_spam_enable') {
                            if (isset($info[$k])) {
                                if ($info[$k] === "on" || $info[$k] == 1) {
                                    $info[$k] = 1;
                                }elseif ($info[$k] == '-1') {
                                    $info[$k] = -1;
                                } else {
                                    $info[$k] = 0;
                                }
                            }
                        } else {
                            if (!isset($info[$k]))
                                $info[$k] = null;
                        }
                        if (preg_match("/_enable_all$/", $k)) {
                            $info[$k] = true;
                        }
                    } else {
                        if (preg_match("/_enable$/", $k)) {
                            $info[$k] = $service[$k];
                        }
                    }
                }
            }

            // Урезаем длинну строк до значений поддерживаемых базой
            $info['name'] = mb_substr(isset($info['name']) ? $info['name'] : '', 0, 64);
			$info['hostname'] = mb_substr(isset($info['hostname']) ? $info['hostname'] : '', 0, 1024);
            if (isset($info['server_response']) && isset($info['server_response_enable']) && isset($_POST['server_response'])) {
                if ($info['server_response'] == '') {
                    $info['server_response'] = null;
                } else {
//                    $info['server_response'] = mb_substr($info['server_response'], 0, cfg::server_response_length);
					$server_response = str_replace("'", '&rsquo;', $_POST['server_response']);
					$server_response = strip_tags($server_response, '<a><p><br><br />');
					$info['server_response'] = mb_substr($server_response, 0, cfg::server_response_length);
                }
            } else {
                $info['server_response'] = null;
            }

            $info['send_log_to_email'] = isset($info['send_log_to_email']) ? 1 : 0;

            if (isset($info['engine']) && !isset($this->platforms[$info['engine']]))
                $info['engine'] = 'wordpress';

            if (!isset($info['response_lang']))
                $info['response_lang'] = $this->user_info['lang'];

            if (!isset($this->response_langs[$info['response_lang']]))
                $info['response_lang'] = 'en';

            if ($info['hostname'] == '' || $info['hostname'] === false) {
                $info['hostname'] = null;
            } else {
                $hostname = $tools->get_domain($info['hostname']);
                if ($hostname === null)
                    $info['hostname'] = null;
                else
                    $info['hostname'] = $hostname;
            }

            if ($info['hostname'] && function_exists('idn_to_utf8')) {
                $info['hostname'] = idn_to_utf8($info['hostname']);
            }

            if ($info['name'] == '' || $info['name'] == false)
                $info['name'] = null;

            if (isset($info['engine']) && $info['engine'] == 'wordpress')
                $info['show_move_to_spam'] = true;

            $skip_offtop = 1;
            switch (@$info['offtop_enable']) {
                case 1: $skip_offtop = 0;
            }

            // Удаляем кеш сервисов в памяти, дабы вновь добавленный/измененный сайт отобразился в панели
            $app_label = '';
            if ($this->app_mode)
                $app_label = 'app';

            $services_label = sprintf('services:%d_%s_%d', $this->user_id, $app_label, $this->page_info['panel_version']);

            $this->mc->delete($services_label);

            if ($action == 'edit') {
                if ($this->page_info['main_user_site'] == 1)
                    $user_id_add = ' and user_id = '.$this->user_id;
                else
                    $user_id_add = '';

                // Изменения, которые могут быть применены ко всем сервисам
                $props = array('allow_links_enable', 'sms_test_enable', 'move_to_spam_enable', 'stop_list_enable', 'server_response_enable', 'send_log_to_email','logging_restriction','auto_update_app');
                foreach ($props as $prop) {
                    if (isset($info[$prop . '_all'])) {
                        $prop_value = 1;
                        if (!isset($info[$prop]) || !$info[$prop]) $prop_value = 0;
                        if ($prop == 'server_response_enable') {
                            $prop = 'server_response';
                            $prop_value = $this->stringToDB($info['server_response']);
                        }
                        foreach ($services as $k => $v) {
                            if ($k == $service_id) continue;
                            $this->db->run(sprintf('UPDATE services SET %s = %s WHERE service_id = %d', $prop, $prop_value, $k));
                        }
                    }
                }

                if (isset($info['apply_to_all']) && $info['apply_to_all'] == 'on') {
                    foreach ($services as $k => $v) {
                        if ($k == $service_id) continue;
                        $this->db->run(sprintf("UPDATE services SET 
                            engine = %s, response_lang = %s, stop_list_enable = %d, 
                            move_to_spam_enable = %d, offtop_enable = %d, allow_links_enable = %d, send_log_to_email = %d,
                            skip_offtop = %d, server_response = %s, logging_restriction = %d, auto_update_app = %d, updated = now()
                            WHERE service_id = %d",
                            $this->stringToDB($info['engine']),
                            $this->stringToDB($info['response_lang']),
                            @$info['stop_list_enable'],
                            @$info['move_to_spam_enable'],
                            @$info['offtop_enable'],
                            isset($info['allow_links']) ? $info['allow_links'] : 1,
                            $info['send_log_to_email'],
                            $skip_offtop,
                            $this->stringToDB($info['server_response']),
                            isset($info['logging_restriction']) ? 1 : 0,
                            isset($info['auto_update_app']) ? 1 : 0,
                            $k
                        ));
                    }
                }

                $sql = sprintf("update services set name = %s, hostname = %s, engine = %s, response_lang = %s, 
                                        stop_list_enable = %d, move_to_spam_enable = %d,
                                        offtop_enable = %d, allow_links_enable = %d, send_log_to_email = %d, skip_offtop = %d,
                                        server_response = %s, logging_restriction = %d, auto_update_app = %d, updated = now() where service_id = %d%s;",
                            $this->stringToDB($info['name']),
                            $this->stringToDB($info['hostname']),
                            $this->stringToDB($info['engine']),
                            $this->stringToDB($info['response_lang']),
                            @$info['stop_list_enable'],
                            @$info['move_to_spam_enable'],
                            @$info['offtop_enable'],
                            isset($info['allow_links']) ? $info['allow_links'] : 1,
                            $info['send_log_to_email'],
                            $skip_offtop,
                            $this->stringToDB($info['server_response']),
                            isset($info['logging_restriction']) ? 1 : 0,
                            isset($info['auto_update_app']) ? 1 : 0,
                            $service_id,
                            $user_id_add
                    );

                $this->db->run($sql);

                // Обновляем информацию в кешах серверов автоматической модерации
                $tools->store_servicedata_at_mc($service_id);

                $this->post_log(sprintf("Обновлена услуга %s (%d) пользователя %s.", $info['hostname'], $service_id, $this->user_info['email']));
                setcookie('service_updated', 1, null);
                $this->url_redirect('service?service_id=' . $service_id . '&action=' . $action);
            }
            apc_delete('number_sites_'.$this->user_id);
            if ($action == 'new') {
                $auth_key = $this->generatePassword($this->options['auth_key_length'], 4);
                $service_moderate = 0;
                $service_product_id = null;
                if (isset($this->user_info['licenses']) && isset($this->user_info['licenses'][$this->cp_mode])) {
                    $license = $this->user_info['licenses'][$this->cp_mode];
                    $service_product_id = $license['product_id'];
                    $service_moderate = $license['moderate'];
                }
                $sql = sprintf("SELECT service_id FROM services WHERE user_id=%d AND hostname=%s AND product_id=%d AND engine=%s",
                    $this->user_id,
                    $this->stringToDB($info['hostname']),
                    cfg::product_antispam,
                    $this->stringToDB($info['engine'])
                );
                if($this->db->select($sql)){
                    apc_store('antispam_message_'.$this->user_id, $this->stringToDB($info['hostname']));
                    $this->url_redirect('service?action=new');
                    return;
                }
                $service_id = $this->db->run(sprintf("insert into services (user_id, name, hostname, engine, response_lang, stop_list_enable, sms_test_enable, auth_key, created, allow_links_enable, move_to_spam_enable, product_id, moderate_service, auto_update_app) values (%d, %s, %s, %s, %s, %d, %d, %s, now(), %d, %d, %s, %d, %d);",
                    $this->user_id,
                    $this->stringToDB($info['name']),
                    $this->stringToDB($info['hostname']),
                    $this->stringToDB($info['engine']),
                    $this->stringToDB($info['response_lang']),
                    @$info['stop_list_enable'],
                    @$info['sms_test_enable'],
                    $this->stringToDB($auth_key),
                    @$info['allow_links_enable'],
                    @$info['move_to_spam_enable'],
                    $service_product_id ? $service_product_id : 'NULL',
                    $service_moderate,
                    ($info['engine']=='wordpress') ? 1 : -1
                ));
                // Обновляем информацию в кешах серверов автоматической модерации
                $tools->store_servicedata_at_mc($service_id);
				
				apc_delete($this->account_label);

                $this->post_log(sprintf("Добавлена услуга %s (%d) пользователя %s.", $info['hostname'], $service_id, $this->user_info['email']));
                $this->url_redirect('service?service_id=' . $service_id . '&action=created');
            }
            if ($action == 'delete') {
                $this->db->run(sprintf("delete from services where service_id = %d and user_id = %d;", $service_id, $this->user_id));
				apc_delete($this->account_label);

                $this->post_log(sprintf("Удалена услуга %d пользователя %s.", $service_id, $this->user_info['email']));
                $this->url_redirect('service?service_id=' . $service_id . '&action=deleted');
            }
            $this->page_info['info'] = $info;
        }

        ksort($this->response_langs);

        $this->page_info['response_langs'] = $this->response_langs;

        if (isset($service_id)) {
            $connect_info = $this->db->select(sprintf("select * from (select last_seen, inet_ntoa(ip) as ip from users_ips where user_id = %d and service_id = %d order by last_seen desc limit 1) as tmp1, (select agent from requests where user_id = %d and service_id = %d order by datetime desc limit 1) as tmp2;", $this->user_id, $service_id, $this->user_id, $service_id));
            if (isset($connect_info['ip'])){
                $connect_info['hostname'] = gethostbyaddr($connect_info['ip']);
                if ($connect_info['hostname'] == $connect_info['ip'] || $connect_info['hostname'] === false)
                    $connect_info['hostname'] = '';
                else
                    $connect_info['hostname'] = ' (' . $connect_info['hostname'] . ') ';

                $this->page_info['connect_info'] = sprintf($this->lang['l_connect_info'],
                                                $connect_info['agent'], $connect_info['ip'], $connect_info['hostname'], $connect_info['last_seen']);
            }
        } else {
            if (isset($_GET['platform']) && isset($this->platforms[$_GET['platform']]))
                $this->page_info['info']['engine'] = $_GET['platform'];
        }

        $this->page_info['jsf_focus_on_field'] = 'hostname';

        return true;
    }

    /**
      * Функция страницы с баннерами
      *
      * @return bool
      */

	function show_banners(){

		$list = scandir("../banners");
		$i = 0;
		$banners = array();
		foreach ( $list as $entry ){
			if (preg_match("/^(\d+)x(\d+)-(\w+)-cleantalk\.gif$/", $entry, $fields)){
				$banners[$i]['filename'] = $fields[0];
				$banners[$i]['engine'] = $fields[3];
				$banners[$i]['size'] = $fields[1] . 'x' . $fields[2];
				$banners[$i]['format'] = $fields[1] > $fields[1] ? 'row' : 'col';
				$i++;
			}
		}
		$this->page_info['banners'] = &$banners;
		$i = 0;
		return true;
	}

    /**
      * Функция управления почтовыми подписками
      *
      * @return bool
      */

	function show_postman(){
		if(!$this->check_access()){
			$this->url_redirect('session', null, 'new_password');
			return 1;
		}

		if (isset($this->lang['l_newpass_title']))
			$this->page_info['head']['title'] = $this->lang['l_newpass_title'];

		$postman_info = $this->db->select(sprintf('select pi.task_id, pi.title, pi.notice, unix_timestamp(pt.last_sent) as last_sent, pt.enable, pi.period from postman_info pi left outer join postman_tasks pt on pt.task_id = pi.task_id and pt.user_id = %d where pi.manual_control = 1;', $this->user_info['user_id']), true);
		foreach ($postman_info as $key => $value){
			// Делаем коррекцию на временную зону пользователя
			$user_time = $postman_info[$key]['last_sent'];
			if (isset($this->user_info['timezone']) && isset($user_time)){
				$user_time = $user_time - (3600 * (int) cfg::billing_timezone) + (3600 * (int) $this->user_info['timezone']);
				$postman_info[$key]['last_sent'] = date("Y-m-d H:i:s", $user_time);
			}
		}
		$this->page_info['postman_info'] = &$postman_info;

		if (count($_POST)){
			$info = $this->safe_vars($_POST);
			// Перебираем значения в массиве $_POST
			foreach ($postman_info as $task){
				if (isset($_POST['task_id:' . $task['task_id']]))
					$subscribe = 1;
				else
					$subscribe = 0;

				$this->subscribe_task($task['task_id'], $this->user_info['user_id'], $subscribe, $task['period'], $task['title']);
			}

			$this->url_redirect('postman?subscribed=1');

		}
		return true;
	}

    /**
      * Функция управления подпиской
      *
      * @param int $task_id ID задания
      *
      * @param int $user_id ID пользователя
      *
      * @param string $subscribe
      *
      * @param int $period
      *
      * @param string $task_title Заголовок задания
      *
      * @return bool
      */

	function subscribe_task($task_id, $user_id, $subscribe, $period = 1, $task_title = ''){
		if (!isset($task_id) || !isset($user_id) || !isset($subscribe))
			return false;

		// Завершаем работу функции если ключ не равен 0 или 1
		if (!array_key_exists((int) $subscribe, array(0, 1)))
			return false;

		$result = false;
		$task = $this->db->select(sprintf('select count(*) as count, enable from postman_tasks where task_id = %d and user_id = %d;', $task_id, $user_id));
		if ($task['count'] == 1){
			if (!isset($task['enable']) || (isset($task['enable']) && $task['enable'] != $subscribe)){
				$result = $this->db->run(sprintf('update postman_tasks set enable = %d where task_id = %d and user_id = %d;', $subscribe, $task_id, $user_id));

			}
		}
		if ($task['count'] == 0){
			$result = $this->db->run(sprintf('insert into postman_tasks values (%d, %d, %d, null, %d, \'%s\');',
							$task_id, $user_id, $subscribe, $period, md5($task_id . ':' . $user_id)));
		}

		// Если запись была изменена, то записываем об этом в журнал
		if ($result)
			if ($subscribe)
				$this->post_log(sprintf(messages::user_subscribed, $this->user_info['email'], $task_title));
			else
				$this->post_log(sprintf(messages::user_unsubscribed, $this->user_info['email'], $task_title));

		return true;
	}

    /**
      * Страница партнеров сервиса
      *
      * @return bool
      */

	function show_partners(){

        if (isset($this->lang['l_afl_title']))
            $this->page_info['head']['title'] = $this->lang['l_afl_title'];

		$partner['clicks'] = $this->db->select(sprintf('select count(*) as count from partners_clicks where partner_id = %d;', $this->user_info['user_id']));
		$partner['clicks'] = $partner['clicks']['count'];
		$partner['regs'] = $this->db->select(sprintf('select count(*) as count from partners_regs where partner_id = %d;', $this->user_info['user_id']));
		$partner['regs'] = $partner['regs']['count'];
		$row = $this->db->select(sprintf('select balance, balance_usd, first_fee, monthly_fee,first_fee_level_2 from partners where partner_id = %d;', $this->user_info['user_id']));
        $sql = sprintf("select usd_rate from currency where currency = %s;",
                $this->stringToDB('RUB')
        );
        $cur = $this->db->select($sql, true);

        $balance_local = null;
        $balance_local_currency = null;
        if ($this->ct_lang == 'en') {
            if ($row['balance'] > 0) {
                $balance_local = $row['balance'];
                $balance_local_currency = 'RUB';
            }
        }
	    if ($this->ct_lang == 'ru') {
            if ($row['balance_usd'] > 0) {
                $balance_local = $row['balance_usd'];
                $balance_local_currency = 'USD';
            }
        }
        $this->page_info['balance_local'] = number_format($balance_local, 2, '.', ' ');
        $this->page_info['balance_local_currency'] = $balance_local_currency;

        $partner['balance']= $row['balance'];
        if ($this->ct_lang == 'en') {
            $partner['balance']= $row['balance_usd'];
        }

		$partner['first_fee'] = $row['first_fee'] * 100;
		$partner['first_fee_level_2'] = $row['first_fee_level_2'] * 100;
		$partner['monthly_fee'] = $row['monthly_fee'] * 100;
		$partner['pays'] = $this->db->select(sprintf('select count(*) as count from partners_pays where partner_id = %d;', $this->user_info['user_id']));
		$partner['pays'] = $partner['pays']['count'];

		$this->page_info['partner'] = &$partner;
        $this->page_info['bsdesign'] = true;

		$this->page_info['platforms'] = array(
											'phpbb3' => 'phpBB3',
											'joomla15' => 'Joomla',
											'wordpress' => 'WordPress',
											'dle' => 'DataLife Engine'
											);

        $balance = $partner['balance'];
        $this->page_info['partner_balance'] = $balance;
        setcookie('partner_balance', $balance, null);


		/*
			Формируем список партнерских акаунтов
		*/
		$rows = $this->db->select(sprintf("
        select email, u.created, cast(paid_till as date) as paid_till, ui.last_seen from users u left join partners_regs p on p.user_id = u.user_id left join (select user_id, max(last_seen) as last_seen from users_ips group by user_id) ui on ui.user_id = u.user_id where p.partner_id = %d;
        ", $this->user_id), true);

        $current_month = '';
        $regs_by_months = array();

        foreach ($rows as $k => $v){
            $cmonthexpl = explode('-', $v['created']);

            $current_month = date('Y-M', strtotime($cmonthexpl[0].'-'.$cmonthexpl[1].'-15'));

            if (!isset($regs_by_months[$current_month]))
                $regs_by_months[$current_month] = 0;
        }

        $current_month = '';

		foreach ($rows as $k => $v){
			if (!isset($v['email']))
				continue;

			$hidden_email = preg_replace("/^([\w\.\-]+)\@[\w\.\-]+\.([\w\-]+)$/iu", "$1@...$2", $v['email']);

			// Если не удалось выполнить замену
			if (!$hidden_email)
				continue;

			// Заменяем настоящий email его копией без доменного имени
			$v['email'] = $hidden_email;

			if (!isset($v['last_seen']))
				$v['last_seen'] = '-';

			$this->page_info['accounts'][] = $v;

            $cmonthexpl = explode('-', $v['created']);

            $current_month = date('Y-M', strtotime($cmonthexpl[0].'-'.$cmonthexpl[1].'-15'));

            $regs_by_months[$current_month]++;

		}

        $regs_months = array_keys($regs_by_months);

        $first_month = date('Y-M', strtotime("-1 month", strtotime($regs_months[0])));

        $last_month = date('Y-M', strtotime("+1 month", strtotime($regs_months[count($regs_months) - 1])));

        $regs_by_months[$first_month] = 0;

        $regs_by_months = array_merge( array( $first_month => 0 ), $regs_by_months);

        $regs_by_months[$last_month] = 0;

		/*
			Формируем список платежей по партнерскому акаунту
		*/
        $sql = sprintf("select email, cast(p.date as date) as date, p.gross as cost, pp.cost as partner_cost, b.cost_usd, pp.currency from pays p left join users u on u.user_id = p.fk_user left join partners_regs pr on pr.user_id = u.user_id left join partners_pays pp on pp.bill_id = p.fk_bill left join bills b on b.bill_id = p.fk_bill where pr.partner_id = %d",
            $this->user_info['user_id']
        );
//        echo($sql); exit;
//        var_dump($sql,$this->currencyCode);
		$rows = $this->db->select($sql, true);
		$total_sum = 0;
        $chart_pays = array();
//        var_dump($rows);exit;
		foreach ($rows as $k => $v){
			if (!isset($v['email']))
				continue;

			$hidden_email = preg_replace("/^([\w\.\-]+)\@[\w\.\-]+\.([\w\-]+)$/iu", "$1@...$2", $v['email']);

			// Если не удалось выполнить замену
			if (!$hidden_email)
				continue;

			// Заменяем настоящий email его копией без доменного имени
			$v['email'] = $hidden_email;

            $v['date'] = date('d.m.Y',strtotime($v['date']));

            if ($v['cost']) {
                $pay_cost = $v['cost'];
            } else {
                $pay_cost = $v['cost_usd'];
                if ($this->ct_lang == 'ru') {
                    $pay_cost = $v['cost'];
                }
            }

            $chart_pays[date('Y-M',strtotime($v['date']))] = $v['partner_cost'];
			$v['fee'] = number_format(($v['partner_cost'] / $pay_cost) * 100, 0, '.', ' ');

            if ($v['currency'] == $this->currencyCode) {
                $total_sum = $total_sum + $v['partner_cost'];
            } else {
                $currency = $this->currencyCode;
                if ($this->currencyCode == 'USD') {
                    $currency = $v['currency'];
                }
                if (isset($cur[0]['usd_rate'])) {
                    if ($this->currencyCode == 'USD') {
                        $v['partner_cost_local'] = $v['partner_cost'] / $cur[0]['usd_rate'];
                    } else {
                        $v['partner_cost_local'] = $v['partner_cost'] * $cur[0]['usd_rate'];
                    }
                    $v['currency_local'] = $this->currencyCode;
                    $total_sum = $total_sum + $v['partner_cost_local'];
                    $v['partner_cost_local'] = number_format($v['partner_cost_local'], 2, '.', ' ');
                }
//                var_dump($sql, $cur, $currency);
            }

			$this->page_info['partner_pays'][] = $v;
		}
//	exit;
        $this->page_info['total_sum'] = number_format($total_sum, 2, '.', ' ');

        $template_chart = array();
        foreach($regs_by_months as $kreg => $vreg) {
            $template_chart[$kreg]['regs'] = $vreg;
            if (isset($chart_pays[$kreg]))
                $template_chart[$kreg]['pays'] = $chart_pays[$kreg];
            else
                $template_chart[$kreg]['pays'] = 0;
        }

        //print_r($template_chart); exit();

        $this->page_info['template_chart'] = $template_chart;

        $tools = new CleanTalkTools();
        $this->page_info['links'] = $tools->show_partners_links($this->user_id, $this->ct_lang);

        $level_2_part = '';
        $com_info_accounts_part = '';
        if (isset($this->options['partners_min_level_2_users']) && $this->options['partners_min_level_2_users'] > 0
            && isset($partner['first_fee_level_2']) && $partner['first_fee_level_2'] > 0
            ) {

            $com_info_accounts_part = sprintf($this->lang['l_com_info_accounts'],
                $this->options['partners_min_level_2_users']
            );

            $level_2_part = sprintf($this->lang['l_com_info_level_2'],
                $partner['first_fee_level_2'],
                $this->options['partners_min_level_2_users']
            );
        }
        if (isset($this->lang['l_com_info'])) {
            $this->page_info['com_info'] = sprintf($this->lang['l_com_info'],
                $partner['first_fee'],
                $com_info_accounts_part,
                $level_2_part
            );

            if ($partner['monthly_fee'] > 0) {
                $this->page_info['com_info'] .= sprintf($this->lang['l_com_info_sub'], $partner['monthly_fee']);
            }
            $this->page_info['com_info'] .= '.';
        }

        //
        // Логика вывода денег с партнерского счета
        //

        // Удаляем куку, дабы показать основную панель Партнерского раздела
        if (!isset($_GET['need_agree'])) {
            setcookie('agree_transfer', null, null);
            setcookie('balance_negative', null, null);
        }

        $transfer_type = 'ct';
        $this->page_info['transfer_type'] = $transfer_type;
        if (isset($_POST['transfer_type'])) {

            if (preg_match("/^(ct|ym)$/",$_POST['transfer_type'] )) {
                $transfer_type = $_POST['transfer_type'];
            }

            $this->page_info['transfer_error'] = '';
            if ($transfer_type != 'ct' && (!isset($_POST['account']) || !preg_match("/^\w+$/", $_POST['account']))) {
                $this->page_info['transfer_type'] = $transfer_type;
                $this->page_info['transfer_error'] .= $this->lang['l_fill_account'] . '<br />';
                return false;
            }
            $account = null;
            if (isset($_POST['account']) && $transfer_type !== 'ct')
                $account = $_POST['account'];

     		if ((float) $partner['balance'] > 0 && isset($_POST['agree'])){

                $result = true;
                $balance_negative = false;
                // Переводим деньги на текущий счет в сервисе
                if ($transfer_type === 'ct')
                    $result = $this->db->run(sprintf("update users set balance = balance + %.2f where user_id = %d;", (float) $partner['balance'], $this->user_info['user_id']));
                if ($transfer_type === 'ym') {
                    require_once('../YaMoney/lib/YandexMoney.php');
                    $ym = new YandexMoney(cfg::ym_client_id);

                    $resp = $ym->accountInfo(cfg::ym_token);
                    if ($resp->isSuccess() && $resp->getBalance() > $partner['balance']) {
                        $result = $this->transfer_to_ym($account, $partner['balance'], $ym);
                    } else {
                        $balance_negative = true;
                    }
                }

                $balance_hint = null;
                if ($result === true && $balance_negative === false) {
                    if ($transfer_type == 'ct') {
                        $row = $this->db->select(sprintf("select balance from users where user_id = %d;", $this->user_id));

                        if ($this->ct_lang != 'ru') {
                            $row['balance'] = $row['balance'] / cfg::usd_mpc;
                        }
                        $this->page_info['transfer_complete'] = sprintf($this->lang['l_transfer_complete_ct'], $balance, $row['balance']);
                        $balance_hint = $this->lang['l_balance_hint'];
                    } else {
                        $this->page_info['transfer_complete'] = sprintf($this->lang['l_transfer_complete'],
                                                                        $balance,
                                                                        $account,
                                                                        $this->transfers_type[$transfer_type]);
                    }

                    $this->db->run(sprintf("insert into partners_transfers (partner_id, date, cost, direction, account) values(%d, now(), %.2f, '%s', %s);", $this->user_info['user_id'], $partner['balance'], $transfer_type, $this->stringToDB($account)));
                    $this->db->run(sprintf("update partners set balance = 0 where partner_id = %d;", $this->user_info['user_id']));
                } else {
                   if ($balance_negative === true) {
                        $this->page_info['transfer_complete'] = sprintf($this->lang['l_balance_negative']);
                        $title = sprintf('Недостаточно средств для вывода %.2f рублей на Яндекс.деньги. Партнер %s, user_id: %d.', $partner['balance'], $this->user_info['email'], $this->user_id);
                        $this->send_email(cfg::noc_email, $title, $title . '<br />');
                        $this->post_log($title);
                    }

                    if ($result === false)
                        $this->page_info['transfer_complete'] = sprintf($this->lang['l_transfer_error'], $this->resp->getError());
                }

                setcookie('transfer_complete', $this->page_info['transfer_complete'], null);
                setcookie('balance_hint', $balance_hint, null);
                setcookie('balance_negative', (int) $balance_negative, null);

                $this->post_log($this->page_info['transfer_complete']);

                $this->url_redirect('partners?transfer_complete=1');
			} else {
                if ($transfer_type == 'ct')
                    $this->page_info['agree_transfer'] = sprintf($this->lang['l_agree_transfer_ct'], $balance);
                else
                    $this->page_info['agree_transfer'] = sprintf($this->lang['l_agree_transfer'],
                                                                    $balance,
                                                                    $account,
                                                                    $this->transfers_type[$transfer_type]);
                if ($partner['balance'] == 0)
                    $this->page_info['agree_transfer'] = $this->lang['l_empty_balance'];

                setcookie('agree_transfer', $this->page_info['agree_transfer'], null);
                $this->url_redirect('partners?need_agree=1');
            }

            setcookie('account', $account, time() + cfg::partner_account_cookie_store * 86400);
            setcookie('transfer_type', $transfer_type, null);

        }


        return true;
	}

    /**
      * Функция перечисления средст на счет Яндекс.денег
      *
      * @param string $account
      *
      * @param int $amount
      *
      * @param resource $ym
      *
      * @return bool
      */

    public function transfer_to_ym($account, $amount, $ym){

        $comment = 'Выплата по партнерской программе cleantalk.ru.';
        $message = $comment . sprintf(' Пользователю %s (user_id: %d).', $this->user_info['email'], $this->user_id);
        $resp = $ym->requestPaymentP2P(cfg::ym_token, $account, $amount, $message, $comment);

        $requestId = $resp->getRequestId();

        // Платим с кошелька Яндекс.денег, к сожалению перевод P2P с карты Visa не возможен из-за текущей политики Яндекса.
        $this->resp = $ym->processPaymentByWallet(cfg::ym_token, $requestId);

        if (!$this->resp->isSuccess()) {
            $title = sprintf('Ошибка вывода денег на Яндекс.деньги. Партнер %s, user_id: %d.', $this->user_info['email'], $this->user_id);
            $this->send_email(cfg::noc_email, $title, json_encode($this->resp));
            $this->post_log($title);
        }

        return $this->resp->isSuccess();
    }

    /**
      * Функция устанавливает рекоммендуемый к подключению тариф
      *
      * @return void
      */

    private function set_offer_tariff_id() {
        $offer_tariff_id = $this->show_upgrade_tariffs(true);

        if ($offer_tariff_id)
            $this->db->run(sprintf("update users set offer_tariff_id = %d where user_id = %d;", $offer_tariff_id, $this->user_id));

        return null;
    }

    /**
      * Функция проставляет куку с сообщением от сервиса
      * и переходит на указанный адрес
      *
      * @param string $cookiename Имя куки
      *
      * @param string $cookievalue Значение куки
      *
      * @param string $redirect Адрес перенаправления
      *
      * @return void
      */

    public function set_message_cookie($cookiename, $cookievalue, $redirect) {
        setcookie($cookiename, $cookievalue, time() + 24*60*60, '/', $this->cookie_domain);
        header('Location: '.$redirect);
        exit();
    }

    /**
      * Отображения проставленной куки с сообщением
      *
      * @param string $cookiename Имя куки
      *
      * @return void
      */

    public function display_message_cookie($cookiename) {
        if (isset($_COOKIE[$cookiename])) {
            $this->page_info[$cookiename] = $_COOKIE[$cookiename];
            setcookie($cookiename, '', time() - 24*60*60, '/', $this->cookie_domain);
            if ($cookiename == 'wrong_message')
                sleep(3);
        }
    }

    /**
      * Функция возвращает имя сервиса в зависимости от $service_id
      *
      * @param int $service_id ID сайта
      *
      * @return string
      */

    public function get_service_hostname($service_id) {
        $service = $this->db->select(sprintf("select hostname from
                                              services
                                              where service_id = %d",
                                              $service_id));
        if (isset($service['hostname']))
            return $service['hostname'];
        else
            return '#'.$service_id;
    }

}
?>
