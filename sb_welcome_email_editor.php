<?php
/*
Plugin Name: SB Welcome Email Editor
Plugin URI: http://www.sean-barton.co.uk
Description: Allows you to change the wordpress welcome email (and resend passwords) for both admin and standard members. Simple!
Version: 2.4
Author: Sean Barton
Author URI: http://www.sean-barton.co.uk

Changelog:
<V1.6 - Didn't quite manage to add a changelog until now :)
V1.6 - 25/3/11 - Added user_id and custom_fields as hooks for use
V1.7 - 17/4/11 - Added password reminder service and secondary email template for it's use
V1.8 - 24/8/11 - Added [admin_email] hook to be parsed for both user and admin email templates instead of just the email headers
V1.9 - 24/10/11 - Removed conflict with User Access Manager plugin causing the resend welcome email rows to now show on the user list
V2.0 - 27/10/11 - Moved the user column inline next to the edit and delete user actions to save space
V2.1 - 17/11/11 - Added multisite support so that the welcome email will be edited and sent in the same way as the single site variant
V2.2 - 12/12/11 - Added edit box for the subject line and body text for the reminder email. Added option to turn off the reminder service
V2.3 - 16/12/11 - Broke the reminder service in the last update. This patch sorts it out. Also tested with WP 3.3
V2.4 - 03/01/12 - Minor update to disable the reminder service send button in the user list. Previously only stopped the logging but the button remained
*/

$sb_we_file = trailingslashit(str_replace('\\', '/', __FILE__));
$sb_we_dir = trailingslashit(str_replace('\\', '/', dirname(__FILE__)));
$sb_we_home = trailingslashit(str_replace('\\', '/', get_bloginfo('wpurl')));
$sb_we_active = true;

define('SB_WE_PRODUCT_NAME', 'SB Welcome Email Editor');
define('SB_WE_PLUGIN_DIR_PATH', $sb_we_dir);
define('SB_WE_PLUGIN_DIR_URL', trailingslashit(str_replace(str_replace('\\', '/', ABSPATH), $sb_we_home, $sb_we_dir)));
define('SB_WE_PLUGIN_DIRNAME', str_replace('/plugins/','',strstr(SB_WE_PLUGIN_DIR_URL, '/plugins/')));

$sb_we_admin_start = '<div id="poststuff" class="wrap"><h2>' . SB_WE_PRODUCT_NAME . '</h2>';
$sb_we_admin_end = '</div>';

$sb_we_pages = array(
__('Settings','sb_we')=>'sb_we_settings'
);

//sb_we_printr(get_option('active_plugins'));

function sb_we_loaded() {
	$settings = get_option('sb_we_settings');
	
	add_action('init', 'sb_we_init');
	add_action('admin_menu', 'sb_we_admin_page');
	
	if (!$settings->disable_reminder_service) {
		add_action('profile_update', 'sb_we_profile_update');
		add_filter('user_row_actions', 'sb_we_user_col_row', 10, 2);
	}
	
	//add_action('manage_users_custom_column', 'sb_we_user_col_row', 98, 3);
	//add_filter('manage_users_columns', 'sb_we_user_col');
	add_filter('wpmu_welcome_user_notification', 'sw_we_mu_new_user_notification', 10, 3 );
	
	global $sb_we_active;
	
	if (is_admin()) {
		if (!$sb_we_active) {
			$msg = '<div class="error"><p>' . SB_WE_PRODUCT_NAME . ' can not function because another plugin is conflicting. Please disable other plugins until this message disappears to fix the problem.</p></div>';
			add_action('admin_notices', create_function( '', 'echo \'' . $msg . '\';' ));
		}
		
		foreach ($_REQUEST as $key=>$value) {
			if (substr($key, 0, 6) == 'sb_we_') {
				if (substr($key, 0, 13) == 'sb_we_resend_') {
					if ($user_id = substr($key, 13)) {
						sb_we_send_new_user_notification($user_id, true);
						wp_redirect(admin_url('users.php'));
					}
				}
			}
		}		
	}
}

function sb_we_send_new_user_notification($user_id, $reminder=false) {
	$return = false;
	
	if (!$plaintext_pass = get_usermeta($user_id, 'sb_we_plaintext_pass')) {
		$plaintext_pass = '[Your Password Here]';
	}
	
	if (wp_new_user_notification($user_id, $plaintext_pass, $reminder)) {
		$return = 'Welcome email sent.';
	}
	
	return $return;
}

