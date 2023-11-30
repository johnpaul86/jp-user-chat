<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function jp_update_sender_list(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$nonce = wp_create_nonce("jp_chat_nonce");
	global $wpdb;
	$jp_userId = get_current_user_id();
	$jp_tablename = $wpdb->prefix . "jpchat";
	$jp_results = $wpdb->get_results( 'SELECT sender_id, receiver_id FROM '.$jp_tablename.' WHERE sender_id = '.$jp_userId.' OR receiver_id = '.$jp_userId.' ORDER BY id DESC');
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
	foreach( $jp_users as $jp_user ){
		$jp_user_obj = get_userdata( $jp_user );
		$jp_html_output .= '<p class="jp-messenger" data-jp-messenger-uid="'.$jp_user_obj->ID.'"><a onclick="jp_open_message_box('.$jp_user_obj->ID.');">'.get_avatar( $jp_user_obj->ID, 32 ).'<span class="jp-messager-name">'.$jp_user_obj->user_nicename.'</span></a><span class="jp-message-counter"></span></p>';
	}
	echo $jp_html_output;
	exit;
}
add_action( 'wp_ajax_jp_update_sender_list', 'jp_update_sender_list' );

function jp_message_counter(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$jp_result = array();
	$jp_result['new_messages'] = 'no';
	global $wpdb;
	$jp_userId = get_current_user_id();
	$jp_tablename = $wpdb->prefix . "jpchat";
	$jp_results_count = $wpdb->get_results( 'SELECT id, sender_id, count(id) AS msgs, max(id) as last_id FROM '.$jp_tablename.' WHERE receiver_id = '.$jp_userId.' AND recd = "0" GROUP BY sender_id ORDER BY id ASC', ARRAY_A);
	if(count($jp_results_count)>0){
		$jp_result['new_messages'] = 'yes';
		$jp_result['results'] = $jp_results_count;
	}else{
		$jp_result['new_messages'] = 'no';
	}
	echo json_encode($jp_result);
	exit;
}
add_action( 'wp_ajax_jp_message_counter', 'jp_message_counter' );

function jp_send_message(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$jp_send_to = intval( $_POST['jp_send_to'] );
	$jp_response = array();
	if( $jp_send_to > 0 ){
		global $wpdb;
		$jp_send_from = intval( get_current_user_id() );
		$jp_msg = $_POST['jp_msg'];
		if(is_int($jp_send_to)){
			if(check_blocked($jp_send_to)){
				$jp_response['message'] = 'user blocked';
				$jp_response['response'] = 'error';
			}else{
				$jp_tablename = $wpdb->prefix . "jpchat";
				$result_check = $wpdb->insert($jp_tablename,
					array(
						'sender_id' => $jp_send_from,
						'receiver_id' => $jp_send_to,
						'message' => $jp_msg,
					),
					array(
						'%d',
						'%d',
						'%s',
					)
				);
				if($result_check){
					$jp_results = $wpdb->get_results( 'SELECT sent FROM '.$jp_tablename.' WHERE id = '.$wpdb->insert_id );
					$jp_msgdAt = '';
					if(isset($jp_results[0])){
						$jp_dateTime_format = get_option('date_format').' '.get_option('time_format');
						$jp_msgdAt = mysql2date( $jp_dateTime_format, $jp_results[0]->sent );
					}
					$jp_response['response'] = 'success';
					$jp_response['jp_msgDate'] = $jp_msgdAt;
				}else{
					$jp_response['response'] = 'error';
				}
			}
		}
	}
	echo json_encode($jp_response);
	wp_die();
}
add_action( 'wp_ajax_jp_send_message', 'jp_send_message' );

function jp_report_user(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$block_user_id = intval( $_POST['block_user_id'] );
	$report_text = $_POST['report_text'];
	$jp_response = array();
	if( $block_user_id > 0 ){
		global $wpdb;
		$jp_send_from = intval( get_current_user_id() );
		$jp_tablename = $wpdb->prefix . "jpchat_blockings";
		$jp_results = $wpdb->get_results( 'SELECT * FROM '.$jp_tablename.' WHERE block_user_id = '.$block_user_id.' AND receiver_id = '.$jp_send_from.' AND status = 1' );
		if( count($jp_results) > 0 ){
			$result = $wpdb->update($jp_tablename, array('blocked_report'=>$report_text), array('id'=>$jp_results[0]->id));
			if($result){
				$jp_response['message'] = 'reported';
				$jp_response['response'] = 'success';
			}else{
				$jp_response['response'] = 'error';
			}
		}
		echo json_encode($jp_response);
		wp_die();
	}
}
add_action( 'wp_ajax_jp_report_user', 'jp_report_user' );

