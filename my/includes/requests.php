<?php

include '../includes/helpers/storage.php';

/**
* Класс для работы с запросами
*
*/
class Requests extends CCS {
    /**
     * @var int $requests_limit Лимит количества запросов
     */
	private $requests_limit = 300;

    /**
     * @var int $max_word_size
     */
	private $max_word_size = 64;

    /**
     * @var int $max_message_size
     */
    private $max_message_size = 0;

    /**
     * @var int $max_sender_size
     */
    private $max_sender_size = 35;

    /**
     * @var int $max_url_size
     */
    private $max_url_size = 64;

    /**
     * @var int $notify_service_id
     */
    private $notify_service_id = null;

    /**
     * @var int $service_id ID Сайта
     */
    private $service_id = null;

    /**
     * @var string $int Диапазон дат - day week month
     */
    private $int = null;

    /**
     * @var int $page Страница при подгрузке запросов ajax
     */
    private $page = 0;

    /**
     * @var int $numrecs Количество записей
     */
    private $numrecs = 0;

    /**
     * @var string Имя домена для добавления в БД приватных списков. 
     */
	private $private_list_domain = null;

    /**
     * @var array Доступное кол-во элементов на странице
     */
    private $items_per_page = array(10,50,100,300,500,1000);

	function __construct() {
		parent::__construct();
		$this->ccs_init();
	}

    /**
      * Функция показа страницы
      *
      * @param bool $ajax
      *
      * @return void
      */

	function show_page($unknown, $ajax = false){

        // Выбираем список делегированных сайтов

        $granted_services = $this->get_granted_services($this->user_id);

        $this->page_info['granted_services'] = $granted_services;

        // Массив с id делегированных сервисов
        $this->granted_services_ids = array();
        if($granted_services){
            foreach($granted_services as $onegrservice)
                $this->granted_services_ids[] = $onegrservice['service_id'];
        }

        $page_template = null;
		switch($this->link->id){
			// Список запросов
			case 17:
                $requests = null;
                $this->page_info['bsdesign'] = true;
                $this->smarty_template = 'includes/general.html';
                $this->page_info['template']  = 'antispam/log-antispam.html';
                $this->page_info['container_fluid'] = true;
                $this->page_info['scripts'] = array(
                    'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js',
                    '/my/js/jquery.download.min.js',
                    '/my/js/antispam-log.js?v01042018'
                );
                $this->page_info['styles'] = array(
                    '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css'
                );
                 // Постраничная навигация                            
                $current_page = 1; // Страница по умолчанию
                $visible_pages = 5; // Количество отображаемых страниц
                $page_from = 1; // Номер первой страницы
                $pages = array();
                $this->page_info['items_per_page_list'] = $this->items_per_page;
                // Количество записей читаем из куки
                if(isset($_COOKIE['asl_ipp']) && in_array($_COOKIE['asl_ipp'], $this->items_per_page)){
                    $items_per_page = intval($_COOKIE['asl_ipp']);
                }else{
                    $items_per_page = 100; // Количество записей по умолчанию
                }
                $this->page_info['items_per_page'] = $items_per_page;


                // Установим количество записей на странице, если значение из списка разрешенных
                if(isset($_GET['items_per_page']) && in_array($_GET['items_per_page'], $this->items_per_page)){
                    $items_per_page = intval($_GET['items_per_page']);
                    setcookie('asl_ipp', $items_per_page, time() + 365*24*60*60, '/', $this->cookie_domain);
                }

                // Установим текущую страницу
                if(isset($_GET['current_page'])){
                    $current_page = intval($_GET['current_page']);
                    if($current_page<1){
                        $current_page = 1;
                    }
                }
                if (!$this->check_access(null, true)) {
                    if (!$this->app_mode) {
                        $this->url_redirect('session', null, true);
                        exit();
                    }
                }

                // Считаем кол-во записей в таблице
                $records_count = $this->get_requests_count();

                // Максимальное кол-во страниц
                $total_pages_num = ceil($records_count / $items_per_page);

                // Переопределяем текущую страницу, если она больше максимальной
                if($total_pages_num<$current_page && $total_pages_num>0){
                    $current_page = $total_pages_num;
                }
                $this->page_info['total_pages'] = $total_pages_num;
                $this->page_info['current_page'] = $current_page;
                $this->page_info['records_found'] = isset($this->lang['l_records_found']) ? sprintf($this->lang['l_records_found'], number_format($records_count, 0, ',', ' ')) : '';
                // Определяем номера страниц для показа
                if ($current_page > floor($visible_pages/2)){
                    $page_from = max(1, $current_page-floor($visible_pages/2));
                }
                if ($current_page > $total_pages_num-ceil($visible_pages/2)){
                    $page_from = max(1, $total_pages_num-$visible_pages+1);
                }                            
                $page_to = min($page_from+$visible_pages-1, $total_pages_num);
                for ($i = $page_from; $i<=$page_to; $i++){
                    $pages[]=$i;
                }
                if (isset($_GET['page']))
                  $this->page = (int) $_GET['page'];

                if (!$this->check_access(null, true)) {
                    if (!$this->app_mode) {
                        $this->url_redirect('session', null, true);
                    }
                }
                if ($this->app_mode) {
                    if ($this->app_return['auth']) {
				        $requests = $this->show_requests();
                        $this->app_return['requests'] = $requests;
                    }
                } else {
                    if (count($_GET) < 2) $_GET['int'] = 'week';
                    if (isset($this->user_info['license']) && !$this->user_info['license']['moderate']) {
                        $this->page_info['show_modal'] = true;
                    }
                    $rows = $this->db->select(sprintf("select service_id, name, hostname, engine, stop_list_enable, sms_test_enable, auth_key, response_lang, created, updated from services where user_id = %d and product_id = %d order by created;", $this->user_id, cfg::product_antispam), true);
                    if (!count($rows)) $rows = $this->db->select(sprintf("select service_id, name, hostname, engine, stop_list_enable, sms_test_enable, auth_key, response_lang, created, updated from services where user_id = %d and product_id is null order by created;", $this->user_id), true);
                    $services = array();
                    foreach ($rows as $k => $v) {
                        $services[$v['service_id']]['service_name'] = $this->get_service_visible_name($v);
                    }
                    if (count($services) > 1) {
                        $this->page_info['services'] = $services;
                    } else {
                        foreach ($services as $k => $v) {
                            $this->service_id = $k;
                        }
                    }

                    if (isset($_GET['details']) && preg_match('/[a-z0-9]{32}/', $_GET['details'])) {
                        $request = $this->get_request($_GET['details']);
                        echo(json_encode($request));
                        exit;
                    }

                    if ((isset($_GET['mode']) && $_GET['mode'] == 'csv') || isset($_GET['is_ajax'])) {
                        if (count($_GET) < 3) $_GET['int'] = 'week';
                        $this->requests_limit = 100000;
                        //$requests = $this->show_requests(null, !((isset($_GET['mode']) && $_GET['mode'] == 'csv') || isset($_GET['is_ajax'])));                        
                        if(isset($this->page_info['show_modal'])){
                            $requests = array();
                        }elseif(isset($_GET['is_ajax'])){
                            

                            $requests = $this->get_requests(($current_page-1)*$items_per_page,$items_per_page);
                            echo(json_encode(array('pages'=>$pages,'items_per_page'=>$items_per_page,'page'=>$current_page,'total_pages'=>$total_pages_num,'records_found'=>$this->page_info['records_found'],'d'=>$requests)));
                            exit;
                        }else{
                            $requests = $this->get_requests();
                        }
                    } else {
                        $requests = array();
                        $this->info_requests();
                    }
                    // Обработка ajax
                    if ($this->page != 0) {
                        $this->page_info['requests'] = $requests;
                        $this->display('show_requests_ajax.html');
                        exit();
                    }
                    if (isset($_GET['mode']) && $_GET['mode'] == 'csv') {
                        $filename = 'antispam_requests';
                        if (isset($_GET['int']) && in_array($_GET['int'], array('today', 'yesterday', 'week'))) {
                            $filename .= '_' . $_GET['int'];
                        } else if (isset($_GET['start_from']) && isset($_GET['end_to'])) {
                            $filename .= sprintf(
                                '_%s-%s',
                                date('Ymd', strtotime($_GET['start_from'])),
                                date('Ymd', strtotime($_GET['end_to']))
                            );
                        }
                        $filename .= '.' . date('Y-m-d_H-i-s') . '.csv';
                        $csv = '';
                        if (!empty($requests)) {
                            ob_start();
                            $df = fopen("php://output", 'w');
                            /**
                             * id: request_id
                             * sid: service_id
                             * dt: datetime
                             * a:  allow
                             * m:  moderate
                             * c:  country
                             * r:  short result
                             * h:  service host|name|id
                             * f:  feedback result message
                             * n:  sender nickname
                             * e:  sender email
                             * i:  sender IP
                             * s:  show report spam
                             */
                            fputcsv($df, array(
                                'Request ID', 'Hostname', 'Date', 'Allow', 'Moderate', 'Country', 'Result',
                                'Nickname', 'Email', 'IP', 'Feedback'
                            ));
                            $hostname = false;
                            if ($this->user_info['services'] === '1') {
                                $row = $this->db->select(sprintf(
                                    "SELECT service_id, hostname, name FROM services WHERE user_id = %d AND product_id = %d",
                                    $this->user_info['user_id'], cfg::product_antispam
                                ));
                                if (isset($row['hostname'])) {
                                    $hostname = $row['hostname'] . ($row['name'] ? sprintf(' (%s)', $row['name']) : '');
                                } else if (isset($row['name'])) {
                                    $hostname = $row['name'];
                                } else {
                                    $hostname = '#' . $row['service_id'];
                                }
                            }
                            if(isset($_POST['rid']) && is_array($_POST['rid'])){
                                foreach ($_POST['rid'] as $rid) {
                                    $rids[]=$rid;
                                }
                            }
                            foreach ($requests as $request) {
                                if(empty($rids) || (!empty($rids) && in_array($request['id'], $rids))){
                                    fputcsv($df, array(
                                        $request['id'], (isset($request['h']) ? $request['h'] : $hostname), $request['dt'],
                                        $request['a'], $request['m'], $request['c'], $request['r'],
                                        $request['n'], $request['e'], $request['i'], (isset($request['f']) ? $request['f'] : '')
                                    ));
                                }
                            }
                            fclose($df);
                            $csv = ob_get_clean();
                        }

                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . $filename . '"');
                        header('Content-Transfer-Encoding: binary');
                        header('Content-Control: must-revalidate');
                        header('Content-Length: ' . mb_strlen($csv, '8bit'));
                        header('Set-Cookie: fileDownload=true; path=/');

                        echo $csv;
                        exit();
                    }

                    //$this->page_info['records_found'] = sprintf($this->lang['l_records_found'], number_format(count($requests), 0, ',', ' '));
                    $this->page_info['requests'] = &$requests;
                    $this->page_info['show_datepicker'] = true;

                    if (isset($this->lang['l_no_requests']) && isset($this->user_info['engine']))
                        $this->page_info['no_requests'] = sprintf($this->lang['l_no_requests'], $this->user_info['engine']);

                    $ints = $this->lang['ints'];
                    $this->page_info['ints'] = $ints;

                    if (isset($services[$this->service_id]['service_name']))
                        $this->page_info['head']['title'] = sprintf("%s %s", $services[$this->service_id]['service_name'], $this->lang['l_page_title']);
                    else
                        $this->page_info['head']['title'] = ucfirst($this->lang['l_page_title']);
                    if (isset($_GET['service_id']) && !in_array($_GET['service_id'], $this->granted_services_ids))
                        $this->show_setup_hint();

                }
                if (count($requests) > 0) {
                    $this->page_info['show_export'] = true;
                }

                //
                // Export to CSV
                //
                if (isset($_GET['mode']) && $_GET['mode'] == 'csv') {
                    $tools = new CleanTalkTools();
                    $tools->download_send_headers("requests_export_period_" . $this->int . '_' . date("Y-m-d-H-i-s") . ".csv");
                    $csv = '';
                    if (!empty($requests)) {
                        ob_start();
                        $df = fopen("php://output", 'w');
                        fputcsv($df, array_keys($requests[0]));
                        foreach ($requests as $request) {
                            $request['message_array'] = implode(', ', $request['message_array']);
                            fputcsv($df, array_values($request));
                        }
                        fclose($df);
                        $csv = ob_get_clean();
                    }
                    echo $csv;
                    exit();
                }

                // Вывод переключателя 7/45 days
                // Выводим переключатель если trial = 0 и отсутствует addon_id = 1
                $this->page_info['keep_history_45'] = 'none';
                if ($this->user_info['trial'] === '0') {
                    if (isset($this->user_info['addons'])) {
                        foreach ($this->user_info['addons'] as $addon) {
                            if ($addon['addon_id'] === '1') {
                                $this->page_info['keep_history_45'] = 'info';
                                $this->page_info['l_keep_history_45'] = $this->lang['l_keep_history_45'];
                                break;
                            }
                        }
                    }
                    if ($this->page_info['keep_history_45'] == 'none') {
                        $this->get_addon('keep_history_45_days');
                        $this->page_info['keep_history_45'] = 'toggle';
                    }
                    $this->page_info['keep_history'] = sprintf($this->lang['l_keep_history'], $this->options['days_2_keep_requests_extended']);
                    $this->page_info['keep_history_hint'] = $this->lang['l_keep_history_hint'];
                }

                $this->page_info['ajaxurl'] = $_SERVER['REQUEST_URI'];
                $this->page_info['requests_limit'] = $this->requests_limit;
                $this->page_info['recs_found'] = (int) $this->numrecs;
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
                $this->page_info['show_dashboard_tour'] = $this->options['show_dashboard_tour'];
                if(defined('cfg::show_dashboard_tour')){
                    $this->page_info['show_dashboard_tour'] = cfg::show_dashboard_tour;
                }

                break;
            case 28:
                switch ($this->cp_product_id) {
                    case cfg::product_database_api:
                        $this->api_stat();
                        break;
                    case cfg::product_hosting_antispam:
                        $this->hosting_stat();
                        break;
                    case cfg::product_security:
                        include 'inc_security_stat.php';
                        break;
                }
                if (!$this->check_access(null, true)) {
                    $user_email = null;
                    $this->page_info['show_aaid'] = false;
                    if (isset($_GET['aaid']) && preg_match("/^[a-f0-9]{1,32}$/", $_GET['aaid'])) {
                        $aaid = $_GET['aaid'];
                        $sql = sprintf("
                            select service_id, aak.user_id, u.email from aa_keys aak left join users u on u.user_id = aak.user_id where aaid = %s; 
                            ",
                            $this->stringToDB($aaid)
                        );
                        $row = $this->db->select($sql);

                        if (isset($row['user_id']) && isset($row['email'])) {
                            $user_email = $row['email'];
                            $this->user_id = $row['user_id'];
                            $this->user_info = $this->get_user_info($user_email);
                            $this->user_id = $this->user_info['user_id'];
                            $this->page_info['strict_tariff'] = false;
                            $this->page_info['show_promo'] = false;
                            $this->page_info['is_auth'] = false;
                            $this->page_info['show_local_translate'] = false;
                            $this->page_info['show_aaid'] = true;
                            $this->get_lang($this->ct_lang, 'main');
                            $this->get_lang($this->ct_lang, $this->link->class);

                            $this->page_info['user_info']['email'] = $this->obfuscate_email($user_email);
                            $this->page_info['show_signup_login'] = true;

                            if (isset($row['service_id'])) {
                                $_GET['service_id'] = $row['service_id'];
                            }

                            // Тормозим дабы не допустить подбор счетов
                            sleep(cfg::fail_timeout);
                        }
                    }
                    if (!$user_email) {
                        $this->url_redirect('session', null, true);
                    }
                }
                $this->show_service_stat();
                $this->page_info['show_dashboard_tour'] = $this->options['show_dashboard_tour'];
                if(defined('cfg::show_dashboard_tour')){
                    $this->page_info['show_dashboard_tour'] = cfg::show_dashboard_tour;
                }
                break;
            case 38:
                if (!$this->check_access(null, true)) {
                    if (!$this->app_mode) {
                        $this->url_redirect('session', null, true);
                    }
                }
                $this->show_api_stat();
                break;
            case 34:
                if (!$this->check_access(null, true)) {
                    if (!$this->app_mode) {
                        $this->url_redirect('session', null, true);
                    }
                }
                if ($this->app_mode && $this->app_return['auth']) {
                    // Отдаём логи SFW для мобильного приложения
                    $this->logs = new Logs($this->db, $this->user_info);
                    $sfw = $this->logs->sfw();

                    if (isset($_REQUEST['start_from']) && preg_match("/^\d+$/", $_REQUEST['start_from'])) {
                        $sfw->startFrom($_REQUEST['start_from']);
                    }
                    if (isset($_REQUEST['days']) && preg_match("/^\d+$/", $_REQUEST['days'])) {
                        $sfw->days = $_REQUEST['days'];
                    }
                    if (isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
                        $sfw->service_id = $_REQUEST['service_id'];
                    }

                    $requests = array();
                    $result = $sfw->logs('Y-m-d H:i:s');
                    foreach ($result['logs'] as $log) {
                        $requests[] = array(
                            'ip' => $log['sfw_ip'],
                            'datetime' => $log['mindt'],
                            'country' => $log['countrycode'],
                            'total' => $log['sumnumtotal'],
                            'allow' => $log['sumnumallow']
                        );
                    }
                    $this->app_return['requests'] = $requests;
                }
                if (!$this->app_mode) {
                    $this->page_info['head']['title'] = 'SpamFireWall ' . $this->lang['l_log'];
                    $this->page_info['bsdesign'] = true;
                    $this->smarty_template = 'includes/general.html';
                    $this->page_info['template']  = 'antispam/log-sfw.html';
                    $this->page_info['container_fluid'] = true;
                    $this->page_info['scripts'] = array(
                        '/my/js/sfw-log.min.js?v1',
                        '/my/js/bulk.js?v5',
                        '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js',
                        '//cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js',
                        '//cdn.datatables.net/plug-ins/1.10.16/sorting/date-de.js',
                        '//cdn.datatables.net/plug-ins/1.10.16/type-detection/formatted-num.js',
                        '//cdn.datatables.net/plug-ins/1.10.16/sorting/ip-address.js'
                    );
                    /*$this->page_info['scripts'] = array(
                        'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js',
                        '/my/js/jquery.download.min.js',
                        '/my/js/antispam-log.js?v01042018'
                    );
                    $this->page_info['styles'] = array(
                        '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css'
                    );*/

                    if (isset($this->user_info['license']) && !$this->user_info['license']['moderate']) {
                        $this->page_info['show_modal'] = true;
                    }

                    $service_id = 0;
                    if (isset($_GET['service_id']) && preg_match('/^\d+$/', $_GET['service_id'])) {
                        $service_id = (int)$_GET['service_id'];
                    }
                    $this->page_info['service_id'] = $service_id;

                    $sfw = $this->logs->sfw();
                    $is_grant = $service_id && in_array($service_id, $this->granted_services_ids);

                    if ($is_grant) {
                        $sfw->grant = $this->granted_services_ids;
                    } else if ($service_id) {
                        $sfw->service_id = $service_id;
                    }
                    if (isset($_GET['int'])) {
                        $sfw->interval = $_GET['int'];
                        if (in_array($_GET['int'], array('week', 'today', 'yesterday')))
                            $this->page_info['interval'] = $_GET['int'];
                    } else {
                        $sfw->interval = 'week';
                        $this->page_info['interval'] = 'week';
                    }
                    if (isset($_GET['country'])) $sfw->country = $_GET['country'];
                    if (isset($_GET['ip'])) {
                        $sfw->ip = $_GET['ip'];
                        $this->page_info['search_ip'] = $_GET['ip'];
                    }

                    $result = $sfw->logs();

                    $sfw_services = $this->db->select(sprintf("SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id=%d", $this->user_id, cfg::product_antispam), true);
                    $countries = $this->get_lang_countries();

                    $this->page_info['sfw_rows'] = $result['logs'];
                    $this->page_info['total_sfw'] = $result['total'];
                    $this->page_info['allow_sfw'] = $result['allow'];
                    $this->page_info['sfw_services'] = $sfw_services;
                    $this->page_info['countries'] = $countries;
                }
                $this->page_info['show_dashboard_tour'] = $this->options['show_dashboard_tour'];
                if(defined('cfg::show_dashboard_tour')){
                    $this->page_info['show_dashboard_tour'] = cfg::show_dashboard_tour;
                }
                break;

            //// ПРИВАТНЫЕ СПИСКИ
            case 35:
                include 'inc_show_private.php';
                break;
            // Security Log
            case 45:
                $this->security_log();
                break;
            // Security FireWall Log
            case 53:
                $this->security_firewall_log();
                break;
            // Malware Scans Log
            case 61:
                include 'inc_logs_mscan.php';
                break;
			default: break;
		}

