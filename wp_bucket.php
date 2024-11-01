<?php 
/*
	Plugin Name: WP Bucket
	Plugin URI: https://bitbucket.org/khosroblog/wp_bucket
	Description: A lightweight wordpress plugin that helps you to login to your site by using a "Login by BitBucket" system and it enables developers to run most of server side ability in plugin or theme by using of BitBucket API, This includes access to all features of the version 1 and version 2 Bitbucket API .
	Version: 0.1.2
	Author: Hadi Khosrojerdi
	Author URI: http://khosroblog.com
	License: GNU General Public License v2 or later 
*/
/* 
	Special thanks to Mohammad Reza Golestan for fluent persian to english translation 
	Translator URI : https://www.facebook.com/mreza.golestan
*/

	function wb_get_option( $value="", $default="" ){
		$option = get_option("wb_settings");
		
		if( $value && isset( $option[$value] ) ){
			return $option[$value];
			
		}else{
			return $default;
		}
	}
	
	
	class WP_Bucket_Plugin {
		
		public function __construct(){
			register_activation_hook( __FILE__, array( $this, 'install' ) );
			register_deactivation_hook( __FILE__, array( $this, 'uninstall' ) );
			$this->load();
		}
		
		
		public function load(){
		
			# Constants
			add_action("plugins_loaded", array($this, "define_constants"));
			
			# Classes
			add_action("plugins_loaded", array($this, "load_classes"));
			
			# Localization
			add_action("plugins_loaded", array($this, "localization"));
			
			# Pages
			add_action("page_template", array($this, "load_pages"));
			
			# Settings
			add_action("admin_init", array($this, "register_settings"));
			add_action("sanitize_option_wb_settings", array($this, "sanitize_settings"));
			add_action("pre_update_option_wb_settings", array($this, "pre_update_settings"));
			
		}
		
		
		public function install(){
			# Add Pages 
			$this->add_pages();
		}
		
		
		public function uninstall(){
			# Remove Pages 
			$this->remove_pages();
		}
		
		public function add_pages(){
			$pages = array("Login By Bitbucket", "WP Bucket Test");
			$args = array(
				"post_type"		=>	"page",
				"post_status"	=>	"publish",
				"ping_status"	=>	"closed",
				"comment_status" =>	"closed"
			);
			
			foreach( $pages as $page ){
				$post_title = sanitize_title( $page );
				if( !get_page_by_title( $post_title, 'OBJECT', 'page' ) ){
					$args["post_name"] = $post_title ;
					$args["post_title"] = $page ;
					wp_insert_post( $args );
				}
			}
		}
		
		
		public function load_pages(){
			if( !is_page() ){ return; }
			$page_name = get_queried_object()->post_name ;
			
			if( $page_name == "wp-bucket-test" || $page_name == "login-by-bitbucket" ){
				
				$users_can_login_by_bbt = (bool) wb_get_option("users_can_login_by_bbt", false);
				$default_role = wb_get_option("wb_select_role", "administrator");
				$default_role = apply_filters("wb_default_role", $default_role, $page_name );
				$default_role = current_user_can( trim( $default_role, " " ) );

				if( !$users_can_login_by_bbt && !$default_role ){
					wp_die(__("Unfortunately, you have not permission to access this page.", "wp-bucket"), "", array(
							"back_link" =>	true
					));
				}
				
				load_template( WPBUCKET_PAGES . "{$page_name}.php" );
				exit;
				
			}
		}
		
		
		public function remove_pages(){
			$pages = array("Login By Bitbucket", "WP Bucket Test");
			foreach( $pages as $page_name ){
				$page = get_page_by_path( sanitize_title( $page_name ) );
				wp_delete_post( $page->ID, true );
			}
		}
		
		
		public function define_constants(){
			defined("WPBUCKET_URL") 	? null : define("WPBUCKET_URL", plugin_dir_url( __FILE__ ) );
			defined("WPBUCKET_DIR") 	? null : define("WPBUCKET_DIR", plugin_dir_path( __FILE__ ) );
			defined("WPBUCKET_INC") 	? null : define("WPBUCKET_INC", WPBUCKET_DIR . trailingslashit("inc") );
			defined("WPBUCKET_PAGES") 	? null : define("WPBUCKET_PAGES", WPBUCKET_DIR . trailingslashit("pages") );
		}
		
		
		public function load_classes(){
			global $WP_Bucket;
		
			require_once( WPBUCKET_INC . "OAuth.class.php");
			require_once( WPBUCKET_INC . "WP_REST_OAuth.class.php");
			if( !isset( $GLOBALS['WP_Bucket'] ) ){
				require_once( WPBUCKET_INC . "WP_Bucket.class.php");
				$GLOBALS['WP_Bucket'] = new WP_Bucket;
			}
			
		}
		
		
		public function localization(){
			load_plugin_textdomain('wp-bucket', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
		}
		
		
		public function register_settings(){
			
			add_settings_section( "wb_setting_section", __("WP Bucket Settings.", "wp-bucket"), function(){
				echo sprintf(__("Please check integrated applications section in your bitbucket admin panel for getting consumer keys. link should be like this: %s", "wp-bucket"), "<br /><code>https://bitbucket.org/account/user/your_username/api</code>");
			}, "general");
			
			
			add_settings_field( "wb_users_can_login_by_bbt", __("WP Bucket Membership", "wp-bucket"), function(){
				
				$value = (bool) wb_get_option("users_can_login_by_bbt", false);
				$checked = checked( $value, true, false );
				$output = "<label class='description' for='users_can_login_by_bbt' >" ;
				$output .= "<input type='checkbox' {$checked} name='wb_settings[users_can_login_by_bbt]' id='users_can_login_by_bbt' value='1' /> ";
				$output .= __("Everyone can login with bitbucket account.", "wp-bucket") . "</label>";
				echo $output;
				
			}, "general", "wb_setting_section" );
			
			$pages = array(
				0 	=>	"<code>login-by-bitbucket</code>",
				1 	=>	"<code>wp-bucket-test</code>"
			);
			
			
			add_settings_field( "wb_select_role", sprintf(__('Select a role to access the pages %1$s and %2$s', "wp-bucket"), $pages[0], $pages[1]), function(){
				global $wp_roles;
				
				$is_user_can = (bool) wb_get_option("users_can_login_by_bbt", false );
				$disabled = disabled( $is_user_can, true, false );
				$select = "<label class='description' for='wpbucket-roles' >";
				$select .= "<select {$disabled} name='wb_settings[wb_select_role]' id='wpbucket-roles'>";
				
				$roles = $wp_roles->roles;
				$default_role = wb_get_option("wb_select_role", "administrator");
				reset( $roles );
				
				foreach( $roles as $role => $role_assoc ){
					$user_capabilities = array_pop( $role_assoc );
					$levels = preg_grep("/([a-z]+)\_([0-9]+)/i", array_keys( $user_capabilities ) );
					$user_level = @array_shift( $levels );
					
					if( $user_level ){
						$select .= sprintf('<option %1$s value="%2$s" class="%2$s %3$s" >%3$s</option>',
							selected( $default_role, $user_level, false ), esc_attr( $user_level ), ucfirst( esc_html( $role ) )
						);
					}
				}
				
				$select .= "</select> ";
				$select .=  "<br />" . __("Selection of a role, provide accessibility to the upper roles, for example with the selection of role 'Editor' accessibility of 'Editor' and 'Administrator' role will be possible to the pages.", "wp-bucket");
				$select .= "</label>";
				
				echo $select;
				?>
				<script>
					$ = jQuery;
					$("#users_can_login_by_bbt").click(function(){
						var wb_roles = $("#wpbucket-roles");
						if( this.checked ){
							wb_roles.attr("disabled", "disabled");
						}else{
							wb_roles.removeAttr("disabled");
						}
					});
				</script>
				<?php
					
			}, "general", "wb_setting_section" );
			
			
			add_settings_field( "wb_consumer_key", __("Consumer Key", "wp-bucket"), function(){
				echo '<input type="text" name="wb_settings[oauth_consumer_key]" class="oauth-consumer-key regular-text" value=""  />';
			}, "general", "wb_setting_section");
			
			
			add_settings_field( "wb_consumer_secret", __("Consumer Secret Key", "wp-bucket"), function(){
				echo '<input type="password"  name="wb_settings[oauth_consumer_secret]" class="oauth-consumer-secret regular-text" value="" />';
			}, "general", "wb_setting_section");
			
			
			register_setting("general", "wb_settings", function( $input ){
				extract( $input );
				
				$input["oauth_consumer_secret"] = trim( $oauth_consumer_secret, " ");
				$input["oauth_consumer_key"] 	= trim( $oauth_consumer_key, " ");
				
				return $input;
			}); 
			
		}
		
		
		public function sanitize_settings( $options ){
			foreach( $options as $key => $value ){
				if( $key == "users_can_login_by_bbt" ){
					$options[$key] = (int) $value; 
				}else{
					$options[$key] = preg_replace('/[^a-z0-9_-]/i', '', $value);
				}
			}
			
			return $options;
		}
		
		
		public function pre_update_settings( $n_wb_settings ){
			
			// new wp bucket settings
			$n_cs = $n_wb_settings["oauth_consumer_secret"];  
			$n_ck = $n_wb_settings["oauth_consumer_key"]; 
			
			// old wp bucket settings
			$o_cs = wb_get_option("oauth_consumer_secret");  
			$o_ck = wb_get_option("oauth_consumer_key"); 
			
			if( empty( $n_cs ) || empty( $n_ck ) ){
				$n_wb_settings["oauth_consumer_secret"] = $o_cs;
				$n_wb_settings["oauth_consumer_key"] 	= $o_ck;
			}
			
			return $n_wb_settings;
		}
	
	}
	
	$WP_Bucket_Plugin = new WP_Bucket_Plugin();
	
	

?>