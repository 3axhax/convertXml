<?php



if (!$this->check_access(null, true)) {
    if (!$this->app_mode) {
        $this->url_redirect('session', null, true);
    }
} else {
    $this->check_authorize();
    $this->page_info['bsdesign'] = true;

    /*
        Формируем список дополнений.
    */
    $this->get_addon('countries_stop_list');

    /*
        Общие данные.
    */
    $record_type_service = array(
        'antispam' => array(1, 2, 3, 4, 5, 7, 8),
        'spamfirewall' => array(6),
        'securityfirewall' => array(1,3,7)
    );
    $record_type_labels = array(
        1 => $this->lang['l_record_type_ip'],
        2 => $this->lang['l_record_type_email'],
        3 => $this->lang['l_record_type_country'],
        4 => $this->lang['l_record_type_domain'],
        5 => $this->lang['l_record_type_domain'],
        6 => $this->lang['l_record_type_network'],
        7 => $this->lang['l_record_type_network'],
        8 => $this->lang['l_record_type_stop_word'],
    );
    if(isset($this->user_info['licenses']['antispam']) && $this->user_info['licenses']['antispam']['trial']==1 && $this->user_info['licenses']['antispam']['moderate']==1){
        $this->page_info['stop_word_enabled'] = true;
    }else{
        $this->page_info['stop_word_enabled'] = false;
    }
    if(is_array($this->user_info['addons'])){
        foreach ($this->user_info['addons'] as $addon) {
            if( $addon['addon_id']==2 && $addon['enabled']==1 ){
                $this->page_info['stop_word_enabled'] = true;
            }
        }
    }
    $networks_rts = array(6, 7);
    $security_ips_rts = array(1);
    $record_type_service_display = array();
    foreach ($record_type_service as $k => $v) {
        $record_type_service_display[$k] = $this->lang['l_record_service_' . $k];
    }
    $this->page_info['show_record_type'] = $this->is_admin || cfg::show_sfw_records ? true : false;
    $this->page_info['record_type_service_display'] = $record_type_service_display;
    $this->page_info['record_type_labels'] = $record_type_labels;
    $this->page_info['record_type_service'] = $record_type_service;

    // Данные по лицензиям
    $this->get_lang($this->ct_lang, 'Security');
    $licenses = array('antispam' => false, 'api' => false, 'hosting-antispam' => false, 'security' => false);
    if (isset($this->user_info['licenses']) && count($this->user_info['licenses'])) {
        foreach ($this->user_info['licenses'] as $key =>$license) {
            if ($key == 'ssl') continue;
            $licenses[$key] = $license['moderate'] ? true : false;
        }
    }
    $this->page_info['licenses_moderate'] = json_encode($licenses);
    $this->page_info['licenses'] = $this->user_info['licenses'];
    $this->page_info['modal_license'] = '/my/bill/security';
    $this->page_info['head']['title'] = $this->lang['l_black_white_lists_title'];
    $this->page_info['show_datepicker'] = true;
    $this->page_info['show_hint'] = true;

    $uri = '';
    $service_type = null;
    if (isset($_GET['service_type']) && isset($record_type_service[$_GET['service_type']])) {
        $uri .= $uri == '' ? '?' : '&';
        $uri .= 'service_type=' . $_GET['service_type'];
        $service_type = $_GET['service_type'];
    }
    if (isset($_GET['service_id']) && (preg_match("/^\d+$/", $_GET['service_id']) || $_GET['service_id']=='all')) {
        $uri .= $uri == '' ? '?' : '&';
        $uri .= 'service_id=' . $_GET['service_id'];
        $this->service_id = $_GET['service_id'];
    }
    if (isset($_GET['status']) && in_array($_GET['status'], array('allow', 'deny'))) {
        $uri .= $uri == '' ? '?' : '&';
        $uri .= 'status=' . $_GET['status'];
    }
    if (isset($_GET['record_type'])) {
        $uri .= $uri == '' ? '?' : '&';
        $uri .= 'record_type=' . $_GET['record_type'];
        $this->page_info['record_type'] = $_GET['record_type'];
    }

    if (isset($_COOKIE['showPrivateSort'])) {
        $sort = explode('|', $_COOKIE['showPrivateSort']);
        switch ($sort[1]) {
            case 'down':
                $this->page_info['sort'] = array('field' => $sort[0], 'class' => 'sort-down');
                break;
            case 'up':
                $this->page_info['sort'] = array('field' => $sort[0], 'class' => 'sort-up');
                break;
        }
    }
    if (count($_POST) || (isset($_GET['action']) && $_GET['action'] == 'add_record')) {
        $service_id = $this->service_id;
        $note = (isset($_POST['note'])) ? $this->stringToDB(substr(str_replace(array("'", '\\'), '', $_POST['note']), 0, 100)) : 'NULL';
        if (isset($_POST['service_id']))
            $service_id = preg_replace('/[^0-9]/i', '', $_POST['service_id']);
        // Если сайт принадлежит текущему пользователю
        // или находится в списке делегированных сайтов
        if ($this->check_service_user($service_id, $this->user_id) 
            || in_array($service_id, $this->granted_services_ids) 
            || isset($_POST['service_id']) && $_POST['service_id'] == 'all'
            || isset($_POST['action']) && $_POST['action']=='delete_record' && isset($_POST['records'])) {
            // Проверка - если сайт в списке делегированных но только с правом на чтение
            // не даём проводить никакие действия
            if (in_array($service_id, $this->granted_services_ids)
                && !$this->grant_access_level($service_id)
            )
                $this->redirect($_SERVER['HTTP_REFERER']);
            $product_id = $this->db->select(sprintf("select product_id, stop_list_enable from services where service_id=%d", $service_id));
            $services_stop_list_enable = $product_id['stop_list_enable'];
            $product_id = $product_id['product_id'];            
            if (!$product_id) $product_id = cfg::product_antispam;
            // Добавление записи
            switch ($service_type) {
                case 'antispam':
                case 'spamfirewall':
                    $sql_product_id = sprintf(" and product_id=%d",cfg::product_antispam);
                    break;
                case 'securityfirewall':
                    $sql_product_id = sprintf(" and product_id=%d", cfg::product_security);
                    break;
            }
            if ($_POST['action'] == 'add_record') {
                if (isset($_POST['add_record_type']) && $_POST['add_record_type']=='8' && !empty($_POST['add_record']) && $this->page_info['stop_word_enabled']) {// Добавление стоп слов
                    $num_added_records = (int)apc_fetch('added_records_' . $this->user_info['user_id']);
                    $num_exist_records = (int)apc_fetch('exist_records_' . $this->user_info['user_id']);

                    $recordstatus = ($_POST['add_status'] == 'allow' ? 'allow' : 'deny');                        
                    $records = explode(',', $_POST['add_record']);
                    $records = array_map('mysql_real_escape_string', $records);
                    $records = array_map('trim', $records);

                    if (isset($_POST['service_id']) && $_POST['service_id'] == 'all') {
                        $allmainservices = $this->db->select(sprintf('select service_id, product_id, stop_list_enable from services where user_id = %d %s', $this->user_id, $sql_product_id), true);
                    }else{
                        $allmainservices[] = array('service_id'=>$service_id,'product_id'=>$product_id,'stop_list_enable'=>$services_stop_list_enable);
                    }
                    foreach ($allmainservices as $acaddservice) {
                        if(empty($acaddservice['stop_list_enable']) && $this->page_info['stop_word_enabled']){ // Включаем стоп-слова для сайта
                            $sql_update = sprintf("UPDATE services SET stop_list_enable=1 WHERE service_id=%d;",$acaddservice['service_id']);
                            $this->db->run($sql_update, true);
                        }
                        foreach ($records as $word) {
                            $sql = sprintf('SELECT service_id FROM services_private_list WHERE service_id = %d AND record = %s AND product_id = %d',
                                $this->intToDB($acaddservice['service_id']),
                                $this->stringToDB('=' . $word),
                                $this->intToDB($acaddservice['product_id'])
                            );
                            if($this->db->select($sql)){
                                $num_exist_records++;
                                continue;
                            }
                            $insertsql = sprintf("insert into services_private_list 
                                                      (service_id, record, created, updated, status, record_type, product_id, note)
                                                    values (%d, %s, %s, %s, %s, %d, %d, %s);",
                                $acaddservice['service_id'],
                                $this->stringToDB('=' . $word),
                                $this->stringToDB(date('Y-m-d H:i:s')),
                                $this->stringToDB(date('Y-m-d H:i:s')),
                                $this->stringToDB($recordstatus),
                                $this->intToDB(8),
                                $this->intToDB($acaddservice['product_id']),
                                $note
                            );
                            $insert_result = $this->db->run($insertsql, true);
                            if($insert_result){
                                $num_added_records++;
                            }else{
                                $num_exist_records++;
                            }
                        }
                    }
                    apc_store('added_records_' . $this->user_info['user_id'], $num_added_records);
                    apc_store('exist_records_' . $this->user_info['user_id'], $num_exist_records);
                    if(count($records)>5){
                        $this->post_log(sprintf(
                            'Пользователь %s (%d) добавил в персональные списки, %d стоп слов, статус - %s',
                            $this->user_info['email'], $this->user_info['user_id'], count($records), $recordstatus
                        ));
                    }else{
                        $this->post_log(sprintf(
                            'Пользователь %s (%d) добавил стоп слова в персональные списки: %s, статус - %s',
                            $this->user_info['email'], $this->user_info['user_id'], implode(', ', $records), $recordstatus
                        ));
                    }
                    if(empty($_POST['ajax'])){
                        $this->redirect('/my/show_private' . $uri);
                    }else{
                        exit;
                    }
                // Добавление стран 
                } elseif ((isset($_POST['add_countries']) && $_POST['add_countries'] == 1) || isset($_POST['selected-countries'])) {
                    $num_added_records = (int)apc_fetch('added_records_' . $this->user_info['user_id']);
                    $num_exist_records = (int)apc_fetch('exist_records_' . $this->user_info['user_id']);
                    if (isset($_POST['countries_status']))
                        $recordstatus = ($_POST['countries_status'] == 'allow' ? 'allow' : 'deny');
                    $countries = $this->get_lang_countries();
                    $countrycodes = array();
                    foreach ($countries as $onecountry)
                        $countrycodes[] = $onecountry['countrycode'];
                    if (isset($_POST['countries_status']))
                        $recordstatus = ($_POST['countries_status'] == 'allow' ? 'allow' : 'deny');

                    if(is_array($_POST['selected-countries'])){
                        $add_items = $_POST['selected-countries'];
                        if($_POST['countries_status']=='deny_except' || $_POST['countries_status']=='allow_except'){
                            foreach ($countries as $country) {
                                if(!in_array($country['countrycode'], $add_items)){
                                    $except_items[] = $country['countrycode'];
                                }
                            }
                            $add_items = $except_items;
                            if($_POST['countries_status']=='allow_except'){
                                $recordstatus = 'allow';
                            }
                            if($_POST['countries_status']=='deny_except'){
                                $recordstatus = 'deny';
                            }
                        }
                    }else{
                        $add_items = $_POST;
                    }
                    // Добавляем страны из Аналитики для всех стран
                    if (isset($_POST['service_id']) && $_POST['service_id'] == 'all') {
                        $allmainservices = $this->db->select(sprintf('select service_id, product_id from services
                                                      where user_id = %d %s', $this->user_id, $sql_product_id), true);

                        $alladdservices = array();
                        foreach ($allmainservices as $onemainservice)
                            $alladdservices[] = $onemainservice['service_id'];
                        foreach ($allmainservices as $acaddservice) {
                            foreach ($add_items as $onepostentry) {
                                if (in_array($onepostentry, $countrycodes)) {
                                    $sql = sprintf('SELECT service_id FROM services_private_list WHERE service_id = %d AND record = %s AND product_id = %d',
                                        $this->intToDB($acaddservice['service_id']),
                                        $this->stringToDB('country_' . $onepostentry),
                                        $this->intToDB($acaddservice['product_id'])
                                    );
                                    if($this->db->select($sql)){
                                        $num_exist_records++;
                                        continue;
                                    }
                                    $insertsql = sprintf("insert into services_private_list 
                                                  (record_id, service_id, record, created, updated, status, record_type, product_id, note)
                                                values (NULL, %d, %s, %s, %s, %s, %d, %d, %s);",
                                        $acaddservice['service_id'],
                                        $this->stringToDB('country_' . $onepostentry),
                                        $this->stringToDB(date('Y-m-d H:i:s')),
                                        $this->stringToDB(date('Y-m-d H:i:s')),
                                        $this->stringToDB($recordstatus),
                                        $this->intToDB(3),
                                        $this->intToDB($acaddservice['product_id']),
                                        $note
                                    );
                                    $insert_result = $this->db->run($insertsql, true);
                                    if ($acaddservice['product_id'] == cfg::product_security) {
                                        $this->create_spbc_task($acaddservice['service_id']);
                                    } else if ($acaddservice['product_id'] == cfg::product_antispam && isset($_GET['service_type']) && $_GET['service_type'] == 'spamfirewall') {
                                        $this->create_spbc_task($acaddservice['service_id'], 'sfw_update', 'anti-spam');
                                    }
                                    if($insert_result){
                                        $num_added_records++;
                                    }else{
                                        $num_exist_records++;
                                    }
                                }
                            }
                        }
                    } else {
                        foreach ($add_items as $onepostentry) {
                            if (in_array($onepostentry, $countrycodes)) {
                                $sql = sprintf('SELECT service_id FROM services_private_list WHERE service_id = %d AND record = %s AND product_id = %d',
                                    $this->intToDB($service_id),
                                    $this->stringToDB('country_' . $onepostentry),
                                    $this->intToDB($product_id)
                                );
                                if($this->db->select($sql)){
                                    $num_exist_records++;
                                    continue;
                                }
                                $insertsql = sprintf("insert into services_private_list 
                                             (record_id, service_id, record, created, updated, status, record_type, product_id, note)
                                            values (NULL, %d, %s, %s, %s, %s, %d, %d, %s);",
                                    $service_id,
                                    $this->stringToDB('country_' . $onepostentry),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    $this->stringToDB($recordstatus),
                                    $this->intToDB(3),
                                    $this->intToDB($product_id),
                                    $note
                                );
                                $insert_result = $this->db->run($insertsql);
                                if ($product_id == cfg::product_security) {
                                    $this->create_spbc_task($service_id);
                                } else if ($product_id == cfg::product_antispam && isset($_GET['service_type']) && $_GET['service_type'] == 'spamfirewall') {
                                    $this->create_spbc_task($service_id, 'sfw_update', 'anti-spam');
                                }
                                if($insert_result){
                                    $num_added_records++;
                                }else{
                                    $num_exist_records++;
                                }
                            }
                        }
                    }
                    apc_store('added_records_' . $this->user_info['user_id'], $num_added_records);
                    apc_store('exist_records_' . $this->user_info['user_id'], $num_exist_records);
                    if(count($add_items)>5){
                        $this->post_log(sprintf(
                            'Пользователь %s (%d) добавил страны в персональные списки, %d записей, статус - %s',
                            $this->user_info['email'], $this->user_info['user_id'], count($add_items), $recordstatus
                        ));
                    }else{
                        $this->post_log(sprintf(
                            'Пользователь %s (%d) добавил страны в персональные списки: %s, статус - %s',
                            $this->user_info['email'], $this->user_info['user_id'], implode(', ', $add_items), $recordstatus
                        ));
                    }
                    // Если пришли со страницы аналитики то туда же и
                    // возвращаемся с сообщением
                    if(empty($_POST['ajax'])){
                        if (strstr($_SERVER['HTTP_REFERER'], 'stat')) {
                            setcookie('show_stat_message', 1, time() + 300, '/', $this->cookie_domain);
                            $this->redirect($_SERVER['HTTP_REFERER']);
                        } else {
                            $this->redirect('/my/show_private' . $uri);
                        }
                    }else{
                        exit;
                    }
                }
                // Если добавляем запись по всем сайтам
                // выбираем все сайты пользователя
                if (isset($_POST['service_id']) && $_POST['service_id'] == 'all') {
                    $allmainservices = $this->db->select(sprintf('select service_id, product_id from services
                                                      where user_id = %d %s', $this->user_id, $sql_product_id), true);
                    $allgrantedservices = $this->get_granted_services($this->user_id);
                    // Массив содержит в себе service_id's всех сайтов пользователя
                    // основных и делегированных
                    $alladdservices = array();
                    foreach ($allmainservices as $onemainservice)
                        $alladdservices[] = array($onemainservice['service_id'], $onemainservice['product_id']);
                    foreach ($allgrantedservices as $onegrantedservice) {
                        // Добавляем только те делегированные сайты
                        // у которых стоит разрешение на запись
                        if ($onegrantedservice['grantwrite'] == 1)
                            $alladdservices[] = array($onegrantedservice['service_id'], $onegrantedservice['product_id']);
                    }

                }
                // Конец блока добавления стран
                // Добавление IP email списка email и IP
                $record = preg_replace('/\s+/', ' ', $_POST['add_record']);
                $addrecords = array();
                if (strpos($record, ';'))
                    $addrecords = explode(";", $record);
                elseif (strpos($record, ','))
                    $addrecords = explode(",", $record);
                elseif (strpos($record, ' '))
                    $addrecords = explode(' ', $record);
                elseif (strpos($record, "\n"))
                    $addrecords = explode("\n", $record);
                else
                    $addrecords[] = $record;
				$num_added_records = (int)apc_fetch('added_records_' . $this->user_info['user_id']);
                $num_exist_records = (int)apc_fetch('exist_records_' . $this->user_info['user_id']);
                $num_wrong_records = (int)apc_fetch('wrong_records_' . $this->user_info['user_id']);
                foreach ($addrecords as $i => $oneaddrecord) {
                    $record_type = 0;
                    if (($record_type = $this->check_private_list_record(trim($oneaddrecord), $service_type)) || $_POST['add_record_type']==2) {
                        if(!$record_type && $_POST['add_record_type']==2){
                            if (!preg_match("/^[^@]{1,64}@[^@]{1,255}$/", $oneaddrecord)) {
                                $num_wrong_records++;
                                continue;
                            }
                            $email_array = explode("@", $oneaddrecord);
                            if (!preg_match("/^[A-Za-z0-9!#$%&#038;'*+\/\=?^_`{|}~\.-]+$/", $email_array[0])) {
                                $num_wrong_records++;
                                continue;
                            }
                            if (!preg_match("/^[A-Za-z0-9-\.\*]+$/", $email_array[1])) {
                                $num_wrong_records++;
                                continue;
                            }
                            $record_type = 2;
                        }

                        // Для Security FireWall не пропускаем email и domain
                        if (isset($service_type) && $service_type == 'securityfirewall') {
                            if (in_array($record_type, array(2, 4, 5, 3))){
                                $num_wrong_records++;
                                continue;
                            }
                        }

                        // Добавляем префикс дабы сделать запись совместимое с БД.
                        if (in_array($record_type, $networks_rts) && !preg_match("/\/\d{1,2}$/", $oneaddrecord)) {
                            $oneaddrecord .= '/32';
                        }

                        // Преобразуем IP в сеть для SFW записей.
                        if (in_array($record_type, $networks_rts)) {
                            $oneaddrecord = $this->get_network_record($oneaddrecord);
                        }

						if ($record_type == 4 && $this->private_list_domain !== null) {
							$oneaddrecord = $this->private_list_domain;
						}
                        if (isset($matches[0]))
                            $oneaddrecord = $matches[0];
                        $oneaddrecord = str_ireplace(array('insert', 'delete', 'drop', 'update', 'select'), '', $oneaddrecord);
                        if (isset($_POST['add_status']))
                            $recordstatus = ($_POST['add_status'] == 'allow' ? 'allow' : 'deny');
                        if (isset($_POST['service_id']) && $_POST['service_id'] == 'all') {
                            foreach ($alladdservices as $oneallservice) {
                                $sql = sprintf('SELECT service_id FROM services_private_list WHERE service_id = %d AND record = %s AND product_id = %d AND record_type = %d',
                                    $this->intToDB($oneallservice[0]),
                                    $this->stringToDB(strtolower(trim($oneaddrecord))),
                                    $this->intToDB($oneallservice[1]),
                                    $this->intToDB($record_type)
                                );
                                if($this->db->select($sql)){
                                    $num_exist_records++;
                                    continue;
                                }
                                $insertsql = sprintf("insert into services_private_list 
                                          (record_id, service_id, record, created, updated, status, record_type, product_id, note)
                                          values (NULL, %d, %s, %s, %s, %s, %d, %d, %s);",
                                    $oneallservice[0],
                                    $this->stringToDB(strtolower(trim($oneaddrecord))),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    $this->stringToDB($recordstatus),
                                    $this->intToDB($record_type),
                                    $oneallservice[1],
                                    $note
								);
                                $insert_result = $this->db->run($insertsql);
                                if ($oneallservice[1] == cfg::product_security) {
                                    $this->create_spbc_task($oneallservice[1]);
                                } else if ($oneallservice[1] == cfg::product_antispam && isset($_GET['service_type']) && $_GET['service_type'] == 'spamfirewall') {
                                    $this->create_spbc_task($oneallservice[1], 'sfw_update', 'anti-spam');
                                }
                                if($insert_result){
                                    $num_added_records++;
                                    $this->post_log(sprintf(
                                        'Пользователь %s (%d) добавил запись %s в персональные списки.',
                                        $this->user_info['email'], $this->user_info['user_id'], $oneaddrecord
                                    ));
                                }else{
                                    $num_exist_records++;
                                }
                            }

                        } else {
                            $sql = sprintf('SELECT service_id FROM services_private_list WHERE service_id = %d AND record = %s AND product_id = %d AND record_type = %d',
                                $this->intToDB($service_id),
                                $this->stringToDB(strtolower(trim($oneaddrecord))),
                                $this->intToDB($product_id),
                                $this->intToDB($record_type)
                            );
                            if($this->db->select($sql)){
                                $num_exist_records++;
                            }else{
                                $insertsql = sprintf("insert into services_private_list 
                                              (record_id, service_id, product_id, record, created, updated, status, record_type, note)
                                              values (NULL, %d, %d, %s, %s, %s, %s, %d, %s);",
                                    $service_id,
                                    $product_id,
                                    $this->stringToDB(strtolower(trim($oneaddrecord))),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    $this->stringToDB(date('Y-m-d H:i:s')),
                                    $this->stringToDB($recordstatus),
                                    $this->intToDB($record_type),
                                    $note
                                );
                                $insert_result = $this->db->run($insertsql, true);
                                if ($product_id == cfg::product_security) {
                                    $this->create_spbc_task($service_id);
                                } else if ($product_id == cfg::product_antispam && isset($_GET['service_type']) && $_GET['service_type'] == 'spamfirewall') {
                                    $this->create_spbc_task($service_id, 'sfw_update', 'anti-spam');
                                }
                                if($insert_result){
                                    $num_added_records++;
                                    $this->post_log(sprintf(
                                        'Пользователь %s (%d) добавил запись %s в персональные списки.',
                                        $this->user_info['email'], $this->user_info['user_id'], $oneaddrecord
                                    ));
                                }
                            }
                        }
                        
                    }else{
                        $num_wrong_records++;
                    }
                    // Ограничение в 1000 записей
                    if ($i > 1000)
                        break;
                }
                apc_store('added_records_' . $this->user_info['user_id'], $num_added_records);
                apc_store('exist_records_' . $this->user_info['user_id'], $num_exist_records);
                apc_store('wrong_records_' . $this->user_info['user_id'], $num_wrong_records);

                //Данные для аякс запроса
                if(!empty($_POST['ajax'])){
                    if($_POST['ajax']=='progress'){
                        header("Content-type: application/json; charset=UTF-8");
                        echo json_encode(['result'=>'ok']);
                        exit();
                    }else{
                        // Вытаскиваем количество добавленных записей
                        $num_added_records = apc_fetch('added_records_' . $this->user_info['user_id']);
                        if ($num_added_records) {
                            $result['addedrecords'] = sprintf($this->lang['l_records_added'], $num_added_records);
                            apc_delete('added_records_' . $this->user_info['user_id']);
                        }
                        // Вытаскиваем количество существующих записей
                        $num_exist_records = apc_fetch('exist_records_' . $this->user_info['user_id']);
                        if ($num_exist_records) {
                            $result['existrecords'] = sprintf($this->lang['l_records_exist'], $num_exist_records);
                            apc_delete('exist_records_' . $this->user_info['user_id']);
                        }
                        // Вытаскиваем количество ошибочных записей
                        $num_wrong_records = apc_fetch('wrong_records_' . $this->user_info['user_id']);
                        if ($num_wrong_records) {
                            $result['wrongrecords'] = sprintf($this->lang['l_records_wrong'], $num_wrong_records);
                            apc_delete('wrong_records_' . $this->user_info['user_id']);
                        }
                        header("Content-type: application/json; charset=UTF-8");
                        echo json_encode($result);
                        exit();
                    }
                }

                // Если пришли со страницы аналитики то туда же и
                // возвращаемся с сообщением
                if (strstr($_SERVER['HTTP_REFERER'], 'stat')) {
                    setcookie('show_stat_message', 1, time() + 300, '/', $this->cookie_domain);
                    $this->redirect($_SERVER['HTTP_REFERER']);
                } else {
                    //$this->redirect($_SERVER['HTTP_REFERER']);
                    $this->redirect('/my/show_private' . $uri);
                }

                 
            }
            switch ($_POST['action']) {
                case 'note':
                    $record_id = preg_replace('/[^0-9]/i', '', $_POST['record_id']);
                    $this->db->run(sprintf("UPDATE services_private_list SET note = %s WHERE record_id = %d",
                        $note, $record_id));
                    $this->redirect($_SERVER['HTTP_REFERER']);
                    break;
                case 'change_status' : {
                    $record_id = preg_replace('/[^0-9]/i', '', $_POST['record_id']);
                    $status = ($_POST['status'] == 1 ? 'allow' : 'deny');
                    $changesql = sprintf("update services_private_list 
                                  set status = %s
                                  where record_id = %d",
                        $this->stringToDB($status),
                        $record_id);
                    $this->db->run($changesql);
                    if ($product_id == cfg::product_security) {
                        $this->create_spbc_task($service_id);
                    }
                    break;
                }
				case 'delete_record' : {
                    if(isset($_POST['records']) && is_array($_POST['records'])){ //Delete multiple records
                        $records = $this->db->select(sprintf("select record_id, service_id, product_id from services_private_list where record_id IN (%s);",
                            implode(',', array_map('intval',$_POST['records']))
                        ),true);
                        foreach ($records as &$record) {
                            if($this->check_service_user($record['service_id'], $this->user_id) || in_array($record['service_id'], $this->granted_services_ids)){
                                $del_records_ids[]=$record['record_id'];
                                if ($record['product_id'] == cfg::product_security) {
                                    $this->create_spbc_task($record['service_id']);
                                } else if ($record['product_id'] == cfg::product_antispam) {
                                    $this->create_spbc_task($record['service_id'], 'sfw_update', 'anti-spam');
                                }
                            }
                        }
                        if(!empty($del_records_ids)){
                            $deletesql = sprintf("delete from services_private_list where record_id IN (%s)", implode(',', $del_records_ids));
                            $this->db->run($deletesql);
                            $this->post_log(sprintf(
                                'Пользователь %s (%d) удалил %d записей из персональных списков.',
                                $this->user_info['email'], $this->user_info['user_id'], count($del_records_ids)
                            ));
                            echo json_encode(array('records' => $del_records_ids));
                        }

                    }else{ //Delete single record
                        $record_id = preg_replace('/[^0-9]/i', '', $_POST['record_id']);						
						if (!$record_id) {
							break;
						}
						$record = $this->db->select(sprintf("select record from services_private_list where record_id = %d;",
							$record_id
						));
						if (!isset($record['record'])) {
							break;
						}

                        $deletesql = sprintf("delete from services_private_list 
                                      where record_id = %d",
                            $record_id);
                        $this->db->run($deletesql);
                        $this->post_log(sprintf(
                            'Пользователь %s (%d) удалил запись %s (%d) из персональных списков.',
							$this->user_info['email'], $this->user_info['user_id'], 
							$record['record'],
							$_POST['record_id']
                        ));
                        if ($product_id == cfg::product_security) {
                            $this->create_spbc_task($service_id);
                        } else if ($product_id == cfg::product_antispam && isset($_POST['service_type']) && $_POST['service_type'] == 'spamfirewall') {
                            $this->create_spbc_task($service_id, 'sfw_update', 'anti-spam');
                        }
                    }
                    break;
                }
                case 'upload_csv' : {
                    $upload_limit = 25;
					$tmp_name = isset($_FILES["upfile"]["tmp_name"]) ? $_FILES["upfile"]["tmp_name"] : false;

                    if (!$tmp_name){
                        $file_path = apc_fetch('upload_csv_'.$this->user_info['user_id']);
                        if(!$file_path){
                            exit;
                        }
                    }else{
                        $name = rand(0, 1000000) . '_' . rand(0, 1000000) . '.csv';
                        $tmp_dir = sys_get_temp_dir();
                        $file_path = "$tmp_dir/$name";
                        move_uploaded_file($tmp_name, $file_path);
                        $file_content = file_get_contents($file_path);
                        file_put_contents($file_path, str_replace(array(';'), ',', $file_content));
                    }

					$recordsdate = date('Y-m-d H:i:s');
                    if (($handle = fopen($file_path, "r")) !== FALSE) {
                        // Переходим на заданную позицию, если импортируем не сначала
                        if($from = (int)$_POST['from'])
                            fseek($handle, $from);

                        $num_added_records = (int)apc_fetch('added_records_' . $this->user_info['user_id']);
                        $num_exist_records = (int)apc_fetch('exist_records_' . $this->user_info['user_id']);
                        $num_wrong_records = (int)apc_fetch('wrong_records_' . $this->user_info['user_id']);
                        $sql = sprintf("select service_id, product_id from services where user_id = %d %s", 
                            $this->user_id,
                            $sql_product_id
                        );
                        $service_id = (int)$_POST['service_id'];
                        if($service_id){
                            $sql .= " AND service_id=".$service_id;
                        }
                        $importservices = $this->db->select($sql, true);
                        // Проходимся по строкам, пока не конец файла
                        // или пока не импортировано достаточно строк для одного запроса
                        for($k=0; !feof($handle) && $k<$upload_limit; $k++){
                            // Читаем строку
                            if(($data = fgetcsv($handle, 0)) === FALSE) {
                                break;
                            }

                            $record = $data[0];
                            if ($record == 'record')
								continue;
                            $status = $data[1];
                            $record_type = 0;
                            if ($record_type = $this->check_private_list_record(trim($record), $service_type)) { 

                                // Для Security FireWall не пропускаем email и domain
                                if (isset($service_type) && $service_type == 'securityfirewall') {
                                    if (in_array($record_type, array(2, 4, 5, 3, 8))){
                                        $num_wrong_records++;
                                        continue;
                                    }
                                }
                                // Для Spam FireWall не пропускаем email и domain
                                if (isset($service_type) && $service_type == 'spamfirewall') {
                                    if (!in_array($record_type, array(6))){
                                        $num_wrong_records++;
                                        continue;
                                    }
                                }

                                // Добавляем префикс дабы сделать запись совместимое с БД.
                                if (in_array($record_type, $networks_rts) && !preg_match("/\/\d{1,2}$/", $record)) {
                                    $record .= '/32';
                                }

                                // Преобразуем IP в сеть для SFW записей.
                                if (in_array($record_type, $networks_rts)) {
                                    $record = $this->get_network_record($record);
                                }

                                if ($record_type == 4 && $this->private_list_domain !== null) {
                                    $record = $this->private_list_domain;
                                }

                                if ($status == 'allow' || $status == 'deny') {

                                } else {
                                    $status = 'deny';
                                }

                                if( $status == 'allow' && (
                                    $record_type == 8 || 
                                    ( isset($service_type) && $service_type == 'spamfirewall' )
                                )){
                                    $status = 'deny';
                                }
                                $record = str_ireplace(array('insert', 'delete', 'drop', 'update', 'select'), '', $record);
                                if(is_array($importservices))                                 
                                foreach ($importservices as $s) {
                                    // Для существующих записей обновляем статус, новые добавляем
                                    $sql = sprintf('SELECT service_id FROM services_private_list WHERE service_id = %d AND record = %s AND product_id = %d AND record_type = %d',
                                        $this->intToDB($s['service_id']),
                                        $this->stringToDB(strtolower(trim($record))),
                                        $this->intToDB($s['product_id']),
                                        $this->intToDB($record_type)
                                    );
                                    if($this->db->select($sql)){
                                        $num_exist_records++;
                                        continue;
                                    }
                                    $sql = sprintf('INSERT INTO services_private_list 
                                                    SET service_id=%d, record=%s, created=%s, updated=%s, status=%s, record_type=%s, product_id=%d
                                                    ON DUPLICATE KEY UPDATE
                                                    service_id=%1$d, record=%2$s, updated=%4$s, status=%5$s, record_type=%6$s, product_id=%7$d',
                                        $this->intToDB($s['service_id']),
                                        $this->stringToDB(strtolower(trim($record))),
                                        $this->stringToDB($recordsdate),
                                        $this->stringToDB($recordsdate),
                                        $this->stringToDB($status),
                                        $this->intToDB($record_type),
                                        $this->intToDB($s['product_id']));
                                    $insert_result = $this->db->run($sql, true);
                                    if($insert_result){
                                        $num_added_records++;
                                    }else{
                                        $num_exist_records++;
                                    }
                                    if ($product_id == cfg::product_security) {
                                        $this->create_spbc_task($service_id);
                                    }
                                    $allmainservices = $this->db->select(sprintf('select service_id, product_id from services
                                                          where user_id = %d %s', $this->user_id, $sql_product_id), true);                                        
                                }
                                
                            }
                        }
                        apc_store('added_records_' . $this->user_info['user_id'], $num_added_records);
                        apc_store('exist_records_' . $this->user_info['user_id'], $num_exist_records);
                        apc_store('wrong_records_' . $this->user_info['user_id'], $num_wrong_records);
                    }
                    
                    // Создаем объект результата
                    $result = new stdClass;
                    // На каком месте остановились
                    $result->from = ftell($handle); 
                    // И закончили ли полностью весь файл
                    $result->end = feof($handle);
                    // Закрываем файл
                    fclose($handle);
                    // Размер всего файла
                    $result->totalsize = filesize($file_path);
                    
                    if($result->end){ // Удаляем файл если закончили
                        unlink($file_path);
                        apc_delete('upload_csv_'.$this->user_info['user_id']);
                    }else{ // Сохраняем пуьт к файлу
                        apc_store('upload_csv_'.$this->user_info['user_id'], $file_path);
                    }
                    
                    header("Content-type: application/json; charset=UTF-8");
                    echo json_encode($result);
                    exit();
                }
                case 'upload_cancel' : {
                    if($file_path = apc_fetch('upload_csv_'.$this->user_info['user_id'])){
                        unlink($file_path);
                        apc_delete('upload_csv_'.$this->user_info['user_id']);
                    }
                    exit;
                }
                default:
                    break;
            }
            exit();
        } else
            $this->redirect('/my/show_private' . $uri);
    }

    // Фильтр по service_type
    $sql_service_type = '';
    $sql_product_id = '';
    $service_type = 'antispam';
    if (isset($_GET['service_type'])) {
        $service_type = preg_replace('/[^a-z]/i', '', $_GET['service_type']);
    }   
    switch ($service_type) {
        case 'securityfirewall':
            $sql_service_type = sprintf(" and spl.product_id=%d", cfg::product_security);
            $sql_product_id = sprintf(" and spl.product_id=%d", cfg::product_security);
            break;
        default:
            if($service_type == 'spamfirewall'){
                $service_type = 'spamfirewall';
            }else{
                $service_type = 'antispam';
            }
            if (isset($record_type_service[$service_type])) {
                $sql_service_type = sprintf(" and record_type in (%s) and spl.product_id=1",
                    implode(',', $record_type_service[$service_type])
                );
                $sql_product_id = sprintf(" and (spl.product_id = 1 or spl.product_id is null)",
                    implode(',', $record_type_service[$service_type])
                );
            }
    }
    $this->page_info['service_type'] = $service_type;

    // Фильтр по service_id        
    if(!isset($_GET['service_id'])){
        $this->service_id = 'all';
    }
    $sql = sprintf("select service_id, hostname, product_id from services spl where user_id = %d%s;", 
        $this->user_id,
        $sql_product_id
    );
    $services = $this->db->select($sql, true);
    // Проверка входит ли предлагаемый id сайта в список id сайтов пользователей
    $service_ids = array();
    foreach ($services as $k => $oneserviceid) {
        $service_ids[] = $oneserviceid['service_id'];
        $services[$k]['service_name'] = $this->get_service_visible_name($oneserviceid, true);
    }
    $service_id = $this->service_id;

    // Проверяем id сайта на пренадлежность к текущему пользователю и к списку делегированных сайтов
    if($service_id!='all' && !in_array($service_id, $service_ids) && !in_array($service_id, $this->granted_services_ids)){ 
        if(count($service_ids)){ // Перенаправляем на первый сайт из списка сайтов пользователя
            $service_id = $service_ids[0];
            $this->redirect('/my/show_private?service_id=' . $service_id);
            exit;
        }elseif(count($this->granted_services_ids)){ // Перенаправляем на первый сайт из списка делегированных
            $service_id = $this->granted_services_ids[0];
            $this->redirect('/my/show_private?service_id=' . $service_id);
            exit;
        }
        // Перенаправляем на главную ПУ
        $this->redirect('/my');
        exit;
    }


    // Признак того что сайт имеет право на запись
    // по умолчанию для всех сайтов выставляется в 1
    // если же сайт делегированный и у него только право на чтение то
    // становится 0
    $this->page_info['grantwrite'] = 1;
    if (in_array($service_id, $this->granted_services_ids)
        && !$this->grant_access_level($service_id)
    )
        $this->page_info['grantwrite'] = 0;
    
    $sql_service = '';
    if(isset($_GET['service_id']) && $_GET['service_id']=='all' || $service_id=='all'){ 
        if(is_array($service_ids) && !empty($service_ids)){
            $sql_service = 'spl.service_id IN ('.implode(', ', $service_ids).')';
        }else{
            $sql_service = '1>1';
        }
    }else{
        $sql_service = 'spl.service_id='.$service_id;
    }

    // Фильтр по типу
    $sql_record_type = '';
    if(isset($_GET['record_type'])){
        $record_type = intval($_GET['record_type']);
        if($record_type==4 || $record_type==5){
            $record_type_array = array(4,5);
        }elseif($record_type==6 || $record_type==7){
            $record_type_array = array(6,7);
        }else{
            $record_type_array = array($record_type);
        }
        $sql_record_type = " AND record_type IN (".implode(', ', $record_type_array).") ";
        $this->page_info['record_type'] = $record_type;
    }
    
    // Поиск записи
    if(isset($_GET['record'])){
        $record = $_GET['record'];
        $sql_record = sprintf(" AND spl.record LIKE '%%%s%%' ", mysql_real_escape_string($record));
        $this->page_info['record'] = $record;
    }
    // Фильтр по датам
    if (isset($_GET['start_from']) && isset($_GET['end_to'])) {
        $start_from = date('Y-m-d', preg_replace('/[^0-9]/i', '', $_GET['start_from']));
        $end_to = date('Y-m-d', preg_replace('/[^0-9]/i', '', $_GET['end_to']));
        $sql_dates = " and spl.updated between '" . $start_from . " 00:00:00' and '" . $end_to . " 23:59:59'";
        $this->page_info['end_to'] = date('M d, Y', strtotime($end_to));
        $this->page_info['start_from'] = date('M d, Y', strtotime($start_from));
    } else {
        $sql_dates = '';
    }
    // Фильтр по статусу
    if (isset($_GET['status'])) {
        $status = preg_replace('/[^a-z]/i', '', $_GET['status']);
        $sql_status = " and status = '" . $status . "'";
        $this->page_info['status'] = $status;
    } else
        $sql_status = '';

    if (isset($_GET['mode']) && $_GET['mode'] == 'csv') {
        $privatelist = $this->db->select(sprintf("select record, status,spl.created, s.hostname, note
                                        from services_private_list spl
                                        LEFT JOIN services s ON spl.service_id=s.service_id
                                        where %s %s %s %s %s %s
                                        order by record asc", $sql_service, $sql_service_type, $sql_status, $sql_dates,  $sql_record, $sql_record_type), true);
        $tools = new CleanTalkTools();
        $tools->download_send_headers("privatelist_export_" . date("Y-m-d-H-i-s") . ".csv");
        echo $tools->array2csv($privatelist,';');
        exit();
    }

	$page = 1;
	$page_limit = cfg::page_limit;
	if (isset($_GET['page']) && preg_match("/^\d+$/", $_GET['page']) && $_GET['page'] > 0) {
		$page = $_GET['page'];
	}
	$sql_page = sprintf(" limit %d, %d",
		($page - 1) * $page_limit,
		$page_limit
	);
	$this->page_info['page'] = $page;
    

    $privaterecs_sql = sprintf("select record_id, spl.record, spl.created, spl.updated, status, record_type, spl.product_id, note, spl.service_id, s.hostname, l.requests_week as 'hits'
                        from services_private_list spl
                        LEFT JOIN services s ON spl.service_id=s.service_id
                        LEFT JOIN services_private_list_stat l ON spl.service_id=l.service_id AND REPLACE(spl.record, 'country_', '')=l.record
                        where %s %s %s %s %s %s
                        order by spl.created desc%s;",
        $sql_service,
        $sql_status,
        $sql_service_type,
        isset($sql_record) ? $sql_record : '',
		$sql_dates,
        $sql_record_type,
		$sql_page
	);
    $privaterecs_total_sql = sprintf("select count(*) as count 
                        from services_private_list spl
                        where %s %s %s %s %s %s;",
        $sql_service,
        $sql_status,
        $sql_service_type,
        isset($sql_record) ? $sql_record : '',
        $sql_record_type,
		$sql_dates
	);
    
	$search_by_url = false;
	$records_total = 0;
	$record = null;

	$privaterecs = $this->db->select($privaterecs_sql, true);
    $row = $this->db->select($privaterecs_total_sql);
    $records_count = $row['count']; 
	
	$pages = array();         
    $visible_pages = 5;
    $page_from = 1;

    $total_pages_num = ceil($records_count / $page_limit);

    $current_page_num = $page;

    if ($current_page_num > floor($visible_pages/2)){
        $page_from = max(1, $current_page_num-floor($visible_pages/2));
    }

    if ($current_page_num > $total_pages_num-ceil($visible_pages/2)){
        $page_from = max(1, $total_pages_num-$visible_pages+1);
    }
    
    $page_to = min($page_from+$visible_pages-1, $total_pages_num);

    for ($i = $page_from; $i<=$page_to; $i++){
        $pages[]=$i;
    }
	$this->page_info['pages'] = $pages;
	$this->page_info['request_uri_page_free'] = isset($_SERVER['REQUEST_URI']) ? preg_replace("/\&page\=\d+/","", $_SERVER['REQUEST_URI']): '';
	$this->page_info['page_prev'] = $page > 1 ? $page - 1 : '';
	$this->page_info['page_next'] = $page < $records_count / $page_limit ? $page + 1 : '';
    $this->page_info['page_cur']  = $page;

    $dmnfile = file_get_contents(dirname(dirname(__FILE__)) . '/domains.txt');
    $domains = explode("\n", $dmnfile);
	$countries = $this->get_lang_countries();

	$records_list = '';
	$sql_where = '';
	$nets_hits = array();
    $countries_code = array();
    $service_ids = array();
	foreach ($privaterecs as $i => $onerec) {
		if ($records_list != '' && !strstr($onerec['record'], 'country')) {
			$records_list .= ','; 
		} 
		if ($service_type == 'securityfirewall' && in_array($onerec['record_type'], $security_ips_rts)) {
			$records_list .= ip2long($onerec['record']) ? ip2long($onerec['record']) : 0;
		} elseif (strstr($onerec['record'], 'country')) {
            $arr_rec = explode('_', $onerec['record']);
            $countries_code[] = $this->stringToDB($arr_rec[1]);
        }elseif($onerec['record_type']==8){
            $privaterecs[$i]['record'] = substr($onerec['record'], 1);
            $records_list .= $this->stringToDB($onerec['record']);
        }else{
			$records_list .= $this->stringToDB($onerec['record']);
		}
        $service_ids[] = intval($onerec['service_id']);
	}

	$data_requests_week = array();
  
    foreach ($privaterecs as $i => $onerec) {
        $privaterecs[$i]['sourcerecord'] = $privaterecs[$i]['record'];
        $privaterecs[$i]['created_time'] = strtotime($privaterecs[$i]['created']);
        $privaterecs[$i]['created'] = date('M d, Y', strtotime($privaterecs[$i]['created']));
        // Ставим @ для доменов первого уровня
        if (in_array(strtoupper($privaterecs[$i]['record']), $domains)) {
			$privaterecs[$i]['record'] = '*@*' . $privaterecs[$i]['record'];
		}
        $dotsnumber = substr_count($privaterecs[$i]['record'], '.');
		if (($dotsnumber == 1 || $dotsnumber == 2) && !strpos($privaterecs[$i]['record'], '@')) {
			$privaterecs[$i]['record'] = '*@' . $privaterecs[$i]['record'];
		}
		
		if (preg_match('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/i', $privaterecs[$i]['record']) && function_exists('geoip_country_code_by_name')) {
            $privaterecs[$i]['countrycode'] = strtolower(@geoip_country_code_by_name($privaterecs[$i]['record']));
            $privaterecs[$i]['countryname'] = Locale::getDisplayRegion('-' . $privaterecs[$i]['countrycode']);
        } else {
            $privaterecs[$i]['countrycode'] = '';
            $privaterecs[$i]['countryname'] = '';
        }
        // Вывод названий стран в списке записей            
        
        if (strstr($privaterecs[$i]['record'], 'country')) {
            $countryexpl = explode('_', $privaterecs[$i]['record']);
            foreach ($countries as $showcountry) {
                if ($showcountry['countrycode'] == $countryexpl[1]) {
                    $privaterecs[$i]['record'] = $showcountry['langname'];
                    $privaterecs[$i]['countrycode'] = $showcountry['countrycode'];
                    break;
                }
            }
        }
       
        $service_type = array();
        foreach ($record_type_service as $service => $rt) {
            if (in_array($onerec['record_type'], $rt) && !in_array($service, $service_type)) {
                $service_type[] = $service;
            }
        }
        $service_type_display = '';
        foreach ($service_type as $v) {
            if ($service_type_display != '') {
                $service_type_display .= ', ';
            }
            $service_type_display .= $this->lang['l_record_service_' . $v];
        }
        if ($service_type_display == '') {
            $service_type_display = $this->lang['l_record_service_antispam'];
        }
        if ($onerec['product_id'] == cfg::product_security) $service_type_display = 'Security FireWall';
        if ($onerec['product_id'] == cfg::product_antispam && $onerec['record_type']!=6) $service_type_display = 'AntiSpam';
        $privaterecs[$i]['service_type_display'] = $service_type_display;
        if ($search_by_url) {
            $recordexpl = explode('@', $privaterecs[$i]['sourcerecord']);
            if ($recordexpl[1] != $email_url)
                unset($privaterecs[$i]);
        }
	}
    // Вытаскиваем количество добавленных записей
    $num_added_records = apc_fetch('added_records_' . $this->user_info['user_id']);
    if ($num_added_records) {
    	$this->page_info['addedrecords'] = sprintf($this->lang['l_records_added'], $num_added_records) . $this->lang['l_addedas_appendix'];
        apc_delete('added_records_' . $this->user_info['user_id']);
    }
    // Вытаскиваем количество существующих записей
    $num_exist_records = apc_fetch('exist_records_' . $this->user_info['user_id']);
    if ($num_exist_records) {
        $this->page_info['existrecords'] = sprintf($this->lang['l_records_exist'], $num_exist_records);
        apc_delete('exist_records_' . $this->user_info['user_id']);
    }
    // Вытаскиваем количество ошибочных записей
    $num_wrong_records = apc_fetch('wrong_records_' . $this->user_info['user_id']);
    if ($num_wrong_records) {
        $this->page_info['wrongrecords'] = sprintf($this->lang['l_records_wrong'], $num_wrong_records);
        apc_delete('wrong_records_' . $this->user_info['user_id']);
    }
    $this->page_info['deleted_recs_text'] = $this->lang['l_deleted_recs_text'];
    $this->page_info['services'] = $services;
    $this->page_info['service_id'] = $service_id;
    $this->page_info['privaterecs'] = $privaterecs;
    $this->page_info['sfip'] = time() - 45 * 24 * 60 * 60;
    $this->page_info['etip'] = time();
	$this->page_info['privaterecs'] = $privaterecs;
	if ($records_count <= $page_limit) {
		$this->page_info['records_found'] = sprintf($this->lang['l_records_found'],
			'<span id="records_found">'.number_format($records_count, 0, ',', ' ').'</span>'
		);
	} else {
		$this->page_info['records_found'] = sprintf($this->lang['l_records_found_pv'],
			($page-1) * $page_limit + 1,
			($page * $page_limit>$records_count) ? $records_count : $page * $page_limit,
			'<span id="records_found">'.number_format($records_count, 0, ',', ' ').'</span>'
		);
	}
   
    $this->page_info['countries_data'] = $countries;
}


// Загружаем списки стоп слов
if ($this->ct_lang == 'ru') {
    $stop_list_url = 'http://download.cleantalk.org/stop_list_common_ru.txt';
}else{
    $stop_list_url = 'http://download.cleantalk.org/stop_list_common_en.txt';
}
$stop_list = file($stop_list_url,FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Игнорируем комментарии
foreach ($stop_list as $stop_word) {    
    if($stop_word[0]=='#') continue;
    $words[] = $stop_word;
}
$this->page_info['stop_list'] = $words;
$this->smarty_template = 'includes/general.html';
$this->page_info['container_fluid'] = true;
$this->page_info['scripts'] = array(
    '//cdn.jsdelivr.net/momentjs/latest/moment.min.js',
    '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js', 
    '/my/js/private.js?v=01042018',
);
$this->page_info['styles'] = array(
    '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css',
    '/my/css/font-awesome.min.css?v=16062016',
);
$this->page_info['geo_list'] = isset($geo_list) ? $geo_list : false;
$this->page_info['template']  = 'show_private_new.html';