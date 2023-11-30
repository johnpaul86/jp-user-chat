<?php
/**
 * @internal never define functions inside callbacks.
 * these functions could be run multiple times; this would result in a fatal error.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * custom option and settings
 */
function jpchat_settings_init() {
	// Register a new setting for "jpchat" page.
	register_setting( 'jpchat', 'jpchat_options' );

	// Register a new section in the "jpchat" page.
	add_settings_section(
		'jpchat_settings_page',
		__( 'These settings are saved in "options" table in database', 'jpchat' ), 'jpchat_settings_page_callback',
		'jpchat'
	);

	// Register a new field in the "jpchat_settings_page" section, inside the "jpchat" page.
	add_settings_field(
		'jpchat_theme', // As of WP 4.6 this value is used only internally.
		                        // Use $args' label_for to populate the id inside the callback.
			__( 'Themes', 'jpchat' ),
		'jpchat_theme_cb',
		'jpchat',
		'jpchat_settings_page',
		array(
			'label_for'         => 'jpchat_theme',
			'class'             => 'jpchat_row',
			'jpchat_custom_data' => 'custom',
		)
	);
	// Register a new field in the "jpchat_settings_page" section, inside the "jpchat" page.
	add_settings_field(
		'jpchat_restrict_users', // As of WP 4.6 this value is used only internally.
		                        // Use $args' label_for to populate the id inside the callback.
			__( 'Disable User Roles', 'jpchat' ),
		'jpchat_restrict_users_cb',
		'jpchat',
		'jpchat_settings_page',
		array(
			'label_for'         => 'jpchat_restrict_users',
			'class'             => 'jpchat_row',
			'jpchat_custom_data' => 'user-roles',
		)
	);
}

/**
 * Register our jpchat_settings_init to the admin_init action hook.
 */
add_action( 'admin_init', 'jpchat_settings_init' );

/**
 * Custom option and settings:
 *  - callback functions
 */

/**
 * Developers section callback function.
 *
 * @param array $args  The settings array, defining title, id, callback.
 */
function jpchat_settings_page_callback( $args ) {
	?>
	<p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Settings Page For JPChat plugin', 'jpchat' ); ?></p>
	<?php
}

/**
 * Pill field callbakc function.
 *
 * WordPress has magic interaction with the following keys: label_for, class.
 * - the "label_for" key value is used for the "for" attribute of the <label>.
 * - the "class" key value is used for the "class" attribute of the <tr> containing the field.
 * Note: you can add custom key value pairs to be used inside your callbacks.
 *
 * @param array $args
 */
function jpchat_theme_cb( $args ) {
	// Get the value of the setting we've registered with register_setting()
	$options = get_option( 'jpchat_options' );
	?>
	<select style="padding:10px 30px;" id="<?php echo esc_attr( $args['label_for'] ); ?>" data-custom="<?php echo esc_attr( $args['jpchat_custom_data'] ); ?>" name="jpchat_options[<?php echo esc_attr( $args['label_for'] ); ?>]">
		<option value="dark" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'dark', false ) ) : ( '' ); ?>>
			<?php esc_html_e( 'Dark Theme', 'jpchat' ); ?>
		</option>
 		<option value="light" <?php echo isset( $options[ $args['label_for'] ] ) ? ( selected( $options[ $args['label_for'] ], 'light', false ) ) : ( '' ); ?>>
			<?php esc_html_e( 'Light Theme', 'jpchat' ); ?>
		</option>
	</select>
	<p class="description">
		<?php esc_html_e( 'Dark theme consume less energy than light theme.', 'jpchat' ); ?>
	</p>
	<?php
}

function jpchat_restrict_users_cb( $args ) {
	
	// Get the value of the setting we've registered with register_setting()
	$options = get_option( 'jpchat_options' );

	global $wp_roles;
	$roles = $wp_roles->roles;
	$selected_roles = array();
	
	if(isset($options[esc_attr( $args['label_for'] )])){
		
		$selected_roles = $options[esc_attr( $args['label_for'] )];
	
	}
	
	?>
	<select name="jpchat_options[<?php echo esc_attr( $args['label_for'] ); ?>][]" id="jp-restrict-users" data-custom="<?php echo esc_attr( $args['jpchat_custom_data'] ); ?>" multiple>
	<?php
	
	$selected = '';
	
	foreach( $roles as $key => $role ){
		
		if(in_array( $role['name'], $selected_roles )){
			
			$selected = 'selected';
			
		}
		
		?>
		<option <?php echo $selected; ?>>
		<?php esc_html_e( $role['name'], 'jpchat' ); ?>
		</option>
		<?php
		
		$selected = '';
		
		}
		
	?>
	</select>';
<?php

}