		$this->display($page_template);
	}

	private function create_spbc_task($service_id, $action = 'update_security_firewall', $plugin_name = 'spbc') {
	    // Проверяем наличие созданных, но не обработанных записей
        if ($row = $this->db->select(sprintf("SELECT call_id FROM spbc_remote_calls WHERE user_id = %d AND service_id = %d AND call_action = %s AND notification_sent IS NULL", $this->user_info['user_id'], $service_id, $this->stringToDB($action)))) {
            $this->db->run(sprintf("UPDATE spbc_remote_calls SET created = now() WHERE call_id = %d", $row['call_id']));
        } else {
            $this->db->run(sprintf("INSERT INTO spbc_remote_calls (created, user_id, service_id, call_action, plugin_name) VALUES (now(), %d, %d, %s, %s)",
                $this->user_info['user_id'], $service_id, $this->stringToDB($action), $this->stringToDB($plugin_name)));
        }
    }

	private function security_private_list() {
        if (!$this->check_access(null, true)) {
            $this->url_redirect('session', null, true);
        }

        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/private.html';
        $this->page_info['head']['title'] = $this->lang['l_private_title'];
        $this->page_info['container_fluid'] = true;

        $this->page_info['scripts'] = array(
            '//cdn.jsdelivr.net/momentjs/latest/moment.min.js',
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js'
        );
        $this->page_info['styles'] = array(
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css'
        );
    }

    private function security_firewall_log() {
        if (!$this->check_access(null, true)) {
            $this->url_redirect('session', null, true);
        }

        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/log_firewall.html';
        $this->page_info['head']['title'] = $this->lang['l_log_firewall_title'];
        $this->page_info['container_fluid'] = true;

        $this->page_info['scripts'] = array(
            '//cdn.jsdelivr.net/momentjs/latest/moment.min.js',
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js',
            '/my/js/bulk.js?v5',
            '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js',
            '//cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js',
            '//cdn.datatables.net/plug-ins/1.10.16/sorting/date-de.js',
            '//cdn.datatables.net/plug-ins/1.10.16/type-detection/formatted-num.js',
            '//cdn.datatables.net/plug-ins/1.10.16/sorting/ip-address.js'
        );
        $this->page_info['styles'] = array(
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css',
            '//cdn.datatables.net/r/bs-3.3.5/jq-2.1.4,dt-1.10.8/datatables.min.css',
        );

        $services = array();
        $rows = $this->db->select(sprintf("SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d", $this->user_id, cfg::product_security), true);
        foreach ($rows as $row) {
            $services[$row['service_id']] = $row;
        }
        $this->page_info['services'] = $services;
		
		$statuses = array('DENY','ALLOW','DENY_BY_NETWORK','DENY_BY_DOS','DENY_ALL');	
		$this->page_info['statuses'] = $statuses;

        // Установки временной зоны пользователя
        $tz = (isset($this->user_info['timezone'])) ? (float)$this->user_info['timezone'] : 0;
        $tz_ts = ($tz - 5) * 3600;
        $tz_user = $tz*3600;

        $date_range_begin = strtotime('-1 week');
        $date_range_end = time();
        $sql_range_begin = sprintf("'%s'", gmdate('Y-m-d H:i:s', (strtotime(date('Y-m-d 00:00:00'))) - 60 * 60 * 24 * 7));
        $sql_range_end = sprintf("'%s'", gmdate('Y-m-d H:i:s', time()));

        $sql_where = array('user_id = ' . $this->user_id);

        if (count($_GET)) {
            if (isset($_GET['date_range']) && preg_match('/^([0-9]{4}\-[0-9]{2}\-[0-9]{2})_([0-9]{4}\-[0-9]{2}\-[0-9]{2})$/i', $_GET['date_range'], $matches)) {
                $date_range_begin = strtotime(date('Y-m-d 00:00:00', strtotime($matches[1])));
                $date_range_end = strtotime(date('Y-m-d 23:59:59', strtotime($matches[2])));
                $sql_range_begin = sprintf("'%s'", date('Y-m-d H:i:s', $date_range_begin - $tz_user));
                $sql_range_end = sprintf("'%s'", date('Y-m-d H:i:s', $date_range_end - $tz_user));
            }
            if (isset($_GET['ip']) && preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $_GET['ip'])) {
                $sql_where[] = sprintf("l.visitor_ip = INET_ATON('%s')", $_GET['ip']);
                $this->page_info['ip_current'] = $_GET['ip'];
            }
			if (isset($_GET['ip']) && preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\/(\d{1,2})$/', $_GET['ip'], $matches)) {
				$mask = pow(2,32) - pow(2,32 - $matches[2]);
				$net = ip2long($matches[1]) & $mask;
				$sql_where[] = sprintf("l.visitor_ip & %.0f = %.0f", 
					$mask,
					$net
				);
//				var_dump($matches, $mask, $net, $sql_where);
                $this->page_info['ip_current'] = $_GET['ip'];
            }
            if (isset($_GET['country']) && preg_match('/^[A-Za-z]{2}$/', $_GET['country'])) {
                $sql_where[] = sprintf("l.visitor_country = '%s'", $_GET['country']);
                $this->page_info['country_current'] = $_GET['country'];
            }
            if (isset($_GET['service']) && isset($services[$_GET['service']])) {
                $sql_where[] = sprintf("l.service_id = %d", $_GET['service']);
                $this->page_info['service_current'] = $_GET['service'];
            }
            if (isset($_GET['status']) && in_array($_GET['status'], $statuses)) {
                if($_GET['status']=='DENY_ALL'){
                    $sql_where[] = "l.status LIKE 'DENY%'";
                }else{
                    $sql_where[] = sprintf("l.status = %s", $this->stringToDB($_GET['status']));
                }
                $this->page_info['status_current'] = $_GET['status'];
            }
        }
        $sql_where[] = sprintf("l.datetime BETWEEN %s AND %s", $sql_range_begin, $sql_range_end);
        $sql_count = sprintf("SELECT count(*) as 'count' FROM security_firewall_logs l LEFT JOIN asn a ON a.asn_id = l.asn_id WHERE %s", implode(' AND ', $sql_where));
        $result = $this->db->select($sql_count);
        $records_count = $result['count'];
         // Постраничная навигация                            
        $current_page = 1; // Страница по умолчанию
        $visible_pages = 5; // Количество отображаемых страниц
        $page_from = 1; // Номер первой страницы
        $this->page_info['items_per_page_list'] = $this->items_per_page;
        // Количество записей читаем из куки
        if(isset($_COOKIE['sfwl_ipp']) && in_array($_COOKIE['sfwl_ipp'], $this->items_per_page)){
            $items_per_page = intval($_COOKIE['sfwl_ipp']);
        }else{
            $items_per_page = 100; // Количество записей по умолчанию
        }

        // Установим количество записей на странице, если значение из списка разрешенных
        if(isset($_GET['items_per_page']) && in_array($_GET['items_per_page'], $this->items_per_page)){
            $items_per_page = intval($_GET['items_per_page']);
            setcookie('sfwl_ipp', $items_per_page, time() + 365*24*60*60, '/', $this->cookie_domain);
        }
        $this->page_info['items_per_page'] = $items_per_page;

        // Установим текущую страницу
        if(isset($_GET['page'])){
            $current_page = intval($_GET['page']);
            if($current_page<1){
                $current_page = 1;
            }
        }

        // Максимальное кол-во страниц
        $total_pages_num = ceil($records_count / $items_per_page);

        // Переопределяем текущую страницу, если она больше максимальной
        if($total_pages_num<$current_page && $total_pages_num>0){
            $current_page = $total_pages_num;
        }
        $this->page_info['total_pages'] = $total_pages_num;
        $this->page_info['current_page'] = $current_page;
        $this->page_info['records_found'] = sprintf($this->lang['l_records_found'], number_format($records_count, 0, ',', ' '));
        // Определяем номера страниц для показа
        if ($current_page > floor($visible_pages/2)){
            $page_from = max(1, $current_page-floor($visible_pages/2));
        }
        if ($current_page > $total_pages_num-ceil($visible_pages/2)){
            $page_from = max(1, $total_pages_num-$visible_pages+1);
        }                            
        $page_to = min($page_from+$visible_pages-1, $total_pages_num);
        for ($i = $page_from; $i<=$page_to; $i++){
            $pages[]=$i;
        }
        $this->page_info['pages'] = isset($pages) ? $pages : false;
        $this->page_info['current_page'] = $current_page;
        $url = array();
        if(is_array($_REQUEST)){
            foreach ($_REQUEST as $key => $val) {
                if(!empty($val) && !in_array($key, array('page','items_per_page','q'))){
                    $url[$key] = $val;
                }
            }
        }
        $this->page_info['url'] = '/my/logs_firewall?'.http_build_query($url);
        $sql_limit = sprintf('LIMIT %d,%d', ($current_page-1)*$items_per_page, $items_per_page);
        $sql = sprintf(
            "SELECT l.datetime, l.updated, INET_NTOA(l.visitor_ip) AS visitor_ip, l.service_id, l.hits, l.status, l.visitor_country, l.page_url,
                           a.asn_id, a.org_name
                    FROM security_firewall_logs l
                    LEFT JOIN asn a ON a.asn_id = l.asn_id
                    WHERE %s ORDER BY l.datetime DESC %s",
                    implode(' AND ', $sql_where),
                    $sql_limit
					);
		$rows = $this->db->select($sql, true);

		$asn_ip = null;
        foreach ($rows as &$row) {
            $row['country'] = ($row['visitor_country'] && isset($this->countries[$row['visitor_country']])) ? $this->countries[$row['visitor_country']] : '-';
            $row['service'] = isset($services[$row['service_id']]) ? $services[$row['service_id']]['hostname'] : '-';
            if (isset($row['page_url'])) {
                $row['url'] = $row['page_url'];
                if (strlen($row['url']) > 50) {
                    $row['page_url'] = substr($row['url'], 0, 47) . '...';
                }
			}
			/*if (!isset($asn_ip[$row['visitor_ip']])) {
				$sql = sprintf("select a.asn_id, a.org_name from asn a left join bl_ips_networks bipn on bipn.asn_id = a.asn_id where bipn.network = %d & bipn.mask limit 1;",
					ip2long($row['visitor_ip'])
				);
//				$asn = $this->db->select($sql);
				if (isset($asn['asn_id'])) {
					$asn_ip[$row['visitor_ip']] = $asn;
                	$row['asn'] = sprintf('AS%d %s', $asn['asn_id'], $asn['org_name']);
				}
			} else {
                $row['asn'] = sprintf('AS%d %s', $asn_ip[$row['visitor_ip']]['asn_id'], $asn_ip[$row['visitor_ip']]['org_name']);
			} */
			
            if (isset($row['asn_id'])) {
                $row['asn'] = sprintf('AS%d %s', $row['asn_id'], $row['org_name']);
			}
            $row['updated'] = date('M d, Y H:i:s', strtotime($row['updated']) + $tz_ts);
			$row['datetime'] = date('M d, Y H:i:s', strtotime($row['datetime']) + $tz_user);
			if (isset($this->lang['l_security_firewall_status_' . strtolower($row['status'])])) {
				$row['status_human'] = $this->lang['l_security_firewall_status_' . strtolower($row['status'])];
			}
        }
        $this->page_info['logs'] = $rows;

        if (!count($rows) && count($_GET) === 1) {
            $row = $this->db->select(sprintf("SELECT hostname FROM services WHERE user_id = %d AND product_id = %d LIMIT 1", $this->user_id, cfg::product_security));
            if ($row) {
                $this->page_info['service_hostname'] = $row['hostname'];
            }
        }

        if (isset($this->user_info['license']) && !$this->user_info['license']['moderate']) {
            $this->page_info['modal_license'] = '/my/bill/security';
        }

        $this->page_info['date_range_begin'] = date('M d, Y', $date_range_begin);
        $this->page_info['date_range_end'] = date('M d, Y', $date_range_end);
        $this->page_info['date_range'] = sprintf('%s_%s', date('Y-m-d', $date_range_begin), date('Y-m-d', $date_range_end));
        $this->page_info['countries'] = $this->countries;
    }

	private function security_log() {
        if (!$this->check_access(null, true)) {
            $this->url_redirect('session', null, true);
        }

        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/log.html';
        $this->page_info['head']['title'] = $this->lang['l_log_title'];
        $this->page_info['container_fluid'] = true;

        $this->page_info['scripts'] = array(
            '//cdn.jsdelivr.net/momentjs/latest/moment.min.js',
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js',
            '/my/js/bulk.js?v5',
            '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js',
            '//cdn.datatables.net/1.10.16/js/dataTables.bootstrap.min.js',
            '//cdn.datatables.net/plug-ins/1.10.16/sorting/date-de.js',
            '//cdn.datatables.net/plug-ins/1.10.16/type-detection/formatted-num.js',
            '//cdn.datatables.net/plug-ins/1.10.16/sorting/ip-address.js'
        );
        $this->page_info['styles'] = array(
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css',
            '//cdn.datatables.net/r/bs-3.3.5/jq-2.1.4,dt-1.10.8/datatables.min.css',
        );

        $event_types = array(
            'authorization' => array('login', 'logout', 'auth_failed', 'invalid_username', 'invalid_email'),
            'audit' => array('View', 'view'),
            'login' => array('login', 'logout'),
            'attack' => array('auth_failed', 'invalid_username', 'invalid_email')
        );
        $event_ids = array('login', 'logout', 'invalid_username', 'invalid_email', 'auth_failed', 'view');

        $services = array();
        $rows = $this->db->select(sprintf("SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d", $this->user_id, cfg::product_security), true);
        foreach ($rows as $row) {
            $services[$row['service_id']] = $row;
        }
        $this->page_info['services'] = $services;

        $tz = (isset($this->user_info['timezone'])) ? (float)$this->user_info['timezone'] : 0;
        $tz_ts = ($tz - 5) * 3600;
        $tz_user = $tz*3600;

        $date_range_begin = strtotime('-1 week');
        $date_range_end = time();
        $sql_range_begin = sprintf("'%s'", gmdate('Y-m-d H:i:s', (strtotime(date('Y-m-d 00:00:00'))) - 60 * 60 * 24 * 7));
        $sql_range_end = sprintf("'%s'", gmdate('Y-m-d H:i:s', time()));
        
        $sql_where = array('user_id = ' . $this->user_id);

        if (count($_GET)) {
            if (isset($_GET['date_range']) && preg_match('/^([0-9]{4}\-[0-9]{2}\-[0-9]{2})_([0-9]{4}\-[0-9]{2}\-[0-9]{2})$/i', $_GET['date_range'], $matches)) {
                $date_range_begin = strtotime(date('Y-m-d 00:00:00', strtotime($matches[1])));
                $date_range_end = strtotime(date('Y-m-d 23:59:59', strtotime($matches[2])));
                $sql_range_begin = sprintf("'%s'", date('Y-m-d H:i:s', $date_range_begin - $tz_user));
                $sql_range_end = sprintf("'%s'", date('Y-m-d H:i:s', $date_range_end - $tz_user));
            }
            if (isset($_GET['event']) && in_array($_GET['event'], array_keys($event_types))) {
                $sql_where[] = sprintf("event IN ('%s')", implode("', '", $event_types[$_GET['event']]));
                $this->page_info['event_current'] = $_GET['event'];
                if ($_GET['event'] == 'audit') {
                    $this->page_info['event_id_current'] = 'view';
                }
            }
            if (isset($_GET['event_id']) && in_array($_GET['event_id'], $event_ids)) {
                $sql_where[] = sprintf("event = %s", $this->stringToDB($_GET['event_id']));
                $this->page_info['event_id_current'] = $_GET['event_id'];
            }
            if (isset($_GET['ip']) && preg_match('/^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$/', $_GET['ip'])) {
                $sql_where[] = sprintf("auth_ip = INET_ATON('%s')", $_GET['ip']);
                $this->page_info['ip_current'] = $_GET['ip'];
            }
            if (isset($_GET['country']) && preg_match('/^[A-Za-z]{2}$/', $_GET['country'])) {
                $sql_where[] = sprintf("ip_country = '%s'", $_GET['country']);
                $this->page_info['country_current'] = $_GET['country'];
            }
            if (isset($_GET['service']) && isset($services[$_GET['service']])) {
                $sql_where[] = sprintf("service_id = %d", $_GET['service']);
                $this->page_info['service_current'] = $_GET['service'];
            }
            if (isset($_GET['username']) && !empty($_GET['username'])) {
                $_GET['username'] = str_replace(array("'", '"', '\\', '<', '>'), '', mb_strtolower($_GET['username']));
                $sql_where[] = sprintf("user_log = '%s'", $_GET['username']);
                $this->page_info['username_current'] = $_GET['username'];
            }
        }
        $sql_where[] = sprintf("datetime BETWEEN %s AND %s", $sql_range_begin, $sql_range_end);
        $sql_count = sprintf("SELECT count(*) as 'count' FROM services_security_log WHERE %s ", implode(' AND ', $sql_where));
        $result = $this->db->select($sql_count);
        $records_count = $result['count'];
         // Постраничная навигация                            
        $current_page = 1; // Страница по умолчанию
        $visible_pages = 5; // Количество отображаемых страниц
        $page_from = 1; // Номер первой страницы
        $this->page_info['items_per_page_list'] = $this->items_per_page;
        // Количество записей читаем из куки
        if(isset($_COOKIE['sl_ipp']) && in_array($_COOKIE['sl_ipp'], $this->items_per_page)){
            $items_per_page = intval($_COOKIE['sl_ipp']);
        }else{
            $items_per_page = 100; // Количество записей по умолчанию
        }

        // Установим количество записей на странице, если значение из списка разрешенных
        if(isset($_GET['items_per_page']) && in_array($_GET['items_per_page'], $this->items_per_page)){
            $items_per_page = intval($_GET['items_per_page']);
            setcookie('sl_ipp', $items_per_page, time() + 365*24*60*60, '/', $this->cookie_domain);
        }
        $this->page_info['items_per_page'] = $items_per_page;

        // Установим текущую страницу
        if(isset($_GET['page'])){
            $current_page = intval($_GET['page']);
            if($current_page<1){
                $current_page = 1;
            }
        }

        // Максимальное кол-во страниц
        $total_pages_num = ceil($records_count / $items_per_page);

        // Переопределяем текущую страницу, если она больше максимальной
        if($total_pages_num<$current_page && $total_pages_num>0){
            $current_page = $total_pages_num;
        }
        $this->page_info['total_pages'] = $total_pages_num;
        $this->page_info['current_page'] = $current_page;
        $this->page_info['records_found'] = sprintf($this->lang['l_records_found'], number_format($records_count, 0, ',', ' '));
        // Определяем номера страниц для показа
        if ($current_page > floor($visible_pages/2)){
            $page_from = max(1, $current_page-floor($visible_pages/2));
        }
        if ($current_page > $total_pages_num-ceil($visible_pages/2)){
            $page_from = max(1, $total_pages_num-$visible_pages+1);
        }                            
        $page_to = min($page_from+$visible_pages-1, $total_pages_num);
        for ($i = $page_from; $i<=$page_to; $i++){
            $pages[]=$i;
        }
        $this->page_info['pages'] = isset($pages) ? $pages : false;
        $this->page_info['current_page'] = $current_page;
        $url = array();
        if(is_array($_REQUEST)){
            foreach ($_REQUEST as $key => $val) {
                if(!empty($val) && !in_array($key, array('page','items_per_page','q'))){
                    $url[$key] = $val;
                }
            }
        }
        $this->page_info['url'] = '/my/logs?'.http_build_query($url);
        $sql_limit = sprintf('LIMIT %d,%d', ($current_page-1)*$items_per_page, $items_per_page);
        $sql = sprintf("SELECT datetime, submited, event, INET_NTOA(auth_ip) AS auth_ip, ip_country, service_id, user_log, page_url, event_runtime FROM services_security_log WHERE %s ORDER BY datetime DESC %s",
            implode(' AND ', $sql_where),$sql_limit);
        $rows = $this->db->select($sql, true);
        foreach ($rows as &$row) {
            $row['country'] = ($row['ip_country'] && isset($this->countries[$row['ip_country']])) ? $this->countries[$row['ip_country']] : '-';
            $row['service'] = isset($services[$row['service_id']]) ? $services[$row['service_id']]['hostname'] : '-';
            $row['user_log'] = htmlspecialchars($row['user_log']);
            $row['submited'] = date('M d, Y H:i:s', strtotime($row['submited']) + $tz_ts);
            $row['datetime'] = date('M d, Y H:i:s', strtotime($row['datetime']) + $tz_user);
            if ($row['event_runtime'] > 0) {
                $event_runtime_hours = floor($row['event_runtime'] / 3600);
                $event_runtime_mins = floor($row['event_runtime'] / 60 % 60);
                $event_runtime_secs = floor($row['event_runtime'] % 60);
                $row['event_runtime'] = sprintf('%02d:%02d:%02d', $event_runtime_hours, $event_runtime_mins, $event_runtime_secs);
            } else {
                $row['event_runtime'] = '-';
            }
            $row['status_class'] = 'text-success';
            $row['status'] = 'Passed';
            switch ($row['event']) {
                case 'auth_failed':
                    $row['event_class'] = 'text-danger';
                    $row['status_class'] = 'text-danger';
                    $row['event_title'] = sprintf($this->lang['l_log_event_title_auth_failed'], $row['user_log']);
                    $row['status'] = 'Banned';
                    break;
                case 'login':
                    $row['event_class'] = 'text-success';
                    $row['event_title'] = sprintf($this->lang['l_log_event_title_login'], $row['user_log']);
                    break;
                case 'logout':
                    $row['event_title'] = sprintf($this->lang['l_log_event_title_logout'], $row['user_log']);
                    break;
                case 'view':
                case 'View':
                    $row['event_class'] = 'text-info';
                    $row['event_title'] = $this->lang['l_log_event_title_view'];
                    break;
                case 'invalid_username':
                    $row['status_class'] = 'text-danger';
                    $row['event_title'] = $this->lang['l_log_event_title_invalid_username'];
                    $row['status'] = 'Banned';
                    break;
                case 'invalid_email':
                    $row['status_class'] = 'text-danger';
                    $row['event_title'] = $this->lang['l_log_event_title_invalid_email'];
                    $row['status'] = 'Banned';
                    break;
                default:
                    $row['event_class'] = '';
                    break;
            }
        }
        $this->page_info['logs'] = $rows;

        if (!count($rows) && count($_GET) === 1) {
            $row = $this->db->select(sprintf("SELECT hostname FROM services WHERE user_id = %d AND product_id = %d LIMIT 1", $this->user_id, cfg::product_security));
            if ($row) {
                $this->page_info['service_hostname'] = $row['hostname'];
            }
        }

        if (isset($this->user_info['license']) && !$this->user_info['license']['moderate']) {
            $this->page_info['modal_license'] = '/my/bill/security';
        }

        $this->page_info['event_types'] = $event_types;
        $this->page_info['date_range_begin'] = date('M d, Y', $date_range_begin);
        $this->page_info['date_range_end'] = date('M d, Y', $date_range_end);
        $this->page_info['date_range'] = sprintf('%s_%s', date('Y-m-d', $date_range_begin), date('Y-m-d', $date_range_end));
        $this->page_info['countries'] = $this->countries;
    }

    private function hosting_stat() {
        if (!$this->check_access(null, true)) {
            $this->url_redirect('session', null, true);
        }

        $this->get_lang($this->ct_lang, 'Hoster');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'hoster/stat.html';
        $this->page_info['head']['title'] = $this->lang['l_stat_title'];
        $this->page_info['container_fluid'] = true;

        $this->page_info['scripts'] = array(
            '//cdn.jsdelivr.net/momentjs/latest/moment.min.js',
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js',
            '/my/js/hosting-stat.min.js?v22052017'
        );
        $this->page_info['styles'] = array(
            '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css'
        );

        if (isset($this->user_info['license']) && !isset($_GET['is_ajax'])) {
            $ips = $this->db->select(sprintf("SELECT ip FROM ips WHERE user_id = %d ORDER BY ip", $this->user_info['user_id']), true);
            $this->page_info['ips'] = array();
            foreach ($ips as $ip) {
                $this->page_info['ips'][] = $ip['ip'];
            }
            $license = $this->user_info['license'];
            if (!$license['moderate']) {
                $this->page_info['modal_license'] = '/my/bill/hosting';
            }
        } else if (isset($this->user_info['license']) && isset($_GET['is_ajax'])) {
            $response = array();

            $users = array($this->user_info['user_id']);
            $rows = $this->db->select(sprintf("SELECT user_id FROM users WHERE hoster_id = %d", $this->user_info['user_id']), true);
            foreach ($rows as $row) {
                $users[] = $row['user_id'];
            }

            $date_start = (isset($_GET['date_start']) && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_GET['date_start'])) ?
                strtotime($_GET['date_start']) : time() - 86400 * 7;
            $date_end = (isset($_GET['date_end']) && preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_GET['date_end'])) ?
                strtotime($_GET['date_end']) : time();

            if (isset($_GET['ip']) && preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $_GET['ip'])) {
                $ips = $this->db->select(sprintf("SELECT * FROM ips WHERE user_id = %d AND ip = %s", $this->user_info['user_id'], $this->stringToDB($_GET['ip'])), true);
            } else {
                $ips = $this->db->select(sprintf("SELECT * FROM ips WHERE user_id = %d ORDER BY ip", $this->user_info['user_id']), true);
            }
            foreach($ips as $ip) {
                $sql = sprintf(
                    "SELECT %s FROM services s LEFT JOIN users u ON u.user_id = s.user_id WHERE s.user_id IN (%s) AND s.ip = inet_aton(%s)",
                    implode(', ', array(
                        's.service_id', 's.user_id', 's.name', 's.hostname', 's.engine', 's.ip',
                        'u.email'
                    )),
                    implode(', ', $users),
                    $this->stringToDB($ip['ip']));
                $services = $this->db->select($sql, true);

                foreach ($services as $service) {
                    $service['hostname'] = explode('.', $service['hostname']);
                    $service['hostname'][0] = substr($service['hostname'][0], 0, round(strlen($service['hostname'][0]) / 2))
                    . str_repeat('*', round(strlen($service['hostname'][0]) / 2));
                    $service['hostname'] = implode('.', $service['hostname']);
                    if ($service['email']) {
                        $service['email'] = explode('@', $service['email']);
                        $service['email'][0] = substr($service['email'][0], 0, round(strlen($service['email'][0]) / 2))
                            . str_repeat('*', round(strlen($service['email'][0]) / 2));
                        $service['email'] = implode('@', $service['email']);
                    } else {
                        $service['email'] = '#' . $service['user_id'];
                    }
                    $sql = sprintf(
                        "SELECT SUM(allow) AS allow, SUM(count) AS count FROM requests_stat_services WHERE user_id = %d AND service_id = %d AND date BETWEEN %s AND %s",
                        $service['user_id'], $service['service_id'],
                        $this->stringToDB(date('Y-m-d', $date_start)),
                        $this->stringToDB(date('Y-m-d', $date_end))
                    );
                    $stats = $this->db->select($sql);
                    /*$stats = $this->db->select(sprintf(
                        "SELECT SUM(allow) AS allow, SUM(count) AS count FROM requests_stat_ips WHERE user_id = %d AND ip_id = %d AND date BETWEEN %s AND %s",
                        $service['user_id'], $ip['ip_id'],
                        $this->stringToDB(date('Y-m-d', $date_start)),
                        $this->stringToDB(date('Y-m-d', $date_end))
                    ));*/
                    if (!isset($stats['allow'])) {
                        $stats = array('allow' => 0, 'count' => 0);
                    }
                    $response[] = array(
                        'ip' => $ip['ip'],
                        'name' => $service['email'],
                        'site' => $service['hostname'],
                        'deny' => $stats['count'] - $stats['allow'],
                        'allow' => $stats['allow'],
                        'date_start' => date('Y-m-d', $date_start),
                        'date_end' => date('Y-m-d', $date_end)
                    );
                }
            }

            echo(json_encode($response));
            exit;
        } else if (isset($_GET['is_ajax'])) {
            echo(json_encode(array()));
            exit;
        }

        $this->display();
        exit;
    }

	private function api_stat() {
        $this->get_lang($this->ct_lang, 'Api');
	    $this->page_info['bsdesign'] = true;
        $this->page_info['template'] = 'api-trends.html';
        $this->page_info['head']['title'] = $this->lang['l_api_trends_title'];

        // Период для показа статистики
        $period = array();
        if (isset($_GET['int'])) {
            switch ($_GET['int']) {
                case '7':
                    $period['interval'] = 7;
                    $period['chartPoint'] = 'day';
                    break;
                case '30':
                    $period['interval'] = 30;
                    $period['chartPoint'] = 'day';
                    break;
                case '365':
                    $period['interval'] = 365;
                    $period['chartPoint'] = 'month';
                    break;
                default:
                    $period['interval'] = 7;
                    $period['chartPoint'] = 'day';
                    break;
            }
            $s = time() - (86400 * $period['interval']);
            $period['start'] = date('Y-m-d 00:00:00', $s);
            $period['start_t'] = date('M d, Y', $s);
            $period['end'] = date('Y-m-d 00:00:00');
            $period['end_t'] = date('M d, Y');
        } else if (isset($_GET['start_from']) && isset($_GET['end_to'])) {
            $s = preg_replace('/[^0-9]/i', '', $_GET['start_from']);
            $e = preg_replace('/[^0-9]/i', '', $_GET['end_to']);
            $period['start'] = date('Y-m-d 00:00:00', $s);
            $period['start_t'] = date('M d, Y', $s);
            $period['end'] = date('Y-m-d 00:00:00', $e);
            $period['end_t'] = date('M d, Y', $e);
            $periodDiff = floor((time() - strtotime($period['start'])) / 86400);
            $period['chartPoint'] = ($periodDiff > 30) ? 'month' : 'day';
        } else {
            $s = time() - (86400 * 7);
            $period = array(
                'start' => date('Y-m-d 00:00:00', $s),
                'start_t' => date('M d, Y', $s),
                'end' => date('Y-m-d 00:00:00'),
                'end_t' => date('M d, Y'),
                'chartPoint' => 'day',
                'interval' => 7
            );
        }
        $this->page_info['period'] = $period;

        // Загружаем методы
        $methods = array();
        $m = false;
        $rows = $this->db->select('SELECT method_name, method_id FROM api_methods WHERE method_type=1', true);
        foreach ($rows as $method) {
            $methods[] = array(
                'name' => $method['method_name'],
                'selected' => (isset($_GET['method']) && $_GET['method'] == $method['method_name']) ? true : false
            );
            $method_ids[]=$method['method_id'];
            if (!$m && isset($_GET['method']) && $_GET['method'] == $method['method_name']) $m = $method['method_id'];
        }
        $this->page_info['methods'] = $methods;
        // Данные для таблицы
        $sqlMethod = ($m) ? ' AND method_id = ' . $m : ' AND method_id IN ('.implode(',', $method_ids).')';
        if($period['chartPoint'] == 'day'){ // По дням
            $sql = sprintf("SELECT * FROM (
                            SELECT date, DATE_FORMAT(date,'%%b %%d, %%Y') as display_date, SUM(calls) as calls, SUM(checks) as checks, SUM(blacklisted_checks) as blacklisted_checks FROM api_methods_stat 
                            WHERE user_id=%d
                            AND date BETWEEN '%s' AND '%s' %s
                            GROUP BY date WITH ROLLUP
                        ) stat ORDER BY date DESC", $this->user_id, $period['start'], $period['end'],$sqlMethod);
        }else{ // По месяцам
            $sql = sprintf("SELECT *,DATE_FORMAT(CONVERT(CONCAT(date,'-01'),DATE),'%%b %%Y') as display_date FROM (
                            SELECT DATE_FORMAT(date,'%%Y-%%m') as date, SUM(calls) as calls, SUM(checks) as checks, SUM(blacklisted_checks) as blacklisted_checks FROM api_methods_stat 
                            WHERE user_id=%d
                            AND date BETWEEN '%s' AND '%s' %s
                            GROUP BY  DATE_FORMAT(date,'%%Y-%%m') WITH ROLLUP
                        ) stat ORDER BY date DESC", $this->user_id, date('Y-m-01',strtotime($period['start'])), $period['end'],$sqlMethod);
        }
        $stats = $this->db->select($sql, true);
        foreach ($stats as &$stat) {
            $stat['efficiency'] = $stat['checks'] ? $stat['blacklisted_checks']/$stat['checks']*100 : 0;
            if($stat['display_date'])// Данные для графика
                $chartJSON[$stat['display_date']] = $stat['checks'];
        }

        // Заполнение нулями данных для графика
        $start_zerofill = strtotime($period['start']);
        $end_zerofill = strtotime($period['end']);
        $chart_zerofill = array();
        while ($start_zerofill <= $end_zerofill) {
            if($period['chartPoint']=='day'){
                $date_format = 'M d, Y';
            }else{
                $date_format = 'M Y';
            }
            if(isset( $chartJSON[ date($date_format, $start_zerofill) ] )){
                $chart_zerofill[ date($date_format, $start_zerofill) ] = $chartJSON[ date($date_format, $start_zerofill) ];
            }else{
                $chart_zerofill[ date($date_format, $start_zerofill) ] = 0;
            }
            if($period['chartPoint']=='day'){
                $start_zerofill = strtotime('+1 day',$start_zerofill);
            }else{
                $start_zerofill = strtotime('+1 month',$start_zerofill);
            }
        }

        $this->page_info['stats'] = $stats;
        $this->page_info['chart'] = json_encode($chart_zerofill);
        $this->page_info['chartData'] = isset($chartPoints) ? $chartPoints : false;

        $this->display();
        exit;
    }


    /**
      * Функция проверяет тип записи и возвращает record_type
      *
      * @param string $record Запись для определения
      *
      * @param string $service_type Тип журнала - antispam или firewall
      *
      * @return int
      */

    private function check_private_list_record($record, $service_type = null){
		$this->private_list_domain = null; 
        $record_type = 0;
        // Стоп-слово
        if(substr($record, 0,1)=='='){
            return 8;
        }

        if ($this->valid_email($record)){
            $record_type = 2;
        }

        if ($record_type === 0 && filter_var($record, FILTER_VALIDATE_IP)) {
            $record_type = 1;
            if ($service_type && $service_type == 'spamfirewall') {
                $record_type = 6;
            }
        }

        // Домен второго уровня
		if ($record_type === 0 && preg_match('/^[\.a-zA-Z0-9_\-]+[\.]{1}[a-zA-Z]+$/i',$record, $matches)){
			$record_type = 4;
			// $this->private_list_domain = $domain;
		}

		// Ссылка.
        if ($record_type === 0) { 
			$tools = new CleanTalkTools();
			$domain = $tools->get_domain($record);
			if ($domain != $record && preg_match("/\.(\w+)$/", $domain, $matches)) {
				$dmnfile = file_get_contents(dirname(dirname(__FILE__)) . '/domains.txt');
				$domains = explode("\n", $dmnfile);
				if (in_array(strtoupper($matches[1]), $domains)) {
					$record_type = 4;
					$this->private_list_domain = $domain;
				}
			}
        }

        // Домен 1-го уровня
        if ($record_type === 0 && preg_match('/^[a-zA-Z]+$/i',$record, $matches))
            $record_type = 5;

        if ($record_type === 0 && preg_match('/^country_[A-Z]+$/i',$record, $matches))
            $record_type = 3;

        // SpamFireWall networks
        if ($record_type === 0 && preg_match('/(^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,2})$/i',$record, $matches)){
            if (ip2long($matches[1]) !== false && $matches[2] >= 0 && $matches[2] <= 32) {
                if ($service_type && $service_type == 'spamfirewall') {
                    $record_type = 6;
                } else {
                    $record_type = 7; // AntiSpam
                }
            }
        }

        return $record_type;

    }

    /**
      * Возвращает сеть/маску, если указан ip/маска.
      *
      * @param string $record Запись для проверки
      *
      * @return string
      */

    public function get_network_record($record = null) {
        if (preg_match("/^([0-9\.]+)\/(\d+)$/", $record, $matches)) {
            $ip_long = ip2long($matches[1]);
            if ($ip_long !== false && $matches[2] >= 0 && $matches[2] <= 32) {
                $mask = pow(2,32) - pow(2,32 - $matches[2]);
                $network = $ip_long & $mask;
                if ($ip_long != $network) {
                    $record = sprintf("%s/%d",
                        long2ip($network),
                        $matches[2]
                    );
                }
            }
        }
        return $record;
    }

    /**
      * Функция выводит общую статистику по сервису
      *
      * @return void
      */

    private function show_service_stat() {

        $this->page_info['bsdesign'] = true;

        // Показываем сообщение о добавлении записей в приватные списки

        $this->page_info['show_top20_message'] = false;
        if (isset($_COOKIE['show_stat_message']) && $_COOKIE['show_stat_message'] == 1){
            $this->page_info['show_top20_message'] = true;
            setcookie('show_stat_message', 1, time() - 300, '/', $this->cookie_domain);
        }

        $default_service_id = 0;
        $this->page_info['default_service_id'] = $default_service_id;
        $this->service_id = $default_service_id;

        $rows = $this->db->select(sprintf("select service_id, name, hostname, engine from services where user_id = %d order by created;", $this->user_id), true);
        if (!count($rows)) {
            return false;
        }

        foreach ($rows as $k => $v) {
            if ($k == 0)
                $this->page_info['first_site_id'] = $v['service_id'];
            $services[$v['service_id']]['service_name'] = $this->get_service_visible_name($v);
        }

        if (count($services) > 1) {
            $this->page_info['services'] = $services;
        }

        $service = null;
        $sql_service = '';
        $is_delegated = false;
        if (isset($_GET['service_id']) && preg_match("/^\-?\d+$/", $_GET['service_id']) && $_GET['service_id'] != 0) {
            $this->service_id = $_GET['service_id'];
            // Проверяем является ли сайт делегированным
            if (in_array($this->service_id, $this->granted_services_ids))
                $is_delegated = true;
            if ($this->service_id != $default_service_id) {
                $sql_service = sprintf(" and service_id = %d", $this->service_id);
                // Показ аналитики для делегированных сайтов
                if ($is_delegated)
                    $service = $this->db->select(sprintf("select service_id, name, hostname, engine from services where 1 %s;",
                        $sql_service
                    ));
                else
                    $service = $this->db->select(sprintf("select service_id, name, hostname, engine from services where user_id = %d%s;",
                        $this->user_id,
                        $sql_service
                    ));
            }
        } else {
            if ($this->user_info['services'] == 1) {
                // Показ аналитики для делегированных сайтов
                if ($is_delegated)
                    $service = $this->db->select(sprintf("select service_id, name, hostname, engine from services where 1 %s;",
                        $sql_service
                    ));
                else
                    $service = $this->db->select(sprintf("select service_id, name, hostname, engine from services where user_id = %d%s;",
                        $this->user_id,
                        $sql_service
                    ));
            }
        }

        if (!isset($_GET['stat_type'])){
            $stat_type = 'antispam';
            $this->page_info['stat_type'] = 1;
        }
        else {
            switch (preg_replace('/[^0-9a-z]/i', '', $_GET['stat_type'])) {
                case '1' : {
                    $stat_type = 'antispam';
                    $this->page_info['stat_type'] = 1;
                    break;
                }
                case '2' : {
                    $stat_type = 'sfw';
                    $this->page_info['stat_type'] = 2;
                    break;
                }
                case '20ips' : {
                    $stat_type = 'top20';
                    $this->page_info['stat_type'] = '20ips';
                    break;
                }
                case '20emails' : {
                    $stat_type = 'top20';
                    $this->page_info['stat_type'] = '20emails';
                    break;
                }
                case '20countries' : {
                    $stat_type = 'top20';
                    $this->page_info['stat_type'] = '20countries';
                    break;
                }
                default : {
                    $stat_type = 'antispam';
                    $this->page_info['stat_type'] = 1;
                    break;
                }

            }
        }


        if ($stat_type == 'antispam') {
            $this->page_info['stat_title'] = sprintf($this->lang['l_stat_title'], '');
            if (isset($service['service_id'])) {
                $this->page_info['service_id'] = $service['service_id'];
                $this->page_info['service_name'] = $this->get_service_visible_name($service);
                $this->page_info['stat_title'] = sprintf($this->lang['l_stat_title'], $this->get_service_visible_name($service));
            }
        }
        else {

            $this->page_info['stat_title'] = sprintf($this->lang['l_sfw_title'], '');
            if (isset($service['service_id'])) {
                $this->page_info['service_id'] = $service['service_id'];
                $this->page_info['service_name'] = $this->get_service_visible_name($service);
                $this->page_info['stat_title'] = sprintf($this->lang['l_sfw_title'], $this->get_service_visible_name($service));
            }
        }

        // Обработка TOP20

        $this->page_info['top20'] = false;

        if ($stat_type == 'top20') {
            $stf = preg_replace('/[^0-9]/i', '', $_GET['start_from']);
            $endt = preg_replace('/[^0-9]/i', '', $_GET['end_to']);

            $start_from = date('Y-m-d 00:00:00',$stf);
            $end_to = date('Y-m-d 23:59:59',$endt);

            $this->page_info['start_from'] = date('M d, Y',strtotime($start_from));
            $this->page_info['end_to'] = date('M d, Y',strtotime($end_to));
            $this->page_info['top20'] = true;
            $sql_ss_id = '';
            $this->page_info['all_sites_top20'] = false;
            if (isset($_GET['service_id'])){
                $ssid = preg_replace('/[^0-9]/i', '', $_GET['service_id']);
                $sql_ss_id = ' and a.service_id = '.$ssid;
            }
            else
                $this->page_info['all_sites_top20'] = true;
            switch ($this->page_info['stat_type']) {
                case '20ips': {
                    $top20sql = sprintf("select inet_ntoa(a.sender_ip) as 20label, 
                                         count(*) as numresult, b.status, a.ip_country, c.%s as 20country
                                        from requests a join logs_countries c
                                        on a.ip_country = c.countrycode
                                        left join 
                                        (select distinct(record), status 
                                         from services_private_list spl
                                         join services ss
                                         on spl.service_id = ss.service_id
                                         where ss.user_id = %d) b 
                                        on inet_ntoa(a.sender_ip) = b.record
                                        where a.allow = 0 and a.user_id = %d %s
                                        group by a.sender_ip
                                        order by numresult desc
                                        limit 20",
                                        $this->ct_lang,
                                        $this->user_id,
                                        $this->user_id,
                                        $sql_ss_id);
                    $this->page_info['head']['title'] = $this->page_info['stat_title'] = $this->lang['l_top20_ips'];
                    break;
                }
                case '20emails': {
                    $top20sql = sprintf("select sender_email as 20label, 
                                        count(*) as numresult, b.status
                                        from requests a
                                        left join 
                                        (select distinct(record), status 
                                         from services_private_list spl
                                         join services ss
                                         on spl.service_id = ss.service_id
                                         where ss.user_id = %d
                                         ) b 
                                        on a.sender_email = b.record
                                        where a.allow = 0 and a.sender_email is not null
                                        and a.user_id = %d %s
                                        group by a.sender_email
                                        order by numresult desc
                                        limit 20",
                                        $this->user_id,
                                        $this->user_id,
                                        $sql_ss_id);
                    $this->page_info['head']['title'] = $this->page_info['stat_title'] = $this->lang['l_top20_emails'];
                    break;
                }
                case '20countries': {
                    // Вытаскиваем названия стран из таблицы logs_countries
                    $top20sql = sprintf("select a.ip_country, 
                                        b.%s as 20label,    
                                        count(*) as numresult,
                                        c.status
                                        from requests a join logs_countries b
                                        on a.ip_country = b.countrycode 
                                        left join 
                                        (select distinct(REPLACE(record, 'country_', '')) as reprecord, 
                                         status 
                                         from services_private_list spl
                                         join services ss
                                         on spl.service_id = ss.service_id
                                         where ss.user_id = %d) c
                                        on a.ip_country = c.reprecord 
                                        where a.allow = 0 and a.user_id = %d %s
                                        group by a.ip_country
                                        order by numresult desc
                                        limit 20",
                                        $this->ct_lang,
                                        $this->user_id,
                                        $this->user_id,
                                        $sql_ss_id);
                    $this->page_info['head']['title'] = $this->page_info['stat_title'] = $this->lang['l_top20_countries'];
                    break;
                }


            }

            $restop20 = $this->db->select($top20sql, true);
            $this->page_info['top20recs'] = array_reverse($restop20);
            $this->page_info['top20asc'] = $restop20;
            $this->page_info['ta_content']= '';
            // Формируем список для textarea
            foreach($restop20 as $onert20)
                $this->page_info['ta_content'] .= $onert20['20label'].' ';
            // Добавляем ссылку на aaid
            if ($this->page_info['is_auth']) {
                $this->page_info['share_aa_results'] = true;

                $aaid = dechex(crc32(cfg::aaid_key . ':' . $this->user_id . ':' . $this->service_id));
                $sql = sprintf("select service_id, user_id from aa_keys where aaid = %s;", $this->stringToDB($aaid));
                $row = $this->db->select($sql);

                if (!isset($row['user_id'])) {
                    $this->db->run(sprintf("insert aa_keys (aaid, user_id, service_id, submitted) values (%s, %d, %d, now())",
                        $this->stringToDB($aaid),
                        $this->user_id,
                        $this->service_id
                    ));
                }

                $this->page_info['aaid'] = $aaid;

                $this->page_info['share_url'] = urlencode(sprintf('https://%s/my/stat?aaid=%s', $_SERVER['HTTP_HOST'], $aaid));
                $this->page_info['share_title'] = urlencode($this->lang['l_share_title']);
            }

            $this->display();
            exit();

        }


        $this->page_info['head']['title'] = $this->page_info['stat_title'];

        if ($stat_type == 'antispam')
            $stat_label = sprintf('stat_service_id_%d_%d_%s', $service['service_id'], $this->user_id, $this->ct_lang);
        else
            $stat_label = sprintf('sfw_service_id_%d_%d_%s', $service['service_id'], $this->user_id, $this->ct_lang);
        $stat = $this->mc->get($stat_label);
        $stat = false;
        $show_by_months = false;
        if (!$stat) {

            // Обработка custom dates и периодов (7 30 365)
            if (isset($_GET['start_from']) || isset($_GET['end_to']) || isset($_GET['int'])){
                if (isset($_GET['service_id']))
                    $service_id = (int) $_GET['service_id'];
                else
                    $service_id = 0;

                // Обработка custom dates

                if ((isset($_GET['start_from']) || isset($_GET['end_to'])) && !isset($_GET['int'])){

                    $stf = preg_replace('/[^0-9]/i', '', $_GET['start_from']);
                    $endt = preg_replace('/[^0-9]/i', '', $_GET['end_to']);

                    $days_between = floor(($endt - $stf)/(24*60*60));

                    $start_from = date('Y-m-d 00:00:00',$stf);
                    $end_to = date('Y-m-d 23:59:59',$endt);

                    $this->page_info['start_from'] = date('M d, Y',strtotime($start_from));
                    $this->page_info['end_to'] = date('M d, Y',strtotime($end_to));
                    $this->page_info['show_custom'] = true;

                    if ($service_id != 0){
                        if ($days_between > 31){
                            if ($stat_type == 'antispam') {
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(allow) as allow, sum(count) as count
                                                from requests_stat_services
                                                where %s and service_id = %d
                                                and date between '%s' and '%s'
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                $end_to
                                                );
                            } else
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(num_allow) as allow, sum(num_total) as count 
                                                from sfw_logs_stat 
                                                where %s and service_id = %d
                                                and date between '%s' and '%s'
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                $end_to
                                                );
                            $show_by_months = true;
                            $prev_year = (int) date('Y',$stf);
                            $prev_week = (int) date('W',$stf);
                            $now_year = (int) date('Y',$endt);
                            $now_week = (int) date('W',$endt);

                        }
                        else {
                            if ($stat_type == 'antispam')
                                $sql = sprintf("select date, allow, count 
                                                from requests_stat_services 
                                                where %s and service_id = %d
                                                and date between '%s' and '%s';",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                $end_to
                                                );
                            else
                                $sql = sprintf("select date, 
                                                sum(num_allow) as allow, sum(num_total) as count 
                                                from sfw_logs_stat
                                                where %s and service_id = %d
                                                and date between '%s' and '%s'
                                                group by date
                                                order by date asc;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                $end_to
                                                );

                        }
                    }
                    else {
                        if ($days_between >= 31){
                            if ($stat_type == 'antispam') {
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(allow) as allow, sum(count) as count 
                                                from requests_stat_services 
                                                where %s
                                                and date between '%s' and '%s'
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                    ($is_delegated ? '1' : 'user_id = ' . $this->user_id),
                                    $start_from,
                                    $end_to
                                );
                            } else
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(num_allow) as allow, sum(num_total) as count 
                                                from sfw_logs_stat
                                                where %s 
                                                and date between '%s' and '%s'
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $start_from,
                                                $end_to
                                                );
                            $show_by_months = true;
                            $prev_year = (int) date('Y',$stf);
                            $prev_week = (int) date('W',$stf);
                            $now_year = (int) date('Y',$endt);
                            $now_week = (int) date('W',$endt);

                        }
                        else
                            if ($stat_type == 'antispam')
                                $sql = sprintf("select date, sum(allow) as allow, sum(count) as count 
                                                from requests_stat_services where %s
                                                and date between '%s' and '%s'
                                                group by date;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $start_from,
                                                $end_to
                                                );
                            else
                                $sql = sprintf("select date, 
                                                sum(num_allow) as allow, sum(num_total) as count  
                                                from sfw_logs_stat
                                                where %s
                                                and date between '%s' and '%s'
                                                group by date
                                                order by date asc;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $start_from,
                                                $end_to
                                                );
                    }

                }

                // Обработка периодов
                $int = 0;
                if (isset($_GET['int'])) {
                    $int = preg_replace('/[^0-9]/i', '', $_GET['int']);
                    $this->page_info['int'] = $int;
                    $start_from = date('Y-m-d',time() - $int*86400);
                    $this->page_info['start_from'] = date('M d, Y',strtotime($start_from));
                    $this->page_info['end_to'] = date('M d, Y',time());
                    if ($service_id != 0) {
                        if ($int != 365) {
                            if ($stat_type == 'antispam')
                                $sql = sprintf("select date, allow, count 
                                                from requests_stat_services 
                                                where %s and service_id = %d
                                                and date between '%s' and %s;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                'now()'
                                                );
                            else
                                $sql = sprintf("select date,
                                                sum(num_allow) as allow, sum(num_total) as count
                                                from sfw_logs_stat 
                                                where %s and service_id = %d
                                                and date between '%s' and %s
                                                group by date
                                                order by date asc;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                'now()'
                                                );
                            $stf = strtotime(date('Y-m-d')) - 24*60*60*$int;
                            $endt = strtotime(date('Y-m-d'));
                            $days_between = floor(($endt-$stf)/(24*60*60));
                        }
                        else {
                            if ($stat_type == 'antispam') {
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth, 
                                                sum(allow) as allow, sum(count) as count 
                                                from requests_stat_services 
                                                where %s and service_id = %d
                                                and date between '%s' and %s
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                    ($is_delegated ? '1' : 'user_id = ' . $this->user_id),
                                    $service_id,
                                    $start_from,
                                    'now()'
                                );
                            } else
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(num_allow) as allow, sum(num_total) as count 
                                                from sfw_logs_stat
                                                where %s and service_id = %d
                                                and date between '%s' and %s
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date)) 
                                                ORDER BY date ASC;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $service_id,
                                                $start_from,
                                                'now()'
                                                );
                            $show_by_months = true;
                            $prev_year = (int) date('Y',time() - 365*24*60*60);
                            $prev_week = (int) date('W',time() - 365*24*60*60);
                            $now_year = (int) date('Y');
                            $now_week = (int) date('W');
                        }
                    }
                    else {
                        if ($int!=365){
                            if ($stat_type == 'antispam')
                                $sql = sprintf("select date, sum(allow) as allow, sum(count) as count 
                                                from requests_stat_services 
                                                where %s 
                                                and date between '%s' and %s
                                                group by date;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $start_from,
                                                'now()'
                                                );
                            else
                                $sql = sprintf("select date, 
                                                sum(num_allow) as allow, sum(num_total) as count 
                                                from sfw_logs_stat
                                                where %s
                                                and date between '%s' and %s
                                                GROUP BY date
                                                ORDER BY date ASC;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $start_from,
                                                'now()'
                                                );

                            $stf = strtotime(date('Y-m-d')) - 24*60*60*$int;
                            $endt = strtotime(date('Y-m-d'));
                            $days_between = floor(($endt-$stf)/(24*60*60));
                        }
                        else {
                            if ($stat_type == 'antispam') {
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(allow) as allow, sum(count) as count 
                                                from requests_stat_services 
                                                where %s
                                                and date between '%s' and %s
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                    ($is_delegated ? '1' : 'user_id = ' . $this->user_id),
                                    $start_from,
                                    'now()'
                                );
                            } else
                                $sql = sprintf("select DATE_FORMAT(date, '%%Y.%%m') AS yearmonth,
                                                sum(num_allow) as allow, sum(num_total) as count 
                                                from sfw_logs_stat 
                                                where %s
                                                and date between '%s' and %s
                                                GROUP BY CONCAT(YEAR(date), '.', MONTH(date))
                                                ORDER BY date ASC;",
                                                ($is_delegated ? '1': 'user_id = '.$this->user_id),
                                                $start_from,
                                                'now()'
                                                );
                            $show_by_months = true;
                            $prev_year = (int) date('Y',time() - 365*24*60*60);
                            $prev_week = (int) date('W',time() - 365*24*60*60);
                            $now_year = (int) date('Y');
                            $now_week = (int) date('W');
                        }
                    }
                }

                $stat = $this->db->select($sql, true);

                if ($stat){

                    $chart = null;

                    $points = array(
                        'spam' => array (
                            'alltime' => 0
                        ),
                        'allow' => array (
                            'alltime' => 0
                        )
                    );

                    $spam_total = 0;

                    if ($show_by_months){

                        /* Разбивка по неделям*/
                        /*$yearweek = array();
                        if($prev_year != $now_year){
                        for($i=$prev_week;$i<=52;$i++)
                            $yearweek[] = $prev_year.'/'.$i;
                        for($i=1;$i<=$now_week;$i++)
                            $yearweek[] = $now_year.'/'.$i;
                        }
                        else{
                            for($i=$prev_week;$i<=$now_week;$i++)
                                $yearweek[] = $now_year.'/'.$i;
                        }

                        foreach($yearweek as $oneyearweek){
                            $ywexpl = explode('/',$oneyearweek);
                            $datetoshow = date('d.m.Y',strtotime($ywexpl[0].'-01-01') + 24*60*60*7*$ywexpl[1]);
                            $chart[$datetoshow]['spam'] = 0;
                            $chart[$datetoshow]['allow'] = 0;
                            foreach($stat as $onestat){
                                if ($onestat['yearweek'] == $oneyearweek){
                                    $spam = $onestat['count'] - $onestat['allow'];
                                    $chart[$datetoshow]['spam'] = $spam;
                                    $chart[$datetoshow]['allow'] = $onestat['allow'];
                                    $points['spam']['alltime'] = $points['spam']['alltime'] + $spam;
                                    $points['allow']['alltime'] = $points['allow']['alltime'] + $onestat['allow'];
                                    $spam_total = $spam_total + $spam;
                                }

                            }
                        } */

                        /* Разбивка по месяцам */

                        if ($int == 365 || isset($_GET['start_from']) || isset($_GET['end_to'])){
                            if ((isset($_GET['start_from']) || isset($_GET['end_to'])) && $int != 365){
                                $chart = null;
                                $number_months = floor(($endt - $stf)/(30*60*60*24));
                                for($i=$number_months;$i>=1;$i--){
                                    $datetoshow = date('Y.m',strtotime("-".$i."month",$endt));
                                    $chart[$datetoshow]['spam'] = 0;
                                    $chart[$datetoshow]['allow'] = 0;
                                }
                            }
                            elseif ($int == 365){
                                $chart = null;
                                for($i=12;$i>=1;$i--){
                                    $datetoshow = date('Y.m',strtotime("-".$i."month",time()));
                                    $chart[$datetoshow]['spam'] = 0;
                                    $chart[$datetoshow]['allow'] = 0;
                                }
                            }

                        }


                        foreach($stat as $onestat){
                            $datetoshow = $onestat['yearmonth'];
                            $chart[$datetoshow]['spam'] = 0;
                            $chart[$datetoshow]['allow'] = 0;
                            $spam = $onestat['count'] - $onestat['allow'];
                            $chart[$datetoshow]['spam'] = $spam;
                            $chart[$datetoshow]['allow'] = $onestat['allow'];
                            $points['spam']['alltime'] = $points['spam']['alltime'] + $spam;
                            $points['allow']['alltime'] = $points['allow']['alltime'] + $onestat['allow'];
                            $spam_total = $spam_total + $spam;
                        }

                    }
                    else {
                        for($i=0;$i<=$days_between;$i++){
                            $currentdate = date('d.m.Y',$stf + 24*60*60*$i);
                            $chart[$currentdate]['spam'] = 0;
                            $chart[$currentdate]['allow'] = 0;
                            foreach($stat as $onestat){
                                $datetoshow = date('d.m.Y',strtotime($onestat['date']));
                                if ($datetoshow == $currentdate){
                                    $spam = $onestat['count'] - $onestat['allow'];
                                    $chart[$datetoshow]['spam'] = $spam;
                                    $chart[$datetoshow]['allow'] = $onestat['allow'];
                                    $points['spam']['alltime'] = $points['spam']['alltime'] + $spam;
                                    $points['allow']['alltime'] = $points['allow']['alltime'] + $onestat['allow'];
                                    $spam_total = $spam_total + $spam;
                                    break;
                                }

                            }
                        }
                    }

                    foreach ($points as $t => $v) {
                        foreach ($v as $t2 => $p) {
                            $points[$t][$this->lang['stat_ints'][$t2]] = number_format($p, 0, ',', ' ');
                            unset($points[$t][$t2]);
                        }
                    }
                }
                else {
                    if ((isset($_GET['start_from']) || isset($_GET['end_to'])) && !isset($_GET['int'])) {
                        $stf = strtotime($start_from);
                        $endt = strtotime($end_to);
                        $days_between = floor(($endt-$stf)/(24*60*60));
                        $months_between = floor($days_between/30);
                        for($i=0;$i<=$months_between;$i++){
                            $datetoshow = date('d.m.Y',strtotime("+".$i." month",$stf));
                            $chart[$datetoshow]['spam'] = 0;
                            $chart[$datetoshow]['allow'] = 0;
                        }
                    }

                    if (isset($_GET['int'])){
                        $stf = strtotime(date('Y-m-d')) - 24*60*60*$int;
                        $endt = strtotime(date('Y-m-d'));
                        $days_between = floor(($endt-$stf)/(24*60*60));
                        for($i=0;$i<=$days_between;$i++){
                            $datetoshow = date('d.m.Y',$stf + 24*60*60*$i);
                            $chart[$datetoshow]['spam'] = 0;
                            $chart[$datetoshow]['allow'] = 0;
                        }
                    }

                    $points['spam']['alltime'] = 0;
                    $points['allow']['alltime'] = 0;
                    $spam_total = 0;

                }

                $stat['points'] = $points;
                $stat['chart'] = $chart;
                $stat['spam_total'] = $spam_total;

            }
            else {

                $this->page_info['start_from'] = date('M d, Y',time() - 365*24*60*60);
                $this->page_info['end_to'] = date('M d, Y',time());
                if ($stat_type == 'antispam')
                    $sql = sprintf("select unix_timestamp(date) as date_ts, allow, count from requests_stat_services where user_id = %d%s;",
                        $this->user_id,
                        $sql_service
                    );
                else
                    $sql = sprintf("select unix_timestamp(date_format(datetime,'%%Y-%%m-%%d')) as date_ts, 
                                    sum(num_allow) as allow, sum(num_total) as count 
                                    from sfw_logs 
                                    where user_id = %d%s
                                    group by date_format(datetime,'%%Y-%%m-%%d')
                                    order by date_format(datetime,'%%Y-%%m-%%d');",
                        $this->user_id,
                        $sql_service
                    );

                $stat = $this->db->select($sql, true);

                $week_start_ts = time() - 86400 * 7;
                $month_start_ts = time() - 86400 * 31;

                $points = array(
                    'spam' => array (
                        'week' => 0,
                        'month' => 0,
                        'alltime' => 0
                    ),
                    'allow' => array (
                        'week' => 0,
                        'month' => 0,
                        'alltime' => 0
                    )
                );

                //
                // Cоздаем пустой график с интервалом года назад
                //
                $chart = null;

                $period_intervals = cfg::default_stat_period_intervals;
                $period = cfg::default_stat_period;

                for ($i = $period_intervals; $i >= 0; $i--) {
		            $days_shift = 0;
                    $month = date("Y", strtotime("-$i $period")) . '-' . $this->get_month_name(strtotime("-$i $period -$days_shift day"));
                    $chart[$month]['spam'] = 0;
                    $chart[$month]['allow'] = 0;
                }
                $spam_total = 0;
                foreach ($stat as $date) {
                    $spam = $date['count'] - $date['allow'];
                    if ($date['date_ts'] >= $week_start_ts) {
                        $points['spam']['week'] = $points['spam']['week'] + $spam;
                        $points['allow']['week'] = $points['allow']['week'] + $date['allow'];
                    }
                    if ($date['date_ts'] >= $month_start_ts) {
                        $points['spam']['month'] = $points['spam']['month'] + $spam;
                        $points['allow']['month'] = $points['allow']['month'] + $date['allow'];
                    }
                    $points['spam']['alltime'] = $points['spam']['alltime'] + $spam;
                    $points['allow']['alltime'] = $points['allow']['alltime'] + $date['allow'];
                    $spam_total = $spam_total + $spam;

                    //
                    // Строим график только для последних 12 месяцев
                    //
                    if ($date['date_ts'] < strtotime("-12 month")) {
                        continue;
                    }
                    $month = date("Y", $date['date_ts']) . '-' . $this->get_month_name($date['date_ts']);

                    if (isset($chart[$month]['spam'])) {
                        $chart[$month]['spam'] = $chart[$month]['spam'] + $spam;
                    }
                    else {
                        $chart[$month]['spam'] = $spam;
                }
                if (isset($chart[$month]['allow'])) {
                    $chart[$month]['allow'] = $chart[$month]['allow'] + $date['allow'];
                }
                else {
                    $chart[$month]['allow'] = $date['allow'];
                }
            }

            foreach ($points as $t => $v) {
                foreach ($v as $t2 => $p) {
                    $points[$t][$this->lang['stat_ints'][$t2]] = number_format($p, 0, ',', ' ');
                    unset($points[$t][$t2]);
                }
            }

            $stat['points'] = $points;
            $stat['chart'] = $chart;
            $stat['spam_total'] = $spam_total;

            if ($stat_type == 'antispam')
                $stat_label = sprintf('stat_service_id_%d_%d_%s', $service['service_id'], $this->user_id, $this->ct_lang);
            else
                $stat_label = sprintf('sfw_service_id_%d_%d_%s', $service['service_id'], $this->user_id, $this->ct_lang);


            $this->mc->set($stat_label, $stat, null, $this->options['service_cache_timeout']);
            }
        }

        $this->page_info['points'] = $stat['points'];
        $this->page_info['chart'] = json_encode($stat['chart']);

        $share_desc = '';
        if ($this->page_info['is_auth']) {
            $this->page_info['share_aa_results'] = true;

            $aaid = dechex(crc32(cfg::aaid_key . ':' . $this->user_id . ':' . $this->service_id));
            $sql = sprintf("select service_id, user_id from aa_keys where aaid = %s;", $this->stringToDB($aaid));
            $row = $this->db->select($sql);

            if (!isset($row['user_id'])) {
                $this->db->run(sprintf("insert aa_keys (aaid, user_id, service_id, submitted) values (%s, %d, %d, now())",
                    $this->stringToDB($aaid),
                    $this->user_id,
                    $this->service_id
                ));
            }

            $this->page_info['aaid'] = $aaid;

            $this->page_info['share_url'] = urlencode(sprintf('https://%s/my/stat?aaid=%s', $_SERVER['HTTP_HOST'], $aaid));
            $this->page_info['share_title'] = urlencode($this->lang['l_share_title']);

        }

        if ($stat['spam_total']) {
            $share_desc = sprintf($this->lang['l_share_desc'], number_format($stat['spam_total'], 0, ',', ' '));
            $this->page_info['head']['meta_description'] = $share_desc;
            $this->page_info['share_desc'] = urlencode($share_desc);
            $this->page_info['head']['title'] = $share_desc;
        }

        return null;
    }

    function show_requests_new() {
        if (!$this->logs) $this->logs = new Logs($this->db, array('user_id' => $this->user_id, 'timezone' => 0));
        $antispam = $this->logs->antispam();

        // Страны для фильтрации по стране
        $logs_countries = apc_fetch('logs_countries_'.$this->ct_lang);
        if (!$logs_countries && isset($this->ct_langs[$this->ct_lang])){
            $logs_countries = $this->db->select(
                sprintf("SELECT countrycode, %s AS langname FROM logs_countries ORDER BY langname ASC", preg_replace('/[^a-zA-Z]/i', '', $this->ct_lang)), true
            );
            apc_store('logs_countries_' . $this->ct_lang, $logs_countries, 25000);
        }
        $this->page_info['logs_countries'] = $logs_countries;

        if (isset($_GET['country'])) $antispam->country = $_GET['country'];
        if (isset($_REQUEST['start_from'])) $antispam->from = (int) $_REQUEST['start_from'];
        if (isset($_REQUEST['end_to'])) $antispam->to = (int) $_REQUEST['end_to'];
        if (isset($_GET['int'])) $antispam->interval = $_GET['int'];
        if (isset($_REQUEST['allow'])) $antispam->allow = $_REQUEST['allow'];
        if (isset($_GET['type'])) $antispam->type = $_GET['type'];

        // Признак является ли предложенный сайт делегированным именно этому пользователю
        $is_granted_service = false;
        $this->page_info['grantwrite'] = 1;
        if (isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
            $service_id = $_REQUEST['service_id'];
            if (in_array($service_id, $this->granted_services_ids)) {
                $is_granted_service = true;
                // Определяем уровень доступа для сайта - чтение или запись
                // функция grant_access_level в ccs.php
                $this->page_info['grantwrite'] = $this->grant_access_level($service_id);
            }
            $antispam->service_id = $service_id;
            $this->notify_service_id = $service_id;
            $this->service_id = $service_id;
        }
        $confirm_deletion_bulk = sprintf($this->lang['l_confirm_deletion_bulk']);
        $this->page_info['confirm_deletion_bulk'] = $confirm_deletion_bulk;

        // Удаляем записи из журнала.
        if (isset($_GET['delete_records']) && $_GET['delete_records'] == 1) {
            $sql_service_id = '';
            if (isset($_GET['service_id']) && preg_match("/^\d+$/", $_GET['service_id'])) {
                $service_id = $_GET['service_id'];
                $sql_service_id = ' and service_id = ' . $service_id;
            }
            $sql = sprintf("select count(*) as count from requests r where user_id = %d and allow = 1%s;",
                $this->user_id,
                $sql_service_id
            );
            $row = $this->db->select($sql);

            if ($row['count'] > 0) {
                $sql = sprintf("delete from requests where user_id = %d and allow = 1%s;",
                    $this->user_id,
                    $sql_service_id
                );
                $this->db->run($sql);
                $this->post_log(sprintf("Пользователь %s (%d) удалил %d одобренных запросов из антиспам журнала.",
                    $this->user_info['email'],
                    $this->user_id,
                    $row['count']
                ));

                $return_url = '/my';
                if (isset($_GET['return_url'])) {
                    $return_url_tmp = urldecode($_GET['return_url']);
                    $tools = new CleanTalkTools();
                    if ($tools->get_domain($return_url_tmp) == $_SERVER['HTTP_HOST']) {
                        $return_url = $return_url_tmp;
                    }
                }
                header("Location:" . $return_url);
                exit;
            }
        }

        if (isset($_GET['request_id'])) $antispam->request_id = $_GET['request_id'];

        $sql_ipemailnick = "";
        $recs_ipemailnick = "";
        $search_by_url = false;
        $email_url = '';
        $search_by_rootdomain = false;
        $rootdomain = '';

        if (isset($_GET['ipemailnick'])) {
            $ipemailnick = htmlspecialchars(strip_tags(trim($_GET['ipemailnick'])));
            $this->page_info['ipemailnick'] = $ipemailnick;
            $antispam->ipemailnick = $ipemailnick;
        }

        if ($this->page != 0) {
            $limit1 = ($this->page - 1) * $this->requests_limit;
            $limit2 = $this->requests_limit;
        } else {
            $this->numrecs = $antispam->logs_count();
            $limit1 = 0;
            $limit2 = $this->requests_limit;
        }

        // TODO continue antispam

        $row = $this->db->select($sql, true);

        $collectors_ids_init = array();
        $collectors_ids = array();
        // определяем список id коллекторов для дальнейших запросов
        foreach ($row as $onerow)
            $collectors_ids_init[] = $onerow['collector_id'];
        $collectors_ids = array_unique($collectors_ids_init);

        $details_query = array();
        foreach ($row as $k => $v){

            $row[$k]['type'] = '';

            // Дубль типа сообщения нужен для совместимости со старым кодом
            if ($v['request_type'] == 'registration') {
                $row[$k]['type'] = 'newuser';
            }
            if ($v['request_type'] == 'comment') {
                $row[$k]['type'] = 'message';
            }

            if ($v['request_type'] == 'contact_enquire') {
                $row[$k]['type'] = 'contact';
            }

            foreach($collectors_ids as $onecollectorid) {
                if ($onecollectorid == $v['collector_id']) {
                    if (!isset($details_query[$onecollectorid])) {
                        $details_query[$onecollectorid] = '';
                    }
                    if ($details_query[$onecollectorid] != '')
                        $details_query[$onecollectorid] .= ',';
                    if ($is_granted_service)
                        $details_query[$onecollectorid] .= sprintf("%d:%s:%s", $v['user_id'], $v['request_id'], $v['datetime_db']);
                    else
                        $details_query[$onecollectorid] .= sprintf("%d:%s:%s", $this->user_id, $v['request_id'], $v['datetime_db']);
                }
            }

        }

        $ctTools = new CleanTalkTools();
        $remote_details = array();
        foreach($details_query as $k => $onedetailquery) {
            if ($onedetailquery != '' && cfg::enable_remote_details) {
                $remote_details[$k] = $ctTools->get_remote_details(
                    str_replace('collector', 'collector'.$k, $this->options['remote_details_url']),
                    $onedetailquery,
                    'unmasked,response,message,message_decoded,sender,comment_server'
                );
            }
        }

        $requests = &$row;
        $approved_requests = 0;
        foreach ($requests as $k => $v){

            // Поиск по домену второго уровня сразу же
            if ($search_by_url){
                $emailexpl = explode('@', trim($requests[$k]['sender_email']));
                if ($emailexpl[1] != $email_url) {
                    unset($requests[$k]);
                    continue;
                }
            }

            if ($search_by_rootdomain) {
                if (end(explode('.', trim($requests[$k]['sender_email']))) != $rootdomain) {
                    unset($requests[$k]);
                    continue;
                }

            }

            switch($requests[$k]['type']){
                case 'newuser':
                    $requests[$k]['type'] = $this->lang['r_type_newuser'];
                    break;
                case 'message':
                    $requests[$k]['type'] = $this->lang['r_type_message'];
                    break;
                case 'contact':
                    $requests[$k]['type'] = $this->lang['r_type_contact'];
                    break;
                case 'order':
                    $requests[$k]['type'] = $this->lang['r_type_order'];
                    break;
                default:
                    $requests[$k]['type'] = $this->lang['r_type_unknown'];
            }

            if ($requests[$k]['moderate'] == 1) {
                $requests[$k]['short_result'] = sprintf("%s %s",
                    $requests[$k]['type'],
                    mb_strtolower($requests[$k]['allow'] == 1 ? $this->lang['l_approved'] : $this->lang['l_denied'])

                );
            } else {
                $requests[$k]['short_result'] = $this->lang['l_service_disabled'];
            }
            if (isset($requests[$k]['sender_ip']) && $requests[$k]['sender_ip'] != '-')
                $requests[$k]['country'] = @geoip_country_code_by_name($requests[$k]['sender_ip']);

            if (isset($remote_details[$v['collector_id']][$v['request_id']])) {
                $data = $remote_details[$v['collector_id']][$v['request_id']];
            } else {
                $data = $ctTools->getRequestData($requests[$k]['user_id'], $requests[$k]['request_id'], strtotime($v['datetime_db']));
            }
            $r_message = null;
            $message = null;

            // Используем декодированную версию сообщения
            if (isset($data['message_decoded']) && $data['message_decoded'] != null) {
                $r_message = $data['message_decoded'];
            }

            // Используем декодированную версию сообщения
            if (isset($data['unmasked']) && $data['unmasked'] != null) {
                $r_message = $data['unmasked'];
            }

            // Используем оригинальную версию сообщения
            if (!$r_message && isset($data['message']) && $data['message'] != null && $data['message'] != '') {
                $r_message = $data['message'];
            }

            //
            // Используем JSON кодированную версию сообщения, если таковая имеется.
            //
            $message_array = isset($data['message']) ? json_decode($data['message'], true) : null;

            if ($this->app_mode) {
                if ($r_message)
                    $message = substr($r_message, 0, $this->options['app_message_max_size']);

                unset($requests[$k]['method_name']);
                unset($requests[$k]['user_id']);
                unset($requests[$k]['sender_ip']);
                unset($requests[$k]['country']);
                unset($requests[$k]['post_url']);
            } else {
                $requests[$k]['sender_info'] = '';

                $r_message = urldecode($r_message);

                // Для турецкого языка фикс

                $r_message = str_replace("%u015F","s",$r_message);
                $r_message = str_replace("%E7","c",$r_message);
                $r_message = str_replace("%FC","u",$r_message);
                $r_message = str_replace("%u0131","i",$r_message);
                $r_message = str_replace("%F6","o",$r_message);
                $r_message = str_replace("%u015E","S",$r_message);
                $r_message = str_replace("%C7","C",$r_message);
                $r_message = str_replace("%DC","U",$r_message);
                $r_message = str_replace("%D6","O",$r_message);
                $r_message = str_replace("%u0130","I",$r_message);
                $r_message = str_replace("%u011F","g",$r_message);
                $r_message = str_replace("%u011E","G",$r_message);

                $message = strip_tags($r_message, '<p>');
                // Обрезаем длинные блоки символов, дабы не ломалась HTML верстка на странице.
#                $message = wordwrap($message, $this->max_word_size, "<br />", true);

                $message_original = $message;
                if ($this->max_message_size > 0) {
                    $message = mb_substr($message, 0, $this->max_message_size);
                }
                if ($message != $message_original) {
                    $message .= '...';
                }
                $message = nl2br($message);

                // Показываем кнопку "Сообщить о спаме"
                if (isset($requests[$k]['approved'])) {
                    $requests[$k]['show_report_spam'] = (bool) $requests[$k]['approved'];
                    $requests[$k]['show_report_not_spam'] = (bool) !$requests[$k]['approved'];
                } else {
                    // Показываем кнопку "Сообщить о спаме"
                    if ($requests[$k]['allow'] == 1)
                    {
                        $requests[$k]['show_report_spam'] = true;
                        $requests[$k]['show_report_not_spam'] = false;
                    }

                    // Показываем кнопку "Это не спам"
                    if ($requests[$k]['allow'] == 0)
                    {
                        $requests[$k]['show_report_not_spam'] = true;
                        $requests[$k]['show_report_spam'] = false;
                    }
                }

                if (isset($requests[$k]['approved'])){
                    $requests[$k]['feedback_result_message'] = $this->lang['l_feedback_result_message_' . $requests[$k]['approved']];
                }
                else
                    $requests[$k]['feedback_result_message'] = '';

                /*
                    Выводим ссылку на страницу с формой.
                */
                $requests[$k]['page_url'] = $requests[$k]['post_url'];
                if (!$requests[$k]['page_url'] && isset($data['sender']['REFFERRER'])) {
                    $requests[$k]['page_url'] = $data['sender']['REFFERRER'];
                }
                /*
                                if (isset($data['sender']['page_url'])) {
                                    if (!preg_match("/^http/", $data['sender']['page_url'])) {
                                        $data['sender']['page_url'] = 'http://' . $data['sender']['page_url'];
                                    }
                                    $requests[$k]['page_url'] = $data['sender']['page_url'];
                                }
                */
                if ($requests[$k]['hostname'] && $requests[$k]['page_url']) {

                    //
                    // Присваеваем page_url только при условии совпадения hostname со значением в базе, дабы мотивировать пользователей
                    // использовать отдельные ключи доступа для каждого вебсайта.
                    //
                    if (preg_match("@^(?:https?://)([^/:]+)(.*)$@i", $requests[$k]['page_url'], $matches)) {
                        $requests[$k]['page_url'] = sprintf("http://%s%s",
                            $requests[$k]['hostname'],
                            $matches[2]
                        );
                    }
                } else {
                    $requests[$k]['page_url'] = null;
                }

                $requests[$k]['sender_email_display'] = wordwrap($requests[$k]['sender_email'], $this->max_sender_size, "<br />", true);
//                $requests[$k]['page_url_display'] = wordwrap($requests[$k]['page_url'], $this->max_sender_size, "<br />", true);
//                $requests[$k]['page_url_display'] = $requests[$k]['page_url'];

                if ($this->max_url_size > 0) {
                    $requests[$k]['page_url_display'] = mb_substr($requests[$k]['page_url'], 0, $this->max_url_size);
                }
                if ($requests[$k]['page_url_display'] != $requests[$k]['page_url']) {
                    $requests[$k]['page_url_display'] .= '...';
                }

                $requests[$k]['datetime'] = date("M d Y H:i:s", strtotime($requests[$k]['datetime']));
            }
            $requests[$k]['message'] = $message;
            $requests[$k]['message_array'] = $message_array;
            $requests[$k]['comment'] = utf8_decode($data['response']['comment']);
            $requests[$k]['comment_server'] = $data['response']['comment_server'];
            if ($this->user_info['services'] > 1) {
                $requests[$k]['visible_hostname'] = $this->get_service_visible_name($requests[$k]);
            }
            if ($requests[$k]['allow'] == 1) {
                $approved_requests++;
            }

            if ($requests[$k]['sender_email'] == '******')
                $requests[$k]['show_approved'] = false;
            else
                $requests[$k]['show_approved'] = true;
            if (isset($data['sender']['REFFERRER']))
                $requests[$k]['referer'] = $data['sender']['REFFERRER'];

        }

        if ($this->app_mode && count($requests) && $service_id && $start_from) {
            $row_notify = $this->db->select(sprintf("select unix_timestamp(app_last_notify) as app_last_notify_ts from services where user_id = %d and service_id = %d;",
                $this->user_id, $service_id));

            if (!isset($row_notify['app_last_notify_ts']) || $row_notify['app_last_notify_ts'] < $start_from)
                $this->db->run(sprintf("update services set app_last_notify = now() where user_id = %d and service_id = %d;",
                    $this->user_id, $service_id));
        }

        // Тормозим дабы не задосить сервер
        if (count($requests))
            sleep($this->options['fail_timeout']);

        $this->page_info['date_range'] = $date_range;
        $this->page_info['date_range_begin'] = date("M d, Y", $date_range_begin);
        $this->page_info['date_range_end'] = date("M d, Y", $date_range_end);
        $this->page_info['approved_requests'] = $approved_requests;
        $this->page_info['thanks_notice'] = $this->lang['l_thanks_notice'];
        $this->page_info['notice_1'] = $this->lang['l_notice_1'];
        $this->page_info['notice_2'] = $this->lang['l_notice_2'];

        return $requests;
    }

    function info_requests() {
        if (isset($_REQUEST['start_from']) && preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $_REQUEST['start_from'])) {
            $_REQUEST['start_from'] = strtotime($_REQUEST['start_from']);
            $this->page_info['date_range_begin'] = date('M d, Y', $_REQUEST['start_from']);
        }
        if (isset($_REQUEST['end_to']) && preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/", $_REQUEST['end_to'])) {
            $_REQUEST['end_to'] = strtotime($_REQUEST['end_to']);
            $this->page_info['date_range_end'] = date('M d, Y', $_REQUEST['end_to']);
        }

        $period = null;
        $start_from = null;
        $end_to = null;

        $timezone = isset($this->user_info['timezone']) ? (int) $this->user_info['timezone'] : 0;
        $tz_ts = $this->user_info['tz_ts'];
        $midnight_seconds = $tz_ts - strtotime("today", $tz_ts);
        $this->user_info['midnight_seconds'] = &$midnight_seconds;

        // Страны для фильтрации по стране
        $logs_countries = apc_fetch('logs_countries_'.$this->ct_lang);
        if (!$logs_countries && isset($this->ct_langs[$this->ct_lang])){
            $logs_countries = $this->db->select(
                sprintf("SELECT countrycode, %s AS langname FROM logs_countries ORDER BY langname ASC", preg_replace('/[^a-zA-Z]/i', '', $this->ct_lang)), true
            );
            apc_store('logs_countries_' . $this->ct_lang, $logs_countries, 25000);
        }
        $this->page_info['logs_countries'] = $logs_countries;

        if (isset($_GET['country'])) {
            $country = preg_replace('/[^A-Z]/i', '', $_GET['country']);
            $this->page_info['sel_logcountry'] = $country;
        }

        // Периоды
        if (isset($_REQUEST['start_from']) && preg_match("/^\d+[\.0-9]*$/", $_REQUEST['start_from'])) {
            $start_from = sprintf("%d",$_REQUEST['start_from']);
        }

        if (isset($_REQUEST['end_to']) && preg_match("/^\d+$/", $_REQUEST['end_to'])) {
            $end_to = $_REQUEST['end_to'];
        }

        if (isset($_GET['ipemailnick'])) {
            if (!$start_from) $start_from = 0;
            if (!$end_to) $end_to = time();

            $ipemailnick = htmlspecialchars(strip_tags(trim($_GET['ipemailnick'])));
            $this->page_info['ipemailnick'] = $ipemailnick;
        }

        if (isset($_GET['int'])) {
            switch($_GET['int']){
                case 'today':
                    $period = 0;
                    break;
                case 'yesterday':
                    $period = 1;
                    break;
                case 'week':
                    $period = 7;
                    break;
                default:
                    if (is_int((int)$_GET['int']) && $_GET['int'] > 7) {
                        $start_from = (int) $_GET['int'];
                    } else {
                        $period = 0;
                    }
                    break;
            }
        }

        $allow = null;
        if (isset($_REQUEST['allow']) && ($_REQUEST['allow'] == 1 || $_REQUEST['allow'] == 0))
            $allow = $_REQUEST['allow'];


        // Признак является ли предложенный сайт делегированным именно этому пользователю
        $is_granted_service = false;
        $this->page_info['grantwrite'] = 1;
        if (isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
            $service_id = $_REQUEST['service_id'];
            if (in_array($service_id, $this->granted_services_ids)) {
                $is_granted_service = true;
                $this->page_info['grantwrite'] = $this->grant_access_level($service_id);
            }
        }

        $date_range_begin = time();
        $date_range_end = time();

        if ($start_from !== null) {
            $date_range_begin = $start_from;
        } else {
            $timezone = isset($this->user_info['timezone']) ? (int) $this->user_info['timezone'] : 0;
            $ts_start_day = strtotime("today") + ($timezone * 3600);
            $start_date_ts = $ts_start_day - (86400 * $period);
            $date_range_begin = $start_date_ts;
            if ($period == 1) {
                $date_range_end = $date_range_begin + 86400 - 1;
            }
        }

        $date_range = sprintf("%s - %s",
            date("M d, Y", $date_range_begin),
            date("M d, Y", $date_range_end)
        );

        $this->page_info['date_range'] = $date_range;
        if (!isset($this->page_info['date_range_begin'])) $this->page_info['date_range_begin'] = date("M d, Y", $date_range_begin);
        if (!isset($this->page_info['date_range_end'])) $this->page_info['date_range_end'] = date("M d, Y", $date_range_end);
    }

    function get_request($request_id) {
        $request = array();

        $row = $this->db->select(sprintf(
            "SELECT %s FROM requests r LEFT JOIN requests_feedback f ON f.request_id = r.request_id LEFT JOIN services s ON s.service_id = r.service_id WHERE r.request_id = %s",
            implode(', ', array(
                'r.request_id', 'r.allow', 'r.moderate', 'r.server_id', 'r.method_name',
                'r.service_id', 'r.user_id', 'r.datetime', 'r.post_url',
                'inet_ntoa(r.sender_ip) as sender_ip', 'r.sender_email', 'r.sender_nickname', 'r.sender_url',
                'r.ip_country', 'r.request_type', 'r.collector_id',
                'f.approved',
                's.hostname'
            )),
            $this->stringToDB($request_id)
        ));
        if ($row) {
            $ctTools = new CleanTalkTools();

            $is_granted_service = false;
            $details_query = '';
            if (in_array($row['service_id'], $this->granted_services_ids)) {
                $is_granted_service = true;
                $details_query = sprintf('%d:%s:%s', $row['user_id'], $row['request_id'], $row['datetime']);
            } else {
                $details_query = sprintf('%d:%s:%s', $this->user_id, $row['request_id'], $row['datetime']);
            }

            $remote_details = array();
            if (cfg::enable_remote_details) {
                $remote_details = $ctTools->get_remote_details(
                    str_replace('collector', 'collector' . $row['collector_id'], $this->options['remote_details_url']),
                    $details_query,
                    'unmasked,response,message,message_decoded,sender,comment_server'
                );
            }

            // Установки временной зоны пользователя
            $tz = (isset($this->user_info['timezone']) ? (int)$this->user_info['timezone'] : 0) - 5;
            $tz_ts = $tz * 3600;
            $request['datetime'] = date('M d, Y H:i:s', strtotime($row['datetime']) + $tz_ts);


            $request['id'] = $row['request_id'];
            $request['allow'] = (bool)$row['allow'];
            $request['moderate'] = (bool)$row['moderate'];
            $request['nickname'] = $row['sender_nickname'];
            $request['email'] = $row['sender_email'];
            $request['ip'] = $row['sender_ip'];
            $request['country'] = $row['ip_country'];
            switch ($row['request_type']) {
                case 'registration':
                    $request['type'] = $this->lang['r_type_newuser'];
                    break;
                case 'comment':
                    $request['type'] = $this->lang['r_type_message'];
                    break;
                case 'contact_enquire':
                    $request['type'] = $this->lang['r_type_contact'];
                    break;
                case 'order':
                    $request['type'] = $this->lang['r_type_order'];
                    break;
                default:
                    $request['type'] = $this->lang['r_type_unknown'];
                    break;
            }
            if ($row['moderate'] == 1) {
                $request['short_result'] = sprintf("%s %s",
                    $request['type'],
                    mb_strtolower($row['allow'] ? $this->lang['l_approved'] : $this->lang['l_denied'])
                );
            } else {
                $request['short_result'] = $this->lang['l_service_disabled'];
            }

            if (isset($row['sender_ip']) && $row['sender_ip'] != '-' && function_exists('geoip_country_code_by_name')) {
                $request['country'] = @geoip_country_code_by_name($row['sender_ip']);
            }

            if (isset($remote_details[$row['request_id']])) {
                $data = $remote_details[$row['request_id']];
            } else {
                $data = $ctTools->getRequestData($row['user_id'], $row['request_id'], strtotime($row['datetime']));
            }

            $r_message = null;
            $message = null;

            // Используем декодированную версию сообщения
            if (isset($data['message_decoded']) && $data['message_decoded'] != null) {
                $r_message = $data['message_decoded'];
            }
            if (isset($data['unmasked']) && $data['unmasked'] != null) {
                $r_message = $data['unmasked'];
            }

            // Используем оригинальную версию сообщения
            if (!$r_message && isset($data['message']) && $data['message'] != null && $data['message'] != '') {
                $r_message = $data['message'];
            }

            // Используем JSON кодированную версию сообщения, если таковая имеется.
            $message_array = isset($data['message']) ? json_decode($data['message'], true) : null;

            $r_message = urldecode($r_message);

            // Для турецкого языка фикс
            $r_message = str_replace("%u015F","s",$r_message);
            $r_message = str_replace("%E7","c",$r_message);
            $r_message = str_replace("%FC","u",$r_message);
            $r_message = str_replace("%u0131","i",$r_message);
            $r_message = str_replace("%F6","o",$r_message);
            $r_message = str_replace("%u015E","S",$r_message);
            $r_message = str_replace("%C7","C",$r_message);
            $r_message = str_replace("%DC","U",$r_message);
            $r_message = str_replace("%D6","O",$r_message);
            $r_message = str_replace("%u0130","I",$r_message);
            $r_message = str_replace("%u011F","g",$r_message);
            $r_message = str_replace("%u011E","G",$r_message);

            $message = strip_tags($r_message, '<p>');
            $message_original = $message;

            if ($this->max_message_size > 0) {
                $message = mb_substr($message, 0, $this->max_message_size);
            }
            if ($message != $message_original) $message .= '...';

            $message = nl2br($message);

            // Заменяем последовательности цифр на звездочки
            $message = preg_replace('/(\d{4}\s*)\d{4}/m', '$1****', $message);

            // Показываем кнопку "Сообщить о спаме"
            if (isset($row['approved'])) {
                $request['show_report_spam'] = (bool) $row['approved'];
                $request['feedback_result_message'] = $this->lang['l_feedback_result_message_' . $row['approved']];
            } else {
                $request['show_report_spam'] = (bool) $row['allow'];
                $request['feedback_result_message'] = '';
            }

            $request['page_url'] = $row['post_url'];
            if (!$request['page_url'] && isset($data['sender']['REFFERRER'])) {
                $request['page_url'] = $data['sender']['REFFERRER'];
            }

            if ($row['hostname'] && $request['page_url']) {
                if (preg_match("@^(?:https?://)([^/:]+)(.*)$@i", $request['page_url'], $matches)) {
                    $requests['page_url'] = sprintf("http://%s%s", $row['hostname'], $matches[2]);
                }
            } else {
                $request['page_url'] = null;
            }

            if ($this->max_url_size > 0) {
                $request['page_url_display'] = mb_substr($request['page_url'], 0, $this->max_url_size);
            }
            if ($request['page_url_display'] != $request['page_url']) {
                $request['page_url_display'] .= '...';
            }

            $request['message'] = $message;
            $request['message_array'] = $message_array;
            $request['comment'] = utf8_decode(isset($data['response']['comment']) ? $data['response']['comment'] : '');
            $request['comment_server'] = isset($data['response']['comment_server']) ? $data['response']['comment_server'] : '';
            if (isset($data['sender']['REFFERRER'])) {
                $request['referrer'] = $data['sender']['REFFERRER'];
            } else {
                $request['referrer'] = '';
            }

            if ($this->max_url_size > 0) {
                $request['referrer_display'] = mb_substr($request['referrer'], 0, $this->max_url_size);
                if ($request['referrer_display'] != $request['referrer']) $request['referrer_display'] .= '...';
            } else {
                $request['referrer_display'] = $request['referrer'];
            }

            if (isset($row['approved'])) {
                $request['feedback'] = $this->lang['l_feedback_result_message_' . $row['approved']];
            } else {
                $request['feedback'] = '';
            }
        }

        return $request;
    }

    function get_requests_filter() {
        $where = array();
        $sql_join = '';
        // Установки временной зоны пользователя
        $tz = (isset($this->user_info['timezone']) ? (int)$this->user_info['timezone'] : 0) - 5;
        $tz_ts = $tz * 3600;
        $ts_start_day = strtotime('midnight') - $tz_ts;

        // Временной интервал (период)
        if (isset($_GET['int'])) {
            switch ($_GET['int']) {
                case 'today':
                    $_GET['start_from'] = date('Y-m-d H:i:s', $ts_start_day);
                    break;
                case 'yesterday':
                    $_GET['start_from'] = date('Y-m-d H:i:s', $ts_start_day - 86400);
                    $_GET['end_to'] = date('Y-m-d H:i:s', $ts_start_day);
                    break;
                case 'week':
                    $_GET['start_from'] = date('Y-m-d H:i:s', $ts_start_day - 86400 * 6);
                    break;
            }
        }

        // Временной интервал (диапазон)
        if (isset($_GET['start_from'])) {
            if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_GET['start_from'])) {
                $_GET['start_from'] = date('Y-m-d H:i:s', strtotime($_GET['start_from'] . ' 00:00:00') - $tz_ts);
            }
            if (preg_match('/^\d+$/', $_GET['start_from'])) {
                $_GET['start_from'] = date('Y-m-d H:i:s', $_GET['start_from'] - $tz_ts);
            }
            if (!preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/', $_GET['start_from'])) {
                unset($_GET['start_from']);
            }
        }
        if (isset($_GET['end_to'])) {
            if (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $_GET['end_to'])) {
                $_GET['end_to'] = date('Y-m-d H:i:s', strtotime($_GET['end_to'] . ' 23:59:59') - $tz_ts);
            }
            if (preg_match('/^\d+$/', $_GET['end_to'])) {
                $_GET['end_to'] = date('Y-m-d H:i:s', $_GET['end_to'] - $tz_ts);
            }
            if (!preg_match('/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/', $_GET['end_to'])) {
                unset($_GET['end_to']);
            }
        }

        if (isset($_GET['start_from']) && isset($_GET['end_to'])) {
            $where['r.datetime'] = array('BETWEEN', sprintf("'%s' AND '%s'", $_GET['start_from'], $_GET['end_to']));
        } else if (isset($_GET['start_from'])) {
            $where['r.datetime'] = array('BETWEEN', sprintf("'%s' AND now()", $_GET['start_from']));
        }

        // Approved filter
        if (isset($_GET['allow'])) {
            $where['r.allow'] = (bool)$_GET['allow'] ? 1 : 0;
        }

        // Service ID
        if (isset($_GET['service_id']) && preg_match('/^\d+$/', $_GET['service_id'])) {
            $where['r.service_id'] = (int)$_GET['service_id'];
        } else if (isset($_GET['service_id'])) {
            unset($_GET['service_id']);
        }

        // Country filter
        if (isset($_GET['country']) && preg_match('/^[A-Z]{2}$/', $_GET['country'])) {
            $where['r.ip_country'] = $this->stringToDB($_GET['country']);
        }

        // IP, Email or Nickname
        if (isset($_GET['ipemailnick'])) {
            $_GET['ipemailnick'] = urldecode($_GET['ipemailnick']);
            $ip_email_nick = htmlspecialchars(strip_tags(trim($_GET['ipemailnick'])));
            if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip_email_nick)) {
                $where['r.sender_ip'] = sprintf("inet_aton(%s)",
                    $this->stringToDB($ip_email_nick)
                );
            } else if (preg_match('/^::ffff:\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $ip_email_nick)) {
                // Адрес IPv4, отображённый на IPv6
                $where['inet6_2ntoa(r.sender_ip6_left, r.sender_ip6_right)'] = $this->stringToDB($ip_email_nick);
            } else if (preg_match('/^[a-f0-9]{1,4}:[a-f0-9]{1,4}:[a-f0-9]{1,4}:[a-f0-9]{1,4}:[a-f0-9]{1,4}:[a-f0-9]{1,4}:[a-f0-9]{1,4}:[a-f0-9]{1,4}$/i', $ip_email_nick)) {
                // Адрес IPv6
                $where['inet6_2ntoa(r.sender_ip6_left, r.sender_ip6_right)'] = $this->stringToDB($ip_email_nick);
            } else if (preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/(\d{1,32})$/', $ip_email_nick, $matches)) {
                if (ip2long($matches[1]) !== false && $matches[2] >= 0 && $matches[2] <= 32) {
                    $mask = pow(2, 32) - pow(2, 32 - $matches[2]);
                    $network = ip2long($matches[1]) & $mask;
                    $where['r.sender_ip'] = array('BETWEEN', sprintf('%d AND %d', $network, $network + pow(2, 32 - $matches[2])));
                }
            } else if ($this->valid_email($ip_email_nick)) {
                $where['r.sender_email'] = $this->stringToDB($ip_email_nick);
            } else if (preg_match('/^country_[A-Z]{2}$/i', $ip_email_nick)) {
                $where['r.ip_country'] = str_replace('country_', '', $ip_email_nick);
            } else if (preg_match('/^=.*$/i', $ip_email_nick)) {
                $where['l.record'] = $this->stringToDB($ip_email_nick);
                $sql_join = 'LEFT JOIN services_private_list_log l ON l.request_id = r.request_id';
            } else {
                $where['r.sender_nickname'] = array('LIKE', sprintf("'%%%s%%'", str_replace("'", '', $ip_email_nick)));
            }
        }

        // Request ID
        if (isset($_GET['request_id']) && preg_match('/^[a-f0-9]{32}$/', $_GET['request_id'])) {
            $where = array();
            $where['r.request_id'] = $this->stringToDB($_GET['request_id']);
        }

        // Сервисы к которым есть доступ
        if (isset($_GET['service_id']) && !in_array($_GET['service_id'], $this->granted_services_ids) && isset($this->user_info['user_id'])) {
            $where['s.user_id'] = $this->user_info['user_id'];
        } else if (!isset($_GET['service_id']) && isset($this->user_info['user_id'])) {
            $where['s.user_id'] = $this->user_info['user_id'];
        }

        $sql_where = array();
        foreach ($where as $key => $val) {
            if ($key == 'granted') {
                $sql_where[] = $val;
            } else if (is_array($val)) {
                $sql_where[] = sprintf('%s %s %s', $key, $val[0], $val[1]);
            } else {
                $sql_where[] = $key . '=' . $val;
            }
        }
        if(empty($sql_where))$sql_where[] = 's.user_id = '.$this->user_id;
        return array('where'=>$sql_where,'join'=>$sql_join);
    }

    function get_requests_count() {
        $count = 0;
        $filter_result = $this->get_requests_filter();
        $sql_where = $filter_result['where'];
        $sql_join = $filter_result['join'];
        $sql = sprintf(
            "SELECT count(r.request_id) as 'count' FROM requests r 
                    LEFT JOIN services s ON s.service_id = r.service_id 
                    $sql_join
                    WHERE %s", implode(' AND ', $sql_where)
        );
        $result = $this->db->select($sql);
        if(isset($result['count'])){
            $count = intval($result['count']);
        }
        return $count;
    }


    function get_requests($start=0,$limit=false) {
        // Установки временной зоны пользователя
        $tz = (isset($this->user_info['timezone']) ? (int)$this->user_info['timezone'] : 0) - 5;
        $tz_ts = $tz * 3600;

        $requests = array();
        $filter_result = $this->get_requests_filter();
        $sql_where = $filter_result['where'];
        $sql_join = $filter_result['join'];
        $sql_limit = '';
        if($limit){
            $sql_limit = sprintf('LIMIT %d,%d', $start, $limit);
        }
        $sql = sprintf(
            "SELECT %s FROM requests r 
                    LEFT JOIN services s ON s.service_id = r.service_id 
                    LEFT JOIN requests_feedback f on f.request_id = r.request_id 
                    LEFT JOIN bl_ips i ON i.ip=r.sender_ip 
                    LEFT JOIN bl_ips_v6 i6 ON i6.ip6_left=r.sender_ip6_left AND i6.ip6_right=r.sender_ip6_right
                    LEFT JOIN bl_emails e ON e.email=r.sender_email
                    $sql_join
                    WHERE %s ORDER BY r.datetime DESC %s",
            implode(',', array(
				'r.request_id', 'r.allow', 'r.service_id', 'inet_ntoa(r.sender_ip) AS ip',
                'inet6_2ntoa(r.sender_ip6_left, r.sender_ip6_right) as ip6',
                'r.sender_email', 'r.ip_country', 'r.datetime', 'r.moderate', 'r.request_type',
                'r.sender_nickname', 'e.email_exists',
                's.hostname', 's.name', 's.user_id', 'IFNULL(i.frequency,0)+IFNULL(i6.frequency,0) AS ip_frequency',
                'IFNULL(e.frequency,0) AS email_frequency',
                'f.approved', 'IFNULL(i.frequency,0)+IFNULL(i6.frequency,0)+IFNULL(e.frequency,0) AS frequency'
            )),
            implode(' AND ', $sql_where),
            $sql_limit
		);
        $rows = $this->db->select($sql, true);
        // Predefined short results
        $short_results = array(
            'registration' => array(
                $this->lang['r_type_newuser_denied'],
                $this->lang['r_type_newuser_approved']
            ),
            'comment' => array(
                $this->lang['r_type_message_denied'],
                $this->lang['r_type_message_approved']
            ),
            'contact_enquire' => array(
                $this->lang['r_type_contact_denied'],
                $this->lang['r_type_contact_approved']
            ),
            'order' => array(
                $this->lang['r_type_order_denied'],
                $this->lang['r_type_order_approved']
            ),
            'unknown' => array(
                $this->lang['r_type_unknown_denied'],
                $this->lang['r_type_unknown_approved']
            )
        );
        if(is_array($rows))
        foreach ($rows as $row) {
            /**
             * id: request_id
             * sid: service_id
             * dt: datetime
             * a:  allow
             * m:  moderate
             * c:  country
             * r:  short result
             * h:  service host|name|id
             * f:  feedback result message
             * n:  sender nickname
             * e:  sender email
             * i:  sender IP
             * s:  show report spam
             * q:  frequency
             */
            $request = array(
                'id' => $row['request_id'],
                'sid' => $row['service_id'],
                'dt' => date('M d, Y H:i:s', strtotime($row['datetime']) + $tz_ts),
                'a' => (bool)$row['allow'],
                'm' => (bool)$row['moderate'],
                'c' => $row['ip_country'],
                'n' => $row['sender_nickname'],
                'e' => $row['sender_email'],
                'i' => (isset($row['ip'])) ? $row['ip'] : (isset($row['ip6']) ? $row['ip6'] : false),
                'q' => number_format($row['frequency'],0,'',' '),
                'eq' => number_format($row['email_frequency'],0,'',' '),
                'iq' => number_format($row['ip_frequency'],0,'',' '),
                'ne' => ($row['email_exists']=='NOT_EXISTS') ? 1 : 0,
				
            );

            if ($request['m']) {
                if (!isset($short_results[$row['request_type']])) $row['request_type'] = 'unknown';
                $request['r'] = $short_results[$row['request_type']][$row['allow']];
            } else {
                $request['r'] = $this->lang['l_service_disabled'];
            }

            if ($this->user_info['services'] > 1) {
                if (isset($row['hostname'])) {
                    $request['h'] = $row['hostname'] . (isset($row['name']) ? sprintf(' (%s)', $row['name']) : '');
                } else if (isset($row['name'])) {
                    $request['h'] = $row['name'];
                } else {
                    $request['h'] = '#' . $row['service_id'];
                }
            }

            if (isset($row['approved'])) {
                $request['f'] = $this->lang['l_feedback_result_message_' . $row['approved']];
                $request['s'] = (bool)$row['approved'];
            } else {
                $request['s'] = $request['a'];
            }

            $requests[] = $request;
        }

        return $requests;
    }

    /**
      * Функция вывода запросов к сервису
      *
      * @param int $service_id ID сервиса
      *
      * @return array
      */
	function show_requests($service_id = null) {
		$requests = null; // Массив с запросами
		$period = null; // Временной интервал запросов
        $start_from = null;
        $end_to = null;
        $days = null;

        $timezone = isset($this->user_info['timezone']) ? (int) $this->user_info['timezone'] : 0;
        $tz_ts = $this->user_info['tz_ts'];
        $midnight_seconds = $tz_ts - strtotime("today", $tz_ts);
	    $this->user_info['midnight_seconds'] = &$midnight_seconds;

        // Страны для фильтрации по стране
        $logs_countries = apc_fetch('logs_countries_'.$this->ct_lang);
        if (!$logs_countries && isset($this->ct_langs[$this->ct_lang])){
            $logs_countries = $this->db->select(
                sprintf("SELECT countrycode, %s AS langname FROM logs_countries ORDER BY langname ASC", preg_replace('/[^a-zA-Z]/i', '', $this->ct_lang)), true
            );
            apc_store('logs_countries_' . $this->ct_lang, $logs_countries, 25000);
        }
        $this->page_info['logs_countries'] = $logs_countries;

        if (isset($_GET['country'])){
            $country = preg_replace('/[^A-Z]/i', '', $_GET['country']);
            $sql_country = sprintf(" and ip_country = %s", $this->stringToDB($country));
            $this->page_info['sel_logcountry'] = $country;
        }
        else
            $sql_country = '';

        if (isset($_REQUEST['start_from']) && preg_match("/^\d+[\.0-9]*$/", $_REQUEST['start_from'])) {
            $start_from = sprintf("%d",$_REQUEST['start_from']);
        }

        if (isset($_REQUEST['end_to']) && preg_match("/^\d+$/", $_REQUEST['end_to'])) {
            $end_to = $_REQUEST['end_to'];
        }

        // Переводим полученный диапазон дат в дни для совместимости с предыдущим кодом.
        if ($start_from && $end_to && $start_from < $end_to) {
            $days = sprintf("%d", ($end_to - $start_from) / 86400);
        }

        if (isset($_REQUEST['days']) && is_int((int)$_REQUEST['days'])) {
            $days = (int)$_REQUEST['days'];
        }

        if ($days && !$end_to && $start_from) {
            $end_to = $start_from;
        }

        if (isset($_GET['ipemailnick'])) {
            if (!$start_from) {
                $start_from = 0;
            }
            if (!$end_to) {
                $end_to = time();
            }
            $_GET['ipemailnick'] = urldecode($_GET['ipemailnick']);
        }

		if (isset($_GET['int'])) {
   			switch($_GET['int']){
				case 'today':
					$period = 0;
                    break;
				case 'yesterday':
					$period = 1;
                    break;
				case 'week':
					$period = 7;
                    break;
                default:
                    if (is_int((int)$_GET['int']) && $_GET['int'] > 7) {
                        $start_from = (int) $_GET['int'];
                        $days = 1;
                    } else {
                        $period = 0;
                    }
                break;
			}

            if ($period !== null)
                $this->int = $_GET['int'];
        }

		$allow = null;
		if (isset($_REQUEST['allow']) && ($_REQUEST['allow'] == 1 || $_REQUEST['allow'] == 0))
			$allow = $_REQUEST['allow'];

        $type = null;
        $sql_type = '';
		if (isset($_GET['type']) && preg_match("/^(message|newuser)$/", $_GET['type'])) {
			$type = 'check_' . $_GET['type'];
            $sql_type = 'and method_name = \'' . $type . '\'';
        }

        $service_id = null;
        $sql_service_id = '';
        $confirm_deletion_bulk = sprintf($this->lang['l_confirm_deletion_bulk']);
        // Признак является ли предложенный сайт делегированным именно этому пользователю
        $is_granted_service = false;
        $this->page_info['grantwrite'] = 1;
		if (isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
			$service_id = $_REQUEST['service_id'];
            if (in_array($service_id, $this->granted_services_ids)) {
                $is_granted_service = true;
                // Определяем уровень доступа для сайта - чтение или запись
                // функция grant_access_level в ccs.php
                $this->page_info['grantwrite'] = $this->grant_access_level($service_id);
            }
            $sql_service_id = 'and r.service_id = ' . $service_id;
            $this->notify_service_id = $service_id;
            $this->service_id = $service_id;
        }
        $this->page_info['confirm_deletion_bulk'] = $confirm_deletion_bulk;

        /*
            Удаляем записи из журнала.
        */
        if (isset($_GET['delete_records']) && $_GET['delete_records'] == 1) {
            $sql_service_id = '';
            if (isset($_GET['service_id']) && preg_match("/^\d+$/", $_GET['service_id'])) {
                $service_id = $_GET['service_id'];
                $sql_service_id = ' and service_id = ' . $service_id;
            }
            $sql = sprintf("select count(*) as count from requests r where user_id = %d and allow = 1%s;",
                $this->user_id,
                $sql_service_id
            );
            $row = $this->db->select($sql);

            if ($row['count'] > 0) {
                $sql = sprintf("delete from requests where user_id = %d and allow = 1%s;",
                    $this->user_id,
                    $sql_service_id
                );
                $this->db->run($sql);
                $this->post_log(sprintf("Пользователь %s (%d) удалил %d одобренных запросов из антиспам журнала.",
                    $this->user_info['email'],
                    $this->user_id,
                    $row['count']
                ));

                $return_url = '/my';
                if (isset($_GET['return_url'])) {
                    $return_url_tmp = urldecode($_GET['return_url']);
                    $tools = new CleanTalkTools();
                    if ($tools->get_domain($return_url_tmp) == $_SERVER['HTTP_HOST']) {
                       $return_url = $return_url_tmp;
                    }
                }
                header("Location:" . $return_url);
                exit;
            }
        }

        $sql_allow = '';
		if ($allow !== null)
			$sql_allow = 'and allow = ' . $allow;

        if (preg_match("/(\d).(\d)$/", $this->user_info['timezone'], $matches)) {
            $sql_timezone = sprintf("%d:%d", $matches[1], (60 / 10) * $matches[2]);
        } else {
            $sql_timezone = $this->user_info['timezone'] . ":00";
        }

        if (preg_match("/^\d/", $sql_timezone)) {
            $sql_timezone = '+' . $sql_timezone;
        }
        /*if ($is_granted_service)
            $sql = 'select r.request_id, allow, (%s) as datetime, r.sender_email, inet_ntoa(r.sender_ip) as sender_ip, r.sender_nickname, r.user_id, r.method_name, r.post_url, r.request_type, r.collector_id, f.approved, datetime as datetime_db, ss.hostname, ss.service_id, r.moderate from requests r left join requests_feedback f on f.request_id = r.request_id left join services ss on ss.service_id = r.service_id where 1 %s %s %s %s %s %s %s order by datetime desc limit %d,%d;';
        else
		    $sql = 'select r.request_id, allow, (%s) as datetime, r.sender_email, inet_ntoa(r.sender_ip) as sender_ip, r.sender_nickname, r.user_id, r.method_name, r.post_url, r.request_type, r.collector_id, f.approved, datetime as datetime_db, ss.hostname, ss.service_id, r.moderate from requests r left join requests_feedback f on f.request_id = r.request_id left join services ss on ss.service_id = r.service_id where (ss.user_id = %d %s) %s %s %s %s %s %s %s order by datetime desc limit %d,%d;';
        $sql_datetime_show = sprintf('convert_tz(datetime, \'+%d:00\', \'%s\')', $this->options['billing_timezone'], $sql_timezone);*/
        if ($is_granted_service)
            $sql = 'select r.request_id, allow, datetime, r.sender_email, inet_ntoa(r.sender_ip) as sender_ip, r.sender_nickname, r.user_id, r.method_name, r.post_url, r.request_type, r.collector_id, f.approved, datetime as datetime_db, ss.hostname, ss.service_id, r.moderate from requests r left join requests_feedback f on f.request_id = r.request_id left join services ss on ss.service_id = r.service_id where 1 %s %s %s %s %s %s %s order by datetime desc limit %d,%d;';
        else
            $sql = 'select r.request_id, allow, datetime, r.sender_email, inet_ntoa(r.sender_ip) as sender_ip, r.sender_nickname, r.user_id, r.method_name, r.post_url, r.request_type, r.collector_id, f.approved, datetime as datetime_db, ss.hostname, ss.service_id, r.moderate from requests r left join requests_feedback f on f.request_id = r.request_id left join services ss on ss.service_id = r.service_id where (ss.user_id = %d %s) %s %s %s %s %s %s %s order by datetime desc limit %d,%d;';

        $sql_date = '';
        $date_range_begin = time();
        $date_range_end = time();

        if ($start_from !== null) {
            $date_range_begin = $start_from;
            if ($days) {
                // Учитываем всю дату - от 00.00.00 до 23.59.59
                $date_range_end = $end_to;
                $date_range_end_base = $end_to + $days * 86400 - 1;
                if ($days == 7) {
                    $date_range_end_base = $date_range_end_base + 86400;
                }
                $sql_date = sprintf('and datetime between from_unixtime(%d) and from_unixtime(%d)',
                        $date_range_begin, $date_range_end_base);
            } else {
                $sql_date = sprintf('and datetime >= from_unixtime(%d)', $date_range_begin);
            }
        } else {
            $timezone = isset($this->user_info['timezone']) ? (int) $this->user_info['timezone'] : 0;
            $ts_start_day = strtotime("today") + ($timezone * 3600);

            //$start_date_ts = time() - $midnight_seconds - 86400 * $period;
            $start_date_ts = $ts_start_day - (86400 * $period);
            $date_range_begin = $start_date_ts;
            $sql_datediff = sprintf('and datetime between %s and now()',
                            $this->stringToDB(date("Y-m-d H:i:s", $date_range_begin)));

            if ($period == 1) {
                $date_range_end = $date_range_begin + 86400 - 1;
                $sql_datediff = sprintf('and datetime between %s and %s',
                                        $this->stringToDB(date("Y-m-d H:i:s", $date_range_begin)),
                                        $this->stringToDB(date("Y-m-d H:i:s", $date_range_end))
                                       );
            }

            $sql_date = $sql_datediff;
        }

        $date_range = sprintf("%s - %s",
            date("M d, Y", $date_range_begin),
            date("M d, Y", $date_range_end)
        );

        $request_id = null;
        $sql_request_id = '';
		if (isset($_GET['request_id']) && preg_match("/^[a-f0-9]+$/i", $_GET['request_id'])) {
			$request_id = $_GET['request_id'];
            $sql_request_id = ' and r.request_id = ' . $this->stringToDB($request_id);
            $sql_date = '';
            $sql_allow = '';
            $sql_type = '';
            $sql_service_id = '';
            $this->page_info['single_request'] = 1;
        }

        $sql_ipemailnick = "";
        $recs_ipemailnick = "";
        $search_by_url = false;
        $email_url = '';
        $search_by_rootdomain = false;
        $rootdomain = '';

        if (isset($_GET['ipemailnick']))
          {

            $ipemailnick = htmlspecialchars(strip_tags(trim($_GET['ipemailnick'])));

            $this->page_info['ipemailnick'] = $ipemailnick;

            if (preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ipemailnick))
              {
                $sql_ipemailnick = " and inet_ntoa(r.sender_ip) = '" . $ipemailnick . "'";
                $recs_ipemailnick = " and inet_ntoa(sender_ip) = '" . $ipemailnick . "'";
              }
            elseif (preg_match("/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\/(\d{1,32})$/", $ipemailnick, $matches)) {
                if (ip2long($matches[1]) !== false && $matches[2] >= 0 && $matches[2] <= 32) {
                    $mask = pow(2,32) - pow(2,32 - $matches[2]);
                    $network = ip2long($matches[1]) & $mask;

                    $sql_ipemailnick = sprintf(" and r.sender_ip between %d and %d",
                        $network,
                        $network + pow(2,32 - $matches[2])
                    );
//                    var_dump($sql_ipemailnick,$mask, $network,$matches);
                }
              }
            elseif($this->valid_email($ipemailnick))
              {
                $sql_ipemailnick = " and r.sender_email = '" . $ipemailnick . "'";
                $recs_ipemailnick = " and sender_email = '" . $ipemailnick . "'";
              }
            elseif (preg_match('/country_[A-Z]{2}/i', $ipemailnick)) {
                $sql_ipemailnick = " and r.ip_country = '" . str_replace('country_', '', $ipemailnick) . "'";
                $recs_ipemailnick = " and sender_email = '" . $ipemailnick . "'";

            }
            elseif (preg_match('/[\.a-zA-Z0-9_]+\.[a-z]+/i', $ipemailnick)) {
                $search_by_url = true;
                $email_url = $ipemailnick;
            }
            elseif (preg_match('/^[a-z]+\.[a-z]+$/i', $ipemailnick)){
                $search_by_rootdomain = true;
                $rootdomain = $ipemailnick;
            }
            else
              {
                $ipemailnick = preg_replace('/drop|delete|select|truncate|insert|update|where/i', '', $ipemailnick);
                $sql_ipemailnick = " and r.sender_nickname like '%" . $ipemailnick . "%'";
                $recs_ipemailnick = " and sender_nickname like '%" . $ipemailnick . "%'";
              }

          }
        else
          {
            $this->page_info['ipemailnick'] = '';
          }

        if ($this->page!=0)
          {
            $limit1 = ($this->page-1)*$this->requests_limit;
            $limit2 = $this->requests_limit;
          }
        else
          {
            // Определяем количество записей в таблице для пользователя
            // Если задан сайт для которого нужно посчитать количество записей
            if (isset($_REQUEST['service_id']) && preg_match("/^\d+$/", $_REQUEST['service_id'])) {
              $service_id = $_REQUEST['service_id'];
              $numrecs_sql_service_id = 'and service_id = ' . $service_id;
            }
            else
              $numrecs_sql_service_id = '';

            $numrecs = $this->db->select(sprintf(
                "select count(*) as numrecs from requests r where user_id=%d %s %s %s %s %s",
                $this->user_info['user_id'],
                $sql_date,
                $sql_allow,
                $numrecs_sql_service_id,
                $sql_ipemailnick,
                $sql_country
            ));

            $this->numrecs = $numrecs['numrecs'];
            $limit1 = 0;
            $limit2 = $this->requests_limit;
          }
        if ($is_granted_service)
            $sql = sprintf($sql, $sql_date, $sql_allow, $sql_type, $sql_service_id, $sql_request_id, $sql_ipemailnick, $sql_country, $limit1, $limit2);
        else {
            if (!isset($_GET['service_id']) && count($this->granted_services_ids))
                $sql_allsites = ' or ss.service_id in ('.implode(',', $this->granted_services_ids).')';
            else
                $sql_allsites = '';
            $sql = sprintf($sql, $this->user_id, $sql_allsites, $sql_date, $sql_allow, $sql_type, $sql_service_id, $sql_request_id, $sql_ipemailnick, $sql_country, $limit1, $limit2);
        }
        //echo($sql); exit();
        if ($this->app_mode && cfg::debug_app) {
            error_log(print_r($sql,true));
        }
