<?php
/**
 * Add collection products to the WooCommerce cart.
 *
 * @package GoodieCollections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle collection cart submissions.
 */
class Goodie_Collections_Cart_Functions {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_loaded', array( $this, 'handle_add_collection_to_cart' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'add_collection_item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'append_collection_cart_label' ), 10, 3 );
	}

	/**
	 * Add all products from a collection to the cart.
	 *
	 * @return void
	 */
	public function handle_add_collection_to_cart() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( empty( $_POST['goodie_collection_cart_action'] ) || 'add_collection_to_cart' !== sanitize_key( wp_unslash( $_POST['goodie_collection_cart_action'] ) ) ) {
			return;
		}

		$collection_id = isset( $_POST['goodie_collection_id'] ) ? absint( wp_unslash( $_POST['goodie_collection_id'] ) ) : 0;

		if ( ! $collection_id || 'goodie_collection' !== get_post_type( $collection_id ) ) {
			return;
		}

		check_admin_referer( 'goodie_collection_add_to_cart_' . $collection_id, 'goodie_collection_cart_nonce' );

		$product_ids = goodie_collections_get_product_ids( $collection_id );

		if ( empty( $product_ids ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$added_products = 0;
		$collection_name = get_the_title( $collection_id );
		$group_key       = wp_generate_uuid4();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_purchasable() ) {
				continue;
			}

			$quantity = (int) apply_filters( 'goodie_collections_cart_quantity', 1, $product_id, $collection_id );
			$cart_item_data = array(
				'goodie_collection_id'        => $collection_id,
				'goodie_collection_name'      => $collection_name,
				'goodie_collection_group_key' => $group_key,
			);

			if ( WC()->cart->add_to_cart( $product_id, max( 1, $quantity ), 0, array(), $cart_item_data ) ) {
				++$added_products;
			}
		}

		if ( $added_products > 0 ) {
			wc_add_notice( sprintf( _n( '%d candy was added to your cart.', '%d candies were added to your cart.', $added_products, 'goodie-collections' ), $added_products ), 'success' );
		} else {
			wc_add_notice( __( 'No products from this collection could be added to the cart.', 'goodie-collections' ), 'error' );
		}

		$redirect_url = apply_filters( 'goodie_collections_cart_redirect_url', wc_get_cart_url(), $collection_id, $added_products );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Add collection details to cart item meta.
	 *
	 * @param array $item_data Existing displayed item data.
	 * @param array $cart_item Cart item array.
	 *
	 * @return array
	 */
	public function add_collection_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['goodie_collection_name'] ) ) {
			return $item_data;
		}

		$item_data[] = array(
			'key'   => __( 'Collection', 'goodie-collections' ),
			'value' => wc_clean( $cart_item['goodie_collection_name'] ),
		);

		return $item_data;
	}

	/**
	 * Append a visible collection label below cart item names.
	 *
	 * @param string $product_name Product name HTML.
	 * @param array  $cart_item    Cart item array.
	 * @param string $cart_item_key Cart item key.
	 *
	 * @return string
	 */
	public function append_collection_cart_label( $product_name, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );
		static $rendered_groups = array();

		if ( empty( $cart_item['goodie_collection_name'] ) || ( ! is_cart() && ! is_checkout() ) ) {
			return $product_name;
		}

		$group_key = isset( $cart_item['goodie_collection_group_key'] ) ? (string) $cart_item['goodie_collection_group_key'] : '';
		$heading   = '';

		if ( $group_key && ! isset( $rendered_groups[ $group_key ] ) ) {
			$rendered_groups[ $group_key ] = true;
			$heading = sprintf(
				'<div class="goodie-cart-collection-heading">%s <strong>%s</strong></div>',
				esc_html__( 'Collection:', 'goodie-collections' ),
				esc_html( $cart_item['goodie_collection_name'] )
			);
		}

		$label = sprintf(
			'<p class="goodie-cart-collection-meta">%s <strong>%s</strong></p>',
			esc_html__( 'Part of collection:', 'goodie-collections' ),
			esc_html( $cart_item['goodie_collection_name'] )
		);

		return $heading . $product_name . $label;
	}
}
