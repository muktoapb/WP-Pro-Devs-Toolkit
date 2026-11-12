<?php
/**
 * Built-in MCP tool groups.
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress core: posts and pages. Always available.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Group_Core extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'core';
	}

	public function label() {
		return __( 'WordPress Core', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'list_posts'  => array(
				'description' => 'List posts or pages, newest first. Supports search, status and pagination.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'post, page, any, or a custom type. Default "post".' ),
						'status'    => array( 'type' => 'string', 'description' => 'publish, draft, any, ... Default "any".' ),
						'search'    => array( 'type' => 'string' ),
						'per_page'  => array( 'type' => 'integer' ),
						'page'      => array( 'type' => 'integer' ),
					),
				),
				'handler'     => array( $this, 'list_posts' ),
			),
			'get_post'    => array(
				'description' => 'Get a single post or page by ID, including its content.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_post' ),
			),
			'create_post' => array(
				'description' => 'Create a new post or page.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
						'status'    => array( 'type' => 'string', 'description' => 'publish, draft, pending or private. Default "draft".' ),
						'post_type' => array( 'type' => 'string', 'description' => 'post or page (or custom type). Default "post".' ),
						'excerpt'   => array( 'type' => 'string' ),
						'slug'      => array( 'type' => 'string' ),
						'featured_media' => array( 'type' => 'integer', 'description' => 'Attachment ID for the featured image (0 to remove).' ),
						'categories'     => array( 'type' => 'array', 'description' => 'Category names or IDs.' ),
						'tags'           => array( 'type' => 'array', 'description' => 'Tag names or IDs.' ),
						'meta'           => array( 'type' => 'object', 'description' => 'Custom field key/value pairs.' ),
					),
				),
				'handler'     => array( $this, 'create_post' ),
			),
			'update_post' => array(
				'description' => 'Update an existing post or page. Only the fields you pass are changed.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
						'excerpt' => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'featured_media' => array( 'type' => 'integer' ),
						'categories'     => array( 'type' => 'array' ),
						'tags'           => array( 'type' => 'array' ),
						'meta'           => array( 'type' => 'object' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'update_post' ),
			),
			'delete_post' => array(
				'description' => 'Trash a post or page (set force=true to delete permanently).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'force' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_post' ),
			),
			'set_featured_image' => array(
				'description' => 'Set (or clear with attachment_id=0) the featured image of a post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'            => array( 'type' => 'integer' ),
						'attachment_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'id', 'attachment_id' ),
				),
				'handler'     => array( $this, 'set_featured_image' ),
			),
			'list_terms'         => array(
				'description' => 'List terms in a taxonomy (e.g. category, post_tag).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array( 'type' => 'string' ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'taxonomy' ),
				),
				'handler'     => array( $this, 'list_terms' ),
			),
			'create_term'        => array(
				'description' => 'Create a term (e.g. a category or tag).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array( 'type' => 'string' ),
						'name'     => array( 'type' => 'string' ),
						'parent'   => array( 'type' => 'integer' ),
					),
					'required'   => array( 'taxonomy', 'name' ),
				),
				'handler'     => array( $this, 'create_term' ),
			),
			'set_post_terms'     => array(
				'description' => 'Assign terms (names or IDs) to a post in a taxonomy. Set append=true to add without removing existing.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'taxonomy' => array( 'type' => 'string' ),
						'terms'    => array( 'type' => 'array' ),
						'append'   => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id', 'taxonomy', 'terms' ),
				),
				'handler'     => array( $this, 'set_post_terms' ),
			),
		);
	}

	public function set_featured_image( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		$att = isset( $args['attachment_id'] ) ? absint( $args['attachment_id'] ) : 0;
		if ( $att ) {
			set_post_thumbnail( $id, $att );
		} else {
			delete_post_thumbnail( $id );
		}

		return array( 'id' => $id, 'featured_media' => $att );
	}

	public function list_terms( $args ) {
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new RuntimeException( 'Unknown taxonomy: ' . $taxonomy );
		}
		$query = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => isset( $args['per_page'] ) ? min( 200, max( 1, absint( $args['per_page'] ) ) ) : 100,
		);
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = sanitize_text_field( $args['search'] );
		}

		$terms = array();
		foreach ( get_terms( $query ) as $term ) {
			$terms[] = array(
				'id'     => (int) $term->term_id,
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
				'count'  => (int) $term->count,
			);
		}

		return array( 'taxonomy' => $taxonomy, 'terms' => $terms );
	}

	public function create_term( $args ) {
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
		$name     = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new RuntimeException( 'Unknown taxonomy: ' . $taxonomy );
		}
		if ( '' === $name ) {
			throw new InvalidArgumentException( '"name" is required.' );
		}

		$result = wp_insert_term( $name, $taxonomy, array( 'parent' => isset( $args['parent'] ) ? absint( $args['parent'] ) : 0 ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'id' => (int) $result['term_id'], 'taxonomy' => $taxonomy, 'name' => $name );
	}

	public function set_post_terms( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new RuntimeException( 'Unknown taxonomy: ' . $taxonomy );
		}
		$terms  = isset( $args['terms'] ) && is_array( $args['terms'] ) ? $args['terms'] : array();
		$append = ! empty( $args['append'] );

		$result = wp_set_post_terms( $id, $this->resolve_term_ids( $terms, $taxonomy ), $taxonomy, $append );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'id' => $id, 'taxonomy' => $taxonomy, 'terms' => array_map( 'intval', (array) $result ) );
	}

	public function list_posts( $args ) {
		$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
		$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
		$per_page  = isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 20;
		$paged     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;

		$query_args = array(
			'post_type'      => 'any' === $post_type ? 'any' : $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( ! empty( $args['search'] ) ) {
			$query_args['s'] = sanitize_text_field( $args['search'] );
		}

		$query = new WP_Query( $query_args );
		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'id'       => $post->ID,
				'title'    => get_the_title( $post ),
				'status'   => $post->post_status,
				'type'     => $post->post_type,
				'modified' => $post->post_modified_gmt,
				'link'     => get_permalink( $post ),
			);
		}

		return array(
			'total'    => (int) $query->found_posts,
			'page'     => $paged,
			'per_page' => $per_page,
			'posts'    => $posts,
		);
	}

	public function get_post( $args ) {
		$id   = $this->require_id( $args );
		$post = get_post( $id );
		if ( ! $post ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}

		return array(
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'status'        => $post->post_status,
			'type'          => $post->post_type,
			'slug'          => $post->post_name,
			'link'          => get_permalink( $post ),
			'content'       => $post->post_content,
			'excerpt'       => $post->post_excerpt,
			'author'        => (int) $post->post_author,
			'modified'      => $post->post_modified_gmt,
			'has_elementor' => 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ),
		);
	}

	public function create_post( $args ) {
		$postarr = array(
			'post_title'   => isset( $args['title'] ) ? (string) $args['title'] : '',
			'post_content' => isset( $args['content'] ) ? (string) $args['content'] : '',
			'post_status'  => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft',
			'post_type'    => isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post',
		);
		if ( isset( $args['excerpt'] ) ) {
			$postarr['post_excerpt'] = (string) $args['excerpt'];
		}
		if ( ! empty( $args['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $args['slug'] );
		}

		$id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$this->apply_extras( $id, $args );

		return array(
			'id'        => $id,
			'status'    => get_post_status( $id ),
			'link'      => get_permalink( $id ),
			'edit_link' => get_edit_post_link( $id, 'raw' ),
		);
	}

	/**
	 * Applies optional post extras: featured image, categories, tags and meta.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    Tool arguments.
	 * @return void
	 */
	private function apply_extras( $post_id, $args ) {
		if ( array_key_exists( 'featured_media', $args ) ) {
			$att = absint( $args['featured_media'] );
			if ( $att ) {
				set_post_thumbnail( $post_id, $att );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}
		if ( isset( $args['categories'] ) && is_array( $args['categories'] ) ) {
			wp_set_post_terms( $post_id, $this->resolve_term_ids( $args['categories'], 'category' ), 'category', false );
		}
		if ( isset( $args['tags'] ) && is_array( $args['tags'] ) ) {
			wp_set_post_terms( $post_id, $this->resolve_term_ids( $args['tags'], 'post_tag' ), 'post_tag', false );
		}
		if ( isset( $args['meta'] ) && is_array( $args['meta'] ) ) {
			foreach ( $args['meta'] as $key => $value ) {
				update_post_meta( $post_id, (string) $key, $value );
			}
		}
	}

	/**
	 * Resolves a list of term names or IDs to term IDs, creating missing terms.
	 *
	 * Hierarchical taxonomies (e.g. category) require IDs, so names must be
	 * resolved up front rather than passed to wp_set_post_terms().
	 *
	 * @param array  $terms    Term names or IDs.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	private function resolve_term_ids( $terms, $taxonomy ) {
		$ids = array();
		foreach ( (array) $terms as $term ) {
			if ( is_numeric( $term ) ) {
				$ids[] = (int) $term;
				continue;
			}
			$name     = (string) $term;
			$existing = get_term_by( 'name', $name, $taxonomy );
			if ( $existing ) {
				$ids[] = (int) $existing->term_id;
			} else {
				$new = wp_insert_term( $name, $taxonomy );
				if ( ! is_wp_error( $new ) ) {
					$ids[] = (int) $new['term_id'];
				}
			}
		}

		return $ids;
	}

	public function update_post( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}

		$postarr = array( 'ID' => $id );
		if ( isset( $args['title'] ) ) {
			$postarr['post_title'] = (string) $args['title'];
		}
		if ( isset( $args['content'] ) ) {
			$postarr['post_content'] = (string) $args['content'];
		}
		if ( isset( $args['status'] ) ) {
			$postarr['post_status'] = sanitize_key( $args['status'] );
		}
		if ( isset( $args['excerpt'] ) ) {
			$postarr['post_excerpt'] = (string) $args['excerpt'];
		}
		if ( ! empty( $args['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $args['slug'] );
		}

		$result = wp_update_post( $postarr, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->apply_extras( $id, $args );

		return array(
			'id'     => $id,
			'status' => get_post_status( $id ),
			'link'   => get_permalink( $id ),
		);
	}

	public function delete_post( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}

		$force  = ! empty( $args['force'] );
		$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
		if ( ! $result ) {
			throw new RuntimeException( sprintf( 'Failed to delete post %d.', $id ) );
		}

		return array(
			'id'      => $id,
			'deleted' => true,
			'forced'  => $force,
		);
	}
}

/**
 * Elementor builder tools.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Group_Elementor extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'elementor';
	}

	public function label() {
		return __( 'Elementor', 'wp-pro-devs-toolkit' );
	}

	public function requires() {
		return 'Elementor';
	}

	public function is_available() {
		return class_exists( '\\Elementor\\Plugin' );
	}

	public function tools() {
		return array(
			'get_elementor_data'       => array(
				'description' => 'Get the Elementor builder data (decoded JSON) for a page.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_data' ),
			),
			'update_elementor_data'    => array(
				'description' => 'Replace the Elementor builder data for a page and regenerate its CSS. "data" is the Elementor elements tree (array or JSON string).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'data' => array( 'type' => array( 'array', 'string' ), 'description' => 'Elementor elements tree.' ),
					),
					'required'   => array( 'id', 'data' ),
				),
				'handler'     => array( $this, 'update_data' ),
			),
			'regenerate_elementor_css' => array(
				'description' => 'Regenerate Elementor CSS for one page (pass id) or clear the whole CSS cache (omit id).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
				),
				'handler'     => array( $this, 'regenerate_css' ),
			),
		);
	}

	public function get_data( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}

		$raw  = get_post_meta( $id, '_elementor_data', true );
		$data = ( is_string( $raw ) && '' !== $raw ) ? json_decode( $raw, true ) : array();

		return array(
			'id'        => $id,
			'edit_mode' => get_post_meta( $id, '_elementor_edit_mode', true ),
			'data'      => $data,
		);
	}

	public function update_data( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}
		if ( ! isset( $args['data'] ) ) {
			throw new InvalidArgumentException( '"data" is required.' );
		}

		$data = $args['data'];
		if ( is_array( $data ) ) {
			$json = wp_json_encode( $data );
		} else {
			$json = (string) $data;
			json_decode( $json );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new InvalidArgumentException( '"data" must be a valid JSON string or array.' );
			}
		}

		update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		}

		$this->regenerate( $id );

		return array(
			'id'      => $id,
			'updated' => true,
		);
	}

	public function regenerate_css( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;
		$this->regenerate( $id );

		return array(
			'regenerated' => true,
			'scope'       => $id ? $id : 'all',
		);
	}

	private function regenerate( $id ) {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;

		if ( $id && class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
			try {
				\Elementor\Core\Files\CSS\Post::create( $id )->update();
				return;
			} catch ( \Throwable $e ) {
				// Fall through to a full cache clear.
			}
		}
		if ( isset( $plugin->files_manager ) ) {
			$plugin->files_manager->clear_cache();
		}
	}
}

/**
 * Advanced Custom Fields (ACF).
 *
 * Targets ACF. Secure Custom Fields (the ACF fork maintained on WordPress.org)
 * shares the exact same get_field()/update_field() API, so it works here
 * automatically with no extra code.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Group_ACF extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'acf';
	}

	public function label() {
		return __( 'Advanced Custom Fields (ACF)', 'wp-pro-devs-toolkit' );
	}

	public function requires() {
		return 'Advanced Custom Fields (ACF)';
	}

	public function is_available() {
		return function_exists( 'get_field' ) && function_exists( 'update_field' );
	}

	public function tools() {
		return array(
			'acf_get_fields'   => array(
				'description' => 'Get all custom field values for a post (Secure Custom Fields / ACF).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_fields' ),
			),
			'acf_get_field'    => array(
				'description' => 'Get one custom field value by name or key for a post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'selector' => array( 'type' => 'string', 'description' => 'Field name or key.' ),
					),
					'required'   => array( 'id', 'selector' ),
				),
				'handler'     => array( $this, 'get_one' ),
			),
			'acf_update_field' => array(
				'description' => 'Update one custom field value for a post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'selector' => array( 'type' => 'string', 'description' => 'Field name or key.' ),
						'value'    => array( 'description' => 'New value (string, number, boolean or array).' ),
					),
					'required'   => array( 'id', 'selector', 'value' ),
				),
				'handler'     => array( $this, 'update_one' ),
			),
		);
	}

	public function get_fields( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_post( $id ) ) {
			throw new RuntimeException( sprintf( 'Post %d not found.', $id ) );
		}

		$fields = get_fields( $id );

		return array(
			'id'     => $id,
			'fields' => is_array( $fields ) ? $fields : array(),
		);
	}

	public function get_one( $args ) {
		$id       = $this->require_id( $args );
		$selector = isset( $args['selector'] ) ? (string) $args['selector'] : '';
		if ( '' === $selector ) {
			throw new InvalidArgumentException( '"selector" is required.' );
		}

		return array(
			'id'       => $id,
			'selector' => $selector,
			'value'    => get_field( $selector, $id ),
		);
	}

	public function update_one( $args ) {
		$id       = $this->require_id( $args );
		$selector = isset( $args['selector'] ) ? (string) $args['selector'] : '';
		if ( '' === $selector ) {
			throw new InvalidArgumentException( '"selector" is required.' );
		}
		if ( ! array_key_exists( 'value', $args ) ) {
			throw new InvalidArgumentException( '"value" is required.' );
		}

		$ok = update_field( $selector, $args['value'], $id );

		return array(
			'id'       => $id,
			'selector' => $selector,
			'updated'  => (bool) $ok,
		);
	}
}

/**
 * Contact Form 7.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Group_CF7 extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'cf7';
	}

	public function label() {
		return __( 'Contact Form 7', 'wp-pro-devs-toolkit' );
	}

	public function requires() {
		return 'Contact Form 7';
	}

	public function is_available() {
		return class_exists( 'WPCF7_ContactForm' );
	}

	public function tools() {
		return array(
			'cf7_list_forms' => array(
				'description' => 'List all Contact Form 7 forms.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
				'handler'     => array( $this, 'list_forms' ),
			),
			'cf7_get_form'   => array(
				'description' => 'Get a Contact Form 7 form, including its form template and mail settings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_form' ),
			),
			'cf7_save_form'  => array(
				'description' => 'Create or update a Contact Form 7 form. Omit "id" to create. "mail"/"messages" are objects; "form"/"additional_settings" are strings.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'                  => array( 'type' => 'integer' ),
						'title'               => array( 'type' => 'string' ),
						'form'                => array( 'type' => 'string', 'description' => 'The form template markup.' ),
						'mail'                => array( 'type' => 'object' ),
						'mail_2'              => array( 'type' => 'object' ),
						'messages'            => array( 'type' => 'object' ),
						'additional_settings' => array( 'type' => 'string' ),
					),
				),
				'handler'     => array( $this, 'save_form' ),
			),
		);
	}

	public function save_form( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		if ( $id ) {
			$form = WPCF7_ContactForm::get_instance( $id );
			if ( ! $form ) {
				throw new RuntimeException( sprintf( 'Contact Form 7 form %d not found.', $id ) );
			}
		} else {
			$form = WPCF7_ContactForm::get_template();
		}

		if ( isset( $args['title'] ) ) {
			$form->set_title( sanitize_text_field( $args['title'] ) );
		}

		$props = array();
		foreach ( array( 'form', 'mail', 'mail_2', 'messages', 'additional_settings' ) as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$props[ $key ] = $args[ $key ];
			}
		}
		if ( $props ) {
			$form->set_properties( $props );
		}

		$saved_id = $form->save();
		if ( ! $saved_id ) {
			throw new RuntimeException( 'Failed to save the Contact Form 7 form.' );
		}

		return array(
			'id'      => (int) $saved_id,
			'title'   => $form->title(),
			'created' => 0 === $id,
		);
	}

	public function list_forms( $args ) {
		$posts = get_posts(
			array(
				'post_type'   => 'wpcf7_contact_form',
				'post_status' => 'publish',
				'numberposts' => 100,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);

		$forms = array();
		foreach ( $posts as $post ) {
			$forms[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
			);
		}

		return array( 'forms' => $forms );
	}

	public function get_form( $args ) {
		$id   = $this->require_id( $args );
		$form = WPCF7_ContactForm::get_instance( $id );
		if ( ! $form ) {
			throw new RuntimeException( sprintf( 'Contact Form 7 form %d not found.', $id ) );
		}

		return array(
			'id'    => $id,
			'title' => $form->title(),
			'form'  => $form->prop( 'form' ),
			'mail'  => $form->prop( 'mail' ),
		);
	}
}

/**
 * Gravity Forms.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Group_GravityForms extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'gravityforms';
	}

	public function label() {
		return __( 'Gravity Forms', 'wp-pro-devs-toolkit' );
	}

	public function requires() {
		return 'Gravity Forms';
	}

	public function is_available() {
		return class_exists( 'GFAPI' );
	}

	public function tools() {
		return array(
			'gf_list_forms'   => array(
				'description' => 'List all Gravity Forms.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
				'handler'     => array( $this, 'list_forms' ),
			),
			'gf_get_form'     => array(
				'description' => 'Get a Gravity Forms form definition (fields, settings) by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_form' ),
			),
			'gf_list_entries' => array(
				'description' => 'List entries (submissions) for a Gravity Forms form.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'form_id'   => array( 'type' => 'integer' ),
						'page_size' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'form_id' ),
				),
				'handler'     => array( $this, 'list_entries' ),
			),
			'gf_get_entry'    => array(
				'description' => 'Get a single Gravity Forms entry by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_entry' ),
			),
			'gf_save_form'    => array(
				'description' => 'Create (omit id) or update a Gravity Forms form. "form" is the full form definition (title + fields).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'integer' ),
						'form' => array( 'type' => 'object' ),
					),
					'required'   => array( 'form' ),
				),
				'handler'     => array( $this, 'save_form' ),
			),
			'gf_delete_form'  => array(
				'description' => 'Delete a Gravity Forms form by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_form' ),
			),
		);
	}

	public function save_form( $args ) {
		$form = isset( $args['form'] ) && is_array( $args['form'] ) ? $args['form'] : null;
		if ( ! $form ) {
			throw new InvalidArgumentException( '"form" (object) is required.' );
		}
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		if ( $id ) {
			$form['id'] = $id;
			$result     = GFAPI::update_form( $form, $id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array( 'id' => $id, 'created' => false );
		}

		$new_id = GFAPI::add_form( $form );
		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		return array( 'id' => (int) $new_id, 'created' => true );
	}

	public function delete_form( $args ) {
		$id     = $this->require_id( $args );
		$result = GFAPI::delete_form( $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'id' => $id, 'deleted' => (bool) $result );
	}

	public function list_forms( $args ) {
		$forms  = GFAPI::get_forms();
		$result = array();
		foreach ( (array) $forms as $form ) {
			$result[] = array(
				'id'        => isset( $form['id'] ) ? (int) $form['id'] : 0,
				'title'     => isset( $form['title'] ) ? $form['title'] : '',
				'is_active' => ! empty( $form['is_active'] ),
				'entries'   => class_exists( 'GFAPI' ) && isset( $form['id'] ) ? (int) GFAPI::count_entries( $form['id'] ) : null,
			);
		}

		return array( 'forms' => $result );
	}

	public function get_form( $args ) {
		$id   = $this->require_id( $args );
		$form = GFAPI::get_form( $id );
		if ( ! $form ) {
			throw new RuntimeException( sprintf( 'Gravity Forms form %d not found.', $id ) );
		}

		return $form;
	}

	public function list_entries( $args ) {
		$form_id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $form_id ) {
			throw new InvalidArgumentException( 'A valid "form_id" is required.' );
		}
		$page_size = isset( $args['page_size'] ) ? min( 100, max( 1, absint( $args['page_size'] ) ) ) : 20;

		$entries = GFAPI::get_entries( $form_id, array(), null, array( 'page_size' => $page_size ) );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}

		return array(
			'form_id' => $form_id,
			'entries' => $entries,
		);
	}

	public function get_entry( $args ) {
		$id    = $this->require_id( $args );
		$entry = GFAPI::get_entry( $id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}

		return $entry;
	}
}

/**
 * Media library.
 *
 * @since 1.4.0
 */
class WPPDT_MCP_Group_Media extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'media';
	}

	public function label() {
		return __( 'Media Library', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'upload_media' => array(
				'description' => 'Download an image/file from a URL into the media library. Optionally attach it to a post and set alt text.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'url'      => array( 'type' => 'string' ),
						'filename' => array( 'type' => 'string' ),
						'alt'      => array( 'type' => 'string' ),
						'post_id'  => array( 'type' => 'integer', 'description' => 'Attach the upload to this post.' ),
					),
					'required'   => array( 'url' ),
				),
				'handler'     => array( $this, 'upload_media' ),
			),
			'list_media'   => array(
				'description' => 'List media library attachments.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'handler'     => array( $this, 'list_media' ),
			),
			'get_media'    => array(
				'description' => 'Get a media attachment by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_media' ),
			),
			'delete_media' => array(
				'description' => 'Delete a media attachment.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'force' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_media' ),
			),
		);
	}

	public function upload_media( $args ) {
		$url = isset( $args['url'] ) ? esc_url_raw( $args['url'] ) : '';
		if ( '' === $url ) {
			throw new InvalidArgumentException( '"url" is required.' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$name = ! empty( $args['filename'] ) ? sanitize_file_name( $args['filename'] ) : basename( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name ) {
			$name = 'upload';
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		$id      = media_handle_sideload( $file_array, $post_id );

		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return $id;
		}

		if ( ! empty( $args['alt'] ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt'] ) );
		}

		return array(
			'id'  => (int) $id,
			'url' => wp_get_attachment_url( $id ),
		);
	}

	public function list_media( $args ) {
		$query = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 20,
			'paged'          => isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1,
		);
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = sanitize_text_field( $args['search'] );
		}

		$items = array();
		foreach ( get_posts( $query ) as $att ) {
			$items[] = array(
				'id'        => $att->ID,
				'title'     => $att->post_title,
				'mime_type' => $att->post_mime_type,
				'url'       => wp_get_attachment_url( $att->ID ),
			);
		}

		return array( 'media' => $items );
	}

	public function get_media( $args ) {
		$id  = $this->require_id( $args );
		$att = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			throw new RuntimeException( 'Attachment not found.' );
		}

		return array(
			'id'        => $att->ID,
			'title'     => $att->post_title,
			'mime_type' => $att->post_mime_type,
			'url'       => wp_get_attachment_url( $id ),
			'alt'       => get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'parent'    => (int) $att->post_parent,
		);
	}

	public function delete_media( $args ) {
		$id  = $this->require_id( $args );
		$att = get_post( $id );
		if ( ! $att || 'attachment' !== $att->post_type ) {
			throw new RuntimeException( 'Attachment not found.' );
		}

		$result = wp_delete_attachment( $id, ! empty( $args['force'] ) );

		return array( 'id' => $id, 'deleted' => (bool) $result );
	}
}

