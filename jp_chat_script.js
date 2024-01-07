jQuery(document).ready( function() {
	
	/* On page load, we are relocating the create chat button to the bottom left.*/
	var jpEle = jQuery('.jp-open-chat-link').detach();
	
	jQuery('.jp-container').prepend(jpEle);
	
	jp_message_counter();
	
	setInterval(function() { jp_message_counter()}, 5000 );
	
});

function jp_text_typed(ths, jpEvent){
	
	var code = (jpEvent.keyCode ? jpEvent.keyCode : jpEvent.which);
	
	if (code == 13) {
		
		jQuery(ths).parent().parent().find('.jp_send_btn').trigger('click');
		
		return true;
		
	}
	
}

function jp_close_chat_window(userId){
	
	jQuery(".jp-chat-box-user-"+userId).remove();
	
	ob.splice(ob.indexOf(userId),1);
	
}

function jp_send_report(ths){
	
	var userId = jQuery(ths).parent().parent().attr('data-jp-chat-user-id');
	var reportText = jQuery(ths).parent().find('.jp-block-report').val();
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	
	jQuery.ajax({
		type : "post",
		dataType : "json",
		url : jpChat.ajaxurl,
		data : {action: "jp_report_user", block_user_id : userId, report_text : reportText, nonce: nonce},
		success: function(retData) {
			
			if(retData.response == 'success'){
				
				jQuery(ths).parent().slideUp();
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-alerts').delay(3000).slideUp('slow');
				
			}
			
		}
	});
	
}

function jp_block_user(userId){
	
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	
	jQuery.ajax({
		type : "post",
		dataType : "json",
		url : jpChat.ajaxurl,
		data : {action: "jp_block_user", block_user_id : userId, nonce: nonce},
		success: function(retData) {
			
			if(retData.message == 'blocked'){
				
				jQuery('.jp-chat-box-user-'+userId).addClass('jp-user-blocked');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-header .jp-block-btn').attr('title','Unblock this user!');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-alerts').html('<p class="success jp-blocked-active">User blocked!</p>');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-alerts').slideDown('slow');
				
				var tmp = '<div class="jp-report-box"><input type="text" class="jp-block-report" placeholder="Report User" maxlength="254"/><input type="button" value="ok" onClick="jp_send_report(this);"/></div>';
				
				jQuery(tmp).insertAfter('.jp-chat-box-user-'+userId+' .jp-chat-header');
			
			}else{
				
				jQuery('.jp-chat-box-user-'+userId).removeClass('jp-user-blocked');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-header .jp-block-btn').attr('title','Block this user!');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-alerts').html('<p class="success jp-blocked-inactive">User Un-blocked!');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-alerts').slideDown('slow');
				
				jQuery('.jp-chat-box-user-'+userId+' .jp-chat-alerts').delay(3000).slideUp('slow');
			
			}
			
		}
	});
	
}

function jp_display_message_list(){
	
	jQuery('.jp-msg-list-box').slideToggle();
	
}

function jp_message_counter(){
	
	/*this function fetches the new messages bubbles*/
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	var jp_total_msgs = 0;
	
	jQuery.ajax({
		type : "post",
		dataType : "json",
		url : jpChat.ajaxurl,
		data : {action: "jp_message_counter", nonce: nonce},
		success: function(response) {
			
			jQuery('.jp-msg-list-box .jp-messenger .jp-message-counter').css('display','none');
			
			if(response.new_messages == 'yes'){
				
				jQuery.map(response.results, function(n,i){
					
					jQuery('[data-jp-messenger-uid="'+n.sender_id+'"]').find('.jp-message-counter').html(n.msgs);
					jQuery('[data-jp-messenger-uid="'+n.sender_id+'"]').find('.jp-message-counter').css('display','block');
					jQuery('[data-jp-messenger-uid="'+n.sender_id+'"]').attr('data-jp-has-new-msgs','yes');
					jp_total_msgs += parseInt(n.msgs);
					
				});
				
				if( jQuery('[data-jp-has-new-msgs="yes"]').length != response.results.length ){
					
					jp_update_sender_list();
					
				}
				
			}
			if( jp_total_msgs>0 ){
				
				jQuery('.jp-message-bubble .jp-message-counter').html(jp_total_msgs);
				
				jQuery('.jp-message-bubble .jp-message-counter').css('display','block');
				
			}else{
				
				jQuery('.jp-message-bubble .jp-message-counter').html('');
				
				jQuery('.jp-message-bubble .jp-message-counter').css('display','none');
				
			}
			
		}
		
	});
	
}

