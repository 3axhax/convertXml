<?php

require 'smarty/libs/Smarty.class.php';

include 'includes/sql.php';
include 'includes/messages.php';
include "../includes/tools.php";

include "../includes/helpers/database.php";
include "../includes/helpers/utils.php";
include "../includes/helpers/logs.php";

/**
 * Базовый класс раздела /my
 */
class CCS {
    /**
     * @var Database Объект для работы с базой данных
     */
    public $db = null;

    /**
     * @var Utils Вспомогательные методы
     */
    protected $utils = null;

    /**
     * @var Logs Вспомогательные методы для работы с логами
     */
    protected $logs = null;

    /**
     * @var StdClass
     */
    public $link = null;

    public $page_info = array();

    /**
    * @var array $work_tariffs Рабочие тарифы
    */
    public $work_tariffs = null;
    /**
    * @var array $premium_tariffs Премиальные тарифы
    */
    public $premium_tariffs = array();
    /**
    * @var array $personal_tariffs Персональные тарифы
    */
    public $personal_tariffs = null;

    /**
    * @var array $engines Платформы CMS
    */
    public $engines = array(
			'phpbb3' => array('cms_name' => 'phpBB3', 'regs' => true, 'posts' => true, 'sms' => true),
			'joomla15' => array('cms_name' => 'Joomla', 'regs' => true, 'posts' => true, 'sms' => false),
			'wordpress' => array('cms_name' => 'WordPress', 'regs' => false, 'posts' => true, 'sms' => false),
			'dle' => array('cms_name' => 'DataLife Engine', 'regs' => true, 'posts' => true, 'sms' => false),
			'ipboard' => array('cms_name' => 'IP.Board', 'regs' => true, 'posts' => false, 'sms' => false),
			'vbulletin' => array('cms_name' => 'vBulletin', 'regs' => true, 'posts' => false, 'sms' => false),
			'unknown' => array('cms_name' => 'CMS', 'regs' => true, 'posts' => true, 'sms' => false),
		);
    /**
    * @var array $staff Сотрудники компании
    */
    public $staff = array(
            'shagimuratov@cleantalk.ru' => true,
            'aleksandr.razor@gmail.com' => true,
            'znaeff@mail.ru' => true,
            '9820498@gmail.com' => true,
            'poluxster@gmail.com' => true
        );

    /**
    * @var array $platforms Платформы
    */

    public $platforms = null;

    /**
    * @var array $response_langs Массив языков ответа сервера
    */

    public $response_langs = array(
        'en' => 'English - English',
        'fr' => 'French - Français',
        'ru' => 'Russian - Русский',
        'es' => 'Spanish - Español',
        'da' => 'Danish - Dansk',
        'pl' => 'Polish - Polski',
        'de' => 'German - Duitse',
        'it' => 'Italian - Italiano',
        'pt' => 'Portuguese - Português',
        'vi' => 'Vietnamese - tiếng Việt',
    );

    /**
    * @var array $ct_langs Массив языков панелу управления
    */

    public $ct_langs = array(
        'en' => 'English - English',
        'ru' => 'Russian - Русский',
//        'fr' => 'French - Français'
    );

    /**
    * @var array $cp_modes Массив режимов панели управления
    */
    public $cp_modes = array('antispam', 'hosting-antispam', 'api', 'security', 'ssl');

    public $cp_modes_products = array('anti-spam' => 'antispam', 'database_api' => 'api', 'anti-spam-hosting' => 'hosting-antispam');
    public $cp_modes_products_ids = array(
        cfg::product_antispam => 'antispam',
        cfg::product_database_api => 'api',
        cfg::product_hosting_antispam => 'hosting-antispam',
        cfg::product_security => 'security',
        cfg::product_ssl => 'ssl'
    );

    /**
    * @var bool $show_site_offer Признак показывать ли предложения на сайте
    */

    public $show_site_offer = false;

    /**
    * @var string $currencyCode Код валюты строковый
    */

    public $currencyCode = 'USD';

    /**
    * @var resource $memcache Ссылка на $this->mc
    */

    public $memcache = null;

    /**
    * @var array $payment_methods Методы оплаты
    */

    public $payment_methods = null;

    /**
    * @var bool $app_mode Режим работы с приложением
    */

    public $app_mode = false;

    /**
    * @var string $app_return
    */
    public $app_return = null;

    /**
    * @var array $options Опции сервиса из таблицы options
    */
    public $options = null;

    /**
    * @var string $ct_lang Язык приложения
    */
    public $ct_lang = null;

    /**
    * @var int $free_months Количество бесплатных месяцев при оплате по акции
    */
    public $free_months = 0;

    /**
    * @var array $token_user Массив данных пользователя авторизовашегося через токен
    */
    public $token_user = null;

    /**
    * @var string $user_token Токен пользователя
    */
    public $user_token = null;

    /**
    * @var string $row_cur Информация о валюте пользователя
    */

    public $row_cur = null;

    /**
    * @var int $user_id Id пользователя
    */

    public $user_id = null;

    /**
    * @var string $cost_label
    */

    public $cost_label = 'cost_usd';

    /**
    * @var array $apps Массив приложений
    */

    public $apps;

    /**
    * @var bool $is_admin Признак является ли аккаунт админским
    */

    public $is_admin;

    /**
    * @var bool $hoster_mode Признак режима хостера
    */

    public $hoster_mode = false;

    /**
    * @var string $cp_mode
    */

    public $cp_mode = 'antispam';

    public $cp_product_id = 1;

    /**
    * @var string $page_url Строка запроса
    */

    public $page_url = null;

    /**
    * @var bool $renew_account Признак обновления аккаунта
    */

    public $renew_account = false;

    /**
    * @var string $cookie_domain Домен для выставления кук
    */

    public $cookie_domain = null;

    /**
    * @var bool $show_bonuses_count Признак показывать ли количество бонусов
    */

    public $show_bonuses_count = true;

    /**
    * @var bool $no_bonus
    */

    public $no_bonus;

    /**
    * @var bool $staff_mode Признак что в аккаунт вошел сотрудник компании
    */

    public $staff_mode = false;

    /**
    * @var string $account_label
    */

    public $account_label = null;

    /**
    * @var array $free_cleantalk_with_hosting Список хостингов с бесплатным CleanTalk
    */

    private $free_cleantalk_with_hosting = array(
        'en' => array(
            'https://hostname.club/web-hosting/'
        ),
//        'ru' => array(
//            'http://hosterbox.ru/promo/cleantalk2016/'
//        )
    );
    public $user_info = null;
    protected $user_info_reload = false;

