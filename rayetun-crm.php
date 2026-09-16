<?php
/**
 * Plugin Name:       RayEtun CRM – Sales Pipeline & Lead Management
 * Plugin URI:        https://wordpress.org/plugins/rayetun-crm
 * Description:       A self-hosted CRM for WordPress: visual sales pipeline, lead capture from any form, contacts, tasks and reports. Your leads, your data, no monthly fees.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Md Rayhan Uddin
 * Author URI:        https://rayetun.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rayetun-crm
 * Domain Path:       /languages
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin version. Bump on every release; used for asset cache-busting.
 */
define( 'RAYETUN_CRM_VERSION', '1.0.0' );

/**
 * Absolute path to the plugin directory, with trailing slash.
 */
define( 'RAYETUN_CRM_PATH', plugin_dir_path( __FILE__ ) );

/**
 * URL to the plugin directory, with trailing slash.
 */
define( 'RAYETUN_CRM_URL', plugin_dir_url( __FILE__ ) );

/**
 * Absolute path to the plugin's main file.
 */
define( 'RAYETUN_CRM_FILE', __FILE__ );

/**
 * The add-on contract this build of the free plugin honours.
 *
 * A Pro add-on declares the contract major it was built against. The free
 * plugin refuses to boot an add-on built against a contract major it no longer
 * honours, so a stale add-on reports a clear message instead of failing
 * somewhere harder to diagnose.
 */
define( 'RAYETUN_CRM_ADDON_CONTRACT', '1.0.0' );

require_once RAYETUN_CRM_PATH . 'includes/class-rayetun-crm-requirements.php';
require_once RAYETUN_CRM_PATH . 'includes/class-rayetun-crm.php';

/**
 * Returns the plugin instance.
 *
 * @since 1.0.0
 *
 * @return Rayetun_CRM The plugin instance.
 */
function rayetun_crm() {
	return Rayetun_CRM::instance();
}

/**
 * Boots the plugin.
 *
 * Hooked to `init` at priority 5 rather than `plugins_loaded`: feature labels
 * carry translated strings, and WordPress 6.7+ emits a
 * `_load_textdomain_just_in_time` notice for any translation requested before
 * `init`. Requirements are checked first so the plugin degrades gracefully on
 * unsupported hosts.
 *
 * @since 1.0.0
 *
 * @return void
 */
function rayetun_crm_bootstrap() {
	if ( ! Rayetun_CRM_Requirements::are_met() ) {
		add_action( 'admin_notices', array( 'Rayetun_CRM_Requirements', 'render_notice' ) );
		return;
	}

	rayetun_crm()->init();
}
add_action( 'init', 'rayetun_crm_bootstrap', 5 );

/**
 * Runs on plugin activation: creates tables, seeds defaults and maps
 * capabilities onto existing roles. Guarded so it never fatals on an
 * unsupported host.
 *
 * @since 1.0.0
 *
 * @return void
 */
function rayetun_crm_activate() {
	if ( ! Rayetun_CRM_Requirements::are_met() ) {
		return;
	}

	require_once RAYETUN_CRM_PATH . 'includes/class-rayetun-crm-db.php';
	require_once RAYETUN_CRM_PATH . 'includes/class-rayetun-crm-capabilities.php';
	require_once RAYETUN_CRM_PATH . 'includes/class-rayetun-crm-install.php';

	Rayetun_CRM_Install::activate();
}
register_activation_hook( __FILE__, 'rayetun_crm_activate' );

/**
 * Runs on plugin deactivation: clears scheduled events. Data is preserved
 * (removed only on uninstall).
 *
 * @since 1.0.0
 *
 * @return void
 */
function rayetun_crm_deactivate() {
	require_once RAYETUN_CRM_PATH . 'includes/class-rayetun-crm-scoring.php';
	Rayetun_CRM_Scoring::clear_schedule();
}
register_deactivation_hook( __FILE__, 'rayetun_crm_deactivate' );
