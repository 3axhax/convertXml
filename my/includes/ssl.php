<?php

//include '../includes/helpers/storage.php';

class Ssl extends CCS {
    const STATUS_CHECKOUT   = 'CHECKOUT';   // Необходимо оплатить сертификат
    const STATUS_WAIT_USER  = 'WAIT_USER';  // Ожидает команды пользователя на выписку сертификата в CA
    const STATUS_READY_CA   = 'READY_CA';   // Готов к выполнению API запроса к CA
    const STATUS_WAIT_CA    = 'WAIT_CA';    // Запрос к CA сделан, ожидание результата
    const STATUS_ERROR      = 'ERROR';      // Ошибка обращения к CA
    const STATUS_ISSUED     = 'ISSUED';     // Сертификат выписан

    /**
     * @var array Информация о доступных запросах к Comodo API
     */
    private $comodoRequests = array(
        'GetDCVEmailAddressList' => array(
            'url' => 'https://secure.comodo.net/products/!GetDCVEmailAddressList',
            'response' => 'text'
        ),
        'ResendDCVEmail' => array(
            'url' => 'https://secure.comodo.net/products/!ResendDCVEmail',
            'response' => 'url-encoded'
        )
    );

    /**
     * @var array Данные для статусов сертификатов
     */
    private $statuses = array(
        self::STATUS_CHECKOUT => array(
            'id' => self::STATUS_CHECKOUT,
            'text' => 'Checkout',
            'hint' => 'Purchase the certificate',
            'link' => '/my/bill/ssl',
            'class' => 'btn-primary',
            'allow_delete' => true
        ),
        self::STATUS_WAIT_USER => array(
            'id' => self::STATUS_WAIT_USER,
            'text' => 'Activate',
            'hint' => 'Click on the button',
            'link' => '/my?activate=:CERT_ID:',
            'class' => 'btn-success',
            'allow_delete' => false
        ),
        self::STATUS_READY_CA => array(
            'id' => self::STATUS_READY_CA,
            'text' => 'Activated',
            'hint' => 'The certificate will be issued within 3-5 minutes, you will get a notice to :DCV_EMAIL_ADDRESS:.',
            'class' => 'text-success',
            'allow_delete' => false
        ),
        self::STATUS_WAIT_CA => array(
            'id' => self::STATUS_WAIT_CA,
            'text' => 'Awaiting full validation',
            'hint' => 'Please see the instructions on email that has been sent to :DCV_EMAIL_ADDRESS: (<a href="#" data-id=":CERT_ID:" data-name=":COMMON_NAME:" class="dcv-link">change email</a>).',
            'class' => 'text-warning',
            'allow_delete' => false
        ),
        self::STATUS_ERROR => array(
            'id' => self::STATUS_ERROR,
            'text' => 'Something wrong',
            'hint' => 'Please open a <a href="/my/support">support ticket</a> or <a href="#" class="text-danger delete-link" id="delete_:CERT_ID:" data-id=":CERT_ID:">delete</a>.',
            'class' => 'text-danger',
            'allow_delete' => true
        ),
        self::STATUS_ISSUED => array(
            'id' => self::STATUS_ISSUED,
            'text' => 'Active',
            'hint' => '<a href="/help/install-SSL-certificate" target="_blank">SSL Certificate setup manual</a>',
            'class' => 'text-success',
            'allow_delete' => false
        )
    );

    /**
     * @var array Параметры шаблона для текущей страницы.
     */
    private $template = array(
        'layout'    => 'includes/general.html',
        'page'      => 'ssl/index.html',
        'scripts'   => array(),
        'styles'    => array()
    );

    /**
     * @var array Коллекция штатов США для генератора CSR.
     */
    private $states = array(
        "AL" => "Alabama", "AK" => "Alaska", "AS" => "American Samoa",
        "AZ" => "Arizona", "AR" => "Arkansas", "CA" => "California",
        "CO" => "Colorado", "CT" => "Connecticut", "DE" => "Delaware",
        "DC" => "District Of Columbia", "FM" => "Federated States Of Micronesia", "FL" => "Florida",
        "GA" => "Georgia", "GU" => "Guam", "HI" => "Hawaii",
        "ID" => "Idaho", "IL" => "Illinois", "IN" => "Indiana",
        "IA" => "Iowa", "KS" => "Kansas", "KY" => "Kentucky",
        "LA" => "Louisiana", "ME" => "Maine", "MH" => "Marshall Islands",
        "MD" => "Maryland", "MA" => "Massachusetts", "MI" => "Michigan",
        "MN" => "Minnesota", "MS" => "Mississippi", "MO" => "Missouri",
        "MT" => "Montana", "NE" => "Nebraska", "NV" => "Nevada",
        "NH" => "New Hampshire", "NJ" => "New Jersey", "NM" => "New Mexico",
        "NY" => "New York", "NC" => "North Carolina", "ND" => "North Dakota",
        "MP" => "Northern Mariana Islands", "OH" => "Ohio", "OK" => "Oklahoma",
        "OR" => "Oregon", "PW" => "Palau", "PA" => "Pennsylvania",
        "PR" => "Puerto Rico", "RI" => "Rhode Island", "SC" => "South Carolina",
        "SD" => "South Dakota", "TN" => "Tennessee", "TX" => "Texas",
        "UT" => "Utah", "VT" => "Vermont", "VI" => "Virgin Islands",
        "VA" => "Virginia", "WA" => "Washington", "WV" => "West Virginia",
        "WI" => "Wisconsin", "WY" => "Wyoming"
    );

