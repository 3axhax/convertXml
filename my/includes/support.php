<?php

include '../includes/helpers/storage.php';

class Support extends CCS {
    /**
     * @var Storage Объект хранилища файлов.
     */
    private $storage;

    public function __construct() {
        parent::__construct();
        $this->ccs_init();
        if (!$this->check_access()) $this->url_redirect('session', null, true);
        $this->storage = new Storage($this->db, $this->user_info['user_id']);
        $this->_remove_unpublished();
    }

    public function show_page() {
        $this->get_lang($this->ct_lang, 'Support');

        $community_link = 'https://community.cleantalk.org';
        $has_wordpress = array(
            cfg::product_antispam => false,
            cfg::product_database_api => false,
            cfg::product_hosting_antispam => false,
            cfg::product_security => false,
            cfg::product_ssl => false
        );
        if ($this->user_id) {
            $sql = sprintf("select engine, product_id from services where user_id = %d;", $this->user_id);
            $engines = $this->db->select($sql, true);
            foreach ($engines as $engine) {
                if ($engine['engine'] == 'wordpress') {
                    if ($this->cp_product_id == $engine['product_id'])
                        $community_link = 'https://wordpress.org/support/plugin/cleantalk-spam-protect';
                    $has_wordpress[$engine['product_id']] = true;
                }
            }
        }
        $this->page_info['community_link'] = $community_link;

        if (!isset($_GET['action'])) {
            switch ($this->link->template) {
                case 'support/outsourcing.html':
                    $this->outsourcing();
                    break;
                case 'support/open.html':
                    $this->form();
                    break;
                default:
                    $this->index($has_wordpress);
                    break;
            }
        } else {
            switch($_GET['action']) {
                case 'view_ticket':
                    $this->view();
                    break;
                case 'upload':
                    $this->upload();
                    break;
                case 'delete_file':
                    $this->delete();
                    break;
                case 'articles':
                    $this->articles_json();
                    break;
            }
        }
    }

    /**
     * Поиск статей при наборе заголовка обращения.
     */
    protected function articles_json() {
        $result = array();

        if (isset($_GET['q']) && !empty($_GET['q'])) {
            $keywords = apc_fetch('articles_keywords');
            if (!$keywords) {
                $keywords = array();
                $rows = $this->db->select("SELECT keywords, article_id FROM articles WHERE article_where = 'help' AND article_linkid <> 0", true);
                foreach ($rows as $row) {
                    $keys = explode(',', $row['keywords']);
                    foreach ($keys as $r) {
                        $r = trim(mb_strtolower($r));
                        if (isset($keywords[$r])) {
                            $keywords[$r][] = $row['article_id'];
                        } else {
                            $keywords[$r] = array($row['article_id']);
                        }
                    }
                }
                apc_store('articles_keywords', $keywords, 600);
            }

            $q = trim(mb_strtolower(preg_replace('/[\':";,.\\\\\/]/', '', $_GET['q'])));
            $q_russian = ($this->ct_lang == 'ru' || preg_match('/[А-Яа-яЁё]/u', $q)) ? true : false;

            $keys = explode(' ', $q);
            foreach ($keys as $k => $v) {
                $keys[$k] = trim($v);
            }

            $r = array();
            foreach($keywords as $key => $val) {
                if (!empty($q) && strpos($q, $key) !== false) {
                    foreach ($val as $id) {
                        if (isset($r[$id])) $r[$id]++; else $r[$id] = 1;
                    }
                }
            }
            arsort($r);
            $r = array_slice($r, 0, 10, true);

            if (count($r)) {
                $sql = sprintf("SELECT a.article_id, a.article_title, l.seo_url FROM articles a LEFT JOIN links l ON l.id = a.article_linkid WHERE a.article_id IN (%s)", implode(', ', array_keys($r)));
                //$sql = sprintf("SELECT a.article_id, a.article_title, l.seo_url FROM articles a LEFT JOIN links l ON l.id = a.article_linkid WHERE a.article_linkid <> 0 AND a.article_where = 'help' AND a.article_title LIKE '%s' LIMIT 20;", $q);
                $rows = $this->db->select($sql, true);
                $unique = array();
                foreach ($rows as $row) {
                    if (preg_match('/\-ru$/', $row['seo_url']) && !$q_russian) continue;
                    if (preg_match('/\-ru$/', $row['seo_url'])) $row['seo_url'] = preg_replace('/\-ru$/', '', $row['seo_url']);
                    if (in_array($row['seo_url'], $unique)) continue;
                    $unique[] = $row['seo_url'];
                    $result[] = array(
                        'title' => $row['article_title'],
                        'url' => sprintf('/help/%s', $row['seo_url'])
                    );
                }
            }
        }

        echo(json_encode($result));
        exit;
    }

