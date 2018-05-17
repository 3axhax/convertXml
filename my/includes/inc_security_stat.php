<?
$this->get_lang($this->ct_lang, 'Security');
$this->page_info['bsdesign'] = true;
$this->page_info['template'] = 'security/trends.html';
$this->page_info['head']['title'] = $this->lang['l_trends_title'];

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
} else if (isset($_GET['start_from']) && isset($_GET['end_to']) && ($s = preg_replace('/[^0-9]/i', '', $_GET['start_from'])) && ($e = preg_replace('/[^0-9]/i', '', $_GET['end_to']))) {    
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

$sql_where = sprintf("WHERE ss.user_id=%d AND ss.date BETWEEN '%s' AND '%s'",$this->user_id,$period['start'], $period['end']);

// Фильтр по типу данных
if(isset($_GET['event']) && $_GET['event']=='se'){
    $event = 'security_logs_event is not null';
}
if(isset($_GET['event']) && in_array($_GET['event'],array('auth_failed','invalid_email','invalid_username','login','logout','view'))){
    $event = "security_logs_event = '".$_GET['event']."'";
}
if(isset($_GET['event']) && $_GET['event']=='fw'){
    $event = 'security_firewall_logs_event is not null';
}
if(isset($_GET['event']) && in_array($_GET['event'],array('ALLOW','DENY'))){
    $event = "security_firewall_logs_event = '".$_GET['event']."'";
}
if(!empty($event)){
    $sql_where .= ' AND '.$event;
}

// Фильтр по сайтам
$sql_join = '';
$sql_group = '';
$sql_select = '';
if(!empty($_GET['services']) && is_array($_GET['services'])){
    $sql_where .= ' AND ss.service_id IN ('.implode(',', $_GET['services']).')';
    // $sql_join = 'LEFT JOIN services s ON s.service_id=ss.service_id';
    // $sql_group = ', ss.service_id';
    // $sql_select = ', ss.service_id, s.hostname';
}

// Загружаем сайты
$services = array();
$rows = $this->db->select(sprintf("SELECT service_id, hostname FROM services WHERE user_id = %d AND product_id = %d", $this->user_id, cfg::product_security), true);
foreach ($rows as $row) {
    $services[$row['service_id']] = $row;
}
$this->page_info['services'] = $services;
  
if($period['chartPoint'] == 'day'){ // По дням
    $group = "DATE_FORMAT(date,'%b %d, %Y')";
}else{ //По месяцам
    $group = "DATE_FORMAT(date,'%b %Y')";
}

$sql = "SELECT $group as 'display_date' $sql_select , sum(count) as count 
        FROM security_stat ss
        $sql_join
        $sql_where
        GROUP BY display_date ASC $sql_group WITH ROLLUP";
$total = 0;
$stats = $this->db->select($sql, true);
foreach ($stats as $stat) {
    if($stat['display_date']){
        $all_stats[strtotime($stat['display_date'])] = $stat;
    }else{
        $total = $stat;
    }
        
}
// заполняем данные нулями
if(empty($e)){
    if($period['chartPoint'] == 'day'){
        $e = strtotime(date('Y-m-d 00:00:00'));
    }else{
        $e = strtotime(date('M Y',time()));
    }
}else{
    if($period['chartPoint'] == 'day'){
        $e = strtotime(date('Y-m-d 00:00:00',$e));
    }else{
        $e = strtotime(date('Y-m-01 00:00:00',$e));
    }
}
while($s<=$e){
    if($period['chartPoint'] == 'day'){
        $cur_date_format = date('M d, Y',$e);
    }else{
        $cur_date_format = date('M Y',$e);
    }
    if(!isset($all_stats[$e])){
        $all_stats[$e] = array('display_date' => $cur_date_format, 'count' => 0);
    }
    // echo date('M d, Y',$e);
    if($period['chartPoint'] == 'day'){
        $e = strtotime('-1 day',$e);
    }else{
        $e = strtotime('-1 months',$e);
    }
}
if(!empty($all_stats)){
    ksort($all_stats);
    foreach ($all_stats as $stat) {
        if($stat['display_date'])// Данные для графика
            $chartJSON[$stat['display_date']] = $stat['count'];
    }
}

$all_stats[]=$total;
$this->page_info['stats'] = $all_stats;
if(!empty($chartJSON) )
$this->page_info['chart'] = json_encode($chartJSON);
else
$this->page_info['chart'] = json_encode(array());
$this->page_info['chartData'] = isset($chartPoints) ? $chartPoints : false;
if($this->user_info['moderate']==0){
    $this->page_info['show_modal'] = 1;
}

$this->display();
exit;