function sw_we_mu_new_user_notification($user_id, $password, $meta='') {
	return wp_new_user_notification($user_id, $password);
}

function sb_we_profile_update() {
	$pass1 = sb_we_post('pass1');
	$pass2 = sb_we_post('pass2');
	$action = sb_we_post('action');
	$user_id = sb_we_post('user_id');
	
	if ($action == 'update' && $user_id) {
		if ($pass1 && $pass1 == $pass2) {
			update_usermeta($user_id, 'sb_we_plaintext_pass', $pass1);
		}
	}
	
}

/*function sb_we_user_col($cols) {
	$cols['welcome_email'] = 'Resend Welcome Email';
	
	return $cols;
}*/

/*function sb_we_user_col_row($value, $col_name, $id) {
	$return = '-';
	
	if ($col_name == 'welcome_email') {
		$plain_pass = get_usermeta($id, 'sb_we_plaintext_pass');
		$last_sent = get_usermeta($id, 'sb_we_last_sent');
				
		if ($plain_pass) {
			$return = '<input type="submit" name="sb_we_resend_' . $id . '" value="Resend Welcome (Inc Pw)" />';
		} else {
			$return = '<input type="submit" name="sb_we_resend_' . $id . '" value="Resend Welcome (Ex Pw)" />';
		}

		if ($last_sent) {
			$last_sent_string = date('jS F Y H:i:s', $last_sent);
			if ($last_sent > time()-3600) {
				$last_sent_string = '<span style="color: green;">' . $last_sent_string . '</span>';
			}
			$return .= '<br /><em>Last Re/Sent: ' . $last_sent_string . '</em>';
		}
	}
	
	return $return;
}*/

function sb_we_user_col_row($actions, $user) {
	$return = '';
	
	$id = $user->ID;
	
	$plain_pass = get_user_meta($id, 'sb_we_plaintext_pass', true);
	$last_sent = get_user_meta($id, 'sb_we_last_sent', true);
	$style = 'cursor: pointer; display: inline;';
	$title = 'Click to send a reminder email to this user.';
			
	if ($plain_pass && $plain_pass != '[Your Password Here]') {
		$return = '<input style="' . $style . '" title="' . $title . ' We have their password to send (' . $plain_pass . ')." type="submit" name="sb_we_resend_' . $id . '" value="Remind PW" />';
	} else {
		$return = '';
	}

	/*if ($last_sent) {
		$last_sent_string = date('jS F Y H:i:s', $last_sent);
		if ($last_sent > time()-3600) {
			$last_sent_string = '<span style="color: green;">' . $last_sent_string . '</span>';
		}
		$return .= '<br /><em>Last Re/Sent: ' . $last_sent_string . '</em>';
	}*/

	if ($return) {
		$actions['welcome_email'] = $return;
	}
	
	return $actions;
}

function sb_we_init() {
	if (!get_option('sb_we_settings')) {
		$sb_we_settings = new stdClass();
		$sb_we_settings->user_subject = '[[blog_name]] Your username and password';
		$sb_we_settings->user_body = 'Username: [user_login]<br />Password: [user_password]<br />[login_url]';
		$sb_we_settings->admin_subject = '[[blog_name]] New User Registration';
		$sb_we_settings->admin_body = 'New user registration on your blog ' . $blog_name . '<br /><br />Username: [user_login]<br />Email: [user_email]';
		$sb_we_settings->admin_notify_user_id = 1;
		$sb_we_settings->remind_on_profile_update = 0;
		$sb_we_settings->disable_reminder_service = 0;
		$sb_we_settings->reminder_subject = '[[blog_name]] Your username and password reminder';
		$sb_we_settings->reminder_body = 'Just a reminder for you...<br /><br />Username: [user_login]<br />Password: [user_password]<br />[login_url]';
		$sb_we_settings->header_from_name = '';
		$sb_we_settings->header_from_email = '[admin_email]';
		$sb_we_settings->header_reply_to = '[admin_email]';
		$sb_we_settings->header_send_as = 'html';
		$sb_we_settings->header_additional = '';
		
		add_option('sb_we_settings', $sb_we_settings);
	}
}

