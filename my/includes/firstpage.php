<?php

include '../includes/helpers/storage.php';
include __DIR__.'/favicon.php';

/**
 * Класс для обработки страниц FirstPage
 */
class FirstPage extends CCS {


	function __construct() {
        if (!$this->db) {
            parent::__construct();
            $this->ccs_init();
        }
		if (!$this->check_access(null, true)) {
            if (!$this->app_mode) {
			    $this->url_redirect('session', null, true);
            }
        }
	}

    /**
      * Отображение страницы
      *
      * @return void
      */
	function show_page(){
        if ($this->app_mode) {
            if ($this->app_return['auth']) {
                $this->app_return['services'] = $this->get_services($this->user_id, false, cfg::product_antispam);
                $this->app_return['timezone'] = $this->user_info['timezone'];
            }
        } else {
            // Показ промо-страницы
            if (isset($_COOKIE['cp_promo']) && !$this->user_info['trial']) {
                $redirect_url = $_COOKIE['cp_promo'];
                if (preg_match("/^[a-z\/\-\_0-9\=\?\.\@\%\&]+$/i", $redirect_url)) {
                    if (!preg_match("/^\//", $redirect_url)) $redirect_url = '/' . $redirect_url;
                } else {
                    $redirect_url = '/my';
                }
                setcookie('cp_promo', '', -1, '/', $this->cookie_domain);
                $template = 'includes/promo.html';

                if (isset($_COOKIE['cp_promo_security'])) {
                    if ($_COOKIE['cp_promo_security'] == 2) {
                        if (!$this->db->select(sprintf("SELECT bonus_id FROM users_bonuses WHERE user_id = %d AND bonus_name = 'review'", $this->user_info['user_id']))) {
                            if ($this->db->select(sprintf("SELECT service_id FROM services WHERE user_id = %d AND engine = 'wordpress' LIMIT 1", $this->user_info['user_id']))) {
                                setcookie('cp_promo_security', '3', time() + 86400 * 60, '/', $this->cookie_domain);
                                $template = 'includes/promo_review.html';
                            }
                        }
                    } else if ($_COOKIE['cp_promo_security'] == 3) {
                        $template = 'includes/promo_review.html';
                    } else {
                        setcookie('cp_promo_security', $_COOKIE['cp_promo_security'] + 1, time() + 86400 * 60, '/', $this->cookie_domain);
                    }
                } else {
                    setcookie('cp_promo_security', '1', time() + 86400 * 60, '/', $this->cookie_domain);
                }

                $this->page_info['redirect_url'] = $redirect_url;
                $this->display($template);
                exit();
            }

            // Автоматический редирект на оплату SSL
            if (isset($_COOKIE['payment_redirect']) && preg_match('/^ssl_certificate:landing\-([1-9])$/', $_COOKIE['payment_redirect'], $matches)) {
                setcookie('payment_redirect', '', time() - 3600, '/');
                header('Location: /my/bill/ssl?period=' . $matches[1]);
                exit;
            }

            if (
                (!isset($this->user_info['license']) || !$this->user_info['license']['pay_id']) &&
                (!isset($_COOKIE['hide_annotation']) || (strpos($_COOKIE['hide_annotation'], $this->cp_mode) === false))
            ) {
                $this->user_info['license_show_annotation'] = true;
            }
            switch ($this->cp_mode) {
                case 'hosting-antispam':
                    $this->show_hoster_page();
                    break;
                case 'api':
                    $this->show_api_page();
                    break;
                case 'security':
                    $this->show_security_page();
                    break;
                case 'ssl':
                    include cfg::includes_dir . 'ssl.php';
                    $class = new Ssl($this);
                    return $class->show_page();
                    //$this->show_ssl_page();
                    break;
                default:
                    $this->show_page_native();
                    break;
            }
        }

        $this->display();

        return null;
    }

    /**
     * Панель управления SSL.
     */
    private function show_ssl_page() {
        $this->get_lang($this->ct_lang, 'Ssl');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'ssl/index.html';
        $this->page_info['head']['title'] = $this->lang['l_ssl_dashboard_title'];
        $this->page_info['container_fluid'] = true;

        $certs = array();
        if (isset($this->user_info['licenses']['ssl'])) {
            $ids = array();
            $licenses = array();
            foreach ($this->user_info['licenses']['ssl'] as $license) {
                if ($license['multiservice_id']) {
                    $ids[] = $license['multiservice_id'];
                    $created_ts = strtotime($license['created']);
                    $expires_ts = strtotime($license['valid_till']);
                    $licenses[$license['multiservice_id']] = array(
                        'created_ts' => $created_ts,
                        'expires_ts' => $expires_ts,
                        'created' => date('M d, Y', $created_ts),
                        'expires' => date('M d, Y', $expires_ts)
                    );
                }
            }
            if (count($ids)) {
                $certs = $this->db->select(sprintf("SELECT * FROM ssl_certs WHERE cert_id IN (%s)", implode(', ', $ids)), true);
                foreach ($certs as $key => $cert) {
                    foreach ($licenses[$cert['cert_id']] as $k => $v) {
                        $certs[$key][$k] = $v;
                    }
                }
            }
        }

        if (count($certs)) {
            $storage = new Storage($this->db);
            $processed = array();

            foreach ($certs as $key => $cert) {
                $domain = $cert['domains'];
                if (substr($domain, 0, 2) == '*.') $domain = substr($domain, 2);

                $favicon_url = 'http://' . $domain . '/favicon.ico';
                if ($favicon = $storage->findByLabel('favicon_ssl_' . $cert['cert_id'])) {
                    if ($favicon->isExpired(86400 * 30)) {
                        $favicon->replace($favicon_url);
                    }
                } else {
                    try {
                        $favicon = $storage->upload($favicon_url, 'ssl' . $cert['cert_id'] . '.ico', 'db');
                        $favicon['label'] = 'favicon_ssl_' . $cert['cert_id'];
                    } catch (Exception $e) {

                        $favicon = $storage->upload('https://cleantalk.org/favicon.ico', 'ssl' . $cert['cert_id'] . '.ico', 'db');
                        $favicon['label'] = 'favicon_ssl_' . $cert['cert_id'];
                    }
                }
                $certs[$key]['favicon'] = $favicon->base64();

                switch ($cert['status']) {
                    case 0:
                        $certs[$key]['status_text'] = 'Awaiting full validation';
                        $certs[$key]['status_hint'] = sprintf('Please see the instructions on email that has been sent to %s.', $cert['dcvEmailAddress']);
                        $certs[$key]['status_class'] = 'text-success';
                        $processed[] = $cert['cert_id'];
                        break;
                    case 1:
                        $certs[$key]['status_text'] = 'Active';
                        $certs[$key]['status_hint'] = '<a href="/help/install-SSL-certificate" target="_blank">SSL Certificate setup manual</a>';
                        $certs[$key]['status_class'] = 'text-success';
                        break;
                    default:
                        $certs[$key]['status_text'] = 'Something wrong';
                        $certs[$key]['status_hint'] = sprintf('Please open a <a href="/my/support">support ticket</a>');
                        $certs[$key]['status_class'] = 'text-danger';
                        break;
                }

                $certs[$key]['years_title'] = $this->lang['l_ssl_years'][$cert['years']];
            }

            $this->page_info['certs'] = $certs;
            $this->page_info['processed'] = json_encode($processed);
            $this->page_info['statuses'] = json_encode(array(
                array('text' => 'Active', 'hint' => '<a href="/help/install-SSL-certificate" target="_blank">SSL Certificate setup manual</a>'),
                array('text' => 'Something wrong', 'hint' => sprintf('Please open a <a href="/my/support">support ticket</a>'))
            ));
        } else {
            $this->page_info['container_fluid'] = false;
            $this->page_info['annotation_hide_top'] = true;
        }
    }