    /**
     * @var array Требуемые поля для CSR
     */
    private $requiredCSRFields = array(
        'countryName', 'stateOrProvinceName', 'localityName', 'organizationName', 'commonName'
    );

    /**
     * @var CCS
     */
    private $ccs;

    /**
     * @var bool|string Flash сообщение
     */
    private $flash = false;

    public function __construct($ccs) {
        $this->ccs = $ccs;
        if (isset($_COOKIE['flash_message'])) {
            $this->flash = $_COOKIE['flash_message'];
            setcookie('flash_message', null, -1, '/');
        }
    }

    public function show_page() {
        $this->ccs->get_lang($this->ccs->ct_lang, 'Ssl');
        foreach ($this->ccs->lang['l_ssl_statuses'] as $key => $status) {
            $this->statuses[$key]['text'] = $status['text'];
            $this->statuses[$key]['hint'] = $status['hint'];
        }

        switch ($this->ccs->link->id) {
            // Главная страница
            case 2:
                $this->index();
                break;
            // Добавление сертификата
            case '21':
                $this->certificate();
                break;
        }

        $this->ccs->smarty_template = $this->template['layout'];
        $this->ccs->page_info['template']  = $this->template['page'];
        $this->ccs->page_info['scripts'] = $this->template['scripts'];
        $this->ccs->page_info['flash_message'] = $this->flash;
        //$this->ccs->page_info['keep_certificate_data_default_days'] = $this->ccs->options['keep_certificate_data_default_days']; // НЕ ЗАБЫТЬ УБРАТЬ

        $this->ccs->smarty->assign($this->ccs->page_info);
        $this->ccs->display();
    }