    public $countries = array(
        'AF' => 'Afghanistan', 'AX' => 'Aland Islands', 'AL' => 'Albania', 'DZ' => 'Algeria',
        'AS' => 'American Samoa', 'AD' => 'Andorra', 'AO' => 'Angola', 'AI' => 'Anguilla',
        'AQ' => 'Antarctica', 'AG' => 'Antigua And Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia',
        'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
        'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin',
        'BM' => 'Bermuda', 'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia And Herzegovina',
        'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil', 'IO' => 'British Indian Ocean Territory',
        'BN' => 'Brunei Darussalam', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
        'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada',
        'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic',
        'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island', 'CC' => 'Cocos (Keeling) Islands',
        'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo, Democratic Republic',
        'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote D\'Ivoire', 'HR' => 'Croatia',
        'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti',
        'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia',
        'FK' => 'Falkland Islands (Malvinas)', 'FO' => 'Faroe Islands', 'FJ' => 'Fiji', 'FI' => 'Finland',
        'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'TF' => 'French Southern Territories',
        'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana',
        'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe',
        'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard Island & Mcdonald Islands',
        'VA' => 'Holy See (Vatican City State)', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary',
        'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran, Islamic Republic Of',
        'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle Of Man', 'IL' => 'Israel', 'IT' => 'Italy',
        'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
        'KE' => 'Kenya', 'KI' => 'Kiribati', 'KR' => 'Korea', 'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan',
        'LA' => 'Lao People\'s Democratic Republic', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho',
        'LR' => 'Liberia', 'LY' => 'Libyan Arab Jamahiriya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
        'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
        'FM' => 'Micronesia, Federated States Of', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia',
        'ME' => 'Montenegro', 'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique',
        'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands',
        'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand',
        'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
        'PW' => 'Palau', 'PS' => 'Palestinian Territory, Occupied', 'PA' => 'Panama', 'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland',
        'PT' => 'Portugal', 'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania',
        'RU' => 'Russian Federation', 'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena',
        'KN' => 'Saint Kitts And Nevis', 'LC' => 'Saint Lucia', 'MF' => 'Saint Martin',
        'PM' => 'Saint Pierre And Miquelon', 'VC' => 'Saint Vincent And Grenadines', 'WS' => 'Samoa',
        'SM' => 'San Marino', 'ST' => 'Sao Tome And Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore',
        'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia',
        'ZA' => 'South Africa', 'GS' => 'South Georgia And Sandwich Isl.', 'ES' => 'Spain',
        'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard And Jan Mayen',
        'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syrian Arab Republic',
        'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste',
        'TG' => 'Togo', 'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad And Tobago', 'TN' => 'Tunisia',
        'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TC' => 'Turks And Caicos Islands', 'TV' => 'Tuvalu',
        'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom',
        'US' => 'United States', 'UM' => 'United States Outlying Islands', 'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Viet Nam',
        'VG' => 'Virgin Islands, British', 'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis And Futuna',
        'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    );
	
    /**
    * @var array $revew_links Список ссылок на отзывы к плагинам. 
    */
	public $review_links = array();

    protected $notification = false;

    protected $news = false;

    /**
      * Конструктор
      *
      * @return
      */

	function __construct() {
	    $this->utils = new Utils();
		if (!isset($_SESSION))
			session_start();
	}

    /**
      * Зачищаем переменные от иньекций
      *
      * @param $array $in Входящий массив
      *
      * @return array
      */

	function safe_vars($in){
		$out = array();
		foreach ($in as $key => $value) {
		    if (is_array($value)) {
		        $out[$key] = $this->safe_vars($value);
            } else {
                $value = str_replace("'", '&rsquo;', htmlspecialchars(trim($value)));
                $out[$key] = strip_tags($value);
            }
		}

		return $out;
	}

    /**
      * Функция проверки доступа
      *
      * @param bool $is_admin Админский ли аккаунт
      *
      * @param bool $read_only Признак только чтение
      *
      * @return bool
      */
	function check_access($is_admin = false, $read_only = false) {
        $result = false;
        $this->page_info['a_passed'] = 0;
        if ($this->app_mode) {

            if (isset($_POST['app_session_id']) && preg_match("/^[a-f0-9]{32}$/", $_POST['app_session_id'])) {
                $row = $this->db->select(sprintf('select user_id, email from users where app_session_id = %s;',
                    $this->stringToDB($_POST['app_session_id'])));

                if (isset($row['user_id']) && isset($row['email'])) {
                    $result = true;
                    $this->user_info = $this->get_user_info($row['email']);
                    $this->user_id = $row['user_id'];
                    $this->ct_lang = $this->user_info['lang'];
                    $this->get_lang($this->ct_lang, 'main');
                    $this->get_lang($this->ct_lang, $this->link->class);
                }
            }
            $this->app_return['auth'] = (int) $result;
        } else {
            if (isset($_SESSION['user_info']['id']) && $this->check_user_id($_SESSION['user_info']['id'])){
                $this->page_info['a_passed'] = 1;
				$this->page_info['is_auth'] = true;
                $result = true;
            }
        }

        if (!$result && $read_only) {
            if (isset($this->token_user['user_id'])) {
                $this->page_info['a_passed'] = 1;
				$this->page_info['is_auth'] = true;
				$this->page_info['token_auth'] = true;
                $result = true;
             }
        }

        return $result;
	}

	protected function check_authorize() {
	    if (isset($this->user_info['read_only']) && !$this->staff_mode) {
            $this->smarty_template = 'includes/general.html';
            $this->page_info['template']  = 'includes/authorize_need.html';
            $this->display();
            exit;
        }
    }

	protected function check_notification() {
	    if ($notification = $this->db->select(sprintf("SELECT notification_id, message, link, platforms 
                FROM notifications
                WHERE lang = '%s' OR lang = 'BOTH'",
            mb_strtoupper($this->ct_lang)))) {
	        if (isset($_COOKIE['hide_notification']) && $_COOKIE['hide_notification'] == $notification['notification_id']) return;
            $this->notification = array(
                'id' => $notification['notification_id'],
                'message' => $notification['message'],
                'link' => $notification['link']
            );
            if ($notification['platforms']) {
                $platforms = $this->db->select(sprintf("SELECT engine FROM platforms WHERE platform_id IN (%s)", $notification['platforms']), true);
                $this->notification['platforms'] = array();
                foreach ($platforms as $platform) {
                    $this->notification['platforms'][] = $platform['engine'];
                }
                $row = $this->db->select(sprintf("SELECT COUNT(*) AS services FROM services WHERE user_id=%d AND engine IN ('%s')",
                    $this->user_info['user_id'], implode("', '", $this->notification['platforms'])));
                if ($row['services'] > 0) {
                    $this->page_info['notification'] = $this->notification;
                }
            } else {
                $this->page_info['notification'] = $this->notification;
            }
        }
    }

    protected function check_news() {
        if (!$this->news = apc_fetch('news')) {
            $platforms = array();
            $rows = $this->db->select("SELECT platform_id, engine FROM platforms WHERE with_app=1", true);
            foreach ($rows as $row) {
                $platforms[$row['platform_id']] = $row['engine'];
            }
            $news = $this->db->select(sprintf("SELECT * FROM news WHERE created > %s AND (lang = '%s' OR lang = 'BOTH') ORDER BY created DESC",
                $this->stringToDB(date('Y-m-d 00:00:00', time() - 365 * 86400)),
                mb_strtoupper($this->ct_lang)), true);
            foreach ($news as &$v) {
                if ($v['platforms']) {
                    $p = explode(',', $v['platforms']);
                    $v['platforms'] = array();
                    foreach ($p as $platform_id) {
                        $v['platforms'][] = $platforms[$platform_id];
                    }
                }
                if ($v['products']) {
                    $p = explode(',', $v['products']);
                    $v['products'] = array();
                    foreach ($p as $product_id) {
                        switch ($product_id) {
                            case cfg::product_antispam:
                                $v['products'][] = 'antispam';
                                $v['products'][] = 'anti-spam';
                                break;
                            case cfg::product_database_api:
                                $v['products'][] = 'api';
                                $v['products'][] = 'database_api';
                                break;
                            case cfg::product_hosting_antispam:
                                $v['products'][] = 'hosting-antispam';
                                break;
                            case cfg::product_security:
                                $v['products'][] = 'security';
                                break;
                        }
                    }
                }
                $v['created_ts'] = strtotime($v['created']);
            }
            apc_store('news', $news, 15 * 60);
            $this->news = $news;
        }
        $user_news = apc_fetch('news_' . $this->user_info['user_id']);
        if (!isset($user_news)) {
            $user_news = array();
            $user_last_news = array();
            $user_ts = strtotime($this->user_info['created']);
            $offs = array();
            $rows = $this->db->select(sprintf("SELECT news_id FROM news_views WHERE user_id=%d", $this->user_info['user_id']), true);
            foreach ($rows as $row) {
                $offs[$row['news_id']] = true;
            }
            $services = array();
            $rows = $this->db->select(sprintf("SELECT engine FROM services WHERE user_id=%d", $this->user_info['user_id']), true);
            foreach ($rows as $row) {
                $services[$row['engine']] = true;
            }
            foreach ($this->news as $news) {
                $show = true;
                // Если новость для определённых продуктов
                if ($news['products']) {
                    if (!isset($this->user_info['licenses']) && !in_array('antispam', $news['products'])) {
                        $show = false;
                    } else if (isset($this->user_info['licenses'])) {
                        $show = false;
                        foreach ($news['products'] as $p) {
                            if (isset($this->user_info['licenses'][$p])) {
                                if (!is_null($news['bill'])) {
                                    if ($news['bill'] === '1' && !$this->user_info['licenses'][$p]['trial']) {
                                        $show = true;
                                    } else if ($news['bill'] === '0' && $this->user_info['licenses'][$p]['trial']) {
                                        $show = true;
                                    }
                                } else {
                                    $show = true;
                                }
                                break;
                            }
                        }
                    }
                } else if (!is_null($news['bill'])) {
                    if (isset($this->user_info['licenses'])) {
                        $show = false;
                        foreach($this->user_info['licenses'] as $l) {
                            if ($news['bill'] === '1' && !isset($l['trial']) && isset($l['moderate'])) {
                                $show = true;
                            } else if ($news['bill'] === '0' && isset($l['trial']) && isset($l['moderate'])) {
                                $show = true;
                            }
                        }
                    } else {
                        $show = false;
                        if ($news['bill'] === '1' && !$this->user_info['trial'] && $this->user_info['moderate']) {
                            $show = true;
                        } else if ($news['bill'] === '0' && $this->user_info['trial'] && $this->user_info['moderate']) {
                            $show = true;
                        }
                    }
                }
                // Если определены cms
                if ($news['platforms']) {
                    $show = false;
                    foreach ($news['platforms'] as $p) {
                        if (isset($services[$p])) {
                            $show = true;
                            break;
                        }
                    }
                }
                // Проверка даты
                if ($news['created_ts'] < $user_ts) {
                    $show = false;
                }

                if ($show && !isset($offs[$news['id']])) {
                    $user_news[] = $news;
                } else if ($show && count($user_last_news) < 3) {
                    $user_last_news[] = $news;
                }
            }

            apc_store('news_' . $this->user_info['user_id'], $user_news, 10 * 60);
        }
        if (count($user_news)) {
            $this->page_info['news'] = $user_news;
        } else if (count($user_last_news)) {
            $this->page_info['news'] = $user_last_news;
            $this->page_info['news_readonly'] = true;
        }
    }

    /**
      * Записываем в журнал системы сообщение
      *
      * @param string $message Сообщение для записи
      *
      * @return bool
      */
	function post_log($message) {
		$user_id = isset($this->user_info['user_id']) ? $this->user_info['user_id'] : -1;
		$time = time();
		if ($this->db->run(sprintf(sql::insert_log, $time, $message, $this->remote_addr))) {
			return 1;
		} else {
			return 0;
		}
	}

    /**
      * Var dump
      *
      * @param string $title
      *
      * @param bool $var
      *
      * @param array $info
      *
      * @return bool
      */

	function var_dump($title = "", $var = false, $info = false){
		if ( !cfg::debug ){
			return 0;
		}
		if ( is_array($var) ){
			$var = implode(" ",$var);
		}
		$dump = array ('title' => &$title, 'var' => &$var);
		if ( is_array($info) ){
			$info['var_dump'][] = $dump;
		}else{
			$this->page_info['var_dump'][] = $dump;
		}
		return 1;
	}

	/**
      * Инициализация класса
      *
      * @param bool $start
      *
      * @return bool
      */
	function ccs_init($start = false) {
	    $this->db = new Database(array(
	        'db_host' => local_cfg::db_host,
            'db_username' => local_cfg::db_username,
            'db_password' => local_cfg::db_password,
            'db_name' => local_cfg::db_name,
            'email' => 'abalakov@cleantalk.org'
        ), $this->page_info, cfg::debug);

		$uri_prefix = cfg::uri_prefix;
		$uri = $_SERVER['REQUEST_URI'];
		$uri = preg_replace("/(\?back_url=.+)$/", '', $uri);
		$c_uri = $uri;
		if (strpos($c_uri, '?') !== false) {
		    $c_uri = explode('?', $c_uri);
		    $c_uri = $c_uri[0];
        }
		if (preg_match("/$uri_prefix\/([a-z0-9_\/\.\-]+)(\?*[a-z0-9_\-=\/\\\'\"\%\&\;\:\.\@]*)$/i", $c_uri, $matches)){

            $this->page_url = addslashes($matches[1]);

            $template = $this->page_url . '.html';

            $this->link = (object) $this->db->select(sprintf(sql::get_pi_template, $template));
			if (isset($this->link->id)) {
				$page_id = $this->link->id;
			}
		} else {
			$page_id = (isset($_GET['page']) && preg_match("/^(\d+)$/", $_GET['page'])) ? $_GET['page'] : 2;
            $this->link = (object) $this->db->select(sprintf(sql::get_page_info, $page_id));
		}

		if (!isset($this->link->id)){
			$this->link = new stdClass();
			$this->link->id = null;
			$this->link->url = null;
			$this->link->class= null;
			$this->link->name = null;
			$this->link->template = cfg::pagenotfound_tpl;
		}

		$this->mc = new Memcache;
		$this->mc->addServer(cfg::memcache_host, cfg::memcache_port);
		$stats = @$this->mc->getExtendedStats();
		$this->mc_online = (bool) $stats[cfg::memcache_host . ":" . cfg::memcache_port];

		if (!$start){
			$this->smarty = new Smarty();
			$this->smarty_template = 'index.html';
			$this->smarty->debugging = false;
			$this->smarty->error_reporting = false;

			$this->page_info["page_id"] = isset($page_id) ? $page_id : null;
			$this->show_main_menu = true;
			$this->debug = cfg::debug;

            // Формируем список опций из БД
            $options_label = 'cleantalk_options';
            $this->options = apc_fetch($options_label);
            if (!$this->options) {
                $options_sql = $this->db->select("select name, value from options;", true);
                foreach ($options_sql as $k => $v) {
                    $this->options[$v['name']] = $v['value'];
                }
                apc_store($options_label, $this->options, cfg::apc_cache_lifetime);
            }

            $tools = new CleanTalkTools();
			$this->remote_addr = $tools->get_remote();
            #
            # Устанавливаем куку на поддомены
            #
//            $this->cookie_domain = $tools->get_cookie_domain();
            if ($this->cookie_domain) {
                $this->page_info['cookie_domain'] = $this->cookie_domain;
            }

            //
            // Чистим куки "другого" формата
            //
//            $tools->clean_old_cookies($this->cookie_domain);

			$this->page_info['panel_version'] = $this->options['default_panel_version'];
//			var_dump($this->options);exit;
            if (isset($_GET['app_mode']) && $_GET['app_mode'] == 1) {
                $this->app_mode = true;
                if (cfg::debug_app) {
                    error_log(print_r($_GET, true));
                    error_log(print_r($_POST, true));
                }
                 return true;
            }

            if (isset($_COOKIE['cp_mode'])) {
                $this->cp_mode = $_COOKIE['cp_mode'];
            }
            if (isset($_GET['cp_mode'])) {
                $this->cp_mode = $_GET['cp_mode'];
                $this->user_info_reload = true;
            }
            if (isset($_GET['product_id']) && isset($this->cp_modes_products_ids[$_GET['product_id']])) {
                $this->cp_mode = $this->cp_modes_products_ids[$_GET['product_id']];
                $this->user_info_reload = true;
            }
            switch ($this->cp_mode) {
                case 'antispam':
                case 'anti-spam':
                    $this->cp_product_id = cfg::product_antispam;
                    break;
                case 'anti-spam-hosting':
                case 'hosting-antispam':
                    $this->cp_product_id = cfg::product_hosting_antispam;
                    break;
                case 'api':
                case 'database_api':
                    $this->cp_product_id = cfg::product_database_api;
                    break;
                case 'security':
                    $this->cp_product_id = cfg::product_security;
                    break;
                case 'ssl':
                    $this->cp_product_id = cfg::product_ssl;
                    break;
            }

            $user_email = null;
			if (isset($_SESSION['user_info']['id']) && $this->check_user_id($_SESSION['user_info']['id'])){
                $user_email = $_SESSION['user_info']['email'];
            }
            if (isset($_GET['staff_mode']) && $_GET['staff_mode'] == 1) {
                $this->staff_mode = true;
                setcookie('staff_mode', $_GET['staff_mode'], null, '/', $this->cookie_domain);
            } else if (isset($_COOKIE['staff_mode']) && $_COOKIE['staff_mode'] == 1) {
                $this->staff_mode = true;
            }
            if ($this->staff_mode) $this->page_info['read_only'] = false;

            $token_auth = false;
			$this->get_user_by_token();
            if (isset($this->token_user['email'])) {
                $user_email = $this->token_user['email'];
                $token_auth = true;
            }

			if ($user_email){
				$this->user_info = $this->get_user_info($user_email);
				$this->user_id = $this->user_info['user_id'];
				$this->page_info['user_id'] = $this->user_id;
                $this->page_info['user_info'] = $this->user_info;
                if (isset($this->user_info['read_only'])) $this->page_info['read_only'] = true;

                // Разрешаем смену пароля
                $this->set_session_auth_params($this->user_info, true);

                // Скрываем адрес пользователя для безопасности
                if ($token_auth) {
                    $this->page_info['user_info']['email'] = $this->obfuscate_email($user_email);

                    if ($this->user_token && !isset($_COOKIE['user_token'])) {
                        $this->post_log(sprintf("Пользователь %s (%d) авторизовался по токену %s.", $user_email, $this->user_id, $this->user_token));
                    }
                }

                if (isset($this->user_info['tariff']) && $this->user_info['services'] >= $this->user_info['tariff']['services'] && $this->user_info['first_pay_id'] !== null)
                    $this->show_site_offer = true;

                if ($this->user_info['first_pay_id'] === null && $this->user_info['services'] >= cfg::free_services && $this->user_info['trial'])
                    $this->show_site_offer = true;
                if (isset($this->user_info['tariff']) && $this->show_site_offer && $this->user_info['tariff']['services'] == 0)
                    $this->show_site_offer = false;
			} else {
				$this->page_info['user_info']['user_id'] = -1;
			}

            $this->is_admin = $this->get_admin_status();
            $this->page_info['is_admin'] = $this->is_admin;

            // Кука для сотрудников

            if ($this->is_admin)
              {

                if (!isset($_COOKIE['ct_cmnd']))
                  setcookie('ct_cmnd', 1, time() + 24*60*60, '/');
              }
            if ($this->is_admin || !$this->user_id) {
//                $this->ct_langs['fr'] = 'French - Français';
            }

            if (isset($_COOKIE['ct_lang']) && (array_key_exists($_COOKIE['ct_lang'], $this->ct_langs))){
				$this->ct_lang = $_COOKIE['ct_lang'];
            } else {
                if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && preg_match("/^(\w{2})/", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches)) {
                    $language = $matches[1];
                    $this->ct_lang = $language;
                }
            }

            if (isset($_GET['lang']) && (array_key_exists($_GET['lang'], $this->ct_langs))){
				$this->ct_lang = $_GET['lang'];
                $app_label = '';
                if ($this->app_mode)
                    $app_label = 'app';
                $services_label = sprintf('services:%d_%s_%d', $this->user_id, $app_label, $this->page_info['panel_version']);
                $services = $this->mc->delete($services_label);
                unset($app_label);
            }
            if (!$this->ct_lang || !array_key_exists($this->ct_lang, $this->ct_langs)) {
                $this->ct_lang = 'en';
            }

			if ($this->ct_lang && ((isset($_COOKIE['ct_lang']) && $_COOKIE['ct_lang'] != $this->ct_lang) || !isset($_COOKIE['ct_lang']))) {
				setcookie('ct_lang', $this->ct_lang, time() + 3600 * 24, '/', $this->cookie_domain);
			}

            if ($this->cp_mode) {
                if (in_array($this->cp_mode, $this->cp_modes)) {
                    $this->page_info['cp_mode'] = $this->cp_mode;
                    setcookie('cp_mode', $this->cp_mode, strtotime("+365 day"), '/', $this->cookie_domain);
                } else if (isset($this->cp_modes_products[$this->cp_mode])) {
                    $this->cp_mode = $this->cp_modes_products[$this->cp_mode];
                    $this->page_info['cp_mode'] = $this->cp_mode;
                    setcookie('cp_mode', $this->cp_mode, strtotime("+365 day"), '/', $this->cookie_domain);
                }
            }

            if ($this->cp_mode == 'hosting-antispam') {
				$this->ct_lang = 'en';
                $this->page_info['show_affiliate_program'] = false;
            }

            $this->payment_methods = $tools->get_payment_methods($this->ct_lang);

            if ($this->ct_lang === 'ru') {
                $this->currencyCode = 'RUB';
                $this->cost_label = 'cost';
            }

			// Присоединяем файл язовых меток
            $this->get_lang($this->ct_lang, 'main');
            $this->get_lang($this->ct_lang, $this->link->class);

            if (isset($this->user_info['google_auth_secret']) && $this->user_info['google_auth_secret'] != '' && !$this->app_mode && !$this->user_token) {
                $useremailkey = md5('ga2f'.str_replace(array('.','@','_','-'),'',$user_email).'f2ag');
                if (isset($_COOKIE['gaath']) && ($_COOKIE['gaath'] == $useremailkey)){

                } else {
                    if (isset($_GET['checkmy'])) {
                        require_once('GoogleAuthenticator.php');
                        $ga = new PHPGangsta_GoogleAuthenticator();
                        $checkResult = $ga->verifyCode($this->user_info['google_auth_secret'], preg_replace('/[^0-9]/i', '', $_GET['checkmy']), 2);
                        if ($checkResult){
                            setcookie('gaath', $useremailkey, time() + 7 * 24 * 60 * 60, '/', $this->cookie_domain);
                            if (isset($_GET['r'])) {
                                header('Location: ' . $_GET['r']);
                            } else {
                                header('Location: /my');
                            }
                            exit();
                        }
                    }
                    if ($this->user_token) {
                        $uri = preg_replace('/&user_token=[A-Za-z0-9]+/', '', $_SERVER['REQUEST_URI']);
                        $this->page_info['ga_redirect'] = $uri;
                    }
                    $this->page_info['ga_lang'] = $this->ct_lang;
                    $this->page_info['ct_lang'] = $this->ct_lang;
                    $this->page_info['ct_langs'] = $this->ct_langs;
                    $this->display('ga2f.html');
                    exit();

                }
            }

            $row_cur = null;
            $this->page_info['currency'] = isset($this->user_info['currency']) ? $this->user_info['currency'] : $this->options['default_currency'];

            $user_currency = isset($this->user_info['currency']) ? $this->user_info['currency'] : null;
            if (isset($_COOKIE['currency']) && preg_match("/^[A-Z]{3}$/", $_COOKIE['currency'])) {
                $user_currency = $_COOKIE['currency'];
            }
            if (!$user_currency && isset($this->user_info['country']) && $this->user_info['country']) {
                $sql = sprintf("select c.currency, usd_rate, currency_sign from currency c left join currency_country cc on cc.currency = c.currency where cc.country = %s;", $this->stringToDB($this->user_info['country']));

                $row = $this->db->select($sql);
                if (isset($row['currency'])) {
                    $user_currency = $row['currency'];
                }
            }

            $set_currency = true;
            if ($this->ct_lang == 'ru') {
				$user_currency = $user_currency ? $user_currency : 'RUB';
				if ($this->staff_mode) {
                	$set_currency = false; // Запрещаем автоматически привязывать валюту к акаунту, т.к. механизм назначения валюты "ручной".
				}
            }
			if ($user_currency && $this->options['show_currencies'] && !$this->staff_mode && !$this->user_token) {
				if ($this->user_id){
					if ($this->user_info['currency'] == $user_currency) {
						$set_currency = false;
					}
					$sql = sprintf("select c.currency, usd_rate, currency_sign from currency c where c.currency = %s;", $this->stringToDB($user_currency));
					$row_cur = $this->db->select($sql);
				}
                if (isset($row_cur['currency']) && $set_currency) {
                    $this->db->run(sprintf("update users set currency = %s where user_id = %d;",
                        $this->stringToDB($user_currency),
                        $this->user_id
                    ));

                    // Удаляем информацию об акаунте из кеша.
                    apc_delete($this->account_label);

                    $this->post_log(sprintf("Установлена валюта %s пользовтелю %s (%d).", $user_currency, $this->user_info['email'], $this->user_id));
                }
            }
            /*
                Валюта интерфейса
            */
            if (isset($row_cur['currency'])) {
                $this->page_info['currency'] = $row_cur['currency'];
                $this->row_cur = &$row_cur;
            }

			$rows = $this->db->select(sql::get_tariffs, true);

			foreach($rows as $row){
				if ($row['product_id'] != $this->cp_product_id) {
					continue;
				}

				$this->page_info['tariffs'][$row['tariff_id']] = $row;

                // Пересчитываем стоимость для иностранных пользователей
                $tariff_cost = $row['cost'];
                $l_currency = $this->lang['l_currency'];
                if ($this->ct_lang == 'ru') {
                    $row['l_currency'] =  $this->lang['l_currency'];
                } else {
                    if (isset($row['cost_usd'])) {
					    $tariff_cost = $row['cost_usd'];
                        if (isset($row_cur['usd_rate']) && isset($row_cur['currency_sign'])) {
                            $tariff_cost = $row['cost_usd'] * $row_cur['usd_rate'];
                            $l_currency = $row_cur['currency_sign'];
                        }

                    } else {
                        $tariff_cost = $row['cost'] / cfg::usd_mpc;
                    }
                    $row['l_currency'] =  $l_currency;
                }

                // Убираем дробную часть из цифр с 00 после запятой
                if ((int) $tariff_cost == $tariff_cost) {
                    $tariff_cost = number_format(round($tariff_cost), 0, '.', ' ');
                } else {
                    $tariff_cost = number_format($tariff_cost, 2, '.', ' ');
                }

                $row['tariff_cost'] =  $tariff_cost;

                $period_label = $this->lang['l_month'];
                if ($row['period'] == 365)
                    $period_label = $this->lang['l_year'];

                $multi_label = '';
                if ($row['services'] > 2)
                    $multi_label = $this->lang['l_word_multi'];

                if ($this->ct_lang == 'ru' && $row['services'] > 2)
                    $multi_label = $this->lang['l_word_multi2'];

                if ($this->ct_lang == 'ru' && $row['services'] >= 2 && $row['services'] < 5) {
                    $multi_label = $this->lang['l_word_multi'];
                }

                $row['services_display'] = $row['services'];
                if ($row['services'] == 0) {
                    $multi_label = $this->lang['l_word_multi'];
                    if ($this->ct_lang == 'ru') {
                        $multi_label = $this->lang['l_word_multi2'];
                    }
                    $row['services_display'] = ucfirst($this->lang['l_unlimited']);
                }

                $row['info'] = sprintf($this->lang['l_tariff_info_short'], $row['services_display'], $multi_label, $l_currency, $tariff_cost, $period_label);
                $row['info_charge'] = $row['info'];

                if ($this->ct_lang == 'ru' && $row['services'] == 1 ) {
                    $multi_label = $this->lang['l_word_multi'];
                }
                $row['package_info_short_wo_cost'] = sprintf($this->lang['l_package_info_short_wo_cost'], $row['services_display'], $multi_label);

                if ($row['mpd'] > 0) {
                    $row['mpd_display'] = number_format($row['mpd'], 0, '.', ' ');
                    $row['package_info_short_wo_cost'] .= ", " . sprintf($this->lang['l_package_mpd_info'], $row['mpd_display']);
                }

				$this->tariffs[$row['tariff_id']] = $row;

				$this->page_info['l_tariff_info_' . $row['tariff_id']] = sprintf($this->lang['l_tariff_info'], $row['mpd'], $row['services_display'], $tariff_cost, $l_currency, $period_label);
				if ($this->ct_lang == 'ru')
					$this->page_info['l_tariff_info_' . $row['tariff_id'] . '_switch'] = sprintf($this->lang['l_tariff_info_switch'], $tariff_cost);
				else
					$this->page_info['l_tariff_info_' . $row['tariff_id'] . '_switch'] = sprintf($this->lang['l_tariff_info_switch'], $row['mpd'], $tariff_cost, $l_currency);

			}

            $this->page_info['tariffs'] = $this->tariffs;

            $this->redirect_url = '';
			if (isset($_SERVER['REDIRECT_URL']) && preg_match("/^[a-z\/\-\_0-9]+$/i", $_SERVER['REDIRECT_URL']))
				$this->redirect_url = $_SERVER['REDIRECT_URL'];

			$this->page_info['show_footer'] = true;

			$this->page_info['show_translate'] = $this->ct_lang !== 'ru' ? true : false;

			if ($this->ct_lang != 'ru'){
				$this->page_info['usd_mpc'] = cfg::usd_mpc;
			}

            if (isset($this->user_info['first_pay_id']) && $this->user_info['first_pay_id'] !== null && $this->options['site_feedback_on'])
			    $this->page_info['show_feedback'] = true;

			$this->page_info['show_main_hint'] = 0;
			$this->page_info['show_user_id'] = true;

            $platforms = $this->db->select(sprintf("select platform_id, engine, info, lang from platforms order by info;"), true);
            foreach ($platforms as $p){
                $this->platforms[$p['engine']] = $p['info'];
            }
            if ($this->ct_lang != 'ru'){
                $this->platforms['other'] = "Another CMS";
                $this->platforms['unknown'] = "I don't know CMS name!";
                $this->platforms['bitrix'] = '1C Bitrix';
            }

            $this->work_tariffs = $tools->get_allowed_tariffs();
            $this->premium_tariffs = $tools->get_allowed_tariffs(true);

            $this->personal_tariffs = $tools->get_allowed_tariffs(false, true);
//           var_dump($this->personal_tariffs);
            $this->page_info['pay_button'] = $this->lang['l_pay_service'];

            $this->page_info['template'] = isset($this->link->template) ? $this->link->template : null;

            if (isset($_GET['free_months_key']) && preg_match("/^\w+$/i", $_GET['free_months_key']) && !isset($_COOKIE['free_months_key'])) {
				setcookie('free_months_key', $_GET['free_months_key'], time() + 3600 * 24 * 7, '/', $this->cookie_domain);
            }

            // Включаем аналитику на странице
            $this->page_info['load_counters'] = true;
            $this->page_info['load_yandex_metric'] = false;
            $this->page_info['show_ga'] = true;
            $this->page_info['show_affiliate_program'] = true;
            $this->page_info['show_top_menu'] = true;

            if ($this->user_id && $this->user_info['trial'] == -1 && $this->user_info['moderate'] == 0) {
//                $this->page_info['show_top_menu'] = false;
            }

            $this->page_info['show_currencies'] = $this->options['show_currencies'];
            if ($this->ct_lang == 'ru') {
                $this->page_info['show_currencies'] = false;
            }

            if ($this->user_id && $this->user_info['trial'] == -1) {
                $this->page_info['show_affiliate_program'] = false;
            }

            if (isset($_COOKIE['panel_version']) && $_COOKIE['panel_version'] == 3) {
                $this->page_info['panel_version'] = 3;
            }

            $this->page_info['show_local_translate_footer'] = true;

            $activated_bonuses = isset($this->user_info['activated_bonuses']) ? count($this->user_info['activated_bonuses']) : 0;
            $row = $this->db->select(sprintf("select count(*) as count from bonuses;"));
            if($activated_bonuses < $row['count']) {
                $this->page_info['unused_bonuses'] = true;
            }

            if ($this->cp_mode == 'hosting-antispam') {
                $this->page_info['show_affiliate_program'] = false;
            }

            if (isset($this->user_info['hoster_api_key']) && $this->user_info['hoster_api_key']) {
                $this->page_info['show_dashboard_switcher'] = true;
            }
            $this->renew_account = false;
            if (isset($this->user_info['first_pay_id']) && isset($this->user_info['tariff']) && $this->user_info['first_pay_id'] !== null) {
                if ($this->user_info['tariff']['billing_period'] == 'Year' && $this->user_info['paid_till_ts'] - cfg::pay_days * 86400 < time()) {
                    $this->renew_account = true;
                }
                if ($this->user_info['tariff']['billing_period'] == 'Month' && $this->user_info['paid_till_ts'] - cfg::pay_days_month * 86400 < time()) {
                    $this->renew_account = true;
                }
            }
            if ($this->renew_account && $this->cp_mode == 'antispam') {
                $second_top_button['url'] = '/my/bill/recharge';
                $second_top_button['title'] = $this->lang['l_renew_antispam'];
                $second_top_button['background_color'] = '#49C73B';
                $this->page_info['second_top_button'] = $second_top_button;
#                $this->page_info['show_currencies'] = false;
            }

            //
            // Записываем дату и время последнего посещения Панели управления
            //
            if ($this->user_id && !$this->user_info['my_last_login']) {
                $this->db->run(sprintf('update users_info set my_last_login = now(), ip = \'%s\' where user_id = %d;', $this->remote_addr, $this->user_id));
            }

            ksort($this->ct_langs);
            $this->page_info['ct_langs'] = $this->ct_langs;

            // Показываем верхний блок с ссылками на мобильные приложения пользователям без этих приложений.
            if ($this->user_id) {
                if (!isset($this->user_info['app_device_token']) && !isset($this->user_info['app_sender_id'])) {
                    $this->page_info['show_mobile_apps_top'] = true;
                }
            }

            $this->page_info['money_back_title'] = sprintf($this->lang['l_money_back_title'],
                cfg::money_back_days
            );
			if (isset($this->user_info) && in_array($this->cp_mode, array('antispam', 'security'))) {
                $this->user_info = $this->get_free_bonuses($this->user_info);
                $this->page_info['user_info'] = $this->user_info;
            }


            // Helpers initialization
            $this->logs = new Logs($this->db, $this->user_info);

            // Проверяем наличие уведомлений и новостей
            $this->check_notification();
            $this->check_news();
        }
	}

    /**
      * Функция отображения шаблона
      *
      * @param string $page_template Имя шаблона
      *
      * @return string
      */

