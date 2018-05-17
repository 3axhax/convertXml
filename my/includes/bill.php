<?php
// Sets config file path and registers the classloader
use PayPal\CoreComponentTypes\BasicAmountType;
use PayPal\EBLBaseComponents\DoExpressCheckoutPaymentRequestDetailsType;
use PayPal\EBLBaseComponents\PaymentDetailsType;
use PayPal\PayPalAPI\DoExpressCheckoutPaymentReq;
use PayPal\PayPalAPI\DoExpressCheckoutPaymentRequestType;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsReq;
use PayPal\PayPalAPI\GetExpressCheckoutDetailsRequestType;
use PayPal\Service\PayPalAPIInterfaceServiceService;
use PayPal\EBLBaseComponents\AddressType;
use PayPal\EBLBaseComponents\BillingAgreementDetailsType;
use PayPal\EBLBaseComponents\PaymentDetailsItemType;
use PayPal\EBLBaseComponents\SetExpressCheckoutRequestDetailsType;
use PayPal\PayPalAPI\SetExpressCheckoutReq;
use PayPal\PayPalAPI\SetExpressCheckoutRequestType;

require("PayPal/PPBootStrap.php");

/**
 * Класс для работы со счетами пользователей
 */
class Bill extends CCS {
    /**
    * @var array Массив значений оценки
    */
    public $bonus_rate = array (
                        3 => 0,
                        6 => 0.05,
                        9 => 0.07,
                        12 => 0.10,
                        );

    /**
    * @var string
    */
    public $pp_auto_bill = cfg::pp_auto_bill;

    /**
    * @var string
    */
    public $pp_account = cfg::pp_account_inc;

    /**
    * @var string URL для оплаты заказа
    */
    private $pp_checkout_url = 'pp_checkout_url';

    function __construct(){
		parent::__construct();

		$this->ccs_init();
        if ($this->ct_lang == 'ru') {
            $this->pp_account = cfg::pp_account_ooo;
        }

        if($this->cp_mode == 'api'){ // Оплата Database API на pp_account_inc
            $this->pp_account = cfg::pp_account_inc;
        }

        if (cfg::pp_mode === 'sandbox') {
            $this->pp_account = cfg::pp_account_inc_sandbox;
            if ($this->ct_lang == 'ru') {
                $this->pp_account = cfg::pp_account_ooo_sandbox;
            }
            if ($this->cp_mode == 'api') {
                $this->pp_account = cfg::pp_account_inc_sandbox;
            }
        }
 	}

    /**
     * Функция показа страницы
     */
	function show_page() {
        // Отключаем счетчик бонусов на странице оплаты дабы он не отвлека внимание.
        $this->show_bonuses_count = false;

        if ($this->options['show_currencies']) {
            $this->get_currencies();
        }
        if (isset($this->user_id)) {
            $this->pp_checkout_url .= ':' . $this->user_id;
        } else {
            $this->pp_checkout_url = null;
        }
        if(cfg::pp_mode=='sandbox') {
            $this->page_info['stripe_public_key'] = cfg::stripe_test_public_key;
        }

        // Вызываем функцию страницы автоматически, либо переходим к предыдущей логике
        if (!$this->call_class_function()) {
            switch ($this->link->id){
                case 12:
                    $this->recharge_pm();
                case 27:
                    if (isset($_GET['get_act']) && isset($_GET['bill_id'])) {
                        $this->get_printed_docs($_GET['bill_id'], 'act');
                        break;
                    }
                    if (isset($_GET['get_invoice']) && isset($_GET['bill_id'])) {
                        $this->get_printed_docs($_GET['bill_id'], 'invoice');
                        break;
                    }
                    switch ($this->cp_mode) {
                        case 'ssl':
                            $this->get_lang($this->ct_lang, 'Ssl');
                            break;
                    }
                    $this->show_payments();
                    break;
                case 51:
                    $this->custom();
                    break;
                default:
                    if (isset($_GET['change_currency']) && preg_match("/^[A-Z]{3}$/", $_GET['change_currency']) && !$this->staff_mode) {
                        if (isset($this->user_info['currency']) && $this->user_info['currency'] == $_GET['change_currency']) {
                            echo('true');
                            exit;
                        }
                        $row = $this->db->select(sprintf("select currency from currency where currency = %s;", $this->stringToDB($_GET['change_currency'])));
                        if ($row) {
                            $this->db->run(sprintf("update users set currency = %s where user_id = %d;",
                                $this->stringToDB($_GET['change_currency']),
                                $this->user_info['user_id']
                            ));
                            echo('true');
                        } else {
                            echo('false');
                        }
                        exit;
                    }
                    switch ($this->link->template) {
                        case 'bill/api.html':
                            if ($this->cp_mode != 'api') $this->url_redirect('bill/api?cp_mode=api');
                            $this->api();
                            break;
                        case 'bill/hosting.html':
                            if ($this->cp_mode != 'hosting-antispam') $this->url_redirect('bill/hosting?cp_mode=hosting-antispam');
                            $this->hosting();
                            break;
                        case 'bill/security.html':
                            if ($this->cp_mode != 'security') $this->url_redirect('bill/security?cp_mode=security');
                            $this->security();
                            break;
                        case 'bill/ssl.html':
                            if ($this->cp_mode != 'ssl') $this->url_redirect('bill/ssl?cp_mode=ssl');
                            $this->ssl();
                            break;
                        case 'bill/ssl/success.html':
                            if ($this->cp_mode != 'ssl') $this->url_redirect('bill/ssl/success?cp_mode=ssl');
                            $this->ssl_success();
                            break;
                        default:
                            $this->recharge();
                            break;
                    }
                    break;
		    }
        }
        $this->page_info['hide_submenu'] = true;
		$this->display();
	}

	protected function currencies_prepare($default_currency = 'USD') {
        $currencies = array();
        if ($rows = $this->db->select(sprintf("SELECT * FROM currency WHERE datediff(now(), updated) <= %d ORDER BY currency ASC", $this->options['currency_max_rate_age']), true)) {
            foreach ($rows as $row) {
                $currencies[$row['currency']] = array(
                    'id' => $row['currency'],
                    'sign' => $row['currency_sign'],
                    'rate' => $row['usd_rate'],
                    'title' => sprintf('%s %s', $row['currency'], $row['currency_sign']),
                    'selected' => false
                );
            }

            $currency = 'USD';
            if (isset($_GET['currency']) && isset($currencies[$_GET['currency']])) {
                $currency = $_GET['currency'];
            } else if ($this->ct_lang == 'ru') {
                $currency = 'RUB';
            }
			$this->currencyCode = $currency;
            $currencies[$currency]['selected'] = true;
            $this->row_cur = array('currency' => $currency, 'usd_rate' => $currencies[$currency]['rate'], 'currency_sign' => $currencies[$currency]['sign']);
        } else {
            $this->currencyCode = 'USD';
            $default_currency = 'USD';
            $this->row_cur = array('currency' => 'USD', 'usd_rate' => 1, 'currency_sign' => '&#36;');
            $currencies = array('USD' => array('id' => 'USD', 'sign' => '&#36;', 'rate' => 1, 'title' => 'USD &#36;'));
        }
        if ($this->ct_lang == 'ru' || !count($currencies)) {
            $this->page_info['currencies'] = false;
        } else {
            $this->page_info['currencies'] = $currencies;
            $this->page_info['currencies_json'] = json_encode($currencies);
        }
        $this->page_info['currency_default'] = $default_currency;
    }

	protected function _bill_prepare($bill_uri, $early_pay = false, $custom_bill = false, $return_uri = array('/my/messages/pay_success', '/my/messages/pay_fail'), $is_test = false) {
        if (!$this->check_access()) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }
        $this->currencies_prepare($this->user_info['currency']);

        $paid_till_ts = 0;
        $bonus_months = 0;

        if (isset($this->user_info['license']) && !$custom_bill) {
            $license = $this->user_info['license'];
            $paid_till_ts = strtotime($license['valid_till']);

            // Бонус за оплату до окончания триала
            if ($early_pay && $license['trial'] && $paid_till_ts > time()) {
                if ($bonus = $this->db->select("SELECT free_months FROM bonuses WHERE bonus_name = 'early_pay'")) {
                    $bonus_months = (int)$bonus['free_months'];
                    if ($this->ct_lang == 'ru') {
                        $this->page_info['bonus_months_title'] = sprintf($this->lang['l_bill_bonus'], $bonus_months,
                            $this->number_lng($bonus_months, array('месяц', 'месяца', 'месяцев')));
                    } else {
                        $this->page_info['bonus_months_title'] = sprintf($this->lang['l_bill_bonus'], $bonus_months,
                            $this->number_lng($bonus_months, array('month', 'months')));
                    }
                }
            }
        }
        if ($paid_till_ts < time()) $paid_till_ts = time();
        $this->page_info['paid_till_ts'] = $paid_till_ts;
        $this->page_info['bonus_months'] = $bonus_months;

        // Оплата по PayPal
        if (
            (isset($_POST['pp_tariff_id']) && preg_match("/^\d+$/", $_POST['pp_tariff_id'])) &&
            (isset($_POST['pp_period']) && preg_match("/^\d+$/", $_POST['pp_period']))
        ) {
            $bill = $this->get_bill_new($_POST['pp_tariff_id'], $_POST['pp_period'], $paid_till_ts, $bonus_months);
            if ($bill && isset($bill['bill_id'])) {
                $this->pp_pay($bill['comment'], $bill[$this->cost_label], $bill['auto_bill'], $bill['period'], $bill['bill_id'], $bill_uri);
            }
            return true;
        }

        // Stripe
        if (
            isset($_POST['stripeToken']) &&
            (isset($_GET['product']) && preg_match("/^\d+$/", $_GET['product'])) &&
            (isset($_GET['period']) && preg_match("/^\d+$/", $_GET['period']))
        ) {
            $bill = $this->get_bill_new($_GET['product'], $_GET['period'], $paid_till_ts, $bonus_months);
            if ($bill && isset($bill['bill_id'])) {
                require_once('./stripe/lib/Stripe.php');
                if ($is_test || cfg::pp_mode == 'sandbox') Stripe::setApiKey(cfg::stripe_test_secret_key); else Stripe::setApiKey(cfg::stripe_secret_key);

                $token = $_POST['stripeToken'];
                $charge_success = true;
                try {
                    $charge = Stripe_Charge::create(array(
                        "amount" => $bill['cost_usd_cents'],
                        "currency" => "usd",
                        "card" => $token,
                        "description" => $bill['comment'],
                        "metadata" => array("bill_id" => $bill['bill_id'])
                    ));
                } catch (Stripe_CardError $e) {
                    error_log(print_r($e, true));
                    $charge_success = false;
                }

                $redirect_url = $return_uri[0].'?bill_id='.$bill['bill_id'];
                if (!$charge_success) {
                    $redirect_url = $return_uri[1];
                }
                header("Location:" . $redirect_url);
                exit;
            }
        }

        // Значения по-умолчанию
        $defaults = array(
            cfg::product_antispam => array(cfg::antispam_tariff_default, cfg::antispam_period_default),
            cfg::product_hosting_antispam => array(cfg::hosting_tariff_default, cfg::hosting_period_default),
            cfg::product_database_api => array(cfg::api_tariff_default, cfg::api_period_default),
            cfg::product_security => array(cfg::security_tariff_default, cfg::security_period_default),
            cfg::product_ssl => array(cfg::ssl_tariff_default, cfg::ssl_period_default)
        );
        if (isset($this->user_info['tariff']) && !isset($_GET['product'])) {
            $tariff_default = $this->user_info['tariff']['tariff_id'];
            $period_default = $defaults[$this->cp_product_id][1];
        } else {
            $tariff_default = (isset($_GET['product'])) ? $_GET['product'] : $defaults[$this->cp_product_id][0];
            $period_default = (isset($_GET['period'])) ? $_GET['period'] : $defaults[$this->cp_product_id][1];
        }
        if (isset($this->user_info['tariff_recommend']) && !isset($_GET['product'])) {
            $tariff_default = $this->user_info['tariff_recommend'];
        }