    /**
     * Главная страница.
     */
    protected function index() {
        $this->template['page'] = 'ssl/index.html';
        $this->ccs->page_info['head']['title'] = $this->ccs->lang['l_ssl_dashboard_title'];
        $this->ccs->page_info['container_fluid'] = true;

        if (isset($_GET['activate']) && preg_match('/^\d+$/', $_GET['activate'])) {
            $this->activate($_GET['activate']);
        }

        if (isset($_GET['delete']) && preg_match('/^\d+$/', $_GET['delete'])) {
            $this->delete($_GET['delete']);
        }

        if (isset($_GET['dcv']) && preg_match('/^\d+$/', $_GET['dcv'])) {
            $this->dcv($_GET['dcv']);
        }

        if (isset($_POST['dcv']) && preg_match('/^\d+$/', $_POST['dcv']) && isset($_POST['email'])) {
            $this->dcvChange($_POST['dcv'], $_POST['email']);
        }

        if (isset($_GET['check']) && preg_match('/^\d+$/', $_GET['check'])) {
            $row = $this->ccs->db->select(sprintf("SELECT cert_id, ct_status, emailAddress, dcvEmailAddress, domains FROM ssl_certs WHERE cert_id = %d", $_GET['check']));
            echo(json_encode($this->status($row['ct_status'], $row)));
            exit;
        }

        if ($rows = $this->ccs->db->select(sprintf("SELECT * FROM ssl_certs WHERE user_id = %d ORDER BY cert_id DESC", $this->ccs->user_info['user_id']), true)) {
            $storage = new Storage($this->ccs->db);

            $licenses = array();
            if (isset($this->ccs->user_info['licenses']['ssl'])) {
                foreach ($this->ccs->user_info['licenses']['ssl'] as $license) {
                    if ($license['multiservice_id']) {
                        $created_ts = strtotime($license['created']);
                        $expires_ts = strtotime($license['valid_till']);
                        $licenses[$license['multiservice_id']] = array(
                            'created_ts' => $created_ts,
                            'expires_ts' => $expires_ts,
                            'created'    => date('M d, Y', $created_ts),
                            'expires'    => date('M d, Y', $expires_ts)
                        );
                    }
                }
            }

            $certificates = array();
            $processed = array();
            foreach ($rows as $row) {
                $certificate = array(
                    'id' => $row['cert_id'],
                    'domains' => $row['domains'],
                    'name' => $row['name'],
                    'created' => (isset($licenses[$row['cert_id']]) ? $licenses[$row['cert_id']]['created'] : '-'),
                    'expires' => (isset($licenses[$row['cert_id']]) ? $licenses[$row['cert_id']]['expires'] : '-'),
                    'years' => $this->ccs->lang['l_ssl_years'][$row['years']]
                );

                // Иконка сайта
                $favicon_url = 'http://' . $row['domains'] . '/favicon.php';
                if ($favicon = $storage->findByLabel('favicon_ssl_' . $row['cert_id'])) {
                    if ($favicon->isExpired(86400 * 3)) {
                        $favicon->replace($favicon_url);
                    }
                } else {
                    try {
                        $favicon = $storage->upload($favicon_url, 'ssl' . $row['cert_id'] . '.ico', 'db');
                        $favicon['label'] = 'favicon_ssl_' . $row['cert_id'];
                    } catch (Exception $e) {
                        $favicon = $storage->upload('https://cleantalk.org/favicon.ico', 'ssl' . $row['cert_id'] . '.ico', 'db');
                        $favicon['label'] = 'favicon_ssl_' . $row['cert_id'];
                    }
                }
                $certificate['favicon'] = $favicon->base64();

                // Статус
                switch ($row['ct_status']) {
                    case self::STATUS_WAIT_CA:
                    case self::STATUS_READY_CA:
                        $certificate['status'] = $this->status($row['ct_status'], $row);
                        $processed[$row['cert_id']] = $row['ct_status'];
                        break;
                    default:
                        $certificate['status'] = $this->status($row['ct_status'], $row);
                        break;
                }

                //CSR + Private key
                if(!empty($row['csr']) && !empty($row['private_key'])){
                    $certificate['csr'] = $row['csr'];
                    $certificate['key'] = $row['private_key'];
                }

                $certificates[] = $certificate;
            }
            $this->ccs->page_info['certificates'] = $certificates;
            $this->ccs->page_info['processed'] = json_encode($processed);
        }
    }

    /**
     * Активация сертификата пользователем.
     * Меняет статус сертификата с WAIT_USER на READY_CA.
     *
     * В случае успеха устанавливает флеш-сообщение, что сертификат успешно активирован.
     * Возвращает на главную страницу.
     *
     * @param integer $cert_id Идентификатор сертификата
     */
    protected function activate($cert_id) {
        $certificate = $this->ccs->db->select(sprintf("SELECT * FROM ssl_certs WHERE user_id = %d AND cert_id = %d", $this->ccs->user_info['user_id'], $cert_id));
        if ($certificate && $certificate['ct_status'] == self::STATUS_WAIT_USER) {
            $this->ccs->db->run(sprintf("UPDATE ssl_certs SET ct_status = '%s' WHERE cert_id = %d", self::STATUS_READY_CA, $cert_id));
            $this->redirect('/my', '<div class="alert alert-success" id="flash-message">' . $this->ccs->lang['l_ssl_msg_successful_activated'] . '</div>');
        }
        $this->redirect('/my');
    }

    /**
     * Удаление сертификата пользователем.
     * Для удаления доступны только сертификаты со статусами ERROR или CHECKOUT.
     *
     * В случае успеха устанавливает флеш-сообщение, что сертификат успешно удалён.
     * Возвращает на главную страницу.
     *
     * @param integer $cert_id Идентификатор сертификата.
     */
    protected function delete($cert_id) {
        $certificate = $this->ccs->db->select(sprintf("SELECT * FROM ssl_certs WHERE user_id = %d AND cert_id = %d", $this->ccs->user_info['user_id'], $cert_id));
        if ($certificate && in_array($certificate['ct_status'], array(self::STATUS_ERROR, self::STATUS_CHECKOUT))) {
            $this->ccs->db->run(sprintf("DELETE FROM ssl_certs WHERE cert_id = %d", $cert_id));
            $this->ccs->db->run(sprintf("UPDATE users_licenses SET multiservice_id = 0 WHERE multiservice_id = %d", $cert_id));
            $this->redirect('/my', '<div class="alert alert-danger" id="flash-message">' . $this->ccs->lang['l_ssl_msg_deleted'] . '</div>');
        }
        $this->redirect('/my');
    }