/**
 * Add the top level menu page.
 */
function jpchat_options_page() {
	add_menu_page(
		'JPCHAT SETTINGS',
		'jpchat Options',
		'manage_options',
		'jpchat',
		'jpchat_options_page_html'
	);
}


/**
 * Register our jpchat_options_page to the admin_menu action hook.
 */
add_action( 'admin_menu', 'jpchat_options_page' );

/**
 * Top level menu callback function
 */
function jpchat_options_page_html() {
	// check user capabilities
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// add error/update messages

	// check if the user have submitted the settings
	// WordPress will add the "settings-updated" $_GET parameter to the url
	if ( isset( $_GET['settings-updated'] ) ) {
		// add settings saved message with the class of "updated"
		add_settings_error( 'jpchat_messages', 'jpchat_message', __( 'Settings Saved', 'jpchat' ), 'updated' );
	}

	// show error/update messages
	settings_errors( 'jpchat_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			// output security fields for the registered setting "jpchat"
			settings_fields( 'jpchat' );
			// output setting sections and their fields
			// (sections are registered for "jpchat", each field is registered to a specific section)
			do_settings_sections( 'jpchat' );
			// output save settings button
			submit_button( 'Save Settings' );
			?>
		</form>
		<?php
		global $wpdb;
		$jp_tablename = $wpdb->prefix . "jpchat";
		if($wpdb->get_var("SHOW TABLES LIKE '$jp_tablename'") == $jp_tablename) {
		?>
		<div class="extra-buttons">
			<button class="button button-primary clear_jp_chat" onclick="clear_jp_chat();">Clear Chat</button>
			<button class="button button-danger drop_jp_tables" onclick="drop_jp_tables();">Delete Jp Chat Database Tables</button>
		</div>
		<?php }else{ echo "<p>Database tables don't exists! Please deactivate and activate the plugin to re-create the tables.</p>"; }?>
	</div>
	
	<script>
		function clear_jp_chat(){
			/*Function to clear the existing chat history*/
			if (confirm("This will clear all the chat history, Would you like to proceed further?") == true) {
				jQuery.ajax({
					type : "post",
					dataType : "html",
					url : "<?php echo admin_url( 'admin-ajax.php' ); ?>",
					data : {action: "clear_jp_chat"},
					success: function(retData) {
						if(retData=='true'){
							jQuery('.clear_jp_chat').hide();
						}
					}
				});
			}
		}
		
		function drop_jp_tables(){
			/*This function will delete the custom tables created by this plugin*/
			if (confirm("This will delete the jp-chat database tables, Would you like to proceed further?") == true) {
				jQuery.ajax({
					type : "post",
					dataType : "html",
					url : "<?php echo admin_url( 'admin-ajax.php' ); ?>",
					data : {action: "drop_jp_tables"},
					success: function(retData) {
						if(retData=='true'){
							jQuery('.clear_jp_chat').hide();
							jQuery('.drop_jp_tables').hide();
							alert('Please deactivate and activate the plugin to recreate the tables.');
						}
					}
				});
			}
		}
	</script>
<?php
}

function clear_jp_chat(){
	/*function to empty both tables. This will erase the chat history*/
	global $wpdb;
	$jp_tablename = $wpdb->prefix . "jpchat";
	$result = $wpdb->query($wpdb->prepare('TRUNCATE TABLE '.$jp_tablename));
	
	$jp_tablename2 = $wpdb->prefix . "jpchat_blockings";
	$result2 = $wpdb->query($wpdb->prepare('TRUNCATE TABLE '.$jp_tablename2));
	echo json_encode($result);
	exit;
}
add_action( 'wp_ajax_clear_jp_chat', 'clear_jp_chat' );

function drop_jp_tables(){
	/*function to empty delete tables. This will erase the chat history*/
	global $wpdb;
	$jp_tablename = $wpdb->prefix . "jpchat";
	$result = $wpdb->query($wpdb->prepare('DROP TABLE '.$jp_tablename));
	
	$jp_tablename2 = $wpdb->prefix . "jpchat_blockings";
	$result2 = $wpdb->query($wpdb->prepare('DROP TABLE '.$jp_tablename2));
	echo json_encode($result);
	exit;
}
add_action( 'wp_ajax_drop_jp_tables', 'drop_jp_tables' );

?>