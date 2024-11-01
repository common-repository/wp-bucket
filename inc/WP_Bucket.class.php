<?php 
	
	class WP_Bucket extends WP_REST_OAuth{
		
		public $wp_user_id;
		
		public function __construct( $config=array() ){
			$this->config( $config );
		}
		
		/**
		* WP_Bucket Class Configuration.
		* 
		* @since 0.1.0
		* @param string | array $config. an array of parameters for configuration WP_Bucket class.
		* -- array(
		*		"server" 				=> "",
		*		"wp_user_id" 			=> "",
		*		"oauth_consumer_key" 	=> "",
		*		"oauth_consumer_secret" => "",
		*		"request_token_url" 	=> "",
		*		"authorize_url" 		=> "",
		*		"access_token_url" 		=> "",
		*		"oauth_token" 			=> "",
		*		"oauth_token_secret" 	=> "",
		*   )
		* 
		* @return string 
		*/
		public function config( $config ){
			$c_key 	  = wb_get_option("oauth_consumer_key", null );
			$c_secret = wb_get_option("oauth_consumer_secret", null);

			$this->server 				 = "https://bitbucket.org";
			$this->oauth_consumer_key 	 = $c_key;
			$this->oauth_consumer_secret = $c_secret;
			$this->request_token_url 	 = 'https://bitbucket.org/!api/1.0/oauth/request_token';
			$this->authorize_url 		 = 'https://bitbucket.org/!api/1.0/oauth/authenticate';
			$this->access_token_url 	 = 'https://bitbucket.org/!api/1.0/oauth/access_token';
			
			$_c = wp_parse_args( $config, array() );
			$wp_user_id = isset( $_c["wp_user_id"] )? $_c["wp_user_id"] : null ;
			$tokens = wp_get_tokens( $wp_user_id );
			extract( $tokens );
			
			$this->oauth_token 			 = $oauth_token;
			$this->oauth_token_secret 	 = $oauth_token_secret;
			
			parent::init( $config );
		}
		
		/**
		* Get a absolute url created for bitbucket API.
		* 
		* @since 0.1.0
		* @param string $temp_url The relative or absolute api url with or without a template :
		* -- https://bitbucket.org/api/1.0/user --> absolute url
		* -- /1.0/user 							--> relative url
		* -- /2.0/users/{my_username} 				--> relative url with tamplte 
		*
		* @param array $temps enter the url template values.
		* -- array("my_username" => "khosro")
		* @return string 
		*/
		public function get_api_url( $temp_url='', $temps=array() ){
			$ver = "1.0";
			$server = rtrim( $this->server, "/" ) ;
			$temp_url = preg_replace("|[\s]*|", "", $temp_url );
			
			$pattern = "|\{[\w]*\}|i";
			if( preg_match_all( $pattern, $temp_url, $matches ) ){
				$matches = array_shift( $matches );
				$matches = array_map(function( $m ){
					return preg_replace("|([\W]+)|i", "", $m);
				}, $matches);
				
				foreach( $matches as $match ){
					if( isset( $temps[$match] ) ){
						$temp_url = preg_replace("|\{$match\}|i", $temps[$match], $temp_url);
					}
				}
			}
			
			if( preg_match("|(https?://bitbucket\.org/!?api/[\d]\.[\d])|i", $temp_url ) ){
				return $temp_url;
				
			}elseif( preg_match("|([\d\s]+\.[\d\s]+)+|i", $temp_url, $match) ){
				$ver = $match[0];
				$temp_url = preg_replace("|/?{$ver}/?|i", "", $temp_url);
				
			}
			
			$url = $server . "/api/{$ver}/" . ltrim( $temp_url, "/" );
			return $url;
		}

		/**
		* Get a absolute url created for download repository from bitbucket.
		* 
		* @since 0.1.0
		* @param string $repo_slug the bitbucket repository slug.
		* @param string $owner the bitbucket account owner.
		* @param string $revision A SHA1 value for the commit.
		*	You can also specify a branch name, a bookmark, or tag.
		*	If you do Bitbucket responds with the commit that the revision points.
		*	For example, if you supply a branch name this returns the branch tip (or head).
		* @return string 
		*/
		public function get_download_url( $repo_slug=null, $owner=null, $revision=null ){
			if( !$repo_slug ){ return null; }
			
			if( !$owner ){
				$user = wb_get_user();
				if( is_wp_error( $user ) ){
					return $user;
				}
				$owner = $user->user->username ;
			}
			$revision = ( !$revision )? "master" : $revision ;
			
			return rtrim( $this->server, "/" ) . "/{$owner}/{$repo_slug}/get/{$revision}.zip";
		}
		
		/**
		* Get a repository compressed file from bitbucket.
		* 
		* @since 0.1.0
		* @param string $repo_slug the bitbucket repository slug.
		* @param string $owner the bitbucket account owner.
		* @param string $revision A SHA1 value for the commit.
		*	You can also specify a branch name, a bookmark, or tag.
		*	If you do Bitbucket responds with the commit that the revision points.
		*	For example, if you supply a branch name this returns the branch tip (or head).
		* @return zip file 
		*/
		public function download_stream( $repo_slug=null, $owner="", $revision=null ){
			if( !$repo_slug ){ return null; }
			$url = $this->get_download_url( $repo_slug, $owner , $revision );
			
			if( is_wp_error( $url ) ){
				$this->wb_die( $url, false );
			}
			
			$file = $this->GET( $url );
			if( is_wp_error( $file ) ){
				$this->wb_die( $file, false );
			}
			
			$default_headers = array(
				'date' 	=> "" ,'content-type' => "" ,'content-disposition' => "", 'last-modified' => "",
				'cache-control' => "",'expires' => "",'etag' => "",'accept-ranges' => "", 'connection' => "",
			);
			
			$headers = $file->get_http_headers();
			foreach( $headers as $h => $value ){
				if( isset( $default_headers[$h] ) ){
					header("{$h}: {$value}");
				}
			}
			
			echo $file->get_http_body();
			exit;
		}
		
		/**
		*  This method is the most useful method of `WP_Bucket` class, the main usability of this method is for making REST request to the bitbucket website. 
		* return value of this method can be include one of `JSON, XML, YAML, WP_Error` outputs. at default if there"s no error, return value will be a `JSON` string.
		* 
		* @since 0.1.0
		* @param string $url The relative or absolute api url with or without a template :
		* -- https://bitbucket.org/api/1.0/user --> absolute url
		* -- /1.0/user 							--> relative url
		* -- /2.0/users/{username} 				--> relative url with tamplte 
		*
		* @param string $method The RESTful Methods ( GET, POST, PUT, DELETE )
		* @param string $args An array of arguments:
		*  array(
		*  		"template"		=>	array("username"=>"khosroblog"), // an array of template values for api url --> /2.0/users/{username} username is a template
		*  		"body"			=>	array(), // a string json or an array of parameters for the PUT and POST requests.
		*  		"query_params"	=>	array('format'=>'xml'), // an array of parameters for add to signature request.
		*  )
		* @return String JSON|XML|YAML | Object WP_Error 
		*/
		public function api( $url="", $method="GET", $args=array() ){
			$defaults = array(
				"template"		=>	array(), 
				"query_params"	=>	array(), 
				"body"			=>	array(),
			); 
			$all_args = array();
			foreach( $defaults as $d => $value ){
				$all_args[$d] = array_key_exists($d, $args)? wp_parse_args( $args[$d],$defaults[$d]) : "" ;
			}
			
			$is_json = false;
			if( isset( $args["body"] ) ) {
				if( wb_is_json( $args["body"] ) ){
					$is_json = true;
					$b = array_keys( $all_args["body"] );
					$all_args["body"] = array_shift( $b );
				}
			}
			extract( $all_args ); 
			
			$url = $this->get_api_url( $url, $template );
			$is_ver = preg_match("|([\d\s]+\.[\d\s]+)+|i", $url, $m);
			$ver_1 = ( $m && (int) $m[0] === 1 )? true : false ;
			$charset = get_option("blog_charset");
			
			$content_type = "";
			if( $is_json ){
				$content_type = "application/json; charset={$charset};";
			}elseif( is_array( $body ) ){
				if( $ver_1 ){
					$content_type = "application/x-www-url-form-encoded; charset={$charset};";
				}else{
					$content_type = "application/x-www-form-urlencoded; charset={$charset};";
				}
			} 
			
			switch( strtoupper( $method ) ){
				# GET
				case "GET" : 
					$result = $this->GET( $url, array(
						"query_params"	=>	$query_params
					) );
					break; 
				# POST
				case "POST" : 
					$result = $this->POST( $url, array(
						"headers"		=>	array("Content-Type" =>	$content_type ), 
						"body"			=>	$body ,
						"query_params"	=>	$query_params
					) ); 
					break; 
				# PUT
				case "PUT" : 
					$result = $this->PUT( $url, array(
						"headers"		=>	array("Content-Type" =>	$content_type ), 
						"body"			=>	$body ,
						"query_params"	=>	$query_params
					) );
					break; 
				# DELETE
				case "DELETE" : 
					$result = $this->DELETE( $url, array(
						"query_params"	=>	$query_params
					) );
					break;
				# GET
				default: 
					$result = $this->GET( $url, array(
						"query_params"	=>	$query_params
					) );
					break;
			}
			
			if( is_wp_error( $result ) ){
				return $result;
			}else{
				return $result->get_http_body();
			}
		}
		
		/**
		* Get request tokens from bitbucket.
		* 
		* @since 0.1.0
		* @return an array of request tokens
		*/
		public function get_request_token(){
			
			$page_name = "login-by-bitbucket";
			$login_page = get_page_by_path( $page_name );
			$page_url = get_permalink( $login_page->ID );
			$nonce = wp_create_nonce("_wb_oauth_{$login_page->ID}");
			
			$this->oauth_callback = add_query_arg(array(
				"action"	=>	"access_token-{$nonce}"
			), $page_url); 
			
			$url = $this->request_token_url;
			$this->oauth_token 		  = null;
			$this->oauth_token_secret = null;

			$result = $this->GET( $url, array(
				"query_params"	=>	"oauth_callback={$this->oauth_callback}",
			));
			
			if( is_wp_error( $result ) ){
				return $result;
			}
			
			$data = $result->get_http_body();
			@parse_str( $data, $tokens );
			$parameters = array(
				"oauth_token"		 =>	@$tokens["oauth_token"],
				"oauth_token_secret" =>	@$tokens["oauth_token_secret"]
			);
			
			return $parameters;
		}
		
		/**
		* Redirect user to bitbucket for authenticate.
		* 
		* @since 0.1.0
		*/
		public function authenticate(){
			$oauth_token = $this->oauth_token;
			if( ! $oauth_token ){
				return ;
			}
			
			$url = $this->authorize_url;
			$url = add_query_arg("oauth_token", $oauth_token, $url);
			
			wp_redirect( $url );
			exit;
		}
		
		/**
		* Get access tokens from bitbucket  
		* 
		* @since 0.1.0
		* @return an array of access tokens
		*/
		public function get_access_token(){
			$oauth_token = $this->oauth_token;
			$oauth_verifier = $this->oauth_verifier;
			
			$url = $this->access_token_url;
			$result = $this->GET( $url, array(
				"query_params"	=>	"oauth_verifier={$oauth_verifier}"
			) );
			
			if( is_wp_error( $result ) ){
				return $result;
			}
			
			$data = $result->get_http_body();
			@parse_str( $data, $tokens );
			$parameters = array(
				"oauth_token"		 =>	@$tokens["oauth_token"],
				"oauth_token_secret" =>	@$tokens["oauth_token_secret"]
			);
			
			return $parameters;
		}
		
		/**
		* Kill WordPress execution and display HTML message with error message.  
		* 
		* @since 0.1.0
		* @param string $error_obj The WP_Error object
		* @param string $button The button for show in html message.
		*/
		public function wb_die( $error_obj=null, $button="" ){
			if( !$error_obj && is_wp_error( $this->http ) ){
				$error_obj = $this->http;
			}
			
			if( is_wp_error( $error_obj ) ){
				$error_code = esc_html( $error_obj->get_error_code() );
				$error_message = esc_html( $error_obj->get_error_message() );
				$error_data = esc_html( $error_obj->get_error_data() );
				
				$message  = "<div class='error-message' >";
				$message .= "<strong class='error-code' >{$error_code}</strong> : {$error_message}<br /><br />";
				
				if( $button ){
					$message .= $button;
				}
				$message .= "</div>";
				$message .=  "<pre class='error-data' >" . print_r( $error_data, true ) . "</pre>";
				
				do_action("wb_die", $error_code, $error_obj );
				
				wp_die( $message , $error_message, array(
					"response" 	=>	$error_code,
					"back_link" =>	true
				));	
			}
			
		}
		
	}
	
	$WP_Bucket = new WP_Bucket();
	
	/**************************************************************************************************
	* User Authenticate and get wordpress user_id.
	* 
	* @since 0.1.0
	* @param string $oauth_token The authentication token key received from bitbucket.
	* @param string $oauth_token_secret The authentication token secret key received from bitbucket.
	* @return wordpress user id | Object WP_Error .
	*/
	function wb_user_authenticate( $oauth_token=null, $oauth_token_secret=null ){
		global $WP_Bucket;
		
		$WP_Bucket->config(array(
			"oauth_token"			=>	$oauth_token,
			"oauth_token_secret"	=>	$oauth_token_secret
		));
		# 1. if current user logged in
		if( is_user_logged_in() ){
			
			return get_current_user_id();
		}
		
		# 2. if tokens exits in wp_usermeta table
		$user = wp_check_user_by_tokens( $oauth_token, $oauth_token_secret );
		if( $user && (int) $user->ID ){
			
			return $user->ID;
		}
		
		$bbt_user = wb_get_user_info();
		if( is_wp_error( $bbt_user ) ){
			
			return $bbt_user;
		}
		
		# 3. if user email exits.
		if( $user_id = email_exists( $bbt_user->user_email ) ){

			return $user_id;
		}
		
		# 4. create an user .
		return wp_insert_user(array(
					'user_login'	=>	$bbt_user->username,	
					'user_pass'		=>	$oauth_token,
					'display_name' 	=>	$bbt_user->display_name,
					'user_email' 	=>	$bbt_user->user_email,
					'role'			=>	get_option('default_role')
				));
		
	}
	
	/**************************************************************************************************
	* Whether the current string is json string.  
	* 
	* @since 0.1.0
	* @param String $string The json string
	* @return Bool True|False .
	*/
	function wb_is_json( $string=null ){
		if( !$string || !is_string( $string ) ){ return false; }
		$js = json_decode( $string );
		
		if( is_object( $js ) || is_array( $js ) ){ 
			return true; 
		}else{
			return false;
		}
	}
	
	/***************************************************************************************************
	* Get tokens from the table wp_usermeta by wordpress user id. 
	* 
	* @since 0.1.0
	* @param string $wp_user_id The wordpress user id.
	* @return array("oauth_token", "oauth_token_secret")
	*/
	function wp_get_tokens( $wp_user_id=null ){
		$wp_user_id = ( $wp_user_id )? (int) $wp_user_id : get_current_user_id();
		if( !$wp_user_id ){
			return array(
				"oauth_token"			=>	"unauthorized",
				"oauth_token_secret"	=>	"unauthorized",
			);
		}
		$oauth_token = ( $oat = get_user_meta( $wp_user_id, "oauth_token", true ) )? $oat : "unauthorized";
		$oauth_token_secret = ( $oast = get_user_meta( $wp_user_id, "oauth_token_secret", true ) )? $oast : "unauthorized";
		
		return array(
			"oauth_token"			=>	$oauth_token,
			"oauth_token_secret"	=>	$oauth_token_secret,
		);
	}
	
	/***************************************************************************************************
	*  Checks the table wp_users for recieve user to existing tokens. 
	* 
	* @since 0.1.0
	* @param string $oauth_token The authentication token key received from bitbucket.
	* @param string $oauth_token_secret The authentication token secret key received from bitbucket.
	* @return WP_User|False.
	*/
	function wp_check_user_by_tokens( $oauth_token=null, $oauth_token_secret=null ) {
		global $WP_Bucket;
		
		if( $WP_Bucket->oauth_token && !$oauth_token ){
			$oauth_token = $WP_Bucket->oauth_token ;
		}
		
		if( $WP_Bucket->oauth_token_secret && !$oauth_token_secret ){
			$oauth_token_secret = $WP_Bucket->oauth_token_secret ;
		}
		if( !$oauth_token || !$oauth_token_secret ){ return false; }
		
		$users = get_users(
			array(
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' 	=> 'oauth_token',
						'value' => $oauth_token,
					),
					array(
						'key' 	=> 'oauth_token_secret',
						'value' => $oauth_token_secret,
					)
				)
			)
		);
		if( !$users ){ return false; }
		
		return array_shift( $users );
	}
	
	/***************************************************************************************************
	* Get bitbucket user . 
	* 
	* @since 0.1.0
	* @param string $username The bitbucket username
	* @return Object|WP_Error.
	*/
	function wb_get_user( $username="" ){
		global $WP_Bucket;
		
		$url = "/1.0/user";
		if( $username ){
			$url = "/2.0/users/{$username}/";
		}
		
		$result = $WP_Bucket->api( $url );
		if( is_wp_error( $result ) ){
			return $result;
		}else{
			return json_decode( $result );
		}
	}
	
	/***************************************************************************************************
	* Get bitbucket user emails by username : 
	* 
	* @since 0.1.0
	* @param string $username The bitbucket username
	* @return Object|WP_Error.
	*/
	function wb_get_user_emails( $username='' ){
		global $WP_Bucket;
		
		$result = $WP_Bucket->api("/1.0/users/{$username}/emails");
		if( is_wp_error( $result ) ){
			return $result;
		}else{
			return json_decode( $result );
		}
	}
	
	/***************************************************************************************************
	* Get bitbucket user info : 
	* --[username] 
	* --[website] // if $username not empty
	* --[display_name] 
	* --[user_email] 
	* 
	* @since 0.1.0
	* @param string $username The bitbucket username
	* @return Object|WP_Error.
	*/
	function wb_get_user_info( $username="" ){
		$user_object = wb_get_user( $username );
		if( is_wp_error( $user_object ) ){
			return $user_object;
		}
		
		$bbt_user = ( isset( $user_object->user ) )? $user_object->user : $user_object ;
		$user = new stdClass();
		$user->username 	= $bbt_user->username;
		$user->website  	= isset( $bbt_user->website )? $bbt_user->website : "";
		$user->display_name = isset( $bbt_user->display_name )? $bbt_user->display_name : "";
		$user->avatar 		= $bbt_user->avatar ;
		$user->is_team 		= $bbt_user->is_team ;
		
		$user_emails = wb_get_user_emails( $user->username );
		if( is_wp_error( $user_emails ) ){
			return $user_emails ;
		}
		
		foreach( $user_emails as $user_email ){
			if( $user_email->active && $user_email->primary ){
				$user->user_email = $user_email->email;
			}
		}
		
		return $user;
	}
	
	/***************************************************************************************************
	* Create a login by bitbucket button.
	* 
	* @since 0.1.0
	* @param array $args An array of arguments
	* @return html login button.
	*/
	function wb_login_button( $args=array() ){
		$defaults = array(
			"id"	=>	"wb_login_button",
			"class"	=>	"wb-login",
			"title"	=>	__("Login by Bitbucket", "wp-bucket"),
			"echo"	=>	1,
		);
		$args = wp_parse_args( $args, $defaults );
		extract( $args );
		
		$page_name = "login-by-bitbucket";
		$login_by_bbt = get_page_by_path( $page_name );
		$nonce = wp_create_nonce("_wb_oauth_{$login_by_bbt->ID}");
		$login_page_url = add_query_arg(array(
			"action"	=>	"authenticate-{$nonce}",
		), get_permalink( $login_by_bbt->ID ) );
		
		$string = "\n<!-- WB Bucket Login Button -->\n";
		$string .= '<a href="%1$s" class="%2$s" id="%3$s" >%4$s</a>';
		$string .= "\n<!-- /WB Bucket Login Button -->\n";
		$output = sprintf( $string, 
					$login_page_url, esc_attr( $class ), esc_attr( $id ), esc_html( $title )
		); 
		
		if( $echo ){
			echo $output; return;
		}
		return $output;
	}
	
	/***************************************************************************************************
	* Get all bitbucket repository commits 
	* 
	* @since 0.1.0
	* @param string $owner The bitbucket account name
	* @param string $repo_slug The bitbucket repository name
	* @param string $query The variables query like "pagelen", "page", "limit", "format", ...
	* @return Object | WP_Error | String( xml | yaml | json ) .   
	*/
	function wb_get_repo_commits( $repo_slug=null, $owner=null, $query=array() ){
		global $WP_Bucket;
		
		if( !$repo_slug ){ return; }
		if( !$owner ){
			$user = wb_get_user();
			if( is_wp_error( $user ) ){ return $user; }
			$owner = $user->user->username;
		}
		$url = "2.0/repositories/{$owner}/{$repo_slug}/commits";
		$result = $WP_Bucket->api($url, "GET", array("query_params" => $query) );
		
		if( is_wp_error( $result ) ){
			return $result;
		}else{
			if( isset( $query["format"] ) ){
				return $result;
			}
			return json_decode( $result );
		}
	}
	
	
	
?>