    /**
     * Возвращает список доступных email для валидации в JSON формате.
     * Метод доступен только для сертификатов со статусом WAIT_CA.
     *
     * @param integer $cert_id Идентификатор сертификата.
     */
    protected function dcv($cert_id) {
        $certificate = $this->ccs->db->select(sprintf("SELECT * FROM ssl_certs WHERE user_id = %d AND cert_id = %d", $this->ccs->user_info['user_id'], $cert_id));
        if ($certificate && $certificate['ct_status'] == self::STATUS_WAIT_CA) {
            $dcvEmails = $this->comodo_GetDCVEmailAddressList($certificate['domains']);
            echo(json_encode($dcvEmails));
            exit;
        }
        $this->jsonError('Internal Server Error');
    }

    /**
     * Производит изменение dcvEmailAddress сертификата со статусом WAIT_CA.
     *
     * @param integer $cert_id Идентификатор сертификата
     * @param string $email Новый email
     */
    protected function dcvChange($cert_id, $email) {
        $certificate = $this->ccs->db->select(sprintf("SELECT * FROM ssl_certs WHERE user_id = %d AND cert_id = %d", $this->ccs->user_info['user_id'], $cert_id));
		if ($certificate && $certificate['ct_status'] == self::STATUS_WAIT_CA) {
			if (isset($certificate['ca_orderNumber']) && !$this->comodo_ResendDCVEmail($certificate, $email)) {
                $this->redirect('/my', '<div class="alert alert-danger" id="flash-message">Server Error!</div>');
			}
            $this->ccs->db->run(sprintf(
                "UPDATE ssl_certs SET dcvEmailAddress = %s, ct_status = %s WHERE cert_id = %d",
                $this->ccs->stringToDB($email),
                $this->ccs->stringToDB(self::STATUS_READY_CA),
                $cert_id
            ));
            $this->redirect('/my', '<div class="alert alert-success" id="flash-message">' . $this->ccs->lang['l_ssl_msg_email_changed'] . '</div>');
        }
        $this->redirect('/my', '<div class="alert alert-danger" id="flash-message">' . $this->ccs->lang['l_ssl_msg_error'] . '</div>');
    }

    /**
     * Интерфейс создания сертификата.
     */
    protected function certificate() {
        $this->template['scripts'][] = '/my/js/validator.min.js';
        $this->template['page'] = 'ssl/certificate/index.html';

        if ((!empty($_POST) && isset($_POST['mode'])) || (!empty($_GET['sf_mode']) && $_GET['sf_mode']=='email')) {
            $response = false;
            $request = $this->ccs->safe_vars($_POST);
            if(!empty($_GET['sf_mode']) && $_GET['sf_mode']=='email'){
                $_POST['mode'] = 'email';
            }
            switch ($_POST['mode']) {
                case 'generator':
                    $response = $this->certificateGenerator($request);
                    break;
                case 'csr':
                    $response = $this->csrCheck($request);
                    break;
                case 'download':
                    $this->download($request);
                    break;
                case 'email':
                    $this->dcvEmail($request);
                    $this->ccs->page_info['head']['title'] = $this->ccs->lang['l_ssl_title_email'];
                    return;
                case 'create':
                    $this->certificateCreate($request);
                    break;
            }
            if (!$response) $response = $this->ccs->lang['l_ssl_msg_error'];
            if (is_string($response)) $this->jsonError($response);

            header('Content-Type: application/json');
            echo(json_encode($response));
            exit;
        }

        $this->ccs->page_info['head']['title'] = $this->ccs->lang['l_ssl_title_add'];

        $this->ccs->page_info['countries'] = $this->ccs->countries;
        $this->ccs->page_info['states'] = $this->states;
    }

    /**
     * Запрос на проверку CSR.
     * При валидных данных возвращает массив:
     *     commonName: String,
     *     emailAddress: String,
     *     organizationName: String,
     *     organizationUnitName: String,
     *     countryName: String,
     *     stateOrProvinceName: String,
     *     localityName: String
     *
     * @param $request array Данные запроса
     * @return array|string Результат выполнения
     */
    protected function csrCheck($request) {
        return array(
            'data' => $this->checkCSR($request['data']),
            'csr' => $request['data']
        );
    }

