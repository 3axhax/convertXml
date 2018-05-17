<?php

class cfg{
	const domain 						= "cleantalk.org";
	const mantainer_url					= "http://cleantalk.org";
	const uri_prefix					= "my";

    const includes_dir                  = "includes/";

	const pagenotfound_tpl 				= 'messages/pagenotfound.html';

	// Количество секунд при неудачной попытке авторизоватья, заполнить форму
	const fail_timeout				 	= 1;

	const title						 	= '';

	const debug							= false;

    const debug_app                     = false;

    # Идентификатор старого тарифа с минимальной стоимостью сервиса. Используется для корректного выставления счета в firstpage.php.
    const default_tariff_id_old     	= 24;

	const free_tariff_id                = 15;
	const free_services                 = 10; # Максимальное количество веб-сайтов на тестовый период

	const reply_to						= '"Клинтолк" <welcome@cleantalk.org>';
	const contact_email 				= 'welcome@cleantalk.org';
	const noc_email                     = 'noc@cleantalk.org';
	const office_url					= "http://cleantalk.org/office";

	const MNT_HOST 						= 'https://www.moneta.ru/assistant.htm';
	const MNT_ID						= 44472428;
	const MNT_CURRENCY_CODE				= 'RUB';
	const MNT_CODE						= 'bOx24Goner';
	const MNT_TEST_MODE					= 0;
	const MNT_paymentSystem_id			= 'card'; // 1020 - Яндекс.Деньги, 1017 - Webmoney, 382203 - Visa, Mastercard, 499669 - Visa, Mastercard Банк Москвы

	const memcache_host					= 'localhost';
	const memcache_port					= 11211;

	const premium_tariff_id				= 4;
	const year_tariff_id				= 24;
	const quarter_tariff_id				= 21;
	const cheap_tariff_id				= 20;
	const default_tid					= 4; // Тариф по-умолчанию

	const min_password_length			= 5;

	// Приставка к паролю пользователю дабы нельзя было зная хеш перебором подобрать пароль
	const password_prefix				= '1225';

	const usd_mpc						= 30;

	// Размер бонуса за своевременную оплату в процентах от еденицы
	const bonus_rate					= 0.1;

    // Временная ставка для партнеров
	const promo_monthly_fee				= 0.3;

	// Временная зона сервера биллинга
	const billing_timezone				= '+5';

	// Минимальный срок оплаты сервиса
	const min_paid						= 1;
	const default_period                = 3;

	// Максимальное количество ключевых слов в словаре сайта
	const top_words_max					= 30;

	// Минимальный и максимальный размер шрифта для облага ключевых слов
	const tw_font_size_min				= 8;
	const tw_font_size_max				= 30;
	const tw_manual_color               = 'CC3300';
	const tw_out_dictionary_color       = 'CCCCCC';

	// Хранилище обработанных запросов
	const store_dir						= '/usr/local/cleantalk/store/arc/';

	// Платежей в выборке для главной
	const pays_count					= 10;

	// Префикс данных об акаунте в  Memcache
	const auth_key_prefix				= 'auth_key';

	// Время хранения информации о пользователе в Memcache
	const memcache_store_timeout_user	= 900;

    // Время хранения информации в Memcache
	const memcache_store_timeout_mid = 900;

    // Время хранения информации в Memcache
	const memcache_store_timeout = 60;

    // Адрес и строка запроса к SMS шлюзу
    const sms_url = 'http://smspilot.ru/api.php?send=%s&to=%s&from=%s&apikey=%s';

    // Ключ подключения к SMS шлюзу
    const sms_api_key = 'MQ5HADC70TT3344LZ3IH7KO37D3E408W5G8F9NT19YT0U15J20F78TCCDIDC342B';

    // Скидка на заполнение анкеты
    const profile_promo_key = '2xo8Mm';

    // Идентификатор приложения к счету на Яндекс.деньгах
    const ym_client_id = '99E0A60EC7B618116E30433644B6EDB2A912391B23BDD23C053280BF0EEBEE22';

    // Идентификатор приложения к счету на Яндекс.деньгах
    const ym_redirect_url = 'http://cleantalk.org/test.php';

