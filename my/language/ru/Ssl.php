<?php

$lang = array_merge($lang, array(
    'l_ssl_title' => 'SSL',
    'l_ssl_title_email' => 'Выберите email для подтверждения владения доменом',
    'l_ssl_title_add' => 'Добавление сертификата',

    'l_ssl_years' => array(
        '1' => '1 год',
        '2' => '2 года',
        '3' => '3 года'
    ),

    'l_bill_title' => 'Купить SSL сертификат',
    'l_bill_license_name' => 'Сертификат',
    'l_bill_package' => 'Package',
    'l_bill_currency' => 'Валюта',
    'l_bill_period_s' => 'Период',
    'l_bill_package_title' => ':SERVICES: :SERVICES_TITLE:, :SIGN::COST_ROUND:/year',
    'l_bill_period' => 'Billing period',
    'l_bill_period_12' => '1 год',
    'l_bill_period_1' => '1 год',
    'l_bill_period_2' => '2 года',
    'l_bill_period_3' => '3 года',
    'l_bill_date' => 'm d y',
    'l_bill_websites' => 'Сайты',
    'l_bill_cost_per_month' => 'Стоимость',
    'l_bill_total' => 'Итого',
    'l_bill_total_to_pay' => 'Итого к оплате',
    'l_bill_months' => "'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'",
    'l_bill_text' => 'SSL certificate for site for :PERIOD:',
    'l_bill_comment' => 'SSL certificate for :PERIOD:, :COST:',
    'l_bill_comment_2' => 'SSL certificate for :SERVICES: :SERVICES_TITLE:, :SIGN::COST:, valid till :PAID_TILL:',
    'l_bill_comment_3' => ':SERVICES: websites, :SIGN::COST:, :PERIOD: months',
    'l_bill_cost_format' => ':SIGN::VALUE:',
    'l_bill_cost_format_full' => '<strong>:SIGN::VALUE:</strong> <span class="hidden">(:TOTAL_VALUE: USD)</span>',
    'l_num_services' => array('website', 'websites'),
    'l_num_services_2' => array('website', 'websites'),
    'l_num_calls' => array('call', 'calls'),

    // Statuses

    'l_ssl_statuses' => array(
        'CHECKOUT' => array(
            'text' => 'Оплатить',
            'hint' => 'Оплатите сертификат'
        ),
        'WAIT_USER' => array(
            'text' => 'Активировать',
            'hint' => 'Нажмите на кнопку'
        ),
        'READY_CA' => array(
            'text' => 'Активация',
            'hint' => 'Сертификат будет выпущен в течении 3-5 минут, вы получите уведомление на адрес :DCV_EMAIL_ADDRESS:.'
        ),
        'WAIT_CA' => array(
            'text' => 'Ожидание подтверждения',
            'hint' => 'Пожалуйста, ознакомьтесь с инструкциями, высланными на адрес :DCV_EMAIL_ADDRESS: (<a href="#" data-id=":CERT_ID:" data-name=":COMMON_NAME:" class="dcv-link">сменить email</a>).'
        ),
        'ERROR' => array(
            'text' => 'Что-то не так',
            'hint' => 'Пожалуйста откройте <a href="/my/support">обращение в техподдержку</a> или <a href="#" class="text-danger delete-link" id="delete_:CERT_ID:" data-id=":CERT_ID:">удалить</a>.'
        ),
        'ISSUED' => array(
            'text' => 'Активный',
            'hint' => '<a href="/help/install-SSL-certificate" target="_blank">Инструкция по установке сертификата</a>'
        )
    ),

    // Dashboard

    'l_ssl_dashboard_title' => 'Панель управления SSL',
    'l_add_cert' => 'Добавить сертификат',

    'l_ssl_empty_header' => '<p>Google recently introduced HTTPS as a ranking signal.</p><p>Using SSL encryption technology from Comodo</p><p>will help Boost your <strong>Google Ranking</strong>!</p>',
    'l_ssl_empty_title' => 'Positive SSL<span>The best for a personal blog or small or medium size business website</span>',
    'l_ssl_empty_features' => array(
        array(
            'Secure a single domain',
            'Both site.com & www.site.com',
            'Mobile support',
            '99.9% browser recognition'
        ),
        array(
            '2048 bit signatures/256-bit encryption',
            'Unlimited re-issuance',
            'Unlimited server Licenses',
            'No paperwork required'
        )
    ),
    'l_ssl_empty_footer' => array(
        'By using SSL you guarantee the highest possible encryption levels for inline transactions. Each SSL certificate is signed with NIST recommended 2048 bit signatures and provides up to 256 bit encryption of customer data.',
        'As stated at Google, they want to <i>“encourage all website owners to switch from HTTP to HTTPS to keep everyone safe on the web. Beginning in January 2017, Chrome (version 56 and later) will mark pages that collect passwords or credit card details as <strong>“Not Secure”</strong> unless the pages are served over HTTPS. Eventually, Chrome will show a <strong>Not Secure</strong> warning for all pages served over HTTP.”</i>'
    ),

    'l_ssl_th_cert_id' => '#',
    'l_ssl_th_domains' => 'Домены',
    'l_ssl_th_type' => 'Тип',
    'l_ssl_th_valid' => 'Действителен',
    'l_ssl_th_created' => 'Создан',
    'l_ssl_th_expires' => 'Заканчивается',
    'l_ssl_th_status' => 'Статус',

    'l_ssl_btn_delete' => 'Удалить',
    'l_ssl_btn_cancel' => 'Отмена',
    'l_ssl_btn_download' => 'Скачать',
    'l_ssl_btn_send_email' => 'Отправить письмо подтверждения прав',
    'l_ssl_btn_get_now' => 'Получить сертификат',
    'l_ssl_btn_looks_good' => 'Продолжить',

    'l_ssl_helpful' => 'Полезные ссылки',
    'l_ssl_helpful_links' => array(
        array('text' => 'Как сгенерировать CSR (Certificate Signing Request)?', 'url' => '/help/SSL-how-to-generate-CSR'),
        array('text' => 'Как установить сертификат?', 'url' => '/help/install-SSL-certificate')
    ),

    'l_ssl_modal_delete_title' => 'Удалить сертификат',
    'l_ssl_modal_delete_message' => 'Вы действительно хотите удалить сертификат?',

    // Messages

    'l_ssl_msg_successful_activated' => 'Certificate successful activated!',
    'l_ssl_msg_deleted' => 'Certificate deleted!',
    'l_ssl_msg_email_changed' => 'Certificate validation email successful changed!',
    'l_ssl_msg_error' => 'Internal Server Error!',
    'l_ssl_errors' => array(
        'required' => 'Required "%s"',
        'country' => 'Unknown country: %s',
        'commonName' => 'The CSR\'s Common Name may NOT contain a wildcard! Please fill Common name as single domain. For example: cleantalk.org.',
        'csr' => 'Invalid CSR',
        'www' => 'The CSR\'s Common Name may NOT contain a "www."! Please fill Common name as a second-level domain. For example: cleantalk.org.'
    ),

    // Mail

    'l_ssl_mail_title' => 'CleanTalk: CSR and Private Key for %s',
    'l_ssl_mail_message' => 'CSR and Private key for %s',

    // Generator

    'l_ssl_generator_what' => 'What to include in my <a href="https://cleantalk.org/help/SSL-how-to-generate-CSR&lang=en" target="_blank" class="alert-link">CSR</a> to get my cert ASAP?',
    'l_ssl_generator_btn_csr' => 'Уже есть CSR?',
    'l_ssl_generator_btn_generator' => 'CSR генератор',
    'l_ssl_generator_btn_show' => 'Показать',
    'l_ssl_generator_btn_dcv' => 'Отправить письмо подтверждения',

    'l_ssl_generator_title' => 'CSR Генератор',
    'l_ssl_generator_fields' => array(
        'commonName' => array(
            'label' => 'Имя сервера',
            'placeholder' => 'example.com',
            'help' => 'Полностью определённое доменное имя.'
        ),
        'email' => array(
            'label' => 'Email',
            'placeholder' => 'admin@example.com',
            'help' => 'Адрес электронной почты.'
        ),
        'organization' => array(
            'label' => 'Организация',
            'placeholder' => 'Название организации',
            'help' => 'Название организации или ФИО физического лица.'
        ),
        'organizationUnit' => array(
            'label' => 'Отдел организации',
            'placeholder' => 'Название отдела',
            'help' => 'Ответственный отдел, например "IT Department".'
        ),
        'country' => array(
            'label' => 'Страна',
            'help' => 'Страна в которой зарегистрирована организация.'
        ),
        'state' => array(
            'label' => 'Область или регион',
            'help' => 'Область или регион, в котором находится ваша организация.'
        ),
        'locality' => array(
            'label' => 'Город',
            'placeholder' => 'Moscow',
            'help' => 'Город расположения вашей организации.'
        )
    ),
    'l_ssl_generator_generate' => 'Сгенерировать',

    'l_ssl_csr_title' => 'Добавьте CSR',
    'l_ssl_csr_read' => 'Отправить CSR',
    'l_ssl_csr_modal_title' => 'Проверьте данные CSR',
    'l_ssl_csr_modal_items' => array(
        'domains' => 'SSL покрывает',
        'commonName' => 'Доменное имя',
        'emailAddress' => 'Email адрес',
        'organizationName' => 'Название организации',
        'organizationUnitName' => 'Отдел организации',
        'countryName' => 'Страна',
        'stateOrProvinceName' => 'Область или регион',
        'localityName' => 'Город'
    ),

    'l_ssl_dcv_title' => 'Выберите email для подтверждения прав владения'
));
