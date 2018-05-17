<?php
// Проверка доступа
if (!$this->check_access(null, true)) {
    if (!$this->app_mode) {
        $this->url_redirect('session', null, true);
    }
}

if(isset($_GET['action']) && $_GET['action']=='detail'){
    header("Content-type: application/json; charset=UTF-8");
    $result = new stdClass();
    if(isset($_GET['id']) && $log_id = intval($_GET['id'])){
        $sql = sprintf('SELECT user_id, service_id, failed_files, unknown_files FROM security_mscan_logs WHERE log_id=%d', $log_id);
        $row = $this->db->select($sql);
        if($row && ($row['user_id']==$this->user_id || in_array($row['service_id'], $this->granted_services_ids))){
            $failed_files = json_decode($row['failed_files']);
            if(!empty($failed_files)){
                foreach ($failed_files as $key=>$file) {
                    if(isset($file[1]) && $time = intval($file[1])){
                        $failed_files->$key = array(
                            $file[0], 
                            date('M d, Y H:i:s',$time),
                            !empty($file[2]) ? formatSizeUnits($file[2]) : '-'
                        );
                    }
                }
            }
            $result->failed_files = $failed_files;
            $unknown_files = json_decode($row['unknown_files']);
            if(!empty($unknown_files)){
                foreach ($unknown_files as $key=>$file) {
                    if(isset($file[1]) && $time = intval($file[1])){
                        $unknown_files->$key = array(
                            $file[0], 
                            date('M d, Y H:i:s',$time),
                            !empty($file[2]) ? formatSizeUnits($file[2]) : '-'
                        );
                    }
                }
            }
            $result->unknown_files = $unknown_files;
        }else{
            $result->error = 'Not found';    
        }
    }else{
        $result->error = 'Unknown id';
    }
    echo json_encode((array)$result);
    exit();
}

// Фильтры
$filters = array();
$url = array();
// Фильтр по текущему пользователю
$filters[0] = sprintf('l.user_id=%d', $this->user_id);

// Фильтр по датам
if(!empty($_GET['customdates'])){
    $dates = explode(' - ', $_GET['customdates']);
    if(is_array($dates) && !empty($dates[0]) && !empty($dates[1])){
        $date_from = strtotime($dates[0]);
        $date_to = strtotime($dates[1]);

        $this->page_info['start_from'] = date('M d, Y',$date_from);
        $this->page_info['end_to'] = date('M d, Y',$date_to);

        $tz = (isset($this->user_info['timezone'])) ? (float)$this->user_info['timezone'] : 0;
        $tz_ts = ($tz - 5) * 3600;

        $sql_from = date('Y-m-d H:i:s',$date_from+$tz_ts);
        $sql_to = date('Y-m-d H:i:s',$date_to+$tz_ts+24*60*60-1);
        $filters[] = sprintf("l.submited BETWEEN '%s' AND '%s'", $sql_from, $sql_to);
        $url[] = 'customdates='.$this->page_info['start_from'].' - '.$this->page_info['end_to'];
    }
}

// Фильтр по сайту
if(!empty($_GET['service'])){
    if(in_array($_GET['service'], $this->granted_services_ids)){
        unset($filters[0]); // Уберем фильтр по текущему пользователю, для сайтов с доступом.
    }
    $filters[] = sprintf('l.service_id=%d',intval($_GET['service']));
    $url[] = 'service='.intval($_GET['service']);
}

// Фильтр по результату
if(isset($_GET['result'])){
    if(intval($_GET['result'])==1){
        $result = 'PASSED';
    }elseif(intval($_GET['result'])==2){
        $result = 'WARNING';
    }else{
        $result = 'FAILED';
    }
    $filters[] = sprintf('l.result="%s"',$result);
    $url[] = 'result='.intval($_GET['result']);
}

// Соберем все фильтры
$sql_filter = implode(' AND ', $filters);

$requests = null;
$this->page_info['bsdesign'] = true;
$this->page_info['url'] = '/my/logs_mscan';
if(!empty($url)){
    $this->page_info['url'] .= '?'.implode('&', $url);
}
$this->smarty_template = 'includes/general.html';
$this->page_info['template']  = 'security/log_mscan.html';
$this->page_info['container_fluid'] = true;
$this->page_info['head']['title'] = 'Malware Scans Log';
$this->page_info['scripts'] = array(
    '//cdnjs.cloudflare.com/ajax/libs/moment.js/2.18.1/moment.min.js',
    '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js',
    '/my/js/mscan-log.js?v22.02.2018'
);
$this->page_info['styles'] = array(
    '//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css'
);

if (!$this->check_access(null, true)) {
    if (!$this->app_mode) {
        $this->url_redirect('session', null, true);
    }
}
 // Постраничная навигация                            
$pages = array();
$current_page = 1; // Страница по умолчанию
$visible_pages = 5; // Количество отображаемых страниц
$page_from = 1; // Номер первой страницы
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
if(isset($_GET['page'])){
    $current_page = intval($_GET['page']);
    if($current_page<1){
        $current_page = 1;
    }
}

// Считаем кол-во записей в таблице
$sql = 'SELECT count(*) as count FROM security_mscan_logs l WHERE '.$sql_filter;
$row = $this->db->select($sql);
$records_count = $row['count'];

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
$sql_limit = sprintf(' LIMIT %d, %d', $current_page*$items_per_page-$items_per_page, $items_per_page);
$sql = 'SELECT l.log_id, l.user_id, l.service_id, DATE_FORMAT(l.submited,"%b %d, %Y %H:%i:%s") as submited, l.total_core_files, l.result, s.name, s.hostname
        FROM security_mscan_logs l 
        LEFT JOIN services s ON s.service_id = l.service_id 
        WHERE '.$sql_filter.'
        ORDER BY l.submited DESC, log_id DESC '.$sql_limit;
$rows = $this->db->select($sql, true);
if(is_array($rows)){
    foreach ($rows as &$row) {
        $row['service_name'] = $this->get_service_visible_name($row);
    }
}
if($ajax){
    header("Content-type: application/json; charset=UTF-8");
    $result = new stdClass();
    $result->rows = $rows;
    $result->total_pages = $this->page_info['total_pages'];
    $result->page = $this->page_info['current_page'];
    $result->records_count = $this->page_info['records_found'];
    $result->pages = $pages;
    $result->url = $this->page_info['url'];
    echo json_encode($result);
    exit();
}else{
    $this->page_info['rows'] = $rows;
    $this->page_info['pages'] = $pages;
}


// Выборка сайтов пользователя для фильтра
$services = $this->db->select(sprintf("SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d", $this->user_id, cfg::product_security), true);
$this->page_info['services'] = $services;

if($this->user_info['moderate']==0){
    $this->page_info['show_modal'] = 1;
}
function formatSizeUnits($bytes){
    if ($bytes >= 1073741824){
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    }
    elseif ($bytes >= 1048576){
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    }
    elseif ($bytes >= 1024){
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    }
    elseif ($bytes > 1){
        $bytes = $bytes . ' bytes';
    }
    elseif ($bytes == 1){
        $bytes = $bytes . ' byte';
    }
    else{
        $bytes = '0 bytes';
    }

    return $bytes;
}