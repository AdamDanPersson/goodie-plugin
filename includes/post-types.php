<?php
/**
 * Post type registration and archive behavior.
 *
 * @package GoodieCollections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register collection content types and archive sorting.
 */
class Goodie_Collections_Post_Types {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		add_action( 'pre_get_posts', array( $this, 'modify_archive_query' ) );
		add_action( 'admin_init', array( $this, 'restrict_admin_editing' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_restriction_notice' ) );
	}

	/**
	 * Register the collection custom post type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Collections', 'goodie-collections' ),
			'singular_name'      => __( 'Collection', 'goodie-collections' ),
			'menu_name'          => __( 'Collections', 'goodie-collections' ),
			'name_admin_bar'     => __( 'Collection', 'goodie-collections' ),
			'add_new'            => __( 'Add New', 'goodie-collections' ),
			'add_new_item'       => __( 'Add New Collection', 'goodie-collections' ),
			'new_item'           => __( 'New Collection', 'goodie-collections' ),
			'edit_item'          => __( 'Edit Collection', 'goodie-collections' ),
			'view_item'          => __( 'View Collection', 'goodie-collections' ),
			'all_items'          => __( 'All Collections', 'goodie-collections' ),
			'search_items'       => __( 'Search Collections', 'goodie-collections' ),
			'not_found'          => __( 'No collections found.', 'goodie-collections' ),
			'not_found_in_trash' => __( 'No collections found in Trash.', 'goodie-collections' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'has_archive'        => true,
			'rewrite'            => array( 'slug' => 'collections' ),
			'show_in_menu'       => true,
			'show_in_rest'       => false,
			'supports'           => array( 'title', 'author' ),
			'menu_icon'          => 'dashicons-heart',
			'exclude_from_search' => false,
			'publicly_queryable' => true,
		);

		register_post_type( 'goodie_collection', apply_filters( 'goodie_collections_post_type_args', $args ) );
	}

	/**
	 * Register the collection category taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'              => __( 'Collection Categories', 'goodie-collections' ),
			'singular_name'     => __( 'Collection Category', 'goodie-collections' ),
			'search_items'      => __( 'Search Collection Categories', 'goodie-collections' ),
			'all_items'         => __( 'All Collection Categories', 'goodie-collections' ),
			'edit_item'         => __( 'Edit Collection Category', 'goodie-collections' ),
			'update_item'       => __( 'Update Collection Category', 'goodie-collections' ),
			'add_new_item'      => __( 'Add New Collection Category', 'goodie-collections' ),
			'new_item_name'     => __( 'New Collection Category', 'goodie-collections' ),
			'menu_name'         => __( 'Collection Categories', 'goodie-collections' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'collection-category' ),
			'show_in_rest'      => false,
		);

		register_taxonomy( 'goodie_category', array( 'goodie_collection' ), apply_filters( 'goodie_collections_taxonomy_args', $args ) );
	}

	/**
	 * Apply search, sort, and category filters to the archive.
	 *
	 * @param WP_Query $query Main query object.
	 *
	 * @return void
	 */
	public function modify_archive_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( 'goodie_collection' ) ) {
			return;
		}

		$category_slug = isset( $_GET['goodie_category'] ) ? sanitize_title( wp_unslash( $_GET['goodie_category'] ) ) : '';
		$sort          = isset( $_GET['goodie_sort'] ) ? sanitize_key( wp_unslash( $_GET['goodie_sort'] ) ) : 'latest';

		$query->set( 'post_type', 'goodie_collection' );
		$query->set( 'posts_per_page', (int) apply_filters( 'goodie_collections_archive_posts_per_page', 12 ) );

		if ( $category_slug ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'goodie_category',
						'field'    => 'slug',
						'terms'    => $category_slug,
					),
				)
			);
		}

		if ( 'alphabetical' === $sort ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		} else {
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
		}
	}

	/**
	 * Prevent collection creation and editing in WP Admin.
	 *
	 * @return void
	 */
	public function restrict_admin_editing() {
		global $pagenow;

		if ( ! in_array( $pagenow, array( 'post-new.php', 'post.php' ), true ) ) {
			return;
		}

		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';

		if ( empty( $post_type ) && ! empty( $_GET['post'] ) ) {
			$post_type = get_post_type( absint( wp_unslash( $_GET['post'] ) ) );
		}

		if ( 'goodie_collection' !== $post_type ) {
			return;
		}

		wp_safe_redirect( add_query_arg( 'goodie_collections_admin_notice', 'frontend_only', admin_url( 'edit.php?post_type=goodie_collection' ) ) );
		exit;
	}

	/**
	 * Render an admin notice after redirecting from edit screens.
	 *
	 * @return void
	 */
	public function render_admin_restriction_notice() {
		if ( empty( $_GET['goodie_collections_admin_notice'] ) || 'frontend_only' !== sanitize_key( wp_unslash( $_GET['goodie_collections_admin_notice'] ) ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><?php echo esc_html__( 'Goodie collections are created from the frontend form only.', 'goodie-collections' ); ?></p>
		</div>
		<?php
	}
}