        // URL с меткой extended
        if (isset($_GET['extended']) && $_GET['extended'] == 1) {
            $return_result = null;
            $url_redirect = '/my';
            if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token'])) {
                if ($this->pp_auto_bill === true) $this->pp_new_profile($_GET['token']);
                if (
                    isset($_GET['PayerID']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['PayerID']) &&
                    isset($_GET['bill_id']) && preg_match("/^\d+$/", $_GET['bill_id'])
				) {
                    $return_result = 'success';
                    if ($this->pp_do_express_checkout($_GET['token'], $_GET['PayerID'], $_GET['bill_id'])) {
                        $url_redirect = $return_uri[0].'?bill_id='.$_GET['bill_id'];
                    } else {
                        $url_redirect = $return_uri[1];
                        $return_result = 'fail';
                    }
                }
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB($return_result),
                    $this->stringToDB($_GET['token'])
                ));
            }

            $this->url_redirect($url_redirect, null, false, false);
            return true;
        }

        // Результат возвращения с PayPal
        if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token']) && !isset($_GET['extended'])) {
            $bill_rate = $this->db->select(sprintf("select bill_id, return_result from bills_rate where pp_token = %s;",
                $this->stringToDB($_GET['token'])
            ));

            // Если результат до сих пор не известен, значит пользователь отменил платеж самостоятельно
            if ($bill_rate['return_result'] == null) {
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB('user_cancel'),
                    $this->stringToDB($_GET['token'])
                ));

                $bill = $this->db->select(sprintf("select fk_tariff, period from bills where bill_id=%d", $bill_rate['bill_id']));
                if ($bill) {
                    $tariff_default = $bill['fk_tariff'];
                    $period_default = $bill['period'];
                }
            }
        }

        // Наличие промо-кода
        $promo = false;
        if (isset($_COOKIE['promokey']) && preg_match('/^[a-z0-9\.\-а-я\ \_]{1,16}$/ui', $_COOKIE['promokey'])) {
            $promo = addslashes($_COOKIE['promokey']);
        }
        if (isset($_GET['promokey']) && preg_match('/^[a-z0-9\.\-а-я\ \_]{1,16}$/ui', $_GET['promokey'])) {
            $promo = addslashes($_GET['promokey']);
            setcookie('promokey', $promo, strtotime('+3 days'), '/', $this->cookie_domain);
        }
        if ($promo) {
            $row = $this->db->select(sprintf("SELECT * FROM promo WHERE promokey = %s ORDER BY expire DESC LIMIT 1", $this->stringToDB($promo)));
            if ($row && strtotime($row['expire']) > time() && $row['discount'] > 0) {
                $promo = $row['discount'];
            } else {
                $promo = false;
            }
        }

        // Загружаем тарифы
        $sql = sprintf("SELECT %s FROM tariffs WHERE product_id=%d AND allow_subscribe=1 ORDER BY cost_usd ASC;",
            implode(', ', array('tariff_id', 'calls_period', 'name', 'services', 'cost_usd', 'mpd')),
            $this->cp_product_id);
        $rows = $this->db->select($sql, true);
        $tariffs = array();
        foreach ($rows as $row) {
            // Пропускаем тарифы с меньшим количеством сервисов
            if (
                isset($this->user_info['license']) &&
                isset($this->user_info['services']) &&
                $this->user_info['services'] > $row['services'] &&
                $tariff_default != $row['tariff_id']
            ) {
                continue;
            }

            $cost = round($row['cost_usd'] * $this->row_cur['usd_rate']);
            if ($this->row_cur['usd_rate'] == 1 && $cost != $row['cost_usd']) $cost = $row['cost_usd'];
            if ($promo) $cost -= round($cost * $promo);

            $tariff_title = str_replace(
                array(':SERVICES:', ':CALLS:', ':COST:', ':COST_ROUND:', ':SIGN:', ':CURRENCY:'),
                array($row['services'], $row['mpd'], $cost, $cost, $this->row_cur['currency_sign'], $this->row_cur['currency']),
                $this->lang['l_bill_package_title']
            );
            if ($this->ct_lang == 'ru') {
                $tariff_title = str_replace(
                    array(':SERVICES_TITLE:', ':CALLS_TITLE:'),
                    array(
                        $this->number_lng($row['services'], $this->lang['l_num_services']),
                        $this->number_lng($row['mpd'], $this->lang['l_num_calls'])
                    ),
                    $tariff_title
                );
            } else {
                $tariff_title = str_replace(
                    array(':SERVICES_TITLE:', ':CALLS_TITLE:'),
                    array(
                        $this->number_lng($row['services'], $this->lang['l_num_services']),
                        $this->number_lng($row['mpd'], $this->lang['l_num_calls'])
                    ),
                    $tariff_title
                );
            }

            $tariffs[] = array(
                'id' => $row['tariff_id'],
                'title' => $tariff_title,
                'services' => $row['services'],
                'calls' => $row['mpd'],
                'cost' => $cost,
                'sign' => $this->row_cur['currency_sign'],
                'selected' => $tariff_default == $row['tariff_id']
            );
        }
        $this->page_info['currency'] = array(
            'id' => $this->row_cur['currency'],
            'sign' => $this->row_cur['currency_sign']
        );
        $this->page_info['l_num'] = json_encode(array(
            'services' => $this->lang['l_num_services'],
            'services2' => $this->lang['l_num_services_2'],
            'calls' => $this->lang['l_num_calls']
        ));
        $this->page_info['period'] = $period_default;
        $this->page_info['products'] = $tariffs;
        $this->page_info['products_json'] = json_encode($tariffs);
    }

    public function ssl() {
        $this->get_lang($this->ct_lang, 'Ssl');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'ssl/bill/index.html';
        $this->page_info['container_fluid'] = true;

        // Проверяем наличие свободной лицензии
        if (isset($this->user_info['licenses']['ssl'])) {
            $licenses = $this->user_info['licenses']['ssl'];
            $license = false;
            foreach ($licenses as $l) {
                if ($l['multiservice_id'] == 0) {
                    $license = $l;
                    break;
                }
            }
            if ($license) $this->page_info['modal_license'] = true;
        }

        $this->_bill_prepare('/my/bill/ssl', true, false, array(
            '/my/bill/ssl/success',
            '/my/messages/pay_fail'
        ));
        if(cfg::pp_mode=='sandbox')
            $this->page_info['stripe_public_key'] = cfg::stripe_test_public_key;
    }

    public function ssl_success() {
        $this->get_lang($this->ct_lang, 'Ssl');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'ssl/bill/success.html';
        $this->page_info['container_fluid'] = true;

        if (isset($_GET['check'])) {
            if ($row = $this->db->select(sprintf("SELECT id, bill_id FROM users_licenses WHERE product_id = %d AND user_id = %d AND multiservice_id = 0", cfg::product_ssl, $this->user_info['user_id']))) {
                apc_delete($this->account_label);
                // Если есть сертификат со статусом CHECKOUT
                if ($checkout = $this->db->select(sprintf("SELECT * FROM ssl_certs WHERE ct_status = 'CHECKOUT' AND user_id = %d", $this->user_info['user_id']))) {
                    $bill = $this->db->select(sprintf("SELECT period FROM bills WHERE bill_id = %d", $row['bill_id']));
                    $this->db->run(sprintf("UPDATE ssl_certs SET ct_status = 'WAIT_USER', years = %d WHERE cert_id = %d", $bill['period'], $checkout['cert_id']));
                    $this->db->run(sprintf("UPDATE users_licenses SET multiservice_id = %d WHERE id = %d", $checkout['cert_id'], $row['id']));
                    echo(json_encode(array(
                        'result' => 'view',
                        'cert_id' => $checkout['cert_id'],
                        'domain' => $checkout['domains']
                    )));
                } else {
                    echo(json_encode(array('result' => 'add')));
                }
            } else {
                echo('false');
            }
            exit;
        }

        if (isset($_GET['accept']) && preg_match('/^\d+$/', $_GET['accept'])) {
            if ($cert = $this->db->select(sprintf("SELECT * FROM ssl_certs WHERE cert_id = %d AND ct_status = 'WAIT_USER' AND user_id = %d", $_GET['accept'], $this->user_info['user_id']))) {
                $this->db->run(sprintf("UPDATE ssl_certs SET ct_status = 'READY_CA' WHERE cert_id = %d", $_GET['accept']));
                header('Location: /my');
                exit;
            }
        }
    }

	public function security() {
        $this->get_lang($this->ct_lang, 'Security');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'security/bill.html';
        $this->page_info['container_fluid'] = true;

        if (isset($this->user_info['tariff'])) {
            $recommend = $this->db->select(sprintf("SELECT tariff_id FROM tariffs WHERE product_id = %d AND services >= %d AND allow_subscribe = 1 ORDER BY services ASC LIMIT 1",
                cfg::product_security,
                $this->user_info['tariff']['services']
            ));
            if ($recommend) {
                $this->user_info['tariff_recommend'] = $recommend['tariff_id'];
            }
        }

        $this->_bill_prepare('/my/bill/security', true);
    }

    public function custom() {
        $this->get_lang($this->ct_lang, 'BillCustom');
        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'bill/custom.html';
        $this->page_info['container_fluid'] = true;

        if (!isset($_GET['id']) || !preg_match("/^\d+$/", $_GET['id'])) {
            $this->page_info['container_fluid'] = false;
            $this->page_info['error'] = $this->lang['l_error_not_found'];
            return;
        }

        // Оплата по PayPal
        if (
            (isset($_POST['pp_tariff_id']) && preg_match("/^\d+$/", $_POST['pp_tariff_id'])) &&
            (isset($_POST['pp_period']) && preg_match("/^\d+$/", $_POST['pp_period']))
        ) {
            $bill = $this->get_bill_custom($_GET['id']);
            if ($bill && isset($bill['bill_id'])) {
                $this->pp_pay($bill['comment'], $bill[$this->cost_label], $bill['auto_bill'], $bill['period'], $bill['bill_id'], '/my/bill/custom?id=' . $_GET['id']);
            }
            return true;
        }
        // Stripe
        if (
            isset($_POST['stripeToken'])
        ) {

			$bill = $this->get_bill_custom($_GET['id']);
            if ($bill && isset($bill['bill_id'])) {
                require_once('./stripe/lib/Stripe.php');
                Stripe::setApiKey(cfg::stripe_secret_key);

                $token = $_POST['stripeToken'];
                $charge_success = true;
                try {
                    $charge = Stripe_Charge::create(array(
                        "amount" => $bill['cost_usd_cents'],
                        "currency" => "usd",
                        "card" => $token,
                        "description" => $bill['comment'],
                        "metadata" => array("bill_id" => $bill['bill_id'])
                    ));
                } catch (Stripe_CardError $e) {
                    error_log(print_r($e, true));
                    $charge_success = false;
                }

                $redirect_url = '/my/messages/pay_success'.'?bill_id='.$bill['bill_id'];
                if (!$charge_success) {
                    $redirect_url = '/my/messages/pay_fail';
                }
                header("Location:" . $redirect_url);
                exit;
            }
        }

        // URL с меткой extended
        if (isset($_GET['extended']) && $_GET['extended'] == 1) {
            $return_result = null;
            $url_redirect = '/my';
            if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token'])) {
                if ($this->pp_auto_bill === true) $this->pp_new_profile($_GET['token']);
                if (
                    isset($_GET['PayerID']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['PayerID']) &&
                    isset($_GET['bill_id']) && preg_match("/^\d+$/", $_GET['bill_id'])
                ) {
                    $return_result = 'success';
                    if ($this->pp_do_express_checkout($_GET['token'], $_GET['PayerID'], $_GET['bill_id'])) {
                        $url_redirect = '/my/messages/pay_success'.'?bill_id='.$_GET['bill_id'];
                    } else {
                        $url_redirect = '/my/messages/pay_fail';
                        $return_result = 'fail';
                    }
                }
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB($return_result),
                    $this->stringToDB($_GET['token'])
                ));
            }

            $this->url_redirect($url_redirect, null, false, false);
            return true;
        }

        // Результат возвращения с PayPal
        if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token']) && !isset($_GET['extended'])) {
            $bill_rate = $this->db->select(sprintf("select bill_id, return_result from bills_rate where pp_token = %s;",
                $this->stringToDB($_GET['token'])
            ));

            // Если результат до сих пор не известен, значит пользователь отменил платеж самостоятельно
            if ($bill_rate['return_result'] == null) {
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB('user_cancel'),
                    $this->stringToDB($_GET['token'])
                ));

                $bill = $this->db->select(sprintf("select fk_tariff, period from bills where bill_id=%d", $bill_rate['bill_id']));
                if ($bill) {
                    $tariff_default = $bill['fk_tariff'];
                    $period_default = $bill['period'];
                }
            }
        }

        $bill_info = $this->db->select(sprintf("SELECT * FROM bills_custom WHERE id = %d", $_GET['id']));
        if (!$bill_info || ($bill_info['fk_user'] != $this->user_info['user_id'] && !$this->is_admin)) {
            $this->page_info['container_fluid'] = false;
            $this->page_info['error'] = $this->lang['l_error_not_found'];
            return;
        }
        $bill_info['comment_short'] = sprintf('Cleantalk payment, $%d', $bill_info['cost']);

        $row = $this->db->select(sprintf("SELECT fk_tariff FROM users WHERE user_id = %d", $this->user_info['user_id']));
        $this->page_info['tariff_id'] = $row['fk_tariff'];

        $this->page_info['bill_info'] = $bill_info;
    }

	public function hosting() {
        $this->page_info['bsdesign'] = true;
        $this->get_lang($this->ct_lang, 'Hoster');
        $this->currencies_prepare();

        if (!$this->check_access()) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }

        // Загружаем лицензию пользователя
        $sql = sprintf("SELECT ul.id as license_id, ul.max_calls, ul.valid_till FROM users_licenses ul LEFT JOIN tariffs t ON t.tariff_id = ul.fk_tariff WHERE ul.user_id = %d AND t.product_id = %d AND ul.moderate = 1",
            $this->user_info['user_id'],
            cfg::product_hosting_antispam);
        $license = $this->db->select($sql);
        if ($license) {
            $this->page_info['paid_till_ts'] = strtotime($license['valid_till']) < time() ? time() : strtotime($license['valid_till']);
        } else {
            $this->page_info['paid_till_ts'] = time();
        }

        // Оплата по PayPal
        if (
            (isset($_POST['pp_tariff_id']) && preg_match("/^\d+$/", $_POST['pp_tariff_id'])) &&
            (isset($_POST['pp_period']) && preg_match("/^\d+$/", $_POST['pp_period']))
        ) {
            $bill = $this->get_bill_api($_POST['pp_tariff_id'], $_POST['pp_period'], $this->page_info['paid_till_ts']);
            if ($bill && isset($bill['bill_id'])) {
                //var_dump($bill);
                //exit();
                $this->pp_pay($bill['comment'], $bill[$this->cost_label], $bill['auto_bill'], $bill['period'], $bill['bill_id'], '/my/bill/hosting');
            }
            return true;
        }

        // Stripe
        if (
            isset($_POST['stripeToken']) &&
            (isset($_GET['product']) && preg_match("/^\d+$/", $_GET['product'])) &&
            (isset($_GET['period']) && preg_match("/^\d+$/", $_GET['period']))
        ) {
            $bill = $this->get_bill_api($_GET['product'], $_GET['period'], $this->page_info['paid_till_ts']);
            if ($bill && isset($bill['bill_id'])) {
                require_once('./stripe/lib/Stripe.php');
                Stripe::setApiKey(cfg::stripe_secret_key);

                $token = $_POST['stripeToken'];
                $charge_success = true;
                try {
                    $charge = Stripe_Charge::create(array(
                        "amount" => $bill['cost_usd_cents'], // amount in cents, again
                        "currency" => "usd",
                        "card" => $token,
                        "description" => $bill['comment'],
                        "metadata" => array("bill_id" => $bill['bill_id'])
                    ));
                } catch (Stripe_CardError $e) {
                    error_log(print_r($e, true));
                    $charge_success = false;
                }

                $redirect_url = '/my/messages/pay_success'.'?bill_id='.$bill['bill_id'];
                if (!$charge_success) {
                    $redirect_url = '/my/messages/pay_fail';
                }
                header("Location:" . $redirect_url);
                exit;
            }
        }

        // Значения по-умолчанию
        $tariff_default = (isset($_GET['product'])) ? $_GET['product'] : cfg::hosting_tariff_default;
        $period_default = (isset($_GET['period'])) ? $_GET['period'] : cfg::hosting_period_default;

        // URL с меткой extended
        if (isset($_GET['extended']) && $_GET['extended'] == 1) {
            $return_result = null;
            $url_redirect = '/my';
            if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token'])) {
                if ($this->pp_auto_bill === true) $this->pp_new_profile($_GET['token']);
                if (
                    isset($_GET['PayerID']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['PayerID']) &&
                    isset($_GET['bill_id']) && preg_match("/^\d+$/", $_GET['bill_id'])
                ) {
                    $return_result = 'success';
                    if ($this->pp_do_express_checkout($_GET['token'], $_GET['PayerID'], $_GET['bill_id'])) {
                        $url_redirect = '/my/messages/pay_success'.'?bill_id='.$_GET['bill_id'];
                    } else {
                        $url_redirect = '/my/messages/pay_fail';
                        $return_result = 'fail';
                    }
                }
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB($return_result),
                    $this->stringToDB($_GET['token'])
                ));
            }

            $this->url_redirect($url_redirect, null, false, false);
            return true;
        }

        // Результат возвращения с PayPal
        if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token']) && !isset($_GET['extended'])) {
            $bill_rate = $this->db->select(sprintf("select bill_id, return_result from bills_rate where pp_token = %s;",
                $this->stringToDB($_GET['token'])
            ));

            // Если результат до сих пор не известен, значит пользователь отменил платеж самостоятельно
            if ($bill_rate['return_result'] == null) {
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB('user_cancel'),
                    $this->stringToDB($_GET['token'])
                ));

                $bill = $this->db->select(sprintf("select fk_tariff, period from bills where bill_id=%d", $bill_rate['bill_id']));
                if ($bill) {
                    $tariff_default = $bill['fk_tariff'];
                    $period_default = $bill['period'];
                }
            }
        }

        // Загружаем тарифы
        $sql = sprintf("SELECT %s FROM tariffs WHERE product_id=%d;",
            implode(', ', array('tariff_id', 'calls_period', 'name', 'services', 'cost_usd', 'allow_subscribe')),
            cfg::product_hosting_antispam);
        $rows = $this->db->select($sql, true);
        $tariffs = array();
        foreach ($rows as $row) {
            if ($row['allow_subscribe'] == '0') continue;
            if ($row['calls_period'] != 'month') continue;
            $cost = number_format($row['cost_usd'] * $this->row_cur['usd_rate'], 2, '.', '');
            $tariffs[] = array(
                'id' => $row['tariff_id'],
                'ips' => $row['services'],
                'cost' => $cost,
                'selected' => $tariff_default == $row['tariff_id']
            );
        }
        $this->page_info['currency'] = array(
            'id' => $this->row_cur['currency'],
            'sign' => $this->row_cur['currency_sign']
        );
        $this->page_info['period'] = $period_default;
        $this->page_info['products'] = $tariffs;
        $this->page_info['products_json'] = json_encode($tariffs);
        $this->page_info['template'] = 'bill/hosting.html';
    }

	public function api() {
        $this->page_info['bsdesign'] = true;
        $this->get_lang($this->ct_lang, 'Api');

        // Оплата Database API только в USD
        $this->currencyCode = 'USD';
        $this->row_cur = array('currency' => 'USD', 'usd_rate' => 1, 'currency_sign' => '&#36;');

        if (!$this->check_access()) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }

        if (strpos($_SERVER['HTTP_HOST'], 'polygon.cleantalk.org') !== false) {
            $this->page_info['stripe_public_key'] = cfg::stripe_test_public_key;
            $stripe_key = cfg::stripe_test_secret_key;
        } else {
            $stripe_key = cfg::stripe_secret_key;
        }

        // Загружаем лицензию пользователя
        $sql = sprintf("SELECT ul.id as license_id, ul.max_calls, ul.valid_till, ul.periods, ul.bill_id, ul.created, ul.balance, ul.pay_id, ul.fk_tariff, t.mpd FROM users_licenses ul LEFT JOIN tariffs t ON t.tariff_id = ul.fk_tariff WHERE ul.user_id = %d AND t.product_id = %d AND ul.moderate = 1",
            $this->user_info['user_id'],
            cfg::api_product_id);
        $license = $this->db->select($sql);
        if ($license) {
            $this->page_info['paid_till_ts'] = strtotime($license['valid_till']) < time() ? time() : strtotime($license['valid_till']);
        } else {
            $this->page_info['paid_till_ts'] = time();
		}
		
		$upgrade_discount = 0;
		if (isset($license['balance']) && $license['balance'] > 0) {
			$upgrade_discount = $license['balance'];
		}