/**
 * WooCommerce products & orders.
 *
 * @since 1.4.0
 */
class WPPDT_MCP_Group_WooCommerce extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'woocommerce';
	}

	public function label() {
		return __( 'WooCommerce', 'wp-pro-devs-toolkit' );
	}

	public function requires() {
		return 'WooCommerce';
	}

	public function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
	}

	public function tools() {
		return array(
			'wc_list_products'       => array(
				'description' => 'List WooCommerce products.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string' ),
						'status'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'handler'     => array( $this, 'list_products' ),
			),
			'wc_get_product'         => array(
				'description' => 'Get a WooCommerce product by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_product' ),
			),
			'wc_save_product'        => array(
				'description' => 'Create (omit id) or update a simple WooCommerce product.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => array( 'type' => 'integer' ),
						'name'              => array( 'type' => 'string' ),
						'status'            => array( 'type' => 'string', 'description' => 'publish, draft, pending or private.' ),
						'regular_price'     => array( 'type' => 'string' ),
						'sale_price'        => array( 'type' => 'string' ),
						'sku'               => array( 'type' => 'string' ),
						'description'       => array( 'type' => 'string' ),
						'short_description' => array( 'type' => 'string' ),
						'manage_stock'      => array( 'type' => 'boolean' ),
						'stock_quantity'    => array( 'type' => 'integer' ),
						'featured_media'    => array( 'type' => 'integer', 'description' => 'Attachment ID for the product image.' ),
					),
				),
				'handler'     => array( $this, 'save_product' ),
			),
			'wc_delete_product'      => array(
				'description' => 'Delete a WooCommerce product (force=true to skip trash).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'force' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_product' ),
			),
			'wc_list_orders'         => array(
				'description' => 'List WooCommerce orders.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array( 'type' => 'string', 'description' => 'e.g. processing, completed, on-hold.' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'handler'     => array( $this, 'list_orders' ),
			),
			'wc_get_order'           => array(
				'description' => 'Get a WooCommerce order by ID, including line items and totals.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_order' ),
			),
			'wc_update_order_status' => array(
				'description' => 'Update a WooCommerce order status (e.g. processing, completed, cancelled, refunded).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'     => array( 'type' => 'integer' ),
						'status' => array( 'type' => 'string' ),
						'note'   => array( 'type' => 'string' ),
					),
					'required'   => array( 'id', 'status' ),
				),
				'handler'     => array( $this, 'update_order_status' ),
			),
		);
	}

	private function product_summary( $product ) {
		return array(
			'id'             => $product->get_id(),
			'name'           => $product->get_name(),
			'type'           => $product->get_type(),
			'status'         => $product->get_status(),
			'sku'            => $product->get_sku(),
			'price'          => $product->get_price(),
			'regular_price'  => $product->get_regular_price(),
			'sale_price'     => $product->get_sale_price(),
			'stock_status'   => $product->get_stock_status(),
			'stock_quantity' => $product->get_stock_quantity(),
			'permalink'      => $product->get_permalink(),
		);
	}

	public function list_products( $args ) {
		$query = array(
			'limit'    => isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 20,
			'page'     => isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1,
			'paginate' => false,
		);
		if ( ! empty( $args['status'] ) ) {
			$query['status'] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$query['s'] = sanitize_text_field( $args['search'] );
		}

		$products = array();
		foreach ( wc_get_products( $query ) as $product ) {
			$products[] = $this->product_summary( $product );
		}

		return array( 'products' => $products );
	}

	public function get_product( $args ) {
		$id      = $this->require_id( $args );
		$product = wc_get_product( $id );
		if ( ! $product ) {
			throw new RuntimeException( sprintf( 'Product %d not found.', $id ) );
		}

		$data                      = $this->product_summary( $product );
		$data['description']       = $product->get_description();
		$data['short_description'] = $product->get_short_description();
		$data['categories']        = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'names' ) );

		return $data;
	}

	public function save_product( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		if ( $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				throw new RuntimeException( sprintf( 'Product %d not found.', $id ) );
			}
		} else {
			$product = new WC_Product_Simple();
		}

		if ( isset( $args['name'] ) ) {
			$product->set_name( sanitize_text_field( $args['name'] ) );
		}
		if ( isset( $args['status'] ) ) {
			$product->set_status( sanitize_key( $args['status'] ) );
		}
		if ( isset( $args['regular_price'] ) ) {
			$product->set_regular_price( (string) $args['regular_price'] );
		}
		if ( isset( $args['sale_price'] ) ) {
			$product->set_sale_price( (string) $args['sale_price'] );
		}
		if ( isset( $args['sku'] ) ) {
			$product->set_sku( sanitize_text_field( $args['sku'] ) );
		}
		if ( isset( $args['description'] ) ) {
			$product->set_description( (string) $args['description'] );
		}
		if ( isset( $args['short_description'] ) ) {
			$product->set_short_description( (string) $args['short_description'] );
		}
		if ( isset( $args['manage_stock'] ) ) {
			$product->set_manage_stock( (bool) $args['manage_stock'] );
		}
		if ( isset( $args['stock_quantity'] ) ) {
			$product->set_stock_quantity( absint( $args['stock_quantity'] ) );
		}
		if ( isset( $args['featured_media'] ) ) {
			$product->set_image_id( absint( $args['featured_media'] ) );
		}

		$saved_id = $product->save();
		if ( ! $saved_id ) {
			throw new RuntimeException( 'Failed to save the product.' );
		}

		return array(
			'id'      => (int) $saved_id,
			'created' => 0 === $id,
			'permalink' => get_permalink( $saved_id ),
		);
	}

	public function delete_product( $args ) {
		$id      = $this->require_id( $args );
		$product = wc_get_product( $id );
		if ( ! $product ) {
			throw new RuntimeException( sprintf( 'Product %d not found.', $id ) );
		}

		$result = $product->delete( ! empty( $args['force'] ) );

		return array( 'id' => $id, 'deleted' => (bool) $result );
	}

	public function list_orders( $args ) {
		$query = array(
			'limit'    => isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 20,
			'page'     => isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1,
			'paginate' => false,
		);
		if ( ! empty( $args['status'] ) ) {
			$query['status'] = sanitize_key( $args['status'] );
		}

		$orders = array();
		foreach ( wc_get_orders( $query ) as $order ) {
			$orders[] = array(
				'id'       => $order->get_id(),
				'status'   => $order->get_status(),
				'total'    => $order->get_total(),
				'currency' => $order->get_currency(),
				'customer' => $order->get_billing_email(),
				'date'     => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null,
			);
		}

		return array( 'orders' => $orders );
	}

	public function get_order( $args ) {
		$id    = $this->require_id( $args );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			throw new RuntimeException( sprintf( 'Order %d not found.', $id ) );
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}

		return array(
			'id'       => $order->get_id(),
			'status'   => $order->get_status(),
			'total'    => $order->get_total(),
			'currency' => $order->get_currency(),
			'billing'  => array(
				'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			),
			'items'    => $items,
		);
	}

	public function update_order_status( $args ) {
		$id     = $this->require_id( $args );
		$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
		if ( '' === $status ) {
			throw new InvalidArgumentException( '"status" is required.' );
		}
		$order = wc_get_order( $id );
		if ( ! $order ) {
			throw new RuntimeException( sprintf( 'Order %d not found.', $id ) );
		}

		$note = isset( $args['note'] ) ? sanitize_text_field( $args['note'] ) : '';
		$order->update_status( $status, $note );

		return array( 'id' => $id, 'status' => $order->get_status() );
	}
}

