<?php

$lang = array_merge($lang, array(
    'l_page_title' => 'Платежи',
    'l_positive_requests' => 'Доступ к сервису успешно продлен, доступно <b>%d из %d</b> регистраций/сообщений!',
	'l_promo_conditions' => 'Скидка %s%%, действительна при подключении нового тарифа на %s месяца%s, использовать до %s.',
	'l_promo_conditions2' => 'Скидка %s%%, использовать до %s.',
	'l_and_more' => ' и более',
	'l_promokey_not_found' => 'Неизвестный код!',
  'l_promocode' => 'Промо код',
  'l_discount_code' => 'Купон на скидку',
  'l_use_code' => 'Использовать купон',
    'l_years_renew' => array('год', 'года', 'лет'),
	'l_billing_period' => 'Срок подписки',
	'l_account_name_short' => 'Учетная запись',
	'l_payments_not_found' => 'Извините, платежей для вашей учетной записи не найдено.',
	'l_payments_not_found_hint' => 'Если информация о вашем платеже отсутствует, проверьте пожалуйста ваш платежный акаунт (Интернет банк, Яндекс.Деньги, Qiwi и т.д.). Если платеж завершен, но отсутствует здесь, то свяжитесь пожалуйста с нами',
	'l_discount' => 'Скидка',
	'l_service_title' => 'обланый антиспам для вебсайтов',
	'l_secure_payment' => 'Все транзакции защищены',
	'l_payment_process' => 'Обработка платежа может занять до 5 минут',
	'l_onetime_payment_paypal' => 'Разовый PayPal платеж',
	'l_free_months_hint' => 'Заплати до <span class="red_text">%s</span> и получи бонус +3 бесплатных месяца подключения!',
    'l_promocode_info' => 'Личный промокод %s, скидка %s%%, использовать до %s.',

    'l_secure_page' => 'Мы не получаем доступа к Вашим платежным данным, так как все платежи осуществляются по защищенному каналу по протоколу SSL в зашифрованном виде.',

    'l_act_org_tpl' => '%s, %s. ИНН %s, ОГРН (ОГРНИП) %s',

    'l_act_tpl' => '
<h3>Акт на передачу прав №%d от %s</h3>
<hr />
<table width="100%%" border="0">
<tr>
    <td width="20%%">Исполнитель:
    </td>
    <td width="80%%">ООО «Клинтолк», 454021, г. Челябинск, ул. Игнатия Вандышева, 4, офис 130. ИНН 7447214693,
КПП 744701001, ОГРН 1127447012571 
    </td>
</tr>
<tr>
    <td colspan="2">
        &nbsp;
    </td>
</tr>
<tr>
    <td>Заказчик:
    </td>
    <td>%s
    </td>
</tr>
<tr>
    <td colspan="2">
        &nbsp;
    </td>
</tr>
<tr>
    <td>По счету:
    </td>
    <td>№%d
    </td>
</tr>
</table>
<br />
<br />
<table width="100%%" border="1" cellpadding="2">
<tr align="center" bgcolor="#eee">
    <th width="5%%">
        №
    </th>
    <th width="60%%">Наименование услуги
    </th>
    <th width="5%%">Кол-во
    </th>
    <th width="15%%">Стоимость
    </th>
    <th width="15%%">Сумма
    </th>
</tr>
<tr>
    <td>1</td>
    <td>%s</td>
    <td>1</td>
    <td>%s</td>
    <td>%s</td>
</tr>
</table>
<br />
<br />
<table width="100%%" border="0" align="right">
<tr>
    <td width="85%%">Итого без НДС:</td>
    <td width="15%%">%s</td>
</tr>
<tr>
    <td>Итого с НДС:</td>
    <td>-</td>
</tr>
<tr>
    <td>Всего к оплате:</td>
    <td>%s</td>
</tr>
</table>
<br />
<br />
<p>Всего оказано услуг на сумму: <b>%s</b>.</p>
<p>Вышеперечисленные услуги выполнены полностью и в срок. Заказчик претензий по объему, качеству и срокам оказания услуг не имеет.</p>
<br />
<br />
<table width="100%%" align="center" cellpadding="5">
    <tr>
        <td width="50%%">
            <b>Исполнитель</b>
        </td>
        <td width="50%%">
            <b>Заказчик</b>
        </td>
    </tr>
    <tr>
        <td>
            <table width="100%%">
                <tr valign="bottom">
                    <td><img src="/my/files/sign_shagimuratov.png" alt="" height="48" /><br />___________________<br/>подпись
                    </td>
                    <td>
                    <br />
                    <br />
                    <br />
                    <br />
                    <br />
                    Ген.директор /Шагимуратов Д.Р./
                    </td>
                </tr>
            </table>
        </td>
        <td width="50%%">
            <table width="100%%">
                <tr>
                    <td>
                    <br />
                    <br />
                    <br />
                    <br />
                    <br />
                    ___________________<br/>подпись
                    </td>
                    <td>
                    <br />
                    <br />
                    <br />
                    <br />
                    <br />
                    Ген.директор /%s/
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<img src="/my/files/sign_ooo.png" alt="" height="96" />
',
  'l_registration_complete' => 'Регистрация завершена',
  'l_registration_complete_hint' => 'Для активации защиты оплатите антиспам лицензию на 1 год и следуйте инструкции по установке.',
  'l_registration_complete_hint_spambots_check' => 'Для активации полного доступа к базе данных спам активных адресов оплатите лицензию на 1 год.',
  'l_renew' => 'Продлить',

  'l_ep_not_enabled' => 'Расширенный пакет - Не подключен',
  'l_ep_enabled' => 'Расширенный пакет - Подключен',
  'l_ep_buy_now' => 'Подключить расширенный пакет',
  'l_keep_history_45_days_title' => 'Хранить историю спам атак 45 дней.',
  'l_words_stop_list_title' => 'Фильтр сообщений по списку стоп-слов.',
  'l_countries_stop_list_title' => 'Фильтр спама по странам.',
  'l_server_response_addon_title' => 'Сообщение о причинах фильтрации посетителям сайта.',
  'l_anti_spam_packages' => 'Пакеты Анти-Спам услуг',
  'l_anti_spam_packages_non_productive' => 'Устаревшие пакеты Анти-Спам услуг',
  'l_anti_spam_packages_non_productive_hint' => 'Вы используете старый пакет, который был исключен из нашего прайс-листа. Но вы можете обновить пакет или выбрать один из пакетов текущего прайс-листа.',
  'l_show_details' => 'подробнее',
  'l_unlim_antisapm_title' => 'Неограниченная защита от спама.',
  'l_unlim_spamfirewall_title' => 'Неограниченная услуга SpamFireWall',
  'l_keep_history_7_days_title' => 'Хранить историю спам атак 7 дней.',
  'l_service_analytic' => 'Anti-Spam, SpamFireWall аналитика.',
  'l_private_service_title' => 'Фильтр спама по IP, Email, IP network.',
  'l_tech_support_title' => 'Техническая поддержка 24/7.',
  'l_grants_addon_title' => 'Делегирование управления сайтами другим пользователям сервиса CleanTalk.',

    'l_renew' => 'Продлить',
    'l_currency_hint' => 'Курсы валют предоставлены <a href="http://finance.yahoo.com/currency-converter/#from=USD;to=:CURRENCY:;amt=1" target="_blank">Yahoo finance</a>.',
    'l_cost_per_site_td' => 'Стоимость/сайт',
	'l_upgrade_discount_title' => 'Скидка за неиспользованный остаток на текущей лицензии.',
	'l_upgrade_discount_license_title' => 'Скидка за неиспользованный остаток на текущей лицензии.',
    'l_payment_date' => 'Дата оплаты',
    'l_account_creation_date' => 'Дата создания аккаунта',
    'l_license_valid_till' => 'Дата окончания лицензии',
    'l_compensation_label' => 'Компенсация',
    'l_review_external_label' => 'Отзыв на сайте',
    'l_friend_label' => 'Оплата друга',
    'l_early_pay_label' => 'Своевременная оплата',
    'l_review_label' => 'Отзыв',
    'l_twitter_label' => 'Твит',
    'l_other_label' => 'Другое',
    'l_yes' => 'Да',
    'l_no' => 'Нет',
    'l_get_months_premium' => 'Получите до %s бесплатных месяцев, которые доступны для премиум-аккаунтов.',
    'l_get_months_trial' => 'Получите до %s бесплатных месяцев, которые доступны для Вашего аккаунта.',
    'l_summary_months' => 'Всего: %s месяцев.',
));
