<?php
/**
 * Email REST controller: templates and send.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Routes for email templates and sending to a contact.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Email {

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
			'/email-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/email-templates/(?P<id>[a-z0-9]+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_template' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/contacts/(?P<id>\d+)/email',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send' ),
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
	 * GET /email-templates.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_templates() {
		return rest_ensure_response( Rayetun_CRM_Email::templates() );
	}

	/**
	 * POST /email-templates.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_template( WP_REST_Request $request ) {
		$result = Rayetun_CRM_Email::create_template( (array) $request->get_json_params() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$response = rest_ensure_response( $result );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * DELETE /email-templates/{id}.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_template( WP_REST_Request $request ) {
		Rayetun_CRM_Email::delete_template( (string) $request['id'] );
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	/**
	 * POST /contacts/{id}/email.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send( WP_REST_Request $request ) {
		$body    = (array) $request->get_json_params();
		$subject = isset( $body['subject'] ) ? $body['subject'] : '';
		$message = isset( $body['body'] ) ? $body['body'] : '';

		$result = Rayetun_CRM_Email::send( (int) $request['id'], $subject, $message );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( array( 'sent' => true ) );
	}
}
