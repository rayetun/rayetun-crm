<?php
/**
 * Dashboard and report aggregation.
 *
 * Read-only rollups over contacts, deals, tasks and activities for the
 * Dashboard widgets and the Reports charts.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Aggregate queries for dashboards and reports.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Reports {

	/**
	 * Builds the dashboard payload.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function dashboard() {
		$pipeline_id = Rayetun_CRM_Pipelines::default_pipeline_id();
		$analytics   = Rayetun_CRM_Deals::analytics( $pipeline_id );

		$now       = current_time( 'mysql' );
		$week_ago  = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - ( 7 * DAY_IN_SECONDS ) );
		$two_weeks = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - ( 14 * DAY_IN_SECONDS ) );

		$hot = array();
		foreach ( Rayetun_CRM_Contacts::query( array( 'orderby' => 'lead_score', 'order' => 'DESC', 'per_page' => 5 ) )['items'] as $c ) {
			$hot[] = array(
				'id'         => $c['id'],
				'full_name'  => $c['full_name'],
				'email'      => $c['email'],
				'lead_score' => $c['lead_score'],
				'heat'       => $c['heat'],
			);
		}

		return array(
			'currency'           => $analytics['currency'],
			'leads_this_week'    => self::contacts_created_between( $week_ago, $now ),
			'leads_last_week'    => self::contacts_created_between( $two_weeks, $week_ago ),
			'open_pipeline'      => $analytics['open_value'],
			'revenue_this_month' => $analytics['won_this_month_value'],
			'tasks'              => Rayetun_CRM_Tasks::counts(),
			'hot_leads'          => $hot,
			'pipeline_stages'    => self::pipeline_stage_values( $pipeline_id ),
			'recent_activity'    => Rayetun_CRM_Activities::recent( 8 ),
		);
	}

	/**
	 * Counts contacts created within a datetime range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $from Inclusive lower bound (mysql datetime).
	 * @param string $to   Exclusive upper bound (mysql datetime).
	 * @return int
	 */
	private static function contacts_created_between( $from, $to ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT COUNT(*) FROM %i WHERE created >= %s AND created < %s',
			Rayetun_CRM_DB::table( 'contacts' ),
			$from,
			$to
		) );
	}

	/**
	 * Returns per-stage deal value/count for a pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return array[]
	 */
	private static function pipeline_stage_values( $pipeline_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT stage_id, COUNT(*) AS c, COALESCE(SUM(value),0) AS v FROM %i WHERE pipeline_id = %d GROUP BY stage_id',
			Rayetun_CRM_DB::table( 'deals' ),
			(int) $pipeline_id
		) );

		$totals = array();
		foreach ( (array) $rows as $row ) {
			$totals[ (int) $row->stage_id ] = array( 'count' => (int) $row->c, 'value' => (float) $row->v );
		}

		$stages = array();
		foreach ( Rayetun_CRM_Pipelines::stages( $pipeline_id ) as $stage ) {
			$stages[] = array(
				'name'   => $stage['name'],
				'color'  => $stage['color'],
				'count'  => isset( $totals[ $stage['id'] ] ) ? $totals[ $stage['id'] ]['count'] : 0,
				'value'  => isset( $totals[ $stage['id'] ] ) ? $totals[ $stage['id'] ]['value'] : 0,
				'is_won' => $stage['is_won'],
				'is_lost'=> $stage['is_lost'],
			);
		}

		return $stages;
	}

	/**
	 * Contacts created per period over the recent past.
	 *
	 * @since 1.0.0
	 *
	 * @param string $period 'week' or 'month'.
	 * @return array[] List of { label, count }.
	 */
	public static function contacts_over_time( $period = 'week' ) {
		global $wpdb;

		$table  = Rayetun_CRM_DB::table( 'contacts' );
		$series = array();

		if ( 'month' === $period ) {
			$buckets = 6;
			for ( $i = $buckets - 1; $i >= 0; $i-- ) {
				$start = gmdate( 'Y-m-01 00:00:00', strtotime( "-$i months", strtotime( current_time( 'Y-m-01' ) . ' 00:00:00' ) ) );
				$end   = gmdate( 'Y-m-01 00:00:00', strtotime( '+1 month', strtotime( $start ) ) );
				$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created >= %s AND created < %s', $table, $start, $end ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$series[] = array( 'label' => gmdate( 'M Y', strtotime( $start ) ), 'count' => $count );
			}
		} else {
			$buckets = 8;
			$now_ts  = strtotime( current_time( 'mysql' ) );
			for ( $i = $buckets - 1; $i >= 0; $i-- ) {
				$start = gmdate( 'Y-m-d 00:00:00', $now_ts - ( ( $i + 1 ) * 7 - 1 ) * DAY_IN_SECONDS );
				$end   = gmdate( 'Y-m-d 00:00:00', $now_ts - ( $i * 7 - 1 ) * DAY_IN_SECONDS );
				$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created >= %s AND created < %s', $table, $start, $end ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$series[] = array( 'label' => gmdate( 'M j', strtotime( $start ) ), 'count' => $count );
			}
		}

		return $series;
	}

	/**
	 * Lead-source breakdown by contact count.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] List of { label, value }.
	 */
	public static function source_breakdown() {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT CASE WHEN source = '' THEN %s ELSE source END AS label, COUNT(*) AS value FROM %i GROUP BY label ORDER BY value DESC LIMIT 8",
			__( 'Unknown', 'rayetun-crm' ),
			Rayetun_CRM_DB::table( 'contacts' )
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array( 'label' => $row->label, 'value' => (int) $row->value );
		}

		return $out;
	}

	/**
	 * Pipeline conversion funnel and win/loss for the default pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public static function pipeline_report() {
		$pipeline_id = Rayetun_CRM_Pipelines::default_pipeline_id();
		$stages      = self::pipeline_stage_values( $pipeline_id );
		$analytics   = Rayetun_CRM_Deals::analytics( $pipeline_id );

		return array(
			'stages'   => $stages,
			'won'      => $analytics['won_count'],
			'lost'     => $analytics['lost_count'],
			'win_rate' => $analytics['win_rate'],
		);
	}
}
