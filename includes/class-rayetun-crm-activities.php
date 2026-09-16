<?php
/**
 * Activities & notes model and event logger.
 *
 * Stores a timeline entry per contact interaction: manual notes plus automatic
 * system events (contact created, status changed). Also subscribes to the
 * contact lifecycle actions so logging stays decoupled from the model.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repository and logger for the activities timeline.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Activities {

	/**
	 * Subscribes the logger to contact lifecycle actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rayetun_crm_contact_created', array( __CLASS__, 'on_contact_created' ), 10, 2 );
		add_action( 'rayetun_crm_contact_updated', array( __CLASS__, 'on_contact_updated' ), 10, 3 );
	}

	/**
	 * Records a timeline entry.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $type       Entry type: note|created|status_changed.
	 * @param array  $data        Type-specific payload.
	 * @param int    $deal_id     Optional related deal ID.
	 * @return int Inserted activity ID (0 on failure).
	 */
	public static function log( $contact_id, $type, array $data = array(), $deal_id = 0 ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Rayetun_CRM_DB::table( 'activities' ),
			array(
				'contact_id' => (int) $contact_id,
				'deal_id'    => (int) $deal_id,
				'type'       => sanitize_key( $type ),
				'data'       => wp_json_encode( $data ),
				'user_id'    => get_current_user_id(),
				'created'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Returns a contact's timeline, newest first.
	 *
	 * @since 1.0.0
	 *
	 * @param int $contact_id Contact ID.
	 * @param int $page       1-based page. Default 1.
	 * @param int $per_page   Rows per page. Default 30.
	 * @return array{items: array, total: int}
	 */
	public static function for_contact( $contact_id, $page = 1, $per_page = 30 ) {
		global $wpdb;

		$table    = Rayetun_CRM_DB::table( 'activities' );
		$per_page = min( 100, max( 1, (int) $per_page ) );
		$offset   = ( max( 1, (int) $page ) - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contact_id = %d', $table, (int) $contact_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT * FROM %i WHERE contact_id = %d ORDER BY created DESC, id DESC LIMIT %d OFFSET %d',
			$table,
			(int) $contact_id,
			$per_page,
			$offset
		) );

		$items = array();
		foreach ( (array) $rows as $row ) {
			$items[] = self::format( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Returns the most recent activities across all contacts, with names.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit Max rows. Default 8.
	 * @return array[]
	 */
	public static function recent( $limit = 8 ) {
		global $wpdb;

		$limit = min( 50, max( 1, (int) $limit ) );

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT * FROM %i WHERE contact_id > 0 ORDER BY created DESC, id DESC LIMIT %d',
			Rayetun_CRM_DB::table( 'activities' ),
			$limit
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$contact           = Rayetun_CRM_Contacts::get( (int) $row->contact_id );
			$item              = self::format( $row );
			$item['contact_id']   = (int) $row->contact_id;
			$item['contact_name'] = $contact ? $contact['full_name'] : '';
			$out[]             = $item;
		}

		return $out;
	}

	/**
	 * Adds a manual note to a contact.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $content    Note text.
	 * @return int|WP_Error Activity ID, or WP_Error when empty.
	 */
	public static function add_note( $contact_id, $content ) {
		$content = sanitize_textarea_field( wp_unslash( $content ) );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'rayetun_crm_empty_note', __( 'A note cannot be empty.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		return self::log( $contact_id, 'note', array( 'content' => $content ) );
	}

	/**
	 * Logs a contact-created event.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $fields     Inserted fields (unused).
	 * @return void
	 */
	public static function on_contact_created( $contact_id, $fields ) {
		unset( $fields );
		self::log( $contact_id, 'created' );
	}

	/**
	 * Logs a status change when a contact is updated.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $contact_id Contact ID.
	 * @param array $changes    Changed fields.
	 * @param array $previous   Previous contact state.
	 * @return void
	 */
	public static function on_contact_updated( $contact_id, $changes, $previous ) {
		if ( isset( $changes['status'] ) && $changes['status'] !== $previous['status'] ) {
			self::log(
				$contact_id,
				'status_changed',
				array(
					'from' => $previous['status'],
					'to'   => $changes['status'],
				)
			);
		}
	}

	/**
	 * Shapes a raw activity row for output.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function format( $row ) {
		$data   = json_decode( (string) $row->data, true );
		$author = $row->user_id ? get_userdata( (int) $row->user_id ) : null;

		return array(
			'id'      => (int) $row->id,
			'type'    => $row->type,
			'data'    => is_array( $data ) ? $data : array(),
			'author'  => $author ? $author->display_name : '',
			'created' => $row->created,
		);
	}
}
