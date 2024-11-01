<?php 
	global $WP_Bucket;
	
	$page_name = "login-by-bitbucket";
	$login_by_bbt = get_page_by_path( $page_name );
	
	$nonce_action = "_wb_oauth_" . $login_by_bbt->ID ;
	$action = isset( $_GET["action"] )? $_GET["action"] : null ;
	
	$action = @explode("-", $action);
	$action_name = isset( $action[0] )? $action[0] : "" ;
	$action_nonce = isset( $action[1] )? $action[1] : "" ;
	$try_again = wb_login_button("class=wb_button button button-large&echo=0&hide=0&title=".__("Try Again", "wp-bucket") );
	
	# action=authenticate
	if( $action_name == "authenticate" ):
		
		if( !wp_verify_nonce( $action_nonce, $nonce_action ) ){
		
			$error = new WP_Error("cookies_expired", __("Cookies expired. Please try again.", "wp-bucket") );
			$WP_Bucket->wb_die( $error, $try_again );
		}
		
		$tokens = $WP_Bucket->get_request_token();
		if( is_wp_error( $tokens ) ){
			$WP_Bucket->wb_die( $tokens, $try_again );
		}
		@extract( $tokens );
		
		$WP_Bucket->set_session("oauth_token", $oauth_token );
		$WP_Bucket->set_session("oauth_token_secret", $oauth_token_secret );
		
		$WP_Bucket->config( $tokens );
		
		$WP_Bucket->authenticate();
	
	# action=access_token
	elseif( $action_name == "access_token" ):
		
		if( !wp_verify_nonce( $action_nonce, $nonce_action ) ){
			$error = new WP_Error("cookies_expired", __("Cookies expired. Please try again.", "wp-bucket") );
			$WP_Bucket->wb_die( $error, $try_again );
		}
		
		$WP_Bucket->config( array(
			"oauth_verifier" 	 => isset( $_GET["oauth_verifier"] )? $_GET["oauth_verifier"] : NULL ,
			"oauth_token" 		 => $WP_Bucket->get_session("oauth_token"),
			"oauth_token_secret" => $WP_Bucket->get_session("oauth_token_secret"),
		) );
		$WP_Bucket->unset_session();
		
		$tokens = $WP_Bucket->get_access_token();
		if( is_wp_error( $tokens ) ){
			$WP_Bucket->wb_die( $error, $try_again );
		}
		extract( $tokens );
		
		$user_id = wb_user_authenticate( $oauth_token, $oauth_token_secret );
		if( is_wp_error( $user_id ) ){
			$WP_Bucket->wb_die( $tokens, $try_again );
		}
		
		wp_clear_auth_cookie();
		wp_set_auth_cookie( $user_id );
		$user = get_userdata( $user_id )->data;
		
		if( wp_check_password( $oauth_token, $user->user_pass , $user_id ) ){
			update_user_option( $user_id, 'default_password_nag', true, true );
		}
		update_user_meta( $user_id, "oauth_token", $oauth_token );
		update_user_meta( $user_id, "oauth_token_secret", $oauth_token_secret );
		
		wp_redirect( admin_url() );
		exit;
	
	else:
		$referer = home_url();
		if( is_user_logged_in() ){
			$referer = admin_url();
		}
		wp_redirect( $referer );
		
	# /if( $action = [authenticate|access_token] )
	endif;

?>