<?php
/**
 * Main plugin loader.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's subsystems together.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM {

	/**
	 * Singleton instance.
	 *
	 * @var Rayetun_CRM|null
	 */
	private static $instance = null;

	/**
	 * Whether init() has already run.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Rayetun_CRM
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Loads dependencies and registers runtime hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$this->includes();

		// Apply additive schema migrations after a plugin update (activation
		// alone does not fire on update).
		add_action( 'admin_init', array( 'Rayetun_CRM_DB', 'maybe_upgrade' ) );

		// Translations are auto-loaded by WordPress for .org-hosted plugins
		// (since 4.6), so load_plugin_textdomain() is intentionally omitted.
		// Always-on core: activity log and lead scoring.
		Rayetun_CRM_Activities::register();
		Rayetun_CRM_Scoring::register();

		// Optional modules — registration is suspended when switched off under
		// Settings → Modules. Data is preserved either way.
		if ( Rayetun_CRM_Modules::is_enabled( 'tasks' ) ) {
			Rayetun_CRM_Tasks::register();
		}
		if ( Rayetun_CRM_Modules::is_enabled( 'email' ) ) {
			Rayetun_CRM_Email::register();
		}
		if ( Rayetun_CRM_Modules::is_enabled( 'woocommerce' ) ) {
			Rayetun_CRM_WooCommerce::register();
		}
		if ( Rayetun_CRM_Modules::is_enabled( 'lead_forms' ) ) {
			Rayetun_CRM_Forms::register();
			Rayetun_CRM_Integrations::register();
		}

		Rayetun_CRM_Rest::instance()->register();
		Rayetun_CRM_Admin::instance()->register();

		/**
		 * Fires once the free plugin has finished booting.
		 *
		 * Pro add-ons attach here after confirming contract compatibility via
		 * rayetun_crm_register_addon().
		 *
		 * @since 1.0.0
		 */
		do_action( 'rayetun_crm_loaded' );
	}

	/**
	 * Requires the plugin's class files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function includes() {
		$files = array(
			'class-rayetun-crm-db.php',
			'class-rayetun-crm-capabilities.php',
			'class-rayetun-crm-extension.php',
			'class-rayetun-crm-modules.php',
			'class-rayetun-crm-contacts.php',
			'class-rayetun-crm-activities.php',
			'class-rayetun-crm-custom-fields.php',
			'class-rayetun-crm-pipelines.php',
			'class-rayetun-crm-deals.php',
			'class-rayetun-crm-lead-capture.php',
			'class-rayetun-crm-forms.php',
			'class-rayetun-crm-integrations.php',
			'class-rayetun-crm-scoring.php',
			'class-rayetun-crm-tasks.php',
			'class-rayetun-crm-email.php',
			'class-rayetun-crm-reports.php',
			'class-rayetun-crm-woocommerce.php',
			'rest/class-rayetun-crm-rest-contacts.php',
			'rest/class-rayetun-crm-rest-fields.php',
			'rest/class-rayetun-crm-rest-views.php',
			'rest/class-rayetun-crm-rest-pipeline.php',
			'rest/class-rayetun-crm-rest-tasks.php',
			'rest/class-rayetun-crm-rest-email.php',
			'rest/class-rayetun-crm-rest-reports.php',
			'class-rayetun-crm-rest.php',
			'class-rayetun-crm-admin.php',
		);

		foreach ( $files as $file ) {
			require_once RAYETUN_CRM_PATH . 'includes/' . $file;
		}
	}
}
