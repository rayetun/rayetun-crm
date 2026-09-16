<?php
/**
 * Dashboard & reports REST controller.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read-only routes for the dashboard and report datasets.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest_Reports {

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
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reports/contacts-over-time',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_contacts_over_time' ),
				'permission_callback' => array( $this, 'can_view' ),
				'args'                => array(
					'period' => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reports/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_sources' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/reports/pipeline',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_pipeline_report' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Rayetun_CRM_Capabilities::VIEW );
	}

	/**
	 * GET /dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_dashboard() {
		return rest_ensure_response( Rayetun_CRM_Reports::dashboard() );
	}

	/**
	 * GET /reports/contacts-over-time.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_contacts_over_time( WP_REST_Request $request ) {
		$period = ( 'month' === (string) $request['period'] ) ? 'month' : 'week';
		return rest_ensure_response( Rayetun_CRM_Reports::contacts_over_time( $period ) );
	}

	/**
	 * GET /reports/sources.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_sources() {
		return rest_ensure_response( Rayetun_CRM_Reports::source_breakdown() );
	}

	/**
	 * GET /reports/pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_pipeline_report() {
		return rest_ensure_response( Rayetun_CRM_Reports::pipeline_report() );
	}
}
