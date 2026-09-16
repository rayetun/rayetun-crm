<?php
/**
 * Extension / add-on contract.
 *
 * The free plugin is fully functional standalone. The Pro add-on attaches
 * through the hooks declared here after confirming it was built against a
 * contract major this build still honours. Pro never edits core files.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers a Pro add-on against the free plugin's contract.
 *
 * Called by the add-on on `rayetun_crm_loaded`. Returns false (and surfaces an
 * admin notice) when the add-on targets an incompatible contract major.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Add-on registration details.
 *
 *     @type string $contract Contract version the add-on was built against.
 *     @type string $name     Human-readable add-on name, for notices.
 * }
 * @return bool True when the add-on may boot.
 */
function rayetun_crm_register_addon( array $args ) {
	$contract = isset( $args['contract'] ) ? (string) $args['contract'] : '0.0.0';
	$name     = isset( $args['name'] ) ? (string) $args['name'] : __( 'RayEtun CRM add-on', 'rayetun-crm' );

	$honoured = explode( '.', RAYETUN_CRM_ADDON_CONTRACT );
	$target   = explode( '.', $contract );

	// Same major version is required for compatibility.
	if ( ( $honoured[0] ?? '' ) !== ( $target[0] ?? '' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $name ) {
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: %s: add-on name. */
							__( '%s was built for a different version of RayEtun CRM. Please update both plugins to matching versions.', 'rayetun-crm' ),
							$name
						)
					)
				);
			}
		);

		return false;
	}

	/**
	 * Flags the CRM as Pro once a compatible add-on has registered.
	 *
	 * @since 1.0.0
	 */
	add_filter( 'rayetun_crm_is_pro', '__return_true' );

	return true;
}

/**
 * Whether a compatible Pro add-on is active.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function rayetun_crm_is_pro() {
	/**
	 * Filters the Pro edition flag.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $is_pro Whether Pro is active. Default false.
	 */
	return (bool) apply_filters( 'rayetun_crm_is_pro', false );
}