    // Token для доступа к счету на Яндекс.деньгах
    const ym_token = '41001260652931.CA663E231B5DBD2C04A3D8A484ACC5211E22C00528761258D0DE96BD40E47271FD9D0590E598201AA34D720C6FB5BF074DAA58E5C5052006523D6648A96EFC864F5EC146250FD333F4BD52DC3D4841D5B1A36F48BD6F5DD2B68BF8155CAA7D6830110F559F14EDE67C3326671133656789C3ED552CF67DAF8C56581A1F7C4C5E';

    // Время хранения информации о счете партнера в куках, дни
    const partner_account_cookie_store = 365;

    // Время в днях отображения предложения о переходе на повышенный тариф
    const upgrade_offer_show_period = 14;

    // Количество продлений с которых показывать предложение о переходе на годовой тариф
    const year_offer_show_extends = 1;

    // Количество продлений с которых показывать предложение о переходе на 3х месячный тариф
    const quarter_offer_show_extends = 3;

    // Количество продлений с которых показывать предложение о переходе на дешевый месячный тариф
    const cheap_offer_show_extends = 4;

    // Режим работы - продуктив/тесты (live/sandbox)
    const pp_mode = 'live';

    // Режим работы - продуктив/тесты (live/sandbox)
    const pp_account_ooo = 'shagimuratov_api1.gmail.com'; // ООО "Клинтолк"
//    const pp_account_ooo = 'poluxster_api1.gmail.com';  // CleanTalk Inc
    const pp_account_inc = 'poluxster_api1.gmail.com';  // CleanTalk Inc
    const pp_account_ooo_sandbox = 'noc-facilitator_api1.cleantalk.org'; // ООО "Клинтолк" тестовый
    const pp_account_inc_sandbox = 'welcome-facilitator_api1.cleantalk.org'; // CleanTalk Inc тестовый
//    const pp_account_inc_sandbox = 'abalakov_api1.cleantalk.org';

    // Ключ сообщающий о проблемах приема бизнес платежей на PP акаунт
    const paypal_business_accounts_issue = false;

    // Директория пользовательских файлов
    const customers_dir = './customers/';

    // Дни до окончания подписки для предложения перейти на новый тариф
    const pay_days = 31;

    // Дни до окончания подписки для предложения перейти на новый тариф
    const pay_days_month = 14;

    // Включить/выключить создание профилей автоматического списания средств со счетов PayPal
    const pp_auto_bill = false;

    // Параметры платежей через 2checkout.com
    const twoco_enable = false;
    const twoco_URL = 'https://www.2checkout.com/checkout/purchase';
    const twoco_sid = 2103486;
    const twoco_demo = 'N'; // Y|N

    // Максимальный срок хранения не оплаченных счетов в базе
    const bills_max_age = 14;

    // Ссылка на отзыв для плагина WP
    const wp_review_link = 'http://wordpress.org/support/view/plugin-reviews/cleantalk-spam-protect?filter=5';
    const wp_signup_link = 'http://wordpress.org/support/bb-login.php';

    // Количество дней с момента регистраций после которых разрешен вывод предложения проголосовать за предложение.
    const review_timeout = 0;

    // Количество дней на запрет вывода сообщений
    const hint_off_timeout = 181;

    // Время хранения в кеше статической/редкоизменяемой информации
    const memcache_store_static_data = 86400;

    // Ключ для шифрования результатов работы антиспам службы
    const aaid_key = 'Goq8l9lzuV0woWX';

    // Количество периодов для построения графика по спам активности
    const default_stat_period_intervals = 12;

    // Период для графика антиспам атак по-умолчанию.
    const default_stat_period = 'month';

    // Включить получение детализации с удаленного сервера.
    const enable_remote_details = true;

    // Список CMS, для которых не выдаем предложение оставить отзыв
    const skip_cms_for_review = 'phpbb3,joomla15,joomla3,wordpress';
    const skip_stars_for_review = 'smf,ipboard,xenforo,ipboard4';
    const skip_cms_for_review_lang = 'wordpress:ru';

    // Включить подсчет времени исполнения кода
    const enable_profiling = false;