//        var_dump($upgrade_discount, $this->row_cur, $license);exit;
        // Обновление лицензии возиожно если срок действия заканчивается более чем через 3 месяца
        // или указан параметр upgrade.
        $is_upgrade = false;
        /*
        if (isset($_GET['upgrade']) || ($this->page_info['paid_till_ts'] > strtotime('+3 month') && false)) {
			$is_upgrade = true;
			$upgrade_discount = 0;
            if (isset($license['bill_id']) && $license['bill_id']) {
                $last_bill = $this->db->select(sprintf("SELECT date FROM bills WHERE bill_id = %d", $license['bill_id']));
                $this->page_info['paid_till_ts'] = strtotime($last_bill['date']);
            } else {
                $this->page_info['paid_till_ts'] = strtotime($license['created']);
            }
            $this->page_info['valid_till_ts'] = strtotime($license['valid_till']);
            $this->page_info['l_api_bill_comment'] = $this->lang['l_api_bill_comment_0'];
        }*/

        // Конвертация остатка в USD по курсу валюты платежа
        if(isset($license['pay_id']) && $license['pay_id'] && $upgrade_discount){
            $rate = $this->db->select(sprintf("SELECT c.usd_rate FROM pays p LEFT JOIN currency c ON p.currency=c.currency WHERE p.pay_id=%d", $license['pay_id']));
            if(isset($rate['usd_rate']) && $rate['usd_rate']){
                $upgrade_discount = $upgrade_discount/$rate['usd_rate'];
            }
        }

        $this->page_info['is_upgrade'] = $is_upgrade;
        $this->page_info['upgrade_discount'] = $upgrade_discount;
        $this->page_info['upgrade_discount_human'] = number_format($upgrade_discount, 2, '.', '');
        $add_days = 0;
        

        // Оплата по PayPal
        if (
            (isset($_POST['pp_tariff_id']) && preg_match("/^\d+$/", $_POST['pp_tariff_id'])) &&
            (isset($_POST['pp_period']) && preg_match("/^\d+$/", $_POST['pp_period']))
        ) {
            if($upgrade_discount && isset($license['fk_tariff']) && strtotime($license['valid_till'])>time()){ // Определяем кол-во бонусных дней
                if($license['fk_tariff']==$_POST['pp_tariff_id']){ // Для текущего тарифа
                    $add_days = ceil((strtotime($license['valid_till'])-time()) / (60 * 60 * 24)); 
                }else{// Для других тарифов
                    $selected_tariff = $this->db->select(sprintf("SELECT cost_usd FROM tariffs WHERE tariff_id=%d", (int)$_POST['pp_tariff_id']));
                    $add_days = ceil($upgrade_discount/($selected_tariff['cost_usd']/30)); 
                }
            }
			$bill = $this->get_bill_api($_POST['pp_tariff_id'], $_POST['pp_period'], time()+$add_days*24*60*60, false, false, $add_days);
			
            if ($bill && isset($bill['bill_id'])) {
                $this->pp_pay($bill['comment'], $bill[$this->cost_label], $bill['auto_bill'], $bill['period'], $bill['bill_id'], '/my/bill/api');
            }
            return true;
        }

        // Stripe
        if (
            isset($_POST['stripeToken']) &&
            (isset($_GET['product']) && preg_match("/^\d+$/", $_GET['product'])) &&
            (isset($_GET['period']) && preg_match("/^\d+$/", $_GET['period']))
        ) {
            if($upgrade_discount && isset($license['fk_tariff']) && strtotime($license['valid_till'])>time()){ // Определяем кол-во бонусных дней
                if($license['fk_tariff']==$_GET['product']){ // Для текущего тарифа
                    $add_days = ceil((strtotime($license['valid_till'])-time()) / (60 * 60 * 24)); 
                }else{// Для других тарифов
                    $selected_tariff = $this->db->select(sprintf("SELECT cost_usd FROM tariffs WHERE tariff_id=%d", (int)$_GET['product']));
                    $add_days = ceil($upgrade_discount/($selected_tariff['cost_usd']/30)); 
                }
            }
            $bill = $this->get_bill_api($_GET['product'], $_GET['period'], time()+$add_days*24*60*60, false, false, $add_days);
            if ($bill && isset($bill['bill_id'])) {
                require_once('./stripe/lib/Stripe.php');
                Stripe::setApiKey($stripe_key);

                $token = $_POST['stripeToken'];
                $charge_success = true;
                try {
                    $charge = Stripe_Charge::create(array(
                        "amount" => $bill['cost_usd_cents'], // amount in cents, again
                        "currency" => "usd",
                        "card" => $token,
                        "description" => $bill['comment'],
                        "metadata" => array("bill_id" => $bill['bill_id'])
                    ));
                } catch (Stripe_CardError $e) {
                    error_log(print_r($e, true));
                    $charge_success = false;
                }

                $redirect_url = '/my/messages/pay_success'.'?bill_id='.$bill['bill_id'];
                if (!$charge_success) {
                    $redirect_url = '/my/messages/pay_fail';
                }
                header("Location:" . $redirect_url);
                exit;
            }
        }

        // Значения по-умолчанию
        $tariff_default = (isset($_GET['product'])) ? $_GET['product'] : cfg::api_tariff_default;
        $period_default = (isset($_GET['period'])) ? $_GET['period'] : cfg::api_period_default;

        // URL с меткой extended
        if (isset($_GET['extended']) && $_GET['extended'] == 1) {
            $return_result = null;
            $url_redirect = '/my';
            if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token'])) {
                if ($this->pp_auto_bill === true) $this->pp_new_profile($_GET['token']);
                if (
                    isset($_GET['PayerID']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['PayerID']) &&
                    isset($_GET['bill_id']) && preg_match("/^\d+$/", $_GET['bill_id'])
                ) {
                    $return_result = 'success';
                    if ($this->pp_do_express_checkout($_GET['token'], $_GET['PayerID'], $_GET['bill_id'])) {
                        $url_redirect = '/my/messages/pay_success'.'?bill_id='.$_GET['bill_id'];
                    } else {
                        $url_redirect = '/my/messages/pay_fail';
                        $return_result = 'fail';
                    }
                }
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB($return_result),
                    $this->stringToDB($_GET['token'])
                ));
            }

            $this->url_redirect($url_redirect, null, false, false);
            return true;
        }

        // Результат возвращения с PayPal
        if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token']) && !isset($_GET['extended'])) {
            $bill_rate = $this->db->select(sprintf("select bill_id, return_result from bills_rate where pp_token = %s;",
                $this->stringToDB($_GET['token'])
            ));

            // Если результат до сих пор не известен, значит пользователь отменил платеж самостоятельно
            if ($bill_rate['return_result'] == null) {
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB('user_cancel'),
                    $this->stringToDB($_GET['token'])
                ));

                $bill = $this->db->select(sprintf("select fk_tariff, period from bills where bill_id=%d", $bill_rate['bill_id']));
                if ($bill) {
                    $tariff_default = $bill['fk_tariff'];
                    $period_default = $bill['period'];
                }
            }
        }

        // Загружаем тарифы
        $sql = sprintf("SELECT %s FROM tariffs WHERE product_id=%d AND (allow_subscribe=1 OR allow_subscribe_panel=1) ORDER BY cost_usd;",
            implode(', ', array('tariff_id', 'calls_period', 'name', 'mpd', 'cost_usd', 'allow_subscribe')),
            cfg::api_product_id);
        $rows = $this->db->select($sql, true);
        $tariffs = array();

        $usd_rate = 1;
        if (isset($this->row_cur['usd_rate']) && false) { // Отключил поправку на локальную валюту, т.к. в интерфейсе нет вывода локальных валют.
            $usd_rate = $this->row_cur['usd_rate'];
		}

        $left_days = ($is_upgrade) ? floor(($this->page_info['valid_till_ts'] - time()) / 86400) : 0;
        foreach ($rows as $row) {
            if ($row['allow_subscribe'] == '0') continue;
            if ($row['calls_period'] != 'month') continue;
			if ($is_upgrade && $row['mpd'] <= $license['mpd']) continue;
			
			if ($upgrade_discount > $row['cost_usd'] && false) continue; // Отключил искючение тарифов по стоимости меньше текущего остатка.

            $cost = number_format($row['cost_usd'] * $usd_rate, 2, '.', '');
            $cost_total = 0;
            if ($is_upgrade) {
                // Стоимость на оставшийся период с учётом текущего баланса
				$cost_total = number_format(($cost / 31 * $left_days) - $license['balance'], 2, '.', '');
				if ($cost_total <= 0) continue;
            }
            $add_days = 0;
            // Определяем кол-во бонусных дней для всех тарифов
            if($upgrade_discount && isset($license['fk_tariff']) && $license['fk_tariff'] && strtotime($license['valid_till'])>time()){
                if($license['fk_tariff']==$row['tariff_id']){
                    $add_days = ceil((strtotime($license['valid_till'])-time()) / (60 * 60 * 24)); // Для текущего тарифа
                }else{
                    $add_days = ceil($upgrade_discount/($row['cost_usd']/30)); // Для других тарифов

                }
            }
            $tariffs[] = array(
                'id' => $row['tariff_id'],
                'calls' => $row['mpd'],
                'cost' => $cost,
                'cost_total' => $cost_total,
                'selected' => $tariff_default == $row['tariff_id'],
                'add_days' => $add_days
            );
        }
        /*$this->page_info['currency'] = array(
            'id' => $this->row_cur['currency'],
            'sign' => $this->row_cur['currency_sign']
	);*/
