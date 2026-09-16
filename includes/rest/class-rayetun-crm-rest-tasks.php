<?php
/**
 * Tasks REST controller.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD + counts routes for tasks under rayetun-crm/v1/tasks.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Tasks {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $namespace REST namespace.
	 */
	public function __construct( $namespace ) {
		$this->namespace = $namespace;
	}

	/**
	 * Registers routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/tasks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => $this->collection_args(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/counts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_counts' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/tasks/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * View permission.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Rayetun_CRM_Capabilities::VIEW );
	}

	/**
	 * Edit permission.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_edit() {
		return current_user_can( Rayetun_CRM_Capabilities::EDIT );
	}

	/**
	 * Manage permission — required for destructive actions (deletion).
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Rayetun_CRM_Capabilities::MANAGE );
	}

	/**
	 * Collection query args.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function collection_args() {
		return array(
			'status'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'type'       => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'priority'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'due'        => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'contact_id' => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			'search'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'orderby'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'order'      => array( 'type' => 'string' ),
			'page'       => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
			'per_page'   => array( 'type' => 'integer', 'default' => 50, 'sanitize_callback' => 'absint' ),
		);
	}

	/**
	 * GET /tasks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Tasks::query(
			array(
				'status'     => (string) $request['status'],
				'type'       => (string) $request['type'],
				'priority'   => (string) $request['priority'],
				'due'        => (string) $request['due'],
				'contact_id' => (int) $request['contact_id'],
				'search'     => (string) $request['search'],
				'orderby'    => (string) $request['orderby'],
				'order'      => (string) $request['order'],
				'page'       => max( 1, (int) $request['page'] ),
				'per_page'   => (int) $request['per_page'] ? (int) $request['per_page'] : 50,
			)
		);

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );

		return $response;
	}

	/**
	 * GET /tasks/counts.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_counts() {
		return rest_ensure_response( Rayetun_CRM_Tasks::counts() );
	}

	/**
	 * POST /tasks.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$id = Rayetun_CRM_Tasks::create( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$response = rest_ensure_response( Rayetun_CRM_Tasks::get( $id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * PUT/PATCH /tasks/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Tasks::update( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Rayetun_CRM_Tasks::get( (int) $request['id'] ) );
	}

	/**
	 * DELETE /tasks/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Tasks::delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