    // Ключи для stripe.com
    const stripe_public_key = 'pk_live_72fwgeCGK5l1jAxqjEboTHyC';
    const stripe_secret_key = 'sk_live_0AAckdI3iqnnzhqDhRMcj9FQ';
    const stripe_test_public_key = 'pk_test_rKEmSO5sl56IXka8YLw5aBhJ';
    const stripe_test_secret_key = 'sk_test_vmYJAPyuq5NkKeW4VBBJtlc4';

    const stripe_enable = true;

    // Разрешить авторизацию по токену
    const allow_token_login = false;

    // Количество попыток ввода неправильного пароля для запуска механизма авторизации по временному ключу
    const switch_to_onetime_code = 3;

    // Количество попыток ввода неправильного однорозавого кода доступа
    const onetime_code_fail_count = 1;

    // Длина временного ключа
    const onetime_code_length = 6;

    // Пауза между попытками сбросить пароль
    const password_reset_timeout = 60;

    // Показываем новый формат триального банера.
    const show_new_trial_offer = false;

    const hosting_package_multiplicators = '1,2,5,10,20,50,100,200';

    // Инструкция на главной о настройке сайта.
    const show_setup_hint = false;

    // Количество отзывов на главной ПУ
    const max_review_on_first_page = 4;

    const show_promo_key = false;

    const apc_cache_lifetime = 60;

    const apc_cache_lifetime_long = 3600;

    const apc_cache_lifetime_account = 15;

    // Days to moneyback
    const money_back_days = 60;

    // Таймаут для редиркета по-умолчанию.
    const header_redirect_timeout_default = 10;

    // Предложение о бесплатной лицензии, если пропущен спам.
    const year_free_offer = false;

    // Банер о бесплатной услуге у хостера N.
    const show_hosting_offer = false;

    // Предложение о бесплатном CleanTalk на хостинге.
    const show_free_cleantalk_with_hosting = false;

    //
    // Пакет дополнительных услуг.
    //
    const enable_extra_package = true;
    const extra_package_id = 1;
    const extra_package_default_state = true;

    //
    // Вторые цены.
    //
    const show_double_prices = true;

    //
    // SpamFireWall в персональных списках.
    //
    const show_sfw_records = true;

    //
    // Максимальная длинна текста для ввода в server_response (Настройки сайта).
    //
    const server_response_length = 1000;


    // Выдаем банер об оплате для контекстных пользователей через N дней после появления первого запроса.
    const show_offer_for_context = 3;

    // Количество сайтов при которых появляется строка посика и пагинация на главной ПУ

    const sites_number_page_search = 10;

    // Количество записей сайтов на странице по умолчанию - главная ПУ

    const default_num_per_page = 10;

    // Идентификатор продукта для API
    const api_product_id = 2;

    const product_database_api = 2;
    const product_antispam = 1;
    const product_hosting_antispam = 3;
    const product_security = 4;
    const product_ssl = 5;

    // Тариф и период по-умолчанию
    const antispam_tariff_default = 0;
    const antispam_period_default = 3;
    const api_tariff_default = 82;
    const api_period_default = 3;
    const hosting_tariff_default = 68;
    const hosting_period_default = 3;
    const security_tariff_default = 105;
    const security_period_default = 3;
    const ssl_tariff_default = 124;
    const ssl_period_default = 1;

    // Время хранения информации о частоте запросов к черным спискам
    const bl_stat_mc_timeout = 180;
    const bl_stat_mc_timeout_short = 30;
    const bl_stat_mc_timeout_medium = 300;
    const bl_stat_mc_timeout_long = 3600;
    const bl_stat_mc_timeout_day = 86400;
    const bl_fail_count = 3;
    const bl_fail_count_private = 5;

    // Карта запросов по странам на главной ПУ.
	const show_world_map = true;

	const discounts_for_years = true;

	const comodo_login = 'cleantalk';
	const comodo_password = 'FWWmS8vGe*ni9YmR';

	const max_sites_for_token_auth = 3; 
	
	const page_limit = 300; // Records on a page of Private lists.

	const ssl_default_validation_list = 'admin,administrator,postmaster,hostmaster,webmaster';

	// Количество сайтов на акаунте для вывода ссылки на отзыв.
	const show_rate_notice_limit = 5;
    // Флаг разрешающий показывать тур в ПУ
    const show_dashboard_tour = 1;
    const default_register_page_type = 'sketch';
    const cookie_store_days = 31;
}