//		var_dump($tariffs, $left_days, $license, $upgrade_discount);exit;
        $this->page_info['currency'] = array(
            'id' => 'USD',
            'sign' => '$'
        );
        $this->page_info['period'] = $period_default;
        $this->page_info['products'] = $tariffs;
        $this->page_info['products_json'] = json_encode($tariffs);

    }

    /**
     * Функция страницы билинга с произвольным платежом
     *
     * @return null
     */
    public function billing() {
        if (!$this->check_access(false, true)) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
            return false;
        }

        if ($this->cp_mode == 'hosting-antispam') {
            $this->smarty_template = 'includes/general.html';
            $this->page_info['template'] = 'hoster/bill.html';
        }

        if (isset($_POST['bill_id']) && preg_match("/^\d+$/", $_POST['bill_id'])) {
            $bill = $this->get_bill_info($_POST['bill_id']);
            if ($bill !== false && isset($bill['bill_id'])) {
                $this->pp_pay($bill['comment'], $_POST['payment_sum'], $bill['auto_bill'], $bill['period'], $bill['bill_id']);
            }
            return true;
        }

        // Делаем списание через Stripe.
        if (isset($_REQUEST['stripeToken'])
            && isset($_REQUEST['bill_id']) && preg_match("/^\d+$/", $_REQUEST['bill_id'])
            && isset($_REQUEST['payment_sum']) && preg_match("/^\d+$/", $_REQUEST['payment_sum'])
        ) {
            $charge = true;

            $bill_id = $_REQUEST['bill_id'];
            $bill = $this->get_bill_info($bill_id, $this->user_id);
            if (!$bill) {
                $charge = false;
                error_log(sprintf('Not found bill by bill_id %d, file %s, line %d.',
                    $bill_id,
                    __FILE__,
                    __LINE__
                ));
            }
            $amount = $_REQUEST['payment_sum'] * 100;

            if ($charge) {
                require_once('./stripe/lib/Stripe.php');
                // Set your secret key: remember to change this to your live secret key in production
                // See your keys here https://dashboard.stripe.com/account
                Stripe::setApiKey(cfg::stripe_secret_key);

                // Get the credit card details submitted by the form
                $token = addslashes($_REQUEST['stripeToken']);

                // Create the charge on Stripe's servers - this will charge the user's card
                $charge_success = true;
                try {
                    $charge = Stripe_Charge::create(array(
                          "amount" => $amount, // amount in cents, again
                          "currency" => "usd",
                          "card" => $token,
                          "description" => $bill['comment'],
                          "metadata" => array("bill_id" => $bill['bill_id'])
                    ));
                } catch(Stripe_CardError $e) {
                    // The card has been declined
                    error_log(print_r($e, true));
                    $charge_success = false;
                }

                $redirect_url = '/my/messages/pay_success'.'?bill_id='.$bill['bill_id'];
                if (!$charge_success) {
                    $redirect_url = '/my/messages/pay_fail';
                }
                header("Location:" . $redirect_url);
                exit;
            }
        }

        if ($this->cp_mode != 'hosting-antispam') {
            $this->cp_mode = 'hosting-antispam';
            $this->page_info['cp_mode'] = $this->cp_mode;
            setcookie('cp_mode', $this->cp_mode, strtotime("+365 day"), '/');
        }

        $this->page_info['show_currencies'] = false;

        $tariff = $this->db->select(sprintf("select tariff_id, cost_usd from tariffs where hosting = 1 order by cost_usd asc limit 1;"));

        if (isset($tariff['cost_usd'])) {
            $cost_usd_base = $tariff['cost_usd'];
        } else {
            error_log(sprintf("Unset package for hosting antispam. File %s, line %s.", __FILE__, __LINE__));
            return;
        }

        $payment_range = null;
        foreach (explode(",", cfg::hosting_package_multiplicators) as $m) {
            $cost = $cost_usd_base * $m;
            $payment_range[$cost] = sprintf("%.2f USD", $cost);
        }

        $this->page_info['payment_range'] = $payment_range;

        // Третий параметр - количество периодов продления доступа, если 0 то положить деньги на баланс
        $bill = $this->get_bill($tariff['tariff_id'], false, 0);

        // История билинга
        $balance_usage = 0;
        $bh = null;
        $sql = sprintf("select year(date) as year, month(date) as month, sum(charge) as charges from billing_history_ips where user_id = %d group by month(date);", $this->user_id);
        $charges = $this->db->select($sql, true);
        foreach ($charges as $v) {
            if ($v['year'] == date("Y") && $v['month'] == date("m")) {
                $balance_usage = $v['charges'];
            }
            $record_ts = strtotime(sprintf("%s-%s-%s",
                $v['year'],
                $v['month'],
                '01'
            ));
            $bh[$record_ts]['date'] = date("M t Y", $record_ts);
            $bh[$record_ts]['amount'] = number_format($v['charges'], 2, '.', ' ');
            $bh[$record_ts]['comment'] = 'Invoice';
        }
        $this->page_info['balance_usage'] = number_format($balance_usage, 2, '.', ' ');

        $sql = sprintf("select date, gross from pays where fk_user = %d group by month(date);", $this->user_id);
        $payments = $this->db->select($sql, true);
        foreach ($payments as $v) {
            $record_ts = strtotime($v['date']);
            $bh[$record_ts]['date'] = date("M j Y", $record_ts);
            $bh[$record_ts]['amount'] = number_format($v['gross'], 2, '.', ' ');
            $bh[$record_ts]['comment'] = 'Payment';
        }

        krsort($bh);
        $this->page_info['bh'] = $bh;

        return null;
    }

    /**
     * Функция создает акт о выполненных работах.
     *
     * @param null $bill_id Номер счёта
     * @param string $type Тип акта
     * @return bool|null
     */
    public function get_printed_docs($bill_id = null, $type = 'act') {
        if (!$this->check_access(false, true)) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }

        if (!$bill_id || !preg_match("/^\d+$/", $bill_id)) {
            return false;
        }

        $bill = $this->db->select(sprintf("select bill_id, comment, cost, date, cost_usd from bills where bill_id = %d and fk_user = %d;",
            $bill_id,
            $this->user_id
        ));
        if (!isset($bill['bill_id'])) {
            return null;
        }
        $org = $this->db->select(sprintf("select org, org_address, org_inn, org_ogrn, org_ceo from users where user_id = %d;", $this->user_id));

        $tools = new CleanTalkTools();

        // Include the main TCPDF library (search for installation path).
        require_once('./tcpdf/tcpdf.php');

        // create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set font
        // dejavusans is a UTF-8 Unicode font, if you only need to
        // print standard ASCII chars, you can use core fonts like
        // helvetica or times to reduce file size.
        $pdf->SetFont('dejavusans', '', 10, '', true);

        // Add a page
        // This method has several options, check the source code documentation for more information.
        $pdf->setPrintHeader(false);
        $pdf->AddPage();

        $output_filename = "$bill_id.pdf";

        // Set some content to print
        if ($type == 'act') {
            $act_date = date("d-m-Y", strtotime($bill['date']));
            $act_client_info = sprintf($this->lang['l_act_org_tpl'],
                $org['org'],
                $org['org_address'],
                $org['org_inn'],
                $org['org_ogrn']
            );
            $act_comment = $bill['comment'];
            $act_cost = $bill['cost'];
            $act_cost_letters = $tools->num2str($bill['cost']);
            $act_client_ceo = $org['org_ceo'];
            if ($act_client_ceo && preg_match("/^(\w+)\s+(\w)\w+\s+(\w)\w+/ui", $act_client_ceo, $matches)) {
                $act_client_ceo = sprintf("%s %s.%s.", $matches[1], $matches[2], $matches[3]);
            }

            $html = sprintf($this->lang['l_act_tpl'],
                $bill_id,
                $act_date,
                $act_client_info,
                $bill_id,
                $act_comment,
                $act_cost,
                $act_cost,
                $act_cost,
                $act_cost,
                $act_cost_letters,
                $act_client_ceo
            );

            $output_filename = sprintf('act%d.pdf', $bill_id);
        }

        if ($type == 'invoice') {
            // Счета для иностранцев выставляем на Английском
            $this->get_lang('en', $this->link->class);

            $invoice_date = date("M, d Y", strtotime($bill['date']));
            $invoice_client_info = sprintf($this->lang['l_invoice_client_info_tpl'],
                $this->user_info['email'],
                $org['org'],
                $org['org_address']
            );
            $html = sprintf($this->lang['l_invoice_tpl'],
                $bill_id,
                $invoice_date,
                $invoice_client_info,
                $bill['comment'],
                $bill['cost_usd'],
                $bill['cost_usd'],
                $bill['cost_usd']
            );

            $output_filename = sprintf('invoice%d.pdf', $bill_id);
        }

        // Print text using writeHTMLCell()
        $pdf->writeHTML($html);

        // ---------------------------------------------------------

        // Close and output PDF document
        // This method has several options, check the source code documentation for more information.
        $pdf->Output($output_filename, 'D');
        $this->post_log(sprintf("Пользователю %s (%d) выдан документ %s.",
            $this->user_info['email'],
            $this->user_id,
            $output_filename
        ));
        exit;
    }

    /**
     * Функция вывода информации о платежах
     *
     * @return null
     */
	function show_payments(){
        if (!$this->check_access(false, false)) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }

        $this->smarty_template = 'includes/general.html';
        $this->page_info['template']  = 'global/payments.html';
        $this->page_info['container_fluid'] = true;
        $this->show_bonuses_count = true;

        $sql_datetime_show = sprintf('convert_tz(p.date, \'+%d:00\', \'%s:00\') as date', cfg::billing_timezone, $this->user_info['timezone']);
        $sql = sprintf("select 
                        p.pay_id, %s, b.cost, b.cost_usd, b.comment, b.fk_tariff, b.period, p.currency, b.bill_id, 
                        pr.promokey, pr.discount, pr.expire, t.product_id 
                        from pays p 
                        left join bills b on b.bill_id = p.fk_bill 
                        left join tariffs t on t.tariff_id = b.fk_tariff 
                        left join promo pr on b.promo_id_discount = pr.promo_id 
                        where p.fk_user = %d order by p.date desc;",
            $sql_datetime_show, $this->user_info['user_id']
        );
		$rows = $this->db->select($sql, true);

		foreach ($rows as $k => $v){
			if (strtoupper($v['currency']) === 'USD') {
				$v['cost_human'] = $v['cost_usd'];
            } else {
				$v['cost_human'] = $v['cost'];
            }

			$v['cost_human'] = number_format($v['cost_human'], 2, '.', ' ') . ' ' . $v['currency'];
			if ($v['promokey']) {
                $pomokey_part = sprintf('<a href="/my/bill/recharge?promokey=%s">%s</a>',
                    $v['promokey'],
                    $v['promokey']
                );
                $v['promocode_info'] = sprintf($this->lang['l_promocode_info'],
                    $pomokey_part,
                    $v['discount'] * 100,
                    date("M d Y", strtotime($v['expire']))
                );
            }

            switch ($v['product_id']) {
                case cfg::product_database_api:
                    $v['renew'] = sprintf('/my/bill/api?period=%d&product=%d', $v['period'], $v['fk_tariff']);
                    break;
                case cfg::product_hosting_antispam:
                    $v['renew'] = sprintf('/my/bill/hosting?period=%d&product=%d', $v['period'], $v['fk_tariff']);
                    break;
                case cfg::product_security:
                    $v['renew'] = sprintf('/my/bill/security?product=%d&currency=%s', $v['fk_tariff'], $v['currency']);
                    break;
                case cfg::product_ssl:
                    $v['renew'] = sprintf('/my/bill/ssl?product=%d&currency=%s', $v['fk_tariff'], $v['currency']);
                    break;
                default:
                    $v['renew'] = sprintf('/my/bill/recharge?cp_mode=antispam&tariff_id=%d&currency=&s&extra_package=1', $v['fk_tariff'], $v['currency']);
                    break;
            }

            $this->page_info['pays'][] = $v;
            
            $sql = sprintf("select bonus_id,bonus_name,user_id,activated,free_months,paid_till from users_bonuses where user_id = %d order by activated desc;",
                $this->user_id
            );
            $bonuses = $this->db->select($sql, true);
            foreach ($bonuses as $k => $v) {
                $bonus_name_label = 'l_' . $v['bonus_name'] . '_label';
                $v['bonus_name_display'] = $v['bonus_name'];
                if (isset($this->lang[$bonus_name_label])) {
                    $v['bonus_name_display'] = $this->lang[$bonus_name_label];
                }
                $v['activated_display'] = date("M d Y", strtotime($v['activated']));
                $v['paid_till_display'] = date("M d Y", strtotime($v['paid_till']));
                $bonuses[$k] = $v;
            }

            $this->page_info['bonuses_activated'] = $bonuses;
		}
    }

    /**
     * Функция продления доступ для интервальных тарифов
     *
     * @return mixed
     */
	function recharge_pm(){
        if (!$this->check_access())
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);

		if (isset($this->lang['l_extend_title']))
			$this->page_info['head']['title'] = $this->lang['l_extend_title'];

		$this->page_info['recharged'] = false;

		$tariff_id = isset($_GET['tariff_id']) && array_key_exists($_GET['tariff_id'], $this->premium_tariffs) ? addslashes($_GET['tariff_id']) : null;

        $this->show_offer(null, $tariff_id);

		// Завершаем работу если у пользовтеля не интервальный тариф
		// Завершаем работу если у пользовтеля остаток полезных сообщений больше нуля
		if(!isset($this->user_info['tariff']['pmi']) || $this->user_info['limit_pm'] > 0){
		    $this->page_info['positive_requests'] = sprintf($this->lang['l_positive_requests'], $this->user_info['limit_pm'], $this->user_info['tariff']['pmi']);
			return $this->page_info['recharged'];
		}

		// Если max_pm имеет значение, то инкрементируем его. Если нет, то присваем текущее количество
		// полезных сообщений + интервал по тарифу
		// !!! Отключил первое условие, для лояльности к пользователям.
		if (false && isset($this->user_info['max_pm']) && $this->user_info['max_pm'] > 0)
			$max_pm = $this->user_info['max_pm'] + $this->user_info['tariff']['pmi'];
		else
			$max_pm = $this->user_info['total_pm'] + $this->user_info['tariff']['pmi'];

		$this->page_info['extended_pm'] = 0;

		if ($this->db->run(sprintf("update users set max_pm = %d where user_id = %d;", $max_pm, $this->user_info['user_id']))){
			$this->post_log(sprintf(messages::pm_extended, $this->user_info['email'], $this->user_info['tariff']['pmi'], $max_pm));
			$this->page_info['extended_pm'] = $max_pm - $this->user_info['max_pm'];

			if ($this->user_info['requests_today'] <= $this->user_info['tariff']['mpd']){
				$this->db->run(sprintf("update users set moderate = 1, freeze = 0 where user_id = %d;", $this->user_info['user_id']));

                $tools = new CleanTalkTools();
				// Обновляем Memcache сервера
				$tools->store_userdata_at_mc($this->user_info['user_id']);

                /*
                 Записываем событие в таблицу статистики по приостановленным акаунтам
                */
                $freeze_row = $this->db->select(sprintf("select max(datetime) as freeze_time from users_freeze_stat where freeze = 1 and user_id = %d;", $this->user_id));

                // Подстраховка на случай рассинхронизации с биллинговой системой
                if ($freeze_row['freeze_time'] === null)
                   $freeze_row['freeze_time'] = $this->user_info['created'];

                $this->db->run(sprintf("insert into users_freeze_stat (user_id, tariff_id, datetime, datediff, max_pm, freeze) values (%d, %d, now(), datediff(now(), '%s'), %d, %d);", $this->user_id, $this->user_info['tariff']['tariff_id'], $freeze_row['freeze_time'], $max_pm, 0));
			}
		}

        // Продляем срок аренды сервиса
        // Активируем учетную запись
		if ($this->user_info['paid_till_ts'] < time() && isset($this->user_info['tariff']['pmi']) && $this->user_info['tariff']['cost'] == 0)
			$this->db->run(sprintf("update users set paid_till = from_unixtime(%d), inactive = 0 where user_id = %d;", time() + $this->user_info['tariff']['period'] * 86400, $this->user_info['user_id']));

        $this->show_offer($max_pm);

		$this->page_info['recharged'] = true;

		$this->page_info['positive_requests'] = sprintf($this->lang['l_positive_requests'], $this->user_info['tariff']['pmi'], $this->user_info['tariff']['pmi']);

		return $this->page_info['recharged'];
	}

	function recharge() {
		//
		// Fix to load page with correct CP_MODE.
		//
		if ($this->cp_mode != 'antispam' && !isset($_GET['cp_mode'])) {
			$params = array_merge($_GET, array("cp_mode" => "antispam"));
			$new_query_string = http_build_query($params);
			header("Location:?" . $new_query_string);
			exit;
		}

		$this->page_info['read_only'] = false;
		$this->page_info['second_top_button'] = false;
        $this->page_info['show_mobile_apps_top'] = false;
        $this->page_info['bsdesign'] = true;
        if ($this->user_token || ($this->user_id && $this->user_info['trial'] == -1)) {
            $this->page_info['show_websites'] = true;
        }

        if ($this->user_id && $this->user_info['trial'] == -1) {
            $this->page_info['show_websites'] = true;
        }

        if ($this->ct_lang == 'ru') {
            $this->currencyCode = 'RUB';
            $this->currencies_prepare();
        }

        if (isset($_POST['bill_id']) && preg_match("/^\d+$/", $_POST['bill_id'])) {
            $bill = $this->get_bill_info($_POST['bill_id']);
            if ($bill !== false && isset($bill['bill_id'])) {
                $this->pp_pay($bill['comment'], $bill[$this->cost_label], $bill['auto_bill'], $bill['period'], $bill['bill_id']);
            }

            return true;
        }

        if (isset($_GET['invoice']) && preg_match("/^\d+$/", $_GET['invoice'])) {
            $bill = $this->get_bill_info($_GET['invoice']);
            $org = $this->db->select(sprintf("SELECT org, org_address, org_inn, org_ogrn, org_ceo FROM users WHERE user_id = %d", $this->user_info['user_id']));

            require_once('./tcpdf/tcpdf.php');
            $tools = new CleanTalkTools();
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetFont('dejavusans', '', 10, '', true);
            $pdf->setPrintHeader(false);
            $pdf->AddPage();

            $invoice_date = date('d.m.Y');
            $invoice_cost_text = $tools->num2str($bill['cost']);
            $html = file_get_contents('./templates/bill/invoice.html');
            $html = str_replace(
                array(
                    ':ORG:', ':ADDRESS:', ':INN:', ':OGRN:',
                    ':BILL_ID:', ':BILL_DATE:', ':BILL_COMMENT:', ':BILL_COST:', ':BILL_COST_TEXT:'
                ),
                array(
                    $org['org'],
                    $org['org_address'],
                    $org['org_inn'],
                    $org['org_ogrn'],
                    $bill['bill_id'],
                    $invoice_date,
                    $bill['comment'],
                    number_format($bill['cost'], 2, '.', ' '),
                    mb_strtoupper(mb_substr($invoice_cost_text, 0, 1, 'utf-8'), 'utf-8') . mb_substr($invoice_cost_text, 1, mb_strlen($invoice_cost_text) - 1, 'utf-8')
                ),
                $html
            );

            $html = preg_replace(
                array('/\>[^\S ]+/s', '/[^\S ]+\</s', '/(\s)+/s', '/<!--(.|\s)*?-->/'),
                array('>', '<', '\\1', ''),
                $html
            );

            $pdf->writeHTML($html);
            $pdf->Image('images/invoice-stamp.png', 75, 210, 50, 50);
            $pdf->Image('images/invoice-signature.png', 120, 195, 25, 25);
            $pdf->Output(sprintf('invoice_%d.pdf', $bill['bill_id']), 'D');
            exit;
        }

        $this->page_info['service_recharged'] = sprintf($this->lang['l_service_recharged']);

        $return_result = null;

        // Если URL с меткой extended, то завершаем работу функции
		if (isset($_GET['extended']) && $_GET['extended'] == 1) {
            $url_redirect = null;
            if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token'])) {
                if ($this->pp_auto_bill === true)
                    $this->pp_new_profile($_GET['token']);

                if (
                    isset($_GET['PayerID']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['PayerID']) &&
                    isset($_GET['bill_id']) && preg_match("/^\d+$/", $_GET['bill_id'])
                ) {
                    $return_result = 'success';
                    if ($this->pp_do_express_checkout($_GET['token'], $_GET['PayerID'], $_GET['bill_id'])) {
                        $url_redirect = 'messages/pay_success'.'?bill_id='.$_GET['bill_id'];
                    } else {
                        $url_redirect = 'messages/pay_fail';
                        $return_result = 'fail';
                    }
                }
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB($return_result),
                    $this->stringToDB($_GET['token'])
                ));
            }

            // После оплаты возвращаем пользователя к инструкции на устновку плагина
            if (isset($_COOKIE['return_url']) && preg_match("/^[a-z0-9\=\-\?\&\_\/]+$/i", $_COOKIE['return_url'])) {
                setcookie('return_url', "", time() - 3600);
                header("Location:" . $_COOKIE['return_url']);
            }

            if ($url_redirect !== null)
                $this->url_redirect($url_redirect);

			return true;
        }

        // Записываем результат возвращения с paypal.com
        if (isset($_GET['token']) && preg_match("/^[A-Z0-9\-]+$/", $_GET['token']) && !isset($_GET['extended'])) {
            $bill_rate = $this->db->select(sprintf("select bill_id, return_result from bills_rate where pp_token = %s;",
                $this->stringToDB($_GET['token'])
            ));

            // Если результат до сих пор не известен, значит пользователь отменил платеж самостоятельно
            if (!$return_result && $bill_rate['return_result'] == null) {
                $return_result = 'user_cancel';
            }

            if ($return_result) {
                $this->db->run(sprintf("update bills_rate set return_datetime = now(), return_result = %s where pp_token = %s;",
                    $this->stringToDB($return_result),
                    $this->stringToDB($_GET['token'])
                ));
            }
        }

        $bill_row = null;
        $user_found = false;

        if (!$this->check_access()) {
            $this->url_redirect('session', null, true, null, $_SERVER['REQUEST_URI']);
        }

		$renew_options = array(
			1 => '1 месяц',
            2 => '2 месяца',
			3 => '3 месяца',
			6 => '6 месяцев',
			9 => '9 месяцев',
			12 => '12 месяцев',
		);

        if (isset($_GET['strict_tariff']) && $_GET['strict_tariff'] == 1)
            $this->page_info['strict_tariff'] = true;

        $period = cfg::min_paid;
		// Вычисляем период оплаты последнего платежа и предлагаем новый платеж на тот же период
		$sql = sprintf('select b.period from pays p left join bills b on p.fk_bill = b.bill_id left join tariffs t on t.tariff_id = b.fk_tariff where b.fk_user = %d and b.paid = 1 and t.product_id = %d group by b.period order by b.date desc;', 
			$this->user_info['user_id'],
			$this->cp_product_id
		);
		$last_bill = $this->db->select($sql);

		if (isset($last_bill['period']) && $last_bill['period'] > 0) {
			$period = $last_bill['period'];
		}

        $period = isset($_GET['renew_options']) && array_key_exists($_GET['renew_options'], $renew_options) ? addslashes($_GET['renew_options']) : $period;

		// Минимальный срок оплаты сервиса
		if ($period < cfg::min_paid || !array_key_exists($period, $renew_options)) {
			$period = cfg::default_period;
		}

        unset($renew_options[2]);
		$tariff_id = $this->user_info['fk_tariff'] == 1 ? $this->options['default_tariff_id'] : $this->user_info['fk_tariff'];
		$tariff_id = isset($_GET['tariff_id']) && array_key_exists($_GET['tariff_id'], $this->work_tariffs) ? addslashes($_GET['tariff_id']) : $tariff_id;
        $personal_tariff = false;
        if (isset($_GET['tariff_id'])) {
            $tariff_id = array_key_exists($_GET['tariff_id'], $this->premium_tariffs) ? addslashes($_GET['tariff_id']) : $tariff_id;
            if (array_key_exists($_GET['tariff_id'], $this->personal_tariffs)) {
                $tariff_id = addslashes($_GET['tariff_id']);
                // Отключил запрет вывода пакетов
                // https://basecamp.com/2889811/projects/8701471/todos/347225526#comment_611490824
                //$this->page_info['strict_tariff'] = true;
                $personal_tariff = true;
            }
        }

        // Берем тариф указанный в номере счета из $_GET
        if (isset($bill_row['fk_tariff']))
            $tariff_id = $bill_row['fk_tariff'];

        $do_upgrade = true;
        // Добавляем текущий тариф пользователя в список тарифов
        if ($this->user_info['trial'] == 0 && !isset($this->work_tariffs[$this->user_info['fk_tariff']])) {
            $this->work_tariffs[$this->user_info['fk_tariff']] = true;
            $do_upgrade = false;
        }
        /*
        * Переключаем пользователя на старший тариф, т.к. не истек срок текущий подписки.
        * Либо у пользователя триал
		 */
        if (($this->user_info['trial'] == 1 || !$this->renew_account) && !isset($_GET['tariff_id']) && $do_upgrade) {
				$tariff_id = $this->show_upgrade_tariffs(true, false, null, true);
        }
        $tariff = $this->get_tariff_info($tariff_id, false);

        // Меняем тарифный план если он не подходит пользователю по количеству вебсайтов
        if ($this->user_info['services'] > $tariff['services'] && !$personal_tariff && $tariff['services'] != 0) {
            $tariff_id = $this->show_upgrade_tariffs(true);
            $tariff = $this->get_tariff_info($tariff_id, false);
        }

        if ($tariff['cost'] == 0) {
            $tariff_id = cfg::default_tid;
            $tariff = $this->get_tariff_info($tariff_id);
        }

        // Проверяем возможность докупить доп. услуги к ранее купленному базовму тарифу.
        $extra_package_only = false;
        if (isset($_GET['package']) && $this->user_info['trial'] == 0 && $this->user_info['moderate'] == 1) {
			$sql = sprintf("select b.bill_id, paid_till, fk_tariff, b.period as bill_period, date, t.period as tariff_period, t.billing_period, b.comment, t.cost, t.cost_usd from bills b left join tariffs t on t.tariff_id = b.fk_tariff where fk_user = %d and paid = 1 and t.product_id = %d order by date desc limit 1;", 
				$this->user_id,
				$this->cp_product_id
			);
            $last_bill = $this->db->select($sql);
            if (isset($last_bill['paid_till']) && strtotime($last_bill['paid_till']) > time()) {
                $extra_package_only = true;
                $tariff_id = $last_bill['fk_tariff'];
            }
        }
        $this->page_info['extra_package_only'] = $extra_package_only;

        // Пакет дополнительных услуг.
        $extra_package = cfg::extra_package_default_state;
        if (isset($_GET['extra_package']) && preg_match("/^\d+$/", $_GET['extra_package'])) {
            $extra_package = (boolean) $_GET['extra_package'];
        }
        if ($extra_package_only) {
            $extra_package = true;
        }
        if ($personal_tariff) {
            $extra_package = false;
        }
        $this->page_info['extra_package'] = $extra_package;

        $ep_info = null;
        if (cfg::enable_extra_package || $extra_package_only) {
            $sql = sprintf("select package_id, package_name, cost_usd,cost_rate_per_tariff from tariffs_packages where package_id = %d;",
                $this->intToDB(cfg::extra_package_id)
            );
            $ep_info = $this->db->select($sql);
            if (isset($ep_info['package_name'])) {
                $ep_info['title'] = $this->lang['l_' . $ep_info['package_name'] . '_title'];
                // Устанавливаем цену относительно стоимости базового тарифа.
                if (isset($ep_info['cost_rate_per_tariff'])) {
                    $ep_info['cost'] = $this->tariffs[$tariff_id]['cost'] * $ep_info['cost_rate_per_tariff'];
                    $ep_info['cost_usd'] = $this->tariffs[$tariff_id]['cost_usd'] * $ep_info['cost_rate_per_tariff'];
                }
				$tariff_cost = $ep_info['cost_usd'];
				if ($this->ct_lang == 'ru') {
					$tariff_cost = $ep_info['cost'];
				}

//var_dump($ep_info, $tariff_cost);exit;
                // Уменьшаем стоимость услуги пропорционально оставшимся дням лицензии на основную услугу.
                if ($extra_package_only && isset($last_bill['paid_till'])) {
                    $ep_info['paid_till'] = $last_bill['paid_till'];

                    $last_bill['date_ts'] = strtotime($last_bill['date']);
                    $upgrade_info['not_used_period'] = ($last_bill['date_ts'] + ((86400 * $last_bill['tariff_period']) * $last_bill['bill_period'])) - time();
                    $upgrade_info['not_used_period'] = $upgrade_info['not_used_period'] / ((86400 * $last_bill['tariff_period']));
                    $upgrade_info['period_cost'] = $ep_info['cost_usd'];
					if ($this->ct_lang == 'ru') {
                    	$upgrade_info['period_cost'] = $ep_info['cost'];
					}

					// Скидки за длительный срок.
					if ($period > 1) {
                    	$upgrade_info['period_cost'] *= 0.9; 
						if ($period == 3) {
                    		$upgrade_info['period_cost'] *= 0.9; 
						}
					}

                    $discount = $upgrade_info['not_used_period'] * $upgrade_info['period_cost'];
                    if (isset($this->row_cur['usd_rate']) && $this->ct_lang != 'ru') {
                        $upgrade_info['period_cost'] = $upgrade_info['period_cost'] * $this->row_cur['usd_rate'];
                    }

                    $upgrade_info['discount'] = $upgrade_info['not_used_period'] * $upgrade_info['period_cost'];

			//var_dump($ep_info, $last_bill, $upgrade_info, $period); exit;
                    // Устанавливаем цену со скидкой.
                    if ($upgrade_info['discount'] < $ep_info['cost_usd']) {
                        $ep_info['cost_usd'] = $upgrade_info['discount'];
                    }

                    // Устанавливаем цену со скидкой.
                    if ($upgrade_info['discount'] < $ep_info['cost']) {
                        $ep_info['cost'] = $upgrade_info['discount'];
                    }

                    $upgrade_info['period_cost'] = number_format($upgrade_info['period_cost'], 2, '.', ' ');
                    $upgrade_info['not_used_period'] = number_format($upgrade_info['not_used_period'], 2, '.', ' ');
                    $upgrade_info['discount'] = number_format($upgrade_info['discount'], 2, '.', ' ');
                    $upgrade_info['comment'] = $last_bill['comment'];
                    $upgrade_info['billing_period'] = $this->lang['l_' . strtolower($last_bill['billing_period'])];
                    $ep_info['cost'] = $upgrade_info['discount'];
                    $ep_info['cost_usd'] = $upgrade_info['discount'];

                    $this->page_info['show_recalc'] = true;
                    $this->page_info['upgrade_info'] = &$upgrade_info;
                }
                if (isset($this->row_cur['usd_rate']) && isset($this->row_cur['currency_sign']) && $this->ct_lang != 'ru') {
                    $tariff_cost = $tariff_cost * $this->row_cur['usd_rate'];
                    $ep_info['l_currency'] = $this->row_cur['currency_sign'];
                } else {
                    $ep_info['l_currency'] = '$';
					if ($this->ct_lang == 'ru') {
						$ep_info['l_currency'] = $this->lang['l_currency'];
					}
				}
                $ep_info['tariff_cost'] = $tariff_cost;
                $ep_info['extra_package_only'] = $extra_package_only;

                $sql = sprintf("select ta.addon_id,ta.addon_name from tariffs_addons_packages tap left join tariffs_addons ta on ta.addon_id = tap.addon_id where package_id = %d;",
                    $this->intToDB(cfg::extra_package_id)
                );
                $rows = $this->db->select($sql, true);
                foreach ($rows as $k => $v) {
                    $ep_info['addons'][] = $this->lang['l_' . $v['addon_name'] . '_title'];
                }
            }
		}
