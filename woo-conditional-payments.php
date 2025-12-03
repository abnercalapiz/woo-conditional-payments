<?php
/**
 * Plugin Name: Woo Conditional Payments
 * Plugin URI: https://www.jezweb.com.au/
 * Description: Control WooCommerce payment methods visibility based on user roles and user-specific settings
 * Version: 1.0.2
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
define( 'WCP_VERSION', '1.0.2' );
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
        // Filter payment methods based on user roles - run very late to override other filters
        add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_methods_by_role' ), 9999 );
        
        // Add custom fields to payment method settings
        add_action( 'woocommerce_init', array( $this, 'add_role_fields_to_payment_methods' ) );

        // Load plugin text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // User profile fields
        add_action( 'show_user_profile', array( $this, 'add_invoice_payment_field' ) );
        add_action( 'edit_user_profile', array( $this, 'add_invoice_payment_field' ) );
        add_action( 'personal_options_update', array( $this, 'save_invoice_payment_field' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_invoice_payment_field' ) );

        // Admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Admin styles
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

        // User list table modifications
        add_filter( 'manage_users_columns', array( $this, 'add_invoice_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'show_invoice_column' ), 10, 3 );
        add_filter( 'bulk_actions-users', array( $this, 'add_bulk_actions' ) );
        add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_actions' ), 10, 3 );
        
        // User list filters
        add_action( 'restrict_manage_users', array( $this, 'add_invoice_filter_dropdown' ) );
        add_action( 'pre_get_users', array( $this, 'filter_users_by_invoice_status' ) );

        // AJAX handlers
        add_action( 'wp_ajax_wcp_toggle_invoice_payment', array( $this, 'ajax_toggle_invoice_payment' ) );
        
        // Admin notices for bulk actions
        add_action( 'admin_notices', array( $this, 'bulk_action_admin_notices' ) );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'woo-conditional-payments', false, dirname( WCP_PLUGIN_BASENAME ) . '/languages' );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Conditional Payments', 'woo-conditional-payments' ),
            __( 'Conditional Payments', 'woo-conditional-payments' ),
            'manage_woocommerce',
            'wcp-settings',
            array( $this, 'settings_page' )
        );
    }

    /**
     * Settings page
     */
    public function settings_page() {
        if ( isset( $_POST['wcp_save_settings'] ) && wp_verify_nonce( $_POST['wcp_settings_nonce'], 'wcp_settings' ) ) {
            update_option( 'wcp_invoice_gateway_id', sanitize_text_field( $_POST['wcp_invoice_gateway_id'] ) );
            update_option( 'wcp_invoice_default_enabled', isset( $_POST['wcp_invoice_default_enabled'] ) ? 'yes' : 'no' );
            echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'woo-conditional-payments' ) . '</p></div>';
        }

        $invoice_gateway_id = get_option( 'wcp_invoice_gateway_id', '' );
        $default_enabled = get_option( 'wcp_invoice_default_enabled', 'no' );
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Conditional Payments Settings', 'woo-conditional-payments' ); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'wcp_settings', 'wcp_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="wcp_invoice_gateway_id"><?php esc_html_e( 'Invoice Payment Gateway', 'woo-conditional-payments' ); ?></label>
                            </th>
                            <td>
                                <select name="wcp_invoice_gateway_id" id="wcp_invoice_gateway_id">
                                    <option value=""><?php esc_html_e( '— Select —', 'woo-conditional-payments' ); ?></option>
                                    <?php foreach ( $payment_gateways as $gateway ) : ?>
                                        <option value="<?php echo esc_attr( $gateway->id ); ?>" <?php selected( $invoice_gateway_id, $gateway->id ); ?>>
                                            <?php echo esc_html( $gateway->get_title() ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e( 'Select which payment gateway represents "Pay by Invoice".', 'woo-conditional-payments' ); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php esc_html_e( 'Default State', 'woo-conditional-payments' ); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wcp_invoice_default_enabled" value="1" <?php checked( $default_enabled, 'yes' ); ?> />
                                    <?php esc_html_e( 'Enable invoice payments by default for new users', 'woo-conditional-payments' ); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e( 'If checked, new users will have invoice payments enabled by default.', 'woo-conditional-payments' ); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <input type="submit" name="wcp_save_settings" class="button-primary" value="<?php esc_attr_e( 'Save Settings', 'woo-conditional-payments' ); ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Add invoice payment field to user profile
     */
    public function add_invoice_payment_field( $user ) {
        $enabled = get_user_meta( $user->ID, 'wcp_invoice_payment_enabled', true );
        ?>
        <h3><?php esc_html_e( 'Payment Options', 'woo-conditional-payments' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="wcp_invoice_payment_enabled"><?php esc_html_e( 'Invoice Payment', 'woo-conditional-payments' ); ?></label></th>
                <td>
                    <label for="wcp_invoice_payment_enabled">
                        <input type="checkbox" name="wcp_invoice_payment_enabled" id="wcp_invoice_payment_enabled" value="1" <?php checked( $enabled, '1' ); ?> />
                        <?php esc_html_e( 'Enable Pay by Invoice', 'woo-conditional-payments' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( 'Allow this user to pay by invoice at checkout.', 'woo-conditional-payments' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save invoice payment field
     */
    public function save_invoice_payment_field( $user_id ) {
        // Verify nonce - WordPress automatically creates these nonces for user profile pages
        if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'update-user_' . $user_id ) ) {
            return;
        }
        
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( isset( $_POST['wcp_invoice_payment_enabled'] ) ) {
            update_user_meta( $user_id, 'wcp_invoice_payment_enabled', '1' );
        } else {
            delete_user_meta( $user_id, 'wcp_invoice_payment_enabled' );
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts( $hook ) {
        if ( 'users.php' !== $hook && 'user-edit.php' !== $hook && 'profile.php' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'wcp-admin', WCP_PLUGIN_URL . 'assets/admin.css', array(), WCP_VERSION );
        wp_enqueue_script( 'wcp-admin', WCP_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), WCP_VERSION, true );
        wp_localize_script( 'wcp-admin', 'wcp_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wcp_ajax' ),
        ) );
    }

    /**
     * Add invoice column to users list
     */
    public function add_invoice_column( $columns ) {
        $columns['wcp_invoice'] = __( 'Invoice Payment', 'woo-conditional-payments' );
        return $columns;
    }

    /**
     * Show invoice column content
     */
    public function show_invoice_column( $value, $column_name, $user_id ) {
        if ( 'wcp_invoice' !== $column_name ) {
            return $value;
        }

        $enabled = get_user_meta( $user_id, 'wcp_invoice_payment_enabled', true );
        $status_class = $enabled === '1' ? 'enabled' : 'disabled';
        $status_text = $enabled === '1' ? __( 'Enabled', 'woo-conditional-payments' ) : __( 'Disabled', 'woo-conditional-payments' );
        
        return sprintf(
            '<span class="wcp-invoice-status %s" data-user-id="%d">%s</span>',
            esc_attr( $status_class ),
            esc_attr( $user_id ),
            esc_html( $status_text )
        );
    }

    /**
     * Add bulk actions
     */
    public function add_bulk_actions( $bulk_actions ) {
        $bulk_actions['enable_invoice_payment'] = __( 'Enable Invoice Payment', 'woo-conditional-payments' );
        $bulk_actions['disable_invoice_payment'] = __( 'Disable Invoice Payment', 'woo-conditional-payments' );
        return $bulk_actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions( $redirect_to, $action, $user_ids ) {
        // Check if user has permission to edit users
        if ( ! current_user_can( 'edit_users' ) ) {
            return $redirect_to;
        }
        
        if ( 'enable_invoice_payment' === $action ) {
            foreach ( $user_ids as $user_id ) {
                // Verify permission for each user
                if ( current_user_can( 'edit_user', $user_id ) ) {
                    update_user_meta( $user_id, 'wcp_invoice_payment_enabled', '1' );
                }
            }
            $redirect_to = add_query_arg( 'wcp_bulk_action', 'enabled', $redirect_to );
        } elseif ( 'disable_invoice_payment' === $action ) {
            foreach ( $user_ids as $user_id ) {
                // Verify permission for each user
                if ( current_user_can( 'edit_user', $user_id ) ) {
                    delete_user_meta( $user_id, 'wcp_invoice_payment_enabled' );
                }
            }
            $redirect_to = add_query_arg( 'wcp_bulk_action', 'disabled', $redirect_to );
        }

        return $redirect_to;
    }

    /**
     * AJAX toggle invoice payment
     */
    public function ajax_toggle_invoice_payment() {
        check_ajax_referer( 'wcp_ajax', 'nonce' );

        if ( ! current_user_can( 'edit_users' ) ) {
            wp_die( -1 );
        }

        $user_id = intval( $_POST['user_id'] );
        $current = get_user_meta( $user_id, 'wcp_invoice_payment_enabled', true );

        if ( $current === '1' ) {
            delete_user_meta( $user_id, 'wcp_invoice_payment_enabled' );
            wp_send_json_success( array( 'enabled' => false ) );
        } else {
            update_user_meta( $user_id, 'wcp_invoice_payment_enabled', '1' );
            wp_send_json_success( array( 'enabled' => true ) );
        }
    }

    /**
     * Add invoice filter dropdown to users list
     */
    public function add_invoice_filter_dropdown() {
        $selected = isset( $_GET['wcp_invoice_status'] ) ? sanitize_text_field( $_GET['wcp_invoice_status'] ) : '';
        ?>
        <label class="screen-reader-text" for="wcp_invoice_status"><?php esc_html_e( 'Filter by invoice payment status', 'woo-conditional-payments' ); ?></label>
        <select name="wcp_invoice_status" id="wcp_invoice_status" style="margin: 0 5px;">
            <option value=""><?php esc_html_e( 'All Invoice Payment Status', 'woo-conditional-payments' ); ?></option>
            <option value="enabled" <?php selected( $selected, 'enabled' ); ?>><?php esc_html_e( 'Invoice Payment Enabled', 'woo-conditional-payments' ); ?></option>
            <option value="disabled" <?php selected( $selected, 'disabled' ); ?>><?php esc_html_e( 'Invoice Payment Disabled', 'woo-conditional-payments' ); ?></option>
        </select>
        <?php
    }

    /**
     * Filter users by invoice payment status
     */
    public function filter_users_by_invoice_status( $query ) {
        // Only run on admin users list
        if ( ! is_admin() ) {
            return;
        }

        // Check if we have a filter value
        if ( empty( $_GET['wcp_invoice_status'] ) ) {
            return;
        }

        $status = sanitize_text_field( $_GET['wcp_invoice_status'] );
        $default_enabled = get_option( 'wcp_invoice_default_enabled', 'no' );
        
        // Get any existing meta queries
        $existing_meta_query = $query->get( 'meta_query' );
        if ( ! is_array( $existing_meta_query ) ) {
            $existing_meta_query = array();
        }

        if ( $status === 'enabled' ) {
            // Get users with invoice payment enabled
            if ( $default_enabled === 'yes' ) {
                // If default is enabled, get users who don't have it explicitly disabled
                $new_meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wcp_invoice_payment_enabled',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => 'wcp_invoice_payment_enabled',
                        'value' => '1',
                        'compare' => '='
                    )
                );
            } else {
                // If default is disabled, only get users with explicit '1'
                $new_meta_query = array(
                    'key' => 'wcp_invoice_payment_enabled',
                    'value' => '1',
                    'compare' => '='
                );
            }
        } elseif ( $status === 'disabled' ) {
            // Get users without invoice payment enabled
            if ( $default_enabled === 'yes' ) {
                // If default is enabled, only get users who have it explicitly disabled
                $new_meta_query = array(
                    'key' => 'wcp_invoice_payment_enabled',
                    'value' => '1',
                    'compare' => '!='
                );
            } else {
                // If default is disabled, get users without meta or not '1'
                $new_meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'wcp_invoice_payment_enabled',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => 'wcp_invoice_payment_enabled',
                        'value' => '1',
                        'compare' => '!='
                    )
                );
            }
        } else {
            return;
        }
        
        // Combine with existing meta queries if any
        if ( ! empty( $existing_meta_query ) ) {
            $existing_meta_query[] = $new_meta_query;
            $query->set( 'meta_query', $existing_meta_query );
        } else {
            $query->set( 'meta_query', array( $new_meta_query ) );
        }
    }

    /**
     * Display admin notices for bulk actions
     */
    public function bulk_action_admin_notices() {
        if ( ! empty( $_GET['wcp_bulk_action'] ) ) {
            $action = sanitize_text_field( $_GET['wcp_bulk_action'] );
            $count = isset( $_GET['users'] ) ? count( $_GET['users'] ) : 0;
            
            if ( $action === 'enabled' ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php printf( esc_html__( 'Invoice payment enabled for %d user(s).', 'woo-conditional-payments' ), $count ); ?></p>
                </div>
                <?php
            } elseif ( $action === 'disabled' ) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php printf( esc_html__( 'Invoice payment disabled for %d user(s).', 'woo-conditional-payments' ), $count ); ?></p>
                </div>
                <?php
            }
        }
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
        $invoice_gateway_id = get_option( 'wcp_invoice_gateway_id', '' );
        
        // First, handle user-specific invoice payment settings
        if ( ! empty( $invoice_gateway_id ) && is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $invoice_enabled = get_user_meta( $current_user->ID, 'wcp_invoice_payment_enabled', true );
            
            // If user meta is not set, use the default setting
            if ( $invoice_enabled === '' ) {
                $default_enabled = get_option( 'wcp_invoice_default_enabled', 'no' );
                $invoice_enabled = ( $default_enabled === 'yes' ) ? '1' : '0';
            }
            
            // If user has invoice payment explicitly enabled, ensure it's available
            if ( $invoice_enabled === '1' && ! isset( $available_gateways[ $invoice_gateway_id ] ) ) {
                // Re-add the invoice gateway if it was removed by WooCommerce or other plugins
                $payment_gateways = WC()->payment_gateways->payment_gateways();
                if ( isset( $payment_gateways[ $invoice_gateway_id ] ) ) {
                    $available_gateways[ $invoice_gateway_id ] = $payment_gateways[ $invoice_gateway_id ];
                }
            }
        }
        
        foreach ( $available_gateways as $gateway_id => $gateway ) {
            // Ensure gateway is an object and has the get_option method
            if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_option' ) ) {
                continue;
            }

            // Handle invoice payment gateway with user-specific settings
            if ( $gateway_id === $invoice_gateway_id && ! empty( $invoice_gateway_id ) ) {
                if ( is_user_logged_in() ) {
                    $current_user = wp_get_current_user();
                    $invoice_enabled = get_user_meta( $current_user->ID, 'wcp_invoice_payment_enabled', true );
                    
                    // If user meta is not set, use the default setting
                    if ( $invoice_enabled === '' ) {
                        $default_enabled = get_option( 'wcp_invoice_default_enabled', 'no' );
                        $invoice_enabled = ( $default_enabled === 'yes' ) ? '1' : '0';
                    }
                    
                    // If user has invoice payment enabled, skip role checks
                    if ( $invoice_enabled === '1' ) {
                        continue;
                    }
                    
                    // If user doesn't have invoice payment enabled, remove it
                    unset( $available_gateways[ $gateway_id ] );
                    continue;
                } else {
                    // For non-logged-in users, remove the invoice gateway
                    unset( $available_gateways[ $gateway_id ] );
                    continue;
                }
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

    // Set default option for invoice payment default state
    add_option( 'wcp_invoice_default_enabled', 'no' );
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