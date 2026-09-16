<?php
/**
 * Environment requirements gate.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the host meets the plugin's minimum requirements.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Requirements {

	/**
	 * Minimum PHP version.
	 *
	 * @var string
	 */
	const MIN_PHP = '7.4';

	/**
	 * Minimum WordPress version.
	 *
	 * @var string
	 */
	const MIN_WP = '6.2';

	/**
	 * Whether the current environment meets all requirements.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function are_met() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			return false;
		}

		if ( version_compare( $wp_version, self::MIN_WP, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Renders an admin notice explaining the unmet requirement.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function render_notice() {
		$message = sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version. */
			esc_html__( 'RayEtun CRM requires PHP %1$s+ and WordPress %2$s+. The plugin is inactive until the environment is updated.', 'rayetun-crm' ),
			esc_html( self::MIN_PHP ),
			esc_html( self::MIN_WP )
		);

		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}
}