//		var_dump($ep_info);exit;

        $this->page_info['ep_info'] = $ep_info;
        $this->page_info['enable_extra_package'] = (cfg::enable_extra_package && !$personal_tariff) ? true : false;

		$promokey = isset($_COOKIE['promokey']) && preg_match("/^[a-z0-9\.\-а-я\ \_]{1,16}$/ui", $_COOKIE['promokey']) ? addslashes($_COOKIE['promokey']) : null;
        if (isset($_GET['promokey']) && preg_match("/^[a-z0-9\.\-а-я\ \_]{1,16}$/ui", $_GET['promokey'])) {
            $promokey = addslashes($_GET['promokey']);
            setcookie('promokey', $promokey, strtotime("+3 days"), '/', $this->cookie_domain);
        }

        if ($this->ct_lang == "ru") {
            $use_balance = isset($_COOKIE['use_balance']) && $_COOKIE['use_balance'] == 1 ? true : false;
            $use_bonus = isset($_COOKIE['use_bonus']) && $_COOKIE['use_bonus'] == 1 ? true : false;
            $give_bonus = false;
            if ($give_bonus) {
                foreach ($renew_options as $k => $v) {
                    $bonus_text = '';
                    if (isset($this->bonus_rate[$k]) && $this->bonus_rate[$k] > 0)
                        $bonus_text = sprintf(" (+%.2f бонусов)", $this->tariffs[$tariff_id]['cost'] * $k * $this->bonus_rate[$k]);

                    $renew_options[$k] = $v . $bonus_text;
                }
            }
            if ($this->tariffs[$tariff_id]['billing_period'] == 'Month') {
		        $this->page_info['help_link'] = 'help_bill';
            }

            $currency = 'RUB';
            $pay_method = 'payanyway';

            unset($this->payment_methods['2CO']);
        } else {
            foreach ($renew_options as $key => $v) {
                $end = '';
                if ($key > 1) $end = 's';
                $renew_options[$key] = sprintf($this->lang['l_months_renew'], $key, $end);
            }
            $use_balance = false;
            $use_bonus = false;
            $give_bonus = false;
            $currency = 'USD';

            $pay_method = 'paypal';
            if (isset($this->options['default_pay_method_en']) && isset($this->payment_methods[$this->options['default_pay_method_en']])) {
                $pay_method = $this->options['default_pay_method_en'];
            }

            unset($this->payment_methods['payanyway']);
        }

        if (isset($_COOKIE['pay_method']) && isset($this->payment_methods[$_COOKIE['pay_method']])) {
            $pay_method = $_COOKIE['pay_method'];
        } else {
            setcookie('pay_method', $pay_method, null, '/');
        }

        // Хак дабы можно было давать ссылку на оплату с преопредленным методом оплаты
        if (isset($_GET['pay_method']) && isset($this->payment_methods[$_GET['pay_method']]) && $_GET['pay_method'] != $pay_method) {
            $pay_method = $_GET['pay_method'];
            $page = preg_replace("/&pay_method=\w+/", "", $_SERVER['REQUEST_URI']);
            header("Refresh:0; url=$page");
            setcookie('pay_method', $pay_method, null, '/');
            exit;
        }

        $auto_bill = 0;
        $this->page_info['post_action'] = cfg::MNT_HOST;
        $this->page_info['show_promo'] = cfg::show_promo_key;
        if ($pay_method === 'paypal') {
            $this->page_info['post_action'] = '';
            $give_bonus = false;

            if ($this->pp_auto_bill === true)
                $auto_bill = 1;

        }
        if ($pay_method === '2CO') {
            $give_bonus = false;
            $auto_bill = 0;

            $this->page_info['post_action'] = cfg::twoco_URL;
        }
        if ($pay_method === 'payanyway') {
            $this->page_info['show_promo'] = true;
        }

        $this->page_info['2CO_sid'] = cfg::twoco_sid;
        $this->page_info['2CO_demo'] = cfg::twoco_demo;
