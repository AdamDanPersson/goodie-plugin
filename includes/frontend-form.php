<?php
/**
 * Frontend form rendering and submission handling.
 *
 * @package GoodieCollections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle the collection creation form.
 */
class Goodie_Collections_Frontend_Form {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'goodie_collection_form', array( $this, 'render_form_shortcode' ) );
		add_action( 'init', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Render the frontend collection form.
	 *
	 * @return string
	 */
	public function render_form_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p class="goodie-message">' . esc_html__( 'Please log in to create a candy collection.', 'goodie-collections' ) . '</p>';
		}

		$terms    = get_terms(
			array(
				'taxonomy'   => 'goodie_category',
				'hide_empty' => false,
			)
		);
		$products = wc_get_products(
			apply_filters(
				'goodie_collections_form_products_args',
				array(
					'status' => 'publish',
					'limit'  => -1,
					'orderby' => 'title',
					'order'  => 'ASC',
				)
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '<div class="goodie-notice goodie-notice--error"><p>' . esc_html__( 'Please add at least one collection category before creating collections.', 'goodie-collections' ) . '</p></div>';
		}

		if ( empty( $products ) ) {
			return '<div class="goodie-notice goodie-notice--error"><p>' . esc_html__( 'Please publish WooCommerce products before creating collections.', 'goodie-collections' ) . '</p></div>';
		}

		ob_start();
		?>
		<div class="goodie-form-wrapper">
			<?php $this->render_notices(); ?>
			<form class="goodie-collection-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>">
				<?php wp_nonce_field( 'goodie_collection_create', 'goodie_collection_nonce' ); ?>
				<input type="hidden" name="goodie_collection_action" value="create_collection" />

				<p>
					<label for="goodie_collection_name"><?php echo esc_html__( 'Collection name', 'goodie-collections' ); ?></label>
					<input id="goodie_collection_name" name="goodie_collection_name" type="text" maxlength="120" required />
				</p>

				<p>
					<label for="goodie_collection_category"><?php echo esc_html__( 'Category', 'goodie-collections' ); ?></label>
					<select id="goodie_collection_category" name="goodie_collection_category" required>
						<option value=""><?php echo esc_html__( 'Select a category', 'goodie-collections' ); ?></option>
						<?php foreach ( $terms as $term ) : ?>
							<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<fieldset class="goodie-products-fieldset">
					<legend><?php echo esc_html__( 'Pick at least two candies', 'goodie-collections' ); ?></legend>
					<p class="goodie-selection-count" data-selected-count><?php echo esc_html( sprintf( __( 'Selected products: %d', 'goodie-collections' ), 0 ) ); ?></p>
					<div class="goodie-product-grid">
						<?php foreach ( $products as $product ) : ?>
							<label class="goodie-product-option">
								<input type="checkbox" name="goodie_collection_products[]" value="<?php echo esc_attr( $product->get_id() ); ?>" />
								<span class="goodie-product-option__name"><?php echo esc_html( $product->get_name() ); ?></span>
								<span class="goodie-product-option__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>

				<p>
					<button type="submit"><?php echo esc_html__( 'Create collection', 'goodie-collections' ); ?></button>
				</p>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Process the submitted form.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( empty( $_POST['goodie_collection_action'] ) || 'create_collection' !== sanitize_key( wp_unslash( $_POST['goodie_collection_action'] ) ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to create a collection.', 'goodie-collections' ) );
		}

		check_admin_referer( 'goodie_collection_create', 'goodie_collection_nonce' );

		$title       = isset( $_POST['goodie_collection_name'] ) ? sanitize_text_field( wp_unslash( $_POST['goodie_collection_name'] ) ) : '';
		$category    = isset( $_POST['goodie_collection_category'] ) ? sanitize_title( wp_unslash( $_POST['goodie_collection_category'] ) ) : '';
		$product_ids = isset( $_POST['goodie_collection_products'] ) ? array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['goodie_collection_products'] ) ) ) ) : array();

		$redirect_url = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( empty( $title ) ) {
			$this->redirect_with_notice( $redirect_url, 'missing_name' );
		}

		if ( count( $product_ids ) < goodie_collections_get_minimum_products() ) {
			$this->redirect_with_notice( $redirect_url, 'missing_products' );
		}

		$term = get_term_by( 'slug', $category, 'goodie_category' );

		if ( ! $term || is_wp_error( $term ) ) {
			$this->redirect_with_notice( $redirect_url, 'missing_category' );
		}

		$post_id = wp_insert_post(
			apply_filters(
				'goodie_collections_insert_post_args',
				array(
					'post_type'   => 'goodie_collection',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => get_current_user_id(),
				)
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			$this->redirect_with_notice( $redirect_url, 'save_failed' );
		}

		update_post_meta( $post_id, '_goodie_collection_product_ids', $product_ids );
		wp_set_object_terms( $post_id, array( (int) $term->term_id ), 'goodie_category', false );

		do_action( 'goodie_collections_collection_created', $post_id, $product_ids, (int) $term->term_id, get_current_user_id() );

		$success_url = add_query_arg( 'goodie_collection_created', 1, get_permalink( $post_id ) );
		$success_url = apply_filters( 'goodie_collections_success_redirect_url', $success_url, $post_id );

		wp_safe_redirect( $success_url );
		exit;
	}

	/**
	 * Render frontend notices from URL parameters.
	 *
	 * @return void
	 */
	protected function render_notices() {
		$notice = isset( $_GET['goodie_notice'] ) ? sanitize_key( wp_unslash( $_GET['goodie_notice'] ) ) : '';

		if ( empty( $notice ) ) {
			return;
		}

		$messages = array(
			'missing_name'     => __( 'Please enter a collection name.', 'goodie-collections' ),
			'missing_products' => __( 'Please choose at least two different products.', 'goodie-collections' ),
			'missing_category' => __( 'Please choose a category.', 'goodie-collections' ),
			'save_failed'      => __( 'The collection could not be saved. Please try again.', 'goodie-collections' ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		?>
		<div class="goodie-notice goodie-notice--error">
			<p><?php echo esc_html( $messages[ $notice ] ); ?></p>
		</div>
		<?php
	}

	/**
	 * Redirect back to the form with an error notice.
	 *
	 * @param string $url        Target URL.
	 * @param string $notice_key Notice identifier.
	 *
	 * @return void
	 */
	protected function redirect_with_notice( $url, $notice_key ) {
		wp_safe_redirect( add_query_arg( 'goodie_notice', sanitize_key( $notice_key ), $url ) );
		exit;
	}
}
