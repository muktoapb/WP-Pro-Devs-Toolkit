<?php
/**
 * MCP tool registry and tool implementations (Phase 1: content + Elementor).
 *
 * @package WPProDevs_Toolkit
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the available MCP tools and executes calls.
 *
 * @since 1.1.0
 */
class WPPDT_MCP_Tools {

	/**
	 * Registered tools keyed by name.
	 *
	 * Each entry: description (string), inputSchema (array), handler (callable).
	 *
	 * @since 1.1.0
	 * @var array
	 */
	private $tools = array();

	/**
	 * Constructor: builds the registry.
	 */
	public function __construct() {
		$this->register_content_tools();

		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$this->register_elementor_tools();
		}

		/**
		 * Filters the registered MCP tools.
		 *
		 * @since 1.1.0
		 *
		 * @param array            $tools Registered tools.
		 * @param WPPDT_MCP_Tools  $this  Registry instance.
		 */
		$this->tools = apply_filters( 'wppdt_mcp_tools', $this->tools, $this );
	}

	/**
	 * Returns tool definitions for tools/list.
	 *
	 * @return array
	 */
	public function get_definitions() {
		$definitions = array();

		foreach ( $this->tools as $name => $tool ) {
			$definitions[] = array(
				'name'        => $name,
				'description' => $tool['description'],
				'inputSchema' => $tool['inputSchema'],
			);
		}

		return $definitions;
	}

	/**
	 * Returns the list of tool names.
	 *
	 * @return string[]
	 */
	public function get_names() {
		return array_keys( $this->tools );
	}

	/**
	 * Executes a tool call and returns an MCP tool result.
	 *
	 * @param string $name Tool name.
	 * @param array  $args Arguments.
	 * @return array
	 */
	public function call( $name, $args ) {
		if ( ! isset( $this->tools[ $name ] ) ) {
			return $this->error_result( sprintf( 'Unknown tool: %s', $name ) );
		}

		try {
			$data = call_user_func( $this->tools[ $name ]['handler'], is_array( $args ) ? $args : array() );

			if ( is_wp_error( $data ) ) {
				return $this->error_result( $data->get_error_message() );
			}

			return $this->text_result( $data );
		} catch ( \Throwable $e ) {
			return $this->error_result( $e->getMessage() );
		}
	}

	/* ---------------------------------------------------------------------
	 * Result helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Wraps data as a successful MCP text result.
	 *
	 * @param mixed $data Scalar string or structured data.
	 * @return array
	 */
	private function text_result( $data ) {
		$text = is_string( $data )
			? $data
			: wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $text,
				),
			),
		);
	}

	/**
	 * Builds an MCP error result.
	 *
	 * @param string $message Message.
	 * @return array
	 */
	private function error_result( $message ) {
		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => true,
		);
	}

	/**
	 * Reads and validates a required post ID argument.
	 *
	 * @param array $args Arguments.
	 * @return int
	 * @throws InvalidArgumentException When missing/invalid.
	 */
	private function require_id( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		if ( ! $id ) {
			throw new InvalidArgumentException( 'A valid "id" is required.' );
		}

		return $id;
	}

	/* ---------------------------------------------------------------------
	 * Content tools
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the content tools.
	 *
	 * @return void
	 */
	private function register_content_tools() {
		$this->tools['list_posts'] = array(
			'description' => 'List posts or pages, newest first. Supports search, status and pagination.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type (post, page, any, or a custom type). Default "post".',
					),
					'status'    => array(
						'type'        => 'string',
						'description' => 'Post status (publish, draft, any, ...). Default "any".',
					),
					'search'    => array( 'type' => 'string' ),
					'per_page'  => array( 'type' => 'integer' ),
					'page'      => array( 'type' => 'integer' ),
				),
			),
			'handler'     => array( $this, 'tool_list_posts' ),
		);

		$this->tools['get_post'] = array(
			'description' => 'Get a single post or page by ID, including its content.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'id' ),
			),
			'handler'     => array( $this, 'tool_get_post' ),
		);

		$this->tools['create_post'] = array(
			'description' => 'Create a new post or page.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'title'     => array( 'type' => 'string' ),
					'content'   => array( 'type' => 'string' ),
					'status'    => array(
						'type'        => 'string',
						'description' => 'publish, draft, pending or private. Default "draft".',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'post or page (or custom type). Default "post".',
					),
					'excerpt'   => array( 'type' => 'string' ),
					'slug'      => array( 'type' => 'string' ),
				),
			),
			'handler'     => array( $this, 'tool_create_post' ),
		);

		$this->tools['update_post'] = array(
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
			'handler'     => array( $this, 'tool_update_post' ),
		);

		$this->tools['delete_post'] = array(
			'description' => 'Trash a post or page (set force=true to delete permanently).',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id'    => array( 'type' => 'integer' ),
					'force' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'id' ),
			),
			'handler'     => array( $this, 'tool_delete_post' ),
		);
	}

	/**
	 * Tool: list_posts.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function tool_list_posts( $args ) {
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

	/**
	 * Tool: get_post.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function tool_get_post( $args ) {
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

	/**
	 * Tool: create_post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	public function tool_create_post( $args ) {
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

	/**
	 * Tool: update_post.
	 *
	 * @param array $args Arguments.
	 * @return array|WP_Error
	 */
	public function tool_update_post( $args ) {
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

	/**
	 * Tool: delete_post.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function tool_delete_post( $args ) {
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

	/* ---------------------------------------------------------------------
	 * Elementor tools
	 * ------------------------------------------------------------------- */

	/**
	 * Registers the Elementor tools (only when Elementor is active).
	 *
	 * @return void
	 */
	private function register_elementor_tools() {
		$this->tools['get_elementor_data'] = array(
			'description' => 'Get the Elementor builder data (decoded JSON) for a page.',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'id' ),
			),
			'handler'     => array( $this, 'tool_get_elementor_data' ),
		);

		$this->tools['update_elementor_data'] = array(
			'description' => 'Replace the Elementor builder data for a page and regenerate its CSS. "data" is the Elementor elements tree (array or JSON string).',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id'   => array( 'type' => 'integer' ),
					'data' => array(
						'type'        => array( 'array', 'string' ),
						'description' => 'Elementor elements tree.',
					),
				),
				'required'   => array( 'id', 'data' ),
			),
			'handler'     => array( $this, 'tool_update_elementor_data' ),
		);

		$this->tools['regenerate_elementor_css'] = array(
			'description' => 'Regenerate Elementor CSS for one page (pass id) or clear the whole CSS cache (omit id).',
			'inputSchema' => array(
				'type'       => 'object',
				'properties' => array(
					'id' => array( 'type' => 'integer' ),
				),
			),
			'handler'     => array( $this, 'tool_regenerate_elementor_css' ),
		);
	}

	/**
	 * Tool: get_elementor_data.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function tool_get_elementor_data( $args ) {
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

	/**
	 * Tool: update_elementor_data.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function tool_update_elementor_data( $args ) {
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

		// Elementor stores this value slashed.
		update_post_meta( $id, '_elementor_data', wp_slash( $json ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $id, '_elementor_version', ELEMENTOR_VERSION );
		}

		$this->elementor_regenerate( $id );

		return array(
			'id'      => $id,
			'updated' => true,
		);
	}

	/**
	 * Tool: regenerate_elementor_css.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	public function tool_regenerate_elementor_css( $args ) {
		$id = isset( $args['id'] ) ? absint( $args['id'] ) : 0;

		$this->elementor_regenerate( $id );

		return array(
			'regenerated' => true,
			'scope'       => $id ? $id : 'all',
		);
	}

	/**
	 * Regenerates Elementor CSS for a post, or clears the whole cache.
	 *
	 * @param int $id Post ID, or 0 for all.
	 * @return void
	 */
	private function elementor_regenerate( $id ) {
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