    /**
     * Запрос на создание CSR и приватного ключа.
     * При успешном создании и проверке CSR возвращается массив с данными:
     *     data: {
     *         commonName: String,
     *         emailAddress: String,
     *         organizationName: String,
     *         organizationalUnitName: String,
     *         countryName: String,
     *         stateOrProvinceName: String,
     *         localityName: String
     *     },
     *     csr: String,
     *     key: String
     *
     * В случае ошибки возвращается строка с текстом ошибки.
     *
     * @param $request array Данные запроса
     * @return array|string Результат создания
     */
	protected function certificateGenerator($request) {
        $request['common_name'] = strtolower($request['common_name']);
        $request['email'] = strtolower($request['email']);
        $dn = array(
            'commonName' => $request['common_name'],
            'organizationName' => html_entity_decode($request['organization']),
            'countryName' => $request['country'],
            'localityName' => $request['locality']
        );
        if ($request['country'] != 'US'){
            if(!empty($request['state_text'])){
                $dn['stateOrProvinceName'] = $request['state_text'];
            }
        }else{
            $dn['stateOrProvinceName'] = $request['state'];
        }
        if (!empty($request['organization_unit'])) $dn['organizationalUnitName'] = $request['organization_unit'];
        if (!empty($request['email'])) $dn['emailAddress'] = $request['email'];
        $privateKey = openssl_pkey_new();
        $csr = @openssl_csr_new($dn, $privateKey);

        @openssl_csr_export($csr, $csr_out);
        @openssl_pkey_export($privateKey, $privateKey_out);

        $errors = array();
        while(($e = openssl_error_string()) !== false) {
            if (strpos($e, 'NCONF_get_string') !== false || strpos($e, 'ASN1_OBJECT') !== false) continue;
            $errors[] = $e;
        }

        if (empty($errors)) {
            $csr = $this->checkCSR($csr_out);

            if (is_string($csr)) {
                $response = $csr;
            } else {
                $response = array(
                    'data' => $csr,
                    'csr' => $csr_out,
                    'key' => $privateKey_out
                );
            }
        } else {
            $response = implode('<br>', $errors);
        }

        return $response;
    }

    /**
     * Запрос на выбор email для подтверждения прав на владение доменом.
     *
     * @param array $request
     */
    protected function dcvEmail($request) {
        // Подгружаем данные для пользователей после авторизации/регистраци
        if(!empty($_GET['sf_mode'])){
            if($request = apc_fetch('dcvEmail_'.$this->ccs->user_info['user_id'])){
                apc_delete('dcvEmail_'.$this->ccs->user_info['user_id']);
            }
        }
        
        $csr = $this->checkCSR($request['csr']);
        if (is_string($csr)) {
            $this->redirect('/my/service', $csr);
        }

        // Проверяем авторизацию пользователя
        if (!$this->check_access(null, true)) {
            if (!$this->app_mode) {
                // Проверяем емайл с лендинга на существование
                if( ($row = $this->ccs->db->select(sprintf("SELECT user_id FROM users WHERE email = %s", $this->ccs->stringToDB($csr['emailAddress'])))) && !empty($row['user_id'])){ 
                    // отправляем на авторизацию
                    apc_store('dcvEmail_'.$row['user_id'], $request, 60*60);
                    $this->redirect('/my/session?back_url=/service?sf_mode=email&email='.$csr['emailAddress']);
                    exit();
                }else{ // Делаем регистрацию для пользователей с лендинга
                    $product_name = 'ssl_certificate';
                    include __DIR__.'/../../includes/register.php';
                    $register = new Register();
                    $tools = new CleanTalkTools();
                    $_POST['year'] = date('Y');
                    $_POST['email'] = $csr['emailAddress'];
                    $register->app_mode = $this->app_mode;
                    $register->product_name = 'ssl';
                    $register->cookie_domain = $tools->get_cookie_domain();
                    $reg_result = $register->register_via_api($product_name, array('disable_redirect'=>1,'lead_source'=>'ssl_landing'));
                    if( ($row = $this->ccs->db->select(sprintf("SELECT * FROM users WHERE email = %s", $this->ccs->stringToDB($csr['emailAddress'])))) && !empty($row['user_id'])){ 
                        $this->ccs->set_session_auth_params($row);
                        apc_store('dcvEmail_'.$row['user_id'], $request, 60*60);
                    }
                    $this->redirect('/my/service?sf_mode=email');
                    exit();
                }
            }
        }

        if ($request['send'] == 'yes' && !empty($request['key'])) {
            $this->sendFiles($csr, array(
                'csr' => $request['csr'],
                'key' => $request['key']
            ));
        }

		$dcvEmails = $this->comodo_GetDCVEmailAddressList($csr['commonName']);

		//
		// Generates own list of emails to validate the domain.
		//
		if (!is_array($dcvEmails) || count($dcvEmails) == 0) {
			foreach (explode(",", cfg::ssl_default_validation_list) as $v) {
				$dcvEmails[] = sprintf("%s@%s",
					$v,
					$csr['commonName']
				);
			}
		}
        if($_SERVER['HTTP_HOST']=='polygon.cleantalk.org' && $this->ccs->user_info['email']=='hp@cleantalk.org'){
            $dcvEmails[] = 'support@cleantalk.org';
        }

        $this->template['page'] = 'ssl/certificate/email.html';
        $this->ccs->page_info['dcv'] = $dcvEmails;
        $this->ccs->page_info['csr'] = $request['csr'];
        $this->ccs->page_info['key'] = $request['key'];
        $this->ccs->page_info['keep'] = $request['keep'];
        $this->ccs->page_info['commonName'] = $csr['commonName'];
    }

