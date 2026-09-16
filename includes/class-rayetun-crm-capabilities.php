<?php
/**
 * Capability mapping.
 *
 * Rather than registering new roles, RayEtun CRM grants its capabilities to
 * existing WordPress roles: administrators manage everything; editors act as
 * CRM agents (create/edit records, but no settings or destructive admin
 * actions). This keeps the plugin aligned with a site's existing role setup.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and resolves the plugin's capabilities.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Capabilities {

	/**
	 * Full management: settings, deletion, everything.
	 *
	 * @var string
	 */
	const MANAGE = 'manage_rayetun_crm';

	/**
	 * Create and edit CRM records.
	 *
	 * @var string
	 */
	const EDIT = 'edit_rayetun_crm';

	/**
	 * Read-only access to the CRM.
	 *
	 * @var string
	 */
	const VIEW = 'view_rayetun_crm';

	/**
	 * Capabilities granted to each existing role on activation.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,string[]> Role slug => capabilities.
	 */
	private static function grants() {
		return array(
			'administrator' => array( self::MANAGE, self::EDIT, self::VIEW ),
			'editor'        => array( self::EDIT, self::VIEW ),
		);
	}

	/**
	 * Grants capabilities to existing roles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function add_caps() {
		foreach ( self::grants() as $role_slug => $caps ) {
			$role = get_role( $role_slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Removes the plugin's capabilities from all roles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function remove_caps() {
		$all = array( self::MANAGE, self::EDIT, self::VIEW );

		foreach ( wp_roles()->roles as $role_slug => $details ) {
			$role = get_role( $role_slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( $all as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Returns a human-readable label for the current user's CRM access level.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public static function current_label() {
		if ( current_user_can( self::MANAGE ) ) {
			return __( 'Administrator', 'rayetun-crm' );
		}

		if ( current_user_can( self::EDIT ) ) {
			return __( 'Agent', 'rayetun-crm' );
		}

		return __( 'Viewer', 'rayetun-crm' );
	}
}