function jp_block_user(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$block_user_id = intval( $_POST['block_user_id'] );
	$jp_response = array();
	if( $block_user_id > 0 ){
		global $wpdb;
		$jp_send_from = intval( get_current_user_id() );
		$jp_tablename = $wpdb->prefix . "jpchat_blockings";
		$jp_results = $wpdb->get_results( 'SELECT * FROM '.$jp_tablename.' WHERE block_user_id = '.$block_user_id.' AND receiver_id = '.$jp_send_from.' AND status = 1' );
		if( count($jp_results) > 0 ){
			$result_check = $wpdb->delete( $jp_tablename, array( 'id' => $jp_results[0]->id ) );
			$jp_response['deleted_id'] = $jp_results[0]->id;
			$jp_response['message'] = 'unblocked';
		}else{
			$result_check = $wpdb->insert($jp_tablename,
				array(
					'block_user_id' => $block_user_id,
					'receiver_id' => $jp_send_from,
					'status' => 1,
				),
				array(
					'%d',
					'%d',
					'%d',
				)
			);
			$jp_response['insert_id'] = $wpdb->insert_id;
			$jp_response['message'] = 'blocked';
		}
		if($result_check){
			$jp_response['response'] = 'success';
		}else{
			$jp_response['response'] = 'error';
		}
		echo json_encode($jp_response);
		wp_die();
	}
}
add_action( 'wp_ajax_jp_block_user', 'jp_block_user' );

function jp_read_messages(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$jp_send_to = intval( $_POST['jp_send_to'] );
	if( $jp_send_to > 0 ){
		global $wpdb;
		$jp_send_from = intval( get_current_user_id() );
		$jp_tablename = $wpdb->prefix . "jpchat_blockings";
		$jp_blockResults = $wpdb->get_results( 'SELECT * FROM '.$jp_tablename.' WHERE block_user_id = '.$jp_send_to.' AND receiver_id = '.$jp_send_from.' AND status = 1' );
		$jp_blockClass = '';
		if( count($jp_blockResults) > 0 ){
			$jp_block_btn_title = "Unblock this user";
			$jp_blockClass = 'jp-user-blocked';
		}else{
			$jp_block_btn_title = "block this user";
		}
		?>
		<div class="jp-chat-box jp-chat-box-user-<?php echo $jp_send_to; ?> <?php echo $jp_blockClass; ?>" data-jp-chat-user-id="<?php echo $jp_send_to; ?>">
			<div class="jp-chat-header">
				<div class="jp-chat-header-author-block">
					<?php
					echo get_avatar( $jp_send_to, 32 );
					$jp_user_obj = get_userdata( $jp_send_to );
					echo '<span class="name">'.$jp_user_obj->user_nicename.'</span>';
					?>
				</div>
				<div class="jp-chat-header-btn-block">
					<div class="jp-minimize jp-header-btns" style="display:none;">						
						<svg width="20" height="20" version="1.1" xmlns="http://www.w3.org/2000/svg">
							<line stroke-dasharray="20, 10, 5, 10, 15" x1="0" y1="10" x2="20" y2="10" style="stroke-width: 4px;stroke:rgb(255,255,255)"></line>
						</svg>
					</div>
					<div class="jp-close jp-header-btns" onclick="jp_close_chat_window('<?php echo $jp_send_to; ?>');" title="Close this chat window">
						<svg width="20" height="20" viewbox="0 0 40 40">
							<path d="M 5,5 L 35,35 M 35,5 L 5,35" style="stroke-width: 5px;stroke:rgb(255,255,255)"/>
						</svg>
					</div>
					<div class="jp-block-btn jp-header-btns" onclick="jp_block_user('<?php echo $jp_send_to; ?>');" title="<?php echo $jp_block_btn_title; ?>"></div>
					<label class="jp-display-when" title="Display Date and Time"> 
						<input type="checkbox" name="jp-display-when" title="Display Date and Time" onClick="jp_display_when_selector(this, <?php echo $jp_send_to; ?>);">
						<span class="jp-custom-checkbox"></span> 
					</label>
				</div>
			</div>
			<div class="jp-chat-alerts"></div>
			<div class="jp-chat-messages">
				<div class="jp-message-container">
				</div>
			</div>
			<div class="jp-chat-footer">
				<textarea onKeyPress="jp_text_typed( this, event );" class="jp-textarea"></textarea>
				<span class="jp_send_btn" onClick="jp_send_btn_action(this);">
					<svg height="20px" viewBox="0 0 24 24" width="20px"><title>Press Enter to send</title>
						<path d="M16.6915026,12.4744748 L3.50612381,13.2599618 C3.19218622,13.2599618 3.03521743,13.4170592 3.03521743,13.5741566 L1.15159189,20.0151496 C0.8376543,20.8006365 0.99,21.89 1.77946707,22.52 C2.41,22.99 3.50612381,23.1 4.13399899,22.8429026 L21.714504,14.0454487 C22.6563168,13.5741566 23.1272231,12.6315722 22.9702544,11.6889879 C22.8132856,11.0605983 22.3423792,10.4322088 21.714504,10.118014 L4.13399899,1.16346272 C3.34915502,0.9 2.40734225,1.00636533 1.77946707,1.4776575 C0.994623095,2.10604706 0.8376543,3.0486314 1.15159189,3.99121575 L3.03521743,10.4322088 C3.03521743,10.5893061 3.34915502,10.7464035 3.50612381,10.7464035 L16.6915026,11.5318905 C16.6915026,11.5318905 17.1624089,11.5318905 17.1624089,12.0031827 C17.1624089,12.4744748 16.6915026,12.4744748 16.6915026,12.4744748 Z" style="stroke:rgb(255,255,255);fill:#fff;"></path>
					</svg>
				</span>
			</div>
		</div>
		<?php
	}
	wp_die();
}
add_action( 'wp_ajax_jp_read_messages', 'jp_read_messages' );

