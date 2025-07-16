<?php
/**
 * Plugin Name:       WooCommerce Extension
 * Plugin URI:        https://uxvirtual.com/
 * Description:       A boilerplate for extending WooCommerce functionality.
 * Version:           1.0.0
 * Author:            Michael Andrew
 * Author URI:        https://uxvirtual.com/
 * License:           MIT
 * License URI:		  https://opensource.org/licenses/MIT
 * Text Domain:       wc-extension
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.0.2
 */

/** 
 * Stops the plugin being accessed directly
 */
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/** 
 * Enable High Performance Order Storage (HPOS) if WooCommerce supports it. This avoids WooCommerce
 * throwing an error by explicitly declaring compatibility with the custom order tables feature.
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Check if WooCommerce is active before doing anything else.
 *
 * This prevents fatal errors if the plugin is active without WooCommerce.
 */
add_action( 'plugins_loaded', 'wc_extension_init' );

/**
 * Create a custom table for the loyalty program on plugin activation.
 *
 * This function creates a custom table to store user loyalty program data.
 */
register_activation_hook( __FILE__, 'wc_extension_create_loyalty_table' );

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
		<p><?php _e( '<b>WooCommerce Extension</b> requires WooCommerce to be installed and active.', 'wc-extension' ); ?></p>
	</div>
	<?php
}

/**
 * Create the custom table for the loyalty program.
 *
 * This function is called on plugin activation to create a table that stores user loyalty points.
 * The table will have a user ID, loyalty points, and a timestamp for the last update.
 * 
 * The table is indexed by user ID and loyalty points for efficient querying.
 */