	function display($page_template = false){
        if ($this->app_mode && $this->app_return !== null) {
            $return = json_encode($this->app_return);
            echo $return;

            if (cfg::debug_app) {
                error_log(print_r($return, true));
            }

            return null;
        }


		$this->page_info['page_id'] = $this->link->id;

		if (!isset($this->page_info['header']['refresh'])){
			if (isset($this->page_info['jsf_focus_on_field'])){
				if (isset($this->page_info['jsf_on_load']))
					$this->page_info['jsf_on_load'] .= '; ' . sprintf("focus_on_field('%s')", $this->page_info['jsf_focus_on_field']);
				else
					$this->page_info['jsf_on_load'] = sprintf("focus_on_field('%s')", $this->page_info['jsf_focus_on_field']);
			}
		}

		if (!isset($this->page_info['head']['title']) && isset($this->lang['l_page_title']))
			$this->page_info['head']['title'] = $this->lang['l_page_title'];

		if (!isset($this->page_info['head']['meta_description']) && isset($this->lang['l_meta_description']))
			$this->page_info['head']['meta_description'] = sprintf($this->lang['l_meta_description']);

		if (!isset($this->page_info['head']['title'])){
			$title = cfg::title !== '' ? cfg::title : null;
			$title = isset($this->link->name) && isset($title) ? $title . '. ' . $this->link->name : $this->link->name;
			$this->page_info['head']['title'] = $title;
		}
		$this->page_info['mantainer_url'] = cfg::mantainer_url;
		$this->page_info['uri_prefix'] = cfg::uri_prefix;
		$this->page_info['show_po'] = $this->check_access() ? true : false;
		$this->page_info['link_id'] = $this->link->id;
		$this->page_info['hint_off_timeout'] = cfg::hint_off_timeout;
		if (!isset($this->page_info['stripe_public_key'])) $this->page_info['stripe_public_key'] = cfg::stripe_public_key;
		$this->page_info['stripe_enable'] = cfg::stripe_enable;
		$this->page_info['paypal_business_accounts_issue'] = cfg::paypal_business_accounts_issue;
		$this->page_info['twoco_enable'] = cfg::twoco_enable;

        $this->page_info['ct_host'] = 'cleantalk.org';

        /*
            Верхнее меню
        */
        $m_links = array(
            'main' => array(
                'name' => $this->lang['l_home'],
                'active' => false,
                'url' => '/my',
                'is_admin' => false,
            )
        );
        $m_link_template = preg_replace("/\.html$/", "", $this->link->template);

        switch ($this->cp_mode) {
            case 'hosting-antispam':
                $m_links = array_merge($m_links, array(
                    'billing' => array(
                        'name' => $this->lang['l_billing'],
                        'active' => false,
                        'url' => '/my/bill/hosting',
                        'is_admin' => false,
                    ),
                    'stat' => array(
                        //'name' => $this->lang['l_analytics'],
                        'name' => $this->lang['l_stat'],
                        'active' => false,
                        'url' => '/my/stat',
                        'is_admin' => false,
                    ),
                ));
                break;
			case 'api':
                $m_links = array_merge($m_links, array(
                    'stat' => array(
                        'name' => $this->lang['l_analytics'],
                        'active' => true,
                        'url' => '/my/stat',
                        'is_admin' => false
                    )
                ));
				break;
            case 'security':
                $bonuses_available = 0;
                if (isset($this->user_info['license']) && isset($this->user_info['license']['bonuses'])) {
                    $bonuses_available = $this->user_info['license']['bonuses']['available'];
                }
                $m_links = array_merge($m_links, array(
                    'show_requests' => array(
                        'name' => $this->lang['l_log'],
                        'active' => true,
                        'url' => '/my/logs',
                        'is_admin' => false
                    ),
                    'stat' => array(
                        'name' => $this->lang['l_analytics'],
                        'active' => false,
                        'url' => '/my/stat',
                        'is_admin' => false,
                    ),
                    'bonuse' => array(
                        'name' => $this->lang['l_bonuses_title'],
                        'active' => false,
                        'url' => '/my/bonuses',
                        'is_admin' => false,
                        'label' => $bonuses_available
                    )
				));
				if ($bonuses_available == 0) {
					unset($m_links['bonuse']);
				}
                break;
            case 'ssl':
                $bonuses_available = 0;
                break;
            default:
                $show_bonuses_counts = $this->show_bonuses_count;
                $m_links = array_merge($m_links, array(
                    'show_requests' => array(
                        'name' => $this->lang['l_log'],
                        'active' => false,
                        'url' => '/my/show_requests?int=week',
                        'is_admin' => false,
                    ),
                    // Отключил аналитику, т.к. она не информативна на данном этапе. Денис. 14.08.2015.
                    'stat' => array(
                        'name' => $this->lang['l_analytics'],
                        'active' => false,
                        'url' => '/my/stat',
                        'is_admin' => false,
                    ),
                    'bonuses' => array(
                        'name' => $this->lang['l_bonuses_title'],
                        'active' => false,
                        'show_counts' => $show_bonuses_counts,
                        'url' => '/my/bonuses',
                        'is_admin' => false
                    ),
                ));
                break;
        }
        $m_links = array_merge($m_links, array(
            'support' => array(
                'name' => $this->lang['l_support_link'],
                'active' => false,
                'url' => '/my/support',
                'is_admin' => false,
            ),
            'noc' => array(
                'name' => 'NOC',
                'active' => false,
                'url' => '/noc',
                'is_admin' => true,
            ),
        ));
        if (isset($m_links[$m_link_template])) {
            $m_links[$m_link_template]['active'] = true;
		}
		// var_dump($m_links);exit;
//		var_dump($this->user_info);exit;
//		var_dump($this->user_info['licenses']['antispam']['services']);exit;
		if (isset($this->user_info['free_months_avaible']) && $this->user_info['free_months_avaible'] == 0 && $this->user_info['free_months_activated'] == 0) {
            unset($m_links['bonuses']);
        }
        foreach ($m_links as $k => $v) {
            if ($v['is_admin'] && !$this->is_admin) {
                unset($m_links[$k]);
            }
        }
        if (isset($m_links['bonuses']) && !$this->show_bonuses_block($this->user_id) && isset($this->user_info['bonuses_count']) && $this->user_info['bonuses_count'] == 0) {
            unset($m_links['bonuses']);
        }

        $this->page_info['m_links'] = $m_links;

        if (isset($this->page_info['second_top_button']['url']) && !preg_match("/utm_source/", $this->page_info['second_top_button']['url'])) {
            $this->page_info['second_top_button']['url'] .= preg_match("/\?/", $this->page_info['second_top_button']['url']) ? '&' : '?';
            $this->page_info['second_top_button']['url'] .= 'utm_source=cleantalk.org&amp;utm_medium=renew_top_button&amp;utm_campaign=control_panel';
        }

		$page_template ? 0 : $page_template = &$this->smarty_template;
		$this->page_info['ct_lang']	= $this->ct_lang;
		$this->smarty->assign($this->page_info);
		$this->smarty->display($page_template);

        if (cfg::debug) {
            error_log(sprintf("URL: %s, запросов select: %d, update: %d.",
                $_SERVER['REQUEST_URI'],
                $this->db->stats['select'],
                $this->db->stats['update']
            ));
        }
	}

    function show_bonuses_block($user_id = null, $product_id = 1) {
        $show = true;
        if ($user_id) {
            $sql = sprintf("select t.billing_period, t.cost_usd from users_licenses ul left join tariffs t on ul.fk_tariff = t.tariff_id where ul.user_id = %d and t.product_id = %d;",
                $user_id,
                $product_id
            );
            $row = $this->db->select($sql);
            if (isset($row['billing_period']) && $row['billing_period'] !== 'Year') {
                $show = false;
            }
            if (isset($row['cost_usd']) && $row['cost_usd'] >= 45) {
                $show = false;
            }
            if (isset($this->tariffs[$this->user_info['fk_tariff']]['cost_usd']) && $this->tariffs[$this->user_info['fk_tariff']]['cost_usd'] > 45) {
                $show = false;
            }
        }
        return $show;
    }

    /**
      * Функция выдачи на страницу системной ошибки
      *
      * @param string $message Сообщение
      *
      * @param int $sleep Задержка
      *
      * @param string $label
      *
      * @return
      */

	function page_error($message, $sleep = 0, $label = null){
		if (!isset($message))
			return false;

		$label = isset($label) ? $label : 'content_message';
		$this->page_info[$label] = $message;

		$sleep = (isset($sleep)) ? $sleep : cfg::fail_timeout;

		sleep($sleep);

		return true;
	}

    /**
      * Функция отправки email
      *
      * @param string $email Адрес email
      *
      * @param string $title Заголовок
      *
      * @param string $message Текст письма
      *
      * @param string $from Адрес написавшего письмо
      *
      * @param bool $sign
      *
      * @return bool
      */

	function send_email($email, $title, $message, $from = null, $sign = true){

		if (!isset($email))
			return false;

		if (!isset($from))
			$from = cfg::contact_email;

		$headers = 'From: '. $from . "\r\n" .
		'Reply-To: '. $from . "\r\n" .
		'MIME-Version: 1.0' . "\r\n" .
		'Content-type: text/html; charset=UTF-8' . "\r\n";

		if ($sign){
			$message .= $this->lang['email_sign'];
		}

        $title = '=?UTF-8?B?' . base64_encode($title) . '?=';
		$message = sprintf(messages::html_template, $message);

		if (!mail($email, $title, $message, $headers, '-f' . $from)){
			return false;
		}

		return true;
	}

    /**
      * Интерфейс для перенаправления URL
      *
      * @param string $page_url Куда перенаправлять
      *
      * @param int $page_id ID страницы
      *
      * @param bool $redirect
      *
      * @param string $prefix
      *
      * @param string $back_url
      *
      * @return bool
      */

	function url_redirect($page_url = '', $page_id = null, $redirect = false, $prefix = null, $back_url = null){

        $url = '/' . cfg::uri_prefix;
        if ($prefix !== null && $prefix !== false) {
            $url = $prefix;
        }

        if ($prefix === false) {
            $url = '';
        }

		if (isset($page_id))
			$url .= "/?page=" . $page_id;
		else
			if ($prefix === null)
                $url .= "/" . $page_url;
            else
                $url .= $page_url;

		if (isset($_SERVER['REDIRECT_URL']) && preg_match("/^[a-z\/\-\_0-9]+$/i", $_SERVER['REDIRECT_URL']) && $back_url === null) {
			$back_url = $_SERVER['REDIRECT_URL'];
        }

		if (isset($_SERVER['REDIRECT_QUERY_STRING']) && $back_url === null) {
			$back_url .= '?' . $_SERVER['REDIRECT_QUERY_STRING'];
        }

        $back_url = preg_replace("/^\/my\//", '', $back_url);

		if ($redirect && $back_url && $back_url != '') {
			$url = $url . '?back_url=' . $back_url;
        }

		header("Location:" . $url);

        exit;
	}

    /**
      * Функция для получения массива данных о пользователе по его email
      *
      * @param string $email Адрес пользователя
      *
      * @param bool $main_info
      *
      * @return array
      */

	function get_user_info($email, $main_info = false) {
        $this->account_label = 'my_account:';

		if (isset($email) && $this->valid_email($email)) {
            $this->account_label .= $email;
            if ($this->user_info_reload) {
                apc_delete($this->account_label);
            } else {
                $row = apc_fetch($this->account_label);
                if (isset($row['user_id'])) {
                    return $row;
                }
            }
		} else {
			return false;
		}

        $row = $this->db->select(sprintf(sql::get_user_info, $email));

        if ($this->user_token) $row['read_only'] = true;

        // Автарка
        if(empty($row['avatar'])){
            $row['avatar'] = sprintf('https://www.gravatar.com/avatar/%s?s=30&d=%s',
                md5(strtolower($email)),
                urlencode('https://cleantalk.org/images/avatar.png')
            );
            $row['avatar_big'] = sprintf('https://www.gravatar.com/avatar/%s?s=100&d=%s',
                md5(strtolower($email)),
                urlencode('https://cleantalk.org/images/avatar.png')
            );
        }else{
            $row['avatar'] = $row['avatar'];
            $row['avatar_big'] = $row['avatar'];
        }
        // Валюта
        if (isset($row['currency'])) {
            $row_cur = $this->db->select(sprintf("select c.currency, usd_rate, currency_sign from currency c where c.currency = %s;", $this->stringToDB($row['currency'])));
            $row['currency_info'] = array(
                'id' => $row_cur['currency'],
                'rate' => $row_cur['usd_rate'],
                'sign' => $row_cur['currency_sign']
            );
        }

        // Количество сервисов у пользователя по продуктам
        $row['services_count'] = array(
            'antispam' => 0,
            'api' => 0,
            'hosting-antispam' => 0,
            'security' => 0,
            'ssl' => 0
        );

        // Если у пользователя есть лицензии
        $rows = $this->db->select(sprintf("SELECT * FROM users_licenses WHERE user_id=%d", $row['user_id']), true);
        if ($rows && count($rows)) {
            $licenses = array();
            foreach ($rows as $r) {
                $tariff = $this->db->select(sprintf("SELECT * FROM tariffs WHERE tariff_id = %d;", $r['fk_tariff']));
				if (function_exists('money_format')) {
					$tariff['cost'] = money_format('%.0n', $tariff['cost']);
				}
                $r['tariff'] = $tariff;

                if (!isset($r['product_id'])) {
                    continue;
                }
                $services = $this->db->select(sprintf("SELECT COUNT(*) AS sites FROM services WHERE user_id = %d AND product_id = %d", $row['user_id'], $r['product_id']));
                $r['services'] = $services ? $services['sites'] : 0;
                switch ($r['product_id']) {
                    case cfg::product_database_api:
                        $licenses['api'] = $r;
                        $row['services_count']['api'] = $r['services'];
                        break;
                    case cfg::product_antispam:
                        $licenses['antispam'] = $r;
                        $row['services_count']['antispam'] = $r['services'];
                        break;
                    case cfg::product_hosting_antispam:
                        $licenses['hosting-antispam'] = $r;
                        $row['services_count']['hosting-antispam'] = $r['services'];
                        break;
                    case cfg::product_security:
                        $licenses['security'] = $r;
                        $row['services_count']['security'] = $r['services'];
                        break;
                    case cfg::product_ssl:
                        // Для SSL у пользователя может быть несколько лицензий
                        if (!isset($licenses['ssl'])) $licenses['ssl'] = array();
                        $licenses['ssl'][] = $r;
                        $row['services_count']['ssl'] += 1;
                        break;
                }
            }

            $row['licenses'] = $licenses;
            $row['product_id'] = cfg::product_antispam;

            // Устанавливаем тариф в зависимости от текущего cp_mode и наличия лицензии
            if (isset($licenses[$this->cp_mode])) {
                if ($this->cp_product_id == cfg::product_ssl) {

                } else {
                    $row['tariff'] = $row['licenses'][$this->cp_mode]['tariff'];
                    $row['product_id'] = $row['tariff']['product_id'];

                    $license = $licenses[$this->cp_mode];
                    $row['license'] = $license;
                    $row['fk_tariff'] = $license['fk_tariff'];
                    $row['paid_till'] = $license['paid_till'];
                    $row['paid_till_ts'] = strtotime($license['paid_till']);
                    $row['valid_till'] = $license['valid_till'];
                    $row['valid_till_ts'] = strtotime($license['valid_till']);
                    if ($row['valid_till_ts'] > $row['paid_till_ts']) $row['paid_till_ts'] = $row['valid_till_ts'];
                    $row['moderate'] = $license['moderate'];
                    $row['trial'] = $license['trial'];

                    $row['services'] = $this->db->select(sprintf("SELECT COUNT(*) AS count FROM services WHERE user_id=%d AND product_id=%d", $row['user_id'], $row['product_id']));
                    $row['services'] = $row['services'] ? $row['services']['count'] : 0;
                }

                // Дополнительные действия в зависимости от продукта
                switch ($row['product_id']) {
                    case cfg::product_security:
                        // Для security определим бонусы
                        $bonuses_ids = array('early_pay', 'review');
                        $bonuses_activated = array();
                        $bonuses_available = 0;
                        $bonuses_rows = $this->db->select(sprintf("SELECT bonus_name FROM users_bonuses WHERE user_id = %d AND license_id = %d", $row['user_id'], $license['id']), true);
                        foreach ($bonuses_rows as $bonuses_row) {
                            $bonuses_activated[$bonuses_row['bonus_name']] = true;
                        }
                        $bonuses = $this->db->select(sprintf("SELECT bonus_name, show_before_paid, show_after_paid FROM bonuses WHERE bonus_name IN ('%s')", implode("', '", $bonuses_ids)), true);
						foreach($bonuses as $k => $v) {

							// 
							// Skip bonus for review because of WordPress Guidlines.
							// 
							if ($v['bonus_name'] == 'review') {
								continue;
							}
                            if (!isset($bonuses_activated[$v['bonus_name']])) {
                                if ($license['trial'] && !$v['show_before_paid']) {
                                    continue;
                                }
                                if (!$license['trial'] && !$v['show_after_paid']) {
                                    continue;
                                }
                                $bonuses_available++;
                            }
                        }
                        $row['license']['bonuses'] = array(
                            'activated' => $bonuses_activated,
                            'available' => $bonuses_available
                        );
                        break;
                }
            } else if ($this->cp_mode == 'antispam') {
                $tariff = $this->db->select(sprintf(sql::get_tariff, $row['fk_tariff']));
				if (function_exists('money_format')) {
                	$tariff['cost'] = money_format('%.0n', $tariff['cost']);
				}
				$row['tariff'] = $tariff;
            }
        } else {
            $tariff = $this->db->select(sprintf(sql::get_tariff, $row['fk_tariff']));
			if (function_exists('money_format')) {
				$tariff['cost'] = money_format('%.0n', $tariff['cost']);
			}
            $row['tariff'] = $tariff;
        }

        if (isset($row['product_id'])) {
            switch ($row['product_id']) {
                case cfg::product_antispam:
                    $row['product_name'] = 'antispam';
                    break;
                case cfg::product_database_api:
                    $row['product_name'] = 'api';
                    break;
                case cfg::product_hosting_antispam:
                    $row['product_name'] = 'hosting-antispam';
                    break;
                case cfg::product_security:
                    $row['product_name'] = 'security';
                    break;
                case cfg::product_ssl:
                    $row['product_name'] = 'ssl';
                    break;
            }
        }

        // Подключаемся к удаленному серверу для получения статистики по акаунту
        $this->mc_billing = new Memcache;
        $this->mc_billing->addServer(local_cfg::memcache_host_main, local_cfg::memcache_port_main);

        $counters_label = 'counters_';
        $counters = $this->mc_billing->get($counters_label . $row['user_id']);
        $counters = json_decode($counters, true);

        // Делаем прямой запрос в БД для формирования минимальной статистики по запросам.
        if (!isset($counters['requests_total'])) {
            $stat = $this->db->select(sprintf("select sum(allow) as allow, count(*) as count from requests where user_id = %d;",
                $row['user_id']
            ));

            if ($stat) {
                $counters['requests_total'] = $stat['count'];
                $counters['total_pm'] = $stat['allow'];
            }
            $this->mc_billing->set($counters_label . $row['user_id'], json_encode($counters), null, cfg::memcache_store_timeout);
        }

        $row['requests_today'] = isset($counters['requests_today']) ? $counters['requests_today'] : 0;
        $row['requests_yesterday'] = isset($counters['requests_yesterday']) ? $counters['requests_yesterday'] : 0;
        $row['requests_week'] = isset($counters['requests_week']) ? $counters['requests_week'] : 0;
        $row['requests_today_spam'] = isset($counters['requests_today_spam']) ? $counters['requests_today_spam'] : 0;
        $row['requests_yesterday_spam'] = isset($counters['requests_yesterday_spam']) ? $counters['requests_yesterday_spam'] : 0;
        $row['requests_week_spam'] = isset($counters['requests_week_spam']) ? $counters['requests_week_spam'] : 0;
        $row['total_pm'] = isset($counters['total_pm']) ? $counters['total_pm'] : 0;
        $row['requests_total'] = isset($counters['requests_total']) ? $counters['requests_total'] : 0;
        $row['spam_total'] = $row['requests_total'] - $row['total_pm'];

        $row['requests_today'] = number_format($row['requests_today'], 0, '', ' ');
        $row['requests_yesterday'] = number_format($row['requests_yesterday'], 0, '', ' ');
        $row['requests_week'] = number_format($row['requests_week'], 0, '', ' ');
        $row['requests_today_spam'] = number_format($row['requests_today_spam'], 0, '', ' ');
        $row['requests_yesterday_spam'] = number_format($row['requests_yesterday_spam'], 0, '', ' ');
        $row['requests_week_spam'] = number_format($row['requests_week_spam'], 0, '', ' ');

        // Запрос на получения размера бонуса
        $bonus = $this->db->select(sprintf('select bonus from users_bonus where user_id = %d;', $row['user_id']));
        if (isset($bonus['bonus']))
            $row['bonus'] = $bonus['bonus'];
        else
            $row['bonus'] = 0.00;

        if ($this->ct_lang == 'en' && $this->cp_mode == 'antispam'){
            $row['bonus'] = number_format($row['bonus'] / cfg::usd_mpc, 2, '.', ' ');
            $row['balance'] = number_format($row['balance'] / cfg::usd_mpc, 2, '.', ' ');
        }

        // Лимит полезных сообщений
        $row['limit_pm'] = isset($row['max_pm']) ? $row['max_pm'] - $row['total_pm'] : 0;

        // Устанавливаем минимальное значение для лимита полезных запросов
        if ($row['limit_pm'] < 0)
            $row['limit_pm'] = 0;

        // Флаг приостановления сервиса по привышению дневного лимита
        $row['freeze_mpd'] = $row['requests_today'] > $tariff['mpd'] ? true : false;
//       var_dump($_COOKIE['ct_user_timezone']); exit;
        // Определяем временную зону пользователя
        $cookie_timezone = isset($_COOKIE['ct_user_timezone']) && preg_match("/^[+\-0-9\,\.]{1,5}$/", $_COOKIE['ct_user_timezone']) ? $_COOKIE['ct_user_timezone'] : null;
        if ($cookie_timezone !== null && $row['timezone'] === null){
            $this->db->run(sprintf('update users_info set timezone = %s where user_id = %d;', $cookie_timezone, $row['user_id']));
            $row['timezone'] = $cookie_timezone;
            $this->post_log(sprintf(messages::set_new_timezone, $cookie_timezone, $row['email']));
        }
//       var_dump($cookie_timezone); exit;
        $row['timezone_db'] = $row['timezone'];
        if (!$row['timezone']) {
            $row['timezone'] = $this->options['default_timezone'];
        }

        $row['tz_ts'] = $this->get_local_timestamp(time(), $row['timezone']);

		// Определяем страну пользователя
		if ($row['country'] === null && $this->remote_addr && !$this->staff_mode && !$this->user_token) {
            $tools = new CleanTalkTools();
            $country = $tools->get_country_code($this->remote_addr);
            if ($country) {
                $this->db->run(sprintf('update users set country = %s where user_id = %d;', $this->stringToDB($country), $row['user_id']));

                $this->post_log(sprintf('Установлена страна %s пользовтелю %s (%d).',
                    $country,
                    $row['email'],
                    $row['user_id']
                ));
                $row['country'] = $country;
            }
        }

        // Добавляем знак + к временной зоне, для наглядности
		if (isset($row['timezone']) && ($row['timezone'] * -1) <= 0) {
        	$row['timezone'] = '+' . $row['timezone'];
		}

        // Вычисляем местное время с поправкой серверного времени на UTC и поправкой на часовой пояс пользователя
        $row['localtime'] = date("Y-m-d H:i:s", time() - (3600 * (int) cfg::billing_timezone) + (3600 * (int) $row['timezone']));

        // Сохраняем список локалей в броузере
        if (!$row['http_accept_language'] && isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && !$this->staff_mode && !$this->user_token) {
            $this->db->run(sprintf("update users_info set http_accept_language = %s where user_id = %d;",
                $this->stringToDB(addslashes($_SERVER['HTTP_ACCEPT_LANGUAGE'])),
                $row['user_id']
            ));
            // Устанавливаем правильный язык профиля
            if ((!$row['lang'] || $row['lang'] == 'en') && !$this->staff_mode && !$this->user_token) {
                if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
                    && preg_match("/^([a-z]{2})/", $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches)
                    && array_key_exists($matches[1], $this->ct_langs)) {
                    $language = $matches[1];
                    $this->db->run(sprintf('update users_info set lang = %s where user_id = %d;', $this->stringToDB($matches[1]), $row['user_id']));
                    $this->post_log(sprintf('Установлен язык %s пользовтелю %s (%d).',
                        $matches[1],
                        $row['email'],
                        $row['user_id']
                    ));
                    $row['lang'] = $matches[1];
                }
            }
        }