    /**
     * Создание сертфиката (последний шаг).
     *
     * @param array $request
     */
    protected function certificateCreate($request) {
        $csr = $this->checkCSR($request['csr']);
        if (is_string($csr)) {
            $this->redirect('/my/service', $csr);
        }

        // Значения по-умолчанию
        $ca_product = 287;
        $ca_name = 'Positive SSL';
        $ca_years = 1;

        // Если уже есть купленная лицензия на сертификаты
        $license = $this->getFreeLicense();
        if ($license) {
            if (isset($license['tariff']['ssl_type_id']) && $ssl_type = $this->ccs->db->select(sprintf("SELECT * FROM ssl_certs_types WHERE ssl_type_id = %d", $license['tariff']['ssl_type_id']))) {
                $ca_product = $ssl_type['ca_product'];
                $ca_name = $ssl_type['ca_name'];
            }
            if ($bill = $this->ccs->db->select(sprintf("SELECT period FROM bills WHERE bill_id = %d", $license['bill_id']))) {
                $ca_years = $bill['period'];
            }
        }

        $certificate = array(
            'user_id' => $this->ccs->user_info['user_id'],
            'years' => $ca_years,
            'domains' => $this->ccs->stringToDB($csr['commonName']),
            'ca_product_id' => $ca_product,
            'name' => $this->ccs->stringToDB($ca_name),
            'organizationName' => $this->ccs->stringToDB($csr['organizationName']),
            'streetAddress1' => "''",
            'streetAddress2' => "''",
            'streetAddress3' => "''",
            'localityName' => $this->ccs->stringToDB($csr['localityName']),
            'stateOrProvinceName' => $this->ccs->stringToDB($csr['stateOrProvinceName']),
            'postalCode' => "''",
            'countryName' => $this->ccs->stringToDB($csr['countryName']),
            'emailAddress' => (!empty($csr['emailAddress'])) ? $this->ccs->stringToDB($csr['emailAddress']) : $this->ccs->stringToDB($this->ccs->user_info['email']),
            'dcvEmailAddress' => $this->ccs->stringToDB($request['email']),
            'serverSoftware' => -1,
            'csr' => $this->ccs->stringToDB(trim($request['csr'])),
            'ca_api_return' => $this->ccs->stringToDB($request['response']),
            'ct_status' => $this->ccs->stringToDB(($license ? self::STATUS_READY_CA : self::STATUS_CHECKOUT))
        );

        if(!empty($request['keep']) && $request['keep']==1){
            $certificate['private_key'] = $this->ccs->stringToDB($request['key']);
            $certificate['keep_certificate_data'] = intval($this->ccs->options['keep_certificate_data_default_days']);
        }

        $certificate_id = $this->ccs->db->run(sprintf(
            "INSERT INTO ssl_certs (`%s`) VALUES (%s)",
            implode('`, `', array_keys($certificate)),
            implode(', ', array_values($certificate))
        ));

        if ($certificate_id) {
            if ($license) {
                $this->ccs->db->run(sprintf("UPDATE users_licenses SET multiservice_id = %d WHERE id = %d", $certificate_id, $license['id']));
                apc_delete($this->ccs->account_label);
            }
            $this->redirect('/my');
        } else {
            $this->redirect('/my/service', $this->ccs->lang['l_ssl_msg_error']);
        }
    }

    /**
     * Скачивание архива с запросом и ключом.
     *
     * @param array $request
     */
    protected function download($request) {
        $csr = $this->checkCSR($request['csr']);
        if (is_string($csr)) {
            $this->redirect('/my/service', $csr);
        }

        $domain = str_replace('.', '_', $csr['commonName']);
        $filenames = array(
            'archive' => $domain . '.zip',
            'csr' => $domain . '.csr',
            'key' => $domain . '.key'
        );
        $file = tempnam('tmp', 'zip');

        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFromString($filenames['csr'], $request['csr']);
        $zip->addFromString($filenames['key'], $request['key']);
        $zip->close();

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filenames['archive'] . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Control: must-revalidate');
        header('Content-Length: ' . filesize($file));

        readfile($file);
        unlink($file);
        exit;
    }

