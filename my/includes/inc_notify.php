<?php
// Проверка доступа
if (!$this->check_access(null, true)) {
    if (!$this->app_mode) {
        $this->url_redirect('session', null, true);
    }
}

$this->get_lang($this->ct_lang, 'Antispam');
$this->smarty_template = 'includes/general.html';
$this->page_info['template'] = 'antispam/notify.html';
$this->page_info['container_fluid'] = true;
// $this->page_info['scripts'] = array('/my/js/.js');
$this->page_info['head']['title'] = 'notify';

if ($this->cp_mode != 'antispam') {
	$this->cp_mode = 'antispam';
	$this->page_info['cp_mode'] = $this->cp_mode;
	setcookie('cp_mode', $this->cp_mode, strtotime("+365 day"), '/');
}