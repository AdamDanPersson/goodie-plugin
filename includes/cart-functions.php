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

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_purchasable() ) {
				continue;
			}

			$quantity = (int) apply_filters( 'goodie_collections_cart_quantity', 1, $product_id, $collection_id );

			if ( WC()->cart->add_to_cart( $product_id, max( 1, $quantity ) ) ) {
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
}
