<?php

final class sql{
	
	// Session.php
	const update_password = "update users set password = md5('%s') where email = '%s';";

	// Bill.php
	const subscribe_insert = "insert into tariffs_activation values(%d, %d, from_unixtime(%d), %d, %s);";
	const subscribe_update = "update tariffs_activation set tariff_id = %d, subscribed = from_unixtime(%d) where user_id = %d and activated = 0;";
	const subscribe_check = "select tariff_id, user_id, activated from tariffs_activation where user_id = %d and activated = 0;";

	// Firstpage.php
	const get_tariffs = "select tariff_id, name, cost, mpd, period, auto_extend, top_words_edit, stop_list_edit, cost_usd, pmi, hosted_button_id, services, billing_period, cost / period as cost_per_day, cost_usd / period as cost_per_day_usd, allow_subscribe_panel, hosting, cost_usd_2, product_id from tariffs order by cost_per_day;";

	// All
	const insert_log = "insert into logs values(from_unixtime(%d), '%s', '%s')";
	
	const select_bill = "select * from bills where bill_id=%d;";
	const insert_bill = "insert into bills values(null, %d, %d, '%s', '%s', '%s', now(), 0, %d, %d, %d, %.2f);";
	const select_unpaid_bills = "select b.* from bills b, users u where b.fk_user = u.user_id and b.paid = 0 and u.email = '%s' and b.fk_tariff = %d and b.period = %d and b.promo_id = %d and b.use_balance = %d and b.charge_bonus = %.2f order by b.date desc;";
	const get_user_info = "select u.user_id, balance, fk_tariff, email, moderate, date_format(paid_till, '%%d.%%m.%%Y') as paid_till, unix_timestamp(paid_till) as paid_till_ts, freeze, password, max_pm, ui.timezone, unix_timestamp(u.created) as created_ts, inactive, ui.first_pay_id, created, ui.lang, u.phone, u.first_name, u.last_name, u.offer_tariff_id, free_days, show_review, currency, twitter_invite_key, friend_invite_key, ui.my_last_login, ui.http_accept_language, u.hoster_api_key, u.moderate_ip, trial, country, u.app_device_token, u.app_sender_id,u.meta,u.lead_source,u.google_auth_secret, u.avatar, u.user_token from users u left join users_info ui on ui.user_id = u.user_id where email='%s'";
	const get_tariff = "select * from tariffs where tariff_id = %d;";
	const find_user = "select u.user_id, email, password, profile_updated, my_last_login, moderate from users u left join users_info i on i.user_id = u.user_id where email = '%s';";
	const insert_user = "insert into users values(null, '%s', '%s', 0, now(), %d, '%s', 1, '%s', 0, null)";
	const check_email = "select count(user_id) from users where email='%s'";
	const get_all_links = "select * from links_my;";

	const get_page_info = 'select * from links_my where id=%d';
	const get_pi_template = 'select * from links_my where template=\'%s\'';

	const get_menu_links = '
		select 
			l.* 
		from 
			links_my l, links_my_groups lg, links_my_members lm
		where
			lm.fk_group=lg.id and lm.fk_link=l.id and lg.id=%d
		order by
			l.priority
	';
	
	//Session
	const get_firstpage_id = '
		select 
			l.id
		from 
			links_my l, links_my_groups lg, links_my_members lm
		where
			lm.fk_group=lg.id and lm.fk_link=l.id and lg.id=%d and l.priority>=0
		order by
			l.priority asc
	';
	
}

?>