        // Количество подключенных сервисов пользователя
        // --- оставил для обратной совместимости без лицензий
        if (isset($row['product_name'])) {
            $row['services'] = $row['services_count'][$row['product_name']];
        } else if ($this->cp_product_id == cfg::product_antispam) {
            $s_count = $this->db->select(sprintf("select ifnull(count(*), 0) as count from services where user_id = %d and (product_id = %d or product_id is null);", $row['user_id'], cfg::product_antispam));
            $row['services'] = $s_count['count'];
            $row['services_count']['antispam'] = $s_count['count'];
        } else {
            $row['services'] = 0;
            $s_count = $this->db->select(sprintf("select ifnull(count(*), 0) as count from services where user_id = %d and (product_id = %d or product_id is null);", $row['user_id'], cfg::product_antispam));
            $row['services_count']['antispam'] = $s_count['count'];
        }

        // Массив произвольных служебных данных, для подсчета различной статистики по пользователям.
        $row['meta'] = explode(",", $row['meta']);

        $sql = sprintf("select ua.addon_id,ua.paid_till,ta.addon_name,ua.enabled from users_addons ua left join tariffs_addons ta on ta.addon_id = ua.addon_id where user_id = %d;",
            $row['user_id']
        );
        $addons = $this->db->select($sql, true);

        $row['addons'] = $addons;
        apc_store($this->account_label, $row, cfg::apc_cache_lifetime_account);

