<?
class Log extends CCS {
	function __construct() {
		parent::__construct();
    	$this->check_access();
		$this->ccs_init();
	}
	
	private function main() {
		// log_page - number of page
		$log_page = &$_GET['log_page'];
		if ( !preg_match("/^\d+$/", $log_page) ){
			$log_page = 1;
		}
		
		$sql = sprintf(sql::get_log_count);
		$this->var_dump("get_log_count", $sql);
		$sth = mysql_query($sql);
		if ( $sth ){
			$row_count = mysql_fetch_row($sth);
			for ( $i = 0; $i < ($row_count[0] / cfg::log_per_page); $i++){
				$this->page_info['pages'][] = $i + 1;
			}
		}else{
			$this->db->error();
			return 0;
		}
		$start_row = ($log_page - 1) * cfg::log_per_page;
		$end_row = ($log_page - 1) * cfg::log_per_page + cfg::log_per_page;
		$sql = sprintf(sql::get_logs, $start_row, $end_row);
		$this->var_dump("get_logs", $sql);
		$sth = mysql_query($sql);
		if ( $sth ){
			while ( $row = mysql_fetch_assoc($sth) ){
				$row['time'] = date("Y-m-d H:i", $row['time']);
				$this->page_info['logs'][] = $row;
			}
		}else{
			$this->db->error();
			return 0;
		}
	}

	function show_page(){
		$this->main();
		$this->display();
	}
}
