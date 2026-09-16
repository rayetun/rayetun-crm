<?php
/**
 * Pipeline & deals REST controller.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes for pipelines, the Kanban board, and deals.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Pipeline {

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
			'/pipelines',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pipelines' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_pipeline' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/pipelines/(?P<id>\d+)/board',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_board' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/pipelines/(?P<id>\d+)/analytics',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_analytics' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/deals',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_deal' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/deals/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_deal' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_deal' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_deal' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/deals/(?P<id>\d+)/move',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'move_deal' ),
				'permission_callback' => array( $this, 'can_edit' ),
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
	 * GET /pipelines.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_pipelines() {
		return rest_ensure_response( Rayetun_CRM_Pipelines::all() );
	}

	/**
	 * POST /pipelines.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_pipeline( WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();
		$id   = Rayetun_CRM_Pipelines::create( isset( $body['name'] ) ? $body['name'] : '' );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$response = rest_ensure_response( Rayetun_CRM_Pipelines::get( $id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * GET /pipelines/{id}/board.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_board( WP_REST_Request $request ) {
		$board = Rayetun_CRM_Deals::board( (int) $request['id'] );

		if ( is_wp_error( $board ) ) {
			return $board;
		}

		return rest_ensure_response( $board );
	}

	/**
	 * GET /pipelines/{id}/analytics.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_analytics( WP_REST_Request $request ) {
		return rest_ensure_response( Rayetun_CRM_Deals::analytics( (int) $request['id'] ) );
	}

	/**
	 * POST /deals.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_deal( WP_REST_Request $request ) {
		$id = Rayetun_CRM_Deals::create( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$response = rest_ensure_response( Rayetun_CRM_Deals::get( $id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * GET /deals/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_deal( WP_REST_Request $request ) {
		$deal = Rayetun_CRM_Deals::get( (int) $request['id'] );

		if ( ! $deal ) {
			return new WP_Error( 'rayetun_crm_deal_not_found', __( 'Deal not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $deal );
	}

	/**
	 * PUT/PATCH /deals/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_deal( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Deals::update( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Rayetun_CRM_Deals::get( (int) $request['id'] ) );
	}

	/**
	 * DELETE /deals/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_deal( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Deals::delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * PUT/PATCH /deals/{id}/move.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function move_deal( WP_REST_Request $request ) {
		$body     = (array) $request->get_json_params();
		$stage_id = isset( $body['stage_id'] ) ? (int) $body['stage_id'] : 0;

		$result = Rayetun_CRM_Deals::move( (int) $request['id'], $stage_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Rayetun_CRM_Deals::get( (int) $request['id'] ) );
	}
}
