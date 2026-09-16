<?php
/**
 * Custom field definitions REST controller.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD routes for custom field definitions under rayetun-crm/v1/fields.
 *
 * Reading definitions needs view access (the contact form consumes them);
 * managing them requires the manage capability.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Fields {

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
			'/fields',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/fields/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'can_manage' ),
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
	 * Manage permission.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Rayetun_CRM_Capabilities::MANAGE );
	}

	/**
	 * GET /fields.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		return rest_ensure_response( Rayetun_CRM_Custom_Fields::definitions( 'contact' ) );
	}

	/**
	 * POST /fields.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$id = Rayetun_CRM_Custom_Fields::create_field( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$response = rest_ensure_response( Rayetun_CRM_Custom_Fields::get_definition( $id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * PUT/PATCH /fields/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Custom_Fields::update_field( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Rayetun_CRM_Custom_Fields::get_definition( (int) $request['id'] ) );
	}

	/**
	 * DELETE /fields/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Custom_Fields::delete_field( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
