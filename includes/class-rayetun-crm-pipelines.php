<?php
/**
 * Pipelines and stages model.
 *
 * A pipeline is an ordered set of stages that deals move through. The free tier
 * allows a single pipeline (enforced on create via the pipeline-limit filter);
 * Pro removes the cap.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repository for pipelines and their stages.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Pipelines {

	/**
	 * Returns all pipelines, each with its ordered stages.
	 *
	 * @since 1.0.0
	 *
	 * @return array[]
	 */
	public static function all() {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY sort ASC, id ASC', Rayetun_CRM_DB::table( 'pipelines' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::format_pipeline( $row );
		}

		return $out;
	}

	/**
	 * Returns one pipeline with its stages, or null.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Pipeline ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Rayetun_CRM_DB::table( 'pipelines' ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ? self::format_pipeline( $row ) : null;
	}

	/**
	 * Returns the default pipeline's ID (creating a fallback only if none exist).
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public static function default_pipeline_id() {
		global $wpdb;

		$table = Rayetun_CRM_DB::table( 'pipelines' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i ORDER BY is_default DESC, sort ASC, id ASC LIMIT 1', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $id;
	}

	/**
	 * Returns the ordered stages for a pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return array[]
	 */
	public static function stages( $pipeline_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT * FROM %i WHERE pipeline_id = %d ORDER BY sort ASC, id ASC',
			Rayetun_CRM_DB::table( 'stages' ),
			(int) $pipeline_id
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::format_stage( $row );
		}

		return $out;
	}

	/**
	 * Whether a stage belongs to a pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @param int $stage_id    Stage ID.
	 * @param int $pipeline_id Pipeline ID.
	 * @return bool
	 */
	public static function stage_in_pipeline( $stage_id, $pipeline_id ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT id FROM %i WHERE id = %d AND pipeline_id = %d',
			Rayetun_CRM_DB::table( 'stages' ),
			(int) $stage_id,
			(int) $pipeline_id
		) );
	}

	/**
	 * Creates a pipeline with default stages, enforcing the edition limit.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Pipeline name.
	 * @return int|WP_Error New pipeline ID, or WP_Error when the limit is hit.
	 */
	public static function create( $name ) {
		global $wpdb;

		$name  = sanitize_text_field( wp_unslash( $name ) );
		$table = Rayetun_CRM_DB::table( 'pipelines' );

		if ( '' === $name ) {
			return new WP_Error( 'rayetun_crm_pipeline_name_required', __( 'A pipeline name is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$next_sort = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(sort),0)+1 FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->insert( $table, array( 'name' => $name, 'is_default' => 0, 'sort' => $next_sort, 'created' => current_time( 'mysql' ) ), array( '%s', '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$pipeline_id = (int) $wpdb->insert_id;
		self::seed_stages( $pipeline_id );

		return $pipeline_id;
	}

	/**
	 * Seeds a new pipeline with the default stage set.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return void
	 */
	private static function seed_stages( $pipeline_id ) {
		global $wpdb;

		$stages = array(
			array( __( 'New Lead', 'rayetun-crm' ), '#6b7280', 0, 0, 0 ),
			array( __( 'Qualified', 'rayetun-crm' ), '#3b82f6', 1, 0, 0 ),
			array( __( 'Proposal Sent', 'rayetun-crm' ), '#8b5cf6', 2, 0, 0 ),
			array( __( 'Negotiation', 'rayetun-crm' ), '#f59e0b', 3, 0, 0 ),
			array( __( 'Won', 'rayetun-crm' ), '#22c55e', 4, 1, 0 ),
			array( __( 'Lost', 'rayetun-crm' ), '#ef4444', 5, 0, 1 ),
		);

		$table = Rayetun_CRM_DB::table( 'stages' );

		foreach ( $stages as $stage ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
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

	/**
	 * Shapes a raw pipeline row (with stages).
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function format_pipeline( $row ) {
		return array(
			'id'         => (int) $row->id,
			'name'       => $row->name,
			'is_default' => (bool) $row->is_default,
			'stages'     => self::stages( (int) $row->id ),
		);
	}

	/**
	 * Shapes a raw stage row.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function format_stage( $row ) {
		return array(
			'id'          => (int) $row->id,
			'pipeline_id' => (int) $row->pipeline_id,
			'name'        => $row->name,
			'color'       => $row->color,
			'sort'        => (int) $row->sort,
			'is_won'      => (bool) $row->is_won,
			'is_lost'     => (bool) $row->is_lost,
		);
	}
}
