<?php
/**
 * Activation routine.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Runs one-time setup on plugin activation.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Install {

	/**
	 * Creates tables, seeds defaults and maps capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate() {
		Rayetun_CRM_DB::create_tables();
		Rayetun_CRM_Capabilities::add_caps();
		self::seed_default_pipeline();

		if ( ! get_option( 'rayetun_crm_installed_at' ) ) {
			add_option( 'rayetun_crm_installed_at', time() );
		}

		add_option( 'rayetun_crm_version', RAYETUN_CRM_VERSION );
	}

	/**
	 * Seeds the single default pipeline and its stages (idempotent).
	 *
	 * The free tier ships exactly one pipeline; additional pipelines are a Pro
	 * capability. Seeding is skipped if any pipeline already exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function seed_default_pipeline() {
		global $wpdb;

		$pipelines = Rayetun_CRM_DB::table( 'pipelines' );
		$stages    = Rayetun_CRM_DB::table( 'stages' );

		$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $pipelines ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $existing > 0 ) {
			return;
		}

		$now = current_time( 'mysql' );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$pipelines,
			array(
				'name'       => __( 'Sales Pipeline', 'rayetun-crm' ),
				'is_default' => 1,
				'sort'       => 0,
				'created'    => $now,
			),
			array( '%s', '%d', '%d', '%s' )
		);

		$pipeline_id = (int) $wpdb->insert_id;

		$default_stages = array(
			array( __( 'New Lead', 'rayetun-crm' ), '#6b7280', 0, 0, 0 ),
			array( __( 'Qualified', 'rayetun-crm' ), '#3b82f6', 1, 0, 0 ),
			array( __( 'Proposal Sent', 'rayetun-crm' ), '#8b5cf6', 2, 0, 0 ),
			array( __( 'Negotiation', 'rayetun-crm' ), '#f59e0b', 3, 0, 0 ),
			array( __( 'Won', 'rayetun-crm' ), '#22c55e', 4, 1, 0 ),
			array( __( 'Lost', 'rayetun-crm' ), '#ef4444', 5, 0, 1 ),
		);

		foreach ( $default_stages as $stage ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$stages,
				array(
					'pipeline_id' => $pipeline_id,
					'name'        => $stage[0],
					'color'       => $stage[1],
					'sort'        => $stage[2],
					'is_won'      => $stage[3],
					'is_lost'     => $stage[4],
				),
				array( '%d', '%s', '%s', '%d', '%d', '%d' )
			);
		}
	}
}
