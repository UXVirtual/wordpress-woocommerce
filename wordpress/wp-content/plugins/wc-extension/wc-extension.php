<?php
/**
 * Plugin Name:       WooCommerce Extension
 * Plugin URI:        https://uxvirtual.com/
 * Description:       A boilerplate for extending WooCommerce functionality.
 * Version:           1.0.0
 * Author:            Michael Andrew
 * Author URI:        https://uxvirtual.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wc-extension
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:    9.1
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Check if WooCommerce is active before doing anything else.
 *
 * This prevents fatal errors if the plugin is active without WooCommerce.
 */
add_action( 'plugins_loaded', 'wc_extension_init' );

function wc_extension_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_extension_woocommerce_not_active_notice' );
		return;
	}

	// WooCommerce is active, so we can safely initialize our plugin.
	WC_Extension::instance();
}

/**
 * Display an admin notice if WooCommerce is not active.
 */
function wc_extension_woocommerce_not_active_notice() {
	?>
	<div class="error">
		<p><?php _e( '<b>My WooCommerce Extension</b> requires WooCommerce to be installed and active.', 'my-wc-extension' ); ?></p>
	</div>
	<?php
}

/**
 * Main Plugin Class.
 *
 * A singleton class to prevent multiple instances and provide a global access point.
 */
final class WC_Extension {

	/**
	 * The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Ensures only one instance of the class is loaded.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->setup_hooks();
	}

	/**
	 * Define Plugin Constants.
	 */
	private function define_constants() {
		define( 'WC_EXTENSION_VERSION', '1.0.0' );
		define( 'WC_EXTENSION_PATH', plugin_dir_path( __FILE__ ) );
		define( 'WC_EXTENSION_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Setup all the action and filter hooks.
	 */
	private function setup_hooks() {
		// Add your hooks here. For example:
		add_action( 'woocommerce_single_product_summary', array( $this, 'add_custom_text_to_product_page' ), 25 );
	}

	/*
	|--------------------------------------------------------------------------
	| Your Custom Functions
	|--------------------------------------------------------------------------
	|
	| All your functionality should be defined in methods of this class.
	|
	*/

	/**
	 * Example function hooked into WooCommerce.
	 */
	public function add_custom_text_to_product_page() {
		echo '<p>This is my custom text!</p>';
	}
}