        return $row;
	}

    /**
      * Функция для получения данных о тарифе
      *
      * @param int $tariff_id Id тарифа
      *
      * @param bool $get_tariff_info
      *
      * @return array
      */

    function get_tariff_info($tariff_id, $get_tariff_info = true){

        if (!isset($tariff_id))
            return false;

        if (isset($this->lang['l_tariff_info']) && $get_tariff_info === true && isset($this->tariffs[$tariff_id]['cost'])){
            $tariff_cost = $this->tariffs[$tariff_id]['cost'];
            if ($this->ct_lang == 'en')
                if (isset($this->tariffs[$tariff_id]['cost_usd']))
                    $tariff_cost = $this->tariffs[$tariff_id]['cost_usd'];
                else
                    $tariff_cost = $tariff_cost / cfg::usd_mpc;

            $period_label = $this->lang['l_month'];
            if ($this->tariffs[$tariff_id]['period'] == 365)
                $period_label = $this->lang['l_year'];

            $this->page_info['l_tariff_info'] = $this->tariffs[$tariff_id]['info_charge'];

            if (isset($this->tariffs[$tariff_id]['pmi']))
                $this->page_info['l_tariff_info_pmi'] = sprintf($this->lang['l_tariff_info_pmi'], $this->tariffs[$tariff_id]['pmi']);

            if (isset($this->user_info['tariff'])) {
                $this->page_info['l_will_extend'] = sprintf($this->lang['l_will_extend'], $this->user_info['paid_till'], $this->user_info['tariff']['period']);
            }
        }

        $tariff = $this->db->select(sprintf(sql::get_tariff, $tariff_id));
        return $tariff;
 	}

    /**
     * Создаёт лицензию Site Security для пользователя.
     *
     * @param $data array Данные пользователя
     * @param $licenses array Лицензии
     */
 	protected function new_license_security($data, &$licenses) {
 	    $paid_till = date('Y-m-d', time() + 86400 * 14);
 	    $sql_data = array(
 	        'user_id' => $data['user_id'],
            'fk_tariff' => cfg::security_tariff_default,
            'balance' => 0,
            'created' => 'now()',
            'updated' => 'now()',
            'paid_till' => $this->stringToDB($paid_till),
            'valid_till' => $this->stringToDB($paid_till),
            'moderate' => 1,
            'trial' => 1,
            'cost_day_usual' => 0,
            'cost_day_over' => 0,
            'max_calls' => 0,
            'current_calls' => 0,
            'free_days' => $this->options['free_days'],
            'days_2_keep_requests' => 0,
            'logging_restriction' => 0,
            'bill_id' => 0,
            'pay_id' => 0,
            'product_id' => cfg::product_security
        );

 	    $sql = sprintf("INSERT INTO users_licenses (%s) VALUES (%s)",
            implode(', ', array_keys($sql_data)),
            implode(', ', array_values($sql_data)));
        if ($id = $this->db->run($sql)) {
            $license = $this->db->select(sprintf("SELECT * FROM users_licenses WHERE id = %d", $id));
            $license['tariff'] = $this->db->select(sprintf("SELECT * FROM tariffs WHERE tariff_id = %d;", $license['fk_tariff']));
            if ($license) {
                $licenses['security'] = $license;
                if ($this->cp_mode == 'security') $this->user_info['license'] = $license;
            }
        }
    }

    /**
      * Функция перевода даты в timestamp
      *
      * @param string $date
      *
      * @return string
      */

	function DateToTimestamp($date){
		if ( preg_match("/^(\d{4})-(\d{2})-(\d{2})$/", $date, $matches) ){
			return mktime(0, 0, 0, $matches[2], $matches[3], $matches[1]);
		}elseif ( preg_match("/^(\d{2,4})-(\d{1,2})-(\d{1,2})\s(\d{1,2}):(\d{1,2})$/", $date, $matches) ){
			return mktime($matches[4], $matches[5], 0, $matches[2], $matches[3], $matches[1]);
		}else{
			return 0;
		}
	}

    /**
      * Функция генерации пароля
      *
      * @param int $length Длина пароля
      *
      * @param int $strength Уровень шифрования пароля
      *
      * @return
      */

	function generatePassword($length=9, $strength=0) {
		$vowels = 'aeuy';
		$consonants = 'bdghjmnpqrstvz';
		if ($strength & 1) {
			$consonants .= 'BDGHJLMNPQRSTVWXZ';
		}
		if ($strength & 2) {
			$vowels .= "AEUY";
		}
		if ($strength & 4) {
			$consonants .= '23456789';
		}
		if ($strength & 8) {
			$consonants .= '@#$%';
		}

		$password = '';
		$alt = time() % 2;
		for ($i = 0; $i < $length; $i++) {
			if ($alt == 1) {
				$password .= $consonants[(rand() % strlen($consonants))];
				$alt = 0;
			} else {
				$password .= $vowels[(rand() % strlen($vowels))];
				$alt = 1;
			}
		}
		return $password;
	}

    /**
      * Фугцкия генерации VCode
      *
      * @param int $length Длина кода
      *
      * @return string
      */

    function generateVCode($length = 5) {
		$consonants = '0123456789';
		$password = '';
		for ($i = 0; $i < $length; $i++) {
		    $password .= $consonants[(rand() % strlen($consonants))];
		}
		return $password;
	}

    /**
      * Функция проверки email на валидность
      *
      * @param string $email Адрес электронной почты
      *
      * @return bool
      */

	function valid_email($email) {
          // Frst, we check that there's one @ symbol, and that the lengths are right
        if (!preg_match("/^[^@]{1,64}@[^@]{1,255}$/", $email)) {
                // Email invalid because wrong number of characters in one section, or wrong number of @ symbols.
                return false;
        }
          // Split it into sections to make life easier
        $email_array = explode("@", $email);
        $local_array = explode(".", $email_array[0]);
        for ($i = 0; $i < sizeof($local_array); $i++) {
                if (!preg_match("/^(([A-Za-z0-9!#$%&#038;'*+\/\=?^_`{|}~-][A-Za-z0-9!#$%&#038;'*+\/\=?^_`{|}~\.-]{0,63})|(\"[^(\\|\")]{0,62}\"))$/", $local_array[$i])) {
                return false;
                }
        }
        if (!preg_match("/^\[?[0-9\.]+\]?$/", $email_array[1])) { // Check if domain is IP. If not, it should be valid domain name
                $domain_array = explode(".", $email_array[1]);
                if (sizeof($domain_array) < 2) {
                return false; // Not enough parts to domain
                }
                for ($i = 0; $i < sizeof($domain_array); $i++) {
                if (!preg_match("/^(([A-Za-z0-9][A-Za-z0-9-]{0,61}[A-Za-z0-9])|([A-Za-z0-9]+))$/", $domain_array[$i])) {
                        return false;
                }
                }
        }
        return true;
	}

    /**
      * Функция проверки уникальности email в базе пользователей
      *
      * @param string $email Адрес электронной почты
      *
      * @return bool
      */

    function check_email($email){
		$sql = sprintf(sql::check_email, $email);
		if ( $this->debug ) {
				$this->var_dump('check_email: '.$sql);
		}
		$row = $this->db->select($sql);
		if ($row && $row['count(user_id)'] != 0){
			return 1;
		}
		return 0;
	}

    /**
      * Функция проверки уникальности user_id в базе пользователей
      *
      * @param int $user_id ID пользователя
      *
      * @return bool
      */

    function check_user_id($user_id = null){
        $found = false;
        if ($user_id === null || !preg_match("/^\d+$/", $user_id))
            return $found;

        $label = 'my_user_id:' . $user_id;
        $row = apc_fetch($label);
        if (!$row) {
            $row = $this->db->select(sprintf("select user_id from users where user_id = %d;", $user_id));
            apc_store($label, $row, cfg::apc_cache_lifetime_long);
        }

        if (isset($row['user_id']))
            $found = true;

        return $found;
	}

    /**
      * Функция генерации предложения о переходе на коммерческую подписку
      *
      * @param int $max_pm
      *
      * @param int $offer_tariff_id ID тарифа предложения
      *
      * @param int $period
      *
      * @param int $discount
      *
      * @return bool
      */

    function show_offer($max_pm = null, $offer_tariff_id = null, $period = null, $discount = 0){

        $this->page_info['show_offer'] = true;
        $this->page_info['show_year_offer'] = false;

        //
        // Спец. условия для регистраций из контекста.
        // https://basecamp.com/2889811/projects/8701471/todos/254696092
        //
        if (isset($this->user_info['lead_source']) && preg_match("/^context_adwords/", $this->user_info['lead_source'])) {
            $sql = sprintf("select connected from services where user_id = %d order by created asc limit 1;",
                $this->user_id
            );
            $row = $this->db->select($sql);
            $show_offer = true;
            if (isset($row['connected']) && $row['connected']) {
                $first_request = strtotime($row['connected']);
                if (time() - $first_request < cfg::show_offer_for_context * 86400) {
                    $show_offer = false;
                }
            } else {
                $show_offer = false;
            }
            if (!$show_offer) {
                $this->page_info['show_offer'] = false;
                return false;
            }
        }

        if (isset($this->lang['l_tariff_info_pmi']))
            $this->page_info['tariff_info_pmi_offer'] = sprintf($this->lang['l_tariff_info_pmi'], $this->user_info['tariff']['pmi']);

        if ($max_pm === null)
            $max_pm = $this->user_info['max_pm'];

        $row = $this->db->select(sprintf('select count(*) as count from users_freeze_stat where tariff_id = %d and user_id = %d and freeze = 1;',
                                $this->user_info['tariff']['tariff_id'], $this->user_id));

        $row['count'] = 1;
        $strict_tariff = false;
        if ($offer_tariff_id === null)
            $offer_tariff_id = cfg::premium_tariff_id;
        else
            $strict_tariff = true;

        if ($period === null)
            if ($this->ct_lang == 'en' && $this->tariffs[$offer_tariff_id]['billing_period'] == 'Month')
                $period = 3;
            else
                $period = 1;

        $auto_bill = 0;

        $subscribe_period = 'l_month';
        $old_cost_label = '';
        $period_label = ' ';
        if ($period > 1)
            $period_label = " $period ";

        // Если пользователь продлил доступ более 3х раз, то предлагаем ему перейти на платный тариф
        // Годовой тариф показываем только англоязычным пользователям, до тех пор пока не запустим работу с динамическими счетами через PayPal
        if ($row['count'] >= cfg::year_offer_show_extends && $strict_tariff === false) {
            $offer_tariff_id = cfg::year_tariff_id;
            $period = 1;
            $subscribe_period = 'l_year';
            $period_label = " ";
            if ($this->ct_lang == 'ru')
                $period_label = " $period ";
        }

        // Предложение действительно только при условии что текущая стоимость сервиса привышает стоимость по счету
        // Либо пользователь ранее продливал тариф
        if (!$this->cost_is_good($offer_tariff_id) || ($row['count'] == 0 && $strict_tariff == false)) {
            $offer_tariff_id = $this->user_info['fk_tariff'];
        }

        $paid_till = $this->get_tariff_conditions(null, $period, $offer_tariff_id, true, true);
        $bill = $this->get_bill($offer_tariff_id, false, $period, null, false, false, $this->page_info['tariff_conditions'], $auto_bill, $discount, $this->currencyCode, date("Y-m-d", $paid_till));

        if (isset($this->lang['l_positive_requests_tariff'])){
            $this->page_info['positive_requests_current'] = sprintf($this->lang['l_positive_requests_tariff'], $this->user_info['tariff']['mpd']);
            $this->page_info['positive_requests_new'] = sprintf($this->lang['l_positive_requests_tariff_new'], $this->tariffs[$this->page_info['bill']['fk_tariff']]['mpd']);
        }
        $services_current =  $this->user_info['tariff']['services'];
        if ($services_current == 0) {
            $services_current = $this->lang['l_unlimited'];
        }
        $services_new = $this->tariffs[$offer_tariff_id]['services'];
        if ($services_new == 0) {
            $services_new = $this->lang['l_unlimited'];
        }
        if (isset($this->lang['l_services_count'])){
            $this->page_info['services_count_current'] = sprintf($this->lang['l_services_count'], $services_current);
            $this->page_info['services_count_new'] = sprintf($this->lang['l_services_count'], '<b>' . $services_new . '</b>');
        }

        if ($strict_tariff === true){
            $subscribe_period = 'l_' . strtolower($this->tariffs[$offer_tariff_id]['billing_period']);
            $period_label = " $period ";
            $old_cost_label = '';
        }

        $this->page_info['upgrade_offer'] = $this->page_info['tariff_conditions_short'];

        $this->page_info['subscribe_period'] = $this->lang[$subscribe_period];
        $this->page_info['offer_template'] = 'upgrade_offer.html';
//        $this->page_info['offer_tariff_id_param'] = sprintf("?tariff_id=%d", $offer_tariff_id);
        $this->page_info['offer_title'] = $this->lang['l_monthly_antispam'];

        $utm_medium = 'trial_banner_renew_button';
        if ($this->user_info['trial'] == 0) {
            $utm_medium = 'renew_banner_renew_button';
        }
        $this->page_info['offer_tariff_id_param'] = sprintf('?utm_source=cleantalk.org&amp;utm_medium=%s&amp;utm_campaign=control_panel',
            $utm_medium
        );

        if ($this->tariffs[$offer_tariff_id]['billing_period'] == 'Year') {
            $this->page_info['pay_button'] = $this->lang['l_pay_1year'];
            $this->page_info['hide_data_plans'] = true;
            $this->page_info['offer_more'] = false;
            if (isset($this->lang['l_antispam_package_offer'])) {
                $this->page_info['offer_title'] = $this->lang['l_antispam_package_offer'] . ' ' . $bill['comment_short_with_tags'];
			}

            if ($this->user_info['paid_till_ts'] < time() && !$this->user_info['first_pay_id']) {
                $this->page_info['pay_button'] = $this->lang['l_renew_1year'];
            }

            if (!$this->user_info['first_pay_id'] && cfg::show_new_trial_offer) {
                $this->page_info['offer_template'] = 'trial_annual_offer.html';
                $this->page_info['hide_charge'] = true;
                $this->page_info['offer_cost'] = sprintf('%s%s/%s',
                   $this->lang['l_currency'],
                   $this->page_info['cost_human_int'],
                   $this->lang['l_year']
                );
                $this->page_info['spam_total'] = number_format($this->user_info['spam_total'], 0, '', ' ');
                $paid_till_part = sprintf('<span class="red_text">%s</span>',
                    date("M d Y", strtotime($this->user_info['paid_till']))
                );
                $date_part = sprintf($this->lang['l_trial_ends_part'],
                    $paid_till_part
                );
                $free_months_part = '';
                if ($this->page_info['bill']['free_months']) {
                    $free_months_part = sprintf($this->lang['l_free_months_part'],
                        $this->page_info['bill']['free_months']
                    );
                }
                if ($this->user_info['paid_till_ts'] < time()) {
                    $date_part = sprintf($this->lang['l_trial_ended'],
                        $paid_till_part
                    );
                }

                // Если у пользователя истек срок действия триала и нет запросов, то не показываем информацию с датами,
                // т.к. эта информация вводит в заблуждение
                if ($this->user_info['requests_total'] == 0) {
                    $date_part = '';
                }

                $second_part = sprintf($this->lang['l_trial_second_part'],
                    $date_part,
                    $free_months_part
                );
                $this->page_info['trial_offer_notice'] = sprintf($this->lang['l_trial_offer_notice'],
                    $second_part
                );
            }
        }

        $this->page_info['pay_button'] = mb_strtoupper($this->page_info['pay_button']);

        return true;
    }

    /**
      * Функция возвращает true в случаи если стоимость нового тарифа выше текущего
      *
      * @param int $tariff_id ID тарифа
      *
      * @return bool
      */

    function cost_is_good($tariff_id){
        if(!isset($this->user_info['tariff'])){
            return true;
        }
        return (($this->user_info['tariff']['cost'] / $this->user_info['tariff']['period']) <= ($this->tariffs[$tariff_id]['cost'] / $this->tariffs[$tariff_id]['period'])) ? true : false;
    }

    function load_product_licenses() {
		$this->products = [];

		$licenses = $this->db->select("SELECT * FROM `product_license`", true);
		$periods = $this->db->select("SELECT * FROM `product_period`", true);
		$rows = $this->db->select("SELECT * FROM `product`", true);
		foreach ($rows as $row) {
			$productLicenses = [];
			foreach ($licenses as $license) {
				if ($license['product_id'] == $row['product_id']) {
					$productLicensePeriod = [];
					foreach ($periods as $period) {
						if ($period['period_id'] == $license['period_id']) {
							$productLicensePeriod = array(
								'id' => $period['period_id'],
								'measure_unit' => $period['measure_unit'],
								'duration_in_units' => $period['duration_in_units'],
								'calls_per_duration' => $period['calls_per_duration']
							);
						}
					}
					$productLicenses[] = array(
						'id' => $license['license_id'],
						'status' => $license['status'],
						'billing_type' => $license['billing_type'],
						'cost_usd' => $license['cost_usd'],
						'number_of_services' => $license['number_of_services'],
						'number_of_periods' => $license['number_of_periods'],
						'period' => $productLicensePeriod
					);
				}
			}
			$this->products[$row['product_id']] = array(
				'id' => $row['product_id'],
				'service_type' => $row['service_type'],
				'name' => $row['name_en'],
				'description' => $row['descr_' . ($this->ct_lang ? $this->ct_lang : 'en')],
				'licenses' => $productLicenses
			);
		}
	}

	function show_upgrade_licenses() {
		if (!$this->products) $this->load_product_licenses();

		// Вспомогательные метаданные
		$licenseIds = array();
		foreach ($this->products as &$product) {
			foreach ($product['licenses'] as &$license) {
				$licenseIds[] = $license['id'];
				$licenseTitle = '';
				switch ($product['service_type']) {
					case 'site':
						$licenseTitle = $license['number_of_services'] > 0 ?
								$this->lang_form_print($license['number_of_services'], 'l_website_forms')
								: sprintf("%s %s", mb_strtoupper(mb_substr($this->lang['l_unlimited'], 0, 1)) . mb_substr($this->lang['l_unlimited'], 1), $this->lang['l_website_forms'][2]);
						break;
				}
				$license['title'] = $licenseTitle;

				// Стоимость в используемой валюте
				if ($this->row_cur) {
					$license['cost'] = sprintf("%s%d", $this->row_cur['currency_sign'], $license['cost_usd'] * $this->row_cur['usd_rate']);
					$license['cost_total'] = sprintf("%s%.2f", $this->row_cur['currency_sign'], $license['cost_usd'] * $this->row_cur['usd_rate']);
					$license['cost_total2'] = sprintf("%.2f %s", $license['cost_usd'] * $this->row_cur['usd_rate'], $this->row_cur['currency']);
					$license['cost_number'] = sprintf("%.2f", $license['cost_usd'] * $this->row_cur['usd_rate']);
					if ($license['number_of_services'] > 0) {
						$license['cost_per_service'] = sprintf("%s%.2f", $this->row_cur['currency_sign'], ($license['cost_usd'] * $this->row_cur['usd_rate']) / $license['number_of_services']);
					} else {
						$license['cost_per_service'] = $license['cost'];
					}
				} else {
					$license['cost'] = sprintf("$%d", $license['cost_usd']);
					$license['cost_total'] = sprintf("$%.2f", $license['cost_usd']);
					$license['cost_total2'] = sprintf("%.2f USD", $license['cost_usd']);
					$license['cost_number'] = sprintf("%.2f", $license['cost_usd']);
					if ($license['number_of_services'] > 0) {
						$license['cost_per_service'] = sprintf("$%.2f", $license['cost_usd'] / $license['number_of_services']);
					} else {
						$license['cost_per_service'] = sprintf("$%.2f", $license['cost_usd']);
					}
				}

				// Длительность лицензии для вывода с списке
				$duration = $license['period']['duration_in_units'] * $license['number_of_periods'];
				$measure = $license['period']['measure_unit'];
				switch ($measure) {
					case 'month':
						$license['period']['in_months'] = $duration;
						break;
					case 'year':
						$license['period']['in_months'] = $duration * 12;
						break;
					default:
						$license['period']['in_months'] = 0;
				}
				if ($duration == 12 && $measure == 'month') {
					$duration = 1;
					$measure = 'year';
				}
				if ($duration > 1) {
					$license['period']['title'] = sprintf("%d %s", $duration, $this->lang_form($duration, $this->lang['l_' . $measure . '_forms']));
					$license['period']['title_full'] = $license['period']['title'];
				} else {
					$license['period']['title'] = $this->lang['l_' . $measure];
					$license['period']['title_full'] = '1 ' . $license['period']['title'];
				}
				$license['period']['end'] = strtotime(date('Y-m-d', mktime()) . " + " . $duration . " " . $measure);
			}
		}

		// Выбор текущего продукта и лицензии
		if (isset($_GET['license_id']) && !in_array($_GET['license_id'], $licenseIds)) unset($_GET['license_id']);
		if (isset($_GET['license_id'])) {
			foreach ($this->products as &$product) {
				foreach ($product['licenses'] as &$license) {
					if ($license['id'] == $_GET['license_id']) {
						$license['current'] = true;
						$license['period']['dates'] = date('M d Y') . ' - ' . date('M d Y', $license['period']['end']);
						$license['comment_short'] = sprintf('%s, %s, %s', $license['title'], $license['cost_total'], $license['period']['title']);
						$this->page_info['current_product'] = $product;
						break 2;
					}
				}
			}
		} else {
			foreach ($this->products as &$product) {
				foreach ($product['licenses'] as &$license) {
					$license['current'] = true;
					$license['period']['dates'] = date('M d Y') . ' - ' . date('M d Y', $license['period']['end']);
					$license['comment_short'] = sprintf('%s, %s, %s', $license['title'], $license['cost_total'], $license['period']['title']);
					$this->page_info['current_product'] = $product;
					break 2;
				}
			}
		}

		$this->page_info['products'] = $this->products;
	}

	function lang_form_print($number, $form1, $form2 = null, $form3 = null) {
		return sprintf("%d %s", $number, $this->lang_form($number, $this->lang[$form1], $this->lang[$form2], $this->lang[$form3]));
	}

	function lang_form($number, $form1, $form2 = null, $form3 = null) {
		if (is_array($form1)) {
			$form3 = $form1[2];
			$form2 = $form1[1];
			$form1 = $form1[0];
		}
		switch ($this->ct_lang) {
			case 'en':
				return $number > 1 ? $form2 : $form1;
			case 'ru':
				$number = abs($number) % 100;
				$num = $number % 10;
				if ($number > 10 && $number < 20)
					return $form3;
				if ($num > 1 && $num < 5)
					return $form2;
				if ($num == 1)
					return $form1;
				return $form3;
			default:
				return $form1;
		}
	}

    /**
      * Функция заполняет массив доступных тарифов для апгрейда учетной записи
      * $tariff_id - тариф для которого искать апгрейд
      *
      * @param bool $need_more_sites
      *
      * @param bool $only_offer_tariff_id
      *
      * @param int $tariff_id ID тарифа
      *
      * @return int
      */

    function show_upgrade_tariffs($need_more_sites = false, $only_offer_tariff_id = false, $tariff_id = null) {
        $upgrade_tariffs = null;
        $subscribe_tariffs = null;
        $min_tariff_id = null;

        if (!$tariff_id) {
            $tariff_id = $this->user_info['fk_tariff'];
        }
		//var_dump($this->tariffs);
		if (isset($this->tariffs[$tariff_id])) {
        	$tariff = $this->tariffs[$tariff_id];
		} else {
        	return $min_tariff_id;
		}

        if ($need_more_sites) {
            $sites_count = $this->user_info['services'];
        }

        $tariff_id_unlimited_websites = null;

        foreach ($this->tariffs as $k => $v){

            //
            // Не даем продлять свой же тариф до окончания действия текущей лицензии.
            //
            /*if ($v['tariff_id'] == $this->user_info['fk_tariff'] && !$this->renew_account && $this->user_info['trial'] == 0 && $v['services'] > 0) {
                continue;
            }*/

            if ($need_more_sites === true) {
                if ($min_tariff_id === null && $v['services'] > $sites_count && $v['billing_period'] == 'Year' && $v['allow_subscribe_panel'] && $v['tariff_id'] != $this->user_info['fk_tariff'])
                    $min_tariff_id = $v['tariff_id'];
            } else {
                if (array_key_exists($k, $this->premium_tariffs) && $this->cost_is_good($v['tariff_id']) && $v['billing_period'] == $tariff['billing_period']) {
                    $upgrade_tariffs[$v['tariff_id']] = $v;

                   if ($min_tariff_id === null)
                        $min_tariff_id = $v['tariff_id'];

                }
                if (array_key_exists($k, $this->premium_tariffs)) {
                    if (($this->user_info['services'] <= $v['services'] || $v['services'] == 0)) {
                        $subscribe_tariffs[$v['tariff_id']] = $v;
                    }
                }
            }

            if ($v['services'] == 0 && $v['allow_subscribe_panel']) {
                $tariff_id_unlimited_websites = $v['tariff_id'];
            }
        }

        if (!$only_offer_tariff_id) {
            $this->page_info['upgrade_tariffs'] = &$upgrade_tariffs;
            $this->page_info['subscribe_tariffs'] = &$subscribe_tariffs;
        }

        if ($min_tariff_id === null && $need_more_sites && $tariff_id_unlimited_websites) {
            $min_tariff_id = $tariff_id_unlimited_websites;
        }

        return $min_tariff_id;
    }

    public function get_bill_api($tariff_id, $period, $valid_till, $upgrade_license = false, $upgrade_discount = false, $add_days = 0) {
        // Удаление устаревших счетов
        $result = $this->db->run(sprintf("delete from bills where fk_user = %d and paid = 0 and datediff(now(), date) >= %d;", $this->user_id, cfg::bills_max_age));
        if ($result > 0) {
            $this->post_log(sprintf("Автоматически удалили %d устаревших счетов пользователя %s (%d).", $result, $this->user_info['email'], $this->user_id));
        }

        $select_unpaid_bills = "select b.* from bills b, users u where b.fk_user = u.user_id and b.paid = 0 and u.email = '%s' and b.fk_tariff = %d and b.promo_id = %d and b.use_balance = %d and b.charge_bonus = %.2f order by b.date desc;";
        $bill = $this->db->select(sprintf($select_unpaid_bills, $this->user_info['email'], $tariff_id, 0, 0, 0));

        // Расчёт стоимости
        $tariff = $this->get_tariff_info($tariff_id);
        // Если апргейд
        if ($upgrade_license) {
            $period = 0;
            $upgrade_license['left_days'] = floor((strtotime($upgrade_license['valid_till']) - time()) / 86400);
            $cost = ($tariff['cost'] / 31 * $upgrade_license['left_days']) - $upgrade_license['balance'];
            if ($cost < 0) $cost = 0;
            $cost_usd = ($tariff['cost_usd'] / 31 * $upgrade_license['left_days']) - $upgrade_license['balance'];

            if (isset($this->user_info['currency_info']) && false) {
                $currency = $this->user_info['currency_info'];
                $cost = $cost_usd * $currency['rate'];
			}
        } else {
            if ($upgrade_discount) {
                $cost = $tariff['cost'] * $period - $upgrade_discount;
                if ($cost < 0) $cost = 0;
                $cost_usd = $tariff['cost_usd'] * $period - $upgrade_discount;
                if ($cost_usd < 0) $cost_usd = 0;
            } else {
//                $cost = number_format($tariff['cost'] * $period, 2, '.', '');
                $cost = $tariff['cost'] * $period;
                $cost_usd = $tariff['cost_usd'] * $period;
            }

            if (isset($this->user_info['currency_info'])) {
                $currency = $this->user_info['currency_info'];
                $cost = $tariff['cost_usd'] * $currency['rate'] * $period;
            }
        }

        if($this->currencyCode=='USD' && $cost!=$cost_usd){
            $cost = $cost_usd;
        }

        $cost = number_format($cost, 2, '.', '');
        $cost_usd = number_format($cost_usd, 2, '.', '');
        if ($bill['cost'] != $cost || $bill['cost_usd'] != $cost_usd) {
            $bill = null;
        }
        // Расчёт срока
        $paid_till = strtotime(sprintf('+%d month', $period), $valid_till);
        if ($upgrade_license) {
            $paid_till = strtotime($upgrade_license['valid_till']);
        }
        if ($bill['paid_till'] != date('Y-m-d', $paid_till)) {
            $bill = null;
        }

        $comment = str_replace(
            array(':CALLS:', ':SERVICES:', ':PAID_TILL:'),
            array($tariff['mpd'], $tariff['services'], date('M d Y', $paid_till)),
            $this->lang['l_bill_comment_2']
        );
        if($add_days){
            $comment .= ' ' . str_replace(':ADD_DAYS:', $add_days, $this->lang['l_bill_add_days']);
        }
        /*$comment = sprintf($this->lang['l_bill_comment_2'],
            $tariff['mpd'], date('M d Y', $paid_till));*/

        // Создание нового счёта
        $bill = null;
        if (!$bill) {
            $sql = sprintf("INSERT INTO bills (`fk_user`, `fk_tariff`, `fk_product_license_id`, `date`, `cost`, `comment`, `till`, `paid`, `period`, `promo_id`, `use_balance`, `charge_balance`, `give_bonus`, `charge_bonus`, `cost_usd`, `auto_bill`, `paid_till`, `free_months`, `extra_package_only`) VALUES (%d, %d, 0, %s, %s, %s, %s, 0, %d, 0, 0, 0, 0, 0.00, %s, 0, %s, 0, 0)",
                $this->user_info['user_id'],
                $tariff_id,
                $this->stringToDB(date("Y-m-d H:i:s")),
                $cost,
                $this->stringToDB($comment),
                $this->stringToDB(date("Y-m-d", $paid_till)),
                $period,
                $cost_usd,
                $this->stringToDB(date("Y-m-d", $paid_till)));
            $bill_id = $this->db->run($sql);
            if (!$bill_id) {
                $this->db->error();
                return;
            }
            $bill = $this->db->select(sprintf(sql::select_bill, $bill_id));
        }

        if ($bill) {
            $bill['cost_usd_cents'] = $bill['cost_usd'] * 100;
            $bill['comment_short'] = $this->lang['l_bill_comment'];
            $bill['comment_short'] = str_replace(
                array(':CALLS:', ':COST:', ':SIGN:', ':PERIOD:', ':SERVICES:'),
                array($tariff['mpd'], $bill['cost'], $this->row_cur['currency_sign'], $this->lang['l_bill_period_' . $period], $tariff['services']),
                $bill['comment_short']
            );
        }

        $this->page_info['bill'] = &$bill;
        return $bill;
    }

    public function get_bill_custom($custom_id) {
        // Удаление устаревших счетов
        $old = $this->db->select(sprintf("select bill_id from bills where fk_user = %d and paid = 0 and datediff(now(), date) >= %d;", $this->user_id, cfg::bills_max_age), true);
        if (count($old)) {
            foreach ($old as $r) {
                $this->db->run(sprintf("update bills_custom set bill_id = NULL where bill_id = %d", $r['bill_id']));
            }
            $result = $this->db->run(sprintf("delete from bills where fk_user = %d and paid = 0 and datediff(now(), date) >= %d;", $this->user_id, cfg::bills_max_age));
            if ($result > 0) {
                $this->post_log(sprintf("Автоматически удалили %d устаревших счетов пользователя %s (%d).", $result, $this->user_info['email'], $this->user_id));
            }
        }

        $bill = $this->db->select(sprintf("SELECT * FROM bills WHERE b.fk_user = %d AND b.paid = 0 AND b.period = 0 ORDER BY b.date DESC", $this->user_info['user_id']));
        $custom = $this->db->select(sprintf("SELECT * FROM bills_custom WHERE id = %d", $custom_id));

        if (!$custom) return;
        $cost_usd = $custom['cost'];
        $cost = $custom['cost'];
        if (isset($this->row_cur) && isset($this->currencyCode)) {
            $cost = number_format($cost_usd * $this->row_cur['usd_rate'], 2, '.', '');
        }

        $comment = sprintf("%s, $%s", $custom['title'], $cost_usd);

        if ($bill) {
            if ($bill['cost'] != $cost || $bill['cost_usd'] != $cost_usd) {
                $bill = null;
            } else if ($bill['comment'] != $comment) {
                $bill = null;
            }
        }

        // Создание нового счёта
        if (!$bill) {
            $u = $this->db->select("SELECT fk_tariff FROM users WHERE user_id=" . $this->user_info['user_id']);
            $sql = sprintf("INSERT INTO bills (`fk_user`, `fk_tariff`, `fk_product_license_id`, `date`, `cost`, `comment`, `till`, `paid`, `period`, `promo_id`, `use_balance`, `charge_balance`, `give_bonus`, `charge_bonus`, `cost_usd`, `auto_bill`, `paid_till`, `free_months`, `extra_package_only`) VALUES (%d, %d, 0, %s, %s, %s, %s, 0, %d, 0, 0, 0, 0, 0.00, %s, 0, %s, %d, 0)",
                $this->user_info['user_id'],
                $u['fk_tariff'],
                $this->stringToDB(date("Y-m-d H:i:s")),
                $cost,
                $this->stringToDB($comment),
                $this->stringToDB(date("Y-m-d")),
                0,
                $cost_usd,
                $this->stringToDB(date("Y-m-d")),
                0
            );
            $bill_id = $this->db->run($sql);
            if (!$bill_id) {
                $this->db->error();
                return;
            }
            $bill = $this->db->select(sprintf(sql::select_bill, $bill_id));

            // Укажим счёт для custom
            $this->db->run(sprintf("UPDATE bills_custom SET bill_id = %d WHERE id = %d", $bill_id, $custom_id));
        }

        if ($bill) {
            $bill['cost_usd_cents'] = $bill['cost_usd'] * 100;
            $bill['comment_short'] = sprintf('Cleantalk payment, $%d', $cost_usd);
        }

        $this->page_info['bill'] = &$bill;
        return $bill;
    }

    public function get_bill_new($tariff_id, $period, $valid_till, $bonus_months = 0) {
        // Удаление устаревших счетов
        $result = $this->db->run(sprintf("delete from bills where fk_user = %d and paid = 0 and datediff(now(), date) >= %d;", $this->user_id, cfg::bills_max_age));
        if ($result > 0) {
            $this->post_log(sprintf("Автоматически удалили %d устаревших счетов пользователя %s (%d).", $result, $this->user_info['email'], $this->user_id));
        }

        $select_unpaid_bills = "select b.* from bills b, users u where b.fk_user = u.user_id and b.paid = 0 and u.email = '%s' and b.fk_tariff = %d and b.promo_id = %d and b.use_balance = %d and b.charge_bonus = %.2f order by b.date desc;";
        $bill = $this->db->select(sprintf($select_unpaid_bills, $this->user_info['email'], $tariff_id, 0, 0, 0));
        $tariff = $this->get_tariff_info($tariff_id);

        if ($tariff['billing_period'] != 'Year' && $period < 12) $bonus_months = 0;

        // Расчёт стоимости
        $cost = number_format($tariff['cost'] * $period, 2, '.', '');
        $cost_usd = number_format($tariff['cost_usd'] * $period, 2, '.', '');
        if (isset($this->row_cur) && isset($this->currencyCode)) {
            $cost = number_format($tariff['cost_usd'] * $this->row_cur['usd_rate'] * $period, 2, '.', '');
        }
        $promokey = false;
        if (isset($_COOKIE['promokey']) && preg_match('/^[a-z0-9\.\-а-я\ \_]{1,16}$/ui', $_COOKIE['promokey'])) {
            $promokey = addslashes($_COOKIE['promokey']);
        }
        if (isset($_GET['promokey']) && preg_match('/^[a-z0-9\.\-а-я\ \_]{1,16}$/ui', $_GET['promokey'])) {
            $promokey = addslashes($_GET['promokey']);
            setcookie('promokey', $promokey, strtotime('+3 days'), '/', $this->cookie_domain);
        }
        if ($promokey) {
            $promo = $this->db->select(sprintf("SELECT * FROM promo WHERE promokey = %s ORDER BY expire DESC LIMIT 1", $this->stringToDB($promokey)));
            if ($promo && strtotime($promo['expire']) > time() && $promo['discount'] > 0) {
                $promo['discount'] = (float)$promo['discount'];
                $cost -= $cost * $promo['discount'];
                $cost_usd -= $cost_usd * $promo['discount'];
                $promokey = $promo['promo_id'];
            } else {
                $promokey = false;
            }
        }
        if ($bill['cost'] != $cost || $bill['cost_usd'] != $cost_usd) {
            $bill = null;
        }

        // Расчёт срока
        if ($tariff['billing_period'] == 'Year') {
            $paid_till = strtotime(sprintf('+%d year', $period), $valid_till);
        } else {
            $paid_till = strtotime(sprintf('+%d month', $period), $valid_till);
        }
        if ($bonus_months) $paid_till = strtotime(sprintf('+%d month', $bonus_months), $paid_till);
        if ($bill['paid_till'] != date('Y-m-d', $paid_till)) {
            $bill = null;
        }

        $comment = str_replace(
            array(':CALLS:', ':SERVICES:', ':SERVICES_TITLE:', ':PAID_TILL:', ':COST:', ':SIGN:'),
            array(
                $tariff['mpd'],
                $tariff['services'],
                $this->number_lng($tariff['services'], $this->lang['l_num_services']),
                date('M d Y', $paid_till),
                ((round($cost) == $cost) ? $cost : number_format($cost, 2, '.', '')),
                $this->row_cur['currency_sign']
            ),
            $this->lang['l_bill_comment_2']
        );
        if ($bonus_months) {
            if ($this->ct_lang == 'ru') {
                $comment .= sprintf($this->lang['l_bill_bonus_s'], $bonus_months, $this->number_lng($bonus_months, array('месяц', 'месяца', 'месяцев')));
            } else {
                $comment .= sprintf($this->lang['l_bill_bonus_s'], $bonus_months, $this->number_lng($bonus_months, array('month', 'months')));
            }
        }
        if ($comment != $bill['comment']) {
            $bill = null;
        }

        // Создание нового счёта
        if (!$bill) {
            $sql = sprintf("INSERT INTO bills (`fk_user`, `fk_tariff`, `fk_product_license_id`, `date`, `cost`, `comment`, `till`, `paid`, `period`, `promo_id`, `use_balance`, `charge_balance`, `give_bonus`, `charge_bonus`, `cost_usd`, `auto_bill`, `paid_till`, `free_months`, `extra_package_only`) VALUES (%d, %d, 0, %s, %s, %s, %s, 0, %d, %d, 0, 0, 0, 0.00, %s, 0, %s, %d, 0)",
                $this->user_info['user_id'],
                $tariff_id,
                $this->stringToDB(date("Y-m-d H:i:s")),
                $cost,
                $this->stringToDB($comment),
                $this->stringToDB(date("Y-m-d", $paid_till)),
                $period,
                $promokey ? $promokey : 0,
                $cost_usd,
                $this->stringToDB(date("Y-m-d", $paid_till)),
                $bonus_months
            );
            $bill_id = $this->db->run($sql);
            if (!$bill_id) {
                $this->db->error();
                return;
            }
            $bill = $this->db->select(sprintf(sql::select_bill, $bill_id));
        }

        if ($bill) {
            $bill['cost_usd_cents'] = $bill['cost_usd'] * 100;
            $bill['comment_short'] = $this->lang['l_bill_comment_3'];
            $bill['comment_short'] = str_replace(
                array(':CALLS:', ':COST:', ':SIGN:', ':PERIOD:', ':SERVICES:'),
                array($tariff['mpd'], $bill['cost'], $this->row_cur['currency_sign'], $this->lang['l_bill_period_' . $period], $tariff['services']),
                $bill['comment_short']
            );
        }

        $this->page_info['bill'] = &$bill;
        return $bill;
    }

    /**
      * Возвращает информацию о счете
      * Создает счет если он отсутствует
      * Если выставлен флаг $rest, то выставляем счет на разницу между существующим балансом и стоимостью тарифа
      *
      * @param int $tariff_id ID тарифа
      *
      * @param bool $rest
      *
      * @param int $period
      *
      * @param int $promo_id
      *
      * @param int $use_bonus
      *
      * @param bool $give_bonus
      *
      * @param string $comment
      *
      * @param int $auto_bill
      *
      * @param int $discount
      *
      * @param string $currency
      *
      * @param string $paid_till
      *
      * @param array $params
      *
      * @return array
      */

	public function get_bill($tariff_id = null, $rest = false, $period = 1, $promo_id = null, $use_bonus = null, $give_bonus = true, $comment = null, $auto_bill = 0, $discount = 0, $currency = 'USD', $paid_till = null, $params = array()){

        $params_default = array(
            'extra_package' => null,
            'no_option' => null,
        );

        $params = array_merge($params_default,$params);

        #
        # Логика удаления устаревших счетов
        #
        $result = $this->db->run(sprintf("delete from bills where fk_user = %d and paid = 0 and datediff(now(), date) >= %d;", $this->user_id, cfg::bills_max_age));
        if ($result > 0)
            $this->post_log(sprintf("Автоматически удалили %d устаревших счетов пользователя %s (%d).", $result, $this->user_info['email'], $this->user_id));

		// Получаем размер текущего бонуса
		$charge_bonus = 0;
		if ($use_bonus){
			$bonus = $this->db->select(sprintf('select bonus from users_bonus where user_id = %d;', $this->user_info['user_id']));
			$charge_bonus = isset($bonus['bonus']) ? $bonus['bonus'] : 0;
		}

		$bill = null;
		$tariff_id = isset($tariff_id) ? $tariff_id : $this->user_info['fk_tariff'];
		$rest = $rest ? 1 : 0;
		$promo_id = isset($promo_id) ? $promo_id : 0;

        $bill = $this->db->select(sprintf(sql::select_unpaid_bills, $this->user_info['email'], $tariff_id, $period, $promo_id, $rest, $charge_bonus));

		// Отказываемся от найденного счета, т.к. он не учитывает текущую промо-акцию
		if ((isset($bill['promo_id']) && isset($promo_id) && $bill['promo_id'] != $promo_id) || (isset($promo_id) && !isset($bill['promo_id'])))
			$bill = null;

		// Если у счета есть промокод, а пользователь сделал запрос без промокода, то такой счет обнуляем
		if (!isset($promo_id) && $bill['promo_id'] !== null)
			$bill = null;

		// Обнуляем счет, если политика остатка не совпадает
		if ((int) $bill['use_balance'] != $rest)
			$bill = null;

		// Обнуляем счет если расходятся балансы
		if (isset($bill['charge_balance']) && $rest && (float) $bill['charge_balance'] !== (float) $this->user_info['balance']){
			$bill = null;
		}

		// Обнуляем счет если расходятся бонусы
		if ((float)(string) $bill['charge_bonus'] !== (float)(string) $charge_bonus)
			$bill = null;

        // Обнуляем счет если расходится политика автоматических платежей
		if ((int) $bill['auto_bill'] != (int) $auto_bill)
			$bill = null;

        // Обнуляем счет если расходится дата окончания подключения к сервису
		if (isset($bill['paid_till']) && $bill['paid_till'] !== $paid_till)
			$bill = null;

        // Обнуляем счет если расходится количество беслпатных месяцев
		if ($bill['free_months'] != $this->free_months && $this->tariffs[$tariff_id]['hosting'] == 0)
			$bill = null;
        // Обнуляем счет, т.к. расходятся доп. пакеты.
        $package_id = null;
        $extra_package_cost = null;
        $extra_package_cost_usd = null;
        if (isset($params['extra_package']['package_id'])) {
            if ((int) $bill['package_id'] !== (int) $params['extra_package']['package_id']) {
                $bill = null;
            }
            $package_id = $params['extra_package']['package_id'];
            $extra_package_cost = $params['extra_package']['cost'] * $period;
            $extra_package_cost_usd = $params['extra_package']['cost_usd'] * $period;
        } else {
            if ($bill['package_id'] !== null) {
                $bill = null;
            }
        }
        // Производим расчет параметров счета
		if (isset($params['fk_product_license_id']) && $params['fk_product_license_id']) {
			$cost_usd = (float) $this->tariffs[$tariff_id]['cost'];
		} else {
			$tariff = $this->get_tariff_info($tariff_id, false);
			$cost = (float) $tariff['cost'] * $period;
			$cost_usd = $cost / cfg::usd_mpc;
			if (isset($tariff['cost_usd']))
				$cost_usd = $tariff['cost_usd'] * ($period ? $period : 1);
		}

        /*
            Если включен флаг extra_package.extra_package_only, то выставляем счет только на сумму доп. услуг.
            fk_tariff делаем = 0, дабы не было переключений.
        */
        $extra_package_only = 0;
        if ($package_id && $params['extra_package']['extra_package_only']) {
            $cost_usd = 0;
            $cost = 0;
            $extra_package_only = 1;
            if (isset($params['extra_package']['cost_usd'])) $extra_package_cost_usd = $params['extra_package']['cost_usd'];
            if (isset($params['extra_package']['cost'])) $extra_package_cost = $params['extra_package']['cost'];
        }


		// Period dicscount for multiple years license.
        if ($this->tariffs[$tariff_id]['billing_period'] == 'Year' && $period > 1 && cfg::discounts_for_years && !$extra_package_only) {
			$cost *= 0.9;
			$cost_usd *= 0.9;
			if ($period == 3) {
				$cost *= 0.9;
				$cost_usd *= 0.9;
			}
            if ($extra_package_cost_usd) {
                $extra_package_cost *= 0.9;
                $extra_package_cost_usd *= 0.9;
				if ($period == 3) { 
					$extra_package_cost *= 0.9;
					$extra_package_cost_usd *= 0.9;
				}
            }
		}

        //
        // Стоимость дополнительных услуг
        //
        if ($extra_package_cost_usd) {
            $cost = $cost + $extra_package_cost;
            $cost_usd  = $cost_usd + $extra_package_cost_usd;
            $cost_package = $extra_package_cost_usd;
			if ($this->ct_lang == 'ru'){
				$cost_package = $extra_package_cost;
			}
            if (isset($this->row_cur['currency']) && $this->ct_lang != 'ru') {
                if ($this->row_cur['currency'] == 'RUB') {
                    $cost = sprintf("%.2f",
                        $cost + $extra_package_cost_usd * $this->row_cur['usd_rate']
                    );
                }
                $cost_package = sprintf("%.2f",
                    $extra_package_cost_usd * $this->row_cur['usd_rate']
				);
				if ($this->ct_lang == 'ru'){
					$cost_package = sprintf("%.2f",
						$extra_package_cost
					);
				}
            }
        }
//	var_dump($cost, $extra_package_cost_usd, $extra_package_cost, $period); exit;

		$user_balance = (float) $this->user_info['balance'];
        // Учитываем скидку если она есть
        if ($discount > 0 && $extra_package_only == 0) {

            if ($currency == 'RUB') {
                $cost = $cost - $discount;

                if ($cost < 0)
                    $cost = 0;

                if (isset($bill['cost']) && abs($bill['cost'] - $cost) > 0.01)
                    $bill = null;
            }
            if ($currency == 'USD') {
                $cost_usd = $cost_usd - $discount;

                if ($cost_usd < 0)
                    $cost_usd = 0;
            if (isset($bill['cost_usd']) && abs($bill['cost_usd'] - $cost_usd) > 0.01)
                $bill = null;
            }
        }

		// Делаем скидку если указан номер промер-акции
        $cost_discounted = null;
        $cost_discounted_usd = null;
		if (isset($promo_id))
		{
			$promo = $this->db->select(sprintf("select promo_id, promokey, expire, period, discount from promo where promo_id = %d;", $promo_id));
			if (isset($promo['discount'])){
				$discount = (float) $promo['discount'];

                $cost_discounted = $cost * $discount;
                $cost = $cost - $cost_discounted;

                $cost_discounted_usd = $cost_usd * $discount;
                $cost_usd = $cost_usd - $cost_discounted_usd;
			}
		}

		// Сумма для списания с текущего счета
		$charge_balance = 0;
		if ($rest && $user_balance <= $cost)
		{
			$cost = $cost - $user_balance;
			$charge_balance = $user_balance;
		}
		else if ($rest && $user_balance >= $cost)
		{
			$charge_balance = $cost;
			$cost = 0;
		}

		// Уменьшаем сумму счета на размер бонуса
		if ($use_bonus && $charge_bonus > 0 && $cost > 0)
		{
			if ($cost >= $charge_bonus)
			{
				$cost = $cost - $charge_bonus;
			}
			else
			{
				$charge_bonus = $cost;
				$cost = 0;
			}
		}

		//
		// Начисляем бонус если оплата происходит до окончания текущего оплаченного срока
        // И начисление бонуса разрешенно условиями счета
		//
        if ($give_bonus == true) {
            $give_bonus = 0;
            $user_time = time();
            // Но прежде делаем коррекцию на временную зону пользователя
            if (isset($this->user_info['timezone']))
                $user_time = $user_time - (3600 * (int) cfg::billing_timezone) + (3600 * (int) $this->user_info['timezone']);

            // А так же делаем коррекцию сохранненого на сервере времени
            $paid_till_bonus = $this->user_info['paid_till_ts'] - (3600 * (int) cfg::billing_timezone) + 86400;
            if ($user_time < $paid_till_bonus && $cost > 0){
                $give_bonus = $cost * cfg::bonus_rate;
                $this->page_info['give_bonus'] = number_format($give_bonus, 2, '.', ' ');
            }

            $loyal_bonus = 0;
            if (isset($this->bonus_rate[$period]) && $this->bonus_rate[$period] > 0) {
                $loyal_bonus = $cost * $this->bonus_rate[$period];
                $give_bonus = $give_bonus + $loyal_bonus;
                $this->page_info['loyal_bonus'] = number_format($loyal_bonus, 2, '.', ' ');
            }

            $total_bonus = $give_bonus;
            if ($total_bonus > 0)
                $this->page_info['total_bonus'] = number_format($total_bonus, 2, '.', ' ');
        }

		// Обнуляем счет если расходятся бонусы
		if ((float)(string) $bill['give_bonus'] !== (float)(string) $give_bonus){
			$bill = null;
		}

		// Обнуляем счет если расходятся стоимости.
		if (((float) $bill['cost'] !== (float) $cost)
            || ((float) $bill['cost_usd'] !== (float) $cost_usd)
            ){
			$bill = null;
		}

        if ($comment === null) {
            if (isset($this->lang['l_bill_comment'])) {
                $comment = $this->lang['l_bill_comment'];
            } else {
                $comment = messages::bill_comment;
            }
        }
        if ($package_id) {
            if ($extra_package_only) {
                $comment = $params['extra_package']['title'] . '.';
            } else {
                $comment .= sprintf(' %s, %s%s/%s.',
                    $params['extra_package']['title'],
                    $this->tariffs[$tariff_id]['l_currency'],
                    $cost_package,
                    strtolower($this->lang['l_'. strtolower($this->tariffs[$tariff_id]['billing_period'])])
                );
            }
        }

//        var_dump($params, $comment, $this->row_cur['currency']);
        $comment .= sprintf(" %s %s - %s.",
            $this->lang['l_license_period'],
            date("M d Y", time()),
            date("M d Y", strtotime($paid_till))
        );

		if (isset($params['fk_product_license_id']) && $params['fk_product_license_id']) {
			$comment = $params['comment'];
			$bill['comment_short'] = $params['comment_short'];
		} else {
			// Обнуляем счет если расходятся комментарии.
			if ($bill['comment'] != $comment){
				$bill = null;
			}
		}

		// Если нет номера счета, то создаем новый
		if (!isset($bill['bill_id'])){

			// Выставляем счет
			$sql = "insert into bills (`bill_id`, `fk_user`, `fk_tariff`, `fk_product_license_id`, `date`, `cost`, `comment`, `till`, `paid`, `period`, `promo_id`, `use_balance`, `charge_balance`, `give_bonus`, `charge_bonus`, `cost_usd`, `auto_bill`, `pp_profile_id`, `paid_till`, `free_months`, package_id, extra_package_only) values(null, "
					. $this->user_info['user_id'] . ", "
					. $tariff_id . ", "
					. ((isset($params['fk_product_license_id']) && $params['fk_product_license_id']) ? $params['fk_product_license_id'] : '0') . ", '"
					. date("Y-m-d H:i:s") . "', '"
					. (float) $cost . "', '"
					. addslashes($comment) . "', now(), 0, "
					. $period . ", "
					. $promo_id . ", "
					. $rest . ", "
					. $charge_balance . ", "
					. (float) $give_bonus . ", "
					. (float) $charge_bonus . ", "
					. (float) $cost_usd . ", "
					. $auto_bill . ", "
					. 'null' . ", "
					. $this->stringToDB($paid_till) . ", "
					. $this->free_months . ","
					. $this->intToDB($package_id) . ","
					. $this->intToDB($extra_package_only)
					. ");";

            $bill_id = $this->db->run($sql);
			if ( !$bill_id ){
				$this->db->error();
				return;
			}
			$bill = $this->db->select(sprintf(sql::select_bill, $bill_id));
		}

        $bill['billing_period'] = 'Month';
        // 365 - Year
        if ($bill['period'] == 365)
            $bill['billing_period'] = 'Year';

		$cost_full = $this->page_info['tariffs'][$bill['fk_tariff']]['cost'] * $period;
        $cost_local = $bill['cost'];

		$this->page_info['cost_human'] = number_format($bill['cost'], 2, '.', ' ');
		$this->page_info['cost_human_int'] = number_format($bill['cost'], 0, '.', ' ');

		if ($this->ct_lang != 'ru'){
            $cost_full = (float) ($this->tariffs[$bill['fk_tariff']]['cost']) * $period;
            if (isset($this->tariffs[$bill['fk_tariff']]['cost_usd'])) {
                $cost_full = (float) $this->tariffs[$bill['fk_tariff']]['cost_usd'] * $period;
            }

            $cost_local = $bill['cost_usd'];
            if (isset($this->row_cur['usd_rate'])) {
                $cost_local = $cost_local * $this->row_cur['usd_rate'];
            }

		    $this->page_info['cost_human'] = number_format($bill['cost_usd'], 2, '.', ' ');
		    $this->page_info['cost_human_int'] = number_format($bill['cost_usd'], 0, '.', ' ');
		}
//var_dump($bill);exit;
		$this->page_info['cost_human_full'] = number_format($cost_full, 2, '.', ' ');
		$this->page_info['cost_local'] = number_format($cost_local, 2, '.', ' ');

        // Информация о скидке
        if ($promo_id) {
            if ($this->ct_lang == 'ru') {
                $bill['cost_discounted'] = $cost_discounted;
            } else {
                if (isset($this->row_cur['usd_rate'])) {
                    $cost_discounted_usd = $cost_discounted_usd * $this->row_cur['usd_rate'];
                }
                $bill['cost_discounted'] = $cost_discounted_usd;
            }
		    $bill['cost_discounted_human'] = number_format($bill['cost_discounted'], 2, '.', ' ');
        }

        $bill['cost_usd_cents'] = $bill['cost_usd'] * 100;
        if (isset($this->lang['l_bill_comment_short'])) {
            $services_count = $this->tariffs[$bill['fk_tariff']]['services'];
            if ($services_count == 0) {
                $services_count = $this->lang['l_unlimited'];
            }

            $websites_label = $this->lang['l_website'];
            if ($this->tariffs[$bill['fk_tariff']]['services'] > 1 || $this->tariffs[$bill['fk_tariff']]['services'] == 0) {
                $websites_label = $this->lang['l_websites'];
            }

            if ($extra_package_only) {
                $bill['comment_short'] = sprintf("%s, %s%s/%s.",
                    $this->lang['l_extra_package'],
                    $this->tariffs[$bill['fk_tariff']]['l_currency'],
                    $this->page_info['cost_local'],
                    strtolower($this->lang['l_'. strtolower($this->tariffs[$bill['fk_tariff']]['billing_period'])])
                );
            } else {
                $bill['comment_short'] = sprintf($this->lang['l_bill_comment_short'],
                    $services_count,
                    strtolower($websites_label),
                    $this->tariffs[$bill['fk_tariff']]['l_currency'],
                    $this->page_info['cost_local'],
                    $bill['period'],
                    strtolower($this->lang['l_'. strtolower($this->tariffs[$bill['fk_tariff']]['billing_period'])])
                );
            }
            if ($this->ct_lang != 'ru' && $this->options['show_double_prices'] == 1 && $tariff['cost_usd_2'] != '' && cfg::show_double_prices) {
                if ($this->row_cur){
                    $usd_rate_mean = $this->row_cur['usd_rate'];
                    $curr_sign = $this->row_cur['currency_sign'];
                } else {
                    $usd_rate_mean = 1;
                    $curr_sign = '&#36;';
                }
                $bill['comment_short_with_tags'] = sprintf($this->lang['l_bill_comment_short_double'],
                    $services_count,
                    strtolower($websites_label),
                    $this->tariffs[$bill['fk_tariff']]['l_currency'],
                    round($tariff['cost_usd_2']*$usd_rate_mean,2),
                    $this->tariffs[$bill['fk_tariff']]['l_currency'],
                    $this->tariffs[$bill['fk_tariff']]['tariff_cost'],
                    strtolower($this->lang['l_'. strtolower($this->tariffs[$bill['fk_tariff']]['billing_period'])]));
            } else {
                $bill['comment_short_with_tags'] = $bill['comment_short'];
            }
        }

		$MNT_SIGNATURE = md5(cfg::MNT_ID . $bill['bill_id'] . $bill['cost'] . cfg::MNT_CURRENCY_CODE . cfg::MNT_TEST_MODE . cfg::MNT_CODE);
		$this->page_info['bill'] = &$bill;
		$this->page_info['MNT_HOST'] = cfg::MNT_HOST;
		$this->page_info['MNT_ID'] = cfg::MNT_ID;
		$this->page_info['MNT_CURRENCY_CODE'] = cfg::MNT_CURRENCY_CODE;
		$this->page_info['MNT_TEST_MODE'] = cfg::MNT_TEST_MODE;
		$this->page_info['MNT_SIGNATURE'] = &$MNT_SIGNATURE;
		$this->page_info['MNT_paymentSystem_id'] = cfg::MNT_paymentSystem_id;

		return $bill;
	}

    /**
      * Функция формирования условий подключения
      *
      * @param int $tariff
      *
      * @param int $period
      *
      * @param int $tariff_id ID тарифа
      *
      * @param bool $upgrade
      *
      * @param bool $short
      *
      * @return string
      */

    public function get_tariff_conditions($tariff = null, $period, $tariff_id = null, $upgrade = false, $short = false) {
        if (($tariff === null && $tariff_id !== null)) {
            $tariff = $this->get_tariff_info($tariff_id, false);
        }

        $paid_till = $this->user_info['paid_till_ts'] + 86400;

        if (isset($this->user_info['license'])) {
            $license = $this->user_info['license'];
            $paid_till = strtotime($license['valid_till']) + 86400;
        }

        //
        // Логика предоставления бесплатных месяцев подключения при досрочной оплате
        //
        $this->free_months = 0;
        $free_months_string = '';
        $free_months_enabled = false;
        if ($tariff['billing_period'] == 'Year') {
            if ($this->user_info['first_pay_id'] === null && $paid_till > time()) {
                $this->free_months = $this->options['free_months_period'];
                $free_months_enabled = true;
            }
            if ($this->user_info['first_pay_id'] !== null && $paid_till > time() && ($paid_till - (cfg::pay_days * 86400 )) < time()) {
                $this->free_months = (int) $this->options['free_months_period'];
                $free_months_enabled = true;
            }
            /*
                Логика добавления бесплатных месяцев за переход на расширенный тариф
            */
            $upgrade_months_key = isset($_GET['upgrade_months_key']) ? $_GET['upgrade_months_key'] : null;
            if ($upgrade_months_key && $upgrade_months_key == $this->options['upgrade_months_key']) {
                $row = $this->db->select(sprintf("select free_months from bonuses where bonus_name = 'upgrade';"));
                if (isset($row['free_months'])) {
                    $this->free_months = $this->free_months + $row['free_months'];
                }
            }
        }

        if ($this->free_months && $tariff['billing_period'] == 'Year') {
            $free_months_string = sprintf(" (+%d %s)", $this->free_months, $this->lang['l_months_for_free']);
        }

        /*
            Учитываем продления по мимо оплаченных счетов
        */
        $sql = sprintf("select bill_id,paid_till from pays p left join bills b on p.fk_bill = b.bill_id where b.fk_user = %d and paid = 1 order by paid_till desc limit 1;",
            $this->user_id
        );
		$row = $this->db->select($sql);
        if (isset($row['paid_till']) && $upgrade && $tariff['billing_period'] == 'Year') {
            $paid_till_bill = strtotime($row['paid_till']);
            $paid_till_diff = $paid_till - $paid_till_bill;
            if (($paid_till_diff / 86400 / 30) > 1){
                $this->free_months = $this->free_months + $paid_till_diff / 86400 / 30;
                $free_months_string = sprintf(" (+%d %s)", $this->free_months, $this->lang['l_months_for_free']);
            }
        }
//		var_dump($sql, $row, $this->free_months, date(DATE_ATOM, $paid_till), $paid_till_diff, $upgrade, $row, $tariff);exit;

        if ((int) $tariff['period'] == 31){
            $extend_string = sprintf("+%d month", $period);
        }else{
            $extend_string = sprintf("+%d days %d months", $tariff['period'] * $period + 1, $this->free_months);
        }

        if ($paid_till >= time() && isset($this->user_info['tariff']) && $this->user_info['tariff']['cost'] > 0 && isset($this->user_info['first_pay_id']) && $upgrade === false)
            $paid_till = strtotime($extend_string, $paid_till);
        else
            $paid_till = strtotime($extend_string, time());

        $bill_start = time();
        if (isset($this->user_info['tariff']) && $this->user_info['tariff']['cost'] != 0 && isset($this->user_info['first_pay_id']) && $upgrade === false)
            $bill_start = $this->user_info['paid_till_ts'];

        $this->page_info['tariff_conditions'] = strip_tags(sprintf($this->lang['l_service_general'], mb_strtolower($this->tariffs[$tariff['tariff_id']]['info_charge']), $free_months_string));

        if ($short) {
            $this->page_info['tariff_conditions_short'] = sprintf($this->lang['l_service_general_short'], mb_strtolower($this->tariffs[$tariff['tariff_id']]['info_charge']), $free_months_string . '.');
        }

        $per_website_part = '';
        $this->page_info['license_dates'] = sprintf("%s - %s %s", date("M d Y", $bill_start), date("M d Y", $paid_till), $free_months_string);

        if ($this->tariffs[$tariff['tariff_id']]['services'] > 0) {
            $this->page_info['license_package_info'] = $this->lang['l_service_general_short_website'];
        } else {
            $this->page_info['license_package_info'] = sprintf($this->lang['l_service_general_short'], mb_strtolower($this->tariffs[$tariff['tariff_id']]['package_info_short_wo_cost']), $per_website_part);
        }

		if ($free_months_enabled) {
			$security_free_part = '';
			if ($this->is_security_free_allowed()) {
//				echo 123;exit;
				$security_free_part = '<br />'; 
				$security_free_part .= sprintf($this->lang['l_security_free_notice']);

				$this->page_info['show_security_free_billing_offer'] = true; 
			}
            $this->page_info['free_months_notice'] = sprintf($this->lang['l_free_months_notice'], $this->free_months);
            $this->page_info['free_months_notice'] .= $security_free_part; 
        }

        return $paid_till;
    }

	public function is_security_free_allowed() {
		$allow = false;
		if ($this->options['show_security_free'] == 0) {
			return $allow;
		}

		$sql = sprintf("select id from users_licenses where pay_id is not null and user_id = %d and product_id = 4;",
			$this->user_id
		);
		$row = $this->db->select($sql);
		if (!isset($row['id']) || true) {
			$allow = true;
		}
		return $allow;
	}

    /**
      * Функция подключения языковых файлов страниц
      *
      * @param string $user_lang Язык пользователя
      *
      * @param string $class
      *
      * @param string $prefix
      *
      * @return bool
      */

	function get_lang($user_lang = 'en', $class = 'main', $prefix = ''){

		$lang = null;
		if (isset($this->lang))
			$lang = $this->lang;

        $lang_file = $prefix . "language/" . $user_lang . "/" . $class . ".php";
        if (!file_exists($lang_file)) {
            $lang_file = null;
        }
        if (!$lang_file) {
            $lang_file = $prefix . "language/" . $this->options['default_lang'] . "/" . $class . ".php";
            if (!file_exists($lang_file)) {
                $lang_file = null;
            }
        }

        if ($lang_file) {
		    include_once $lang_file;
        }

		if (isset($lang)){
			$this->lang = &$lang;
			$this->page_info = array_merge($this->page_info, $lang);
		}

		return true;
	}

    /**
    * Возвращает информацию о сервисе
    */
