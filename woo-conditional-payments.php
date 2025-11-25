<?php
/**
 * Plugin Name: Woo Conditional Payments
 * Plugin URI: https://www.jezweb.com.au/
 * Description: Control WooCommerce payment methods visibility based on user roles
 * Version: 1.0.1
 * Author: Jezweb
 * Author URI: https://www.jezweb.com.au/
 * License: GPL v2 or later
 * Text Domain: woo-conditional-payments
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'WCP_VERSION', '1.0.1' );
define( 'WCP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class Woo_Conditional_Payments {

	/**
	 * Instance
	 *
	 * @var Woo_Conditional_Payments
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Woo_Conditional_Payments
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Check if WooCommerce is active
		if ( ! $this->is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_inactive_notice' ) );
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool
	 */
	private function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Display WooCommerce inactive notice
	 */
	public function woocommerce_inactive_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'Woo Conditional Payments requires WooCommerce to be installed and active.', 'woo-conditional-payments' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// Filter payment methods based on user roles
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_methods_by_role' ), 999 );
		
		// Add custom fields to payment method settings
		add_action( 'woocommerce_init', array( $this, 'add_role_fields_to_payment_methods' ) );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'woo-conditional-payments', false, dirname( WCP_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Add role fields to payment methods
	 */
	public function add_role_fields_to_payment_methods() {
		// Check if WC() is available
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return;
		}
		
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		
		if ( ! is_array( $payment_gateways ) ) {
			return;
		}
		
		foreach ( $payment_gateways as $gateway_id => $gateway ) {
			// Sanitize gateway ID for use in hook name
			$gateway_id = sanitize_key( $gateway_id );
			add_filter( 'woocommerce_settings_api_form_fields_' . $gateway_id, array( $this, 'add_role_visibility_field' ) );
		}
	}

	/**
	 * Add role visibility field to payment gateway settings
	 *
	 * @param array $form_fields
	 * @return array
	 */
	public function add_role_visibility_field( $form_fields ) {
		$roles = $this->get_all_user_roles();
		
		$form_fields['wcp_visible_to_roles'] = array(
			'title'       => __( 'Visible to User Roles', 'woo-conditional-payments' ),
			'type'        => 'multiselect',
			'description' => __( 'Select which user roles can see this payment method. Leave empty to show to all users.', 'woo-conditional-payments' ),
			'desc_tip'    => true,
			'options'     => $roles,
			'default'     => array(),
			'css'         => 'width: 350px;',
			'class'       => 'wc-enhanced-select',
		);
		
		$form_fields['wcp_hidden_from_roles'] = array(
			'title'       => __( 'Hide from User Roles', 'woo-conditional-payments' ),
			'type'        => 'multiselect',
			'description' => __( 'Select which user roles should NOT see this payment method. This overrides the "Visible to" setting above.', 'woo-conditional-payments' ),
			'desc_tip'    => true,
			'options'     => $roles,
			'default'     => array(),
			'css'         => 'width: 350px;',
			'class'       => 'wc-enhanced-select',
		);
		
		return $form_fields;
	}

	/**
	 * Get all user roles
	 *
	 * @return array
	 */
	private function get_all_user_roles() {
		global $wp_roles;
		
		$roles = array();
		
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		
		foreach ( $wp_roles->roles as $role_key => $role ) {
			$roles[ $role_key ] = translate_user_role( $role['name'] );
		}
		
		// Add guest/non-logged-in user option
		$roles['guest'] = __( 'Guest (non-logged-in)', 'woo-conditional-payments' );
		
		return $roles;
	}

	/**
	 * Filter payment methods based on user role
	 *
	 * @param array $available_gateways
	 * @return array
	 */
	public function filter_payment_methods_by_role( $available_gateways ) {
		// Ensure we're on the frontend and not in admin
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $available_gateways;
		}
		
		if ( ! is_array( $available_gateways ) ) {
			return $available_gateways;
		}
		
		// Don't filter if WooCommerce is not available
		if ( ! function_exists( 'WC' ) || ! WC() ) {
			return $available_gateways;
		}
		
		$current_user_roles = $this->get_current_user_roles();
		
		foreach ( $available_gateways as $gateway_id => $gateway ) {
			// Ensure gateway is an object and has the get_option method
			if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_option' ) ) {
				continue;
			}
			
			// Get both visible and hidden role settings
			$visible_roles = $gateway->get_option( 'wcp_visible_to_roles', array() );
			$hidden_roles = $gateway->get_option( 'wcp_hidden_from_roles', array() );
			
			// Ensure arrays
			if ( ! is_array( $visible_roles ) ) {
				$visible_roles = array();
			}
			if ( ! is_array( $hidden_roles ) ) {
				$hidden_roles = array();
			}
			
			// Sanitize the roles
			$visible_roles = array_map( 'sanitize_text_field', $visible_roles );
			$hidden_roles = array_map( 'sanitize_text_field', $hidden_roles );
			
			// First check if any of user's roles are in hidden roles (takes priority)
			if ( ! empty( $hidden_roles ) && array_intersect( $current_user_roles, $hidden_roles ) ) {
				unset( $available_gateways[ $gateway_id ] );
				continue;
			}
			
			// Then check visible roles (only if visible roles are set)
			if ( ! empty( $visible_roles ) && ! array_intersect( $current_user_roles, $visible_roles ) ) {
				unset( $available_gateways[ $gateway_id ] );
			}
		}
		
		return $available_gateways;
	}

	/**
	 * Get current user roles
	 *
	 * @return array
	 */
	private function get_current_user_roles() {
		if ( ! is_user_logged_in() ) {
			return array( 'guest' );
		}
		
		$current_user = wp_get_current_user();
		
		// Check if user object is valid
		if ( ! $current_user || ! $current_user->exists() ) {
			return array( 'guest' );
		}
		
		$roles = $current_user->roles;
		
		// Ensure roles is an array
		if ( ! is_array( $roles ) || empty( $roles ) ) {
			return array( 'guest' );
		}
		
		// Return all user roles
		return $roles;
	}
}

/**
 * Initialize the plugin
 */
function wcp_init() {
	return Woo_Conditional_Payments::get_instance();
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action( 'plugins_loaded', 'wcp_init', 11 );

/**
 * Plugin activation hook
 */
register_activation_hook( __FILE__, 'wcp_activate' );
function wcp_activate() {
	// Clear any cached payment methods
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook( __FILE__, 'wcp_deactivate' );
function wcp_deactivate() {
	// Clear any cached payment methods
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
}