//error_log($sql);
		$row = $this->db->select($sql, true);

        $collectors_ids_init = array();
        $collectors_ids = array();
        // определяем список id коллекторов для дальнейших запросов
        foreach ($row as $onerow)
            $collectors_ids_init[] = $onerow['collector_id'];
        $collectors_ids = array_unique($collectors_ids_init);

        $details_query = array();
		foreach ($row as $k => $v){

            $row[$k]['type'] = '';

            // Дубль типа сообщения нужен для совместимости со старым кодом
            if ($v['request_type'] == 'registration') {
			    $row[$k]['type'] = 'newuser';
            }
            if ($v['request_type'] == 'comment') {
			    $row[$k]['type'] = 'message';
            }

            if ($v['request_type'] == 'contact_enquire') {
                $row[$k]['type'] = 'contact';
            }

            foreach($collectors_ids as $onecollectorid) {
                if ($onecollectorid == $v['collector_id']) {
                    if (!isset($details_query[$onecollectorid])) {
                        $details_query[$onecollectorid] = '';
                    }
                    if ($details_query[$onecollectorid] != '')
                        $details_query[$onecollectorid] .= ',';
                    if ($is_granted_service)
                        $details_query[$onecollectorid] .= sprintf("%d:%s:%s", $v['user_id'], $v['request_id'], $v['datetime_db']);
                    else
                        $details_query[$onecollectorid] .= sprintf("%d:%s:%s", $this->user_id, $v['request_id'], $v['datetime_db']);
                }
            }

		}

        $ctTools = new CleanTalkTools();
        $remote_details = array();
        foreach($details_query as $k => $onedetailquery) {
            if ($onedetailquery != '' && cfg::enable_remote_details && !isset($_GET['is_ajax'])) {
                $remote_details[$k] = $ctTools->get_remote_details(
                    str_replace('collector', 'collector'.$k, $this->options['remote_details_url']),
                    $onedetailquery,
                    'unmasked,response,message,message_decoded,sender,comment_server'
                );
            }
        }

        if (isset($timezone) && $timezone) {
            $timezone -= $this->options['billing_timezone'];
        } else {
            $timezone = 0 - $this->options['billing_timezone'];
        }

        $requests = &$row;
        $approved_requests = 0;
		foreach ($requests as $k => $v){

            // Поиск по домену второго уровня сразу же
            if ($search_by_url){
                $emailexpl = explode('@', trim($requests[$k]['sender_email']));
                if ($emailexpl[1] != $email_url) {
                    unset($requests[$k]);
                    continue;
                }
            }

            if ($search_by_rootdomain) {
                if (end(explode('.', trim($requests[$k]['sender_email']))) != $rootdomain) {
                    unset($requests[$k]);
                    continue;
                }

            }

			switch($requests[$k]['type']){
				case 'newuser':
					$requests[$k]['type'] = $this->lang['r_type_newuser'];
					break;
				case 'message':
					$requests[$k]['type'] = $this->lang['r_type_message'];
					break;
                case 'contact':
                    $requests[$k]['type'] = $this->lang['r_type_contact'];
                    break;
                case 'order':
                    $requests[$k]['type'] = $this->lang['r_type_order'];
                    break;
				default:
					$requests[$k]['type'] = $this->lang['r_type_unknown'];
			}

            if ($requests[$k]['moderate'] == 1) {
                $requests[$k]['short_result'] = sprintf("%s %s",
                    $requests[$k]['type'],
                    mb_strtolower($requests[$k]['allow'] == 1 ? $this->lang['l_approved'] : $this->lang['l_denied'])

                );
            } else {
                $requests[$k]['short_result'] = $this->lang['l_service_disabled'];
            }
			if (isset($requests[$k]['sender_ip']) && $requests[$k]['sender_ip'] != '-' && function_exists('geoip_country_code_by_name'))
				$requests[$k]['country'] = @geoip_country_code_by_name($requests[$k]['sender_ip']);

            if (isset($remote_details[$v['collector_id']][$v['request_id']])) {
                $data = $remote_details[$v['collector_id']][$v['request_id']];
            } else if (!isset($_GET['is_ajax'])) {
			    $data = $ctTools->getRequestData($requests[$k]['user_id'], $requests[$k]['request_id'], strtotime($v['datetime_db']));
            } else {
                $data = array();
            }
            $r_message = null;
            $message = null;

            // Используем декодированную версию сообщения
            if (isset($data['message_decoded']) && $data['message_decoded'] != null) {
                $r_message = $data['message_decoded'];
            }

            // Используем декодированную версию сообщения
            if (isset($data['unmasked']) && $data['unmasked'] != null) {
                $r_message = $data['unmasked'];
            }

           // Используем оригинальную версию сообщения
            if (!$r_message && isset($data['message']) && $data['message'] != null && $data['message'] != '') {
                $r_message = $data['message'];
            }

            //
            // Используем JSON кодированную версию сообщения, если таковая имеется.
            //
            $message_array = isset($data['message']) ? json_decode($data['message'], true) : null;

            if ($this->app_mode) {
                if ($r_message)
                    $message = substr($r_message, 0, $this->options['app_message_max_size']);

                unset($requests[$k]['method_name']);
                unset($requests[$k]['user_id']);
                unset($requests[$k]['sender_ip']);
                unset($requests[$k]['country']);
                unset($requests[$k]['post_url']);
            } else {
                $requests[$k]['sender_info'] = '';

                $r_message = urldecode($r_message);

                // Для турецкого языка фикс

                $r_message = str_replace("%u015F","s",$r_message);
                $r_message = str_replace("%E7","c",$r_message);
                $r_message = str_replace("%FC","u",$r_message);
                $r_message = str_replace("%u0131","i",$r_message);
                $r_message = str_replace("%F6","o",$r_message);
                $r_message = str_replace("%u015E","S",$r_message);
                $r_message = str_replace("%C7","C",$r_message);
                $r_message = str_replace("%DC","U",$r_message);
                $r_message = str_replace("%D6","O",$r_message);
                $r_message = str_replace("%u0130","I",$r_message);
                $r_message = str_replace("%u011F","g",$r_message);
                $r_message = str_replace("%u011E","G",$r_message);

				$message = strip_tags($r_message, '<p>');
				// Обрезаем длинные блоки символов, дабы не ломалась HTML верстка на странице.
#                $message = wordwrap($message, $this->max_word_size, "<br />", true);

                $message_original = $message;
                if ($this->max_message_size > 0) {
                    $message = mb_substr($message, 0, $this->max_message_size);
                }
                if ($message != $message_original) {
                    $message .= '...';
                }
                $message = nl2br($message);

                // Показываем кнопку "Сообщить о спаме"
                if (isset($requests[$k]['approved'])) {
                    $requests[$k]['show_report_spam'] = (bool) $requests[$k]['approved'];
                    $requests[$k]['show_report_not_spam'] = (bool) !$requests[$k]['approved'];
                } else {
                    // Показываем кнопку "Сообщить о спаме"
                    if ($requests[$k]['allow'] == 1)
                        {
                            $requests[$k]['show_report_spam'] = true;
                            $requests[$k]['show_report_not_spam'] = false;
                        }

                    // Показываем кнопку "Это не спам"
                    if ($requests[$k]['allow'] == 0)
                        {
                          $requests[$k]['show_report_not_spam'] = true;
                          $requests[$k]['show_report_spam'] = false;
                        }
                 }

                if (isset($requests[$k]['approved'])){
                    $requests[$k]['feedback_result_message'] = $this->lang['l_feedback_result_message_' . $requests[$k]['approved']];
                }
                else
                  $requests[$k]['feedback_result_message'] = '';

                /*
                    Выводим ссылку на страницу с формой.
                */
                $requests[$k]['page_url'] = $requests[$k]['post_url'];
                if (!$requests[$k]['page_url'] && isset($data['sender']['REFFERRER'])) {
                    $requests[$k]['page_url'] = $data['sender']['REFFERRER'];
                }
/*
                if (isset($data['sender']['page_url'])) {
                    if (!preg_match("/^http/", $data['sender']['page_url'])) {
                        $data['sender']['page_url'] = 'http://' . $data['sender']['page_url'];
                    }
                    $requests[$k]['page_url'] = $data['sender']['page_url'];
                }
*/
                if ($requests[$k]['hostname'] && $requests[$k]['page_url']) {

                    //
                    // Присваеваем page_url только при условии совпадения hostname со значением в базе, дабы мотивировать пользователей
                    // использовать отдельные ключи доступа для каждого вебсайта.
                    //
                    if (preg_match("@^(?:https?://)([^/:]+)(.*)$@i", $requests[$k]['page_url'], $matches)) {
                        $requests[$k]['page_url'] = sprintf("http://%s%s",
                            $requests[$k]['hostname'],
                            $matches[2]
                        );
                    }
                } else {
                    $requests[$k]['page_url'] = null;
                }

                $requests[$k]['sender_email_display'] = wordwrap($requests[$k]['sender_email'], $this->max_sender_size, "<br />", true);
//                $requests[$k]['page_url_display'] = wordwrap($requests[$k]['page_url'], $this->max_sender_size, "<br />", true);
//                $requests[$k]['page_url_display'] = $requests[$k]['page_url'];

                if ($this->max_url_size > 0) {
                    $requests[$k]['page_url_display'] = mb_substr($requests[$k]['page_url'], 0, $this->max_url_size);
                }
                if ($requests[$k]['page_url_display'] != $requests[$k]['page_url']) {
                    $requests[$k]['page_url_display'] .= '...';
                }

                if (isset($timezone) && $timezone) {
                    $requests[$k]['datetime'] = date("M d Y H:i:s", strtotime($requests[$k]['datetime']) + 3600 * $timezone);
                } else {
                    $requests[$k]['datetime'] = date("M d Y H:i:s", strtotime($requests[$k]['datetime']));
                }
            }
            $requests[$k]['message'] = $message;
            $requests[$k]['message_array'] = $message_array;
            $requests[$k]['comment'] = utf8_decode(isset($data['response']['comment']) ? $data['response']['comment'] : '');
            $requests[$k]['comment_server'] = isset($data['response']['comment_server']) ? $data['response']['comment_server'] : '';
            if ($this->user_info['services'] > 1) {
                $requests[$k]['visible_hostname'] = $this->get_service_visible_name($requests[$k]);
            }
            if ($requests[$k]['allow'] == 1) {
                $approved_requests++;
            }

            if ($requests[$k]['sender_email'] == '******')
                $requests[$k]['show_approved'] = false;
            else
                $requests[$k]['show_approved'] = true;
            if (isset($data['sender']['REFFERRER']))
                $requests[$k]['referer'] = $data['sender']['REFFERRER'];

		}

        if ($this->app_mode && count($requests) && $service_id && $start_from) {
            $row_notify = $this->db->select(sprintf("select unix_timestamp(app_last_notify) as app_last_notify_ts from services where user_id = %d and service_id = %d;",
                            $this->user_id, $service_id));

            if (!isset($row_notify['app_last_notify_ts']) || $row_notify['app_last_notify_ts'] < $start_from)
                $this->db->run(sprintf("update services set app_last_notify = now() where user_id = %d and service_id = %d;",
                            $this->user_id, $service_id));
        }

        // Тормозим дабы не задосить сервер
		if (count($requests))
			sleep($this->options['fail_timeout']);

        $this->page_info['date_range'] = $date_range;
        $this->page_info['date_range_begin'] = date("M d, Y", $date_range_begin);
        $this->page_info['date_range_end'] = date("M d, Y", $date_range_end);
        $this->page_info['approved_requests'] = $approved_requests;
        $this->page_info['thanks_notice'] = $this->lang['l_thanks_notice'];
        $this->page_info['notice_1'] = $this->lang['l_notice_1'];
        $this->page_info['notice_2'] = $this->lang['l_notice_2'];

		return $requests;
	}

    /**
      * Функция возвращает локализованное имя месяца
      *
      * @param string $timestamp
      *
      * @return string
      */

    public function get_month_name($timestamp) {
        $month_name = null;

        $locale_default = 'en_EN';
        if ($this->ct_lang == 'ru') {
            setlocale(LC_ALL, 'ru_RU');
        }

        $month_name = strftime("%b", $timestamp);
        if ($this->ct_lang == 'ru') {
            setlocale(LC_ALL, $locale_default);
        }

        return $month_name;
    }

    /**
      * Функция перенаправляет пользователя на указанный $url
      *
      * @param string $url
      *
      * @return void
      *
      */

    public function redirect($url){
        header('Location: '.$url);
        exit();
    }

    /**
      * Функция берёт из apc массив со странами или из базы если нет в apc
      *
      * @return array
      */

    public function get_lang_countries(){
        $countries = apc_fetch('logs_countries_'.$this->ct_lang);
        if (!$countries){
            $countries = $this->db->select(sprintf("select countrycode, %s as langname 
                                                     from logs_countries
                                                     order by langname asc",
                                                     preg_replace('/[^a-zA-Z]/i', '', $this->ct_lang)),true);
            apc_store('logs_countries_'.$this->ct_lang, $countries, 25000);
        }
        return $countries;
    }

}

/*
	Дабы можно было отсортировать массив по значению
*/
class Tools{

    /**
      *
      *
      * @param
      *
      * @return
      */

	static function cmp_datetime($a, $b)
	{
		return strcmp($b["datetime"], $a["datetime"]);
	}

}