if (!function_exists('wp_new_user_notification')) {
	function wp_new_user_notification($user_id, $plaintext_pass = '', $reminder = false) {
		global $sb_we_home, $current_site;;
		
		if ($user = new WP_User($user_id)) {
			$settings = get_option('sb_we_settings');
			
			if (!$settings->disable_reminder_service) {
				if (!in_array($plaintext_pass, array('[User password will appear here]', '[Your Password Here]'))) {
					update_usermeta($user_id, 'sb_we_plaintext_pass', $plaintext_pass); //store user password in case of reminder
				}
			}
			
			update_usermeta($user_id, 'sb_we_last_sent', time());
			
			$blog_name = get_option('blogname');
			if (is_multisite()) {
				$blog_name = $current_site->site_name;
			}
			
			$admin_email = get_option('admin_email');
			
			$user_login = stripslashes($user->user_login);
			$user_email = stripslashes($user->user_email);
			
			if (!$reminder) {
				$user_subject = $settings->user_subject;
				$user_message = $settings->user_body;
			} else {
				$user_subject = $settings->reminder_subject;
				$user_message = $settings->reminder_body;
			}
			
			$admin_subject = $settings->admin_subject;
			$admin_message = $settings->admin_body;
			
			$first_name = $user->first_name;
			$last_name = $user->last_name;
			
			//Headers
			$headers = '';
			if ($reply_to = $settings->header_reply_to) {
				$headers .= 'Reply-To: ' . $reply_to . "\r\n";
			}
			if ($from_email = $settings->header_from_email) {
				apply_filters('wp_mail_from', create_function('$i', 'return $from_email;'), 1, 100);
				
				if ($from_name = $settings->header_from_name) {
					apply_filters('wp_mail_from_name',create_function('$i', 'return $from_name;'), 1, 100);
					$headers .= 'From: ' . $from_name . ' <' . $from_email . ">\r\n";
				} else {
					$headers .= 'From: ' . $from_email . "\r\n";
				}
			}
			if ($send_as = $settings->header_send_as) {
				if ($send_as == 'html') {
					if (!$charset = get_bloginfo('charset')) {
						$charset = 'iso-8859-1';
					}
					$headers .= 'Content-type: text/html; charset=' . $charset . "\r\n";
					
					apply_filters('wp_mail_content_type', create_function('$i', 'return "text/html";'), 1, 100);
					apply_filters('wp_mail_charset', create_function('$i', 'return $charset;'), 1, 100);
				}
			}
			if ($additional = $settings->header_additional) {
				$headers .= $additional;
			}
			
			$headers = str_replace('[admin_email]', $admin_email, $headers);
			$headers = str_replace('[blog_name]', $blog_name, $headers);
			$headers = str_replace('[site_url]', $sb_we_home, $headers);
			//End Headers
			
			//Don't notify if the admin object doesn't exist;
			if ($settings->admin_notify_user_id) {
				//Allows single or multiple admins to be notified. Admin ID 1 OR 1,3,2,5,6,etc...
				$admins = explode(',', $settings->admin_notify_user_id);
				
				if (!is_array($admins)) {
					$admins = array($admins);
				}
				
				global $wpdb;
				$sql = 'SELECT meta_key, meta_value
					FROM ' . $wpdb->usermeta . '
					WHERE user_ID = ' . $user_id;
				$custom_fields = array();
				if ($meta_items = $wpdb->get_results($sql)) {
					foreach ($meta_items as $i=>$meta_item) {
						$custom_fields[$meta_item->meta_key] = $meta_item->meta_value;
					}
				}
				
				$admin_message = str_replace('[blog_name]', $blog_name, $admin_message);
				$admin_message = str_replace('[admin_email]', $admin_email, $admin_message);
				$admin_message = str_replace('[site_url]', $sb_we_home, $admin_message);
				$admin_message = str_replace('[login_url]', $sb_we_home . 'wp-login.php', $admin_message);
				$admin_message = str_replace('[user_email]', $user_email, $admin_message);
				$admin_message = str_replace('[user_login]', $user_login, $admin_message);
				$admin_message = str_replace('[first_name]', $first_name, $admin_message);
				$admin_message = str_replace('[last_name]', $last_name, $admin_message);
				$admin_message = str_replace('[user_id]', $user_id, $admin_message);
				$admin_message = str_replace('[plaintext_password]', $plaintext_pass, $admin_message);
				$admin_message = str_replace('[user_password]', $plaintext_pass, $admin_message);
				$admin_message = str_replace('[custom_fields]', '<pre>' . print_r($custom_fields, true) . '</pre>', $admin_message);
			
				$admin_subject = str_replace('[blog_name]', $blog_name, $admin_subject);
				$admin_subject = str_replace('[site_url]', $sb_we_home, $admin_subject);
				$admin_subject = str_replace('[first_name]', $first_name, $admin_subject);
				$admin_subject = str_replace('[last_name]', $last_name, $admin_subject);
				$admin_subject = str_replace('[user_email]', $user_email, $admin_subject);
				$admin_subject = str_replace('[user_login]', $user_login, $admin_subject);				
				$admin_subject = str_replace('[user_id]', $user_id, $admin_subject);				
				
				foreach ($admins as $admin_id) {
					if ($admin = new WP_User($admin_id)) {
						wp_mail($admin->user_email, $admin_subject, $admin_message, $headers);
					}
				}
			}
		
			if (!empty($plaintext_pass)) {
				$user_message = str_replace('[admin_email]', $admin_email, $user_message);
				$user_message = str_replace('[site_url]', $sb_we_home, $user_message);
				$user_message = str_replace('[login_url]', $sb_we_home . 'wp-login.php', $user_message);
				$user_message = str_replace('[user_email]', $user_email, $user_message);
				$user_message = str_replace('[user_login]', $user_login, $user_message);
				$user_message = str_replace('[last_name]', $last_name, $user_message);
				$user_message = str_replace('[first_name]', $first_name, $user_message);
				$user_message = str_replace('[user_id]', $user_id, $user_message);
				$user_message = str_replace('[plaintext_password]', $plaintext_pass, $user_message);
				$user_message = str_replace('[user_password]', $plaintext_pass, $user_message);
				$user_message = str_replace('[blog_name]', $blog_name, $user_message);
				
				$user_subject = str_replace('[blog_name]', $blog_name, $user_subject);
				$user_subject = str_replace('[site_url]', $sb_we_home, $user_subject);
				$user_subject = str_replace('[user_email]', $user_email, $user_subject);
				$user_subject = str_replace('[last_name]', $last_name, $user_subject);
				$user_subject = str_replace('[first_name]', $first_name, $user_subject);
				$user_subject = str_replace('[user_login]', $user_login, $user_subject);			
				$user_subject = str_replace('[user_id]', $user_id, $user_subject);			
			
				wp_mail($user_email, $user_subject, $user_message, $headers);
			}
		}
		
		return true;
	}
} else {
	$sb_we_active = false;
}