    /**
     * Панель управления Site Security
     */
    private function show_security_page() {
        if(isset($_GET['update_services'])){
            apc_delete('security_services_' . $this->user_info['user_id']);
            exit;
        }

        if (isset($_COOKIE['sort_security'])) {
            $sort = explode(';', $_COOKIE['sort_security']);
            $this->page_info['sort_by'] = $sort[0];
            $this->page_info['sort_order'] = $sort[1];
        }
        else {
            $this->page_info['sort_by'] = 'Hostname';
            $this->page_info['sort_order'] = 'ASC';
        }
        $storage = new Storage($this->db);

        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/index.html';
        $this->page_info['head']['title'] = $this->lang['l_security_dashboard_title'];
        $this->page_info['container_fluid'] = true;
        $this->page_info['scripts'] = array(
            '/my/js/mscan-log.js?v22.02.2018',
            '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js',
            '//cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js',
            '//cdn.datatables.net/plug-ins/1.10.16/sorting/date-de.js',
            '//cdn.datatables.net/plug-ins/1.10.16/type-detection/formatted-num.js',
        );
        $this->page_info['styles'] = array(
            '//cdn.datatables.net/r/bs-3.3.5/jq-2.1.4,dt-1.10.8/datatables.min.css',
            '/my/css/security.css?v07.05.2018',
        );

        // Вывод аннотации

        if (isset($this->user_info['license_show_annotation'])) {
            $this->page_info['annotation'] = array(
                'title' => $this->lang['l_annotation_title'],
                'text' => $this->lang['l_annotation_text']
            );
            $this->page_info['annotation_hide_top'] = true;
        }

        // Wordpress Security productive
        $sql = "SELECT app_id, release_version,download_url FROM apps WHERE engine='wordpress' AND product_id = 4 AND productive = 1";
        if($row = $this->db->select($sql)){
            $this->page_info['release_version'] = $row['release_version'];
            $this->page_info['release_app_id'] = (int)$row['app_id'];
            $this->page_info['release_app_file'] = $row['download_url'];
            $this->page_info['release_link_name'] = basename($row['download_url']);
        }

        // Список сайтов

        $services = apc_fetch('security_services_' . $this->user_info['user_id']);
        if (!$services) {
            $sql = sprintf("SELECT salist.*, release_version FROM (
                    SELECT slist.*, (
                        SELECT app_id FROM services_apps sa 
                        WHERE sa.service_id=slist.sid 
                        ORDER BY sa.last_seen 
                        DESC LIMIT 1) as app_id 
                    FROM (
                        SELECT s.service_id AS sid, moderate_service, auth_key, user_id AS uid, 
                        hostname, name, created, ct_in_list_db, favicon_url, favicon_update, 
                        security_status 
                        FROM services s
                        WHERE user_id=%d AND s.product_id=%d 
                        ORDER BY created ASC) slist
                    ) salist
                LEFT JOIN apps a ON a.app_id=salist.app_id;",
                $this->user_info['user_id'], cfg::product_security
            );

            $tz = (isset($this->user_info['timezone'])) ? (float)$this->user_info['timezone'] : 0;
            $tz -= 5;
            $tz_ts = $tz * 3600;
            $today_begin = strtotime(gmdate('Y-m-d 00:00:00')) + $tz_ts;
            $dates = array(
                'today' => array(
                    date('Y-m-d H:i:s', $today_begin),
                    date('Y-m-d H:i:s', time())
                ),
                'yesterday' => array(
                    date('Y-m-d H:i:s', $today_begin - 86400),
                    date('Y-m-d H:i:s', $today_begin - 1)
                ),
                'week' => array(
                    date('Y-m-d H:i:s', $today_begin - 86400 * 7),
                    date('Y-m-d H:i:s', time())
                )
            );

            $services = array();
            if ($rows = $this->db->select($sql, true)) {
                /*$fw_data = apc_fetch('security_fw_data_' . $this->user_info['user_id']);
                if (!$fw_data) {
                    foreach ($rows as $row) {
                        $data = array();
                        foreach ($dates as $k => $v) {
                            $sql = sprintf(
                                "SELECT service_id AS sid, user_id AS uid,
                            (SELECT COUNT(*) FROM services_private_list WHERE product_id=%d AND service_id=sid AND status='deny' AND created BETWEEN '%s' AND '%s') AS private_list_count,
                            (SELECT COUNT(*) FROM bl_ips_security WHERE in_list=1 AND submited BETWEEN '%s' AND '%s') AS in_list,
                            (SELECT COUNT(*) FROM bl_ips WHERE in_sfw=1 AND submited BETWEEN '%s' AND '%s') AS in_sfw
                            FROM services WHERE service_id=%d",
                                cfg::product_security, $v[0], $v[1], $v[0], $v[1], $v[0], $v[1], $row['sid']
                            );
                            if ($row_data = $this->db->select($sql)) {
                                $data[$k] = $row_data['private_list_count'] + $row_data['in_list'] + $row_data['in_sfw'];
                            }
                        }
                        $fw_data[$row['sid']] = $data;
                    }
                    apc_store('security_fw_data_' . $this->user_info['user_id'], $fw_data, 3600);
                }*/
                foreach ($rows as $row) {
                    $stats = array();
                    foreach ($dates as $k => $v) {
                        $sql = sprintf('SELECT
                        service_id AS sid, user_id AS uid,
                        (SELECT COUNT(*) FROM services_security_log WHERE user_id=uid AND service_id=sid AND event IN (\'auth_failed\', \'invalid_username\', \'invalid_email\') AND submited BETWEEN \'%1$s\' AND \'%2$s\') AS attacks,
                        (SELECT COUNT(*) FROM services_security_log WHERE user_id=uid AND service_id=sid AND event IN (\'login\', \'logout\') AND submited BETWEEN \'%1$s\' AND \'%2$s\') AS logins,
                        (SELECT COUNT(*) FROM services_security_log WHERE user_id=uid AND service_id=sid AND event IN (\'view\', \'View\') AND submited BETWEEN \'%1$s\' AND \'%2$s\') AS audit,
                        (SELECT COALESCE(SUM(hits),0) FROM security_firewall_logs WHERE user_id=uid AND service_id=sid AND status=\'ALLOW\' AND updated BETWEEN \'%1$s\' AND \'%2$s\') AS allow,
                        (SELECT COALESCE(SUM(hits),0) FROM security_firewall_logs WHERE user_id=uid AND service_id=sid AND status LIKE \'DENY%%\' AND updated BETWEEN \'%1$s\' AND \'%2$s\') AS deny
                        FROM services
                        WHERE service_id=%3$d',
                            $v[0], $v[1], $row['sid']
                        );
                        if ($row_stat = $this->db->select($sql)) {
                            $stats[$k] = array(
                                'attacks' => $row_stat['attacks'],
                                'logins' => $row_stat['logins'],
                                'audit' => $row_stat['audit'],
                                'allow' => $row_stat['allow'],
                                'deny' => $row_stat['deny'],
                            );
                        }
                        //$stats[$k]['data'] = (isset($fw_data[$row['sid']]) && $row['ct_in_list_db']) ? $fw_data[$row['sid']][$k] : '0';
                    }
                    $favicon = Favicon::get_icon_url($row);    
                    $scan = array();
                    $sql = sprintf("SELECT log_id as id, submited, result, total_core_files as 'total', failed_files, unknown_files 
                                    FROM security_mscan_logs WHERE service_id=%d ORDER BY submited DESC LIMIT 1",$row['sid']);
                    if ($row_scan = $this->db->select($sql)) {
                        if(!empty($row_scan['failed_files'])){
                            $failed = count((array)json_decode($row_scan['failed_files']));
                        }else{
                            $failed = 0;
                        }
                        if(!empty($row_scan['unknown_files'])){
                            $unknown = count((array)json_decode($row_scan['unknown_files']));
                        }else{
                            $unknown = 0;
                        }
                        $scan = array(
                            'id' => $row_scan['id'],
                            'date' => date('M d, Y', strtotime($row_scan['submited'])),
                            'result' => $row_scan['result'],
                            'total' => $row_scan['total'],
                            'failed' => $failed,
                            'unknown' => $unknown,
                        );
                    }

                    $security_status = explode(',',$row['security_status']);
                    print_r($security_status);

                    $row['ss_malware_scanner'] = (in_array('malware_scanner', $security_status)) ? 'ENABLED' : 'SUSPENDED';
                    $row['ss_ssl'] = (in_array('ssl', $security_status)) ? 'ENABLED' : 'NOT_INSTALLED';
                    $row['ss_brute_force_protection'] = (in_array('brute_force', $security_status)) ? 'ENABLED' : 'SUSPENDED';
                    $row['ss_site_audit'] = (in_array('site_audit', $security_status)) ? 'ENABLED' : 'SUSPENDED';
                    $row['ss_security_firewall'] = (in_array('fire_wall', $security_status)) ? 'ENABLED' : 'SUSPENDED';

                    $status = $this->lang['l_service_moderate_' . $row['moderate_service']];
                    $ss_malware_scanner = $this->lang['l_service_moderate_' . $row['ss_malware_scanner']];
                    $ss_ssl = $this->lang['l_service_ssl_moderate_' . $row['ss_ssl']];
                    $ss_brute_force_protection = $this->lang['l_service_moderate_' . $row['ss_brute_force_protection']];
                    $site_audit = $this->lang['l_service_moderate_' . $row['ss_site_audit']];
                    $security_firewall = $this->lang['l_service_moderate_' . $row['ss_security_firewall']];

                    $update_available = (!empty($row['app_id']) && isset($this->page_info['release_app_id']) && intval($row['app_id']) < $this->page_info['release_app_id']);
                    $was_updated = false;
                    if(!empty($row['app_id'])){
                        if($remote_call = $this->db->select(sprintf("select * from spbc_remote_calls where service_id = %d AND user_id = %d AND call_action = 'update_plugin' AND plugin_name = 'security' limit 1",$row['sid'],$this->user_id))){
                            if($remote_call['call_result'] && strtotime($remote_call['created'])+5*60<time()){
                                $was_updated = $this->lang['l_was_updated'];
                            }else{
                                $status = $this->lang['l_processing_autoupdate'];
                                $update_available = false;
                            }
                        }
                    }

                    $services[] = array(
                        'id' => $row['sid'],
                        'hostname' => $row['hostname'],
                        'favicon' => $favicon,
                        'name' => $row['name'],
                        'status' => $status,
                        'moderate' => $row['moderate_service'],
                        'key' => $row['auth_key'],
                        'stats' => $stats,
                        'created' => date('M d, Y', strtotime($row['created'])),
                        'scan' => $scan,
                        'update_available' => $update_available,
                        'was_updated' => $was_updated,
                        'app_version' => $row['release_version'],
                        'site_audit' => $site_audit,
                        'security_firewall' => $security_firewall,
                        'ss_malware_scanner' => $ss_malware_scanner,
                        'ss_ssl' => $ss_ssl,
                        'ss_brute_force_protection' => $ss_brute_force_protection,
                    );
                }
            }
            apc_store('security_services_' . $this->user_info['user_id'], $services, 300);
        }
        $this->page_info['services'] = $services;

        if (!count($services)) {
            $this->page_info['annotation'] = array(
                'title' => $this->lang['l_annotation_title'],
                'text' => $this->lang['l_annotation_text2']
            );
            $this->page_info['annotation_hide_top'] = true;
        }

        $pay_banner = true;

        if (isset($this->user_info['license']) && isset($this->user_info['tariff'])) {
            $license = $this->user_info['license'];
            $vt_ts = strtotime($license['valid_till']);

            if ($license['moderate'] && !$license['trial'] && ($vt_ts > time() + 86400 * 14)) {
                $pay_banner = false;
            }

            if ($this->ct_lang == 'ru') {
                $this->page_info['license_info'] = sprintf($this->lang['l_license_info'],
                    $license['tariff']['services'], $this->number_lng($license['tariff']['services'], array('сайт', 'сайта', 'сайтов')),
                    $this->currency_cost($license['tariff']['cost_usd']), date('d.m.Y', $vt_ts));
            } else {
                $this->page_info['license_info'] = sprintf($this->lang['l_license_info'],
                    $license['tariff']['services'], $this->number_lng($license['tariff']['services'], array('site', 'sites')),
                    $this->currency_cost($license['tariff']['cost_usd']), date('M d Y', $vt_ts));
            }

            if (!$license['moderate']) $this->page_info['license_info_alert'] = true;

            // Review bonus
            if (!$license['trial'] && $license['moderate'] && !isset($_COOKIE['security_review_bonus_hide'])) {
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
            }

            // Trial
            if ($license['trial']) {
                $this->page_info['license_trial'] = $this->lang['l_trial_info'];
                $this->page_info['show_reviews'] = true;
            }
        } else {
            $this->page_info['show_reviews'] = true;
        }

        // Баннер об оплате
        if ($pay_banner) {
            $tariff = $this->db->select(sprintf("SELECT tariff_id, services, cost, cost_usd FROM tariffs WHERE product_id = %d AND services >= %d ORDER BY services ASC LIMIT 1", cfg::product_security, count($services)));
            if ($this->ct_lang == 'ru') {
                $cost = $this->currency_cost($tariff['cost_usd']);
                $sites = $this->number_lng($tariff['services'], array('сайт', 'сайта', 'сайтов'));
            } else {
                $cost = $this->currency_cost($tariff['cost_usd']);
                $sites = $this->number_lng($tariff['services'], array('site', 'sites'));
            }

            $this->page_info['pay_banner'] = array(
                'text' => sprintf($this->lang['l_pay_banner'], $tariff['services'], $sites, $cost),
                'button' => $this->lang['l_pay'],
                'link' => sprintf('/my/bill/security?period=%d&product=%d', cfg::security_period_default, $tariff['tariff_id']),
                'bonus' => (isset($this->user_info['license']) && $this->user_info['license']['trial']) ? $this->lang['l_pay_banner_bonus'] : false
            );
        }
    }

    /**
     * Панель управления API
     *
     * https://basecamp.com/2889811/projects/9012184/todos/268540703
     * Vladimir Abalakov
     */
    private function show_api_page() {
        $this->get_lang($this->ct_lang, 'Api');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'api/index.html';
        $this->page_info['head']['title'] = $this->lang['l_api_dashboard_title'];
        $this->page_info['container_fluid'] = true;

        // Вывод аннотации

        if (isset($this->user_info['license_show_annotation'])) {
            $this->page_info['annotation'] = array(
                'title' => $this->lang['l_annotation_title'],
                'text' => $this->lang['l_annotation_text']
            );
        }

        $api = array(
            'enabled' => false,
            'methods' => array()
        );

        // API KEY
        $row = $this->db->select(sprintf('select auth_key as `value` from services where user_id=%d and product_id=%d',
            $this->user_info['user_id'], cfg::api_product_id));
        $api['key'] = $row ? $row['value'] : false;

        if (isset($_GET['get_api_key'])) {
            if ($api['key']) {
                echo(json_encode(array('data' => array('auth_key' => $api['key']))));
            } else {
                $options = array(
                    'http' => array(
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query(array('email' => $this->user_info['email'], 'product_name' => 'database_api'))
                    )
                );
                $context = stream_context_create($options);
                $result = file_get_contents('https://api.cleantalk.org/?method_name=get_api_key', false, $context);
                if ($result === false) {
                    echo('{"error": true}');
                } else {
                    echo($result);
                }
            }
            exit();
        }

        // Статистика

        $this->get_mc_counters();

        $sql = "SELECT usd_rate from currency c where c.currency = 'RUB';";
        $row_cur = $this->db->select($sql);

        // Загружаем лицензию пользователя
        $sql = sprintf("SELECT ul.id as license_id, ul.max_calls, ul.period_begin, ul.period_end, ul.valid_till, ul.paid_till, ul.fk_tariff, ul.created, t.mpd as mpd FROM users_licenses ul LEFT JOIN tariffs t ON t.tariff_id = ul.fk_tariff WHERE ul.user_id = %d AND t.product_id = %d AND ul.moderate = 1",
            $this->user_info['user_id'],
            cfg::api_product_id);
        $license = $this->db->select($sql);

        if ($license) {
            $this->page_info['license_info'] = sprintf('Database API, %s checks per month, valid till %s.',
                number_format($license['mpd'], 0, '', ' '), date('M d Y', strtotime($license['valid_till'])));

            $api['enabled'] = true;
            $api['license'] = array(
                'id' => $license['license_id'],
                'max_calls' => number_format($license['max_calls'], 0, '', ' '),
                'period_begin' => $license['period_begin'] ? $license['period_begin'] : date('M d, Y'),
                'period_end' => $license['period_end'] ? $license['period_end'] : date('M d, Y'),
                'license_valid' => date('M d Y', strtotime($license['valid_till']))
            );

            // Service
            $sql = sprintf("SELECT service_id FROM services WHERE user_id=%d AND product_id=2 AND moderate_service=1", $this->user_info['user_id']);
            $service = $this->db->select($sql);
            $api['license']['status'] = $service ? true : false;

            // Загружаем методы
            $rows = $this->db->select("SELECT m.* FROM api_methods m LEFT JOIN api_methods_types t ON m.method_type=t.type_id WHERE t.type_name='database_api'", true);
            foreach ($rows as $method) {
                $api['methods'][$method['method_name']] = array(
                    'id' => $method['method_id'],
                    'name' => $method['method_name'],
                    'description' => $this->lang['l_api_method_' . $method['method_name']],
                    'help' => $this->lang['l_api_method_help_' . $method['method_name']],
                    'stats' => array()
                );

                // Статистика по вызовам
                $stat_sql = sprintf("SELECT SUM(checks) AS checks, sum(calls) as calls, MAX(last_call) AS last_call, SUM(blacklisted_checks) as blacklisted_checks
                                    FROM api_methods_stat WHERE method_id=%d AND user_id=%d AND DATE(`date`) BETWEEN '%s' AND '%s'",
                    $method['method_id'],
                    $this->user_info['user_id'],
                    $api['license']['period_begin'],
                    $api['license']['period_end']
                );
                if (strtotime($license['created']) > strtotime('2016-09-22 18:36:41')) {
                    $stat_sql = sprintf("SELECT SUM(checks) AS checks, sum(calls) as calls, MAX(last_call) AS last_call, SUM(blacklisted_checks) as blacklisted_checks
                                        FROM api_methods_stat WHERE method_id=%d AND user_id=%d AND DATE(`date`) BETWEEN '%s' AND '%s' AND ul_created=%s",
                        $method['method_id'],
                        $this->user_info['user_id'],
                        $api['license']['period_begin'],
                        $api['license']['period_end'],
                        $this->stringToDB($license['created'])
                    );
                }
				$stat = $this->db->select($stat_sql);
                $stat['last_call'] = strtotime($stat['last_call']);
                $stat['last_call'] = $stat['last_call'] - $this->options['billing_timezone'] * 3600; 
                $stat['last_call'] = $stat['last_call'] + $this->user_info['timezone'] * 3600; 
                $stat['last_call'] = date('M d, Y H:i:s', $stat['last_call']);
				if (!$stat['checks']) {
					$stat['checks'] = 0;
					$stat['last_call'] = '-';
				}
                $api['methods'][$method['method_name']]['stats'] = array(
                    'checks' => $stat['checks'] ? number_format($stat['checks'], 0, '', ' ') : 0,
                    'calls' => $stat['calls'] ? number_format($stat['calls'], 0, '', ' ') : 0,
                    'last_call' => $stat['last_call'] ? $stat['last_call'] : '-',
                    'blacklisted_checks' => $stat['blacklisted_checks'] ? number_format($stat['blacklisted_checks'], 0, '', ' ') : 0,
                    'efficiency' => $stat['checks'] ? number_format($stat['blacklisted_checks']/$stat['checks']*100, 2, '.', ' ') : 0,
				);
            }
            $api['license']['period_begin'] = date('M d, Y', strtotime($api['license']['period_begin']));
            $api['license']['period_end'] = date('M d, Y', strtotime($api['license']['period_end']));

            // Если лицензия заканчивается менее через 14 дней, выводим баннер о продлении.
            $valid_till = strtotime($license['valid_till']);
            if (($valid_till - 86400 * 14) < time()) {
                $tariff = $this->db->select("SELECT tariff_id, mpd, cost, cost_usd FROM tariffs WHERE tariff_id=" . $license['fk_tariff']);
                if ($tariff) {
                    // количество месяцев на лицензии
                    $months = round((strtotime($license['paid_till']) - strtotime($license['created'])) / (86400 * 30));
                    if ($months < 3) {
                        $months = 1;
                    } else if ($months < 6) {
                        $months = 3;
                    } else if ($months < 9) {
                        $months = 6;
                    } else if ($months < 12) {
                        $months = 9;
                    } else {
                        $months = 12;
                    }

                    $cost = '$' . round($tariff['cost_usd']);
                    if ($this->ct_lang == 'ru') {
                        $cost = number_format($row_cur['usd_rate'] * $tariff['cost_usd'], 0, '.', '') . ' руб.';
                    }

                    $banner = array(
                        'text' => sprintf($this->lang['l_api_pay_banner'], number_format($tariff['mpd'], 0, '', ' '), $cost),
                        'button' => $this->lang['l_api_pay'],
                        'link' => sprintf('/my/bill/api?period=%d&product=%d', $months, $tariff['tariff_id'])
                    );
                } else {
                    $banner = false;
                }
                $this->page_info['pay_banner'] = $banner;
            }
        } else {
            // Загружаем методы
            $rows = $this->db->select("SELECT m.* FROM api_methods m LEFT JOIN api_methods_types t ON m.method_type=t.type_id WHERE t.type_name='database_api'", true);
            foreach ($rows as $method) {
                $api['methods'][$method['method_name']] = array(
                    'id' => $method['method_id'],
                    'name' => $method['method_name'],
                    'description' => $this->lang['l_api_method_' . $method['method_name']],
                    'help' => $this->lang['l_api_method_help_' . $method['method_name']]
                );
            }
            // Баннер
			$tariff = $this->db->select("SELECT tariff_id, mpd, cost, cost_usd FROM tariffs WHERE tariff_id=" . cfg::api_tariff_default);
            $cost = '$' . round($tariff['cost_usd']);
            if ($this->ct_lang == 'ru') {
                $cost = number_format($row_cur['usd_rate'] * $tariff['cost_usd'], 0, '.', '') . ' руб.';
            }
            $months = cfg::api_period_default;
            $banner = array(
                'text' => sprintf($this->lang['l_api_pay_banner'], number_format($tariff['mpd'], 0, '', ' '), $cost),
                'button' => $this->lang['l_api_pay'],
                'link' => sprintf('/my/bill/api?period=%d&product=%d', $months, $tariff['tariff_id'])
            );
            $this->page_info['pay_banner'] = $banner;
        }

        $this->page_info['api'] = $api;
    }

    private function get_mc_counters() {
        $this->memcache = &$this->mc;
        if ($this->mc_online) {
            $this->page_info['nu_count'] = number_format($this->memcache->get('nu_count'), 0, '', ' ');
            $this->page_info['spam_count'] = number_format($this->memcache->get('spam_count'), 0, '', ' ');
            $this->page_info['active_hosts'] = number_format($this->memcache->get('active_hosts'), 0, '', ' ');
            $this->page_info['forums_count'] = number_format($this->memcache->get('forums_count'), 0, '', ' ');
            $this->page_info['sites_count'] = number_format($this->memcache->get('sites_count'), null, '', ' ');
            $this->page_info['pm_count'] = number_format($this->memcache->get('pm_count'), 0, '', ' ');
            $this->page_info['total'] = number_format($this->memcache->get('nu_count') + $this->memcache->get('spam_count'), 0, '', ' ');
        }

        $bl_counts_label = 'bl_counts';
        $bl_counts_lock_label = 'bl_counts_lock';
        $bl_counts = $this->memcache->get($bl_counts_label);
        $bl_counts_lock = $this->memcache->get($bl_counts_lock_label);

        $sql = "select count(*) as count from bl_%s where frequency >= %d;";
        $sql_week = "select count(*) as count from bl_%s where frequency >= %d AND submited>DATE_SUB(CURDATE(), INTERVAL 7 DAY);";
        if ($bl_counts === false && $bl_counts_lock === false) {
            $this->memcache->set($bl_counts_lock_label, time(), null, cfg::bl_stat_mc_timeout);
            $row = $this->db->select(sprintf($sql, 'ips', cfg::bl_fail_count));
            $bl_counts['ips'] = $row['count'];
            $row = $this->db->select(sprintf($sql_week, 'ips', cfg::bl_fail_count));
            $bl_counts['ips_week'] = $row['count'];
            $row = $this->db->select(sprintf($sql, 'emails', cfg::bl_fail_count_private));
            $bl_counts['emails'] = $row['count'];
            $row = $this->db->select(sprintf($sql_week, 'emails', cfg::bl_fail_count_private));
            $bl_counts['emails_week'] = $row['count'];
            $row = $this->db->select(sprintf($sql, 'domains', cfg::bl_fail_count_private));
            $bl_counts['domains'] = $row['count'];
            $row = $this->db->select(sprintf($sql_week, 'domains', cfg::bl_fail_count_private));
            $bl_counts['domains_week'] = $row['count'];
            $this->memcache->set($bl_counts_label, $bl_counts, null, cfg::bl_stat_mc_timeout_long);

            $this->memcache->delete($bl_counts_lock_label);
        }

        if ($bl_counts) {
            foreach ($bl_counts as $k => $v) {
                $this->page_info['bl_' . $k . '_count'] = number_format($v, 0, '', ' ');
            }
        }
        $this->bl_counts = $bl_counts;

        return true;
    }

    /**
      * Панель управления реселера
      *
      * @return void
      */

    private function show_hoster_page() {
        $this->smarty_template = 'includes/general.html';
        $this->get_lang($this->ct_lang, 'Hoster');

        // Вывод аннотации

        if (isset($this->user_info['license_show_annotation'])) {
            $this->page_info['annotation'] = array(
                'title' => $this->lang['l_annotation_title'],
                'text' => $this->lang['l_annotation_text']
            );
            $this->page_info['annotation_hide_top'] = true;
        }

        if (isset($this->lang['l_main_title']))
			$this->page_info['head']['title'] = $this->lang['l_dashboard_title'];

        $ips = $this->db->select(sprintf("select ip_id, ip, hostname, submited, updated, last_seen from ips where user_id = %d order by ip;",
            $this->user_id
        ), true);

        $license_active = false;
        $license_valid_till = false;
        if (isset($this->user_info['license'])) {
            $license_active = (bool)$this->user_info['license']['moderate'];
            $license_valid_till = date('M d, Y', strtotime($this->user_info['license']['valid_till']));
        } else {
            $license_active = (bool)$this->user_info['moderate_ip'];
            if ($license_active) {
                $license_valid_till = date('M d, Y', strtotime($this->user_info['paid_till']));
            }
        }

        if (isset($this->user_info['license'])) {
            $tariff = $this->user_info['license']['tariff'];
        } else {
            $tariff = $this->db->select(sprintf(
                "SELECT tariff_id, cost_usd, services FROM tariffs WHERE product_id = %d AND services >= %d ORDER BY services ASC LIMIT 1",
                cfg::product_hosting_antispam, count($ips)
            ));
        }

        // Логика вывода баннера
        // Выводим если:
        //     1. Отсутствует лицензия.
        //     2. Лиценизия - триальная.
        //     3. Заканчивает срок действия (pay_notice_start дней) или просрочена.
        // Не выводим если:
        //     users.hoster_id != null && licenses.moderate (user_id=hoster_id) = 1
        $this->page_info['pay_banner'] = array(
            'title' => sprintf('Anti-Spam Hosting, %d IP, $%d/month.', $tariff['services'], $tariff['cost_usd']),
            'link' => '/my/bill/hosting?period=3&product=' . $tariff['tariff_id']
        );
        if (isset($this->user_info['license']) && !$this->user_info['trial']) {
            if (strtotime($this->user_info['license']['valid_till']) > (time() - $this->options['pay_notice_start'] * 86400)) {
                $this->page_info['pay_banner'] = false;
            }
        } else if (isset($this->user_info['hoster_id'])) {
            if ($pay_check = $this->db->select(sprintf("SELECT moderate FROM users_licenses WHERE user_id = %d", $this->user_info['hoster_id']))) {
                if ($pay_check['moderate']) $this->page_info['pay_banner'] = false;
            }
        }

        foreach ($ips as $k => $v) {
            $v['visible_name'] = $v['ip'];
            if ($v['hostname']) {
                $v['visible_name'] = sprintf("%s (%s)",
                    $v['ip'],
                    $v['hostname']
                );
            }

            if ($license_active) {
                $v['status_message_class'] = 'text-success';
                $v['status'] = $this->lang['l_active'];
            } else {
                $v['status_message_class'] = 'text-danger';
                $v['status'] = $this->lang['l_not_active'];
            }

            $v['valid_till'] = $license_valid_till ? $license_valid_till : date('M d, Y', strtotime($v['updated']));

            if (!$v['last_seen']) {
                $v['last_seen'] = '-';
            }

            $sql = sprintf("select sum(count) as sum from requests_stat_ips where ip_id = %d and datediff(now(), date) <= 6;",
                $v['ip_id']
            );
            $week_stat = $this->db->select($sql);
            if (isset($week_stat['sum'])) {
                $v['week_requests_count'] = $week_stat['sum'];
            } else {
                $v['week_requests_count'] = 0;
            }

            $ips[$k] = $v;
        }
        $this->page_info['ips'] = $ips;

        $users = $this->db->select(sprintf("select u.user_id,u.email,u.created from users u where u.hoster_id = %d order by u.created desc;",
            $this->user_id
        ), true);
        foreach ($users as $k => $v) {
            $sql = "select sum(count) as count from requests_stat_services where user_id = %d and date between now() - interval 7 day and now();";
            $stat = $this->db->select(sprintf($sql,
                $v['user_id']
            ));
            if (!$stat['count']) {
                $stat['count'] = 0;
            }
            $v['created_visible'] = date("M j, Y", strtotime($v['created']));
            $v['week_requests_count'] = number_format($stat['count'], 0, '', ' ');
            $users[$k] = $v;
        }
        $this->page_info['users'] = $users;

        if (!$this->user_info['hoster_api_key']) {
            $hoster_api_key = $this->generatePassword($this->options['hoster_api_key_length'], 5);

            $this->db->run(sprintf("update users set hoster_api_key = %s where user_id = %d;",
                $this->stringToDB($hoster_api_key),
                $this->user_id
            ));

            $this->user_info['hoster_api_key'] = $hoster_api_key;
        }

        // Лицензия пользователя

        $sql = sprintf("SELECT
            l.fk_tariff, l.valid_till, l.paid_till, l.moderate,
            t.services, t.cost_usd
            FROM users_licenses l
            LEFT JOIN tariffs t ON t.tariff_id = l.fk_tariff
            WHERE l.user_id = %d AND l.product_id = %d",
            $this->user_info['user_id'], cfg::product_hosting_antispam
        );
        if ($row = $this->db->select($sql)) {
            $vt_ts = strtotime($row['valid_till']);
            if ($row['moderate'] && $vt_ts > time()) {
                if ($this->ct_lang == 'ru') {
                    $this->page_info['license_info'] = sprintf($this->lang['l_license_info'],
                        $row['services'], round($row['cost_usd'] * $this->row_cur['usd_rate']), date('d.m.Y', $vt_ts));
                } else {
                    $this->page_info['license_info'] = sprintf($this->lang['l_license_info'],
                        $row['services'], round($row['cost_usd']), date('M d Y', $vt_ts));
                }
            }
        }

        $this->page_info['styles'] = array(
            '/my/css/hoster.css?v08.05.2018_02',
        );

        $this->page_info['second_top_button'] = false;
        $this->page_info['template']  = 'hoster/index.html';

        return null;
    }

    /**
      * Функция отображения страницы для браузера
      *
      * @return void
      */

    private function show_page_native() {
        if(isset($_GET['update_services'])){
            apc_delete($this->account_label);
            apc_delete('number_sites_'.$this->user_id);
            exit;
        }
        /*
            Пользователей без ознакомительного срока отправляем на страницу оплаты.
        */
        $bill_page_label = 'bill_page';
        if ($this->user_id && $this->user_info['trial'] == -1 && $this->user_info['moderate'] == 0 && !isset($_COOKIE[$bill_page_label])) {
            setcookie($bill_page_label, 1, null, '/', $this->cookie_domain);
            header("Location:/my/bill/recharge");
            exit;
        }
        $granted_services = array();
        $granted_services = $this->get_services($this->user_id, true, cfg::product_antispam);
        if ($granted_services)
            $this->page_info['granted_services'] = $granted_services;


        // Стартовый баннер
		if ($this->user_info['services_count']['antispam'] == 0 && !$granted_services) {
            $this->smarty_template = 'includes/general.html';
            $this->page_info['template']  = 'antispam/main/banner.html';
            $this->page_info['head']['title'] = $this->lang['l_main_title'];
            $this->page_info['container_fluid'] = true;

            return;
        }
		$this->show_review_notice($this->user_info['services_count']['antispam']);
		
		$this->page_info['show_main_hint'] = 0;

        if(apc_exists('bulk_delete_antispam_'.$this->user_id)){
            $this->page_info['bulk_delete_count'] = apc_fetch('bulk_delete_antispam_'.$this->user_id);
            apc_delete('bulk_delete_antispam_'.$this->user_id);
        }

        // https://basecamp.com/2889811/projects/8701471/todos/264323849
        $users_del_account = apc_fetch('users_del_account');

        if (!$users_del_account) {
            $uda = $this->db->select("select a.user_id 
                                      from postman_logs a join users_info b
                                      on a.user_id=b.user_id 
                                      where a.comment LIKE 'Sub task_id: 41%' 
                                      and a.dsn like '2.%' and a.email_opened=1
                                      and (b.my_last_login>a.post_time 
                                      or b.my_last_login is null)",true);
            $del_acc_user_ids = array();
            foreach($uda as $oneuid)
                $del_acc_user_ids[] = $oneuid['user_id'];
            apc_store('users_del_account', $del_acc_user_ids, 24*60*60);
        }

        if ($users_del_account && in_array($this->user_id, $users_del_account)) {
            if (isset($_COOKIE['uda_banner']))
                $this->page_info['uda_banner'] = false;
            else {
                $this->page_info['uda_banner'] = true;
                setcookie('uda_banner', 1, time() + 365*24*60*60, '/', $this->cookie_domain);
            }
        }
		if (isset($this->lang['l_main_title']))
			$this->page_info['head']['title'] = $this->lang['l_main_title'];

		$tariff_id = (isset($this->user_info['fk_tariff'])) ? $this->user_info['fk_tariff'] : false;
		$tariff = $this->get_tariff_info($tariff_id);
		$cost = (float) $tariff['cost'];
		$user_balance = (float) $this->user_info['balance'];
		$cost = $cost - $user_balance;

        $this->page_info['show_mobile_apps'] = true;

        $this->page_info['show_account_status'] = false;
        if (!$this->user_info['moderate'])
            $this->page_info['show_account_status'] = true;

        if ($this->user_info['trial'] == -1) {
            $this->page_info['show_account_status'] = false;
        }

        $this->get_apps();

		// Продление доступа для интервального тарифа
		$this->page_info['need_recharge_pm'] = isset($this->user_info['tariff']['pmi']) && $this->user_info['limit_pm'] <= 0 ? true : false;

		$this->link->name = sprintf(messages::user_title, $this->page_info['user_info']['email']);

		$will_extend = false;
        $this->page_info['show_paid_till'] = true;
		if ($this->user_info['paid_till_ts'] > time() && empty($this->user_info['tariff']['cost']))
			$will_extend = true;

		if (isset($this->user_info['tariff']) && empty($this->user_info['tariff']['cost']) && $this->user_info['tariff']['auto_extend'] == 1) {
			$will_extend = true;
            $this->page_info['show_paid_till'] = false;
		}
		if (isset($this->tariffs[$this->user_info['fk_tariff']])) {
        	$this->page_info['package_info'] = strip_tags(sprintf($this->lang['l_service_general_short'], mb_strtolower($this->tariffs[$this->user_info['fk_tariff']]['info_charge']), ''));
		}

		$this->page_info['will_extend'] = &$will_extend;

        $this->show_upgrade_tariffs();

        // Автоматическое продление доступа к сервису для пользователей с нулевым трафиком.
        if (isset($this->user_info['license']) && isset($this->user_info['tariff']) && in_array($this->cp_product_id, array(cfg::product_antispam, cfg::product_security)) && false) {
            $free_days = $this->user_info['license']['free_days'];
            $free_days_n = time() + ($free_days - 1) * 86400;
            $free_days_new_features = $this->options['free_days_new_features'];

            if ($this->user_info['tariff']['cost_usd'] > 0 && $this->user_info['license']['trial']) {
                $paid_till = null;
                $paid_till_ts = strtotime($this->user_info['license']['paid_till']);
                $valid_till_ts = strtotime($this->user_info['license']['valid_till']);
                if ($paid_till_ts < $free_days_n && $free_days_n > 0) {
                    switch ($this->cp_product_id) {
						case cfg::product_antispam:
							$min_date_ts = null;
							$sql = sprintf("select unix_timestamp(min(date)) as min_date_ts from requests_stat where user_id = %d;", $this->user_id);
							$connect_info = $this->db->select($sql);
							if (isset($connect_info['min_date_ts'])) {
								$min_date_ts = $connect_info['min_date_ts'];
							}
							$sql = sprintf("select unix_timestamp(min(date)) as min_date_ts  from sfw_logs_stat where user_id = %d;", $this->user_id);
							$connect_info = $this->db->select($sql);
							if (isset($connect_info['min_date_ts']) && ($connect_info['min_date_ts'] < $min_date_ts || $min_date_ts === null)) {
								$min_date_ts = $connect_info['min_date_ts'];
							}
                            if ($min_date_ts !== null) {
                                $paid_till = $min_date_ts + $free_days * 86400;
                            } else {
                                $paid_till = strtotime(sprintf("+%d days", $free_days));
                            }
                            break;
                        case cfg::product_security:
                            if ($connect_info = $this->db->select(sprintf("select unix_timestamp(min(datetime)) as min_date_ts from services_security_log where user_id = %d;", $this->user_id))) {
                                $paid_till = $connect_info['min_date_ts'] + $free_days * 86400;
                            } else {
                                $paid_till = strtotime(sprintf("+%d days", $free_days));
                            }
                            break;
                    }
                }

                // Для переходов из рассылки New features
                if (isset($_GET['utm_campaign']) && $_GET['utm_campaign'] == 'new_features') {
                    if (is_null($paid_till) && $paid_till_ts < $free_days_n && $free_days_new_features > 0) {
                        $paid_till = strtotime(sprintf("+%d days", $free_days_new_features));
                        $this->post_log(sprintf("Пользователь %s (%d) перешел из рассылки New features.",
                            $this->user_info['email'], $this->user_id));
                    }
                }
                if (!is_null($paid_till) && $paid_till > $valid_till_ts) {
                    $valid_till_ts = $paid_till;
				}
				if (!is_null($paid_till) && $paid_till > $paid_till_ts && $paid_till > time()) {
                    $paid_till = date("Y-m-d", $paid_till);
                    $valid_till = date('Y-m-d', $valid_till_ts);
                    $this->db->run(sprintf("UPDATE users_licenses SET paid_till = %s, valid_till = %s, moderate = 1 WHERE id = %d",
                        $this->stringToDB($paid_till), $this->stringToDB($valid_till), $this->user_info['license']['id']));
                    $this->post_log(sprintf("Пользователю %s (%d) ознакомительный период продлен до %s по лицензии %d.",
                        $this->user_info['email'], $this->user_id, $paid_till, $this->user_info['license']['id']));
                }
            }
        }
        /*$free_days = $this->user_info['free_days'];
        $free_days_new_features = $this->options['free_days_new_features'];
        if (
            $this->user_info['tariff']['cost'] > 0
            && $this->user_info['trial'] != 0
            ) {

            $paid_till = null;
            if ((($this->user_info['paid_till_ts']) < (time() + ($free_days - 1) * 86400)) && $free_days > 0) {
                $connect_info = $this->db->select(sprintf("select unix_timestamp(min(date)) as min_date_ts from requests_stat where user_id = %d;",
                    $this->user_id
                ));
                if (isset($connect_info['min_date_ts'])) {
                    $paid_till = $connect_info['min_date_ts'] + $free_days * 86400;
                } else {
                    $paid_till = strtotime(sprintf("+%d days", $free_days));
                }
            }

            if (isset($_GET['utm_campaign']) && $_GET['utm_campaign'] == 'new_features') {
                if ($paid_till === null && (($this->user_info['paid_till_ts']) < (time() + ($free_days - 1) * 86400)) && $free_days_new_features > 0) {
                    $paid_till = strtotime(sprintf("+%d days", $free_days_new_features));
                    $this->post_log(sprintf("Пользователь %s (%d) перешел из рассылки New features.",
                                        $this->user_info['email'], $this->user_id));
                }
            }
            if ($paid_till !== null && $paid_till > $this->user_info['paid_till_ts']) {
                $paid_till = date("Y-m-d", $paid_till);
                $this->db->run(sprintf("update users set paid_till = %s, moderate = 1 where user_id = %d;", $this->stringToDB($paid_till), $this->user_id));
                $this->post_log(sprintf("Пользователю %s (%d) ознакомительный период продлен до %s.",
                                    $this->user_info['email'], $this->user_id, $paid_till));
            }
        }*/

        /*
            Записываем информацию о том, что ползователь пришел по рассылке new_features
        */
        if (isset($_GET['utm_campaign']) && $_GET['utm_campaign'] == 'new_features') {
            if (!in_array('new_features', $this->user_info['meta'])) {
                $this->user_info['meta'][] = 'new_features';
                $this->db->run(sprintf("update users set meta = %s where user_id = %d;",
                    $this->stringToDB(implode(",", $this->user_info['meta'])),
                    $this->user_id
                ));
            }
        }

        // Предложение о переходе на новый тариф показывает только пользователям с бесплатными тарифами
        if ($this->user_info['tariff']['pmi'] != 0 && $this->user_info['tariff']['cost'] == 0) {
            $this->show_offer();

            $mark = '';
            if ($this->user_info['limit_pm'] == 0)
                $mark = 'color: #CC3300; font-weight: bold;';

            $this->page_info['free_positive_requests'] = sprintf($this->lang['l_free_positive_requests'], $mark, $this->user_info['limit_pm'], $this->user_info['tariff']['pmi']);
        } else {
            $this->page_info['paid_till_info'] = sprintf($this->lang['l_paid_till'],
                date("M d Y", strtotime($this->user_info['paid_till']))
            );
            if (isset($this->user_info['valid_till'])) {
                $this->page_info['valid_till_info'] = sprintf($this->lang['l_valid_till'],
                    date("M d Y", strtotime($this->user_info['valid_till'])));
            }
            if ($this->user_info['trial'] == 1) {
                $this->page_info['hide_package_info'] = true;
                $this->page_info['show_apps_services'] = false;
                $this->page_info['show_more_apps'] = false;

                if ($this->user_info['created_ts'] + 86400 > time() || $this->user_info['requests_total'] == 0) {
                    $this->page_info['paid_till_info_trial'] = sprintf($this->lang['l_free_trial'], $this->user_info['free_days']);
                } else {
                    $this->page_info['paid_till_info_trial'] = sprintf($this->lang['l_trial_till'], date("M d Y", strtotime($this->user_info['paid_till'])));
                }

                $this->page_info['paid_till_info'] = null;

                $trial_notice = $this->lang['l_trial_notice'];
                $trial_notice_preambule = $this->lang['l_trial_notice'];
                $trial_notice_text = $this->lang['l_trial_notice_text'];
                $this->page_info['trial_notice'] = sprintf('%s <span id="123"><a href="#" onclick="$(\'trial_notice_text\').style.display = \'block\'; var slide = new Fx.Slide(\'trial_notice_text\', {duration: 200}); slide.toggle(); return true;">%s</a></span><span id="trial_notice_text">%s</span>',
                    $trial_notice_preambule,
                    $this->lang['l_next'],
                    $trial_notice_text
                );
            }
            $need_pay = true;

            /*
                Выдаем список отзывов
            */
            $this->page_info['show_reviews'] = $this->user_info['trial'] != 0 ? true : false;
            if ($this->page_info['show_reviews']) {
                $tools = new CleanTalkTools();
                $this->page_info['show_mobile_apps'] = false;
                $sql = sprintf("select review_title, review_text, review_date, review_author, review_avatar, review_lang, review_url from users_bonuses ub where review_title is not null and review_lang = %s;",
                    $this->stringToDB($this->ct_lang)
                );
                $rs = $this->db->select($sql, true);
                foreach ($rs as $k => $v) {
                    foreach ($v as $k2 => $v2) {
                        if ($k2 == 'review_date') {
                            $v2 = date("F j, Y", strtotime($v2));
                        }
                        $v[$k2] = $v2;
                    }
                    if ($v['review_avatar']) {
                        $v['review_avatar_data'] = 'data:image/png;base64,' . base64_encode($v['review_avatar']);
                    } else {
                        $v['review_avatar_data'] = '/images/cleantalk-logo-128.png';
                    }
                    if ($v['review_date']) {
                        $v['review_date_ts'] = strtotime($v['review_date']);
                    }
                    if ($v['review_url']) {
                        $v['review_host'] = $tools->get_domain($v['review_url']);
                    }
                    $rs[$k] = $v;
                }

                // Перемещиваем данные
                shuffle($rs);

                // Удаляем лишние отзывы
                $i = 0;
                foreach ($rs as $k => $v) {
                    $i++;
                    if ($i > cfg::max_review_on_first_page) {
                        unset($rs[$k]);
                    }
                }

                // Сортируем по времени
                usort($rs, function($a, $b) {
                    return $b['review_date_ts'] - $a['review_date_ts'];
                });

                $this->page_info['reviews'] = $rs;
            }

            // Если пользователь оплатил новую подписку относительно не давно, то запрещаем вывод баннера об оплате.
            if ($this->user_info['trial'] == 0) {
                $need_pay = false;
            }

            $paid_service = $this->db->select(sprintf("select bill_id, auto_bill from bills where fk_user = %d and paid = 1 order by date desc limit 1;",
                                        $this->user_id));
            if (isset($paid_service['bill_id']) && $paid_service['auto_bill'] == 1) {
                $need_pay = false;
                $this->page_info['auto_bill_date'] = sprintf($this->lang['l_auto_bill_date'], $this->user_info['paid_till']);
            }

            // Предложение о переходе на новый тариф для пользователей достигших суточного ограничения
            $row = false;
            if ($this->user_info['tariff']['unlimited_mpd'] == 0)
                $row = $this->db->select(sprintf('select fs.user_id, fs.tariff_id, unix_timestamp(fs.datetime) as datetime_ts, fs.datetime from users_freeze_stat fs left join tariffs t on t.tariff_id = fs.tariff_id where fs.user_id = %d and datediff(now(), datetime) <= %d and freeze = 1 order by datetime desc limit 1;', $this->user_id, cfg::upgrade_offer_show_period));

            if (isset($row['tariff_id'])) {
                $need_pay = true;
            }

            if ($need_pay == true) {
                $max_mpd = 0;
                if ($this->user_info['tariff']['mpd'] > 0) {
                    // Рассчитываем максимальное значение одобренных запросов
                    $sql = sprintf("select (message_allow + newuser_allow) as sum from requests_stat where user_id = %d and datediff(now(), date) <= %d order by sum desc limit 1;", $this->user_id, cfg::upgrade_offer_show_period);
                    $row_mpd = $this->db->select($sql);
                    if (isset($row_mpd['sum'])) {
                        $max_mpd = $row_mpd['sum'];
                    }
                }

                if ($row === false) {
                    $offer_tariff_id = $this->get_offer_tariff_id($max_mpd);

                    $this->page_info['offer_more'] = true;
                    if ($this->user_info['trial'] == 1) {
                        $this->page_info['offer_more'] = false;
                        $this->page_info['hide_charge'] = true;
                        $this->page_info['offer_more_tariffs'] = true;
                        $this->page_info['offer_tariff_id'] = $offer_tariff_id;
					}
                    $this->show_offer(null, $offer_tariff_id);
                } else {
                    if ($this->user_info['tariff']['unlimited_mpd'] == 0) {
                        $sql = sprintf('select tariff_id, mpd, cost, hosted_button_id, billing_period from tariffs where mpd > %d and cost / period >= %.2f and billing_period = %s and services >= %d and allow_subscribe_panel = 1 order by mpd limit 1;', $max_mpd, $this->user_info['tariff']['cost'] / $this->user_info['tariff']['period'], $this->stringToDB($this->user_info['tariff']['billing_period']), $this->user_info['services']);

                        $offer_tariff = $this->db->select($sql);
                        $offer_tariff_id = $offer_tariff['tariff_id'];
                        if ($offer_tariff_id != $this->user_info['offer_tariff_id'])
                            $this->db->run(sprintf("update users set offer_tariff_id = %d where user_id = %d;", $offer_tariff_id, $this->user_id));

                        $freeze_time = $row['datetime'];
                        // Если указана временная зона, то выводим время приостановки с поправкой на часовой пояс клиента
                        if (isset($this->user_info['timezone']))
                            $freeze_time = date("Y-m-d H:i:s", $row['datetime_ts'] - (3600 * (int) cfg::billing_timezone) + (3600 * (int) $this->user_info['timezone']));
                        $period = 1;
                        $period_label = ' ';
                        if ($this->ct_lang == 'en' && $offer_tariff['billing_period'] == 'Month') {
                            $period = 3;
                            $period_label = " $period ";
                        }

                        $upgrade = true;
                        $auto_bill = 0;
                        $discount = $this->get_upgrade_discount($this->user_id, $offer_tariff_id);
                        $paid_till = $this->get_tariff_conditions(null, $period, $offer_tariff_id, $upgrade);

                        $bill = $this->get_bill($offer_tariff_id, false, $period, null, false, false, $this->page_info['tariff_conditions'], $auto_bill, $discount, $this->currencyCode, date("Y-m-d", $paid_till) );
                        $cost = $bill['cost'];
                        if ($this->ct_lang == 'en')
                            $cost = $bill['cost_usd'];

                        $this->page_info['show_offer'] = true;
                        $this->page_info['offer_more'] = false;
                        $this->page_info['offer_tariff'] = &$offer_tariff;
                        $this->page_info['upgrade_offer'] = sprintf($this->lang['l_upgrade_offer'], $freeze_time, $offer_tariff['mpd'], $cost, $period_label, $this->lang['l_' . strtolower($this->user_info['tariff']['billing_period'])]);
                        $this->page_info['pay_button'] = $this->lang['l_upgrade_package'];
                    }
                }

            } else {
                $this->page_info['show_more_apps'] = true;
            }
        }

        // Логика выдачи сообщения об отзыве
        $this->show_review(true);

        //
        // Выводим информацию о продлении доступа за pay_days до окончания текущей подписки
        //
		$this->page_info['need_recharge'] = false;
		if ($this->renew_account) {
            $this->page_info['need_recharge'] = true;
            $this->page_info['show_top_renew_button'] = true;

            $this->page_info['show_review'] = 0;

            $offer_tariff_id = $this->user_info['fk_tariff'];
            $upgrade = false;
            $auto_bill = 0;
            $period = cfg::default_period;
            $discount = $this->get_upgrade_discount($this->user_id, $offer_tariff_id);
            $paid_till = $this->get_tariff_conditions(null, $period, $offer_tariff_id, $upgrade);

            $bill = $this->get_bill($offer_tariff_id, false, $period, null, false, false, $this->page_info['tariff_conditions'], $auto_bill, $discount, $this->currencyCode, date("Y-m-d", $paid_till) );

            $this->show_offer(null, $offer_tariff_id);
            $this->page_info['show_more_apps'] = false;

            $this->page_info['pay_button'] = strtoupper($this->lang['l_renew_antispam']);
        }


        if ($this->page_info['panel_version'] == 3) {
            $this->page_info['template']  = 'main_new.bak.html';
            $this->page_info['bsdesign'] = true;
            $this->page_info['quiz_js']  = false;
            // Если есть записи в sfw_logs то показываем статистику SFW и ссылку на лог SFW в ПУ
            if (isset($_SESSION['has_sfw']))
                $this->page_info['has_sfw'] = $_SESSION['has_sfw'];
            else {

                $has_sfw = $this->db->select(sprintf("select user_id from sfw_logs where user_id = %d limit 1",$this->user_id),true);
                if ($has_sfw && $has_sfw[0]['user_id']>0)
                    $_SESSION['has_sfw'] = 1;
                else
                    $_SESSION['has_sfw'] = 0;
                $this->page_info['has_sfw'] = $_SESSION['has_sfw'];

            }

        }

        $this->page_info['services'] = $this->get_services($this->user_id, false, cfg::product_antispam);
        $granted_services = array();
		$granted_services = $this->get_services($this->user_id, true, cfg::product_antispam);

        //
        if(is_array($this->page_info['services'])){
            foreach ($this->page_info['services'] as &$s) {
                if($s['engine']=='wordpress'){
                    if($row = $this->db->select(sprintf("select * from spbc_remote_calls where service_id = %d AND user_id = %d AND call_action = 'update_plugin' AND plugin_name = 'anti-spam' limit 1",$s['service_id'],$this->user_id))){
                        if($row['call_result'] && (strtotime($row['created'])+5*60<time() || !isset($s['update_app']['productive_app_id']))){
                            $s['was_updated'] = $this->lang['l_was_updated'];
                        }else{
                            $s['status_message_class'] = 'processing';
                            $s['status_message'] = $this->lang['l_processing_autoupdate'];
                            $s['status_message_hint'] = $this->lang['l_processing_autoupdate_hint'];
                        }
                    }
                }
            }
        }

        if ($granted_services)
            $this->page_info['granted_services'] = $granted_services;

        $this->page_info['show_follow_us'] = false;

        foreach (explode(",", $this->options['noc_members_ids']) as $v) {
            if ((int)$this->user_id === (int)$v) {
                $this->page_info['snow_noc_link'] = true;
                $this->page_info['snow_switch_panel_link'] = true;
            }
        }

        $this->page_info['help_icon'] = false;

        // Пояснения к статусу веб-сайта
        if ($this->ct_lang == 'ru')
            $this->page_info['not_connected_sm'] = 'not_connected_sm';
        else
            $this->page_info['not_connected_sm'] = 'not_connected_sm_en';

        // Готовим параметры для оплаты с банера
        $this->fill_pay_method_params();

        //
        // Реклама сервиса для новых пользователей
        //
        if ($this->user_info['trial'] == 1) {
            $services_count_label = 'services_counters';
            $data = apc_fetch($services_count_label);
            if (!$data) {
                $sql = sprintf("select count(*) as websites, sum(count - allow) as spam_count from requests_stat_services where date >= %s;",
                    $this->stringToDB(date("Y-m-d", time() - 86400 * 2))
                );
                $data['websites'] = 10000;
                $row = $this->db->select($sql);
                if ($row['websites'] > $data['websites']) {
                    $data['websites'] = $row['websites'];
                }
                $data['spam_count'] = 1000000;
                if ($row['spam_count'] > $data['spam_count']) {
                    $data['spam_count'] = $row['spam_count'];
                }
                apc_store($services_count_label, $data, cfg::memcache_store_timeout);
            }
            $websites = sprintf('<span class="red_text">%s</span>', number_format($data['websites'], 0, '', ' '));
            $spam_count = sprintf('<span class="red_text">%s</span>', number_format($data['spam_count'], 0, '', ' '));
            $this->page_info['new_customers_adv'] = sprintf($this->lang['l_new_customers_adv'],
                $websites,
                $spam_count
            );

        }

        //
        // Инструкция на установку
        //
        if ($this->user_info['trial'] == 1) {
            if (count($this->page_info['services']) == 1 && $this->user_info['requests_total'] == 0 && cfg::show_setup_hint) {
                $service = null;
                foreach ($this->page_info['services'] as $v) {
                    $service = $v;
                }
                $test_email = explode(",", $this->options['test_email']);
                $this->page_info['test_email'] = $test_email[0];
                $this->page_info['setup_service'] = $service;
                $this->page_info['setup_hint_title'] = $this->lang['l_setup_hint_title'];
            }
        }

        $header_notice = '';
        if ($this->user_info['spam_total'] > 1) {
            //
            // Делаем подсчет спам атак по сервисам, т.к. количество спам атак через кеш не всегда актуально.
            //
            $s_spam_stat = 0;
            foreach ($this->page_info['services'] as $v) {
                foreach ($v['r_stat'] as $v2) {
                    if (isset($v2['week'])) {
                        $s_spam_stat = $s_spam_stat + $v2['week']['spam'];
                    }
                }
            }
            $s_spam_stat = $s_spam_stat < $this->user_info['spam_total'] ? $this->user_info['spam_total'] : $s_spam_stat;
            $header_notice = sprintf($this->lang['l_alltime_blocked'],
                number_format($s_spam_stat, 0, ',', ' ')
            );
        }
        $this->page_info['header_notice'] = $header_notice;

        //
        // Банер о беслпатной лицензии
        //
        if ($this->user_info['trial'] == 1 && $this->user_info['moderate'] == 1 && cfg::year_free_offer) {
           $this->page_info['middle_notice'] = $this->lang['l_1year_for_spam'];
           $this->page_info['middle_notice_hint'] = $this->lang['l_1year_for_spam_hint'];
        }

        if ($this->user_info['trial'] == -1 && cfg::year_free_offer) {
           $this->page_info['middle_notice'] = $this->lang['l_1year_for_spam_trial_off'];
           $this->page_info['middle_notice_hint'] = $this->lang['l_1year_for_spam_hint'];
        }
        if ($this->user_info['trial'] == 0 && $this->user_info['tariff']['billing_period'] == 'Year') {
            $lb = $this->db->select(sprintf("select pr.promo_id, pr.promokey, pr.discount, pr.expire from bills b left join promo pr on pr.promo_id = b.promo_id_discount where b.paid = 1 and b.fk_user = %d order by b.date desc limit 1;",
                $this->user_id
            ));
            if ($lb['promo_id'] && strtotime($lb['expire']) > time()) {
                $pomokey_part = sprintf('%s',
                    $lb['promokey']
                );
                $upgrade_part = sprintf('<a href="/my/bill/recharge?promokey=%s">%s</a>',
                    $lb['promokey'],
                    $this->lang['l_upgrade_own_license']
                );
                $promo_discount['title'] = sprintf($this->lang['l_promocode_main_title'],
                    $pomokey_part
                );
                 $promo_discount['info'] = sprintf($this->lang['l_promocode_main_info'],
                    $lb['discount'] * 100,
                    date("M d Y", strtotime($lb['expire'])),
                    $upgrade_part
                );
                $this->page_info['promo_discount'] = $promo_discount;
            }
        }

/*
        if ($this->user_info['moderate'] == 0 && $this->user_info['first_pay_id'] == null && $this->user_info['paid_till_ts'] < time()) {
            $this->page_info['show_top_renew_button'] = true;
            $this->page_info['pay_button_top'] = $this->lang['l_renew_antispam'];
        }

*/
        /*
            Выводим банер о бесплатном сервисе у хостера N.
        */
        if ($this->user_info['trial'] == 0 && $this->ct_lang == 'ru' && cfg::show_hosting_offer) {
            $this->page_info['show_hosting_offer'] = true;
            $this->page_info['show_more_apps'] = false;
        }
        $this->page_info['trial'] = $this->user_info['trial'];
        $this->page_info['country'] = $this->user_info['country'];

        /*
            Скрываем логотип возврата денег, дабы освободить банер.
        */
        if ($this->options['show_double_prices'] == 1){
            $this->page_info['hide_money_back'] = true;
        }

        /*
            Хостинги с бесплатным CleanTalk
        */
        if ($this->user_info['trial'] == 1) {
            $this->show_free_hostings();
        }

        //
        // Статистика для карты
        //

        if (isset($_GET['hidemap'])) {
            if ($_GET['hidemap'] == '1') {
                setcookie('map_hide', '1', time() + 365*24*60*60, '/', $this->cookie_domain);
                $_COOKIE['map_hide'] = '1';
            }
            if ($_GET['hidemap'] == '0') {
                setcookie('map_hide', '0', time() + 365*24*60*60, '/', $this->cookie_domain);
                $_COOKIE['map_hide'] = '0';
            }
        }

        function traffic_sort_approved($a, $b) {
            if ($a['approved'] == $b['approved']) return 0;
            return ($a['approved'] > $b['approved']) ? -1 : 1;
        }

        function traffic_sort_spam($a, $b) {
            if ($a['spam'] == $b['spam']) return 0;
            return ($a['spam'] > $b['spam']) ? -1 : 1;
        }

		$map_global_label = 'map_global';
        $mapIsGlobal = true;

        $show_world_map = cfg::show_world_map && !isset($_GET['search_service']) ? true : false;
        if (isset($_COOKIE['map_hide']) && $_COOKIE['map_hide'] == '1') {
            $this->page_info['mapHidden'] = true;
        } else {
            $this->page_info['mapHidden'] = false;
            $mapData = apc_fetch('map_data_' . $this->user_id);

			// Если mapData == 'global', используем глобальные данные

            if ($mapData == 'global') {
                $this->page_info['map_debug'] = 'Using map_global';
				$mapData = apc_fetch($map_global_label);
            }
            if (!$mapData && cfg::show_world_map) {
                $this->page_info['map_debug'] = 'Regenerate map';
                $mapDates = array(
                    'start' => date('Y-m-d 00:00', strtotime('-1 week')),
                    'end' => date('Y-m-d H:i')
                );

                $rows = $this->db->select(sprintf(
                    "SELECT COUNT(request_id) AS requests, SUM(allow) AS allows, ip_country FROM requests WHERE user_id=%d AND `datetime` > %s AND `datetime` < %s GROUP BY ip_country",
                    $this->user_id,
                    $this->stringToDB($mapDates['start']),
                    $this->stringToDB($mapDates['end'])
                ), true);

                // Если по пользователю нет данных, пробуем взять общую информацию из кэша
				if (count($rows)) {
                   $mapIsGlobal = false;
				} else {
                    $mapData = apc_fetch($map_global_label);
                    if ($mapData) {
                        $this->page_info['map_debug'] = 'Using map_global';
                        apc_store('map_data_' . $this->user_id, 'global', cfg::apc_cache_lifetime_long);
                    }
                }

                // Если общая информация и её нет в кэше
                if ($mapIsGlobal && !$mapData) {
                    $rows = $this->db->select(sprintf(
                        "SELECT SUM(requests) AS requests, SUM(allows) AS allows, country AS ip_country FROM requests_stat_countries WHERE `date` BETWEEN %s AND %s GROUP BY ip_country",
                        $this->stringToDB($mapDates['start']),
                        $this->stringToDB($mapDates['end'])
                    ), true);

                    /*$rows = $this->db->select(sprintf(
                        "SELECT COUNT(request_id) AS requests, SUM(allow) AS allows, ip_country FROM requests WHERE datetime > date(%s) AND datetime < date(%s) GROUP BY ip_country",
                        $this->stringToDB($mapDates['start']),
                        $this->stringToDB($mapDates['end'])
                    ), true);*/
                }

                if (!$mapData) {
                    $mapData = array();

                    $mapData['map'] = array('approved' => array(), 'spam' => array());
                    $mapData['traffic'] = array();
                    $totalRequests = 0;
                    $totalApproved = 0;
                    $totalSpam = 0;
                    if (!$rows) $rows = array();
                    foreach ($rows as $row) {
                        if ($row['ip_country'] && isset($this->countries[$row['ip_country']])) {
                            $mapData['map']['approved'][$row['ip_country']] = $row['allows'];
                            $mapData['map']['spam'][$row['ip_country']] = $row['requests'] - $row['allows'];
                            $mapData['traffic'][] = array(
                                'country' => $this->countries[$row['ip_country']],
                                'value' => (int) $row['requests'],
                                'approved' => (int) $row['allows'],
                                'spam' => $row['requests'] - $row['allows']
                            );
                            $totalRequests += $row['requests'];
                            $totalApproved += $row['allows'];
                            $totalSpam += $row['requests'] - $row['allows'];
                        }
                    }

                    // проценты
                    foreach ($mapData['traffic'] as &$val) {
                        $val['percent'] = ($totalRequests > 0) ? number_format(($val['value'] / $totalRequests) * 100, 0) . '%' : '0%';
                        $val['approvedPercent'] = ($totalApproved > 0) ? number_format(($val['approved'] / $totalApproved) * 100, 0) . '%' : '0%';
                        $val['spamPercent'] = ($totalSpam > 0) ? number_format(($val['spam'] / $totalSpam) * 100, 0) . '%' : '0%';
                    }

                    if ($mapIsGlobal) {
                        apc_store($map_global_label, $mapData, 600);
                        apc_store('map_data_' . $this->user_id, 'global', cfg::apc_cache_lifetime_long);
                    } else {
                        apc_store('map_data_' . $this->user_id, $mapData, cfg::apc_cache_lifetime_long);
                    }
                }
            }


            if (isset($mapData['traffic']) && $mapData['traffic']) {
                $this->page_info['map'] = array();

                usort($mapData['traffic'], 'traffic_sort_approved');
                $this->page_info['map']['approved'] = array_slice($mapData['traffic'], 0, 5);
                usort($mapData['traffic'], 'traffic_sort_spam');
                $this->page_info['map']['spam'] = array_slice($mapData['traffic'], 0, 5);
			}
//			var_dump($mapIsGlobal);exit;
            $this->page_info['mapTitle'] = $mapIsGlobal ? $this->lang['l_map_global_title'] : $this->lang['l_map_user_title'];

            $this->page_info['gdpData'] = json_encode($mapData['map']);
        }

        $this->page_info['show_world_map'] = $show_world_map;
        if (empty($this->user_info['addons']) && ($this->user_info['licenses']['antispam']['moderate'] == 1)) $this->page_info['user_addon'] = false;
        else $this->page_info['user_addon'] = $this->user_info['addons'];
    //print_r(($this->user_info['addons']));
	}

	function sort_services(&$services, $stat, $field, $order) {
	    foreach ($services as &$service) {
	        switch ($field) {
                case 'approved':
                    $service['stat'] = $stat['today'][$service['service_id']]['allow'];
                    break;
                case 'spam':
                    $service['stat'] = $stat['today'][$service['service_id']]['spam'];
                    break;
                case 'sfw':
                    $service['stat'] = $stat['today'][$service['service_id']]['sfw'];
                    break;
                case 'created':
                    $service['stat'] = strtotime($service['created']);
                    break;
                case 'hostname':
                    $service['stat'] = $service['hostname'];
                    break;
                default:
                    $service['stat'] = strtotime($service['created']);
                    break;
            }
        }
	    function cmp_asc($a, $b) {
	        if ($a['stat'] == $b['stat']) {
	            return 0;
            }
            return $a['stat'] < $b['stat'] ? -1 : 1;
        }
        function cmp_desc($a, $b) {
            if ($a['stat'] == $b['stat']) {
                return 0;
            }
            return $a['stat'] > $b['stat'] ? -1 : 1;
        }
        uasort($services, ($order == 'asc') ? 'cmp_asc' : 'cmp_desc');
    }

    // $is_grant переменная - если true
    // то выбираем делегированные сайты
    /**
      * Функция получает сайты пользователя
      *
      * @param int $user_id ID пользователя
      * @param bool $is_grant если true то выбираем делегированные сайты
      * @param int $product_id ID продукта
      * @return array
      */

    function get_services($user_id = null, $is_grant = false, $product_id = null) {
        if ($user_id === null)
            $user_id = $this->user_id;

        if ($this->app_mode) {
            $this->logs = new Logs($this->db, $this->user_info);
        } else if (!$this->logs) {
            $this->logs = new Logs($this->db, array('user_id' => $this->user_id, 'timezone' => 0));
        }

        $storage = new Storage($this->db);

        $app_label = '';
        if ($this->app_mode)
            $app_label = 'app';

        $services_label = sprintf('services:%d_%s_%d', $this->user_id, $app_label, $this->page_info['panel_version']);
        $services_label_granted = sprintf('granted_services:%d_%s_%d', $this->user_id, $app_label, $this->page_info['panel_version']);

        // Объекты для статистики
        $stat_antispam = $this->logs->antispam();
        $stat_sfw = $this->logs->sfw();

        // Выбираем делегированные сайты
        $granted_services_ids_string = '';
        $granted_services_ids = array();
        if ($is_grant) {
            $granted_sites_sql = sprintf("select service_id 
                                          from services_grants
                                          where user_id_granted = %d",
                                          $this->user_id);
            $granted_sites = $this->db->select($granted_sites_sql,true);
            if ($granted_sites){
                foreach($granted_sites as $onegsite)
                    $granted_services_ids[] = $onegsite['service_id'];
                $granted_services_ids_string = implode(',',$granted_services_ids);
            }
            $stat_antispam->grant = $granted_services_ids;
            $stat_sfw->grant = $granted_services_ids;
        }

        // нужно ли включать режим пагинации и поиcка для сайтов
        // на главной

        $number_sites = apc_fetch('number_sites_'.$this->user_id);
        $is_page_search = 0;

        if (!$number_sites) {
            if ($product_id) {
                $number_sites_res = $this->db->select(sprintf("select count(*) as nsites from services where user_id = %d and (product_id = %d or product_id is null)", $this->user_id, $product_id));
            } else {
                $number_sites_res = $this->db->select(sprintf("select count(*) as nsites from services where user_id = %d", $this->user_id));
            }
            $number_sites = $number_sites_res['nsites'];

            apc_store('number_sites_'.$this->user_id, $number_sites, 1800);

        }

        if ($number_sites >= cfg::sites_number_page_search)
                $is_page_search = 1;
            else
                $is_page_search = 0;

        if ($this->app_mode && isset($_GET['all_results'])) {
            $is_page_search = 0;
        }
        $this->page_info['is_page_search'] = $is_page_search;
        $this->page_info['number_sites'] = $number_sites;

        if ($is_grant) {
            if ($granted_services_ids_string != '') {
                $product_sql = $product_id ? sprintf(' and (s.product_id = %d or s.product_id is null)', $product_id) : '';
                $sql = sprintf('select s.service_id, name, hostname, s.engine, s.product_id, s.moderate_service, s.favicon,
                                   stop_list, auth_key, connected, created, 
                                   a.app_id, a.productive,
                                   sg.grantwrite 
                                   from services s 
                                   left join apps a on a.app_id = s.app_id 
                                   join services_grants sg 
                                   on s.service_id = sg.service_id
                                   where s.service_id in (%s)%s order by s.created;',
                    $granted_services_ids_string, $product_sql);
            } else {
                return false;
            }
        } else {
            // Если включен режим пагинации то добавляем лимиты
            if ($is_page_search) {
                if (isset($_COOKIE['num_per_page']))
                    $num_per_page = preg_replace('/[^0-9]/i', '', $_COOKIE['num_per_page']);
                else{
                    setcookie('num_per_page', cfg::default_num_per_page, time() + 365*24*60*60, '/', $this->cookie_domain);
                    $num_per_page = cfg::default_num_per_page;
                }
                $number_pages = ceil($number_sites/$num_per_page);
                if (isset($_GET['service_page'])){
                    $page = preg_replace('/[^0-9]/i', '', $_GET['service_page']);
                    $prevpage = $page - 1;
                    $nextpage = $page + 1;
                    $this->page_info['prevpage'] = ($prevpage > 0 ? $prevpage : 0);
                    $this->page_info['nextpage'] = ($nextpage > $number_pages ? 0 : $nextpage);
                    $services_limits = ' limit '.($page - 1)*$num_per_page.',';
                    $services_limits.= $page*$num_per_page;
                }
                else{
                    $services_limits = ' limit '.$num_per_page;
                    $this->page_info['prevpage'] = 0;
                    $this->page_info['nextpage'] = ($number_pages == 1 ? 0 : 2);
                }

            }
            else
                $services_limits = '';
            // Если идёт поиск при пагинации то лимиты убираем
            if (isset($_GET['search_service']))
                $services_limits = '';

            $product_sql = $product_id ? sprintf(' and (s.product_id = %d or s.product_id is null)', $product_id) : '';
            $sql = sprintf('select s.service_id, name, hostname, s.engine, s.product_id, s.moderate_service, s.favicon,
                                   s.favicon_url, s.favicon_update,
                                   stop_list, auth_key, connected, created,
                                   a.app_id, a.productive, a.release_version from services s 
                                   left join apps a on a.app_id = s.app_id 
                                   where s.user_id = %d%s order by s.created %s',
                                   $this->user_id,
                                   $product_sql,
                                   ''/*,$services_limits*/);
        }
        $rows = $this->db->select($sql, true);

        // Поиск по сайтам

        $this->page_info['search_service'] = false;
        if (isset($_GET['search_service'])) {
            $this->page_info['search_not_found'] = false;
            $search_service = strip_tags(htmlspecialchars(trim($_GET['search_service'])));
            $this->page_info['search_service'] = $search_service;
            foreach ($rows as $ri => $onerow) {
                if (!strstr($onerow['hostname'], $search_service))
                    unset($rows[$ri]);
            }

            if (count($rows) == 0)
                 $this->page_info['search_not_found'] = sprintf($this->lang['l_search_not_found'], $search_service);

        }

        $services_ids = array();
        foreach ($rows as $s) {
            $services_ids[] = $s['service_id'];
        }


        $services_stat = $this->logs->stat($stat_antispam, $stat_sfw, $is_grant ? $granted_services_ids : $services_ids);
        if (!$is_grant) {
            if (isset($_COOKIE['num_per_page']))
                $num_per_page = preg_replace('/[^0-9]/i', '', $_COOKIE['num_per_page']);
            else{
                setcookie('num_per_page', cfg::default_num_per_page, time() + 365*24*60*60, '/', $this->cookie_domain);
                $num_per_page = cfg::default_num_per_page;
            }
            $start_pos = 0;
            $end_pos = (int)$num_per_page;
            if (!$is_page_search) $end_pos = 9999;
            $this->page_info['sort_approved_link'] = '/my/?sort=approved';
            $this->page_info['sort_spam_link'] = '/my/?sort=spam';
            $this->page_info['sort_sfw_link'] = '/my/?sort=sfw';
            $this->page_info['sort_created_link'] = '/my/?sort=created';
            $this->page_info['sort_hostname_link'] = '/my/?sort=hostname';
            if (isset($_GET['service_page'])) {
                $start_pos = ($_GET['service_page'] - 1) * $num_per_page;
                if ($_GET['service_page'] > 1) {
                    $this->page_info['prev_page_link'] = sprintf('/my/?service_page=%d', $_GET['service_page'] - 1);
                }
                if (isset($_GET['sort'])) {
                    if ($_GET['service_page'] > 1) {
                        $this->page_info['prev_page_link'] .= sprintf('&sort=%s', $_GET['sort']);
                    }
                }
            }
            if (count($rows) > $num_per_page) {
                $this->page_info['next_page_link'] = sprintf('/my/?service_page=%d', (isset($_GET['service_page']) ? $_GET['service_page'] : 1) + 1);
                if (isset($_GET['sort'])) {
                    $this->page_info['next_page_link'] .= sprintf('&sort=%s', $_GET['sort']);
                }
            }
            if (isset($_GET['sort']) || !empty($_COOKIE['antispam_main_order'])) {
                if(isset($_GET['sort'])){
                    $antispam_sort = $_GET['sort'];
                    setcookie('antispam_main_order', $antispam_sort, strtotime("+365 day"), '/', $this->cookie_domain);
                }else{
                    $antispam_sort = $_COOKIE['antispam_main_order'];
                }
                if(isset($_GET['order'])){
                    $direction = $_GET['order'];
                }else{
                    if(!empty($_COOKIE['antispam_main_direction'])){
                        $direction = $_COOKIE['antispam_main_direction'];
                    }else{
                        $direction = 'desc';
                    }
                }
                setcookie('antispam_main_direction', $direction, strtotime("+365 day"), '/', $this->cookie_domain);

                $this->page_info['show_sort_reset'] = true;
                $this->sort_services($rows, $services_stat, $antispam_sort, $direction);
                switch ($antispam_sort) {
                    case 'approved':
                        $this->page_info['sort_approved_link'] .= sprintf('&order=%s', ($direction == 'asc') ? 'desc' : 'asc');
                        $this->page_info['sort_approved_direction'] = ($direction == 'asc') ? '&#9650;' : '&#9660;';
                        break;
                    case 'spam':
                        $this->page_info['sort_spam_link'] .= sprintf('&order=%s', ($direction == 'asc') ? 'desc' : 'asc');
                        $this->page_info['sort_spam_direction'] = ($direction == 'asc') ? '&#9650;' : '&#9660;';
                        break;
                    case 'sfw':
                        $this->page_info['sort_sfw_link'] .= sprintf('&order=%s', ($direction == 'asc') ? 'desc' : 'asc');
                        $this->page_info['sort_sfw_direction'] = ($direction == 'asc') ? '&#9650;' : '&#9660;';
                        break;
                    case 'created':
                        $this->page_info['sort_created_link'] .= sprintf('&order=%s', ($direction == 'asc') ? 'desc' : 'asc');
                        $this->page_info['sort_created_direction'] = ($direction == 'asc') ? '&#9650;' : '&#9660;';
                        break;
                    case 'hostname':
                        $this->page_info['sort_hostname_link'] .= sprintf('&order=%s', ($direction == 'asc') ? 'desc' : 'asc');
                        $this->page_info['sort_hostname_direction'] = ($direction == 'asc') ? '&#9650;' : '&#9660;';
                        break;
                }
                if (isset($this->page_info['prev_page_link'])) {
                    $this->page_info['prev_page_link'] .= sprintf('&order=%s', $direction);
                }
                if (isset($this->page_info['next_page_link'])) {
                    $this->page_info['next_page_link'] .= sprintf('&order=%s', $direction);
                }
            } else {
                $this->page_info['sort_created_link'] .= '&order=desc';
                $this->page_info['sort_created_direction'] = '&#9650;';
            }
            $rows = array_slice($rows, $start_pos, $end_pos);
            $this->page_info['allow_sort'] = true;
        }

        /*
             Логика формирования недельной иконки
        */
        $tz = (int)$this->user_info['timezone'] - 5; // Временная зона пользователя + сервера (YEKT +5)
        $tz_ts = $tz * 3600;
        $week_days = 6;
        $days_h = null;
        $sql_dates = '';
        for ($i = 0; $i <= $week_days; $i++) {
            if ($sql_dates != '') {
               $sql_dates .= ',';
            }
            $date = gmdate("Y-m-d", strtotime(sprintf("+%d days", $i), time() - (86400 * $week_days) - $tz_ts));
            $days_h[$date] = $i;
            $sql_dates .= $this->stringToDB($date);
        }
        $sql = sprintf("select date, count, allow, service_id from requests_stat_services where user_id = %d and date in(%s);",
            $this->user_id,
            $sql_dates
        );
        $stat = $this->db->select($sql, true);
        $user_stat = null;
        foreach ($stat as $s) {
            $user_stat[$s['service_id']][$days_h[$s['date']]] = $s;
        }
        $sql = sprintf("select date, SUM(num_total) as num_total, SUM(num_allow) as num_allow, service_id from sfw_logs_stat where user_id = %d and date in(%s) group by service_id, date;",
            $this->user_id,
            $sql_dates
        );
        $stat = $this->db->select($sql, true);
		foreach ($stat as $s) {
			$s['count'] = $s['num_total'];
			$s['allow'] = $s['num_allow'];
            $user_stat[$s['service_id']][$days_h[$s['date']]] = $s;
        }
//          var_dump($sql, $days_h, $user_stat);exit;
        for ($i = 0; $i <= $week_days; $i++) {
            foreach ($services_ids as $service_id) {
                $week_stat = null;

                $stat = null;
                if (isset($user_stat[$service_id][$i])) {
                    $stat = $user_stat[$service_id][$i];
                }
                if (isset($stat['date'])) {
                    $week_stat[$i]['date'] = $stat['date'];
                    $week_stat[$i]['count'] = $stat['count'];
                    $week_stat[$i]['allow'] = $stat['allow'];
                } else {
                    $week_stat[$i]['date'] = date("Y-m-d", strtotime(sprintf("+%d days", $i), time() - (86400 * $week_days) - $tz_ts));
                    $week_stat[$i]['count'] = 0;
                    $week_stat[$i]['allow'] = 0;
                }

                $week_stat[$i]['spam'] = $week_stat[$i]['count'] - $week_stat[$i]['allow'];

                $services_stat['week_stat'][$service_id]['days'][$i] = $week_stat[$i];
            }
        }
        if (isset($services_stat['week_stat'])) {
            foreach ($services_stat['week_stat'] as $service_id => $s) {
                $max_spam = 0;
                $max_allow = 0;

                foreach ($s['days'] as $i) {
                    if ($i['allow'] > $max_allow) {
                        $max_allow = (int) $i['allow'];
                    }
                    if ($i['spam'] > $max_spam) {
                        $max_spam = $i['spam'];
                    }
                 }
                $services_stat['week_stat'][$service_id]['max_allow'] = $max_allow;
                $services_stat['week_stat'][$service_id]['max_spam'] = $max_spam;
            }
        }

        /*
            Массив активных клиентских приложений.
        */
        $apps = null;
        $apps_all = null;
        $apps_temp = $this->db->select("select app_id, engine, release_version, productive, unix_timestamp(release_date) as release_date_ts from apps where product_id=1;", true);
        foreach ($apps_temp as $v) {
            if ($v['productive'] == 1) {
                $apps[$v['app_id']] = $v;
                $this->apps[$v['engine']]['productive_app_id'] = $v['app_id'];
            }

            $apps_all[$v['app_id']] = $v;
        }

        // Информация для Extra Package
        $this->get_lang($this->ct_lang, 'Bill');
        /*$ep_display = false;
        if ($this->user_info['trial'] === '0') {
            $ep_display = true;
            if (isset($this->user_info['addons'])) {
                foreach ($this->user_info['addons'] as $addon) {
                    if ($addon['addon_id'] === '1') {
                        $ep_display = 'info';
                        break;
                    }
                }
            }
            $ep_info = apc_fetch('ep_info_' . $this->ct_lang);
            if (!$ep_info) {
                $sql = sprintf("select package_id, package_name, cost_usd, cost_rate_per_tariff from tariffs_packages where package_id = %d;",
                    $this->intToDB(cfg::extra_package_id));
                $ep_info = $this->db->select($sql);
                if (isset($ep_info['package_name'])) {
                    $ep_info['title'] = $this->lang['l_' . $ep_info['package_name'] . '_title'];
                    $sql = sprintf("select ta.addon_id,ta.addon_name from tariffs_addons_packages tap left join tariffs_addons ta on ta.addon_id = tap.addon_id where package_id = %d;",
                        $this->intToDB(cfg::extra_package_id)
                    );
                    $addons = $this->db->select($sql, true);
                    foreach ($addons as $k => $v) {
                        $ep_info['addons'][] = $this->lang['l_' . $v['addon_name'] . '_title'];
                    }
                }
                $ep_info['url'] = sprintf('/my/bill/recharge?package=%d&extra_package=1&utm_source=cleantalk.org&utm_medium=button_get_extra_package&utm_campaign=control_panel',
                    cfg::extra_package_id);
                $ep_info['title_enabled'] = $this->lang['l_ep_enabled'];
                $ep_info['title_not_enabled'] = $this->lang['l_ep_not_enabled'];
                $ep_info['buy_now'] = $this->lang['l_ep_buy_now'];
                apc_store($ep_info, 600);
            }
            $this->page_info['ep_info'] = $ep_info;
        }
        $this->page_info['ep_display'] = $ep_display;*/
        $this->page_info['ep_display'] = true;
        $this->page_info['ep_display_info'] = false;
        $this->page_info['brute_display_info'] = false;
        $this->page_info['show_dashboard_tour'] = $this->options['show_dashboard_tour'];
        if(defined('cfg::show_dashboard_tour')){
            $this->page_info['show_dashboard_tour'] = cfg::show_dashboard_tour;
        }
        $this->page_info['brute_display'] = $this->user_info['trial'] == 0 ? true : false;

        $r_stat = array();
        $services = array();
        foreach ($rows as $service) {
            $service_id = $service['service_id'];

            $cat_name = $service['service_id'];
            $service_name = isset($this->platforms[$service['engine']]) ? $this->platforms[$service['engine']] : '';
            if (isset($service['hostname'])) {
                $cat_name = $service['hostname'];

                $service_name = $service['hostname'];
            }
            if (isset($service['name']))
                $service_name = $service['name'];

            if ($this->page_info['panel_version'] == 2) {
                $service['name'] = $service_name;
            }

            
            if ($this->app_mode) {
                $s_app['servicename'] = $service_name;
                $s_app['hostname'] = $service['hostname'];
                $s_app['favicon_url'] = $service['favicon_url'];
                $s_app['service_id'] = $service['service_id'];
                $s_app['auth_key'] = $service['auth_key'];
                $s_app['today']['spam'] = $services_stat['today'][$service_id]['spam'];
                $s_app['today']['allow'] = $services_stat['today'][$service_id]['allow'];
                $s_app['yesterday']['spam'] = $services_stat['yesterday'][$service_id]['count'] - $services_stat['yesterday'][$service_id]['allow'];
                $s_app['yesterday']['allow'] = $services_stat['yesterday'][$service_id]['allow'];
                $s_app['week']['spam'] = $services_stat['week'][$service_id]['count'] - $services_stat['week'][$service_id]['allow'];
                $s_app['week']['allow'] = $services_stat['week'][$service_id]['allow'];
                if (isset($services_stat['today'][$service_id]['sfw']) || isset($services_stat['yesterday'][$service_id]['sfw']) || isset($services_stat['week'][$service_id]['sfw'])) {
                    $s_app['today']['sfw'] = isset($services_stat['today'][$service_id]['sfw']) ? $services_stat['today'][$service_id]['sfw'] : 0;
                    $s_app['yesterday']['sfw'] = isset($services_stat['yesterday'][$service_id]['sfw']) ? $services_stat['yesterday'][$service_id]['sfw'] : 0;
                    $s_app['week']['sfw'] = isset($services_stat['week'][$service_id]['sfw']) ? $services_stat['week'][$service_id]['sfw'] : 0;
                } else {
                    $s_app['today']['sfw'] = 0;
                    $s_app['yesterday']['sfw'] = 0;
                    $s_app['week']['sfw'] = 0;
                }

                $services[] = $s_app;
                continue;
            }

            $r_stat['today'] = $services_stat['today'][$service_id];
            $r_stat['yesterday'] = $services_stat['yesterday'][$service_id];
            $r_stat['week'] = $services_stat['week'][$service_id];
//            $r_stat['all_time'] = $services_stat['all_time'][$service_id];

            $r2 = null;
            foreach ($r_stat as $k => $v) {
                $v['spam'] = $v['count'] - $v['allow'];
                foreach ($v as $k2 => $v2) {
                    $r2['stat'][$k][$k2] = number_format($v2, 0, ',', ' ');
                    $r2['stat'][$k]['period_name'] = $this->lang['l_' . $k];
                }
            }

            $service['r'] = $services_stat['today'][$service_id];
            $service['r_stat'] = $r2;            
            $service['favicon'] = Favicon::get_icon_url($service);            

            // Генерация изображения со статистикой

            $week_stat = $services_stat['week_stat'][$service_id]['days'];
            $max_allow = $services_stat['week_stat'][$service_id]['max_allow'];
            $max_spam = $services_stat['week_stat'][$service_id]['max_spam'];
            $week_days = 6;
            $service['week_stat'] = $week_stat;

            $week_stat_file = cfg::customers_dir . '/week_stat/' . $service['service_id'] . '.png';
            $im_width = 96;
            $im_height = 25;
            $im = imagecreate($im_width, $im_height);
            $background_color = imagecolorallocate($im, 255, 255, 255);
            $grey_color = imagecolorallocate($im, 204, 204, 204);
            $spam_color = imagecolorallocate($im, 204, 51, 0);
            $allow_color = imagecolorallocate($im, 73, 199, 59);
            $null_color = imagecolorallocate($im, 102, 102, 102);

            imagerectangle($im, 0, 0, 95, 24, $grey_color);

            $col_width = 13;
            $null_height = $im_height - 3;
            $max_r = $max_spam + $max_allow;
            for ($i = 0; $i <= $week_days; $i++) {
                $x1 = $col_width * $i + 3;
                $x2 = $col_width * ($i + 1);
                if ($week_stat[$i]['spam'] > 0) {
                    $col_height = round($null_height - $null_height * ($week_stat[$i]['spam'] / $max_r) + 3);
                    $color = $spam_color;
                } else {
                    $col_height = $null_height;
                    $color = $spam_color;
                }

                imagefilledrectangle($im, $x1, $col_height, $x2, $null_height, $color);

                // $cord_y1, $cord_y2 - координаты столбцов для отрисовки карты на week_stat_file.png
                $cord_y1 = $col_height;
                $cord_y2 = $null_height;

                if ($week_stat[$i]['allow'] > 0) {
                    $col_height = round($null_height - $null_height * ($week_stat[$i]['allow'] / $max_r) + 0);
                    imagefilledrectangle($im, $x1, $col_height, $x2, $null_height, $allow_color);
                    $cord_y1 = $cord_y1 + $col_height;
                    $cord_y2 = $cord_y2 + $null_height;
                }
                $coords_date = '';
                if (isset($week_stat[$i]['date']))
                    $coords_date = $week_stat[$i]['date'] . "\n";

//                $coords['coords'] = sprintf("%d,%d,%d,%d", $x1, $im_height, $x2, 0);
                $coords['coords'] = sprintf("%d,%d,%d,%d", $x1, 0, $x2, $im_height);
                $coords['title'] = sprintf($this->lang['l_week_stat_title'],
                                                    $coords_date, $week_stat[$i]['spam'], $week_stat[$i]['allow']);
                $service['spam_coords'][] = $coords;
            }

            ob_start();
            //imagepng($im, $week_stat_file);
            imagepng($im);
            $week_stat_file = ob_get_contents();
            ob_end_clean();
            imagedestroy($im);

            if ($week_stat_icon = $storage->findByLabel('week_stat_' . $service['service_id'])) {
                $week_stat_icon->replace_bin($week_stat_file);
            } else {
                $week_stat_icon = $storage->upload_bin($week_stat_file, sprintf('week_stat_%d.png', $service['service_id']), 'db');
                $week_stat_icon['label'] = 'week_stat_' . $service['service_id'];
            }
            $service['week_stat_file'] = $week_stat_icon->base64();

            //if (file_exists($week_stat_file))
            //    $service['week_stat_file'] = $week_stat_file;
            $service['online'] = true;
            $service['status_info'] = sprintf($this->lang['l_service_status_info'], $service['connected']);
            $service['status_message'] = $this->lang['l_active'];
            $service['status_message_class'] = '';
            if (!$service['connected'] && $service['product_id'] != cfg::product_database_api) {
                $created_ts = strtotime($service['created']);
                if ($created_ts + $this->options['days_to_setup'] * 86400 < time()) {
                    $service['online'] = false;
                    $service['check_setup'] = sprintf($this->lang['l_check_setup'], $service['engine']);
                    $service['status_message'] = $this->lang['l_waiting_first'];
                    $service['status_message_class'] = 'offline';
                }
            }
            $service['update_app'] = null;
            if (isset($this->apps[$service['engine']]['productive_app_id'])
                && $service['app_id'] != null
                && $service['app_id'] != $this->apps[$service['engine']]['productive_app_id']
                && $apps_all[$service['app_id']]['release_date_ts'] < $apps_all[$this->apps[$service['engine']]['productive_app_id']]['release_date_ts']
                && $this->user_info['moderate'] == 1
                // && $this->renew_account == 0
            ) {
                $service['status_message'] = $this->lang['l_update_app'];
                $service['status_message_class'] = 'update_app';
                $service['check_setup'] = sprintf($this->lang['l_update_app_manual'], $service['engine']);
                $service['update_app'] = $this->apps[$service['engine']];
            }
            $service['productive_release_version'] = (
                isset($service['engine']) && 
                isset($this->apps[$service['engine']]) && 
                isset($this->apps[$service['engine']]['productive_app_id']) &&
                isset($apps_all[$this->apps[$service['engine']]['productive_app_id']]) && 
                isset($apps_all[$this->apps[$service['engine']]['productive_app_id']]['release_version'])
            ) ? $apps_all[$this->apps[$service['engine']]['productive_app_id']]['release_version'] : false;
//            var_dump($service, $this->apps);

            if ($this->user_info['moderate'] == 0) {
                $service['online'] = false;
                $service['status_message'] = $this->lang['l_disabled'];
                $service['status_message_class'] = 'offline';
            } else if ($service['product_id'] == cfg::product_database_api && $service['moderate_service'] == 0) {
                $service['online'] = false;
                $service['status_message'] = $this->lang['l_disabled'];
                $service['status_message_class'] = 'offline';
            }

            $service['show_status'] = true;

            $service['details_period'] = 'week';

            $astersks = '';
            for ($i = 1; $i <= strlen($service['auth_key']); $i++) {
                $astersks .= '*';
            }
            $service['astersks'] = $astersks;

            $service['release_version'] = null;
            if (isset($apps_all[$service['app_id']]['release_version'])) {
                $service['release_version'] = $apps_all[$service['app_id']]['release_version'];
            }

            $service['created_at'] = date('M d, Y', strtotime($service['created']));
			$service['rate_url'] = null;
			if (isset($this->review_links[$service['engine']])) {
				$service['rate_url'] = $this->review_links[$service['engine']];
			}

            if ($this->page_info['panel_version'] == 3) {
                $service['visible_name'] = $this->get_service_visible_name($service);
                $services[$service['service_id']] = $service;
            } else {
                $services[$cat_name]['category'] = $cat_name;
                $services[$cat_name]['favicon'] = $service['favicon'];
                $services[$cat_name]['services'][$service['service_id']] = $service;
            }

            // Кешируем информацию индивидуально по каждому сервису дабы была возможность запросить данные на других страницах ПУ
            $this->mc->set('service_' . $service['service_id'], $service, null, $this->options['service_cache_timeout']);
        }
        if ($is_grant)
            $this->mc->set($services_label_granted, $services, null, $this->options['services_cache_timeout']);
        else
            $this->mc->set($services_label, $services, null, $this->options['services_cache_timeout']);
        return $services;

    }

    /**
      * Функция возвращает рекомендуемый для подключения тариф
      *
      * @param int $max_mpd
      *
      * @return int
      */

    private function get_offer_tariff_id($max_mpd = null){

        $offer_tariff_id = null;
        if ($max_mpd === null)
            $max_mpd = $this->user_info['tariff']['mpd'];

        $cost_label = 'cost';
        if ($this->ct_lang == 'en')
            $cost_label .= '_usd';

        // Дабы не включился механизм подбора нового тарифа по количеству сайтов
#        if ($this->user_info['tariff']['unlimited_mpd'] && $this->user_info['services'] <= $this->user_info['tariff']['services'])
#            $offer_tariff_id = $this->user_info['fk_tariff'];

        foreach ($this->tariffs as $k => $v) {
            if ($offer_tariff_id != null || $v['product_id'] != $this->cp_product_id)
                continue;

            if (isset($this->user_info['tariff']) && isset($this->user_info['tariff']['unlimited_mpd']) && $this->user_info['tariff']['unlimited_mpd']) {
                if ($v['allow_subscribe_panel'] == 1 && $v['services'] >= $this->user_info['services'] && $v['billing_period'] == $this->user_info['tariff']['billing_period']
                    )
                    $offer_tariff_id = $v['tariff_id'];
            } else {
                if ($v['allow_subscribe_panel'] == 1 && $v['mpd'] >= $max_mpd && $v['services'] >= $this->user_info['services'] && isset($this->user_info['tariff']) && $v['billing_period'] == $this->user_info['tariff']['billing_period'])
                    $offer_tariff_id = $v['tariff_id'];
            }

        }

        if ($offer_tariff_id === null || !$this->cost_is_good($offer_tariff_id)) {
//            $offer_tariff_id = $this->user_info['fk_tariff'];
        }

        if ($this->user_info['fk_tariff'] == cfg::default_tariff_id_old && $this->user_info['services'] <= $this->user_info['tariff']['services']) {
            $offer_tariff_id = $this->user_info['fk_tariff'];
        }

        if ($offer_tariff_id === null) {
            $offer_tariff_id = $this->user_info['fk_tariff'];
        }

        if ($offer_tariff_id != $this->user_info['fk_tariff']
            && $this->user_info['tariff']['cost'] > 0
            && $this->user_info['trial'] == 1
            && $this->user_info['services'] != $this->tariffs[$offer_tariff_id]['services']
            )
        {
            $this->db->run(sprintf("update users set fk_tariff = %d, offer_tariff_id = %d where user_id = %d;", $offer_tariff_id, $offer_tariff_id, $this->user_id));
            if (isset($this->user_info['licenses']) && isset($this->user_info['licenses']['antispam'])) {
                $this->db->run(sprintf("update users_licenses set fk_tariff=%d where id=%d", $offer_tariff_id, $this->user_info['licenses']['antispam']['id']));
            }

            $this->post_log(sprintf('Пользователь %s (%d) переключен на тариф "%s" -> "%s".', $this->user_info['email'], $this->user_id,
                            $this->user_info['tariff']['name'], $this->tariffs[$offer_tariff_id]['name']));
        }

        return $offer_tariff_id;
    }

    /**
      * Функция возвращает массив данных для отображения статистики по сайту в консоли сайтов
      *
      * @param string $sql
      *
      * @return array
      */

    private function get_service_stat($sql) {
        $r_stat = array();
        $r = $this->db->select($sql, true);

        if (count($r)) {
            foreach ($r as $s) {
                if (!$s['count']) {
                    $s['count'] = 0;
                }
                if (!$s['allow']) {
                    $s['allow'] = 0;
                }
                $s['spam'] = $s['count'] - $s['allow'];

                $r_stat[$s['service_id']] = $s;
            }
        }
        return $r_stat;
    }

    /**
      * Функция возвращает массив данных для отображения статистики по сайтам в консоли сайтов
      *
      * @param array $r_stat
      *
      * @param array $services_ids
      *
      * @param string $sql_sfw
      *
      * @return array
      */

    private function get_services_stat($r_stat, $services_ids, $sql_sfw) {
        $services_stat = array();
        $service_stat_default = array(
            'count' => 0,
            'allow' => 0,
            'spam' => 0
        );

        $sfw = $this->db->select($sql_sfw, true);

        foreach ($services_ids as $service_id) {
            if (isset($r_stat[$service_id])) {
                $services_stat[$service_id] = $r_stat[$service_id];
            } else {
                $services_stat[$service_id] = $service_stat_default;
            }

            foreach($sfw as $onesfw) {
                    if ($onesfw['service_id']==$service_id){
                      $services_stat[$service_id]['sfw'] = $onesfw['sfwcount'];
                      break;
                    }
                  }
        }

        return $services_stat;
    }
}
?>
