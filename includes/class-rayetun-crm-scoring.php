<?php
/**
 * Static lead scoring.
 *
 * Free-tier scoring reacts to server-side events only (form submissions) — no
 * front-end visitor tracking (that is a Pro concern). Each event adds points to
 * the contact's running lead_score and is recorded in the score_events log. A
 * daily cron decays scores for contacts that have gone quiet.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Awards and decays contact lead scores.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Scoring {

	/**
	 * Daily maintenance cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'rayetun_crm_daily_maintenance';

	/**
	 * Hooks scoring into capture events and schedules decay.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rayetun_crm_lead_captured', array( __CLASS__, 'on_lead_captured' ), 10, 2 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'run_decay' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Point values per scoring event (static, free-tier events).
	 *
	 * @since 1.0.0
	 *
	 * @return array<string,int>
	 */
	public static function event_points() {
		/**
		 * Filters the point value awarded per scoring event.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string,int> $points Event slug => points.
		 */
		return apply_filters(
			'rayetun_crm_score_events',
			array(
				'form_submission'      => 20,
				'multiple_submissions' => 30,
			)
		);
	}

	/**
	 * Awards points for an event and updates the contact's score.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $event      Event slug.
	 * @return void
	 */
	public static function award( $contact_id, $event ) {
		global $wpdb;

		$points_map = self::event_points();
		$event      = sanitize_key( $event );

		if ( ! isset( $points_map[ $event ] ) ) {
			return;
		}

		$points = (int) $points_map[ $event ];
		$now    = current_time( 'mysql' );

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Rayetun_CRM_DB::table( 'score_events' ),
			array(
				'contact_id' => (int) $contact_id,
				'event'      => $event,
				'points'     => $points,
				'created'    => $now,
			),
			array( '%d', '%s', '%d', '%s' )
		);

		$contacts = Rayetun_CRM_DB::table( 'contacts' );
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"UPDATE %i SET lead_score = lead_score + %d, last_activity = %s WHERE id = %d",
			$contacts,
			$points,
			$now,
			(int) $contact_id
		) );
	}

	/**
	 * Scores a captured lead: a submission, plus a one-off bonus on the second.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $lead       Lead data (unused).
	 * @return void
	 */
	public static function on_lead_captured( $contact_id, $lead ) {
		unset( $lead );

		self::award( $contact_id, 'form_submission' );

		if ( 2 === self::event_count( $contact_id, 'form_submission' ) ) {
			self::award( $contact_id, 'multiple_submissions' );
		}
	}

	/**
	 * Counts recorded events of a type for a contact.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $event      Event slug.
	 * @return int
	 */
	private static function event_count( $contact_id, $event ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT COUNT(*) FROM %i WHERE contact_id = %d AND event = %s',
			Rayetun_CRM_DB::table( 'score_events' ),
			(int) $contact_id,
			sanitize_key( $event )
		) );
	}

	/**
	 * Daily decay: reduces scores for contacts inactive beyond the threshold.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function run_decay() {
		global $wpdb;

		/**
		 * Filters the inactivity window (days) before score decay applies.
		 *
		 * @since 1.0.0
		 *
		 * @param int $days Days of inactivity. Default 60.
		 */
		$days = (int) apply_filters( 'rayetun_crm_score_decay_days', 60 );

		/**
		 * Filters the percentage a stale score decays by each run.
		 *
		 * @since 1.0.0
		 *
		 * @param int $percent Decay percent. Default 20.
		 */
		$percent = min( 100, max( 0, (int) apply_filters( 'rayetun_crm_score_decay_percent', 20 ) ) );

		if ( $days <= 0 || 0 === $percent ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
		$factor = ( 100 - $percent ) / 100;

		$contacts = Rayetun_CRM_DB::table( 'contacts' );
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"UPDATE %i SET lead_score = FLOOR( lead_score * %f ) WHERE lead_score > 0 AND last_activity < %s",
			$contacts,
			$factor,
			$cutoff
		) );
	}

	/**
	 * Maps a numeric score to a heat label.
	 *
	 * @since 1.0.0
	 *
	 * @param int $score Lead score.
	 * @return string One of: cold, warm, hot, very_hot.
	 */
	public static function heat( $score ) {
		/**
		 * Filters the score thresholds for heat levels.
		 *
		 * @since 1.0.0
		 *
		 * @param array $thresholds { warm, hot, very_hot } minimum scores.
		 */
		$t = apply_filters(
			'rayetun_crm_heat_thresholds',
			array(
				'warm'     => 20,
				'hot'      => 50,
				'very_hot' => 100,
			)
		);

		$score = (int) $score;

		if ( $score >= $t['very_hot'] ) {
			return 'very_hot';
		}
		if ( $score >= $t['hot'] ) {
			return 'hot';
		}
		if ( $score >= $t['warm'] ) {
			return 'warm';
		}

		return 'cold';
	}

	/**
	 * Clears the scheduled decay event (called on deactivation).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_schedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