function sb_we_update_settings() {
	$old_settings = get_option('sb_we_settings');

	$settings = new stdClass();
	if ($post_settings = sb_we_post('settings')) {
		foreach ($post_settings as $key=>$value) {
			$settings->$key = stripcslashes($value);
		}
	
		if (update_option('sb_we_settings', $settings)) {
			sb_we_display_message(__('Settings have been successfully saved', 'sb_we'));
		}
	}
}

function sb_we_display_message($msg, $error=false, $return=false) {
    $class = 'updated fade';
    
    if ($error) {
        $class = 'error';
    }
	
    $html = '<div id="message" class="' . $class . '" style="margin-top: 5px; padding: 7px;">' . $msg . '</div>';

    if ($return) {
            return $html;
    } else {
            echo $html;
    }
}

function sb_we_settings() {
	if (sb_we_post('submit')) {
		sb_we_update_settings();
	}
	
	if (sb_we_post('test_send')) {
		global $current_user;
		get_currentuserinfo();
		
		wp_new_user_notification($current_user->ID, '[User password will appear here]');
		sb_we_display_message('Test email sent to "' . $current_user->user_email . '"');
	}
	
	$html = '';
	$settings = get_option('sb_we_settings');
	
	$page_options = array(
	'settings[user_subject]'=>array(
		'title'=>'User Email Subject'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Subject line for the email sent to the user.'
	)
	, 'settings[user_body]'=>array(
		'title'=>'User Email Body'
		, 'type'=>'textarea'
		, 'style'=>'width: 650px; height: 500px;'
		, 'description'=>'Body content for the email sent to the user.'
	)
	, 'settings[admin_subject]'=>array(
		'title'=>'Admin Email Subject'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Subject Line for the email sent to the admin user(s).'
	)
	, 'settings[admin_body]'=>array(
		'title'=>'Admin Email Body'
		, 'type'=>'textarea'
		, 'style'=>'width: 650px; height: 300px;'
		, 'description'=>'Body content for the email sent to the admin user(s).'
	)
	,'settings[disable_reminder_service]'=>array(
		'title'=>'Disable Reminder Service'
		, 'type'=>'yes_no'
		, 'style'=>'width: 500px;'
		, 'description'=>'Allows the admin to send users their passwords again if they forget them. Turn this off here if you want to'
	)	
	,'settings[reminder_subject]'=>array(
		'title'=>'Reminder Email Subject'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Subject line for the reminder email that admin can send to a user.'
	)
	, 'settings[reminder_body]'=>array(
		'title'=>'Reminder Email Body'
		, 'type'=>'textarea'
		, 'style'=>'width: 650px; height: 500px;'
		, 'description'=>'Body content for the reminder email that admin can send to a user.'
	)	
	, 'settings[header_from_email]'=>array(
		'title'=>'From Email Address'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Optional Header sent to change the from email address for new user notification.'
	)
	, 'settings[header_from_name]'=>array(
		'title'=>'From Name'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Optional Header sent to change the from name for new user notification.'
	)	
	, 'settings[header_reply_to]'=>array(
		'title'=>'Reply To Email Address'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Optional Header sent to change the reply to address for new user notification.'
	)
	, 'settings[header_send_as]'=>array(
		'title'=>'Send Email As'
		, 'type'=>'select'
		, 'style'=>'width: 100px;'
		, 'options'=>array(
			'text'=>'TEXT'
			, 'html'=>'HTML'
		)
		, 'description'=>'Send email as Text or HTML (Remember to remove html from text emails).'
	)
	, 'settings[header_additional]'=>array(
		'title'=>'Additional Email Headers'
		, 'type'=>'textarea'
		, 'style'=>'width: 550px; height: 200px;'
		, 'description'=>'Optional field for advanced users to add more headers. Dont\'t forget to separate headers with \r\n.'
	)
	, 'settings[admin_notify_user_id]'=>array(
		'title'=>'Send Admin Email To...'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'This allows you to type in the User IDs of the people who you want the admin notification to be sent to. 1 is admin normally but just add more separating by commas (eg: 1,2,3,4).'
	)	
	, 'submit'=>array(
		'title'=>''
		, 'type'=>'submit'
		, 'value'=>'Update Settings'
	)
	, 'test_send'=>array(
		'title'=>''
		, 'type'=>'submit'
		, 'value'=>'Test Emails (Save first, will send to current user)'
	)
	);
	
	$html .= '<div style="margin-bottom: 10px;">' . __('This page allows you to update the Wordpress welcome email and add headers to make it less likely to fall into spam. You can edit the templates for both the admin and user emails and assign admin members to receive the notifications. Use the following hooks in any of the boxes below: [site_url], [login_url], [user_email], [user_login], [plaintext_password], [blog_name], [admin_email], [user_id], [custom_fields], [first_name], [last_name]', 'sb_we') . '</div>';	
	$html .= sb_we_start_box('Settings');
	
	$html .= '<form method="POST">';
	$html .= '<table class="widefat form-table">';
	
	$i = 0;
	foreach ($page_options as $name=>$options) {
		if ($options['type'] == 'submit') {
			$value = $options['value'];
		} else {
			$tmp_name = str_replace('settings[', '', $name);
			$tmp_name = str_replace(']', '', $tmp_name);
			$value = stripslashes(sb_we_post($tmp_name, $settings->$tmp_name));
		}
		$title = (isset($options['title']) ? $options['title']:false);
		
		$html .= '	<tr class="' . ($i%2 ? 'alternate':'') . '">
					<th style="vertical-align: top;">
						' . $title . '
						' . ($options['description'] ? '<div style="font-size: 10px; color: gray;">' . $options['description'] . '</div>':'') . '
					</th>
					<td style="' . ($options['type'] == 'submit' ? 'text-align: right;':'') . '">';
					
		switch ($options['type']) {
			case 'text':
				$html .= sb_we_get_text($name, $value, $options['class'], $options['style']);
				break;
			case 'yes_no':
				$html .= sb_we_get_yes_no($name, $value, $options['class'], $options['style']);
				break;
			case 'textarea':
				$html .= sb_we_get_textarea($name, $value, $options['class'], $options['style'], $options['rows'], $options['cols']);
				break;
			case 'select':
				$html .= sb_we_get_select($name, $options['options'], $value, $options['class'], $options['style']);
				break;			
			case 'submit':
				$html .= sb_we_get_submit($name, $value, $options['class'], $options['style']);
				break;
		}
		
		$html .= '		</td>
				</tr>';
				
		$i++;
	}
	
	$html .= '</table>';
	$html .= '</form>';
	
	$html .= sb_we_end_box();;
	
	return $html;
}