/*
    public function get_service_info($service_id = null){
        if ($service_id == null && isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
            $service_id = $_REQUEST['service_id'];
        }

        if ($service_id == null)
            return false;

        return $this->db->select(sprintf("select service_id, name, hostname, engine, stop_list_enable, sms_test_enable, auth_key, response_lang, created, updated from services where service_id = %d and user_id = %d;", $service_id, $this->user_id));
    }
*/

    /**
     * @param $tariff_id int ID тарифа
     * @return int
     */
    protected function get_upgrade_discount_license($tariff_id) {
        $discount = 0;
        $license = $this->user_info['license'];
        $paid_till_ts = strtotime($license['paid_till']);

        // Если текущая лицензия не закончилась и была оплачена
        if ($license['fk_tariff'] != $tariff_id && $paid_till_ts > time() && $license['bill_id'] && $license['pay_id']) {
            $license_bill = $this->db->select(sprintf("SELECT p.date, p.cost, b.period FROM pays p LEFT JOIN bills b ON b.bill_id = p.fk_bill WHERE p.pay_id=%d", $license['pay_id']));
            $license_bill['date_ts'] = strtotime($license_bill['date']);

            // Посчитаем неиспользованный период
            $upgrade_info = array();
            $upgrade_info['not_used_period'] = ($license_bill['date_ts'] + ((86400 * $license['tariff']['period']) * $license_bill['period'])) - time();
            $upgrade_info['not_used_period'] = $upgrade_info['not_used_period'] / (86400 * $license_bill['period']);

            //if ($license_bill['cost'] > 0 && $not_used_period > 0) {

            //}
        }

        return $discount;
    }

    /**
      * Функция рассчитывает скидку при переходе на новый тариф исходя из неизрасходованного остатка по текущему тарифу
      *
      * @param int $user_id ID пользователя
      * @param int $tariff_id ID тарифа
      * @return int
      */
    public function get_upgrade_discount($user_id = null, $tariff_id = null, $product_id = 1){
        $discount = 0;

        if ($user_id === null || $tariff_id === null)
            return $discount;

        /*if ($this->user_info['license']) {
            return $this->get_upgrade_discount_license($tariff_id);
        }*/

        if ($tariff_id != $this->user_info['fk_tariff'] && $this->user_info['paid_till_ts'] > time() && $this->user_info['tariff']['cost'] > 0) {
            $sql = sprintf("
                select b.bill_id, p.pay_id, b.period bill_period, b.package_id, p.date, t.period as tariff_period, t.cost as tariff_cost, t.cost_usd as tariff_cost_usd, t.billing_period, b.comment, p.cost as pay_cost, p.gross, p.currency from bills b left join pays p on p.fk_bill = b.bill_id left join tariffs t on t.tariff_id = b.fk_tariff where paid = 1 and b.fk_user = %d and t.product_id = %d order by p.date desc limit 1;
            ", 
                $this->user_id,
                $product_id
            );
			$last_bill = $this->db->select($sql);
//			var_dump($last_bill, $sql);exit;
            if (isset($last_bill['bill_id']) && $last_bill['pay_cost'] > 0) {
                $last_bill['date_ts'] = strtotime($last_bill['date']);
                $upgrade_info['not_used_period'] = ($last_bill['date_ts'] + ((86400 * $last_bill['tariff_period']) * $last_bill['bill_period'])) - time();
                $upgrade_info['not_used_period'] = $upgrade_info['not_used_period'] / ((86400 * $last_bill['tariff_period']));

                // Если неиспользованный срок отрицательный, то возвращаем нулевую скиду.
                // Т.к. скорее всего время действия услуги было выставленно административно.
                if ($upgrade_info['not_used_period'] < 0)
                    return $discount;

                $upgrade_info['period_cost'] = $last_bill['gross'] / $last_bill['bill_period'];
                $discount = $upgrade_info['not_used_period'] * $upgrade_info['period_cost'];
                if ($last_bill['currency'] == 'RUB') {
                } else {
                    if (isset($this->row_cur['usd_rate'])) {
                        $upgrade_info['period_cost'] = $upgrade_info['period_cost'] * $this->row_cur['usd_rate'];
                    }
                }

                $upgrade_info['discount'] = $upgrade_info['not_used_period'] * $upgrade_info['period_cost'];

                $upgrade_info['period_cost'] = number_format($upgrade_info['period_cost'], 2, '.', ' ');
                $upgrade_info['not_used_period'] = number_format($upgrade_info['not_used_period'], 2, '.', ' ');
                $upgrade_info['discount'] = number_format($upgrade_info['discount'], 2, '.', ' ');
                $upgrade_info['comment'] = $last_bill['comment'];
                $upgrade_info['billing_period'] = $this->lang['l_' . strtolower($last_bill['billing_period'])];
//var_dump($last_bill, $upgrade_info, $this->product_id, $sql);exit;
                $this->page_info['show_recalc'] = true;
                $this->page_info['upgrade_info'] = &$upgrade_info;
            }
        }

        return round($discount, 2);
    }

    /**
      * Загружает в память список активных приложений для CMS
      *
      * @param bool $set_last_modified
      *
      * @return bool
      */

    public function get_apps($set_last_modified = true){
        $this->memcache = &$this->mc;
        $cache_label = $this->ct_lang . '_main_apps';
        $apps = $this->memcache->get($cache_label);
        if ($apps === false) {
            $rows = $this->db->select("
                select engine, info, app_file, lang, l.seo_url from platforms p left join links l on p.link_id = l.id where with_app = 1 order by platform_id;
                ", true);
            foreach ($rows as $k => $v) {
                $lang_supported = 0;
                foreach (explode(",", $v['lang']) as $lang) {
                    if ($this->ct_lang == $lang)
                        $lang_supported = 1;
                }

                // Пропускаем приложенния если для него нет языковой локализации посетителя сайта
                if ($lang_supported === 0)
                    continue;

                if ($v['app_file'] !== null) {
                    foreach (explode(",", $v['app_file']) as $file) {
                        $link_name = $file;
                        if (preg_match("/\/([a-z0-9\-_\.]+)$/i", $file, $matches))
                            $link_name = $matches[1];
                        $f['app_file'] = $file;
                        $f['link_name'] = $link_name;
                        $v['files'][] = $f;
                    }
                }

                $v['lp_url'] = $v['engine'];
                if (isset($v['seo_url']))
                    $v['lp_url'] = $v['seo_url'];

                // ID продуктивного клиентского приложения
                $v['productive_app_id'] = null;

                $apps[$v['engine']] = $v;
            }
            $this->memcache->set($cache_label, $apps, null, cfg::memcache_store_timeout_user);

        }
        $this->page_info['apps'] = &$apps;
        $this->apps = &$apps;
        return true;
    }

    /**
      * Возвращает массив всех часовых поясов
      *
      * @return array
      */
    public function get_timezones() {
        $abbrs = timezone_abbreviations_list();
        $tzs = null;
        foreach ($abbrs as $abbr => $ids) {
            foreach ($ids as $id) {
                $hour = round($id['offset'] / 3600, 1);

                $hour_label = $hour;
                if ($hour > 0)
                    $hour_label = "+$hour";

                $tzs[$hour] = "UTC " . $hour_label;
            }
        }
        asort($tzs);
        return $tzs;
    }

    public function timezones_generator() {
        $this->get_lang($this->ct_lang, 'Time');
        $timezones = array();
        foreach ($this->lang['l_timezones'] as $value) {
            $hours = $value[0];
            $minutes = 0;
            if (is_float($value[0]) || is_double($value)) {
                $hours = floor($value[0]);
                $minutes = ($value[0] - $hours) * 60;
            }
            if ($value[0]) {
                $timezones[] = array(
                    'value' => $value[0],
                    'title' => sprintf("(GMT%s%02d:%02d) %s", ($value[0] > 0 ? '+' : '-'), abs($hours), abs($minutes), $value[1])
                );
            } else {
                $timezones[] = array(
                    'value' => $value[0],
                    'title' => sprintf("(GMT) %s", $value[1])
                );
            }
        }
        return $timezones;
    }

    /**
      * Логика выдачи сообщения об отзыве
      *
      * @param bool $need_cookies
      *
      * @return bool
      */

    public function show_review($need_cookies = false) {
        $skip_review = false;
        // Если есть бонус review то баннер с отзывом не выводим
        $this->page_info['revbon'] = 1;
        if (isset($_COOKIE['revbon']) && $_COOKIE['revbon'] == 0)
				$this->page_info['revbon'] = 0;

		if ($this->page_info['revbon'] == 1) {
				$sql = sprintf('select bonus_id from users_bonuses where user_id = %d and bonus_name = %s;',
					$this->user_id,
					$this->stringToDB('review')
			);
			$row = $this->db->select($sql);
			if (isset($row['bonus_id'])) {
				$this->page_info['revbon'] = 0;
				$skip_review = true;
			}
		}
        $this->page_info['show_review'] = 0;
        $this->page_info['need_review_notice'] = sprintf($this->lang['l_need_review_notice'], $this->options['review_months']);

        if (isset($_COOKIE['review_hint']) && $_COOKIE['review_hint'] == 0 && $need_cookies === true) {
            if ($this->user_info['show_review'] == 1) {
                $this->db->run(sprintf("update users set show_review = 0 where user_id = %d;", $this->user_info['user_id']));
                $this->post_log(sprintf("Пользователь %s (%d) отключил баннер отзывов.", $this->user_info['email'], $this->user_info['user_id']));
            }
            $skip_review = true;
		}else{
            if ($this->user_info['show_review'] == 0) {
                $this->db->run(sprintf("update users set show_review = 1 where user_id = %d;", $this->user_info['user_id']));
                $this->post_log(sprintf("Автоматически включен баннер отзывов пользователю %s (%d).", $this->user_info['email'], $this->user_info['user_id']));
            }
        }
		if ($this->renew_account) {
			$skip_review = true;
		}

        $review_string = '';
        if (isset($this->user_info['licenses'][$this->cp_mode]['tariff']['billing_period'])
           && $this->user_info['licenses'][$this->cp_mode]['tariff']['billing_period'] != 'Year' 
            ){
            $skip_review = true;
		}

        if (isset($this->user_info['licenses'][$this->cp_mode]['tariff']['cost_usd'])
           && $this->user_info['licenses'][$this->cp_mode]['tariff']['cost_usd'] >= 46 
            ){
            $skip_review = true;
		}

        if (!$skip_review && time() - $this->user_info['created_ts'] > 86400 * cfg::review_timeout && $this->user_info['first_pay_id'] !== null && isset($this->options['review_links'])) {
            $s_count = $this->db->select(sprintf("select s.engine from services s where s.connected is not null and s.user_id = %d;", $this->user_id), 1);

            if ($s_count) {
                $tools = new CleanTalkTools();
                $review_string = '';
                foreach (explode(";", $this->options['review_links']) as $v) {
                    if ($v == '') {
                        continue;
                    }

                    $review = explode(",", $v);
                    $r[$review[0]]['link'] = $review[1];
                    $r[$review[0]]['domain'] = $tools->get_domain($review[1]);
                }

                $added = null;
                $skip_cms = null;
                $skip_cms_strict = null;
                foreach (explode(",", cfg::skip_cms_for_review) as $v) {
                    $skip_cms[$v] = true;
                }

                $skip_cms_strict = $this->skip_cms_by_lang($this->ct_lang, cfg::skip_cms_for_review_lang, $skip_cms_strict);

                // Запрещаем выдавать бонус
                $no_bonus = false;
                //
                // Запрещаем выводить звезды, иначе их постят прямо в отзывах,
                // http://www.simplemachines.org/community/index.php?topic=521206.msg3866593#msg3866593
                //
                $no_stars = false;
                $review_link = '';
                foreach ($s_count as $v) {

                    // Пропускаем CMS согласно конфигурации
                    if (isset($skip_cms_strict[$v['engine']])) {
                        continue;
                    }
                    // Включаем особые условия вывода банера
                    if (isset($skip_cms[$v['engine']])) {
                        $no_bonus = true;
                        $this->no_bonus = true;
                    }
                    if (isset($r[$v['engine']]) && !isset($added[$v['engine']]) && ($review_string == '' || isset($skip_cms[$v['engine']]))) {
                        $review_string = $r[$v['engine']]['domain'];
                        $review_link = $r[$v['engine']]['link'];
                        $added[$v['engine']] = 1;
                    }
                    $no_stars = (in_array($v['engine'], explode(',', cfg::skip_stars_for_review))) ? true : false;
                }

                if ($review_string != '') {
                    if ($no_bonus) {
                        $this->page_info['need_review'] = sprintf($this->lang['l_need_review_no_bonus2'], $review_string);
						$this->page_info['no_bonus'] = true;
                    } else {
                        $this->page_info['need_review'] = sprintf($this->lang['l_need_review2'], $review_string, $this->options['review_months']);
                    }
                    if ($no_stars) {
                        $this->page_info['need_review'] = sprintf($this->lang['l_need_review_share'], $review_string, $this->options['review_months']);
                    }

                    $this->page_info['no_stars'] = $no_stars;
                    $this->page_info['no_bonus'] = $no_bonus;
                    $this->page_info['review_link'] = $review_link;
					$this->page_info['show_review'] = 1;
                    return true;
                }
            }
        }
//var_dump($skip_review, $review_string, $no_bonus, $this->page_info['need_review'], $this->page_info['revbon'], $this->page_info['ct_lang']);exit;

//var_dump($skip_review, $s_count, $no_stars, $review_string);exit;
        if ($this->user_info['show_review'] == 0) {
            return false;
        }

        return false;
    }

    /**
      * Выводит информацию о хостингах с бесплатным CleanTalk
      *
      * @return void
      */

    public function show_free_hostings() {
//        if (cfg::show_free_cleantalk_with_hosting && $this->ct_lang == 'ru') {
        if (cfg::show_free_cleantalk_with_hosting) {
            $links_tpl = '<a href="/go?url=%s" class="grey_text" target="_blank">%s</a>';
            $links = array();
            $links_part = '';
            $user_lang = $this->ct_lang;
            if (!isset($this->free_cleantalk_with_hosting[$user_lang])) {
                $user_lang = 'en';
            }
            $tools = new CleanTalkTools();
			$this->remote_addr = $tools->get_remote();
            foreach ($this->free_cleantalk_with_hosting as $lang => $v) {
                if ($user_lang != $lang) {
                    continue;
                }
                foreach ($v as $v2) {
                    if ($links_part != '') {
                        $links_part .= ', ';
                    }
                    $domain = $tools->get_domain($v2);
                    $links_part .= sprintf($links_tpl,
                        $v2,
                        $domain
                    );
                }
            }
            if ($links_part != '') {
                $this->page_info['free_cleantalk_message'] = sprintf($this->lang['l_free_cleantalk_message'],
                    $links_part
                );
            }
        }

        return null;
    }

    /**
      * Функция возвращает список вебсайтов пользователя
      *
      * @param int $user_id
      *
      * @return array
      */

    public function get_websites($user_id = null) {
        $websites = null;
        if ($user_id) {
            $row = $this->db->select(sprintf("select hostname, name, service_id from services where user_id = %d and product_id = %d;", $user_id, cfg::product_antispam), true);
            $websites = '';

            if (count($row) > 10) {
                $websites = sprintf($this->lang['l_websites_common'], count($row));
            } else {
                foreach ($row as $v) {
                    if ($websites != '')
                        $websites .= ', ';

                    if (isset($v['hostname'])) {
                        $websites .= $v['hostname'];
                    } else {
                        $websites .= '#' . $v['service_id'];
                    }
                }
            }
        }

        return $websites;
    }

	
	/**
	 * Функция разрешает авторизаци по токену при условии количества сайтов менее N.
	 * @param null
	 * @return bool
	 */
	public function allow_token_auth_by_sites($user_id = null) {
		$allow = true;
		if ($user_id === null) {
			if (isset($this->user_id)) {
				$user_id = $this->user_id;
			} else {
				return $allow;
			}	
		}
		if ($this->staff_mode) {
			return $allow;
		}
		$sql = sprintf("select count(*) as count from services where user_id = %d and product_id = %d;",
				$user_id,
				$this->cp_product_id
		);
		$row = $this->db->select($sql);
		if (isset($row['count']) && $row['count'] > cfg::max_sites_for_token_auth) {
			$allow = false;
		}
		return $allow;
	}

    /**
      * Функция возвращает user_id, email по user_token
      *
      * @param string $user_token
      *
      * @return array
      */

    public function get_user_by_token($user_token = null) {

        $user = null;
        $token_pattern = '/^[a-z0-9]+$/i';
        $new_token = false;
        if (!$user_token && isset($_GET['user_token']) && preg_match($token_pattern, $_GET['user_token'])) {
            $user_token = $_GET['user_token'];
            $new_token = true;
        }

        if (isset($_COOKIE['user_token']) && preg_match($token_pattern, $_COOKIE['user_token']) && !isset($_GET['user_token'])) {
            if (!$user_token) {
                $user_token = $_COOKIE['user_token'];
            } else {
                $new_token = false;
            }
        }
        if (!$user_token) {
            return $user;
        }
		$sql = sprintf("select u.user_id, u.email, enable_token_auth from users u where user_token = %s;",
			$this->stringToDB($user_token)
		);
		$user = $this->db->select($sql);
		if (isset($user['user_id']) && $this->link->id
		) {
			$reset_token_email = null;
        	if (isset($_GET['reset_token']) && preg_match("/^\w+$/", $_GET['reset_token'])) {
            	$reset_token_label = 'reset_token:' . $_GET['reset_token'];
				$reset_token_email = apc_fetch($reset_token_label);
			}
//			var_dump($user, $this->staff_mode, $this->link->class, (isset($this->link->class) && $this->link->class == 'Bill' && $user['enable_token_auth'] != 0));exit;
			if ( $user['enable_token_auth'] == 1 
				|| ($user['enable_token_auth'] == -1 && $this->allow_token_auth_by_sites($user['user_id'])) 
				|| $this->staff_mode == 1 
				|| $reset_token_email
			   	|| (isset($this->link->class) && $this->link->class == 'Bill' && $this->allow_token_auth_by_sites($user['user_id']))	
			) {
                $this->token_user = $user;
                // Сохраняем токен в куках, дабы работала авторизация на других страницах
				$this->user_token = $user_token;
                if ($new_token) {
//			var_dump(123, $this->allow_token_auth_by_sites($user['user_id']), $new_token);exit;
                    setcookie('user_token', $user_token, strtotime("+30 days"), '/', $this->cookie_domain);
//                    setcookie('user_token', $user_token, 0, '/', $this->cookie_domain);
                }
			} else {
                setcookie('user_token', null, -1, '/', $this->cookie_domain);
                $url_part = (!empty($user['email'])) ? '?email='.urlencode($user['email']) : '';
				$this->url_redirect('messages/token-auth-disabled'.$url_part);
           		exit;
            }
        } else {
            $user = null;
        }

        return $user;
    }

    /**
      * Функция заполняет параметры для метода оплаты
      *
      * @return void
      */

    public function fill_pay_method_params() {
        if ($this->ct_lang == 'en' && isset($this->payment_methods[$this->options['default_pay_method_en']])) {
            $this->page_info['pay_method'] = $this->options['default_pay_method_en'];

            switch ($this->options['default_pay_method_en']) {
                case '2CO':
                    $this->page_info['post_action'] = cfg::twoco_URL;
                    $this->page_info['2CO_sid'] = cfg::twoco_sid;
                    $this->page_info['2CO_demo'] = cfg::twoco_demo;
                    break;
                default:
                    $this->page_info['post_action'] = '/my/bill/recharge';
                    break;
            }
        }

        $this->page_info['choose_payment_service'] = true;

        return null;
    }

    /**
      * Функция возвращает имя сервиса для вывода на страницах ПУ
      *
      * @param array $service Массив данных о сайте
      *
      * @return string
      */

    public function get_service_visible_name ($service, $show_service_id = false) {
        $service_name = '';
		if(isset($service['service_id']))
            $service_name = '#' . $service['service_id'];
        if (isset($service['hostname'])) {
            $service_name = $service['hostname'];
        }

        if (isset($service['name'])) {
            if (isset($service['hostname'])) {
                $service_name .= ' (' . $service['name'] . ')';
            } else {
                $service_name = ' ' . $service['name'];
            }
		}
		if ($show_service_id === true && $service_name != '#' . $service['service_id']) {
			$service_name = '#' . $service['service_id'] . ' ' . $service_name;
		}

        return $service_name;
    }

    /**
      * Функция частично скрывает email адрес
      *
      * @param string $email
      *
      * @return string
      */

    public function obfuscate_email($email) {
        $em   = explode("@", $email);
        $name = implode(array_slice($em, 0, count($em)-1), '@');
        $len  = floor(strlen($name)/2);

        return substr($name, 0, $len) . str_repeat('*', $len) . "@" . end($em);
    }

    /**
      * Функция проверяет состояние ознкомительной подписки
      *
      * @return void
      */

    public function check_trial() {
        // Если пользователь не авторизован, то пропускаем функцию
        if (!$this->user_id) {
            return null;
        }

        if ($this->user_info['moderate'] == 0 && $this->user_info['trial'] == 1) {
            $this->url_redirect('messages/trial-expired');
        }

        return null;
    }

    /**
      * Подготовка строки к запросу в БД
      *
      * @param string $string
      *
      * @return string
      */

    public function stringToDB($string){
        if ($string === null) {
            $string = 'null';
        } else {
            $string = '\'' . str_replace("'", "\\'", $string) . '\'';
        }

        return $string;
    }

    /**
      * Подготовка числа к запросу в БД
      *
      * @param int $int
      *
      * @return int
      */

    public function intToDB($int = null){
        if ($int === null) {
            $int = 'null';
        } else {
            if (!preg_match("/^\d+$/", $int)) {
                $int = 'null';
            }
        }

        return $int;
    }

    /**
      * Функция возвращает временную метку с учетом часового пояса пользователя
      *
      * @param int $ts
      *
      * @param int $timezone
      *
      * @return int
      */

    public function get_local_timestamp($ts = 0, $timezone = 0) {
        return $ts - (int) cfg::billing_timezone * 3600 + (int) $timezone * 3600;
    }

    /**
      * Список доступных валют
      *
      * @return array
      */

    public function get_currencies() {
        $rows = $this->db->select(sprintf("select currency, currency_sign from currency where datediff(now(), updated) <= %d order by currency;",
            $this->options['currency_max_rate_age']
            ),
            true
        );
        if (!$rows) {
            $rows[0] = array('currency' => 'USD', 'currency_sign' => '&#36;');
        }
        foreach ($rows as $v) {
            $v['name'] = sprintf("%s %s", $v['currency'], $v['currency_sign']);

            $v['selected'] = 0;
            if ($this->page_info['currency'] && $v['currency'] == $this->page_info['currency']) {
                $v['selected'] = 1;
            }

            $currencies[$v['currency']] = $v;
        }
        $this->page_info['currencies'] = $currencies;

        return $currencies;
    }

    /**
      * Возвращает хост сайта для ссылок
      *
      * @return string
      */

    public function get_website_name() {

        $hostname = 'cleantalk.org';

        /*if ($this->ct_lang == 'ru') {
            $hostname = 'cleantalk.ru';
        }*/

        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == 'localhost') {
            $hostname = 'localhost';
        }

        return $hostname;
    }

    /**
      * Возвращает ответ о наличии админского доступа
      *
      * @return bool
      */

    public function get_admin_status() {
        $is_admin = false;
        foreach (explode(",", $this->options['noc_members_ids']) as $v) {
            if ((int)$this->user_id === (int)$v) {
                $is_admin = true;
                break;
            }
        }

        return $is_admin;
    }

    /**
      * Функция вызывает функцию в классе по имени переменной
      *
      * @param string $call_function
      *
      * @param bool $show_page_not_found
      *
      * @return bool
      */

    public function call_class_function($call_function = null, $show_page_not_found = false) {

        if (!$call_function) {
            $call_function = $this->page_url;
        }

        $called = false;
        if ($call_function) {

            // Заменяем - на _ т.к. иначе не вызвать функцию.
            $call_function = preg_replace("/-/", "_", $call_function);

            if (method_exists($this, $call_function)) {
                $this->$call_function();
                $called = true;
            } else {
                if ($show_page_not_found) {
                    header('HTTP/1.0 404 Not Found');
                    $this->page_info['content_message'] = 'Page not found.';
                }
            }
        }

        return $called;
    }

    /**
      * Функция убирает нули из числа и возвращает форматированный вывод
      *
      * @param float $float
      *
      * @return float
      */

    public function avoid_zero($float) {
        // Убираем дробную часть из цифр с 00 после запятой
        if ((int) $float == $float) {
            $float = number_format(round($float), 0, '.', ' ');
        } else {
            $float = number_format($float, 2, '.', ' ');
        }

        return $float;
    }

    /**
      * Функция устанавливает необходимые данные в _SESSION
      *
      * @param array $user
      *
      * @param bool $change_password
      *
      * @return bool
      */

    public function set_session_auth_params($user = null, $change_password = false) {
        if (!$user || !isset($user['user_id'])) {
            return false;
        }

        $_SESSION['user_info']['id'] = $user['user_id'];
        $_SESSION['user_info']['login'] = $user['email'];
        $_SESSION['user_info']['email'] = $user['email'];
        $_SESSION['user_info']['login_time'] = time();
        $_SESSION['user_info']['onetime_code_change_password_allow'] = $change_password;

        return true;
    }

    /**
      * Функция готовит данные для вывода короткой инструкции на подключение.
      *
      * @param bool $overwrite_template
      *
      * @return bool
      */

    public function show_setup_hint($overwrite_template = true) {

        /*
            Не выдаем инструкцию
        */
        if ($this->user_info['lead_source'] == 'spambots_check') {
            $redirect_url = '';
            if (isset($_COOKIE['records_hash']) && preg_match("/^\w{4,16}$/", $_COOKIE['records_hash'])) {
                $redirect_url = '/spambots-check?packet=' . $_COOKIE['records_hash'];
                $this->page_info['header']['refresh'] = cfg::header_redirect_timeout_default . ';' . $redirect_url;
                $this->page_info['show_spambots_check_hint'] = sprintf($this->lang['l_show_spambots_check_hint'],
                    $redirect_url,
                    cfg::header_redirect_timeout_default
                );
            }
            return false;
        }

        // Инструкция на установку
        if ($this->user_info['services'] == 1 && $this->user_info['requests_total'] == 0) {

            $this->get_lang($this->ct_lang, 'FirstPage');
            $service = $this->db->select(sprintf("select service_id, hostname, auth_key, connected,engine from services where user_id = %d;", $this->user_id));
            if ($service) {
                $service['visible_name'] = $this->get_service_visible_name($service);

            }

            // Страницу выводим только при условии, что сайт действительно не подключен.
            if ($service && $service['connected'] == null) {
                $test_email = explode(",", $this->options['test_email']);
                $this->page_info['test_email'] = $test_email[0];
                $this->page_info['setup_service'] = $service;
                $this->page_info['setup_hint_title'] = $this->lang['l_setup_hint_title'];
                $this->page_info['show_setup_hint'] = true;
                if ($overwrite_template) {
                    $this->page_info['template'] = 'requests_setup_hint.html';
                }
            }
            return true;
        }
        return null;
    }

    /**
      * Функция формирует данные для подключения дополнительных услуг.
      *
      * @param string $addon_name Название дополнения
      *
      * @return void
      */

    function get_addon($addon_name = '') {
        if ($addon_name == '') {
            return false;
        }

        include_once 'includes/addons.php';
        $ccs_addons = new Addons();
        $order_page_url = sprintf('/my/bill/recharge?package=1&extra_package=1&utm_source=cleantalk.org&amp;utm_medium=button_%s&amp;utm_campaign=control_panel',
            $addon_name
        );
        $order_page_part = sprintf('<a href="%s">%s</a>',
            $order_page_url,
            $this->lang['l_extra_package_title']
        );

        $paid_addons = array();
        if (isset($this->page_info['paid_addons_s'])) {
            $paid_addons = $this->page_info['paid_addons_s'];
        }

        $paid_addons = array_merge($paid_addons, array(
            $addon_name => array(
                'enabled' => false,
                'show_label' => true,
                'trial' => false,
                'notice' => sprintf($this->lang['l_' . $addon_name . '_notice'],
                    $order_page_part
                ),
                'url' => $order_page_url
           ),
        ));
        $paid_addons[$addon_name]['enabled'] = $ccs_addons->is_addon_enable($this->user_info['addons'], $addon_name);

        // На триале даем доступ ко всем услугам.
        if ($this->user_info['trial'] == 1) {
            foreach ($paid_addons as $k => $v) {
                $v['trial'] = true;
                $paid_addons[$k] = $v;
            }
        }
//                var_dump($paid_addons, $this->user_info['addons'], $addon_name);
        $this->page_info['paid_addons'] = json_encode($paid_addons);
        $this->page_info['paid_addons_s'] = $paid_addons; // Smarty version

        return null;
    }

    /**
      * Функция возвращает массив cms, исходя из заданных аргументов
      *
      * @param string $lang
      *
      * @param string $static_list
      *
      * @param array $previous_array
      *
      * @return array
      */

    function skip_cms_by_lang($lang, $static_list, $previous_array = array()) {
        if (!is_array($previous_array)) {
            return $previous_array;
        }

        $skip_cms = array();
        foreach (explode(",", $static_list) as $v) {
            if (preg_match("/^(\w+)\:(\w+)$/", $v, $matches)) {
                if ($lang == $matches[2]) {
                    $skip_cms[$matches[1]] = true;
                }
            }
        }
        return array_merge($skip_cms, $previous_array);
    }

    /**
      * Функция переводит пользователя на форму авторизации по паролю, если у него авторизация по токену.
      *
      * @return void
      */

    function switch_to_password_auth() {
        if (isset($this->token_user['user_id'])) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }

        return null;
    }

    /**
      * Функция возвращает количество неиспользованных бонусов на акаунте.
      *
      * @param array $row
      *
      * @return array
      */

    function get_free_bonuses($row) {
       foreach (explode(",", cfg::skip_cms_for_review) as $v) {
          $skip_cms[$v] = true;
        }

        /*
            Запрещаем бонусы для некоторых CMS с привязкой к языку пользователя.
        */
        $skip_cms = $this->skip_cms_by_lang($this->ct_lang, cfg::skip_cms_for_review_lang, $skip_cms);

        $engine = $this->db->select(sprintf("select s.engine from services s where s.connected is not null and s.user_id = %d;", $row['user_id']), 1);

        $skip_review1 = false;

        // Пропускаем CMS согласно конфигурации
        foreach($engine as $oneengine)
          {
            if (isset($skip_cms[$oneengine['engine']]))
               $skip_review1 = true;
          }

        $skip_review2 = false;

        $this->page_info['optionslinks'] = true;

        if (count($engine)==1)
          {
            if (preg_match('/'.$engine[0]['engine'].'/i', $this->options['review_links']))
              $this->page_info['optionslinks'] = true;
            else
              {
                $this->page_info['optionslinks'] = false;
                $skip_review2 = true;
              }

          }

        $sql = sprintf("select bonus_name,free_months from bonuses where show_before_paid in (%s);",
            $row['first_pay_id'] ? '0,1' : '1'
        );

        if ($row['first_pay_id']) {
            if ($skip_review1 || $skip_review2)
              $sql = sprintf("select bonus_name,free_months from bonuses where show_after_paid = 1 and bonus_name!='review';");
            else
              $sql = sprintf("select bonus_name,free_months from bonuses where show_after_paid = 1;");

        }

        // Логика подсчет доступных бонусов
        $all_bonuses = $this->db->select($sql, true);
		$max_free_months = 0;
        $valid_till_ts = isset($this->user_info['license']['valid_till']) ? strtotime($this->user_info['license']['valid_till']) + 86400 : 0;
        $av_b = array();
        foreach ($all_bonuses as $v) {
            /*
                Выдаем дорогие бонусы только на акаунтах с небольшим количеством сайтов.
            */
            if (isset($this->tariffs[$this->user_info['fk_tariff']]) && (($this->tariffs[$this->user_info['fk_tariff']]['services'] > $this->options['max_services_for_bonus'] || (isset($this->tariffs[$this->user_info['fk_tariff']]) && $this->tariffs[$this->user_info['fk_tariff']]['services'] == 0)) &&
                    $v['free_months'] > $this->options['max_months_for_bonus']
                )) {
                continue;
            }
            if ($v['bonus_name'] == 'early_pay' && $this->user_info['trial'] == 1 && $valid_till_ts < time()) {
                continue;
            }
            $max_free_months = $max_free_months + $v['free_months'];
            $av_b[] = $v['bonus_name'];
        }

        // Список активированных бонусов
        if ($row['first_pay_id']) {
            $sql = sprintf("select bonus_name, free_months from users_bonuses where bonus_name!='early_pay' and user_id = %d;",
                $row['user_id']
            );
        } else {
            $sql = sprintf("select bonus_name, free_months from users_bonuses where user_id = %d;",
                $row['user_id']
            );
        }

        $ub = $this->db->select($sql, true);

        $row['activated_bonuses'] = null;
        $free_months = 0;

        if ($ub) {
            $row['activated_bonuses'] = array();
            foreach ($ub as $v) {
                if (in_array($v['bonus_name'], $row['activated_bonuses'])) {
                    continue;
                }

                if (!in_array($v['bonus_name'], $av_b)) {
                    continue;
                }

                $row['activated_bonuses'][] = $v['bonus_name'];
                $free_months = $free_months + $v['free_months'];
            }
        }

        $free_month_avaible = 0;
        if ($free_months < $max_free_months) {
            $free_month_avaible = $max_free_months - $free_months;
        }

        $row['free_months_activated'] = $free_months;
        $row['free_months_avaible'] = $free_month_avaible;

        $row['user_log_sign'] = sprintf("Пользователь %s (%d).",
            $row['email'],
            $row['user_id']
        );
        $sql = sprintf("select count(*) as count from users_bonuses where user_id = %d order by activated desc;",
            $this->user_id
        );
        $bonuses = $this->db->select($sql);
        $row['bonuses_count'] = 0;
        if ($bonuses) {
            $row['bonuses_count'] = $bonuses['count'];
        }

        return $row;
    }

    /**
      * Функция возвращает список делегированных сайтов пользователя.
      *
      * @param int $user_id ID пользователя
      *
      * @return array
      */

    function get_granted_services($user_id) {
        $granted_services = apc_fetch($this->user_id.'_granted_services');

        if (!$granted_services) {
            $granted_services = $this->db->select(sprintf("select a.service_id, a.grantwrite, b.hostname, b.product_id
                                                           from services_grants a join services b
                                                           on a.service_id = b.service_id
                                                           where a.user_id_granted = %d
                                                           order by a.service_id asc",
                                                           $this->user_id), true);
            apc_store($this->user_id.'_granted_services', $granted_services);
        }

        return $granted_services;
    }

    /**
      * Функция проверяет принадлежит ли сайт с service_id пользователю $user_id
      *
      * @param int $service_id
      *
      * @param int $user_id
      *
      * @return bool
      */

    public function check_service_user($service_id, $user_id){
        $service_user = $this->db->select(sprintf("select user_id from services 
                                                               where service_id = %d;",$service_id));
        if ($user_id == $service_user['user_id'])
            return 1;
        else
            return 0;
    }

    /**
      * Функция вытаскивает из базы уровень доступа для делегированного сайта
      *
      * @param int $service_id
      *
      * @return bool
      */

    public function grant_access_level($service_id) {
        $access_level = $this->db->select(sprintf("select grantwrite
                                                    from services_grants
                                                    where user_id_granted = %d
                                                    and service_id = %d",
                                                    $this->user_id,
                                                    $service_id));
        if (isset($access_level['grantwrite']))
            return $access_level['grantwrite'];
        else
            return 0;
    }

    /**
     * Возвращает числительное в зависимости от текущего ct_lang.
     *
     * Примеры передаваемых числительных:
     *   en: ['site', 'sites']
     *   ru: ['сайт', 'сайта', 'сайтов']
     *
     * @param $number integer Число
     * @param $titles string Числительные
     * @return string Подходящее числительное
     */
    public function number_lng($number, $titles) {
        if ($this->ct_lang == 'ru') {
            $cases = array(2, 0, 1, 1, 1, 2);
            return $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
        }
        return $number > 1 ? $titles[1] : ($number ? $titles[0] : $titles[1]);
    }

    public function currency_cost($cost_usd) {
        $currency = (isset($this->user_info['currency_info'])) ? $this->user_info['currency_info'] : array('id' => 'USD', 'rate' => 1, 'sign' => '$');
        if ($currency['id'] == 'USD') {
            return sprintf('%s%s', $currency['sign'], round($cost_usd * $currency['rate']));
        }
        return sprintf('%s %s', round($cost_usd * $currency['rate']), $currency['sign']);
    }
	
	/**
	 * Функция управляет вывдом сслыки на отзыв.
	 * @return bool
	 */
	public function show_review_notice($sites_on_account = 0) {
		$show = false;

		if ($this->user_info['trial'] != 0) {
			return false;
		}
		if ($sites_on_account <= cfg::show_rate_notice_limit && isset($this->options['review_links'])) {
            $this->page_info['show_rate_notice'] = true;
			foreach (explode(";", $this->options['review_links']) as $v) {
				if ($v == '') {
					continue;
				}
				
				$review = explode(",", $v);
				$this->review_links[$review[0]] = $review[1];
			}
		}

		return $show;
	}

    /**
      * Деструктор
      *
      * @param
      *
      * @return
      */
	function __destruct(){
	}
}