    /**
     * Проверяем наличие свободных лицензий.
     *
     * @return bool|array Свободная лицензия или false
     */
    private function getFreeLicense() {
        if (!isset($this->ccs->user_info['licenses']['ssl'])) return false;
        $licenses = $this->ccs->user_info['licenses']['ssl'];
        $license = false;
        foreach ($licenses as $l) {
            if ($l['multiservice_id'] == 0) {
                $license = $l;
                break;
            }
        }
        return $license;
    }

    /**
     * Метод проверяет на валидность Certificate signing request.
     * Возвращает массив с данными если проверка прошла успешно
     * и строку с ошибкой в противном случае.
     *
     * @param $data string CSR
     * @return array|string Result
     */
    private function checkCSR($data) {
        if ($csr = openssl_csr_get_subject($data, false)) {
            $errors = array();

            foreach ($this->requiredCSRFields as $field) {
                if (!isset($csr[$field]) || empty($field) || $csr[$field] == 'none') {
                    $errors[] = sprintf($this->ccs->lang['l_ssl_errors']['required'], $field);
                }
            }
            if (!isset($this->ccs->countries[$csr['countryName']])) {
                $errors[] = sprintf($this->ccs->lang['l_ssl_errors']['country'], $csr['countryName']);
            }
            if (substr($csr['commonName'], 0, 2) == '*.') {
                $errors[] = $this->ccs->lang['l_ssl_errors']['commonName'];
            }
            if (substr($csr['commonName'], 0, 4) == 'www.') {
                $errors[] = $this->ccs->lang['l_ssl_errors']['www'];
            }
            if (empty($errors)) {
                if (substr($csr['commonName'], 0, 4) != 'www.') {
                    $csr['domains'] = array($csr['commonName'], 'www.' . $csr['commonName']);
                } else {
                    $csr['domains'] = array(substr($csr['commonName'], 4), $csr['commonName']);
                }
                return $csr;
            }

            return implode('<br>', $errors);
        }
        return $this->ccs->lang['l_ssl_errors']['csr'];
    }

    /**
     * Выполняет запрос к API Comodo на получение доступных адресов валидации.
     *
     * @link https://secure.comodo.net/api/pdf/latest/GetDCVEmailAddressList.pdf
     * @param string $domain Домен
     * @return array Список доступных адресов
     */
    private function comodo_GetDCVEmailAddressList($domain) {
        $response = $this->comodo_request('GetDCVEmailAddressList', array('domainName' => $domain));

        if (isset($response['error'])) {
            return array();
        }

        $emails = array();
        for ($i = 1; $i < count($response['response']); $i++) {
            $line = explode("\t", $response['response'][$i]);
            if ($line[0] == 'whois_email' || $line[0] == 'level2_email') {
                if ($this->ccs->valid_email($line[1])) {
                    $emails[] = $line[1];
                }
            }
        }
        return $emails;
    }

    /**
     * Выполняет запрос к API Comodo на смену адреса валидации.
     *
     * @link https://secure.comodo.net/api/pdf/latest/ResendDCVEmail.pdf
     * @param array $cert Данные сертификата
     * @param string $email Новый email из списка GetDCVEmailAddressList
     * @return bool Результат выполнения
     */
    private function comodo_ResendDCVEmail($cert, $email) {
        $response = $this->comodo_request('ResendDCVEmail', array(
            'orderNumber' => $cert['ca_orderNumber'],
            'dcvEmailAddress' => $email
        ));
//var_dump($response);exit;
        return !isset($response['error']);
    }

