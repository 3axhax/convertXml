<?php

$lang = array_merge($lang, array(
    'l_ssl_title' => 'SSL',
    'l_ssl_title_email' => 'Choose email to validate ownership',
    'l_ssl_title_add' => 'Add certificate',

    'l_ssl_years' => array(
        '1' => '1 year',
        '2' => '2 years',
        '3' => '3 years'
    ),

    'l_bill_title' => 'Buy new SSL certificate',
    'l_bill_license_name' => 'Certificate name',
    'l_bill_package' => 'Package',
    'l_bill_currency' => 'Currency',
    'l_bill_period_s' => 'Period',
    'l_bill_package_title' => ':SERVICES: :SERVICES_TITLE:, :SIGN::COST_ROUND:/year',
    'l_bill_period' => 'Billing period',
    'l_bill_period_12' => '1 year',
    'l_bill_period_1' => '1 year',
    'l_bill_period_2' => '2 years',
    'l_bill_period_3' => '3 years',
    'l_bill_date' => 'm d y',
    'l_bill_websites' => 'Websites',
    'l_bill_cost_per_month' => 'Cost',
    'l_bill_total' => 'Total',
    'l_bill_total_to_pay' => 'Total to pay',
    'l_bill_months' => "'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'",
    'l_bill_text' => 'SSL certificate for site for :PERIOD:',
    'l_bill_comment' => 'SSL certificate for :PERIOD:, :COST:',
    'l_bill_comment_2' => 'SSL certificate for :SERVICES: :SERVICES_TITLE:, :SIGN::COST:, valid till :PAID_TILL:',
    'l_bill_comment_3' => ':SERVICES: websites, :SIGN::COST:, :PERIOD: months',
    'l_bill_cost_format' => ':SIGN::VALUE:',
    'l_bill_cost_format_full' => '<strong>:SIGN::VALUE:</strong> (:TOTAL_VALUE: USD)',
    'l_num_services' => array('website', 'websites'),
    'l_num_services_2' => array('website', 'websites'),
    'l_num_calls' => array('call', 'calls'),

    // Statuses

    'l_ssl_statuses' => array(
        'CHECKOUT' => array(
            'text' => 'Checkout',
            'hint' => 'Purchase the certificate'
        ),
        'WAIT_USER' => array(
            'text' => 'Activate',
            'hint' => 'Click on the button'
        ),
        'READY_CA' => array(
            'text' => 'Activated',
            'hint' => 'The certificate will be issued within 3-5 minutes, you will get a notice to :DCV_EMAIL_ADDRESS:.'
        ),
        'WAIT_CA' => array(
            'text' => 'Awaiting full validation',
            'hint' => 'Please see the instructions on email that has been sent to :DCV_EMAIL_ADDRESS: (<a href="#" data-id=":CERT_ID:" data-name=":COMMON_NAME:" class="dcv-link">change email</a>).'
        ),
        'ERROR' => array(
            'text' => 'Something wrong',
            'hint' => 'Please open a <a href="/my/support">support ticket</a> or <a href="#" class="text-danger delete-link" id="delete_:CERT_ID:" data-id=":CERT_ID:">delete</a>.'
        ),
        'ISSUED' => array(
            'text' => 'Active',
            'hint' => '<a href="/help/install-SSL-certificate" target="_blank">SSL Certificate setup manual</a>'
        )
    ),

    // Dashboard

    'l_ssl_dashboard_title' => 'SSL Dashboard',
    'l_add_cert' => 'Add cert',

    'l_ssl_empty_header' => '<p>Google recently introduced HTTPS as a ranking signal.</p><p>Using SSL encryption technology from Comodo</p><p>will help Boost your <strong>Google Ranking</strong>!</p>',
    'l_ssl_empty_title' => 'Positive SSL - $8.50/yr<span>The best for a personal blog or small or medium size business website</span>',
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

    'l_ssl_th_cert_id' => 'Cert #',
    'l_ssl_th_domains' => 'Domains',
    'l_ssl_th_type' => 'Type',
    'l_ssl_th_valid' => 'Valid',
    'l_ssl_th_created' => 'Created',
    'l_ssl_th_expires' => 'Expires',
    'l_ssl_th_status' => 'Status',

    'l_ssl_btn_delete' => 'Delete',
    'l_ssl_btn_cancel' => 'Cancel',
    'l_ssl_btn_download' => 'Download',
    'l_ssl_btn_send_email' => 'Send me the validation email',
    'l_ssl_btn_get_now' => 'Get now certificate $8.50',
    'l_ssl_btn_looks_good' => 'Looks good, onward',

    'l_ssl_helpful' => 'Helpful links',
    'l_ssl_helpful_links' => array(
        array('text' => 'How to generate CSR (Certificate Signing Request)?', 'url' => '/help/SSL-how-to-generate-CSR'),
        array('text' => 'How to install SSL Certificate?', 'url' => '/help/install-SSL-certificate')
    ),

    'l_ssl_modal_delete_title' => 'Delete Certificate',
    'l_ssl_modal_delete_message' => 'Do you really want to delete the certificate?',

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
    'l_ssl_generator_btn_csr' => 'Already have CSR?',
    'l_ssl_generator_btn_generator' => 'CSR Generator',
    'l_ssl_generator_btn_show' => 'Show',
    'l_ssl_generator_btn_dcv' => 'Send me the validation email',

    'l_ssl_generator_title' => 'CSR Generator',
    'l_ssl_generator_fields' => array(
        'commonName' => array(
            'label' => 'Common name',
            'placeholder' => 'example.com',
            'help' => 'The fully qualified domain name (FQDN) of your server. This must match exactly what you type in your web browser.'
        ),
        'email' => array(
            'label' => 'Email',
            'placeholder' => 'admin@example.com',
            'help' => 'An email address used to contact your organization.'
        ),
        'organization' => array(
            'label' => 'Organization',
            'placeholder' => 'Company',
            'help' => 'The legal name of your organization. This should not be abbreviated and should include suffixes such as Inc, Corp, LLC, or the name of the person for whom the certificate is issued.'
        ),
        'organizationUnit' => array(
            'label' => 'Organization unit',
            'placeholder' => 'Department name',
            'help' => 'The division of your organization handling the certificate or you do not have a department, indicate "IT" or "IT Department".'
        ),
        'country' => array(
            'label' => 'Country',
            'help' => 'The country where your organization is located.'
        ),
        'state' => array(
            'label' => 'State',
            'help' => 'The state/region where your organization is located. As the example Alabama.'
        ),
        'locality' => array(
            'label' => 'Locality',
            'placeholder' => 'Montgomery',
            'help' => 'The city where your organization is located.'
        )
    ),
    'l_ssl_generator_generate' => 'Generate',

    'l_ssl_csr_title' => 'Enter CSR',
    'l_ssl_csr_read' => 'Read my CSR',
    'l_ssl_csr_modal_title' => 'Check if we\'ve got you right',
    'l_ssl_csr_modal_items' => array(
        'domains' => 'SSL will cover',
        'commonName' => 'Common name',
        'emailAddress' => 'Email address',
        'organizationName' => 'Organization name',
        'organizationUnitName' => 'Organization unit name',
        'countryName' => 'Country name',
        'stateOrProvinceName' => 'State or province',
        'localityName' => 'Locality name'
    ),

    'l_ssl_dcv_title' => 'Choose email to validate ownership of'
));