//var_dump($this->tariffs[$tariff_id]['billing_period'], $tariff_id);
        if ($this->tariffs[$tariff_id]['billing_period'] == 'Month' && !isset($bill_row['bill_id'])) {
            $this->page_info['show_period_list'] = true;
		} else if ($this->tariffs[$tariff_id]['billing_period'] == 'Year' && !isset($bill_row['bill_id'])) {
            $renew_options = array(
                1 => '1 ' . $this->number_lng(1, $this->lang['l_years_renew']),
                2 => '2 ' . $this->number_lng(2, $this->lang['l_years_renew']) . ' (10% OFF)',
                3 => '3 ' . $this->number_lng(3, $this->lang['l_years_renew']) . ' (19% OFF)'
            );
            if (!isset($_GET['renew_options'])) {
                $period = 3;
            }
            $this->page_info['show_period_list'] = true;
        }

        // Отменяем бонусы на годовых тарифах
        if ($this->tariffs[$tariff_id]['billing_period'] == 'Year') {
            $give_bonus = false;
            //$period = 1;
        }

        if (isset($_GET['show_promo'])) {
            $this->page_info['show_promo'] = true;
            setcookie('show_promo', 1, strtotime("+7 days"), '/');
        }

        $this->page_info['billing_period'] = $this->lang['l_' . strtolower($this->tariffs[$tariff_id]['billing_period'])];
		$this->page_info['use_balance'] = $use_balance;
		$this->page_info['use_bonus'] = $use_bonus;
		$this->page_info['renew_options'] = &$renew_options;
		$this->page_info['selected_period'] = $period;
		$this->page_info['tariff_id'] = &$tariff_id;
		$this->page_info['new_tariff'] = $this->user_info['fk_tariff'] != $tariff_id ? true : false;
		$this->page_info['payment_methods'] = $this->payment_methods;
		$this->page_info['pay_method'] = $pay_method;
//		var_dump($sql, $last_bill, $period, $renew_options, $tariff_id);exit;
        $this->page_info['renew_antispam_for_website'] = $this->lang['l_renew_antispam_for_website'];
        if ($this->user_info['first_pay_id'] != null && $this->user_info['fk_tariff'] != $tariff_id) {
            $this->page_info['renew_antispam_for_website'] = $this->lang['l_upgrade_antispam'];
        }

        if ($this->user_info['trial'] == -1) {
            $this->page_info['renew_antispam_for_website'] = $this->lang['l_registration_complete'];
            $this->page_info['registration_complete_hint'] = $this->lang['l_registration_complete_hint'];
            $this->page_info['show_refund_hint'] = true;
            if ($this->user_info['lead_source'] == 'spambots_check') {
                $this->page_info['registration_complete_hint'] = $this->lang['l_registration_complete_hint_spambots_check'];
                $this->page_info['show_refund_hint'] = false;
            }
        }

		if ($this->page_info['new_tariff'] && $this->ct_lang == 'ru')
			$this->page_info['head']['title'] = messages::new_tariff;

		// Делаем скидку если указан номер промер-акции
		$promo_id = null;
		if (isset($promokey)){
			$promo = $this->db->select(sprintf("select promo_id, unix_timestamp(expire) as expire, period, discount, new_tariff, period_up, partner_id, unix_timestamp(created) as created_ts from promo where promokey = '%s';", $promokey));
			if (isset($promo['promo_id'])){
				// Привязка акаунта к партнеру по partner_id
				if (isset($promo['partner_id']) && $promo['created_ts'] < $this->user_info['created_ts']){
					$user_id = $this->db->select(sprintf("select user_id from partners_regs where partner_id = %d and user_id = %d;", $promo['partner_id'], $this->user_info['user_id']));
					if (!$user_id){
						$this->db->run(sprintf("insert into partners_regs (partner_id, user_id, regtime, ip) values(%d, %d, now(), inet_aton('%s'));",
												$promo['partner_id'], $this->user_info['user_id'], $this->remote_addr));
						$this->post_log(sprintf(messages::user_linked, $this->user_info['email'], $promo['partner_id'], $promokey));
					}
				}

				// Костыль -3600 потому как расходятся данные между БД и PHP
				if ($period >= $promo['period'] && $promo['expire'] > time()){
					$promo_id = $promo['promo_id'];

				if (isset($promo['period_up']) && $promo['period_up'] < $period)
					$promo_id = null;

				// Если акция действует только для новых подключений, то не разрешаем использовать ее на текущем тарифе
				if ($promo['new_tariff'] == 1 && !$this->page_info['new_tariff'])
					$promo_id = null;
				}
				if (!$promo_id) {
					$promo['discount'] = 0;
                }

				$promo['discount'] = $promo['discount'] * 100;
				$promo['expire_human'] = date("M d Y", $promo['expire']);
                $and_more = '';
                if (isset($promo['period_up']))
                    $and_more = $this->lang['l_and_more'];

                if ($promo['new_tariff']) {
                    $this->page_info['promo_conditions'] = sprintf($this->lang['l_promo_conditions'], $promo['discount'], $promo['period'], '123', $and_more, $promo['expire_human']);
                } else {
                    $this->page_info['promo_conditions'] = sprintf($this->lang['l_promo_conditions2'], $promo['discount'], $promo['expire_human']);
                }
			} else {
				$this->page_info['promokey_error'] = $this->lang['l_promokey_not_found'];
				$this->post_log(sprintf(messages::promokey_not_found_sys, $promokey));

				// Удаляем куку, т.к. она не действительна
				setcookie('promokey', '', time() - 3600, '/');
				sleep(cfg::fail_timeout);
			}

            $promo['promokey'] = $promokey;
            $this->page_info['show_promo'] = true;
            $this->page_info['promo'] = &$promo;
		}

        //
        // Логика расчета скидки при переходе на новый тариф
        //
		$this->page_info['need_recharge'] = true;
        $this->page_info['show_recalc'] = false;

        $discount = $this->get_upgrade_discount($this->user_id, $tariff_id, 1);
//        var_dump($discount);exit;

        // Если делаем скидку при переходе на новый тариф, то отключаем автоматическое списание средств
        if ($discount > 0 && $auto_bill === 1)
            $auto_bill = 0;

        $tariff = $this->get_tariff_info($tariff_id);

        $tariff_cost = $tariff[$this->cost_label];

        // Если скидка больше платы за переход на новый тариф, то увеличиваем период оплаты, дабы прошел платеж.
        if ($discount > 0 && $discount > $tariff_cost * $period) {
            $r = $discount / ($tariff_cost * $period);
            foreach ($renew_options as $k => $v) {
                if ($k * $tariff_cost > $discount) {
                    $period = $k;
                    break;
                }
            }
		    $this->page_info['selected_period'] = $period;
        }

        $upgrade_tariff = false;
        if ($this->page_info['show_recalc'] === true || ($this->user_info['first_pay_id'] !== null && $this->user_info['fk_tariff'] != $tariff_id))
            $upgrade_tariff = true;

        $paid_till = $this->get_tariff_conditions($tariff, $period, null, $upgrade_tariff);
//var_dump($ep_info);exit;
        $params = array();
        if (isset($ep_info['cost_usd']) && $extra_package) {
            $params['extra_package'] = $ep_info;
            if ($extra_package_only) {
                $paid_till = strtotime($ep_info['paid_till']);
                $this->page_info['license_dates'] = sprintf("%s - %s %s",
                    date("M d Y", time()),
                    date("M d Y", $paid_till),
                    ''
                );
            }
		}
//		var_dump($ep_info);exit;
		$bill = $this->get_bill($tariff_id, $use_balance, $period, $promo_id, $use_bonus, $give_bonus, $this->page_info['tariff_conditions'], $auto_bill, $discount, $currency, date("Y-m-d", $paid_till), $params);
//var_dump($bill, $this->row_cur); exit;
        // Делаем списание через Stripe.
        if (isset($_POST['stripeToken'])) {
            require_once('./stripe/lib/Stripe.php');
            // Set your secret key: remember to change this to your live secret key in production
            // See your keys here https://dashboard.stripe.com/account
//            Stripe::setApiKey(cfg::stripe_secret_key);
            if (cfg::pp_mode == 'sandbox') Stripe::setApiKey(cfg::stripe_test_secret_key); else Stripe::setApiKey(cfg::stripe_secret_key);

            // Get the credit card details submitted by the form
            $token = $_POST['stripeToken'];

            // Create the charge on Stripe's servers - this will charge the user's card
            $charge_success = true;
            try {
                $charge = Stripe_Charge::create(array(
                      "amount" => $bill['cost_usd_cents'], // amount in cents, again
                      "currency" => "usd",
                      "card" => $token,
                      "description" => $bill['comment'],
                      "metadata" => array("bill_id" => $bill['bill_id'])
                ));
            } catch(Stripe_CardError $e) {
                // The card has been declined
                error_log(print_r($e, true));
                $charge_success = false;
            }

            $redirect_url = '/my/messages/pay_success'.'?bill_id='.$bill['bill_id'];
            if (!$charge_success) {
                $redirect_url = '/my/messages/pay_fail';
            }
            header("Location:" . $redirect_url);
            exit;
        }

        // Если разница равна 0, то не выставляем счет
        if ($bill['cost'] == 0){
            $this->page_info['need_recharge'] = false;
		    $this->page_info['post_action'] = '';
        }

        $this->show_upgrade_tariffs(false, false, $bill['fk_tariff']);

        $per_website_cost = 0;
        $cost_per_website_tariff_id = $bill['fk_tariff'];
        if ($this->tariffs[$cost_per_website_tariff_id]['services'] > 0) {
            $per_website_cost = $this->tariffs[$cost_per_website_tariff_id][$this->cost_label] / $this->tariffs[$cost_per_website_tariff_id]['services'];
            if ($this->tariffs[$bill['fk_tariff']]['billing_period'] == 'Year' && $period > 1 && cfg::discounts_for_years) {
                $per_website_cost *= 0.9;
                if ($period == 3) $per_website_cost *= 0.9;
            }
        }

        $per_website_cost_display = $per_website_cost;
        if ($this->row_cur && $this->ct_lang != 'ru') {
            $per_website_cost_display = $per_website_cost * $this->row_cur['usd_rate'];
        }

        if (round($per_website_cost_display) != $per_website_cost_display) {
            $per_website_cost_display = number_format($per_website_cost_display, 2, '.', ' ');
        }
        $this->page_info['per_website_cost'] = $per_website_cost_display;
        $this->page_info['unit_cost'] = $per_website_cost_display;
        $this->page_info['per_website_cost_display'] = sprintf($this->lang['l_cost_per_website'],
            $this->tariffs[$tariff_id]['l_currency'],
            $per_website_cost_display
        );

        $unit_cost = $this->tariffs[$bill['fk_tariff']][$this->cost_label];
		$gross_cost = $this->tariffs[$bill['fk_tariff']]['services'] * $per_website_cost * $period;