function wc_extension_create_loyalty_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'wc_loyalty_users';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        user_id bigint(20) UNSIGNED NOT NULL,
        loyalty_points int(11) NOT NULL DEFAULT 0,
        last_updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (user_id),
        KEY loyalty_points (loyalty_points)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
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
	 * 
	 * @return void
	 */
	private function __construct() {
		$this->define_constants();
		$this->setup_hooks();
	}

	/**
	 * Define Plugin Constants.
	 * 
	 * @return void
	 */
	private function define_constants() {
		define( 'WC_EXTENSION_VERSION', '1.0.0' );
		define( 'WC_EXTENSION_PATH', plugin_dir_path( __FILE__ ) );
		define( 'WC_EXTENSION_URL', plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Setup all the action and filter hooks.
	 * 
	 * @return void
	 */
	private function setup_hooks() {
		// Loyalty Program Feature: Add a checkbox to user profiles.
		add_action('show_user_profile', [$this, 'add_loyalty_program_field_to_profile']);
		add_action('edit_user_profile', [$this, 'add_loyalty_program_field_to_profile']);
		add_action('personal_options_update', [$this, 'save_loyalty_program_field']);
		add_action('edit_user_profile_update', [$this, 'save_loyalty_program_field']);
		add_action('woocommerce_account_dashboard', [$this, 'display_loyalty_points_on_dashboard']);

		// Custom Pricing Feature: Apply a 10% discount to all users in the loyalty program.
		add_filter('woocommerce_product_get_price', [$this, 'apply_loyalty_pricing'], 10, 2);
    	add_filter('woocommerce_product_variation_get_price', [$this, 'apply_loyalty_pricing'], 10, 2);
		add_filter('woocommerce_get_price_html', [$this, 'display_custom_loyalty_price_html'], 10, 2);


		// Engraving Feature: Add custom engraving text field to product pages.
		add_filter('woocommerce_add_cart_item_data', [$this, 'add_engraving_text_to_cart'], 10, 3);
		add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_engraving_text_to_order_item'], 10, 4);
		add_action('woocommerce_product_options_general_product_data', [$this, 'add_engraving_option_to_products']);
		add_action('woocommerce_process_product_meta', [$this, 'save_engraving_option_field']);
		add_action('woocommerce_before_add_to_cart_button', [$this, 'display_engraving_field']);
		add_filter('woocommerce_get_item_data', [$this, 'display_engraving_in_cart'], 10, 2);
	}

	/*
	|--------------------------------------------------------------------------
	| Your Custom Functions
	|--------------------------------------------------------------------------
	*/

	/**
     * Add a "Loyalty Program" checkbox to user profile pages.
     *
     * @param WP_User $user The user object.
     */
    public function add_loyalty_program_field_to_profile($user) {
        $points = $this->get_user_loyalty_status($user->ID);
        // If null, user isn't in the program yet. Default to 0 for the input field.
        $display_points = is_null($points) ? 0 : $points;
        ?>
        <h3><?php _e('WooCommerce Loyalty', 'wc-extension'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="loyalty_points"><?php _e('Loyalty Points', 'wc-extension'); ?></label></th>
                <td>
                    <input type="number" name="loyalty_points" id="loyalty_points" value="<?php echo esc_attr($display_points); ?>" class="small-text" />
                    <p class="description"><?php _e('Set the loyalty points for this user. A user with points is in the program.', 'wc-extension'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

	/**
     * Save the loyalty program field value from the user profile page.
	 * 
	 * Note: This will not scale to extremely large user bases since it updates user meta directly.
	 * Loyalty programs should ideally be managed in a custom table with proper indexing for performance
	 * OR use a custom user role.
     *
     * @param int $user_id The ID of the user being saved.
     */
    public function save_loyalty_program_field($user_id) {
        if (!current_user_can('edit_user', $user_id) || !isset($_POST['loyalty_points'])) {
            return;
        }
        $points = intval($_POST['loyalty_points']);
        $this->update_user_loyalty_status($user_id, $points);
    }

	/**
     * Displays the user's loyalty points on the My Account dashboard.
	 * 
	 * @return void
     */
    public function display_loyalty_points_on_dashboard() {
        $user_id = get_current_user_id();
        $points = $this->get_user_loyalty_status($user_id);

        // Check if the user is in the loyalty program (points are not null).
        if (!is_null($points)) {
            echo '<p>';
            printf(
                // Translators: %s is the number of points.
                esc_html__('You currently have %s loyalty points.', 'wc-extension'),
                '<strong>' . esc_html($points) . '</strong>'
            );
            echo '</p>';
        }
    }

	/**
     * Applies a 10% discount store-wide for users in the loyalty program.
     *
     * This function hooks into the product price calculation and applies the
     * discount if the logged-in user is part of the loyalty program.
	 * 
	 * Use a static variable to cache the user status for the duration of the page load
	 * This stops multiple database queries for the same user on the same page load,
	 * allowing this logic to run faster.
     *
     * @param float $price The original product price.
     * @param WC_Product $product The product object.
     * @return float The original or modified price.
     */
    public function apply_loyalty_pricing($price, $product) {
        // Don't modify the price in the admin panel or for guests
        if (is_admin() || !is_user_logged_in()) {
            return $price;
        }

        static $loyalty_status = null;
        if (is_null($loyalty_status)) {
            $user_id = get_current_user_id();
            $loyalty_status = $this->get_user_loyalty_status($user_id);
        }

        // If the user is in the loyalty program (status is not null), apply the discount
        if (!is_null($loyalty_status)) {
            $price = $price * 0.90; // 10% discount
        }

        return $price;
    }

	/**
     * Customizes the price display on single product pages for loyalty members.
	 * Only show for logged-in users who are in the loyalty program.
	 * This is a common practice in e-commerce to show both the original price
	 * and the discounted price.
     *
     * @param string $price_html The original price HTML.
     * @param WC_Product $product The product object.
     * @return string The original or modified price HTML.
     */
    public function display_custom_loyalty_price_html($price_html, $product) {
        if (is_user_logged_in()) {
            static $loyalty_status = null;
            if (is_null($loyalty_status)) {
                $user_id = get_current_user_id();
                $loyalty_status = $this->get_user_loyalty_status($user_id);
            }

			if (!is_null($loyalty_status)) {
                $regular_price_html = wc_price($product->get_regular_price());
                $discounted_price_html = wc_price($product->get_price()); // This is already discounted by our other function

                $price_html = sprintf(
                    '<p class="price loyalty-price">Before: <del>%s</del><br>Your Price: <ins>%s</ins></p>',
                    $regular_price_html,
                    $discounted_price_html
                );
            }
        }

        return $price_html;
    }

	/**
	 * Display the custom engraving field on the product page.
	 *
	 * This function checks if engraving is enabled for the product and displays
	 * a text input field for the engraving text.
	 * 
	 * @return void
	 */
	function display_engraving_field() {
		global $product;

		if ('yes' === get_post_meta($product->get_id(), '_enable_engraving', true)) {
			echo '<div class="custom-engraving-field" style="margin-bottom: 1em;">';
			echo '<label for="custom_engraving_text">' . __('Engraving Text:', 'wc-extension') . '</label>';
			echo '<input type="text" id="custom_engraving_text" name="custom_engraving_text" maxlength="50">';
			echo '</div>';
		}
	}

	/**
	 * Add the custom engraving field to the admin product edit page.
	 * 
	 * @return void
	 */
	function add_engraving_option_to_products() {
		woocommerce_wp_checkbox(array(
			'id'          => '_enable_engraving',
			'label'       => __('Enable Engraving', 'wc-extension'),
			'description' => __('Check this box to enable the custom engraving field for this product.', 'wc-extension'),
			'desc_tip'    => true,
		));
	}

	/**
	 * Save the engraving option field when the product is saved.
	 * 
	 * @param int $post_id The ID of the product being saved.
	 * @return void
	 */
	function save_engraving_option_field($post_id) {
		$is_engraving_enabled = isset($_POST['_enable_engraving']) ? 'yes' : 'no';
		update_post_meta($post_id, '_enable_engraving', $is_engraving_enabled);
	}

	/**
	 * Add custom engraving onto cart item for product
	 * 
	 * @param array $cart_item_data The cart item data.
	 * @param int $product_id The product ID.
	 * @param int $variation_id The variation ID (if applicable).
	 * @return array The modified cart item data.
	 */
	function add_engraving_text_to_cart($cart_item_data, $product_id, $variation_id) {
		if (isset($_POST['custom_engraving_text'])) {
			$cart_item_data['engraving_text'] = sanitize_text_field($_POST['custom_engraving_text']);
		}
		return $cart_item_data;
	}

	/**
     * Display the engraving text in the cart and checkout order summary.
     *
     * @param array $item_data The array of item data.
     * @param array $cart_item The cart item data.
     * @return array The modified item data.
     */
    public function display_engraving_in_cart($item_data, $cart_item) {
        if (isset($cart_item['engraving_text'])) {
            $item_data[] = array(
                'key'   => __('Engraving', 'wc-extension'),
                'value' => esc_html($cart_item['engraving_text']),
            );
        }
        return $item_data;
    }

	/**
	 * Update order item with engraving text. Adds meta data to the order item so this can
	 * be displayed in the order details when it is processed prior to shipping.
	 * 
	 * Note that if the engraving text needs to be searched or filtered, it may be better
	 * to store this in a custom table with additional indexes or use a more complex data
	 * structure since the `add_meta_data` function is not optimized for large datasets.
	 * 
	 * @param WC_Order_Item_Product $item The order item object.
	 * @param string $cart_item_key The cart item key.
	 * @param array $values The cart item values.
	 * @param WC_Order $order The order object.
	 * @return void
	 */
	function save_engraving_text_to_order_item($item, $cart_item_key, $values, $order) {
    	if (isset($values['engraving_text'])) {
			$item->add_meta_data(
				'Engraving',
				$values['engraving_text']
			);
    	}
	}

	/**
     * Get loyalty points for a specific user.
	 * 
     * @param int $user_id The user's ID.
     * @return int|null The points, or null if not in the program.
     */
    private function get_user_loyalty_status( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_loyalty_users';
        $points = $wpdb->get_var( $wpdb->prepare( "SELECT loyalty_points FROM $table_name WHERE user_id = %d", $user_id ) );
        return $points;
    }

    /**
     * Update loyalty points for a specific user.
	 * 
     * @param int $user_id The user's ID.
     * @param int $points The new points value.
	 * @return void
     */
    private function update_user_loyalty_status( $user_id, $points ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_loyalty_users';

        $wpdb->replace(
            $table_name,
            array(
                'user_id'        => $user_id,
                'loyalty_points' => $points,
                'last_updated'   => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s' )
        );
    }
}
