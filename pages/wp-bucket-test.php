<?php 
	global $WP_Bucket;
	
	// Examples
	
	############# get current user info ##################
	$response = wb_get_user_info(); 
	
	
	############## without user authenticate ###################
	/* $WP_Bucket->config(array(
		"oauth_token"		 =>	null,
		"oauth_token_secret" =>	null
	));
	$response = wb_get_user("evzijst");
	# or 
	$response = $WP_Bucket->api("2.0/users/evzijst"); */
	
	
	################# get current user and user repositories #################
	//$response = wb_get_user(); // current user
	
	
	################# Create a repository #################
	/* $response = $WP_Bucket->api("2.0/repositories/your_accountname/repo_slug", "POST", array(
		"body" => json_encode(array(
			"description"	=>	"description for repo_slug repository.",
			"is_private"	=>	1,
			"language"		=>	"php"
		))
	)); */
	
	
	################# Update a exists repository #################
	/* $response = $WP_Bucket->api("1.0/repositories/your_accountname/repo_slug", "PUT", array(
		"body" => json_encode(array(
			"language"		=>	"javascript"
		))
	)); */
	
	
	################# Get a exists repository #################
	//$response = $WP_Bucket->api("2.0/repositories/your_accountname/repo_slug");
	
	
	################# Delete a exists repository #################
	//$response = $WP_Bucket->api("2.0/repositories/your_accountname/repo_slug", "DELETE");
	
	
	################# Get all repositories of an user #################
	//$response = $WP_Bucket->api("2.0/repositories/your_accountname/");
	
	
	################# Get 25 public repositories of all bitbucket users --- last page #################
	/* $response = $WP_Bucket->api("2.0/repositories/", "GET", array(
		"query_params" => array(
			"pagelen"	=>	25
		)
	)); */
	
	
	################# Get 25 public repositories of all bitbucket users --- second page | after 2012 #################
	/* $response = $WP_Bucket->api("2.0/repositories/", "GET", array(
		"query_params" => array(
			"pagelen"	=>	25, 
			"page"		=>	2, 
			"after"		=>	"2012"
		)
	));  */
	
?>
<!DOCTYPE HTML>
<html>
<head>
	<title><?php the_title(); ?></title>
	<meta charset="UTF-8" />
	<link rel="stylesheet" type="text/css" href="<?php echo WPBUCKET_URL; ?>pages/css/structure.css">
</head>

<body <?php body_class(); ?> >
		
	<?php 
		// WP Bucket Login Button
		wb_login_button();
	?>
	
	<!-- WP Bucket Test -->
	<div class="box test-oauth">
		<h3 class="line" ><?php the_title(); ?></h3>
		
		<?php if( is_wp_error( $response ) ){ ?>
				<div class="error alert">
					<h4 style="color:white;text-shadow:0 0 0;" class="message title">
						HTTP/1.1
						<?php echo  esc_html( $response->get_error_code() );?>
						<?php echo  esc_html( $response->get_error_message() );?>.
					</h4>
				</div>
				<div style="margin:20px" class="data message">
					<?php 
						$data = $response->get_error_data(); 
						$error_data = json_decode( $data, true );
						if( isset( $error_data["error"] ) ){
							echo "<h4 style='padding-left:0' >" . esc_html( $error_data['error']['message'] ) . "</h4>";
							echo "<code>" . esc_html( $error_data['error']['detail'] ) . "</code>";
						}else{
							echo "<code>" . esc_html( $data ) . "</code>";
						}
						
					?>
				</div>
			<?php
			}else{
				?>
				<div class="success alert">
					<h4 style="color:white;text-shadow:0 0 0;" class="message title">
						HTTP/1.1
						<?php echo  esc_html( $WP_Bucket->get_response_code() );?>
						<?php echo  esc_html( $WP_Bucket->get_response_message() );?>.
					</h4>
				</div>
				
				<pre class='xdebug-var-dump' >
				<?php 
				$response = wb_is_json( $response )? json_decode( $response ) : $response ;
				$res = print_r( $response , true ) ;
				echo esc_html( $res );
				?>
				</pre>
				
	<?php   } ?>
		
	</div>
	<!-- /WP Bucket Test -->
	
</body>
</html>
