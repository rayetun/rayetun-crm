<?php
/**
 * Internal REST API.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's REST routes under the rayetun-crm/v1 namespace.
 *
 * M0 ships the bootstrap route that hydrates the admin SPA. Resource routes
 * (contacts, deals, tasks…) land from M1 onward. Pro routes attach via the
 * `rayetun_crm_register_rest_routes` action.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Rest {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'rayetun-crm/v1';

	/**
	 * Singleton instance.
	 *
	 * @var Rayetun_CRM_Rest|null
	 */
	private static $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Rayetun_CRM_Rest
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks route registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/bootstrap',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_bootstrap' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/onboarding',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'complete_onboarding' ),
				'permission_callback' => array( $this, 'can_view' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/modules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_modules' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_modules' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		// Always-on core resources.
		$contacts = new Rayetun_CRM_Rest_Contacts( self::NAMESPACE );
		$contacts->register_routes();

		$fields = new Rayetun_CRM_Rest_Fields( self::NAMESPACE );
		$fields->register_routes();

		$views = new Rayetun_CRM_Rest_Views( self::NAMESPACE );
		$views->register_routes();

		$reports = new Rayetun_CRM_Rest_Reports( self::NAMESPACE );
		$reports->register_routes();

		// Optional-module resources — routes exist only while the module is on.
		if ( Rayetun_CRM_Modules::is_enabled( 'pipeline' ) ) {
			$pipeline = new Rayetun_CRM_Rest_Pipeline( self::NAMESPACE );
			$pipeline->register_routes();
		}

		if ( Rayetun_CRM_Modules::is_enabled( 'tasks' ) ) {
			$tasks = new Rayetun_CRM_Rest_Tasks( self::NAMESPACE );
			$tasks->register_routes();
		}

		if ( Rayetun_CRM_Modules::is_enabled( 'email' ) ) {
			$email = new Rayetun_CRM_Rest_Email( self::NAMESPACE );
			$email->register_routes();
		}

		/**
		 * Lets Pro add-ons register additional REST routes.
		 *
		 * @since 1.0.0
		 *
		 * @param string $namespace The plugin's REST namespace.
		 */
		do_action( 'rayetun_crm_register_rest_routes', self::NAMESPACE );
	}

	/**
	 * Permission callback: read-only CRM access.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function can_view() {
		return current_user_can( Rayetun_CRM_Capabilities::VIEW );
	}

	/**
	 * Permission callback: manage-level CRM access.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( Rayetun_CRM_Capabilities::MANAGE );
	}

	/**
	 * Returns the configuration the admin SPA needs to boot.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_bootstrap() {
		$data = array(
			'is_pro'         => rayetun_crm_is_pro(),
			'role'           => Rayetun_CRM_Capabilities::current_label(),
			'task_counts'    => Rayetun_CRM_Modules::is_enabled( 'tasks' ) ? Rayetun_CRM_Tasks::counts() : array( 'today' => 0, 'overdue' => 0 ),
			'modules'        => Rayetun_CRM_Modules::enabled_map(),
			'onboarded'      => (bool) get_user_meta( get_current_user_id(), 'rayetun_crm_onboarded', true ),
			'form_shortcode' => '[rayetun_crm_form]',
			'capabilities'   => array(
				'manage' => current_user_can( Rayetun_CRM_Capabilities::MANAGE ),
				'edit'   => current_user_can( Rayetun_CRM_Capabilities::EDIT ),
				'view'   => current_user_can( Rayetun_CRM_Capabilities::VIEW ),
			),
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Marks the current user as having completed onboarding.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_REST_Response
	 */
	public function complete_onboarding() {
		update_user_meta( get_current_user_id(), 'rayetun_crm_onboarded', 1 );

		return rest_ensure_response( array( 'onboarded' => true ) );
	}

	/**
	 * Returns the optional modules and their state for the settings screen.
	 *
	 * @since 1.1.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_modules() {
		return rest_ensure_response( Rayetun_CRM_Modules::for_display() );
	}

	/**
	 * Persists module on/off changes.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Request carrying a `modules` slug => bool map.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_modules( WP_REST_Request $request ) {
		$changes = $request->get_param( 'modules' );

		if ( ! is_array( $changes ) ) {
			return new WP_Error( 'rayetun_crm_bad_modules', __( 'No module changes were provided.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$clean = array();
		foreach ( $changes as $key => $on ) {
			$clean[ sanitize_key( $key ) ] = (bool) $on;
		}

		Rayetun_CRM_Modules::update( $clean );

		return rest_ensure_response( Rayetun_CRM_Modules::for_display() );
	}
}