/**
 * Navigation menus.
 *
 * @since 1.5.0
 */
class WPPDT_MCP_Group_Menus extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'menus';
	}

	public function label() {
		return __( 'Navigation Menus', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'list_menus'          => array(
				'description' => 'List navigation menus and the theme menu locations.',
				'inputSchema' => array( 'type' => 'object', 'properties' => new stdClass() ),
				'handler'     => array( $this, 'list_menus' ),
			),
			'get_menu'            => array(
				'description' => 'Get a navigation menu and its items by menu ID or slug.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'menu' => array( 'type' => array( 'integer', 'string' ) ) ),
					'required'   => array( 'menu' ),
				),
				'handler'     => array( $this, 'get_menu' ),
			),
			'create_menu'         => array(
				'description' => 'Create a navigation menu.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'name' => array( 'type' => 'string' ) ),
					'required'   => array( 'name' ),
				),
				'handler'     => array( $this, 'create_menu' ),
			),
			'add_menu_item'       => array(
				'description' => 'Add an item to a menu. Provide a custom "url"+"title", or link to content with "object_id"+"object" (e.g. page) + "type" (post_type/taxonomy).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'menu_id'   => array( 'type' => 'integer' ),
						'title'     => array( 'type' => 'string' ),
						'url'       => array( 'type' => 'string' ),
						'object_id' => array( 'type' => 'integer' ),
						'object'    => array( 'type' => 'string', 'description' => 'e.g. page, post, category.' ),
						'type'      => array( 'type' => 'string', 'description' => 'post_type, taxonomy or custom.' ),
						'parent'    => array( 'type' => 'integer' ),
					),
					'required'   => array( 'menu_id' ),
				),
				'handler'     => array( $this, 'add_menu_item' ),
			),
			'delete_menu_item'    => array(
				'description' => 'Delete a menu item by its ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_menu_item' ),
			),
			'delete_menu'         => array(
				'description' => 'Delete a navigation menu by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_menu' ),
			),
			'assign_menu_location' => array(
				'description' => 'Assign a menu to a theme menu location.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'menu_id'  => array( 'type' => 'integer' ),
						'location' => array( 'type' => 'string' ),
					),
					'required'   => array( 'menu_id', 'location' ),
				),
				'handler'     => array( $this, 'assign_location' ),
			),
		);
	}

	public function list_menus( $args ) {
		$menus = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$menus[] = array(
				'id'    => (int) $menu->term_id,
				'name'  => $menu->name,
				'slug'  => $menu->slug,
				'count' => (int) $menu->count,
			);
		}

		return array(
			'menus'     => $menus,
			'locations' => get_registered_nav_menus(),
			'assigned'  => get_nav_menu_locations(),
		);
	}

	public function get_menu( $args ) {
		$menu   = isset( $args['menu'] ) ? $args['menu'] : '';
		$object = wp_get_nav_menu_object( is_numeric( $menu ) ? (int) $menu : $menu );
		if ( ! $object ) {
			throw new RuntimeException( 'Menu not found.' );
		}

		$items = array();
		foreach ( (array) wp_get_nav_menu_items( $object->term_id ) as $item ) {
			$items[] = array(
				'id'        => (int) $item->ID,
				'title'     => $item->title,
				'url'       => $item->url,
				'type'      => $item->type,
				'object'    => $item->object,
				'object_id' => (int) $item->object_id,
				'parent'    => (int) $item->menu_item_parent,
				'order'     => (int) $item->menu_order,
			);
		}

		return array(
			'id'    => (int) $object->term_id,
			'name'  => $object->name,
			'items' => $items,
		);
	}

	public function create_menu( $args ) {
		$name = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		if ( '' === $name ) {
			throw new InvalidArgumentException( '"name" is required.' );
		}
		$id = wp_create_nav_menu( $name );
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		return array( 'id' => (int) $id, 'name' => $name );
	}

	public function add_menu_item( $args ) {
		$menu_id = isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0;
		if ( ! $menu_id || ! wp_get_nav_menu_object( $menu_id ) ) {
			throw new RuntimeException( 'Menu not found.' );
		}

		$data = array(
			'menu-item-title'  => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '',
			'menu-item-status' => 'publish',
		);
		if ( ! empty( $args['url'] ) ) {
			$data['menu-item-url']  = esc_url_raw( $args['url'] );
			$data['menu-item-type'] = 'custom';
		}
		if ( ! empty( $args['object_id'] ) ) {
			$data['menu-item-object-id'] = absint( $args['object_id'] );
			$data['menu-item-object']    = isset( $args['object'] ) ? sanitize_key( $args['object'] ) : 'page';
			$data['menu-item-type']      = isset( $args['type'] ) ? sanitize_key( $args['type'] ) : 'post_type';
		}
		if ( ! empty( $args['parent'] ) ) {
			$data['menu-item-parent-id'] = absint( $args['parent'] );
		}

		$item_id = wp_update_nav_menu_item( $menu_id, 0, $data );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		return array( 'id' => (int) $item_id, 'menu_id' => $menu_id );
	}

	public function delete_menu_item( $args ) {
		$id = $this->require_id( $args );
		$ok = is_nav_menu_item( $id ) ? wp_delete_post( $id, true ) : false;
		if ( ! $ok ) {
			throw new RuntimeException( 'Menu item not found.' );
		}

		return array( 'id' => $id, 'deleted' => true );
	}

	public function delete_menu( $args ) {
		$id     = $this->require_id( $args );
		$result = wp_delete_nav_menu( $id );
		if ( is_wp_error( $result ) || ! $result ) {
			throw new RuntimeException( 'Could not delete the menu.' );
		}

		return array( 'id' => $id, 'deleted' => true );
	}

	public function assign_location( $args ) {
		$menu_id  = isset( $args['menu_id'] ) ? absint( $args['menu_id'] ) : 0;
		$location = isset( $args['location'] ) ? sanitize_key( $args['location'] ) : '';
		if ( ! $menu_id || '' === $location ) {
			throw new InvalidArgumentException( '"menu_id" and "location" are required.' );
		}

		$locations              = get_nav_menu_locations();
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		return array( 'menu_id' => $menu_id, 'location' => $location );
	}
}