function jp_update_msgStatus(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$jp_mid = intval( $_POST['jp_mid'] );
	$sender_id = intval( $_POST['sender_id'] );
	$jp_current_userID = intval( get_current_user_id() );
	global $wpdb;
	$jp_tablename = $wpdb->prefix . "jpchat";
	$result = $wpdb->query($wpdb->prepare('UPDATE '.$jp_tablename.' SET recd=1 WHERE id <='.$jp_mid.' AND sender_id = '.$sender_id.' AND receiver_id = '.$jp_current_userID ));
	echo json_encode($result);
	exit;
}
add_action( 'wp_ajax_jp_update_msgStatus', 'jp_update_msgStatus' );

function jp_fetchData(){
	if ( !wp_verify_nonce( $_POST['nonce'], "jp_chat_nonce")) {
		echo json_encode("No naughty business please");
		exit;
	}
	$jp_output = array();
	$jp_output['response'] = 'no-data';
	$jp_send_to = intval( $_POST['jp_send_to'] );
	if( $jp_send_to > 0 ){
		global $wpdb;
		$jp_current_userID = intval( get_current_user_id() );
		$jp_tablename = $wpdb->prefix . "jpchat";
		if( $_POST['triggerOn'] == 'initial' ){
			$jp_results = $wpdb->get_results( 'SELECT * FROM '.$jp_tablename.' WHERE ( sender_id = '.$jp_current_userID.' AND receiver_id = '.$jp_send_to.' ) OR ( sender_id = '.$jp_send_to.' AND receiver_id = '.$jp_current_userID.' ) ORDER BY id ASC');
		}else{
			$jp_results = $wpdb->get_results( 'SELECT * FROM '.$jp_tablename.' WHERE sender_id = '.$jp_send_to.' AND receiver_id = '.$jp_current_userID.' AND recd="0" ORDER BY id ASC');
		}
		if( count($jp_results) > 0 ){
			$i = 0;
			foreach($jp_results as $jp_result){
				$jp_className = 'jp-receiver';
				$jp_recd = 'read';
				if( $jp_current_userID == $jp_result->sender_id ){
					$jp_className = 'jp-sender';
				}else{
					if( 0 == $jp_result->recd ){
						$jp_recd = 'no-read';
					}
				}
				$jp_dateTime_format = get_option('date_format').' '.get_option('time_format');
				$jp_msgdAt = mysql2date( $jp_dateTime_format, $jp_result->sent );
				$jp_output['data'][$i]['jp_msgdAt'] = $jp_msgdAt;
				$jp_output['data'][$i]['jp_className'] = 'jp-messages '.$jp_className;
				$jp_output['data'][$i]['jp_messages'] = $jp_result->message;
				$jp_output['data'][$i]['jp_recd'] = $jp_recd;
				$jp_output['data'][$i]['jp_msg_id'] = $jp_result->id;
				$i++;
			}
			$jp_output['response'] = 'success';
		}else{
			$jp_output['response'] = 'no-data';
		}
	}else{
		$jp_output['response'] = 'error';
	}
	header('Content-Type: application/json');
	echo json_encode($jp_output);
	exit;
	wp_die();
}
add_action( 'wp_ajax_jp_fetchData', 'jp_fetchData' );

?>