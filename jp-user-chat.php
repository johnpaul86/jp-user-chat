<?php
/*
Plugin Name: JP User Chat
Plugin URI: https://www.derniertec.biz/plugins/jp-user-chat
Description: This plugin intergrate the chat feature among WordPress users with shortcode. "Create chat button" will display if shortcode is added. Chat bubble will be displayed if the plugin is activated. This plugin works only for logged-in users.
Version: 1.0.0
Requires at least: 4.7
Requires PHP: 5.6
Author: John Paul O B
Author URI: https://johnpaul.derniertec.biz
Text Domain: jp-user-chat
License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

 * JPChat is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'JPCHAT_VERSION' ) ) {
	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 */
	define( 'JPCHAT_VERSION', '1.0.0' );
}

require_once plugin_dir_path(__FILE__) . 'jp-user-chat-admin.php';
require_once plugin_dir_path(__FILE__) . 'jp-ajax-functions.php';

function jpChat_activate(){
	global $wpdb;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	/*We are creating custom tables on plugin activation*/
	$sql="
	CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."jpchat`
	(
		id bigint(20) NOT NULL auto_increment,
		sender_id int(15) NOT NULL default 0,
		receiver_id int(15) NOT NULL default 0,
		message text,
		sent datetime NOT NULL default CURRENT_TIMESTAMP,
		recd tinyint(2) NOT NULL default 0,
		PRIMARY KEY  (`id`)
	);";
	dbDelta($sql);
	
	$sql2="
	CREATE TABLE IF NOT EXISTS `".$wpdb->prefix."jpchat_blockings`
	(
		id bigint(10) NOT NULL auto_increment,
		block_user_id int(15) NOT NULL default 0,
		receiver_id int(15) NOT NULL default 0,
		blocked_report varchar(255),
		blocked_on datetime NOT NULL default CURRENT_TIMESTAMP,
		status tinyint(2) NOT NULL default 0,
		PRIMARY KEY  (`id`)
	);";
	dbDelta($sql2);
}
register_activation_hook( __FILE__, 'jpChat_activate' );

function jpChat_uninstall(){
	// global $wpdb;
	/*Deleting the custom tables. This feature is moved to the plugin settings page*/
	// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jpchat" );
	// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}jpchat_blockings" );
	delete_option( 'jpchat_options' );
}
register_uninstall_hook( __FILE__, 'jpChat_uninstall' );

function my_script_enqueuer() {
	if ( is_user_logged_in() ) {
		/*We need to include these files only if the user is logged in*/
		wp_register_script( "jp_chat_script", plugin_dir_url( __FILE__ ).'/jp_chat_script.js', array('jquery'), JPCHAT_VERSION );
		wp_localize_script( 'jp_chat_script', 'jpChat', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));        
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jp_chat_script' );
		wp_enqueue_style('main-styles', plugin_dir_url( __FILE__ ).'/jp_chat_style.css', array(), JPCHAT_VERSION, false);
	}
}
add_action( 'wp_enqueue_scripts', 'my_script_enqueuer' );

function jpuser_canaccess(){
	
	/*Retrieving the values saved in plugin settings page*/
	
	$options = get_option( 'jpchat_options' );
	
	$jpchat_restrict_users = array();
	
	if(isset($options['jpchat_restrict_users'])){
		
		$jpchat_restrict_users = $options['jpchat_restrict_users'];
		
	}
	
	global $current_user;

    $user_roles = $current_user->roles;
	
	/*get the current user role*/
	
    $user_role = array_shift($user_roles);
	
	if(in_array(ucfirst($user_role), $jpchat_restrict_users)){
		
		return false;
		
	}else{
		
		return true;
		
	}
}

function jpchat( $attr ){
	if ( !is_user_logged_in() ) {
		return;
	}
	if(!jpuser_canaccess()){
		return;
	}
	$default_atts = array(
        'user_id' => get_current_user_id(),
        'button_type' => 'default',
        'label' => 'Send Message'
    );
	$atts = shortcode_atts( $default_atts, $attr, 'jpchat' );
	$user_id = $atts['user_id'];
	$button_type = $atts['button_type'];
	$label = $atts['label'];
	
	$jp_html_output = '';
	if(!empty($user_id) && $user_id != get_current_user_id()){
		if($button_type == 'default'){
			$jp_html_output .= '<a class="jp-open-chat-link" onclick="jp_open_message_box('.$user_id.');"></a>';
		}else if($button_type == 'button'){
			$jp_html_output .= '<button class="jpchat-custom-button" onclick="jp_open_message_box('.$user_id.');">'.$label.'</button>';
		}else if($button_type == 'bootstrap'){
			$jp_html_output .= '<input type="button" class="btn btn-primary jpchat-bootstrap-button" onclick="jp_open_message_box('.$user_id.');" value="'.$label.'"/>';
		}else if($button_type == 'link'){
			$jp_html_output .= '<a class="jpchat-custom-link" onclick="jp_open_message_box('.$user_id.');">'.$label.'</a>';
		}else{
			$jp_html_output .= '<a class="jp-open-chat-link" onclick="jp_open_message_box('.$user_id.');"></a>';
		}
	}
	return $jp_html_output;
}
add_shortcode( 'jpchat', 'jpchat' );