/**
 * Comments.
 *
 * @since 1.5.0
 */
class WPPDT_MCP_Group_Comments extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'comments';
	}

	public function label() {
		return __( 'Comments', 'wp-pro-devs-toolkit' );
	}

	public function is_available() {
		return true;
	}

	public function tools() {
		return array(
			'list_comments'      => array(
				'description' => 'List comments. Filter by post_id, status (approve, hold, spam, trash) and search.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'status'   => array( 'type' => 'string' ),
						'search'   => array( 'type' => 'string' ),
						'per_page' => array( 'type' => 'integer' ),
						'page'     => array( 'type' => 'integer' ),
					),
				),
				'handler'     => array( $this, 'list_comments' ),
			),
			'get_comment'        => array(
				'description' => 'Get a comment by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_comment' ),
			),
			'create_comment'     => array(
				'description' => 'Create a comment on a post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array( 'type' => 'integer' ),
						'content'      => array( 'type' => 'string' ),
						'author_name'  => array( 'type' => 'string' ),
						'author_email' => array( 'type' => 'string' ),
						'parent'       => array( 'type' => 'integer' ),
					),
					'required'   => array( 'post_id', 'content' ),
				),
				'handler'     => array( $this, 'create_comment' ),
			),
			'update_comment'     => array(
				'description' => 'Update a comment content and/or status (approve, hold, spam, trash).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array( 'type' => 'integer' ),
						'content' => array( 'type' => 'string' ),
						'status'  => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'update_comment' ),
			),
			'delete_comment'     => array(
				'description' => 'Delete a comment (force=true to skip trash).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => 'integer' ),
						'force' => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_comment' ),
			),
		);
	}

	private function shape( $comment ) {
		return array(
			'id'           => (int) $comment->comment_ID,
			'post_id'      => (int) $comment->comment_post_ID,
			'author'       => $comment->comment_author,
			'author_email' => $comment->comment_author_email,
			'content'      => $comment->comment_content,
			'status'       => wp_get_comment_status( $comment->comment_ID ),
			'parent'       => (int) $comment->comment_parent,
			'date'         => $comment->comment_date_gmt,
		);
	}

	public function list_comments( $args ) {
		$query = array(
			'number' => isset( $args['per_page'] ) ? min( 100, max( 1, absint( $args['per_page'] ) ) ) : 20,
			'paged'  => isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1,
		);
		if ( ! empty( $args['post_id'] ) ) {
			$query['post_id'] = absint( $args['post_id'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$query['status'] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$query['search'] = sanitize_text_field( $args['search'] );
		}

		$comments = array();
		foreach ( get_comments( $query ) as $comment ) {
			$comments[] = $this->shape( $comment );
		}

		return array( 'comments' => $comments );
	}

	public function get_comment( $args ) {
		$id      = $this->require_id( $args );
		$comment = get_comment( $id );
		if ( ! $comment ) {
			throw new RuntimeException( 'Comment not found.' );
		}

		return $this->shape( $comment );
	}

	public function create_comment( $args ) {
		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			throw new RuntimeException( 'Post not found.' );
		}
		if ( ! isset( $args['content'] ) || '' === trim( (string) $args['content'] ) ) {
			throw new InvalidArgumentException( '"content" is required.' );
		}

		$data = array(
			'comment_post_ID'      => $post_id,
			'comment_content'      => (string) $args['content'],
			'comment_author'       => isset( $args['author_name'] ) ? sanitize_text_field( $args['author_name'] ) : '',
			'comment_author_email' => isset( $args['author_email'] ) ? sanitize_email( $args['author_email'] ) : '',
			'comment_parent'       => isset( $args['parent'] ) ? absint( $args['parent'] ) : 0,
			'comment_approved'     => 1,
		);

		$id = wp_insert_comment( $data );
		if ( ! $id ) {
			throw new RuntimeException( 'Could not create the comment.' );
		}

		return array( 'id' => (int) $id, 'post_id' => $post_id );
	}

	public function update_comment( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_comment( $id ) ) {
			throw new RuntimeException( 'Comment not found.' );
		}

		if ( isset( $args['content'] ) ) {
			wp_update_comment(
				array(
					'comment_ID'      => $id,
					'comment_content' => (string) $args['content'],
				)
			);
		}
		if ( ! empty( $args['status'] ) ) {
			wp_set_comment_status( $id, sanitize_key( $args['status'] ) );
		}

		return array( 'id' => $id, 'status' => wp_get_comment_status( $id ) );
	}

	public function delete_comment( $args ) {
		$id = $this->require_id( $args );
		if ( ! get_comment( $id ) ) {
			throw new RuntimeException( 'Comment not found.' );
		}

		$result = wp_delete_comment( $id, ! empty( $args['force'] ) );

		return array( 'id' => $id, 'deleted' => (bool) $result );
	}
}