    /**
     * Основная страница.
     */
    protected function index($has_wordpress) {
        if (isset($_GET['subjf']) || isset($_GET['review'])) {
            $subject = '';
            if (isset($_GET['subjf'])) $subject = $_GET['subjf'];
            if (isset($_GET['review']) && $_GET['review'] == 1) $subject .= $this->lang['l_review'];
            if ($subject) {
                header('Location: /my/support/open?subject=' . $subject);
                exit;
            }
        }

        $timezone = (int)$this->user_info['timezone'] - 5;
        $timezone_ts = $timezone * 3600;

		$this->show_review_notice(1);

        $tickets = $this->db->select(sprintf("SELECT t.ticket_id, t.created, t.updated, t.viewed, t.subject, t.status, s.hostname, t.resolved, s.engine
                                          FROM tickets t
                                          LEFT JOIN services s ON t.service_id = s.service_id
                                          WHERE t.user_id = %d
                                          ORDER BY t.updated DESC", $this->user_info['user_id']), true);
        foreach ($tickets as &$ticket) {
            $updated = strtotime($ticket['updated']);
            $viewed = strtotime($ticket['viewed']);
            if ($updated > $viewed) {
                $ticket['is_updated'] = 'updated';
            }
			
			if (isset($this->review_links[$ticket['engine']])) {
				$ticket['rate_url'] = $this->review_links[$ticket['engine']];
			}


            $ticket['created'] = date('M j, Y H:i:s', strtotime($ticket['created']) + $timezone_ts);
            $ticket['updated'] = date('M j, Y H:i:s', $updated + $timezone_ts);
		}
//		var_dump($tickets);exit;
        $this->page_info['tickets'] = $tickets;

        if (isset($_SESSION['ticket_message'])) {
            $this->page_info['show_message'] = $_SESSION['ticket_message'];
            unset($_SESSION['ticket_message']);
        }

        if ($has_wordpress[$this->cp_product_id]) {
            switch ($this->cp_product_id) {
                case cfg::product_antispam:
                    $this->page_info['open_mode'] = array(
                        'link' => 'https://wordpress.org/support/plugin/cleantalk-spam-protect',
                        'text' => sprintf($this->lang['l_ticket_open_text1'], 'https://wordpress.org/support/plugin/cleantalk-spam-protect')
                    );
                    break;
                case cfg::product_security:
                    $this->page_info['open_mode'] = array(
                        'link' => 'https://wordpress.org/support/plugin/security-malware-firewall',
                        'text' => sprintf($this->lang['l_ticket_open_text1'], 'https://wordpress.org/support/plugin/security-malware-firewall')
                    );
                    break;
            }
        }

        $this->smarty_template = 'includes/general.html';
        $this->page_info['template'] = 'support/index.html';
        $this->page_info['container_fluid'] = true;
        $this->display();
    }

    /**
     * Форма обращения на аутсорсинг.
     */
    protected function outsourcing() {
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template'] = 'support/edit.html';

        $services = $this->db->select(sprintf("SELECT service_id, hostname FROM services WHERE user_id = %d;", $this->user_info['user_id']), true);
        $this->page_info['services'] = $services;

        $this->page_info['subject'] = $this->lang['l_outsourcing_subject'];
        $this->page_info['form_notification'] = $this->lang['l_outsourcing_description'];
        $this->page_info['reply_code'] = date('YmdHis');

        $this->display();
    }

    /**
     * Форма создания обращения.
     */
    protected function form() {
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template'] = 'support/edit.html';
        $this->page_info['scripts'] = array('/my/js/validator.min.js');
        $errors = array();

        if (count($_POST)) {
            $info = $this->safe_vars($_POST);

            if (empty($info['subject'])) $errors['subject'] = true;
            if (empty($info['message'])) $errors['message'] = true;
            if (empty($info['reply_code'])) $errors['global'] = 'Internal Server Error';

            if (empty($errors)) {
                // Создаём запись в БД
                $ticket_id = $this->db->run(sprintf(
                    "INSERT INTO tickets (%s) VALUES (%s)",
                    implode(', ', array(
                        'user_id', 'service_id', 'created', 'updated', 'viewed', 'subject', 'message'
                    )),
                    implode(', ', array(
                        $this->user_info['user_id'],
                        ($info['service_id'] && preg_match('/^\d+$/', $info['service_id'])) ? $info['service_id'] : 'NULL',
                        'now()', 'now()', 'now()',
                        $this->stringToDB($info['subject']),
                        $this->stringToDB($info['message'])
                    ))
                ));
                if ($ticket_id) {
                    // Файлы тикета
                    $files = $this->storage->findByTag($_POST['reply_code']);
                    foreach ($files as $file) {
                        $file['tag'] = 'ticket_' . $ticket_id;
                        $file['published'] = 1;
                    }

                    // Отправляем email
                    $this->_email(
                        array('[CleanTalk ticket #%d] %s', $ticket_id, $info['subject']),
                        array('<br><br>--Write answer above this line--<br><br>' .
                            'User <a href="https://cleantalk.org/noc/profile?user_id=%d">%s (%d)</a> created ticket:<br><br>' .
                            '%s<br><br>' .
                            '<a href="https://cleantalk.org/noc/tickets?action=view_ticket&ticket_id=%d">View ticket here</a>',
                            $this->user_info['user_id'], $this->user_info['email'], $this->user_info['user_id'],
                            str_replace("\n", '<br>', $info['message']),
                            $ticket_id)
                    );

                    $this->post_log(sprintf(
                        'Пользователь %s (%d) создал тикет номер %d:',
                        $this->user_info['email'], $this->user_info['user_id'], $ticket_id
                    ));

                    $_SESSION['ticket_message'] = sprintf($this->lang['l_ticket_message'], $info['subject'], $this->utils->getHoursToRespond($this->ct_lang));
                    $this->redirect('/my/support');
                } else {
                    $errors['global'] = 'Internal Server Error';
                }
            }
        } else {
            $info = array(
                'subject' => (isset($_GET['subject']) ? $_GET['subject'] : ''),
                'service_id' => '',
                'message' => ''
            );
        }
		$sql = sprintf("SELECT service_id, hostname, p.descr_en FROM services s left join product p on p.product_id = s.product_id WHERE user_id = %d;", 
			$this->user_id
		);
		$services = $this->db->select($sql, true);
		foreach ($services as $k => $v) {
			$v['service_name'] = $this->get_service_visible_name($v, true);
			$services[$k] = $v;
		}
        $this->page_info['services'] = $services;
        $this->page_info['errors'] = $errors;
        $this->page_info['info'] = $info;
        $this->page_info['reply_code'] = sprintf('ticket_tmp_%d_%d', $this->user_info['user_id'], time());

        $this->display();
    }

    /**
     * Загрузка файла.
     */
    function upload() {
        $info = $this->safe_vars($_POST);

        if (isset($_FILES['file']) && isset($info['reply_code']) && preg_match('/^ticket_tmp_.+$/', $info['reply_code'])) {            
            if(!empty($info['ticket_id'])){
                $sql = sprintf('SELECT user_id FROM tickets WHERE ticket_id = %d;', intval($info['ticket_id']));
                $result = $this->db->select($sql);
                if($result['user_id']!=$this->user_info['user_id']){
                    $this->_jsonError('Internal Server Error');
                    exit;
                }
            }
            try {
                $path = (isset($info['ticket_id']) && preg_match('/^\d+$/', $info['ticket_id'])) ?
                    sprintf('tickets/%d/%s', $info['ticket_id'], $_FILES['file']['name']) :
                    'tickets/' . $_FILES['file']['name'];
                $file = $this->storage->upload($_FILES['file']['tmp_name'], $path, 's3');
                $file['tag'] = $info['reply_code'];
                $file['published'] = 0;
            } catch (Exception $e) {
                $this->_jsonError($e->getMessage());
                exit;
            }
            echo($file['id']);
            exit;
        }

        $this->_jsonError('Internal Server Error');
        exit;
    }

    /**
     * Просмотр обращения.
     */
    protected function view() {
        if (count($_POST)) {
            $info = array();
            foreach($_POST as $key => $value) {
                $info[$key] = strip_tags(addslashes($value), '<a><blockquote><br><br /><ul><ol><li>');
                $info[$key] = str_replace(array("'",'&amp;lt;','&amp;gt;','&amp;amp;'), array('&rsquo;','&lt;','&gt;','&amp;'), htmlspecialchars(trim($info[$key])));
            }
            if (empty($info['replymessage'])) {
                $this->redirect($_SERVER['HTTP_REFERER']);
            }
            if (!isset($_POST['ticket_id']) || !preg_match("/^\d+$/", $_POST['ticket_id'])) {
                $this->redirect($_SERVER['HTTP_REFERER']);
            }

            $ticket = $this->db->select(sprintf('SELECT user_id, subject FROM tickets WHERE ticket_id = %d;', $_POST['ticket_id']));
            if (!$ticket || $ticket['user_id'] != $this->user_info['user_id']) {
                $this->redirect($_SERVER['HTTP_REFERER']);
            }

            $info['replymessage'] = str_replace('<br />', "\n", $info['replymessage']);

            $comment_id = $this->db->run(sprintf("INSERT INTO tickets_replies (ticket_id, user_id, datetime, message) VALUES (%d, %d, now(), %s);",
                $info['ticket_id'],
                $this->user_info['user_id'],
                $this->stringToDB($info['replymessage'])
            ));

            if ($comment_id) {
                // Файлы комментария
                $files = $this->storage->findByTag($info['reply_code']);
                foreach ($files as $file) {
                    $file['tag'] = sprintf('ticket_%d_%d', $info['ticket_id'], $comment_id);
                    $file['published'] = 1;
                }

                $this->_email(
                    array('[CleanTalk ticket #%d] %s', $info['ticket_id'], $ticket['subject']),
                    array(
                        '<br><br>--Write answer above this line--<br><br>' .
                        'User <a href="https://cleantalk.org/noc/profile?user_id=%d">%s (%d)</a> added comment:<br><br>' .
                        '%s<br><br>' .
                        '<a href="https://cleantalk.org/noc/tickets?action=view_ticket&ticket_id=%d#%d">View comment here</a>',
                        $this->user_info['user_id'], $this->user_info['email'], $this->user_info['user_id'],
                        str_replace("\n", '<br>', $info['replymessage']),
                        $info['ticket_id'], $comment_id
                    )
                );

                $log_message = sprintf('Пользователь %s (%d) создал комментарий к тикету номер %d.',
                    $this->user_info['email'], $this->user_info['user_id'],
                    $info['ticket_id']);
                $this->post_log($log_message);

                $this->db->run(sprintf('UPDATE tickets SET updated = now(), viewed = now(), resolved = null WHERE ticket_id = %d;',$info['ticket_id']));
                $_SESSION['comment_message'] = sprintf($this->lang['l_comment_message'], $this->utils->getHoursToRespond($this->ct_lang));
            }

            $this->redirect($_SERVER['HTTP_REFERER']);
        }

        if (!isset($_GET['ticket_id']) || !preg_match("/^\d+$/", $_GET['ticket_id'])) {
            header('Location: /my/support'); exit;
        }
		$sql = sprintf('SELECT t.ticket_id, t.user_id, t.created, t.updated, t.resolved, t.status, t.subject, t.message, s.hostname
                                         FROM tickets t
                                         LEFT JOIN services s ON t.service_id = s.service_id
										 WHERE t.ticket_id = %d and t.user_id = %d;', 
			$_GET['ticket_id'],
			$this->user_id
		);
		$ticket = $this->db->select($sql);
        if (!$ticket) {
            header('Location: /my/support');
            exit;
        }

        if ($this->user_info['user_id'] == $ticket['user_id'] || $this->is_admin) {
            $domains_file = file_get_contents(dirname(dirname(__FILE__)) . '/domains.txt');
            $domains = explode("\n", $domains_file);

            $timezone = (int)$this->user_info['timezone'] - 5;
            $timezone_ts = $timezone * 3600;
            
            $ticket['message'] = str_replace(array("\n", '&nbsp;','&amp;nbsp;'), array('<br>', ' ',' '), $this->make_link($ticket['message'], $domains));
            $ticket['created'] = date("M j,Y H:i:s", strtotime($ticket['created']) + $timezone_ts);
            $ticket['updated'] = date("M j,Y H:i:s", strtotime($ticket['updated']) + $timezone_ts);

            $ticket['file_block'] = '';
            $files = $this->db->select(sprintf('SELECT id, file_name FROM tickets_files WHERE ticket_id = %d AND reply_id IS NULL;', $ticket['ticket_id']), true);
            if (empty($files)) {
                $files = $this->storage->findByTag('ticket_' . $ticket['ticket_id']);
                foreach ($files as $file) {
                    $ticket['file_block'] .= $this->_storageFileLink($file);
                }
            } else {
                foreach ($files as $file) {
                    $ticket['file_block'] .= $this->make_file_link(0,$file['file_name'], $file['id']);
                }
            }

            $this->page_info['ticket'] = $ticket;

            $replies = $this->db->select(sprintf('SELECT r.reply_id, r.datetime, r.message, u.first_name, u.last_name, u.email, u.user_id 
                                                FROM tickets_replies r LEFT JOIN users u
                                                ON r.user_id = u.user_id
                                                WHERE r.ticket_id = %d 
                                                ORDER BY r.datetime ASC;', $ticket['ticket_id']), true);
            foreach ($replies as &$reply) {
                $reply['message'] = str_replace(array("\n", '&nbsp;','&amp;nbsp;'), array('<br>', ' ',' '), $this->make_link($reply['message'], $domains));
                $reply['message'] = preg_replace("/<\/li>[\n\r]*<br>[\n\r]*<li>/i", '</li><li>', $reply['message']);

                if ($reply['first_name'] || $reply['last_name']) {
                    $reply['user'] = $reply['first_name'] . ' ' . $reply['last_name'];
                } else {
                    $reply['user'] = $reply['email'];
                }

                $reply['datetime'] = date("M j,Y H:i:s", strtotime($reply['datetime']) + $timezone_ts);

                $reply['file_block'] = '';
                $files = $this->db->select(sprintf('SELECT id, file_name FROM tickets_files WHERE reply_id = %d;', $reply['reply_id']), true);
                if (empty($files)) {
                    $files = $this->storage->findByTag(sprintf('ticket_%d_%d', $ticket['ticket_id'], $reply['reply_id']));
                    foreach ($files as $file) {
                        $reply['file_block'] .= $this->_storageFileLink($file);
                    }
                } else {
                    foreach ($files as $file) {
                        $reply['file_block'] .= $this->make_file_link($ticket['ticket_id'], $file['file_name'], $file['id']);
                    }
                }
            }

            $this->page_info['replies'] = $replies;
            $this->page_info['reply_code'] = sprintf('ticket_tmp_%d_%d_%d', $ticket['ticket_id'], $this->user_info['user_id'], time());
        } else {
            header('Location: /my/support'); exit;
        }

        if (isset($_SESSION['comment_message'])) {
            $this->page_info['show_message'] = $_SESSION['comment_message'];
            unset($_SESSION['comment_message']);
        }

        $this->smarty_template = 'includes/general.html';
        $this->page_info['template'] = 'support/view.html';
        $this->page_info['scripts'] = array('/tinymce/tinymce.min.js');
        $this->display();
    }

    /**
    * Функция удаления файла c Амазона
    *
    * @return void
    */
    function delete() {
        if (!isset($_GET['file_id']) || !preg_match('/^\d+$/', $_GET['file_id'])) {
            $this->_jsonError('Internal Server Error');
            exit;
        }

        if ($file = $this->storage->findById($_GET['file_id'])) {
            if($this->user_info['user_id']==$file['user_id']){
                $file->remove();
                echo('true');
                exit;
            }
        }

        $this->_jsonError('Internal Server Error');
        exit;        
    }

    /**
    * Функция подсветки ссылок в сообщениях тикета
    *
    * @param string $message Текст для подсветки
    * @param array $domains Домены для подсветки ссылок
    * @return string
    */
    function make_link($message,&$domains){
        // Ищем email и заменяем на временные метки
        preg_match_all('/[\*a-zA-Z0-9_-]+@[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+([\.a-zA-Z0-9]*)/i',$message,$emailmatches);
        foreach($emailmatches[0] as $i=>$oneematches) {
            $message = str_replace($oneematches,'**'.$i,$message);
        }
        preg_match_all('/([http:\/\/|https:\/\/]*)([www\.]*)([\.a-zA-Z0-9_-]+)(\.[a-z]+)([^\s><()]*)/i', $message, $matches);

        $sortedlinks = array();

        // определяем длины найденных значений и сортируем по ним
        foreach($matches[0] as $sortmatch){
            $len = strlen($sortmatch);
            if (!isset($sortedlinks[$len])) $sortedlinks[$len] = $sortmatch;
            else $sortedlinks[$len + rand(100,200)] = $sortmatch;
        }

        krsort($sortedlinks);

        foreach($sortedlinks as $i=>$onematch){
            if (preg_match('/txt|jpg|jpeg|png|gif/i',$onematch))
                continue;
            else {
                preg_match_all('/\.[a-z]+/i', $onematch, $domainmatches);
                foreach($domainmatches[0] as $onedomain) {
                    if (in_array(strtoupper(str_replace('.','',$onedomain)), $domains)) {
                        $message = str_replace($onematch, '**link'.$i, $message);
                        break;
                    }
                }
            }
        }

        foreach($sortedlinks as $i=>$onematch){
            if (stristr($onematch,'http')||strstr($onematch,'https'))
                $message = str_replace('**link'.$i,'<a href="'.$onematch.'" target="_blank">'.$onematch.'</a>',$message);
            else
                $message = str_replace('**link'.$i,'<a href="http://'.$onematch.'" target="_blank">http://'.$onematch.'</a>',$message);
        }


        // Возвращаем email обратно
        foreach($emailmatches[0] as $i=>$oneematches)
            $message = str_replace('**'.$i,$oneematches,$message);

        return $message;
    }

    /**
    * Функция для формирования ссылок на файлы
    *
    * @param int $ticket_id ID Тикета
    * @param string $file_name Имя файла
    * @param int $file_id
    * @return string
    */
    function make_file_link($ticket_id, $file_name, $file_id) {
        $retlink = '';
        if (preg_match('/jpeg|jpg|gif|png/i',$file_name)) {
            return sprintf('<div><span class="glyphicon glyphicon-picture"></span> <a href="%s" target="_blank" data-toggle="tooltip" title="<img src=\'%s\' width=\'120\' height=\'120\'>">%s</a></div>',
                'https://s3.eu-central-1.amazonaws.com/cleantalk-atts/' . $ticket_id . '/' . urlencode($file_name),
                'https://s3.eu-central-1.amazonaws.com/cleantalk-atts/' . $ticket_id . '/' . urlencode($file_name),
                $file_name
            );
        }
        return sprintf('<div><span class="glyphicon glyphicon-file"></span> <a href="%s" target="_blank">%s</a></div>',
            'https://s3.eu-central-1.amazonaws.com/cleantalk-atts/' . $ticket_id . '/' . urlencode($file_name),
            $file_name
        );
    }

    /**
    * Функция перенаправления на указанный адрес
    *
    * @param string $where Адрес куда перенаправить
    *
    * @return void
    */
    function redirect($where) {
        header('Location: '.$where);
        exit();
    }

    private function _storageFileLink($file) {
        if (preg_match('/jpeg|jpg|gif|png/i', $file['location'])) {
            return sprintf(
                '<div><span class="glyphicon glyphicon-picture"></span> ' .
                '<a href="%s" target="_blank" data-toggle="tooltip" title="<img src=\'%s\' width=\'120\' height=\'120\'>">%s</a></div>',
                $file->link(),
                $file->link(),
                $file['filename']
            );
        }
        return sprintf(
            '<div><span class="glyphicon glyphicon-file"></span> <a href="%s" target="_blank">%s</a></div>',
            $file->link(), $file['filename']
        );
    }

    private function _email($subject, $message) {
        if (is_array($subject)) $subject = vsprintf($subject[0], array_slice($subject, 1));
        if (is_array($message)) $message = vsprintf($message[0], array_slice($message, 1));
        $this->send_email('support@cleantalk.org', $subject, $message, 'support@cleantalk.org', false);
    }

    private function _jsonError($message) {
        http_response_code(505);
        echo($message);
        exit;
    }

    private function _remove_unpublished(){
        $files = $this->storage->findForRemove();
        if(!empty($files)){
            foreach ($files as $file) {
                $file->remove();
            }
        }
    }
}
