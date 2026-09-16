<?php
/**
 * Admin menu and SPA host.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin menu and mounts the React SPA.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'rayetun-crm';

	/**
	 * Singleton instance.
	 *
	 * @var Rayetun_CRM_Admin|null
	 */
	private static $instance = null;

	/**
	 * Hook suffix of the SPA page, for conditional asset loading.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Rayetun_CRM_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Hooks the admin menu and asset enqueue.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Registers the top-level CRM menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_menu() {
		$this->hook_suffix = add_menu_page(
			__( 'RayEtun CRM', 'rayetun-crm' ),
			__( 'RayEtun CRM', 'rayetun-crm' ),
			Rayetun_CRM_Capabilities::VIEW,
			self::PAGE_SLUG,
			array( $this, 'render_app' ),
			'dashicons-groups',
			26
		);

		/**
		 * Fires after the CRM menu is registered so add-ons can attach submenus.
		 *
		 * @since 1.0.0
		 *
		 * @param string $page_slug The parent menu slug.
		 */
		do_action( 'rayetun_crm_admin_menu', self::PAGE_SLUG );
	}

	/**
	 * Renders the SPA mount container.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_app() {
		require RAYETUN_CRM_PATH . 'admin/views/app.php';
	}

	/**
	 * Enqueues the SPA bundle, but only on the CRM page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		$asset_file = RAYETUN_CRM_PATH . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'rayetun-crm-app',
			RAYETUN_CRM_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// wp-scripts emits either build/index.css or build/style-index.css
		// depending on the imported stylesheet's name; enqueue whichever exists.
		foreach ( array( 'style-index', 'index' ) as $css_name ) {
			$css_path = RAYETUN_CRM_PATH . 'build/' . $css_name . '.css';

			if ( file_exists( $css_path ) ) {
				wp_enqueue_style(
					'rayetun-crm-app',
					RAYETUN_CRM_URL . 'build/' . $css_name . '.css',
					array(),
					$asset['version']
				);
				break;
			}
		}

		wp_set_script_translations( 'rayetun-crm-app', 'rayetun-crm', RAYETUN_CRM_PATH . 'languages' );

		wp_localize_script(
			'rayetun-crm-app',
			'rayetunCRM',
			array(
				'restNamespace' => Rayetun_CRM_Rest::NAMESPACE,
			)
		);
	}
}
