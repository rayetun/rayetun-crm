<?php
/**
 * Contacts REST controller.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers CRUD routes for contacts under rayetun-crm/v1/contacts.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Contacts {

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
			'/contacts',
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
			'/contacts/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
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

		register_rest_route(
			$this->namespace,
			'/contacts/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => $this->collection_args(),
			)
		);

		register_rest_route(
			$this->namespace,
			'/contacts/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/contacts/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/contacts/(?P<id>\d+)/activities',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_activities' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/contacts/(?P<id>\d+)/notes',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_note' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/contacts/(?P<id>\d+)/woo',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_woo' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * GET /contacts/{id}/woo — WooCommerce purchase summary.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_woo( WP_REST_Request $request ) {
		$contact = Rayetun_CRM_Contacts::get( (int) $request['id'] );

		if ( ! $contact ) {
			return new WP_Error( 'rayetun_crm_not_found', __( 'Contact not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( Rayetun_CRM_WooCommerce::purchase_summary( $contact['email'] ) );
	}

	/**
	 * GET /contacts/export — returns the CSV text as a JSON payload so the
	 * client can trigger a download.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function export( WP_REST_Request $request ) {
		$csv = Rayetun_CRM_Contacts::export_csv(
			array(
				'search' => (string) $request['search'],
				'status' => (string) $request['status'],
				'tag_id' => (int) $request['tag_id'],
			)
		);

		return rest_ensure_response(
			array(
				'filename' => 'contacts-' . gmdate( 'Y-m-d' ) . '.csv',
				'csv'      => $csv,
			)
		);
	}

	/**
	 * POST /contacts/import.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( WP_REST_Request $request ) {
		$body      = (array) $request->get_json_params();
		$rows      = isset( $body['rows'] ) && is_array( $body['rows'] ) ? $body['rows'] : array();
		$duplicate = ( isset( $body['duplicate'] ) && 'update' === $body['duplicate'] ) ? 'update' : 'skip';

		if ( empty( $rows ) ) {
			return new WP_Error( 'rayetun_crm_no_rows', __( 'No rows to import.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		if ( count( $rows ) > 2000 ) {
			return new WP_Error( 'rayetun_crm_too_many_rows', __( 'Import at most 2000 rows per request.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		return rest_ensure_response( Rayetun_CRM_Contacts::import_rows( $rows, $duplicate ) );
	}

	/**
	 * POST /contacts/bulk.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function bulk( WP_REST_Request $request ) {
		$body   = (array) $request->get_json_params();
		$action = isset( $body['action'] ) ? sanitize_key( $body['action'] ) : '';
		$ids    = isset( $body['ids'] ) && is_array( $body['ids'] ) ? $body['ids'] : array();
		$value  = isset( $body['value'] ) ? $body['value'] : '';

		// Bulk deletion is destructive and requires the manage capability, even
		// though other bulk actions (status, tag) are available to agents.
		if ( 'delete' === $action && ! current_user_can( Rayetun_CRM_Capabilities::MANAGE ) ) {
			return new WP_Error( 'rayetun_crm_forbidden', __( 'You are not allowed to delete contacts.', 'rayetun-crm' ), array( 'status' => 403 ) );
		}

		$result = Rayetun_CRM_Contacts::bulk( $action, $ids, $value );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'affected' => (int) $result ) );
	}

	/**
	 * GET /contacts/{id}/activities.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_activities( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! Rayetun_CRM_Contacts::get( $id ) ) {
			return new WP_Error( 'rayetun_crm_not_found', __( 'Contact not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$page   = max( 1, (int) $request['page'] );
		$result = Rayetun_CRM_Activities::for_contact( $id, $page, 30 );

		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );

		return $response;
	}

	/**
	 * POST /contacts/{id}/notes.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_note( WP_REST_Request $request ) {
		$id = (int) $request['id'];

		if ( ! Rayetun_CRM_Contacts::get( $id ) ) {
			return new WP_Error( 'rayetun_crm_not_found', __( 'Contact not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$body    = (array) $request->get_json_params();
		$content = isset( $body['content'] ) ? $body['content'] : '';
		$result  = Rayetun_CRM_Activities::add_note( $id, $content );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response( Rayetun_CRM_Activities::for_contact( $id, 1, 30 )['items'] );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Read permission.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Rayetun_CRM_Capabilities::VIEW );
	}

	/**
	 * Write permission.
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
	 * Declares and validates collection query args.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function collection_args() {
		return array(
			'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
			'status'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'tag_id'   => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			'orderby'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
			'order'    => array( 'type' => 'string', 'enum' => array( 'asc', 'desc', 'ASC', 'DESC' ) ),
			'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
			'per_page' => array( 'type' => 'integer', 'default' => 25, 'sanitize_callback' => 'absint' ),
		);
	}

	/**
	 * GET /contacts.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Contacts::query(
			array(
				'search'   => (string) $request['search'],
				'status'   => (string) $request['status'],
				'tag_id'   => (int) $request['tag_id'],
				'orderby'  => (string) $request['orderby'],
				'order'    => (string) $request['order'],
				'page'     => max( 1, (int) $request['page'] ),
				'per_page' => (int) $request['per_page'] ? (int) $request['per_page'] : 25,
			)
		);

		$per_page = (int) $request['per_page'] ? (int) $request['per_page'] : 25;
		$response = rest_ensure_response( $result['items'] );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) ( (int) ceil( $result['total'] / max( 1, $per_page ) ) ) );

		return $response;
	}

	/**
	 * GET /contacts/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( WP_REST_Request $request ) {
		$contact = Rayetun_CRM_Contacts::get( (int) $request['id'] );

		if ( ! $contact ) {
			return new WP_Error( 'rayetun_crm_not_found', __( 'Contact not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $contact );
	}

	/**
	 * POST /contacts.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$id = Rayetun_CRM_Contacts::create( (array) $request->get_json_params() );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$response = rest_ensure_response( Rayetun_CRM_Contacts::get( $id ) );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * PUT/PATCH /contacts/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Contacts::update( (int) $request['id'], (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( Rayetun_CRM_Contacts::get( (int) $request['id'] ) );
	}

	/**
	 * DELETE /contacts/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Contacts::delete( (int) $request['id'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