function jp_update_sender_list(){
	
	/*function to update the sender list if a new sender sends a message*/
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	
	jQuery.ajax({
		type : "post",
		dataType : "html",
		url : jpChat.ajaxurl,
		data : {action: "jp_update_sender_list", nonce : nonce},
		success: function(response) {
			
			jQuery('.jp-msg-list-box').html(response);
			
			jp_message_counter();
			
		}
	});
	
}
function jp_update_msgStatus(jp_mid, sender_id){
	
	/*Function to update the read receipt*/
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	
	if(ob.includes(parseInt(sender_id)) === true){
		
		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : jpChat.ajaxurl,
			data : {action: "jp_update_msgStatus", jp_mid : jp_mid, sender_id : sender_id, nonce : nonce},
			success: function(response) {
				
			}
		});
		
	}
	
}
/*ob as a global javascript variable which is an array of chat windows*/
var ob = [];
function jp_open_message_box( jp_send_to ){
	
	if(ob.includes(jp_send_to) === true){
		return false;
	}
	
	var jpChat_box = '';
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	jQuery('.jp-msg-list-box').slideUp();
	jQuery('.jp-chat-loader').css('display','block');
	
	/*We are restricting chat windows to a max 3 windows*/
	if(ob.length == 3){
		
		jp_close_chat_window(ob[0]);
	
	}
	
	jQuery.ajax({
		type : "post",
		dataType : "html",
		url : jpChat.ajaxurl,
		data : {action: "jp_read_messages", jp_send_to : jp_send_to, nonce: nonce},
		success: function(response) {
			
			jp_fetchData( jp_send_to, 'initial' );
			
			jpChat_box = response;
			
			jQuery('#jp-msg-box-container').prepend(jpChat_box);
			
			jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-chat-messages').animate({
			   scrollTop: jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-message-container').height()
			}, 0);
			
			ob.push(jp_send_to);
			
			jQuery('.jp-chat-loader').slideUp();
			
			jp_message_counter();
			
		}
		
	});
	
	setInterval(function() { jp_fetchData( jp_send_to, 'interval' )}, 5000 );
	
}
function jp_fetchData( jp_send_to, triggerOn ){
	
	/* This is the function to fetch the chat data from the database*/
	var jpChat_box = '';
	var jp_chatData = '';
	var jp_dateString = '';
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	
	jQuery.ajax({
		type : "post",
		dataType : "json",
		url : jpChat.ajaxurl,
		data : { action : "jp_fetchData", jp_send_to : jp_send_to, triggerOn : triggerOn, nonce : nonce },
		success: function(retData) {
			
			if( retData.response == 'success' ){
				
				var jp_msg_id = '';
				
				jQuery.map(retData.data, function(n,i){
					
					if( n.jp_msgdAt != '' ){
					
						jp_dateString = '<i class="jp-sentAt">'+n.jp_msgdAt+'</i>';
					
					}
					
					jp_msg_id = n.jp_msg_id;
					
					jp_chatData += '<p class="'+n.jp_className+'" data-msg-recd="'+n.jp_recd+'" data-msg-id="'+n.jp_msg_id+'"><span title="'+n.jp_msgdAt+'">'+n.jp_messages+'</span>'+jp_dateString+'</p>';
					
					if(n.jp_recd == 'no-read'){
						
						/*New message detected and sends to update the read receipt*/
						jp_update_msgStatus( jp_msg_id, jp_send_to );
						
					}
					
				});
				
				if( triggerOn == 'initial' ){
					
					jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-chat-messages .jp-message-container').html(jp_chatData);
				
				}else{
					
					jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-chat-messages .jp-message-container').append(jp_chatData);
				
				}
				
				jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-chat-messages').animate({
					scrollTop: jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-message-container').height()
				}, 0);
				
			}
			
		}
		
	});
	
}

function jp_send_btn_action(ths){
	
	/* function to send new message*/
	var nonce = jQuery('.jp-msg-list-box').attr('data-nonce');
	var jp_send_to = jQuery(ths).parent().parent().attr('data-jp-chat-user-id');
	var jp_msg = jQuery(ths).parent().parent().find('.jp-textarea').val();
	var jp_dateString = '';
	
	if(!jQuery.trim(jp_msg)) {
		
		return false;
		
	}
	
	jQuery.ajax({
		type : "post",
		dataType : "json",
		url : jpChat.ajaxurl,
		data : {action: "jp_send_message", jp_send_to : jp_send_to, jp_msg : jp_msg, nonce: nonce},
		success: function(response) {
			
			if(response.response == 'success'){
				
				if( response.jp_msgDate != '' ){
					
					jp_dateString = '<i class="jp-sentAt">'+response.jp_msgDate+'</i>';
					
				}
				
				jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-chat-messages .jp-message-container').append('<p class="jp-messages jp-sender"><span>'+jp_msg+'</span>'+jp_dateString+'</p>');
				
				jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-chat-messages').animate({
				   scrollTop: jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-message-container').height()
				}, 0);
				
				jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-textarea').val('');
				
				jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-textarea').removeClass('shadow-box');
				
			}else{
				
				jQuery('.jp-chat-box-user-'+jp_send_to+' .jp-textarea').addClass('shadow-box');
				
			}
			
		}
	});
}

function jp_display_when_selector( ths, uid ){
	
	if (jQuery(ths).is(":checked")) {		
	
		jQuery('.jp-chat-box-user-'+uid+' .jp-chat-messages .jp-messages .jp-sentAt').css('display','block');
		
	} else {
		
		jQuery('.jp-chat-box-user-'+uid+' .jp-chat-messages .jp-messages .jp-sentAt').css('display','none');
		
	}
	
}