//		var_dump($this->tariffs[$bill['fk_tariff']]['services'], $per_website_cost, $period, $gross_cost);
        if ($this->tariffs[$bill['fk_tariff']]['billing_period'] == 'Year' && $period > 1 && cfg::discounts_for_years) {
            switch ($period) {
                case 2:
                    $this->page_info['discount_for_years'] = '(10%&nbsp;OFF)';
                    break;
                case 3:
                    $this->page_info['discount_for_years'] = '(19%&nbsp;OFF)';
                    break;
			}
            foreach ($this->page_info['subscribe_tariffs'] as $k => $val) {
                if ($val['billing_period'] == 'Year') {
                    $multi_label = '';
                    if ($val['services'] > 2)
                        $multi_label = $this->lang['l_word_multi'];

                    if ($this->ct_lang == 'ru' && $val['services'] > 2)
                        $multi_label = $this->lang['l_word_multi2'];

                    if ($this->ct_lang == 'ru' && $val['services'] >= 2 && $val['services'] < 5) {
                        $multi_label = $this->lang['l_word_multi'];
                    }
                    $this->page_info['subscribe_tariffs'][$k]['cost_usd'] *= 0.9;
                    $this->page_info['subscribe_tariffs'][$k]['cost'] *= 0.9;

                    if ($period == 3) {
                        $this->page_info['subscribe_tariffs'][$k]['cost_usd'] *= 0.9;
                        $this->page_info['subscribe_tariffs'][$k]['cost'] *= 0.9;
                    }

                    if ($this->ct_lang == 'ru') {
                        $this->page_info['subscribe_tariffs'][$k]['tariff_cost'] = number_format(
                            $this->page_info['subscribe_tariffs'][$k]['cost'], 0, '.', ' '
                        );
                    } else {
                        $this->page_info['subscribe_tariffs'][$k]['tariff_cost'] = number_format(
                            $this->page_info['subscribe_tariffs'][$k]['cost_usd'], 2, '.', ' '
                        );
						if ($this->row_cur) {
                        	$this->page_info['subscribe_tariffs'][$k]['tariff_cost'] = number_format(
                            	$this->page_info['subscribe_tariffs'][$k]['cost_usd'] * $this->row_cur['usd_rate'], 2, '.', ' '
                        	);
						}
                    }
                    $this->page_info['subscribe_tariffs'][$k]['info_charge'] = sprintf($this->lang['l_tariff_info_short'], $val['services_display'], $multi_label, $val['l_currency'], $this->page_info['subscribe_tariffs'][$k]['tariff_cost'], $this->lang['l_year']);
                }
			}
            if ($this->page_info['ep_info']) {
                $this->page_info['ep_info']['tariff_cost'] *= 0.9;
                $this->page_info['ep_info']['cost'] *= 0.9;
                $this->page_info['ep_info']['cost_usd'] *= 0.9;
                if ($period == 3) {
                    $this->page_info['ep_info']['tariff_cost'] *= 0.9;
                    $this->page_info['ep_info']['cost'] *= 0.9;
                    $this->page_info['ep_info']['cost_usd'] *= 0.9;
                }
                $this->page_info['ep_info']['tariff_cost'] = number_format($this->page_info['ep_info']['tariff_cost'], 2, '.', '');
                $ep_info['tariff_cost'] = $this->page_info['ep_info']['tariff_cost'];
                $ep_info['cost'] = $this->page_info['ep_info']['cost'];
				$ep_info['cost_usd'] = $this->page_info['ep_info']['cost_usd'];
            }
        }
        if ($this->row_cur && $this->ct_lang != 'ru') {
            $unit_cost = $unit_cost * $this->row_cur['usd_rate'];
            $gross_cost = $gross_cost * $this->row_cur['usd_rate'];
        }
        if ($this->tariffs[$bill['fk_tariff']]['services'] == 0) {
           $this->page_info['unit_cost'] = $this->avoid_zero($unit_cost);
        }
        $this->page_info['gross_cost'] = $this->avoid_zero($gross_cost);

		if (isset($ep_info['cost_usd']) && $extra_package) {
			$usd_rate = isset($this->row_cur['usd_rate']) && $this->row_cur['usd_rate'] ? $this->row_cur['usd_rate'] : 1;
            $gross_cost = $ep_info['cost_usd'] * $bill['period'];
            $gross_cost = $gross_cost * $usd_rate;
			if ($this->ct_lang == 'ru') {
            	$gross_cost = $ep_info['cost'] * $bill['period'];
			}
			$ep_info['gross_cost'] = $this->avoid_zero($gross_cost);
			// Убираем дробную часть из цифр с 00 после запятой
			if ((int) $ep_info['tariff_cost'] == $ep_info['tariff_cost']) {
				$ep_info['tariff_cost'] = number_format(round($ep_info['tariff_cost']), 0, '.', '');
			} else {
				$ep_info['tariff_cost'] = number_format($ep_info['tariff_cost'], 2, '.', '');
			}


            $this->page_info['ep_info'] = $ep_info;
        }

        // Логика блока с предложением перехода на более выгодный тариф
        if (
            $this->user_info['first_pay_id'] === null
            && !isset($_GET['upgrade_months_key'])
            && $this->tariffs[$tariff_id]['billing_period'] == 'Year'
            &&  $this->user_info['trial'] == 1
        ) {
            $offer_tariff_id = $this->show_upgrade_tariffs(true, true, $bill['fk_tariff']);

            $per_website_cost_new = 0;
            if ($offer_tariff_id) {
                $per_website_cost_new = $this->tariffs[$offer_tariff_id][$this->cost_label] / $this->tariffs[$offer_tariff_id]['services'];

            }
            if ($offer_tariff_id
                && isset($this->options['upgrade_promokey'])
                && $this->tariffs[$offer_tariff_id]['services'] <= $this->options['max_upgrade_websites']
                ) {
                $row = $this->db->select(sprintf("select discount from promo where promokey = %s;",
                    $this->stringToDB($this->options['upgrade_promokey'])
                ));
                if (isset($row['discount'])) {
                    $per_website_cost_new = $per_website_cost * (1 - $row['discount']);
                    $this->page_info['upgrade_promokey'] = $this->options['upgrade_promokey'];
                }
            }

            $per_website_discount = (1 - $per_website_cost_new / $per_website_cost) * 100;

            if ($offer_tariff_id && isset($this->options['upgrade_months_key'])) {

                $row = $this->db->select(sprintf("select free_months from bonuses where bonus_name = 'upgrade';"));

                $months_part = '';
                if ($row['free_months'] > 0) {
                    $months_part = sprintf($this->lang['l_upgrade_package_months_part'], $row['free_months']);
                }

                $discount_part = '';
                if ($per_website_discount > 0) {
                    $discount_part = sprintf($this->lang['l_upgrade_package_discount_part'], $per_website_discount);
                    if ($months_part != '') {
                        $months_part = ', ' . $months_part;
                    }
                }
                if ($months_part != '' || $discount_part != '') {
                    $this->page_info['upgrade_discount_notice'] = sprintf($this->lang['l_upgrade_package_discount'],
                       $this->tariffs[$offer_tariff_id]['services'],
                       $discount_part,
                       $months_part
                    );
                }
                $this->page_info['offer_tariff_id'] = $offer_tariff_id;
                $this->page_info['upgrade_months_key'] = $this->options['upgrade_months_key'];
            }
        }
		
		// Добавляем текущий тариф пользователя в список тарифов
		if (!$do_upgrade && !isset($this->page_info['subscribe_tariffs'][$this->user_info['fk_tariff']])) {
            $this->page_info['subscribe_tariffs_old'][$this->user_info['fk_tariff']] = $this->tariffs[$this->user_info['fk_tariff']];
        }

        // Логика подсказки про бесплатные месяцы
        if ($bill['free_months'] > 0 && !$upgrade_tariff) {
            $this->page_info['free_months_hint'] = sprintf($this->lang['l_free_months_hint'],
               date("M d", strtotime($this->user_info['valid_till'])),
               $bill['free_months']
	   		);
        }

		// Если счет с нулевой суммой и указанием на списание с баланса, то проводим списание с текущего счета
		// После чего продляем подписку на количество указанных периодов
		if (count($_POST) && $bill['cost'] == 0 && array_key_exists($tariff_id, $this->work_tariffs)){
            $extended = false;
			if ($bill['charge_balance'] > 0 && $bill['charge_balance'] <= $this->user_info['balance'] && $this->db->run(sprintf("update users set balance = balance - %.2f where user_id = %d;", $bill['charge_balance'], $bill['fk_user'])))
                $extended = true;

            if ($bill['charge_bonus'] > 0 && $bill['charge_bonus'] <= $this->user_info['bonus'] && $this->db->run(sprintf("update users_bonus set bonus = bonus - %.2f where user_id = %d;",$bill['charge_bonus'], $bill['fk_user'])))
                $extended = true;

            if ($extended == true) {
                if (!$this->db->run(sprintf("update users set paid_till = cast(from_unixtime(%d) as date), fk_tariff = %d where user_id = %d;", $paid_till, $bill['fk_tariff'], $bill['fk_user'])))
                    return false;

                $log_message = sprintf('Пользователю %s продленно подключение до %s и списано %.2f рублей с текущего счета.', $this->user_info['email'], date("Y-m-d", $paid_till), $bill['charge_balance']);
				if (!$this->db->run(sprintf("insert into logs values (now(), %s, null)", $this->stringToDB($log_message))))
					return false;

				if (!$this->db->run("update bills set paid = 1 where bill_id = " . $bill['bill_id']))
					return false;

                if (!$this->db->run(sprintf("insert into pays (date, cost, comment, fk_user, counted, fk_bill) values(now(), %.2f, %s, %d, 1, %d);",
                            $bill['charge_balance'], $this->stringToDB($log_message), $this->user_id, $bill['bill_id'])))
					return false;

				$message = sprintf(messages::connection_extended_message,
									$tariff['name'],
									date("Y-m-d", $paid_till),
									$bill['charge_balance'],
									$bill['charge_bonus']);
				if (!$this->send_email($this->user_info['email'], messages::connection_extended_header, $message)){
					$this->page_error(messages::email_failed, null);
					$this->post_log(sprintf(messages::reset_email_not_sent, $this->user_info['email']));
					$this->page_info['info'] = &$info;
					return false;
				};
			    $this->url_redirect('bill/recharge?extended=1');
			}
		}
		$this->page_info['websites'] = $this->get_websites($this->user_id);
		if ($this->user_info['trial'] == 0) {
			$this->page_info['valid_till_info'] = sprintf($this->lang['l_valid_till_current'],
				date("M d Y", strtotime($this->user_info['valid_till'])));
		}
		return true;
	}

    /**
      * Функция добавления нового профайла PayPal для автоматической оплаты сервиса
      *
      * @param string $token Токен
      *
      * @param int $payerId Id клиента
      *
      * @param $bill_id Номер счёта
      *
      * @return bool
      */
    function pp_do_express_checkout($token = null, $payerId = null, $bill_id = null) {
        if ($token === null || $payerId === null || $bill_id === null)
            return false;

        //$bill = $this->get_bill_info($bill_id);        
        $sql = sprintf('select bill_id, fk_tariff, b.cost, b.cost_usd, comment, b.period, auto_bill, pp_profile_id, t.period as tariff_period, t.billing_period from bills b left join tariffs t on t.tariff_id = b.fk_tariff where bill_id = %d;', intval($bill_id));
        $bill = $this->db->select($sql);
        if ($bill === false)
            return false;

        /*
         * The total cost of the transaction to the buyer.
         * If shipping cost (not applicable to digital goods) and tax charges are known, include them in this value.
         * If not, this value should be the current sub-total of the order.
         * If the transaction includes one or more one-time purchases, this field must be equal to the sum of the purchases.
         * Set this field to 0 if the transaction does not include a one-time purchase such as when you set up
         * a billing agreement for a recurring payment that is not immediately charged. When the field is set to 0,
         * purchase-specific fields are ignored.
         */
        $orderTotal = new BasicAmountType();
        $orderTotal->currencyID = $this->currencyCode;
        $orderTotal->value = $bill[$this->cost_label];

        $paymentDetails= new PaymentDetailsType();
        $paymentDetails->OrderTotal = $orderTotal;

        $DoECRequestDetails = new DoExpressCheckoutPaymentRequestDetailsType();
        $DoECRequestDetails->PayerID = $payerId;
        $DoECRequestDetails->Token = $token;
        $DoECRequestDetails->PaymentAction = 'Sale';
        $DoECRequestDetails->PaymentDetails[0] = $paymentDetails;

        $DoECRequest = new DoExpressCheckoutPaymentRequestType();
        $DoECRequest->DoExpressCheckoutPaymentRequestDetails = $DoECRequestDetails;

        $config = $this->get_pp_config();
        $DoECReq = new DoExpressCheckoutPaymentReq();
        $DoECReq->DoExpressCheckoutPaymentRequest = $DoECRequest;
        $paypalService = new PayPalAPIInterfaceServiceService($config);

        $paid = true;
        try {
            /* wrap API method calls on the service object with a try catch */
            $DoECResponse = $paypalService->DoExpressCheckoutPayment($DoECReq, $this->pp_account);
        } catch (Exception $ex) {
            $paid = false;
        }

        if (isset($DoECResponse->DoExpressCheckoutPaymentResponseDetails->PaymentInfo[0]->TransactionID)) {
            $tansaction_id = $DoECResponse->DoExpressCheckoutPaymentResponseDetails->PaymentInfo[0]->TransactionID;
        }
        if (isset($DoECResponse->Ack) && $DoECResponse->Ack != 'Success') {
            $paid = false;
        }

        // Возвращаем пользователя на страницу оплаты PayPal для выбора другого источника списания средств.
        // https://developer.paypal.com/docs/classic/express-checkout/ht_ec_fundingfailure10486/#testing
        $pp_return_codes = array(
            10486,
            10422
        );
        if (isset($DoECResponse->Ack) && $DoECResponse->Ack != 'Success') {
            if (isset($DoECResponse->Errors[0]->ErrorCode) && in_array($DoECResponse->Errors[0]->ErrorCode, $pp_return_codes)) {
                $this->post_log(sprintf("Не удалось завершить платеж по счету №%d, ошибка %s.%s",
                    $bill['bill_id'],
                    $DoECResponse->Errors[0]->ErrorCode,
                    isset($this->user_info['user_log_sign']) ? ' ' . $this->user_info['user_log_sign'] : ''
                ));

                $pp_checkout_url = null;
                if ($this->pp_checkout_url) {
                    $pp_checkout_url = apc_fetch($this->pp_checkout_url);
                    if ($pp_checkout_url) {
                        $this->post_log(sprintf("Перенаправляем для выбора другого способа оплаты по счету #%d.%s",
                            $bill['bill_id'],
                            isset($this->user_info['user_log_sign']) ? ' ' . $this->user_info['user_log_sign'] : ''
                        ));
                        header("Location:" . $pp_checkout_url);
                        exit;
                    }
                }
            }
        }

        if($paid) {
            $this->post_log(sprintf("Завершен платеж по счету №%d (TransactionID: %s).",
                $bill['bill_id'],
                $tansaction_id
            ));
        } else {
            $message = sprintf("Не удалось завершить платеж по счету #%d, token %s!",
                $bill['bill_id'],
                $token
            );
            $this->post_log($message);
            $error = $message;
            if (isset($DoECResponse->Errors)) {
                $error = print_r($DoECResponse->Errors, true);
            }
            $this->send_email(cfg::noc_email, $message, $error);
        }

        return $paid;
    }

    /**
      * Функция добавления нового профайла PayPal для автоматической оплаты сервиса
      *
      * @param string $token Токен
      *
      * @return bool
      */
    function pp_new_profile($token = null) {
        if ($token === null)
            return false;

        $getExpressCheckoutDetailsRequest = new GetExpressCheckoutDetailsRequestType($token);

        $getExpressCheckoutReq = new GetExpressCheckoutDetailsReq();
        $getExpressCheckoutReq->GetExpressCheckoutDetailsRequest = $getExpressCheckoutDetailsRequest;

        /*
         * 	 ## Creating service wrapper object
        Creating service wrapper object to make API call and loading
        configuration file for your credentials and endpoint
        */
        $paypalService = new PayPalAPIInterfaceServiceService(Configuration::getAcctAndConfig());
        try {
            /* wrap API method calls on the service object with a try catch */
            $getECResponse = $paypalService->GetExpressCheckoutDetails($getExpressCheckoutReq);
        } catch (Exception $ex) {
            $this->post_log($ex->errorMessage());
            return false;
        }

        if (isset($getECResponse->GetExpressCheckoutDetailsResponseDetails->InvoiceID) && preg_match("/^\d+$/", $getECResponse->GetExpressCheckoutDetailsResponseDetails->InvoiceID))
            $bill_id = $getECResponse->GetExpressCheckoutDetailsResponseDetails->InvoiceID;
        else
            return false;

        $bill = $this->get_bill_info($bill_id);
        if ($bill === false)
            return false;

        // По данному счету профиль автоматической оплаты уже создан, завершаем работу функции
        // Либо счет не предназначен для автоматических списаний
        if (isset($bill['pp_profile_id']) || $bill['auto_bill'] == 0)
            return true;

        $extend_string = sprintf("+%d %s", $bill['period'], strtolower($bill['billing_period']));
        $ts = strtotime($extend_string, time());

        $RPProfileDetails = new RecurringPaymentsProfileDetailsType();
        // Первое списание по профилю делаем за 3 дня до окончания текущего оплаченного периода
        $RPProfileDetails->BillingStartDate = date(DATE_ATOM, $ts - (86400 * 3));
        $RPProfileDetails->SubscriberName = sprintf("%s (user_id: %s)", $this->user_info['email'], $this->user_info['user_id']);
        $RPProfileDetails->ProfileReference = $bill['bill_id'];

        $activationDetails = new ActivationDetailsType();
        $activationDetails->InitialAmount = new BasicAmountType($this->currencyCode, $bill['cost_usd']);
        $activationDetails->FailedInitialAmountAction = 'CancelOnFailure';

        $paymentBillingPeriod =  new BillingPeriodDetailsType();
        $paymentBillingPeriod->BillingFrequency = $bill['period'];
        $paymentBillingPeriod->BillingPeriod = $bill['billing_period'];
        $paymentBillingPeriod->TotalBillingCycles = 0;
        $paymentBillingPeriod->Amount = new BasicAmountType($this->currencyCode, $bill['cost_usd']);

        $scheduleDetails = new ScheduleDetailsType();
        $scheduleDetails->Description = sprintf($this->lang['l_payment_description'],
                                        $bill['cost_usd'], $bill['period'], $this->lang['l_' . strtolower($bill['billing_period'])]);
        $scheduleDetails->ActivationDetails = $activationDetails;
        $scheduleDetails->PaymentPeriod = $paymentBillingPeriod;

        /*
         * 	 `CreateRecurringPaymentsProfileRequestDetailsType` which takes
        mandatory params:

        * `Recurring Payments Profile Details`
        * `Schedule Details`
        */
        $createRPProfileRequestDetail = new CreateRecurringPaymentsProfileRequestDetailsType();
        $createRPProfileRequestDetail->Token  = $token;
        $createRPProfileRequestDetail->ScheduleDetails = $scheduleDetails;
        $createRPProfileRequestDetail->RecurringPaymentsProfileDetails = $RPProfileDetails;
        $createRPProfileRequest = new CreateRecurringPaymentsProfileRequestType();
        $createRPProfileRequest->CreateRecurringPaymentsProfileRequestDetails = $createRPProfileRequestDetail;

        $createRPProfileReq =  new CreateRecurringPaymentsProfileReq();
        $createRPProfileReq->CreateRecurringPaymentsProfileRequest = $createRPProfileRequest;
        /*
         *  ## Creating service wrapper object
        Creating service wrapper object to make API call and loading
        configuration file for your credentials and endpoint
        */
        $paypalService = new PayPalAPIInterfaceServiceService(Configuration::getAcctAndConfig());
        try {
            /* wrap API method calls on the service object with a try catch */
            $createRPProfileResponse = $paypalService->CreateRecurringPaymentsProfile($createRPProfileReq);
        } catch (Exception $ex) {
            $message = sprintf("Не удалось создать PayPal профиль: %s", $ex->getMessage());
            $this->post_log($message);
            $error = print_r($ex, true);
            $this->send_email(cfg::noc_email, $message, $error);
            return false;
        }

        if (isset($createRPProfileResponse)) {
            if (strtoupper($createRPProfileResponse->Ack) == 'SUCCESS') {
                $this->db->run(sprintf("update bills set pp_profile_id = '%s' where bill_id = %d;", $createRPProfileResponse->CreateRecurringPaymentsProfileResponseDetails->ProfileID, $bill['bill_id']));
                $this->post_log(sprintf("Создан PayPal профиль %s, по счету №%d.", $createRPProfileResponse->CreateRecurringPaymentsProfileResponseDetails->ProfileID, $bill['bill_id']));
            }

            if (strtoupper($createRPProfileResponse->Ack) == 'FAILURE') {
                $message = sprintf("ВНИМАНИЕ! Ошибка создания PayPal профиля. Токен %s, счет №%d.", $token, $bill['bill_id']);
                $this->post_log($message);
                $error = print_r($createRPProfileResponse, true);
                $this->send_email(cfg::noc_email, $message, $error);
            }
        }

        return true;
    }

    /**
      * Платёж через PayPal
      *
      * @param string $service_name Название сервиса
      * @param int $amount Количество
      * @param int $recurring
      * @param int $period Период
      * @param int $bill_id Номер счёта
      * @param string $uri Callback URI
      *
      * @return bool
      */
    function pp_pay($service_name = '', $amount = 0, $recurring = 0, $period = 0, $bill_id = null, $uri = '/my/bill/recharge') {
        if ($bill_id === null)
            return false;

        $bill = $this->get_bill_info($bill_id);

		$protocol = isset($_SERVER["HTTPS"]) ? 'https' : 'http';

        $url = $protocol . '://' . $_SERVER['SERVER_NAME'];
        $url = preg_replace("/,/", "", $url);
		$cancelUrl = $url . $uri;

		$url_connector = '?';
		if (preg_match("/\?/", $uri)) {
			$url_connector = '&';
		}
        $returnUrl = $url . $uri . $url_connector . 'extended=1&bill_id=' . $bill_id;

        // total shipping amount
        $shippingTotal = new BasicAmountType($this->currencyCode, 0);
        //total handling amount if any
        $handlingTotal = new BasicAmountType($this->currencyCode, 0);
        //total insurance amount if any
        $insuranceTotal = new BasicAmountType($this->currencyCode, 0);

        // Create request details
        $itemAmount = new BasicAmountType($this->currencyCode, $amount);

        $itemDetails = new PaymentDetailsItemType();
        $itemDetails->Name = $service_name;
        $itemDetails->Amount = $itemAmount;
        $itemDetails->Quantity = 1;
	    $itemDetails->ItemCategory = 'Physical';

        // details about payment
        $itemTotalValue = $amount;
        $taxTotalValue = 0;
        $orderTotalValue = $shippingTotal->value + $handlingTotal->value + $insuranceTotal->value + $taxTotalValue + $itemTotalValue;

        $paymentDetails = new PaymentDetailsType();
        $paymentDetails->PaymentDetailsItem[0] = $itemDetails;
        $paymentDetails->ItemTotal = new BasicAmountType($this->currencyCode, $itemTotalValue);
        $paymentDetails->TaxTotal = new BasicAmountType($this->currencyCode, $taxTotalValue);
        $paymentDetails->OrderTotal = new BasicAmountType($this->currencyCode, $orderTotalValue);

        $paymentDetails->PaymentAction = 'Sale';

        $paymentDetails->HandlingTotal = $handlingTotal;
        $paymentDetails->InsuranceTotal = $insuranceTotal;
        $paymentDetails->ShippingTotal = $shippingTotal;

//        $paymentDetails->NotifyURL = 'http://pays.cleantalk.ru/mt.php';

        $paymentDetails->InvoiceID = $bill_id;

        // 127 символов ограничение API
        $paymentDetails->OrderDescription = substr($bill['comment'], 0, 127);

        $setECReqType = new SetExpressCheckoutRequestType();
        $setECReqDetails = new SetExpressCheckoutRequestDetailsType();
        $setECReqDetails->PaymentDetails[0] = $paymentDetails;

        $setECReqDetails->CancelURL = $cancelUrl;
        $setECReqDetails->ReturnURL = $returnUrl;

        /*
         * Determines where or not PayPal displays shipping address fields on the PayPal pages. For digital goods, this field is required, and you must set it to 1. It is one of the following values:

            0 – PayPal displays the shipping address on the PayPal pages.

            1 – PayPal does not display shipping address fields whatsoever.

            2 – If you do not pass the shipping address, PayPal obtains it from the buyer's account profile.

         */
        $setECReqDetails->NoShipping = 1;
        /*
         *  (Optional) Determines whether or not the PayPal pages should display the shipping address set by you in this SetExpressCheckout request, not the shipping address on file with PayPal for this buyer. Displaying the PayPal street address on file does not allow the buyer to edit that address. It is one of the following values:

            0 – The PayPal pages should not display the shipping address.

            1 – The PayPal pages should display the shipping address.

         */
        $setECReqDetails->AddressOverride = 0;

        /*
         * Indicates whether or not you require the buyer's shipping address on file with PayPal be a confirmed address. For digital goods, this field is required, and you must set it to 0. It is one of the following values:

            0 – You do not require the buyer's shipping address be a confirmed address.

            1 – You require the buyer's shipping address be a confirmed address.

         */
        $setECReqDetails->ReqConfirmShipping = 0;
        // Billing agreement details
        if ($this->pp_auto_bill === true) {
            $billingType = 'None';
            $billingAgreementText = '';
            if ($recurring == 1) {
                $billingType = 'RecurringPayments';
                $billingAgreementText = sprintf($this->lang['l_payment_description'], $amount, $period, $this->lang['l_' . strtolower($bill['billing_period'])]);
            }
            $billingAgreementDetails = new BillingAgreementDetailsType($billingType);
            $billingAgreementDetails->BillingAgreementDescription = $billingAgreementText;
            $setECReqDetails->BillingAgreementDetails = array($billingAgreementDetails);
        }

        // Display options
        $setECReqDetails->cppheaderimage = '';
        $setECReqDetails->cppheaderbordercolor = '';
        $setECReqDetails->cppheaderbackcolor = '';
        $setECReqDetails->cpppayflowcolor = '';
        $setECReqDetails->cppcartbordercolor = '';
//        $setECReqDetails->cpplogoimage = 'http://cleantalk.org/images/cleantalk-logo-60-2.png';
        $setECReqDetails->cpplogoimage = 'https://cleantalk.org/images/cleantalk-logo-sign-en-60.png';
        $setECReqDetails->PageStyle = '';
        $setECReqDetails->BrandName = 'CleanTalk';
        $setECReqDetails->LocaleCode = 'en_US';
        $setECReqDetails->AllowNote = '0';
        $setECReqDetails->SolutionType = 'Sole';
        $setECReqDetails->LandingPage = 'Billing';
        if ($this->ct_lang == 'ru')
            $setECReqDetails->LocaleCode = 'ru_RU';

        $setECReqType->SetExpressCheckoutRequestDetails = $setECReqDetails;

        // Create request
        $setECReq = new SetExpressCheckoutReq();
        $setECReq->SetExpressCheckoutRequest = $setECReqType;

        $sandbox = '';
        if (cfg::pp_mode === 'sandbox') {
            $sandbox = '.sandbox';
        }

        // Perform request
        $config = $this->get_pp_config();
        $paypalService = new PayPalAPIInterfaceServiceService($config);
        $setECResponse = $paypalService->SetExpressCheckout($setECReq, $this->pp_account);

        // Check results
        if(strtoupper($setECResponse->Ack) == 'SUCCESS') {
            // Success
            $token = $setECResponse->Token;
            // Redirect to paypal.com here
            $payPalURL = 'https://www' . $sandbox . '.paypal.com/webscr?cmd=_express-checkout&token=' . $token;

            if ($this->pp_checkout_url) {
                apc_store($this->pp_checkout_url, $payPalURL, cfg::apc_cache_lifetime_long);
            }

            // Сохраняем информацию о переходе на оплату
            $this->db->run(sprintf("insert into bills_rate (bill_id, datetime, pp_token) values (%d, now(), %s);",
                $bill_id,
                $this->stringToDB($token)
            ));

            header("Location:" . $payPalURL);
            exit;
        } else {
            var_dump($setECResponse);
        }

        return true;
    }

    /**
      * Функция возвращает информацию о счете
      *
      * @param int Номер счёта
      * @param int Id пользователя
      * @param bool Признак оплачен ли счёт
      *
      * @return array
      */
    public function get_bill_info($bill_id, $user_id = null, $paid = 0) {
        $sql_user_id = ' ';
        if ($user_id !== null)
            $sql_user_id = sprintf(' fk_user = %d and ', $user_id);

        $sql = sprintf('select bill_id, fk_tariff, b.cost, b.cost_usd, comment, b.period, auto_bill, pp_profile_id, t.period as tariff_period, t.billing_period from bills b left join tariffs t on t.tariff_id = b.fk_tariff where%spaid = %d and bill_id = %d;', $sql_user_id, 0, $bill_id);

        $bill = $this->db->select($sql);

        return $bill;
    }

    /**
      * Функция возвращает настройки PayPal
      *
      * @return array
      */
    private function get_pp_config() {
		$config = array(
				"mode" => cfg::pp_mode,
		);
	    return $config = array_merge(Configuration::getAcctAndConfig(), $config);
    }
}