    /**
     * Производит указанный запрос к API Comodo.
     * Ответ возвращается в виде ассоциативного массива.
     *
     * Для ошибок:
     *     ['error' => ['code' => NUMBER, 'message' => STRING]]
     *
     * Для успешного результата:
     *     ['response' => [...]]
     *
     * Содержимое response зависит от выполняемого запроса:
     *     - массив строк если ответ приходит в текстовом виде
     *     - ассоциативный массив для ответов в формате url-encoded
     *
     * @param string $requestName Имя запроса
     * @param array $parameters Список параметров, которые необходимо отправить в запросе
     * @param bool|integer $saveResult Если определён, то идентификатор сертификата для записи ответа в БД
     * @return array
     */
    private function comodo_request($requestName, $parameters, $saveResult = false) {
        $parameters['loginName'] = cfg::comodo_login;
        $parameters['loginPassword'] = cfg::comodo_password;
        $context = stream_context_create(array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($parameters)
            )
        ));

        $response = @file_get_contents($this->comodoRequests[$requestName]['url'], false, $context);
        preg_match('/.+([0-9]{3}).+/', $http_response_header[0], $matches);
        $response_code = $matches[1];
        $response_text = ($response_code !== '200') ? '' : $response_code . "\n" . $response;

        $result = false;

        if ($response_code !== '200') {
            $result = array(
                'error' => array(
                    'code' => '200',
                    'message' => 'Response status code: ' . $response_code
                )
            );
        } else {
            switch ($this->comodoRequests[$requestName]['response']) {
                case 'text':
                    $response = explode("\n", $response);
                    if ($response[0] == '0') {
                        $result = array(
                            'response' => array_slice($response, 1),
                            'db' => $response_text
                        );
                    } else {
                        $result = array(
                            'error' => array(
                                'code' => $response[0],
                                'message' => $response[1]
                            ),
                            'db' => $response_text
                        );
                    }
                    break;
                case 'url-encoded':
                    parse_str($response, $output);
                    $response = $output;
                    if ($response['errorCode'] == '0') {
                        $result = array(
                            'response' => $response,
                            'db' => $response_text
                        );
                    } else {
                        $result = array(
                            'error' => array(
                                'code' => $response['errorCode'],
                                'message' => $response['errorMessage']
                            ),
                            'db' => $response_text
                        );
                    }
                    break;
            }
        }

        if (!$result) {
            $result = array(
                'error' => array(
                    'code' => '666',
                    'message' => 'Internal Server Error'
                ),
                'db' => $response_text
            );
        }

        if (isset($result['error'])) $this->comodo_error($result['error']['code'], $result['error']['message']);

        return $result;
    }

    /**
     * Обработчик ошибок Comodo.
     *
     * @param integer $errorCode Код ошибки
     * @param string $errorMessage Сообщение
     */
    private function comodo_error($errorCode, $errorMessage) {
        $this->ccs->post_log(str_replace("'", '"', "Произошла ошибка при запросе к API Comodo: $errorCode:$errorMessage"));
    }

    /**
     * Отправляет файлы CSR и приватного ключа на почту пользователя.
     *
     * @param array $csr Информация из CSR
     * @param array $files Данные для файлов
     */
    private function sendFiles($csr, $files) {
        $title = sprintf($this->ccs->lang['l_ssl_mail_title'], $csr['commonName']);

        $message = sprintf($this->ccs->lang['l_ssl_mail_message'], $csr['commonName']);

        $EOL = "\r\n";
        $boundary   = "--" . md5(uniqid(time()));
        $headers    = "MIME-Version: 1.0;$EOL";
        $headers   .= "Content-Type: multipart/mixed; boundary=\"$boundary\"$EOL";
        $headers   .= "From: <support@cleantalk.org>";

        $multipart  = "--$boundary$EOL";
        $multipart .= "Content-Type: text/html; charset=utf-8$EOL";
        $multipart .= "Content-Transfer-Encoding: base64$EOL";
        $multipart .= $EOL;
        $multipart .= chunk_split(base64_encode($message));

        foreach ($files as $key => $value) {
            $filename = str_replace('.', '_', $csr['commonName']) . '.' . $key;
            $multipart .=  "$EOL--$boundary$EOL";
            $multipart .= "Content-Type: application/octet-stream; name=\"$filename\"$EOL";
            $multipart .= "Content-Transfer-Encoding: base64$EOL";
            $multipart .= "Content-Disposition: attachment; filename=\"$filename\"$EOL";
            $multipart .= $EOL;
            $multipart .= chunk_split(base64_encode($value));
        }

        $multipart .=  "$EOL--$boundary$EOL";

        mail($csr['emailAddress'], $title, $multipart, $headers);
    }

    /**
     * Возвращает текстовое описание статуса сертификата.
     *
     * @param string $status Статус
     * @param array $parameters Параметры для подстановки
     * @return array
     */
    private function status($status, $parameters) {
        $status = $this->statuses[$status];
        $status['hint'] = str_replace(
            array(':CERT_ID:', ':EMAIL_ADDRESS:', ':DCV_EMAIL_ADDRESS:', ':COMMON_NAME:'),
            array($parameters['cert_id'], $parameters['emailAddress'], $parameters['dcvEmailAddress'], $parameters['domains']),
            $status['hint']
        );
        if (isset($status['link'])) $status['link'] = str_replace(':CERT_ID:', $parameters['cert_id'], $status['link']);
        return $status;
    }

    /**
     * Генерирует ошибку в JSON формате.
     *
     * @param string $message Текст ошибки
     */
    private function jsonError($message) {
        http_response_code(505);
        echo($message);
        exit;
    }

    /**
     * Делает перенаправление на указанный URL.
     *
     * @param string $url Адрес для перенаправления
     * @param bool|string $message Флеш-сообщение
     */
    private function redirect($url, $message = false) {
        if ($message) {
            setcookie('flash_message', $message, time() + 3600, '/');
        }
        header('Location: ' . $url);
        exit;
    }
}
