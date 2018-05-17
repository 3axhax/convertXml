<?php
require_once __DIR__.'/../../includes/helpers/s3.php';
require_once __DIR__.'/../libs/simple_html_dom.php';


/**
 * Класс для обработки favicon
 */
Class Favicon extends CCS {

	const update_url_prefix = '/my/favicon?host=';
	const icon_expire       = '3 month';
	private static $allowed_mime = ['image/vnd.microsoft.icon', 'image/png', 'image/gif', 'image/x-icon'];
	private static $allowed_size = 50000;

    public $engine = array('api','bitrix','dle','dotnet','drupal','drupal8','ios','ipboard','ipboard4','joomla','joomla3','joomla15','magento','magento2','mediawiki','opencart','perl','php','phpbb','phpbb3','phpbb31','python','ruby','smf','typo3','vbulletin','vbulletin5','woocommerce','wordpress','xenforo','yii');

	function __construct() {
        if (!$this->db) {
            parent::__construct();
            $this->ccs_init(true);
        }
	}

	/**
      * Формируем favicon URL по записи из таблицы services
      *
      * @param string $row
      *
      * @return string $url 
      */
	static function get_icon_url($row){
		$url = false;
		if(isset($row['hostname']) && !empty($row['hostname'])){
			if(isset($row['favicon_url']) && !empty($row['favicon_url'])){
				if(isset($row['favicon_update']) && !empty($row['favicon_update'])){
					$favicon_update = new DateTime($row['favicon_update']);
					$favicon_update->modify('+'.self::icon_expire);
					if($favicon_update->getTimestamp() > time()){
						$url = $row['favicon_url'];
					}else{
						$url = self::update_url_prefix.$row['hostname'];
					}					
				}else{
					$url = self::update_url_prefix.$row['hostname'];
				}
			}else{
				$url = self::update_url_prefix.$row['hostname'];
			}
		}
		return $url;
	}

	/**
      * Загрузка favicon
      *
      * @param string $hostname
      *
      * @return array|false 
      */
	static function get($hostname){
		$icon_url = false;
		$return = false;
		//Загружаем по http://$hostname/favicon.ico
		$favicon_url = 'http://'.$hostname.'/favicon.ico';
		$headers = @get_headers($favicon_url); // Получаем заголовки
		if(is_array($headers)){
			$code = (int)substr($headers[0], 9, 3); // Определяем код ответа
			if($code<400){ // Загружаем
				if (self::filter_favicon($favicon_url)) $return['data'] = @file_get_contents($favicon_url);
				if($return['data']){
					$return['ext'] = 'ico';
					return $return;
				}
			}
			$return = false;
			//Загружаем по link rel="icon"
			$html = @file_get_html('http://'.$hostname.'/'); // Загружаем код страницы
			if($html){
				foreach($html->find('link[rel*=icon]') as $element) // Ищем ссылку на иконку
					$icon_url = $element->href;
			}
		}
		if($icon_url){ // Конвертируем ссылку из относительной в абсолютную
			$url_parts = parse_url($icon_url);
			if(!isset($url_parts['host'])){
				$url_parts['host'] = $hostname;
			}
			if(substr($url_parts['path'], 0,1)=='/'){
				$url_parts['path'] = substr($url_parts['path'], 1);
			}
			$icon_url = 'http://'.$url_parts['host'].'/'.$url_parts['path'];

            if (self::filter_favicon($icon_url)) $return['data'] = @file_get_contents($icon_url); // Загружаем по ссылке
			if($return['data']){
				$return['ext'] = pathinfo($icon_url,PATHINFO_EXTENSION);
				return $return;
			}
		}
		return $return;		
	}

	public function show_page(){
		$host = $_GET['host'];
		$host = mysql_real_escape_string($host);
		if(empty($host)){
			header('Location: /favicon.ico');
			exit;
		}
		$icon = self::get($host);
		if($icon){	
			$s3 = new S3Storage('favicons/');		
			$favicon_url = isset($icon['ext']) ? $s3->replace($icon['data'],$host.'.'.$icon['ext']) : $s3->replace($icon['data'],$host.'.ico');
			if($favicon_url){
				$favicon_url = mysql_real_escape_string($favicon_url);				
			}
		}else{ //Если нет иконки, установим по движку
			$sql = sprintf('SELECT engine FROM services WHERE hostname = %s LIMIT 1;', $this->stringToDB($host));
			$rows = $this->db->select($sql);
			if(isset($rows['engine'])){
				$engine = $rows['engine'];
				if(in_array($engine, $this->engine)){
					$favicon_url = '/images/'.$engine.'.ico';
				}
			}
		}
		if(!isset($favicon_url)){ // Иконка по умолчанию
			$favicon_url = '/favicon.ico';
		}
		$sql = sprintf("UPDATE services SET favicon_url = %s, favicon_update=NOW() WHERE hostname = %s;",
            $this->stringToDB($favicon_url),
            $this->stringToDB($host)
        );
		$this->db->run($sql);
		header('Location: '.$favicon_url);
	}

	public static function filter_favicon($path) {
        //if (in_array(mime_content_type($path), self::$allowed_mime) && (self::f_size($path) <= self::$allowed_size)) return true;
        if (!(($size = self::f_size($path)) <= self::$allowed_size)) return 'big size: '.$size;
        if (!in_array($mime = self::f_mime($path), self::$allowed_mime)) return 'wrong mime type: "'.$mime.'"';
        return 'Ok; size: "'.$size.'"; mime type: "'.$mime.'"';
    }

    public static function f_size($url)
    {
        $parse = parse_url($url);
        $host = $parse['host'];
        $fp = @fsockopen ($host, 80, $errno, $errstr, 20);
        if(!$fp){
            $ret = self::$allowed_size+1;
        }else{
            $host = $parse['host'];
            fputs($fp, "HEAD ".$url." HTTP/1.1\r\n");
            fputs($fp, "HOST: ".$host."\r\n");
            fputs($fp, "Connection: close\r\n\r\n");
            $headers = "";
            while (!feof($fp)){
                $headers .= fgets ($fp, 128);
            }
            fclose ($fp);
            $headers = strtolower($headers);
            $array = preg_split("|[\s,]+|",$headers);
            $key = array_search('content-length:',$array);
            $ret = $array[$key+1];
        }
        if($array[1]==200) return $ret;
        else return self::$allowed_size+1;
    }

    public static function f_mime($url)
    {
        $file = 'TmpImgFile';

        file_put_contents($file, file_get_contents($url));
        $mime_type = mime_content_type($file);
        if (file_exists($file)) unlink($file);
        return $mime_type;
    }
}