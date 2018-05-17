<?php
/*
 $Header: /projects/napix/www/htdocs/includes/messages.php,v 1.13 2009/09/05 02:44:28 polux Exp $ 
 $Name:  $
 $Log: messages.php,v $
*/

final class messages{

	// css.php
	const set_new_timezone = 'Автоматически установлена временная зона UTC %d для пользователя %s.';
	const unknown_error = 'Неизвестная ошибка в строке %d файла %s';
	const review_link = '<a href="%s" target="_blank">%s</a>';

	// Session.php
	const password_changed = 'Пользователь %s изменил пароль.';
	const password_unknown = 'Пароль не известен!';
	const password_reseted = 'Пользователь %s сбросил пароль.';
	const password_length = 'Минимальная длина пароля %d символов.';
	const password_reset_failed = "Сброс пароля. Указан неизвестный адрес электронной почты %s!";
	const key_notfound = "Указан неизвестный ключ доступа!";
	const key_notfound_sys = "Указан неизвестный ключ доступа %s!";
	const reset_email_not_sent = "ВНИМАНИЕ! Не удалось отправить новый пароль на электронный адрес %s!";

	// Main.php
	const update_pays_id = 'Обновили информацию о первых платежах для %d пользователей в таблице users_info().';
	const update_postman = 'Добавили почтовые задания для %d пользователей.';

	// Bills.php
	const promokey_not_found_sys = "Неизвестный промокод \"%s\"!";
	const new_tariff = "Подключить тариф";
	const tariff_not_found = "Выбранный тариф не найден!";
	const user_logged_in = "Пользователь %s (%d) успешно авторизовался.";
	const user_logged_in_cooks = "Пользователь %s (%d) успешно авторизовался из сохраненной сессии.";
	const user_logged_out  = "Пользователь %s завершил работу.";
	const options_changed = "Пользователь %s изменил настройки.";
	const bill_comment = "Счет для оплаты из панели управления.";
	const pm_extended = "Пользователь %s продлил подключение на %d полезных сообщений максимальное значение %d.";
	const pm_extend_error = "Ошибка продления подключения для пользователя %s.";
	const not_store_memcache = 'Не удалось подключиться к Memcache серверу %s!';
	
	const email_exist = "Адрес электронной почты уже используется!";
	const email_not_valid = "Недопустимый формат электронной почты!";
	const login_failed = "Проверьте логин и пароль!";
	const login_failed_sys = "Ошибка авторизации %s!";
	const email_failed = "Проверьте адрес электронной почты!";
	const db_error = "Ошибка обращения к базе данных: %s";

	const user_linked = "Пользователь %s автоматически привязан к партнерскому акаунту %d, использован промо-код \"%s\".";
	
	const user_title = "Пользователь %s";

	// top_words.php
	const user_added_word = 'Пользователь %s добавил в словарь ключевое слово %s.';
	const user_deleted_word = 'Пользователь %s удалил из словаря ключевое слово %s.';
	
	// top_words.php
	const user_added_stop_word = 'Пользователь %s добавил стоп слово "%s".';
	const user_deleted_stop_word = 'Пользователь %s удалил стоп слово "%s".';

	// Simplepage.php
	const user_subscribed = 'Пользователь %s подписался на рассылку "%s".';
	const user_unsubscribed = 'Пользователь %s отписался от рассылки "%s".';

	const connection_extended_header = 'CleanTalk. Подключение';
	const connection_extended_message = '<p>Добрый день!</p>

<p>Ваше подключение по тарифу "%s" продлено до <b>%s</b>. Изменения вступят в силу в течении нескольких минут.</p>
<p>В счет продления подключения с текущего счета списано <b>%.2f</b> рублей, <b>%.2f</b> бонусных рублей.</p>
<p>Благодарим за пользование услугами сервиса "CleanTalk"!</p>
';
	const message_extended_pm = '<p>Уважаемый пользователь, %s!</p>

<p>Ваше подключение по тарифу "%s" продлено на <b>%d</b> полезных сообщений.</p>
<p>Изменения вступят в силу в течении нескольких минут.</p>
<p>Благодарим за пользование услугами сервиса "CleanTalk"!</p>
';
	
	const html_template = '
<html>
<head>
<title></title>
</head>
<body>
%s
</body>
</html>
	
	';
	const plain_template = '
%s
	
	';
    
    //
    // Profile.php
    //
	const changed_email_admin = 'Пользователь %s (%d) изменил E-mail адрес, новое значение: %s.';
	}
?>
