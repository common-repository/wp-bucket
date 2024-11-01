<?php 
	
	class WP_REST_OAuth{
	
		public $oauth_consumer_key;
		public $oauth_consumer_secret;
		
		public $oauth_token;
		public $oauth_token_secret;
		public $oauth_verifier;
		public $oauth_version = "1.0";
		
		public $oauth_callback;
		public $server;	
		
		private $http;	
		private $signature_url;	
		private $signature_header;	
		
		
		public function __construct( $config=array() ){
			$this->init( $config );
		}
		
		
		public function init( $config=array() ){
			$defaults = array(
				"oauth_consumer_key" 	=> $this->oauth_consumer_key,
				"oauth_consumer_secret" => $this->oauth_consumer_secret,
				"oauth_token" 			=> $this->oauth_token,
				"oauth_token_secret" 	=> $this->oauth_token_secret, 
				"oauth_callback" 		=> $this->oauth_callback,
				"oauth_verifier" 		=> $this->oauth_verifier,
				"oauth_version" 		=> $this->oauth_version,
				"server" 				=> $this->server,
				"request_token_url" 	=> $this->request_token_url,
				"authorize_url" 		=> $this->authorize_url,
				"access_token_url" 		=> $this->access_token_url,
			);
			
			$config = wp_parse_args( $config, $defaults );
			
			foreach( $config as $key => $value ){
				$this->$key = trim($value, " ");
			}
			
		}
		
		
		public function set_session( $key="", $value="" ){
			if( !session_id() ){
				session_start();
			}
			session_regenerate_id(true);
			if( isset( $_SESSION ) ){
				$_SESSION[$key] = $value;
				return true;
			}else{
				return false;
			}
		}
		
		
		public function get_session( $key="" ){
			if( !session_id() ){
				session_start();
			}
			
			if( isset( $_SESSION ) && array_key_exists( $key, $_SESSION ) ){
				return $_SESSION[$key];
				
			}else{
				return null;
			}
		}
		
		
		public function unset_session(){
			if( !session_id() ){
				session_start();
			}
			session_unset();
			session_destroy();
		}
		
		
		protected function OAuth_Consumer(){
			$Consumer = new stdClass();
			$Consumer->key 	  = $this->oauth_consumer_key;
			$Consumer->secret = $this->oauth_consumer_secret;
			
			return $Consumer;
		}
		
		
		protected function OAuth_Token(){
			if( !$this->oauth_token ){ return null; }
			
			$Token = new stdClass();
			$Token->key 	= $this->oauth_token;
			$Token->secret  = $this->oauth_token_secret;
			
			return $Token;
		}
		
		
		public function signature_method( $method="HMAC-SHA1" ){
		
			$method = strtoupper( $method );
			switch( $method ){
				case "PLAINTEXT" :
					return new OAuthSignatureMethod_PLAINTEXT();
					break;
				
				case "HMAC-SHA1" :
				default:
					return new OAuthSignatureMethod_HMAC_SHA1();
					break;
			}
			
			$sign_method = apply_filters("wprest_signature_method_object", $sign_method, $method );
			return $sign_method;
		}
		
		
		public function get_signature_header(){
			return $this->signature_header;
		}
		
		public function get_signature_url(){
			return $this->signature_url;
		}
		
		
		public function build_sign_request( $url='', $method='GET', $params ){
			
			$Consumer = $this->OAuth_Consumer();
			$Token = $this->OAuth_Token();
			$sign_m = apply_filters("wprest_signature_method_string", "HMAC-SHA1");
			
			$method = strtoupper( $method );
			$request = OAuthRequest::from_consumer_and_token( $Consumer, $Token, $method, $url, $params );
			$request->sign_request( $this->signature_method( $sign_m ), $Consumer, $Token );
			$this->signature_url = $request->to_url();
			$this->signature_header = $request->to_header();
			
			return $request;
		}
		
		
		public function get_http_body(){
			$http = $this->http;
			if( is_wp_error( $http ) ){ return null; }
			
			if( isset( $http["body"] ) ){
				return $http["body"];
			}
			return null;
		}
		
		
		public function get_http_headers(){
			$http = $this->http;
			if( is_wp_error( $http ) ){ return null; }
			
			if( isset( $http['headers'] ) ){
				return $http["headers"];
			}
			return null;
		}
		
		
		public function get_response_code(){
			$http = $this->http;
			if( is_wp_error( $http ) ){ return null; }
			
			if( isset( $http["response"] ) && isset( $http["response"]["code"] ) ){
				return $http["response"]["code"];
			}
			return null;
		}
		
		
		public function get_response_message(){
			$http = $this->http;
			if( is_wp_error( $http ) ){ return null; }
			
			if( isset( $http["response"] ) && isset( $http["response"]["message"] ) ){
				return $http["response"]["message"];
			}
			return null;
		}
		
		
		public function is_response_code( $code=200 ){
			if( !$this->http || is_wp_error( $this->http ) ){
				return false;
			}
			return $this->get_response_code() == $code;
		}
		
		
		public function is_status_codes(){
			return ( $this->is_response_code(200) || $this->is_response_code(201) || $this->is_response_code(204) );
		}
		
		
		public function remote_request( $http_url='', $http_args=array() ){
			
			$params = array();
			if( isset( $http_args["query_params"] ) ){
				$params = wp_parse_args( $http_args["query_params"], array() );
			}
			$params = apply_filters("wprest_oauth_query_params", $params );
			$method = isset( $http_args["method"] )? $http_args["method"] : "GET" ;
			
			if( !isset( $http_args["timeout"] ) ){
				$http_args["timeout"] = 30;
			}
			
			if( !isset( $http_args["sslverify"] ) ){
				$http_args["sslverify"] = false;
			}
			
			$OAuth_Request = $this->build_sign_request( $http_url, $method, $params );
			$http_url = apply_filters("wprest_http_url", $this->get_signature_url(), $http_url );
			
			$auth = base64_encode( str_replace("Authorization:", "", $this->get_signature_header() ) );
			$auth = array( "Authorization" => $auth );
			$headers = array();
			if( isset( $http_args["headers"] ) ){
				$headers = $http_args["headers"];
			}
			$http_args["headers"] = wp_parse_args( $headers, $auth );
			$http_request = apply_filters("wprest_http_request", $http_args );
			$this->http =  wp_remote_request( $http_url, $http_request );
			
			if( is_wp_error( $this->http ) ){
				return $this->http;
				
			}elseif( !$this->is_status_codes() ){
				$error_code = $this->get_response_code();
				$error_message = $this->get_response_message();
				$error_data = $this->get_http_body()? $this->get_http_body() : null ;
				
				$this->http = new WP_Error( $error_code, $error_message, $error_data );
				return $this->http;
			}
			
			return $this;
		}
		
		
		public function GET( $http_url, $args=array() ){
			$args["method"] = "GET";
			$http = $this->remote_request( $http_url, $args );
			
			return $http;
		}
		
		
		public function POST(  $http_url, $args=array()){
			$args["method"] = "POST";
			$http = $this->remote_request( $http_url, $args );
			
			return $http;
		}
		
		
		public function PUT( $http_url, $args=array() ){
			$args["method"] = "PUT";
			$http = $this->remote_request( $http_url, $args );
			
			return $http;
		}
		
		
		public function DELETE( $http_url, $args=array() ){
			$args["method"] = "DELETE";
			$http = $this->remote_request( $http_url, $args );
			
			return $http;
		} 
		
		
		public function reset(){
			$defaults = array(
				"oauth_consumer_key" 	=> null,
				"oauth_consumer_secret" => null,
				"oauth_token" 			=> null,
				"oauth_token_secret" 	=> null,
				"oauth_callback" 		=> null,
				"oauth_verifier" 		=> null,
				"oauth_version" 		=> null, 
				"http" 					=> null,
				"signature_url" 		=> null,
				"signature_header" 		=> null,
				"server" 				=> null, 
				"request_token_url" 	=> null, 
				"authorize_url" 		=> null, 
				"access_token_url" 		=> null, 
			);
			$this->init( $defaults );
			
		}
	}
	

?>