function sb_we_printr($array=false) {
    if (!$array) {
        $array = $_POST;
    }
    
    echo '<pre>';
    print_r($array);
    echo '</pre>';
}

function sb_we_get_textarea($name, $value, $class=false, $style=false, $rows=false, $cols=false) {
	$rows = ($rows ? ' rows="' . $rows . '"':'');
	$cols = ($cols ? ' cols="' . $cols . '"':'');
	$style = ($style ? ' style="' . $style . '"':'');
	$class = ($class ? ' class="' . $class . '"':'');
	
	return '<textarea name="' . $name . '" ' . $rows . $cols . $style . $class . '>' . wp_specialchars($value, true) . '</textarea>';
}

function sb_we_get_select($name, $options, $value, $class=false, $style=false) {
	$style = ($style ? ' style="' . $style . '"':'');
	$class = ($class ? ' class="' . $class . '"':'');
	
	$html = '<select name="' . $name . '" ' . $class . $style . '>';
	if (is_array($options)) {
		foreach ($options as $val=>$label) {
			$html .= '<option value="' . $val . '" ' . ($val == $value ? 'selected="selected"':'') . '>' . $label . '</option>';
		}
	}
	$html .= '</select>';
	
	return $html;
}

function sb_we_get_input($name, $type=false, $value=false, $class=false, $style=false, $attributes=false) {
	$style = ($style ? ' style="' . $style . '"':'');
	$class = ($class ? ' class="' . $class . '"':'');
	$value = 'value="' . wp_specialchars($value, true) . '"';
	$type = ($type ? ' type="' . $type . '"':'');
	
	return '<input name="' . $name . '" ' . $value . $type . $style . $class . ' ' . $attributes . ' />';
}

