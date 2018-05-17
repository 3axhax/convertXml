<?

class Top_words extends CCS {
	
	function __construct() {
		parent::__construct();
		$this->ccs_init();
		if (!$this->check_access())
			$this->url_redirect('top_words', null, true);
	}

	function show_page() {
		if (isset($this->lang['l_top_words_title']))
			$this->page_info['head']['title'] = $this->lang['l_top_words_title'];
        
        $service_id = null;
		if (isset($_GET['service_id']) && preg_match("/^\d+$/", $_GET['service_id'])) {
			$service_id = $_GET['service_id'];
        } else {
            $row = $this->db->select(sprintf("select service_id from services where user_id = %d order by created limit 1;", $this->user_id));
            if (isset($row['service_id']))
                $service_id = $row['service_id'];
            else
                $this->url_redirect('main');
        }
        $sql_service_id = 'and service_id = ' . $service_id;
        $url_service_id = '?service_id=' . $service_id;

		if (count($_POST)){

			$info = $this->safe_vars($_POST);
			$word = addslashes($info['word']);
			$word = mb_strtoupper($word, "UTF-8");
			// 1 - добавить слово
			// 2 - удалить слово
			$status = $info['delete_word'] == 1 ? 2 : 1;
			
			$this->page_info['warning'] = false;

			if (preg_match("/^[\wА-ЯЁ]{3,128}$/u", $word) && $this->user_info['tariff']['top_words_edit'] == 1){
				$row = $this->db->select(sprintf('select word_id from top_words where word = \'%s\';', $word));
				if (isset($row['word_id'])){
					$this->utw_change($row['word_id'], $this->user_info['user_id'], 1, $status, $service_id);
				}else{
					$this->db->run(sprintf("insert into top_words (word, submited) values ('%s', now());", $word));
					$row = $this->db->select(sprintf('select word_id from top_words where word = \'%s\';', $word));
					if (isset($row['word_id'])){
						$this->utw_change($row['word_id'], $this->user_info['user_id'], 1, $status, $service_id);
					}else{
						$this->post_log(sprintf(messages::unknown_error, __LINE__, __FILE__));
						$this->page_info['notice'] = $this->lang['not_valid_top_word'];
					}
				}
				
				if (!isset($this->page_info['notice']))
				{
					switch($status){
						case 1:
							$this->page_info['notice'] = $this->lang['top_word_added'];
							$this->post_log(sprintf(messages::user_added_word, $this->user_info['email'], $word));
							break;
						case 2:
							$this->page_info['notice'] = $this->lang['top_word_deleted'];
							$this->post_log(sprintf(messages::user_deleted_word, $this->user_info['email'], $word));
							break;
						default:
							$this->page_info['notice'] = $this->lang['top_word_added'];
					}
				}
			}else{
				switch($status){
						case 1:
							$this->page_info['notice'] = $this->lang['not_valid_top_word'];
							break;
						case 2:
							$this->page_info['notice'] = $this->lang['cant_delete_top_word'];
							break;
						default:
							$this->page_info['notice'] = $this->lang['not_valid_top_word'];
				}

				if ($this->user_info['tariff']['top_words_edit'] != 1)
					$this->page_info['notice'] = $this->lang['not_valid_tariff'];

				$notice_delay = 15000;
				setcookie('ct_notice_delay', $notice_delay);
				$this->page_info['warning'] = true;
			}

			$this->page_info['info'] = &$info;
			setcookie('ct_notice', $this->page_info['notice']);
			setcookie('ct_warning', $this->page_info['warning']);

			$this->url_redirect('top_words' . $url_service_id);
		}

		$sql = sprintf("select tw.word, utw.frequency, tw.word_id, utw.updated, unix_timestamp(utw.updated) as updated_ts, utw.status from users_top_words utw left join top_words tw on tw.word_id = utw.word_id where user_id = %d and utw.status != 2 %s order by utw.status desc, utw.frequency desc;", $this->user_info['user_id'], $sql_service_id);
		$top_words = $this->db->select($sql, true);

		if ($top_words){
			// Вычисляем крайниче значения частоты слов в словаре
			$max_frequency = 0;
			$min_frequency = 100000;
			$last_update = 0;
			$users_tw_count = 0;
			foreach ($top_words as $row){
				if ($row['frequency'] > $max_frequency)
					$max_frequency = $row['frequency'];
				if ($row['frequency'] < $min_frequency)
					$min_frequency = $row['frequency'];
				if ($row['updated_ts'] > $last_update)
					$last_update = $row['updated_ts'];
				if ($row['status'] == 1)
					$users_tw_count++;
			}

			// Добавляем информацию о размере шрифта
            $i = 0;
			foreach ($top_words as $k => $row){
                $i++;
				$font_size = cfg::tw_font_size_max * (($row['frequency']) / $max_frequency);
				$font_size = round($font_size, 0, PHP_ROUND_HALF_UP);
				if ($font_size < cfg::tw_font_size_min)
					$font_size = cfg::tw_font_size_min;
				if ($font_size > cfg::tw_font_size_max)
					$font_size = cfg::tw_font_size_max;
				
				// Выделяем слова внесенные в список вручную
                $word_color = '';
				if ($top_words[$k]['status'] == 1){
					$font_size = cfg::tw_font_size_max;
					$word_color = cfg::tw_manual_color;
                }
				if ($top_words[$k]['status'] == 1){
					$font_size = cfg::tw_font_size_max;
					$word_color = cfg::tw_manual_color;
                }

                if ($i > $this->user_info['tariff']['mpd'])
					$word_color = cfg::tw_out_dictionary_color;

				$top_words[$k]['font_size'] = $font_size;
				$top_words[$k]['color'] = $word_color;
			}
			$last_update = date("Y-m-d H:i", $last_update);
			$this->page_info['top_words'] = &$top_words;
			$this->page_info['tw_last_update'] = &$last_update;
			$this->page_info['top_words_max'] = $this->user_info['tariff']['mpd'];

			if ($users_tw_count >= $this->user_info['tariff']['mpd'])
			{
				$this->page_info['notice'] = $this->lang['max_top_words'];
				$this->page_info['warning'] = true;
			}

			// Рассчитываем количество слов в словаре
			$tw_count = 0;
			$tw_count = $this->db->select(sprintf("select count(*) as count from users_top_words where user_id = %d and service_id = %d and status <> 2;", $this->user_info['user_id'], $service_id));
			$tw_count = number_format($tw_count['count'], 0, '', ' ');
			$this->page_info['tw_count'] = &$tw_count;
			
			if (isset($this->page_info['l_tw_stat']))
				$this->page_info['l_tw_stat'] = sprintf($this->lang['l_tw_stat'], $tw_count, $this->user_info['tariff']['mpd'], $last_update, $service_id);
		}

		$this->page_info['jsf_focus_on_field'] = 'word';
		
		// Продолжительность отображения оповещения
		$notice_delay = 7000;
		if (isset($_COOKIE['ct_notice_delay']) && preg_match("/^\d+$/", $_COOKIE['ct_notice_delay']))
			$notice_delay = $_COOKIE['ct_notice_delay'];

		$this->page_info['notice_delay'] = $notice_delay;
	
		if ($this->ct_lang == 'ru')
			$this->page_info['help_link'] = 'help_top_words';
		else
			$this->page_info['help_link'] = 'help_top_words_en';

		// Если есть сообщение в куках, то выводим его пользователю
		if (isset($_COOKIE['ct_notice']) && !isset($this->page_info['notice'])){
			$this->page_info['notice'] = &$_COOKIE['ct_notice'];
			setcookie('ct_notice', null);
		}
		
		if (isset($_COOKIE['ct_warning']) && !isset($this->page_info['warning'])){
			$this->page_info['warning'] = &$_COOKIE['ct_warning'];
			setcookie('ct_notice', null);
		}

		$this->display();
	}

	/*
		
		Функция добавления слова в Справочник ключевых слов

	*/
	function utw_change($word_id, $user_id, $frequency, $status, $service_id){
		$utw = $this->db->select(sprintf("select word_id, frequency from users_top_words where word_id = %d and user_id = %d and service_id = %d;",
                                    $word_id, $user_id, $service_id));
		if (isset($utw['word_id'])){
			$this->db->run(sprintf("update users_top_words set frequency = frequency + %d, status = %d, updated = now()
									where word_id = %d and user_id = %d and service_id = %d;", $frequency, $status, $word_id, $user_id, $service_id));
		}else{
            if ($service_id === null)
                $sql_service_id = 'null';
            else
                $sql_service_id = $service_id;

			$this->db->run(sprintf("insert into users_top_words (word_id, user_id, frequency, submited, updated, status, service_id) 
									values (%d, %d, %d, now(), now(), %d, %d);", $word_id, $user_id, $frequency, $status, $service_id));
		}

		return 1;

	}
	
}

?>
