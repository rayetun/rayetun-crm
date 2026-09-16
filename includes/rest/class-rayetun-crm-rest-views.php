<?php
/**
 * Saved filter views REST controller.
 *
 * Views are per-user: each CRM user stores their own named filter presets in
 * user meta. No custom table is needed.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD routes for saved filter views under rayetun-crm/v1/views.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Views {

	/**
	 * User meta key that stores the current user's views.
	 *
	 * @var string
	 */
	const META_KEY = 'rayetun_crm_saved_views';

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
			'/views',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/views/(?P<id>[a-z0-9]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);
	}

	/**
	 * Permission: any CRM user reads/creates their own saved views.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Rayetun_CRM_Capabilities::VIEW );
	}

	/**
	 * Permission for removing a saved view (a destructive action). Saved views
	 * are per-user personal meta, so this need not be manage-level, but it is
	 * elevated above read-only.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_edit() {
		return current_user_can( Rayetun_CRM_Capabilities::EDIT );
	}

	/**
	 * Returns the current user's saved views.
	 *
	 * @since 1.0.0
	 *
	 * @return array[]
	 */
	private function views() {
		$views = get_user_meta( get_current_user_id(), self::META_KEY, true );

		return is_array( $views ) ? $views : array();
	}

	/**
	 * GET /views.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		return rest_ensure_response( array_values( $this->views() ) );
	}

	/**
	 * POST /views.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();
		$name = isset( $body['name'] ) ? sanitize_text_field( wp_unslash( $body['name'] ) ) : '';

		if ( '' === $name ) {
			return new WP_Error( 'rayetun_crm_view_name_required', __( 'A view name is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$filters_in = isset( $body['filters'] ) && is_array( $body['filters'] ) ? $body['filters'] : array();
		$order      = isset( $filters_in['order'] ) && 'asc' === strtolower( (string) $filters_in['order'] ) ? 'asc' : 'desc';

		$view = array(
			'id'      => substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 ),
			'name'    => $name,
			'filters' => array(
				'search'  => isset( $filters_in['search'] ) ? sanitize_text_field( wp_unslash( $filters_in['search'] ) ) : '',
				'status'  => isset( $filters_in['status'] ) ? sanitize_key( $filters_in['status'] ) : '',
				'orderby' => isset( $filters_in['orderby'] ) ? sanitize_key( $filters_in['orderby'] ) : 'created',
				'order'   => $order,
			),
		);

		$views   = $this->views();
		$views[] = $view;
		update_user_meta( get_current_user_id(), self::META_KEY, $views );

		$response = rest_ensure_response( $view );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * DELETE /views/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( WP_REST_Request $request ) {
		$id    = sanitize_key( $request['id'] );
		$views = array_values(
			array_filter(
				$this->views(),
				static function ( $view ) use ( $id ) {
					return isset( $view['id'] ) && $view['id'] !== $id;
				}
			)
		);

		update_user_meta( get_current_user_id(), self::META_KEY, $views );

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
