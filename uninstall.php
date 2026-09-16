<?php
/**
 * Uninstall cleanup.
 *
 * Runs on plugin deletion (not deactivation). Drops all custom tables, removes
 * plugin options and strips the capabilities granted to existing roles.
 *
 * @package Rayetun\CRM
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$rayetun_crm_tables = array(
	'contacts',
	'companies',
	'tags',
	'contact_tags',
	'custom_fields',
	'custom_field_values',
	'pipelines',
	'stages',
	'deals',
	'deal_contacts',
	'activities',
	'tasks',
	'forms',
	'form_maps',
	'email_log',
	'score_events',
	'files',
);

foreach ( $rayetun_crm_tables as $rayetun_crm_slug ) {
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'rayetun_crm_' . $rayetun_crm_slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

delete_option( 'rayetun_crm_db_version' );
delete_option( 'rayetun_crm_version' );
delete_option( 'rayetun_crm_installed_at' );
delete_option( 'rayetun_crm_modules' );
delete_option( 'rayetun_crm_email_templates' );

// Remove per-user onboarding + saved-view meta.
delete_metadata( 'user', 0, 'rayetun_crm_onboarded', '', true );
delete_metadata( 'user', 0, 'rayetun_crm_saved_views', '', true );

// Strip granted capabilities from every role.
$rayetun_crm_caps = array( 'manage_rayetun_crm', 'edit_rayetun_crm', 'view_rayetun_crm' );

foreach ( wp_roles()->roles as $rayetun_crm_role_slug => $rayetun_crm_details ) {
	$rayetun_crm_role = get_role( $rayetun_crm_role_slug );

	if ( ! $rayetun_crm_role ) {
		continue;
	}

	foreach ( $rayetun_crm_caps as $rayetun_crm_cap ) {
		$rayetun_crm_role->remove_cap( $rayetun_crm_cap );
	}
}