/**
 * Site Editor: block theme templates & template parts (FSE).
 *
 * @since 1.5.0
 */
class WPPDT_MCP_Group_FSE extends WPPDT_MCP_Tool_Group {

	public function id() {
		return 'fse';
	}

	public function label() {
		return __( 'Site Editor (Block Templates)', 'wp-pro-devs-toolkit' );
	}

	public function requires() {
		return 'A block theme';
	}

	public function is_available() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	public function tools() {
		return array(
			'list_templates'  => array(
				'description' => 'List Site Editor templates. Set type="wp_template_part" for header/footer parts (default is page templates).',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array( 'type' => array( 'type' => 'string' ) ),
				),
				'handler'     => array( $this, 'list_templates' ),
			),
			'get_template'    => array(
				'description' => 'Get a block template/part and its block markup by ID (e.g. "theme//index").',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'string' ),
						'type' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'get_template' ),
			),
			'save_template'   => array(
				'description' => 'Create or customize a block template/part for the active theme. "content" is block markup; "slug" e.g. "index", "404", "header".',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'slug'    => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string' ),
						'title'   => array( 'type' => 'string' ),
						'type'    => array( 'type' => 'string', 'description' => 'wp_template (default) or wp_template_part.' ),
						'area'    => array( 'type' => 'string', 'description' => 'For parts: header, footer or uncategorized.' ),
					),
					'required'   => array( 'slug', 'content' ),
				),
				'handler'     => array( $this, 'save_template' ),
			),
			'delete_template' => array(
				'description' => 'Delete a user-customized template/part, reverting to the theme default. Theme-provided templates cannot be deleted.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array( 'type' => 'string' ),
						'type' => array( 'type' => 'string' ),
					),
					'required'   => array( 'id' ),
				),
				'handler'     => array( $this, 'delete_template' ),
			),
		);
	}

	private function template_type( $args ) {
		$type = isset( $args['type'] ) ? (string) $args['type'] : '';
		return ( false !== strpos( $type, 'part' ) ) ? 'wp_template_part' : 'wp_template';
	}

	private function resolve( $id, $args ) {
		$types = isset( $args['type'] ) ? array( $this->template_type( $args ) ) : array( 'wp_template', 'wp_template_part' );
		foreach ( $types as $type ) {
			$tpl = get_block_template( $id, $type );
			if ( $tpl ) {
				return array( $tpl, $type );
			}
		}
		return array( null, null );
	}

	public function list_templates( $args ) {
		$type      = $this->template_type( $args );
		$templates = array();
		foreach ( get_block_templates( array(), $type ) as $tpl ) {
			$templates[] = array(
				'id'          => $tpl->id,
				'slug'        => $tpl->slug,
				'title'       => $tpl->title,
				'source'      => $tpl->source,
				'description' => $tpl->description,
			);
		}

		return array( 'type' => $type, 'templates' => $templates );
	}

	public function get_template( $args ) {
		$id = isset( $args['id'] ) ? (string) $args['id'] : '';
		if ( '' === $id ) {
			throw new InvalidArgumentException( '"id" is required.' );
		}
		list( $tpl ) = $this->resolve( $id, $args );
		if ( ! $tpl ) {
			throw new RuntimeException( 'Template not found: ' . $id );
		}

		return array(
			'id'      => $tpl->id,
			'slug'    => $tpl->slug,
			'title'   => $tpl->title,
			'type'    => $tpl->type,
			'source'  => $tpl->source,
			'content' => $tpl->content,
		);
	}

	public function save_template( $args ) {
		$type    = $this->template_type( $args );
		$slug    = isset( $args['slug'] ) ? sanitize_title( $args['slug'] ) : '';
		$content = isset( $args['content'] ) ? (string) $args['content'] : '';
		if ( '' === $slug ) {
			throw new InvalidArgumentException( '"slug" is required.' );
		}
		if ( '' === $content ) {
			throw new InvalidArgumentException( '"content" is required.' );
		}

		$theme    = get_stylesheet();
		$title    = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : $slug;
		$existing = get_posts(
			array(
				'post_type'   => $type,
				'name'        => $slug,
				'post_status' => array( 'publish', 'draft', 'auto-draft' ),
				'numberposts' => 1,
				'tax_query'   => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => $theme,
					),
				),
			)
		);

		if ( $existing ) {
			$post_id = $existing[0]->ID;
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $content,
					'post_title'   => $title,
				)
			);
			$created = false;
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'    => $type,
					'post_name'    => $slug,
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => 'publish',
				),
				true
			);
			if ( is_wp_error( $post_id ) ) {
				return $post_id;
			}
			wp_set_object_terms( $post_id, $theme, 'wp_theme' );
			if ( 'wp_template_part' === $type ) {
				$area = ! empty( $args['area'] ) ? sanitize_key( $args['area'] ) : 'uncategorized';
				wp_set_object_terms( $post_id, $area, 'wp_template_part_area' );
			}
			$created = true;
		}

		return array(
			'id'      => $theme . '//' . $slug,
			'post_id' => (int) $post_id,
			'created' => $created,
		);
	}

	public function delete_template( $args ) {
		$id = isset( $args['id'] ) ? (string) $args['id'] : '';
		if ( '' === $id ) {
			throw new InvalidArgumentException( '"id" is required.' );
		}
		list( $tpl ) = $this->resolve( $id, $args );
		if ( ! $tpl ) {
			throw new RuntimeException( 'Template not found: ' . $id );
		}
		if ( 'custom' !== $tpl->source || empty( $tpl->wp_id ) ) {
			throw new RuntimeException( 'Only user-customized templates can be deleted; this one comes from the theme files.' );
		}

		wp_delete_post( $tpl->wp_id, true );

		return array( 'id' => $id, 'deleted' => true );
	}
}
