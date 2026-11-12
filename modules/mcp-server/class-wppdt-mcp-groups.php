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
		);
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

		return array(
			'id'        => $id,
			'status'    => get_post_status( $id ),
			'link'      => get_permalink( $id ),
			'edit_link' => get_edit_post_link( $id, 'raw' ),
		);
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
		);
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