function jpchat_bubble(){
	if ( is_user_logged_in() && jpuser_canaccess() ) {
		$nonce = wp_create_nonce("jp_chat_nonce");
		$options = get_option( 'jpchat_options' );
		$jpchatTheme = "dark";
		if(isset($options['jpchat_theme'])){
			$jpchatTheme = $options['jpchat_theme'];
		}
		global $wpdb;
		$jp_userId = get_current_user_id();
		$jp_results = $wpdb->get_results( $wpdb->prepare( "SELECT sender_id, receiver_id FROM {$wpdb->prefix}jpchat WHERE sender_id = %d OR receiver_id = %d ORDER BY id DESC", $jp_userId, $jp_userId ) );
		
		$jp_results_count = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}jpchat WHERE receiver_id = %d AND recd = '0'", $jp_userId ) );
		
		$jp_users = array();
		$jp_current_user = array();
		$jp_current_user[] = $jp_userId;
		foreach($jp_results as $jp_result){
			$jp_users[] = $jp_result->sender_id;
			$jp_users[] = $jp_result->receiver_id;
		}
		$jp_users = array_diff( $jp_users, $jp_current_user );
		$jp_users = array_unique($jp_users);
		$jp_html_output = '';
		$jp_html_output .= '<div class="jp-container jpchat-theme-'.$jpchatTheme.'">';
		$jp_results_counter = '';
		$jp_results_counter_style = '';
		if(count($jp_results_count) > 0){
			$jp_results_counter = count($jp_results_count);
			$jp_results_counter_style = 'style = "display:block;"';
			
		}
		$jp_result_counter_html = '<span class="jp-message-counter" '.$jp_results_counter_style.'>'.$jp_results_counter.'</span>';
		$jp_html_output .= '<a onclick="jp_display_message_list();" class="jp-message-list-botton"><span class="jp-message-bubble">
		<svg fill="currentColor" viewBox="0 0 24 24" width="1em" height="1em" class="x1lliihq x1k90msu x2h7rmj x1qfuztq x198g3q0 x1qx5ct2 xw4jnvo"><path d="M.5 12C.5 5.649 5.649.5 12 .5S23.5 5.649 23.5 12 18.351 23.5 12 23.5c-1.922 0-3.736-.472-5.33-1.308a.63.63 0 0 0-.447-.069l-3.4.882a1.5 1.5 0 0 1-1.828-1.829l.882-3.4a.63.63 0 0 0-.07-.445A11.454 11.454 0 0 1 .5 12zm17.56-1.43a.819.819 0 0 0-1.125-1.167L14 11.499l-3.077-2.171a1.5 1.5 0 0 0-2.052.308l-2.93 3.793a.819.819 0 0 0 1.123 1.167L10 12.5l3.076 2.172a1.5 1.5 0 0 0 2.052-.308l2.931-3.793z"></path></svg>'.$jp_result_counter_html.'</span></a>';
		$jp_html_output .= '</div>';
		$jp_html_output .= '<div class="jp-msg-list-box jpchat-theme-'.$jpchatTheme.'" data-nonce="'.$nonce.'">';
		foreach( $jp_users as $jp_user ){
			$jp_user_obj = get_userdata( $jp_user );
			if( isset( $jp_user_obj ) && $jp_user_obj != false && intval($jp_user_obj->ID)!= '0' ){
				$jp_html_output .= '<p class="jp-messenger" data-jp-messenger-uid="'.$jp_user_obj->ID.'"><a onclick="jp_open_message_box('.$jp_user_obj->ID.');">'.get_avatar( $jp_user_obj->ID, 32 ).'<span class="jp-messager-name">'.$jp_user_obj->user_nicename.'</span></a><span class="jp-message-counter"></span></p>';
			}
		}
		$jp_html_output .= '</div>';

		$jp_html_output .= '<div id="jp-msg-box-container" class="jpchat-theme-'.$jpchatTheme.'"></div>';
		$jp_html_output .= '<div class="jp-chat-loader">
			<svg version="1.1" id="L4" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 100 20" enable-background="new 0 0 0 0" xml:space="preserve">
				<circle fill="#fff" stroke="none" cx="10" cy="10" r="3">
					<animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin="0.1"></animate>    
				</circle>
				<circle fill="#fff" stroke="none" cx="20" cy="10" r="3">
					<animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin="0.2"></animate>       
				</circle>
				<circle fill="#fff" stroke="none" cx="30" cy="10" r="3">
					<animate attributeName="opacity" dur="1s" values="0;1;0" repeatCount="indefinite" begin="0.3"></animate>     
				</circle>
			</svg>
		</div>';
		echo $jp_html_output;
	}
}
add_action( 'wp_head', 'jpchat_bubble' );

function check_blocked($receiver_id){
	global $wpdb;
	$block_user_id = intval( get_current_user_id() );
	$jp_results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}jpchat_blockings WHERE block_user_id = %d AND receiver_id = %d AND status = 1" , $block_user_id, $receiver_id ) );
	if( count($jp_results) > 0 ){
		return true;
	}else{
		return false;
	}
}
?>