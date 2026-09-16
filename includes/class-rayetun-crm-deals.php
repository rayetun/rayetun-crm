<?php
/**
 * Deals model.
 *
 * Deals live in a pipeline stage and can be linked to contacts. Moving a deal
 * records a fresh "stage since" timestamp (used for the days-in-stage badge)
 * and fires an action for automation add-ons.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repository for deal records.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Deals {

	/**
	 * Builds the Kanban board payload: stages with nested deals and totals.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return array|WP_Error {
	 *     @type array $pipeline Pipeline with stages, each carrying deals/total.
	 * }
	 */
	public static function board( $pipeline_id ) {
		$pipeline = Rayetun_CRM_Pipelines::get( $pipeline_id );

		if ( ! $pipeline ) {
			return new WP_Error( 'rayetun_crm_pipeline_not_found', __( 'Pipeline not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$deals_by_stage = self::deals_grouped( $pipeline_id );

		foreach ( $pipeline['stages'] as &$stage ) {
			$stage_deals    = isset( $deals_by_stage[ $stage['id'] ] ) ? $deals_by_stage[ $stage['id'] ] : array();
			$stage['deals'] = $stage_deals;
			$stage['count'] = count( $stage_deals );
			$stage['total'] = array_sum( array_map( static function ( $d ) {
				return (float) $d['value'];
			}, $stage_deals ) );
		}
		unset( $stage );

		return array( 'pipeline' => $pipeline );
	}

	/**
	 * Computes headline analytics for a pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return array Analytics figures.
	 */
	public static function analytics( $pipeline_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT d.value, d.currency, d.created, d.updated, s.is_won, s.is_lost FROM %i d INNER JOIN %i s ON s.id = d.stage_id WHERE d.pipeline_id = %d',
			Rayetun_CRM_DB::table( 'deals' ),
			Rayetun_CRM_DB::table( 'stages' ),
			(int) $pipeline_id
		) );

		$open_count      = 0;
		$open_value      = 0.0;
		$won_count       = 0;
		$won_value       = 0.0;
		$lost_count      = 0;
		$won_days_total  = 0;
		$won_month_count = 0;
		$won_month_value = 0.0;
		$currency        = '';

		$month_start = strtotime( gmdate( 'Y-m-01 00:00:00' ) );

		foreach ( (array) $rows as $r ) {
			if ( '' === $currency && '' !== $r->currency ) {
				$currency = $r->currency;
			}

			if ( $r->is_won ) {
				$won_count++;
				$won_value     += (float) $r->value;
				$won_days_total += max( 0, (int) floor( ( strtotime( $r->updated ) - strtotime( $r->created ) ) / DAY_IN_SECONDS ) );
				if ( strtotime( $r->updated ) >= $month_start ) {
					$won_month_count++;
					$won_month_value += (float) $r->value;
				}
			} elseif ( $r->is_lost ) {
				$lost_count++;
			} else {
				$open_count++;
				$open_value += (float) $r->value;
			}
		}

		$closed = $won_count + $lost_count;

		return array(
			'currency'             => $currency,
			'open_count'           => $open_count,
			'open_value'           => $open_value,
			'won_count'            => $won_count,
			'won_value'            => $won_value,
			'lost_count'           => $lost_count,
			'win_rate'             => $closed > 0 ? (int) round( $won_count / $closed * 100 ) : 0,
			'avg_days_to_close'    => $won_count > 0 ? (int) round( $won_days_total / $won_count ) : 0,
			'won_this_month_count' => $won_month_count,
			'won_this_month_value' => $won_month_value,
		);
	}

	/**
	 * Returns all deals in a pipeline grouped by stage id.
	 *
	 * @since 1.0.0
	 *
	 * @param int $pipeline_id Pipeline ID.
	 * @return array<int,array> Map of stage_id => deal list.
	 */
	private static function deals_grouped( $pipeline_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT * FROM %i WHERE pipeline_id = %d ORDER BY updated DESC, id DESC',
			Rayetun_CRM_DB::table( 'deals' ),
			(int) $pipeline_id
		) );

		$grouped = array();
		foreach ( (array) $rows as $row ) {
			$grouped[ (int) $row->stage_id ][] = self::format( $row );
		}

		return $grouped;
	}

	/**
	 * Returns one formatted deal, or null.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Deal ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Rayetun_CRM_DB::table( 'deals' ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ? self::format( $row ) : null;
	}

	/**
	 * Creates a deal.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input.
	 * @return int|WP_Error New deal ID, or WP_Error.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$fields = self::sanitize( $data );

		if ( '' === $fields['title'] ) {
			return new WP_Error( 'rayetun_crm_deal_title_required', __( 'A deal title is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		if ( ! $fields['pipeline_id'] ) {
			$fields['pipeline_id'] = Rayetun_CRM_Pipelines::default_pipeline_id();
		}

		if ( ! Rayetun_CRM_Pipelines::stage_in_pipeline( $fields['stage_id'], $fields['pipeline_id'] ) ) {
			$stages = Rayetun_CRM_Pipelines::stages( $fields['pipeline_id'] );
			if ( empty( $stages ) ) {
				return new WP_Error( 'rayetun_crm_no_stages', __( 'The pipeline has no stages.', 'rayetun-crm' ), array( 'status' => 400 ) );
			}
			$fields['stage_id'] = $stages[0]['id'];
		}

		$now                   = current_time( 'mysql' );
		$fields['stage_since'] = $now;
		$fields['created']     = $now;
		$fields['updated']     = $now;

		$wpdb->insert( Rayetun_CRM_DB::table( 'deals' ), $fields, self::formats_for( $fields ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$id = (int) $wpdb->insert_id;

		if ( isset( $data['contact_ids'] ) && is_array( $data['contact_ids'] ) ) {
			self::set_contacts( $id, $data['contact_ids'] );
		}

		return $id;
	}

	/**
	 * Updates a deal.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Deal ID.
	 * @param array $data Raw input (partial allowed).
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id      = (int) $id;
		$current = self::get( $id );

		if ( ! $current ) {
			return new WP_Error( 'rayetun_crm_deal_not_found', __( 'Deal not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$fields  = self::sanitize( $data );
		$update  = array();
		$formats = array();

		foreach ( self::writable_keys() as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$update[ $key ] = $fields[ $key ];
			}
		}

		// A stage change via update also resets the stage timer.
		if ( isset( $update['stage_id'] ) && (int) $update['stage_id'] !== (int) $current['stage_id'] ) {
			if ( ! Rayetun_CRM_Pipelines::stage_in_pipeline( $update['stage_id'], $current['pipeline_id'] ) ) {
				unset( $update['stage_id'] );
			} else {
				$update['stage_since'] = current_time( 'mysql' );
			}
		}

		$update['updated'] = current_time( 'mysql' );

		foreach ( array_keys( $update ) as $key ) {
			$formats[] = self::format_for_key( $key );
		}

		$wpdb->update( Rayetun_CRM_DB::table( 'deals' ), $update, array( 'id' => $id ), $formats, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( isset( $data['contact_ids'] ) && is_array( $data['contact_ids'] ) ) {
			self::set_contacts( $id, $data['contact_ids'] );
		}

		return true;
	}

	/**
	 * Moves a deal to another stage in the same pipeline.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id       Deal ID.
	 * @param int $stage_id Target stage ID.
	 * @return true|WP_Error
	 */
	public static function move( $id, $stage_id ) {
		global $wpdb;

		$deal = self::get( $id );
		if ( ! $deal ) {
			return new WP_Error( 'rayetun_crm_deal_not_found', __( 'Deal not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$stage_id = (int) $stage_id;
		if ( ! Rayetun_CRM_Pipelines::stage_in_pipeline( $stage_id, $deal['pipeline_id'] ) ) {
			return new WP_Error( 'rayetun_crm_bad_stage', __( 'That stage is not in this pipeline.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Rayetun_CRM_DB::table( 'deals' ),
			array( 'stage_id' => $stage_id, 'stage_since' => current_time( 'mysql' ), 'updated' => current_time( 'mysql' ) ),
			array( 'id' => (int) $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires when a deal moves to a new stage.
		 *
		 * @since 1.0.0
		 *
		 * @param int $id        Deal ID.
		 * @param int $stage_id  New stage ID.
		 * @param int $from      Previous stage ID.
		 */
		do_action( 'rayetun_crm_deal_stage_changed', (int) $id, $stage_id, (int) $deal['stage_id'] );

		return true;
	}

	/**
	 * Deletes a deal and its contact links.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Deal ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( ! self::get( $id ) ) {
			return new WP_Error( 'rayetun_crm_deal_not_found', __( 'Deal not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$wpdb->delete( Rayetun_CRM_DB::table( 'deals' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Rayetun_CRM_DB::table( 'deal_contacts' ), array( 'deal_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return true;
	}

	/**
	 * Writable deal columns.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private static function writable_keys() {
		return array( 'title', 'description', 'value', 'currency', 'close_date', 'probability', 'assigned_user', 'stage_id', 'pipeline_id' );
	}

	/**
	 * Sanitises raw deal input into a column map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input.
	 * @return array
	 */
	private static function sanitize( array $data ) {
		$close = isset( $data['close_date'] ) ? sanitize_text_field( wp_unslash( $data['close_date'] ) ) : '';
		if ( '' !== $close && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $close ) ) {
			$close = '';
		}

		return array(
			'title'         => isset( $data['title'] ) ? sanitize_text_field( wp_unslash( $data['title'] ) ) : '',
			'description'   => isset( $data['description'] ) ? sanitize_textarea_field( wp_unslash( $data['description'] ) ) : '',
			'value'         => isset( $data['value'] ) && is_numeric( $data['value'] ) ? (float) $data['value'] : 0,
			'currency'      => isset( $data['currency'] ) ? sanitize_text_field( wp_unslash( $data['currency'] ) ) : '',
			'close_date'    => '' !== $close ? $close : null,
			'probability'   => isset( $data['probability'] ) ? min( 100, max( 0, (int) $data['probability'] ) ) : 0,
			'assigned_user' => isset( $data['assigned_user'] ) ? absint( $data['assigned_user'] ) : 0,
			'stage_id'      => isset( $data['stage_id'] ) ? absint( $data['stage_id'] ) : 0,
			'pipeline_id'   => isset( $data['pipeline_id'] ) ? absint( $data['pipeline_id'] ) : 0,
		);
	}

	/**
	 * Returns the wpdb format for a column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Column name.
	 * @return string
	 */
	private static function format_for_key( $key ) {
		$ints   = array( 'probability', 'assigned_user', 'stage_id', 'pipeline_id' );
		$floats = array( 'value' );

		if ( in_array( $key, $ints, true ) ) {
			return '%d';
		}
		if ( in_array( $key, $floats, true ) ) {
			return '%f';
		}

		return '%s';
	}

	/**
	 * Returns ordered wpdb formats for a column map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Column map.
	 * @return string[]
	 */
	private static function formats_for( array $fields ) {
		$formats = array();
		foreach ( array_keys( $fields ) as $key ) {
			$formats[] = self::format_for_key( $key );
		}
		return $formats;
	}

	/**
	 * Replaces a deal's linked contacts.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $deal_id     Deal ID.
	 * @param int[] $contact_ids Contact IDs.
	 * @return void
	 */
	private static function set_contacts( $deal_id, array $contact_ids ) {
		global $wpdb;

		$table = Rayetun_CRM_DB::table( 'deal_contacts' );
		$wpdb->delete( $table, array( 'deal_id' => (int) $deal_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( array_unique( array_filter( array_map( 'absint', $contact_ids ) ) ) as $cid ) {
			$wpdb->insert( $table, array( 'deal_id' => (int) $deal_id, 'contact_id' => $cid ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Returns a deal's linked contacts as id/name pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $deal_id Deal ID.
	 * @return array[]
	 */
	private static function get_contacts( $deal_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT c.id, c.first_name, c.last_name, c.email FROM %i dc INNER JOIN %i c ON c.id = dc.contact_id WHERE dc.deal_id = %d',
			Rayetun_CRM_DB::table( 'deal_contacts' ),
			Rayetun_CRM_DB::table( 'contacts' ),
			(int) $deal_id
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$name  = trim( $row->first_name . ' ' . $row->last_name );
			$out[] = array(
				'id'   => (int) $row->id,
				'name' => '' !== $name ? $name : $row->email,
			);
		}

		return $out;
	}

	/**
	 * Shapes a raw deal row for output.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function format( $row ) {
		$assignee = $row->assigned_user ? get_userdata( (int) $row->assigned_user ) : null;

		$days = 0;
		if ( ! empty( $row->stage_since ) && '0000-00-00 00:00:00' !== $row->stage_since ) {
			$days = (int) floor( ( time() - strtotime( $row->stage_since ) ) / DAY_IN_SECONDS );
		}

		return array(
			'id'             => (int) $row->id,
			'pipeline_id'    => (int) $row->pipeline_id,
			'stage_id'       => (int) $row->stage_id,
			'title'          => $row->title,
			'description'    => $row->description,
			'value'          => (float) $row->value,
			'currency'       => $row->currency,
			'close_date'     => $row->close_date,
			'probability'    => (int) $row->probability,
			'assigned_user'  => (int) $row->assigned_user,
			'assignee_name'  => $assignee ? $assignee->display_name : '',
			'days_in_stage'  => max( 0, $days ),
			'contacts'       => self::get_contacts( (int) $row->id ),
			'created'        => $row->created,
			'updated'        => $row->updated,
		);
	}
}
