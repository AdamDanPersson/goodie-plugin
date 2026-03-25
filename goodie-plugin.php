<?php
/**
 * Plugin Name: Goodie Collections
 * Plugin URI:  https://goodie.local
 * Description: Frontend candy collection builder for WooCommerce.
 * Version:     1.0.0
 * Author:      Goodie
 * Text Domain: goodie-collections
 * Domain Path: /languages
 *
 * @package GoodieCollections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GOODIE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GOODIE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GOODIE_PLUGIN_PATH . 'includes/post-types.php';
require_once GOODIE_PLUGIN_PATH . 'includes/frontend-form.php';
require_once GOODIE_PLUGIN_PATH . 'includes/cart-functions.php';

/**
 * Main plugin bootstrap class.
 */
class Goodie_Collections {

	/**
	 * Frontend form handler.
	 *
	 * @var Goodie_Collections_Frontend_Form
	 */
	protected $frontend_form;

	/**
	 * Cart handler.
	 *
	 * @var Goodie_Collections_Cart_Functions
	 */
	protected $cart_functions;

	/**
	 * Post type handler.
	 *
	 * @var Goodie_Collections_Post_Types
	 */
	protected $post_types;

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'bootstrap_plugin' ), 20 );
		add_action( 'admin_notices', array( $this, 'maybe_show_woocommerce_notice' ) );
	}

	/**
	 * Bootstrap plugin services after plugins are loaded.
	 *
	 * @return void
	 */
	public function bootstrap_plugin() {
		$this->post_types = new Goodie_Collections_Post_Types();
		$this->post_types->init();

		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		$this->frontend_form  = new Goodie_Collections_Frontend_Form();
		$this->cart_functions = new Goodie_Collections_Cart_Functions();

		$this->frontend_form->init();
		$this->cart_functions->init();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_head', array( $this, 'render_gtm_head' ), 1 );
		add_action( 'wp_body_open', array( $this, 'render_gtm_body' ) );
	}

	/**
	 * Load plugin translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'goodie-collections', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		$active = class_exists( 'WooCommerce' );

		return (bool) apply_filters( 'goodie_collections_is_woocommerce_active', $active );
	}

	/**
	 * Enqueue frontend assets for collection pages.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		global $post;

		$should_enqueue = is_post_type_archive( 'goodie_collection' ) || is_singular( 'goodie_collection' );

		if ( $post instanceof WP_Post ) {
			$should_enqueue = $should_enqueue || has_shortcode( $post->post_content, 'goodie_collection_form' );
		}

		$should_enqueue = (bool) apply_filters( 'goodie_collections_should_enqueue_assets', $should_enqueue, $post );

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_script(
			'goodie-collections',
			GOODIE_PLUGIN_URL . 'assets/js/goodie.js',
			array(),
			'1.0.0',
			true
		);

		wp_localize_script(
			'goodie-collections',
			'goodieCollections',
			array(
				'collectionCreated' => isset( $_GET['goodie_collection_created'] ) ? 1 : 0,
				'collectionId'      => is_singular( 'goodie_collection' ) ? get_the_ID() : 0,
				'collectionName'    => is_singular( 'goodie_collection' ) ? html_entity_decode( wp_strip_all_tags( get_the_title() ), ENT_QUOTES, 'UTF-8' ) : '',
				'eventName'         => apply_filters( 'goodie_collections_gtm_event_name', 'goodie_collection_created' ),
				'minProducts'       => goodie_collections_get_minimum_products(),
				'i18n'              => array(
					'selectedCount' => __( 'Selected products: %d', 'goodie-collections' ),
				),
			)
		);
	}

	/**
	 * Output the Google Tag Manager head snippet.
	 *
	 * @return void
	 */
	public function render_gtm_head() {
		$container_id = goodie_collections_get_gtm_container_id();

		if ( empty( $container_id ) ) {
			return;
		}
		?>
		<!-- Google Tag Manager -->
		<script>
			(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo esc_js( $container_id ); ?>');
		</script>
		<!-- End Google Tag Manager -->
		<?php
	}

	/**
	 * Output the Google Tag Manager body snippet.
	 *
	 * @return void
	 */
	public function render_gtm_body() {
		$container_id = goodie_collections_get_gtm_container_id();

		if ( empty( $container_id ) ) {
			return;
		}
		?>
		<!-- Google Tag Manager (noscript) -->
		<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $container_id ); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
		<!-- End Google Tag Manager (noscript) -->
		<?php
	}

	/**
	 * Show an admin notice when WooCommerce is unavailable.
	 *
	 * @return void
	 */
	public function maybe_show_woocommerce_notice() {
		$show_notice = get_option( 'goodie_collections_missing_woocommerce', false ) || ! $this->is_woocommerce_active();

		if ( ! $show_notice || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		delete_option( 'goodie_collections_missing_woocommerce' );
		?>
		<div class="notice notice-error">
			<p><?php echo esc_html__( 'Goodie Collections requires WooCommerce to be active.', 'goodie-collections' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			update_option( 'goodie_collections_missing_woocommerce', 1 );
		}
	}
}

/**
 * Return the configured GTM container ID.
 *
 * @return string
 */
function goodie_collections_get_gtm_container_id() {
	$container_id = (string) apply_filters( 'goodie_collections_gtm_container_id', '' );

	return preg_replace( '/[^A-Z0-9\-]/', '', $container_id );
}

/**
 * Return the minimum number of products required per collection.
 *
 * @return int
 */
function goodie_collections_get_minimum_products() {
	$minimum = (int) apply_filters( 'goodie_collections_minimum_products', 2 );

	return max( 2, $minimum );
}

/**
 * Return stored WooCommerce product IDs for a collection.
 *
 * @param int $collection_id Collection post ID.
 *
 * @return int[]
 */
function goodie_collections_get_product_ids( $collection_id ) {
	$product_ids = get_post_meta( $collection_id, '_goodie_collection_product_ids', true );
	$product_ids = is_array( $product_ids ) ? $product_ids : array();

	return array_values( array_filter( array_map( 'absint', $product_ids ) ) );
}

/**
 * Return WooCommerce products assigned to a collection.
 *
 * @param int $collection_id Collection post ID.
 *
 * @return WC_Product[]
 */
function goodie_collections_get_collection_products( $collection_id ) {
	$products = array();

	foreach ( goodie_collections_get_product_ids( $collection_id ) as $product_id ) {
		$product = wc_get_product( $product_id );

		if ( $product ) {
			$products[] = $product;
		}
	}

	return (array) apply_filters( 'goodie_collections_collection_products', $products, $collection_id );
}

$goodie_collections = new Goodie_Collections();
$goodie_collections->init();

register_activation_hook( __FILE__, array( 'Goodie_Collections', 'activate' ) );