function sb_we_get_text($name, $value=false, $class=false, $style=false) {
	return sb_we_get_input($name, 'text', $value, $class, $style);
}

function sb_we_get_yes_no($name, $value=false, $class=false, $style=false) {
	$return = '';
	
	$return .= 'Yes: ' . sb_we_get_input($name, 'radio', 1, $class, $style, ($value == 1 ? 'checked="checked"':'')) . '<br />';
	$return .= 'No: ' . sb_we_get_input($name, 'radio', 0, $class, $style, ($value == 1 ? '':'checked="checked"'));
	
	return $return;
}

function sb_we_get_submit($name, $value=false, $class=false, $style=false) {
	if (strpos($class, 'button') === false) {
		$class .= 'button';
	}
	
	return sb_we_get_input($name, 'submit', $value, $class, $style);
}

function sb_we_start_box($title , $return=true){
	$html = '	<div class="postbox" style="margin: 5px 0px; min-width: 0px !important;">
					<h3>' . __($title, 'sb_we') . '</h3>
					<div class="inside">';

	if ($return) {
		return $html;
	} else {
		echo $html;
	}
}

function sb_we_end_box($return=true) {
	$html = '</div>
		</div>';

	if ($return) {
		return $html;
	} else {
		echo $html;
	}
}

function sb_we_admin_page() {
	global $sb_we_pages;
	
	$admin_page = 'sb_we_settings';
	$func = 'sb_we_admin_loader';
	$access_level = 'manage_options';

	add_menu_page(SB_WE_PRODUCT_NAME, SB_WE_PRODUCT_NAME, $access_level, $admin_page, $func);

	foreach ($sb_we_pages as $title=>$page) {
		add_submenu_page($admin_page, $title, $title, $access_level, $page, $func);
	}

}

function sb_we_admin_loader() {
	global $sb_we_admin_start, $sb_we_admin_end;
	
	$page = str_replace(SB_WE_PLUGIN_DIRNAME, '', trim($_REQUEST['page']));
	
	echo $sb_we_admin_start;
	echo $page();
	echo $sb_we_admin_end;
}

function sb_we_post($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_POST, $key, $default, $escape, $strip_tags);
}

function sb_we_session($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_SESSION, $key, $default, $escape, $strip_tags);
}

function sb_we_get($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_GET, $key, $default, $escape, $strip_tags);
}

function sb_we_request($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_REQUEST, $key, $default, $escape, $strip_tags);
}

function sb_we_get_superglobal($array, $key, $default='', $escape=false, $strip_tags=false) {

	if (isset($array[$key])) {
		$default = $array[$key];

		if ($escape) {
			$default = mysql_real_escape_string($default);
		}

		if ($strip_tags) {
			$default = strip_tags($default);
		}
	}

	return $default;
}

add_action('plugins_loaded', 'sb_we_loaded');

?>