<?php

/**
 * Класс для работы с Ajax запросами
 */
class Ajax extends CCS {
    /**
     * @var string $test_email Тестовый email
     */
    private $test_email = null;

    /**
     * @var int $ajax_service_id Используемый service_id сайта
     */
    private $ajax_service_id = 0;

    /**
     * @var int $ajax_request_id Используемый request_id
     */
    private $ajax_request_id = '';

    /**
     * @var int $ajax_user_id Используемый user_id пользователя
     */
    private $ajax_user_id = 0;

	function __construct() {
		parent::__construct();
		$this->ccs_init();
	}

    /**
      * Функция показа страницы
      *
      * @param string $unknown
      * @param bool $ajax Использовать ли Ajax
      */
	function show_page($unknown, $ajax = false) {
	    if (isset($_POST['action'])) {
	        if ($_POST['action'] == 'delete_request_bulk' && isset($_POST['requests'])) {
                $_POST['requests'] = explode(',', $_POST['requests']);
                $ids = array();
                foreach ($_POST['requests'] as $id) {
                    if (preg_match('/^[a-f0-9]{32}$/', $id)) $ids[] = $id;
                }

                if (count($ids)) {
                    $this->db->run(sprintf("DELETE FROM requests WHERE request_id IN ('%s')", implode("','", $ids)));
                }

                echo(json_encode($ids));
                exit;
            }

	        if ($_POST['action'] == 'request_feedback_bulk' && isset($_POST['approve']) && isset($_POST['requests'])) {
	            $response = array(
	                'requests' => array(),
                    'approve' => (bool)$_POST['approve']
                );
	            $_POST['requests'] = explode(',', $_POST['requests']);
	            $ids = array();
	            foreach ($_POST['requests'] as $id) {
	                if (preg_match('/^[a-f0-9]{32}$/', $id)) $ids[] = $id;
                }

                if (count($ids)) {
	                $feedback = array();
	                $users = array();

	                // Запросим текущее состояние записей и удалим те, в которых нет необходимости что-то менять
                    $rows = $this->db->select(sprintf("SELECT r.request_id, r.user_id , r.allow, r.moderate, f.approved FROM requests r LEFT JOIN requests_feedback f ON f.request_id = r.request_id WHERE r.request_id IN ('%s')", implode("','", $ids)), true);
                    foreach ($rows as $row) {
                        if ($row['allow'] && $row['moderate']) {
                            $approved = (is_null($row['approved'])) ? true : (bool)$row['approved'];
                        } else {
                            $approved = (is_null($row['approved'])) ? false : (bool)$row['approved'];
                        }
                        if ($response['approve'] === $approved && ($key = array_search($row['request_id'], $ids)) !== false) {
                            unset($ids[$key]);
                        } else {
                            if (!is_null($row['approved'])) $feedback[] = $row['request_id'];
                            $users[$row['request_id']] = $row['user_id'];
                        }
                    }
                    $response['requests'] = $ids;

                    foreach ($ids as $id) {
                        if (in_array($id, $feedback)) {
                            $this->db->run(sprintf(
                                "UPDATE requests_feedback SET approved = %d WHERE request_id = %s",
                                $response['approve'], $this->stringToDB($id)
                            ));
                        } else {
                            $this->db->run(sprintf(
                                "INSERT INTO requests_feedback (%s) VALUES (%s)",
                                implode(',', array('request_id', 'user_id', 'approved', 'approve_time', 'counted', 'feedback_source', 'notice_text')),
                                implode(',', array($this->stringToDB($id), $users[$id], (int)$response['approve'], 'now()', 0, $this->stringToDB('control_panel'), "''"))
                            ));
                        }
                    }
                }
                echo(json_encode($response));
	            exit;
            }
        }

		if (isset($_GET['action'])) {

		    if ($_GET['action'] == 'cert_status' && isset($_GET['cert_id'])) {
		        $result = array();
		        if ($cert = $this->db->select(sprintf("SELECT ca_orderNumber, ca_certificateID, status FROM ssl_certs WHERE cert_id = %d", $_GET['cert_id']))) {
		            if (!is_null($cert['status'])) {
                        $context = stream_context_create(array(
                            'http' => array(
                                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                                'method' => 'POST',
                                'content' => http_build_query(array(
                                    'loginName' => cfg::comodo_login,
                                    'loginPassword' => cfg::comodo_password,
                                    'orderNumber' => $cert['ca_orderNumber'],
                                    'queryType' => '0',
                                    'showExtStatus' => 'Y',
                                    'showStatusDetails' => 'Y'
                                ))
                            )
                        ));
                        $response = @file_get_contents('https://secure.comodo.net/products/download/CollectSSL', false, $context);
                        preg_match('/.+([0-9]{3}).+/', $http_response_header[0], $matches);
                        $response_code = $matches[1];
                        $this->db->run(sprintf(
                            "UPDATE ssl_certs SET ca_api_method = %s, ca_api_called = now(), ca_api_return = %s WHERE cert_id = %d",
                            $this->stringToDB('CollectSSL'),
                            $this->stringToDB(($response_code !== '200') ? $response_code : $response_code . "\n" . $response),
                            $_GET['cert_id']
                        ));
                        $response = explode("\n", $response);
                        $this->db->run(sprintf("UPDATE ssl_certs SET status = %d WHERE cert_id = %d", $response[0], $_GET['cert_id']));
                        $result['status'] = (int)$response[0];
                    } else {
		                $result['status'] = 0;
                    }
                } else {
		            $result['status'] = -100;
                }

                echo(json_encode($result));
		        exit;
            }

		    if ($_GET['action'] == 'news_dismiss' && isset($_GET['news_id']) && $this->check_access()) {
		        $news_id = (int)$_GET['news_id'];
                if (is_int($news_id) && $news_id) {
                    $this->db->run(sprintf("INSERT INTO news_views (user_id, news_id) VALUES (%d, %d)", $this->user_info['user_id'], $news_id));
                    apc_delete('news_' . $this->user_info['user_id']);
                }
                exit;
            }

            if ($_GET['action'] == 'news_more' && isset($_GET['news_id']) && $this->check_access()) {
                $news_id = (int)$_GET['news_id'];
                foreach ($this->news as $news) {
                    if ($news['id'] == $news_id) {
                        $this->db->run(sprintf("INSERT INTO news_views (user_id, news_id) VALUES (%d, %d)", $this->user_info['user_id'], $news_id));
                        apc_delete('news_' . $this->user_info['user_id']);
                        header('Location:' . $news['link']);
                        exit;
                    }
                }
                header('Location:/my');
                exit;
            }

            if ($_GET['action'] == 'get_promo_code'){
                $promocode = 'H2MXE';
                $step = (int) $_GET['step'];
                if ($step==5)
                    setcookie('quiz', 1, time() + 365*24*60*60, '/', $this->cookie_domain);
                $promoarr = array();
                for($i=0;$i<$step;$i++)
                    $promoarr[] = $promocode[$i];
                echo json_encode($promoarr);
                exit();
            }

			if (isset($_GET['request_id']) && preg_match("/^[a-f0-9]{32}$/", $_GET['request_id'])) {
				$request_id = $_GET['request_id'];
				// Выбираем id сайта и user_id для данного request_id
				$request_service = $this->db->select(sprintf("select r.request_id, 
																	 r.service_id,
																	 r.user_id,
																	 r.request_type,
																	 s.send_log_to_email
																	 from requests r
																	  left join services s on s.service_id = r.service_id
																	 where r.request_id = %s;", $this->stringToDB($request_id)));
				if (!isset($request_service['request_id'])) {
					echo 'Request issue.';
					exit();
				}
                $this->ajax_request_id = $request_id;
                $this->ajax_service_id = $request_service['service_id'];
                // Если user_id запроса равен текущему пользователю
                // то его и оставляем если же нет - то пользуем дальше User_id
                // делегированного сайта
                if ($request_service['user_id'] == $this->user_id)
                    $this->ajax_user_id = $this->user_id;
                else
                    $this->ajax_user_id = $request_service['user_id'];
            }
            // Выбираем список делегированных сайтов
            $granted_services = $this->get_granted_services($this->user_id);

            // Массив с id делегированных сервисов
            $this->granted_services_ids = array();
            if($granted_services){
                foreach($granted_services as $onegrservice)
                    $this->granted_services_ids[] = $onegrservice['service_id'];
            }
            // Если сайт запроса не принадлежит текущему пользователю
            // и не находится в списке делегированных - до свидания
            if (!$this->check_service_user($this->ajax_service_id, $this->ajax_user_id)
                && !in_array($this->ajax_service_id, $this->granted_services_ids)) {
                echo 'Access issue';
                exit();
            }

            // Проверка - если сайт находится в списке делегированных
            // но у него стоит только право на чтение - до свидания
            if (in_array($this->ajax_service_id, $this->granted_services_ids)
                && !$this->grant_access_level($this->ajax_service_id)) {
                echo 'This site read only.';
                exit();
            }

            switch($_GET['action']) {
                case 'request_feedback':
                    $approve = null;
                    $response = array();
                    $this->get_lang($this->ct_lang, 'Requests');
                    if (isset($_GET['approve']) && preg_match("/^[0|1]$/", $_GET['approve']))
                        $approve = (int) $_GET['approve'];
                    if ($this->write_feedback($approve, $response)){
                        if ($this->test_email) {
                            $response['message'] = sprintf($this->lang['l_feedback_skipped_test_email'], $this->test_email);
                        } else {
                            $email_text = '';
                            if ($request_service['send_log_to_email'] && $request_service['request_type'] == 'contact_enquire') {
                                $email_text = sprintf($this->lang['l_request_marked_email'], $this->user_info['email']);
                            }
                            $response['message'] = sprintf($this->lang['l_request_marked' . $approve], $email_text);
                        }
                    } else {
                        //echo $this->lang['l_feedback_write_failed'];
                        $response['message'] = $this->lang['l_feedback_write_failed'];
                        $this->post_log(__FILE__ . ' line ' . __LINE__ . ': ' . $this->lang['l_feedback_write_failed'].' '.$request_id);
                    }
                    echo(json_encode($response));
                    break;
                case 'delete_request':
                    $result = $this->db->run(sprintf("delete from requests where request_id = %s and user_id = %d;",
                        $this->stringToDB($this->ajax_request_id),
                        $this->ajax_user_id
                    ));

                    if ($result) {
                        $result = json_encode(true);
                    }
                    echo $result;
                    break;
                case 'save_notice':
                    $notice_text = htmlspecialchars($_GET['notice_text']);
                    $notice_text = strip_tags(addslashes($notice_text));
                    $notice_sql = sprintf("update requests_feedback set notice_text = %s 
                                            where request_id = %s
                                            and user_id = %d",
                                            $this->stringToDB($notice_text),
                                            $this->stringToDB($this->ajax_request_id),
                                            $this->ajax_user_id);
                    $this->db->run($notice_sql);
					break;
				case 'update_timezone':
					$ajax_timezone = isset($_GET['timezone']) && preg_match("/^[+\-0-9\,\.]{1,5}$/", $_GET['timezone']) ? $_GET['timezone'] : null;
					if (isset($this->user_info['user_id']) && $ajax_timezone !== null && ($this->user_info['timezone_db'] === null)){
						$this->db->run(sprintf('update users_info set timezone = %s where user_id = %d;', $ajax_timezone, $this->user_info['user_id']));
						$user_sign = sprintf("%s (%d) AJAX",
							$this->user_info['email'],
							$this->user_info['user_id']
						);
						$this->post_log(sprintf(messages::set_new_timezone,
							$ajax_timezone,
							$user_sign
						));
					}
					break;
                case 'get_tour_widget':
                    $result = new stdClass();
                    $id = intval($_POST['id']);
                    if(!empty($id)){
                        if($this->ct_lang == 'ru'){
                            $sql_lang = ' AND articles.widget=2';
                        }else{
                            $sql_lang = ' AND articles.widget=1';
                        }
                        $sql = sprintf('SELECT a.article_id, a.article_title,a.article_content, b.seo_url, 
                                        IFNULL(
                                          (select max(article_id) from articles left join links on article_linkid = links.id where article_id<%1$d AND article_where="widget" %2$s),
                                          (select max(article_id) from articles left join links on article_linkid = links.id where article_id>%1$d AND article_where="widget" %2$s)
                                        ) as prev,
                                        (select widget_url from articles where article_id=prev) as prev_url,
                                        IFNULL(
                                          (select min(article_id) from articles left join links on article_linkid = links.id where article_id>%1$d AND article_where="widget" %2$s),
                                          (select min(article_id) from articles left join links on article_linkid = links.id where article_id<%1$d AND article_where="widget" %2$s)
                                        ) as next,
                                        (select widget_url from articles where article_id=next) as next_url
                                          FROM articles a left join links b
                                          on a.article_linkid = b.id 
                                          where a.article_id=%1$d AND article_where="widget";', $id,$sql_lang);
                        $article = $this->db->select($sql);
                        if($article){
                            if(!empty($article['seo_url'])){
                                $article['seo_url'] = '/help/'.str_replace('-ru', '', $article['seo_url']);
                            }
                            $result->article=$article;
                        }else{
                            $result->error = 'Error! Entry not found.';
                        }
                        // Берем ссылки на отзыв и фильтруем через запрещенные
                        foreach (explode(';', $this->options['review_links']) as $s) {
                            if(!empty($s)){
                                $links = explode(',', $s);
                                $links = array_map('trim', $links);
                                if(isset($links[0]) && isset($links[1]) && !in_array($links[0], explode(",", cfg::skip_cms_for_review))){
                                    $review_links[$links[0]] = $links[1];
                                }
                            }
                        }
                        // Делаем выборку CMS пользователя
                        $sql = sprintf('SELECT engine, max(created) as created FROM services WHERE user_id=%d AND engine!="" GROUP BY engine ORDER BY created DESC;', $this->user_info['user_id']);
                        $results = $this->db->select($sql,true);
                        $links = array();
                        if(is_array($results)){
                            foreach ($results as $row) {
                                // Фильтруем ссылки по CMS пользователя
                                if(in_array($row['engine'], array_keys($review_links)))
                                    $links[] = $review_links[$row['engine']];
                            }
                            // Берем первую ссылку
                            if(!empty($links)){
                                $result->review_link = $links[0];
                                $result->review_host = parse_url($result->review_link, PHP_URL_HOST);
                            }
                        }
                        if( $this->renew_account || $this->user_info['trial'] != 0 ){
                            $result->renew = true;
                        }else{
                            $result->renew = false;
                        }
                    }else{
                        $result->error = 'Error! Empty ID.';
                    }

                    header("Content-type: application/json; charset=UTF-8");
                    echo json_encode($result);
                    break;
                case 'auto_update':
                    $result = new stdClass();
                    $service_id = intval($_GET['service_id']);

                    if(!empty($_GET['all_sites']) || !empty($_GET['auto_update'])){
                        if(!empty($_GET['service']) && $_GET['service']=='security'){
                            $rows = $this->db->select(sprintf("SELECT t.service_id FROM 
                                (SELECT s.service_id, MAX(sa.app_id) as app_id FROM services s
                                LEFT JOIN services_apps sa ON s.service_id=sa.service_id
                                WHERE user_id=%d AND product_id=%d GROUP BY s.service_id) t
                                WHERE t.app_id IS NOT NULL AND t.app_id<(SELECT MAX(app_id) current_version FROM apps WHERE engine='wordpress' AND productive=1 AND product_id=4)",
                                $this->user_info['user_id'], cfg::product_security),true);
                        }else{
                            $rows = $this->db->select(sprintf("SELECT service_id FROM services 
                                WHERE user_id = %d AND engine = 'wordpress' AND product_id = %d and app_id >= 1071 and (SELECT MAX(app_id) current_version FROM apps WHERE engine='wordpress' AND productive=1 AND product_id=1)>app_id",
                                $this->user_info['user_id'], cfg::product_antispam),true);
                        }
                    }else{
                        if(!empty($service_id)){
                            if(!empty($_GET['service']) && $_GET['service']=='security'){
                                $this->create_spbc_task($service_id,'update_plugin','security');
                            }else{
                                $this->create_spbc_task($service_id);
                            }
                            $rows[] = array('service_id'=>$service_id);
                        }
                    }
                    if(isset($rows) && is_array($rows)){
                        foreach ($rows as $row) {
                            if(!empty($_GET['all_sites'])){
                                if(!empty($_GET['service']) && $_GET['service']=='security'){
                                    $this->create_spbc_task($row['service_id'],'update_plugin','security');
                                }else{
                                    $this->create_spbc_task($row['service_id']);
                                }
                            }else{
                                if(!empty($_GET['service']) && $_GET['service']=='security'){
                                    $this->create_spbc_task($service_id,'update_plugin','security');
                                }else{
                                    $this->create_spbc_task($service_id);
                                }
                            }
                            $service_ids[] = $row['service_id'];
                        }
                        if(!empty($_GET['auto_update'])){
                            $this->db->run(sprintf("UPDATE services SET auto_update_app = 1 WHERE user_id = %d AND service_id IN (%s)", $this->user_info['user_id'], implode(',', $service_ids)));
                        }
                        $result->records = count($service_ids);
                    }else{
                        $result->error = 'Error! Service not found.';
                    }
                    header("Content-type: application/json; charset=UTF-8");
                    echo json_encode($result);
                    break;
                case 'delete_antispam':
                    $result = new stdClass();
                    $ids = $_POST['ids'];
                    if(is_array($ids) && !empty($ids)){
                        $ids = array_map('intval', $ids);
                        $result->ids = $ids;
                        $this->db->run(sprintf("delete from services where service_id in (%s) and user_id = %d;", implode(',', $ids), $this->user_id));
                        apc_delete($this->account_label);
                        apc_delete('number_sites_'.$this->user_id);
                        include_once __DIR__ . '/../smarty/libs/plugins/modifier.plural.php';
                        $this->post_log(sprintf(
                            "Пользователь %s удалил %d ".smarty_modifier_plural(count($ids),'сайт','сайтов', 'сайта'),
                            $this->user_info['email'],
                            count($ids)));
                        apc_store('bulk_delete_antispam_'.$this->user_id, count($ids),60);
                    }else{
                        $result->error = 'Error! Service not found.';
                    }
                    header("Content-type: application/json; charset=UTF-8");
                    echo json_encode($result);
                    break;
                case 'add_service':
                    $options = array(
                        'http' => array(
                            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                            'method' => 'POST',
                            'ignore_errors' => true,
                            'content' => http_build_query(array(
                                'email' => $this->user_info['email'], 
                                'user_token' => $this->user_info['user_token'], 
                                'product_name' => $_POST['product_name'],
                                'website' => $_POST['website']
                            ))
                        )
                    );
                    $context = stream_context_create($options);
                    $result = @file_get_contents('https://api.cleantalk.org/?method_name=get_api_key', false, $context);
                    if ($result === false) {
                        print_r($http_response_header);

                        // echo('{"error": true}');
                    } else {
                        echo($result);
                    }
                    break;
                case 'log_api':
                    if(!empty($_POST['api_rq'])){
                        error_log(sprintf("api_rq - %s", $_POST['api_rq']));
                    }
                    if(!empty($_POST['api_rs'])){
                        error_log(sprintf("api_rs - %s", $_POST['api_rs']));
                    }
                    break;
                case 'bill_status':
                    if(!empty($_GET['bill_id']) && !empty($this->user_info['user_id'])){
                        $sql = sprintf('SELECT bill_id, paid FROM bills WHERE bill_id = %d AND fk_user = %d', intval($_GET['bill_id']), $this->user_info['user_id']);
                        $row = $this->db->select($sql);
                        if(!empty($row)){
                            header("Content-type: application/json; charset=UTF-8");
                            echo json_encode($row);
                        }
                    }
                    break;
                case 'check_common_name':
                    if ($_GET['common_name'] != '') echo $this->check_common_name($_GET['common_name']);
                    break;
                case 'scan_favicon':
                    (isset($_GET['start']) && isset($_GET['length'])) ? print_r($this->scan_favicon($_GET['start'], $_GET['length'])) : print_r($this->scan_favicon(0,10));
                    break;
                case 'sort_cookie';
                    if (isset($_GET['sort_security'])) setcookie('sort_security', $_GET['sort_security'], strtotime("+365 day"), '/', $this->cookie_domain);
                    break;
                default:
                    echo 'Error! Unknown AJAX action.';
                    break;
            }
        } else {
            echo 'Error! Unknown AJAX request.';
        }

        exit();
	}

    private function create_spbc_task($service_id, $action = 'update_plugin', $plugin_name = 'anti-spam') {
        // Проверяем наличие созданных, но не обработанных записей
        if ($row = $this->db->select(sprintf("SELECT call_id FROM spbc_remote_calls WHERE user_id = %d AND service_id = %d AND call_action = %s AND notification_sent IS NULL", $this->user_info['user_id'], $service_id, $this->stringToDB($action)))) {
            $this->db->run(sprintf("UPDATE spbc_remote_calls SET created = now() WHERE call_id = %d", $row['call_id']));
        } else {
            $this->db->run(sprintf("INSERT INTO spbc_remote_calls (created, user_id, service_id, call_action, plugin_name) VALUES (now(), %d, %d, %s, %s)",
                $this->user_info['user_id'], $service_id, $this->stringToDB($action), $this->stringToDB($plugin_name)));
        }
    }

    /**
      * Функция записи в БД обратной связи по запросу
      *
      * @param int $approve Признак одобрения запроса
      * @return bool
      */
    function write_feedback($approve = null, &$response = null) {
        $show_app_notification = false;
        $sql = sprintf("SELECT r.sender_email, inet_ntoa(r.sender_ip) as sender_ip, a.app_id, a.last_seen FROM requests r LEFT JOIN services_apps a ON a.service_id = r.service_id WHERE request_id = %s ORDER BY a.last_seen DESC LIMIT 1;",
            $this->stringToDB($this->ajax_request_id)
        );
        $rows = $this->db->select($sql);
        if ($rows['app_id']) {
            $app = $this->db->select(sprintf("SELECT productive, engine FROM apps WHERE app_id = %d", $rows['app_id']));
            if (!$app['productive']) $show_app_notification = sprintf($this->lang['l_app_notification'], $app['engine']);
        }
        if (!is_null($response)) $response['notification'] = $show_app_notification;

        $sql_values_tpl = "(%d, %s, now(), now(), %s, %d)";
        $sql_values = '';
        $sql_values_update = '';
        $record_status = $approve ? 'allow' : 'deny';
        $test_emails = explode(",", $this->options['test_email']);
        foreach ($rows as $k => $v) {
            if (isset($v)) {
                // Пропускаем запись в таблицу тестовых адресов
                if (in_array($v, $test_emails)) {
                    $this->test_email = $v;
                    break;
                }

                $row = $this->db->select(sprintf("select record_id from services_private_list where service_id = %d and record = %s;",
                    $this->ajax_service_id,
                    $this->stringToDB($v)
                ));

                // Если запись существует, то делаем обновление и переходим к следующей записи
                if (isset($row['record_id'])) {
                    $this->db->run(sprintf("update services_private_list set status = %s, updated = now() where record_id = %d;",
                        $this->stringToDB($record_status),
                        $row['record_id']
                    ));
                    continue;
                }

                if ($sql_values != '') {
                    $sql_values = $sql_values . ',';
                }
                $sql_values = $sql_values . sprintf($sql_values_tpl,
                    $this->ajax_service_id,
                    $this->stringToDB($v),
                    $this->stringToDB($record_status),
                    $this->check_private_list_record($v)
                );
            }
        }
        if ($this->test_email) {
            return true;
        }

        if ($sql_values != '') {
            $sql = sprintf("insert into services_private_list (service_id, record, created, updated, status, record_type) values %s;",
                $sql_values
            );
            $this->db->run($sql);
        }

        $row = $this->db->select(sprintf("select request_id from requests_feedback where request_id = %s;", $this->stringToDB($this->ajax_request_id)));
        if (isset($row['request_id'])) {
            if (!$this->db->run(sprintf("update requests_feedback set approved = %d, approve_time = now(), counted = 0, feedback_source = 'control_panel' where request_id = %s;",
                    $approve, $this->stringToDB($this->ajax_request_id)), true))
                return false;
        } else {
            if (!$this->db->run(sprintf("insert into requests_feedback (request_id, user_id, approved, approve_time, counted, feedback_source) values (%s, %d, %d, now(), 0, 'control_panel');",
                        $this->stringToDB($this->ajax_request_id), $this->ajax_user_id, $approve), true))
                return false;
        }

        // Таблица users_feedback_days

        $rowfbdays = $this->db->select(sprintf("select request_id from users_feedback_days where request_id = %s;", $this->stringToDB($this->ajax_request_id)));

        if (isset($rowfbdays['request_id'])) {

            $fbdaysupd = sprintf("update users_feedback_days 
                                  set feedback_datetime = %s
                                  where user_id = %d and request_id = %s",
                                  $this->stringToDB(date('Y-m-d H:i:s')),
                                  $this->ajax_user_id,
                                  $this->stringToDB($this->ajax_request_id));
            $this->db->run($fbdaysupd);
        }
        else {

            $fbdaysins = sprintf("insert into users_feedback_days
                                  (user_id, service_id, request_id, 
                                  feedback_datetime, allow)
                                  values (%d, %d, %s, %s, %d)",
                                    $this->ajax_user_id,
                                    $this->ajax_service_id,
                                    $this->stringToDB($this->ajax_request_id),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    ($approve == 1 ? 1:0));
            $this->db->run($fbdaysins);
        }

        return true;
    }

    /**
      * Функция определяет тип записи - 2 - email
      *
      * 1 - IP
      *
      * @param string $record Запись
      *
      * @return int
      */

    private function check_private_list_record($record){
        $record_type = 0;

        if ($this->valid_email($record))
            $record_type = 2;

        if (filter_var($record, FILTER_VALIDATE_IP)) {
            $record_type = 1;
        }

        return $record_type;

    }

    private function check_common_name($commonName){
        $result = dns_check_record ($commonName, ANY);
        if ($result === true) return 'success';
        return 'error';
    }

    private function scan_favicon($start, $length) {
        if ($this->user_id != null) {
            include_once __DIR__.'/favicon.php';

            $favicons = $this->db->select(sprintf("select favicon_url, service_id, hostname from services where favicon_url REGEXP '^(http).*$' group BY favicon_url LIMIT %d,%d", $start, $length), true);
            $count = $this->db->select(sprintf("select count(*) from services where favicon_url REGEXP '^(http).*$'"));
            print_r('Number of row: '.$count['count(*)'].PHP_EOL);
            $result = [];
            $i = 0;
            foreach ($favicons as $favicon)
            {
                $check_result = Favicon::filter_favicon($favicon['favicon_url']);
                if (substr($check_result, 0,2) != 'Ok') {
                    $result[$i]['service_id'] = $favicon ['service_id'];
                    $result[$i]['hostname'] = $favicon ['hostname'];
                    $result[$i]['path'] = $favicon ['favicon_url'];
                    $result[$i]['check_result'] = $check_result;
                    $i++;
                }
            }
            return $result;
        }
        else return 'Access is denied';
    }
}
?>
