<?

class Hoster extends CCS {
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

	function show_page() {
        $this->call_class_function();
        $this->display();

        return null;
    }
    
    /**
    * Функция управления IP адресом
    */
    function hoster_ip() {
        $this->check_authorize();
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template'] = 'hoster/ip.html';
        $this->page_info['button_label'] = $this->lang['l_add_ip'];
        $this->page_info['jsf_focus_on_field'] = 'ip';
        
        $info = null;
        $ip = null;
        $ip_id = isset($_GET['ip_id']) && preg_match("/^\d+$/", $_GET['ip_id']) ? $_GET['ip_id'] : null;
        if ($ip_id) {
            $ip = $this->db->select(sprintf("select ip_id, ip, hostname from ips where ip_id = %d;", $ip_id));

            if ($ip['ip_id']) {
                $this->page_info['ip'] = $ip;
            }
        }

        $action = isset($_GET['action']) ? $_GET['action'] : 'new';

        switch ($action) {
            case 'new':
                // Проверяем ограничения по лицензии
                if (isset($this->user_info['tariff'])) {
                    $ips = $this->db->select(sprintf("select count(ip_id) as ip_count from ips where user_id=%s", $this->user_info['user_id']));
                    if ($ips['ip_count'] >= $this->user_info['tariff']['services'] || !$this->user_info['license']['moderate']) {
                        $this->page_info['alert_message'] = sprintf($this->lang['l_switch_license'], $ips['ip_count'], $this->user_info['tariff']['services']);
                        
                        // подберём подходящий тариф для обновления
                        $tariffs = $this->db->select(sprintf("select %s from tariffs where product_id=%d and services>%d order by services",
                            'tariff_id, cost_usd, services', $this->user_info['tariff']['product_id'], $ips['ip_count']), true);
                        if (count($tariffs)) {
                            $new_tariff = $tariffs[0];
                            $this->page_info['tariff_title'] = sprintf($this->lang['l_switch_license_title'], $new_tariff['services'], round($new_tariff['cost_usd']));
                            $this->page_info['tariff_limit'] = $new_tariff['services'];
                            $this->page_info['tariff_id'] = $new_tariff['tariff_id'];
                            $this->page_info['current_limit'] = $this->user_info['tariff']['services'];
                            $ips = $this->db->select(sprintf("select * from ips where user_id=%d", $this->user_info['user_id']), true);
                            $ips_title = array();
                            foreach ($ips as $v) {
                                $ips_title[] = $v['ip'] . ($v['hostname'] ? sprintf('(%s)', $v['hostname']) : '');
                            }
                            $this->page_info['current_ips'] = implode('<br>', $ips_title);
                        }
                        
                        $this->display('hoster/ip-limit.html');
                        return;
                    }
                }
                $this->page_info['update_service'] = true;
                break;
            case 'delete':
                if (!isset($ip['ip'])) {
                    break;
                }
                $this->page_info['confirm_delete'] = sprintf($this->lang['l_confirm_delete_ip_address'], $ip['ip']);
                break;
            case 'deleted':
                $this->page_info['ip_updated'] = sprintf($this->lang['l_ip_address_deleted']);
                $this->page_info['header']['refresh'] = '3; /my';
                break;
            case 'edit':
                $this->page_info['update_service'] = true;
                $this->page_info['button_label'] = $this->lang['l_save_settings'];
                break;
            case 'created':
                if (!isset($ip['ip'])) {
                    break;
                }
                $this->page_info['ip_updated'] = sprintf($this->lang['l_ip_created'], $ip['ip'], $ip['hostname']);
                break;
            default: break;
        }

        $errors = null;
        $post_action = false;
        if (count($_POST)) {
            $tools = new CleanTalkTools();
            $info = $this->safe_vars($_POST);
            
            if (isset($info['ip'])) {
                if (filter_var($info['ip'], FILTER_VALIDATE_IP)) {
                    $row = $this->db->select(sprintf("select ip_id from ips where user_id = %d and ip = %s;",
                        $this->user_id,
                        $this->stringToDB($info['ip'])
                    ));
                    
                    // Не даем сохранить дубль
                    if (isset($row['ip_id'])) {
                        if ($action != 'edit' || $row['ip_id'] != $info['ip_id']) {
                            $errors[] = sprintf($this->lang['l_double_ip'], $info['ip']);
                        }
                    } else {
                        // Проверяем наличие IP адреса у другого пользователя
                        if ($row = $this->db->select(sprintf("select ip_id from ips where ip = %s", $this->stringToDB($info['ip'])))) {
                            $errors[] = sprintf($this->lang['l_double_ip_global'], $info['ip']);
                        }
                    }
                } else {
                    $errors[] = $this->lang['l_ip_address_error'];
                }
            }

            if (!isset($info['hostname']) || $info['hostname'] == '' || $info['hostname'] === false) {
                $info['hostname'] = null;
            } else {
                $hostname = $tools->get_domain($info['hostname']);
                if ($hostname === null)
                    $info['hostname'] = null;
                else
                    $info['hostname'] = $hostname;
            }
            
            if ($info['hostname']) {
                $info['hostname'] = idn_to_utf8($info['hostname']);
            }
            $post_action = true;
        } else {
            $info = $ip;
        }
        $this->page_info['info'] = $info;

        if ($errors) {
            $this->page_info['errors'] = $errors;
            $post_action = false;
        }

        if ($post_action) {
            switch ($action) {
                case 'edit':
                    if (!isset($ip['ip_id'])) {
                        break;
                    }
                    $this->db->run(sprintf("update ips set ip = %s, hostname = %s, updated = now() where ip_id = %d;",
                        $this->stringToDB($info['ip']),
                        $this->stringToDB($info['hostname']),
                        $ip['ip_id']
                    ));
                    $this->post_log(sprintf("Обновлен ip %s пользователя %s (%d).", $info['ip'], $this->user_info['email'], $this->user_id));
                    
                    $this->url_redirect('hoster-ip?ip_id=' . $ip['ip_id'] . '&action=edit&updated=1');
                break;
                case 'new':
                    $ip_id = $this->db->run(sprintf("insert into ips (ip, hostname, submited, updated, user_id) values (%s, %s, now(), now(), %d);",
                        $this->stringToDB($info['ip']),
                        $this->stringToDB($info['hostname']),
                        $this->user_id
                    ));

                    $this->post_log(sprintf("Добавлен IP %s пользователя %s (%d).", $info['ip'], $this->user_info['email'], $this->user_id));
                    
                    $this->url_redirect('hoster-ip?ip_id=' . $ip_id . '&action=created');
                case 'delete':
                    if (!isset($ip['ip_id'])) {
                        break;
                    }
                    $this->db->run(sprintf("delete from ips where ip_id = %d;",
                        $ip['ip_id']
                    ));

                    $this->post_log(sprintf("Удален IP %s пользователя %s (%d).", $ip['ip_id'], $this->user_info['email'], $this->user_id));
                    
                    $this->url_redirect('hoster-ip?ip_id=' . $ip_id . '&action=deleted');
                break;
            }
        
        }

        return null;
    }
}
