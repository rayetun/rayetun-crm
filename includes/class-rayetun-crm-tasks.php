<?php
/**
 * Tasks & reminders model.
 *
 * Tasks can be linked to a contact and/or a deal, assigned to a user, and given
 * a type, priority, due date and status. A daily cron emails assignees about
 * tasks due that day.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repository for task records.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Tasks {

	/**
	 * Task types.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( 'call', 'email', 'meeting', 'follow_up', 'custom' );
	}

	/**
	 * Priorities.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function priorities() {
		return array( 'high', 'normal', 'low' );
	}

	/**
	 * Statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'open', 'in_progress', 'complete', 'cancelled' );
	}

	/**
	 * Registers the reminder cron handler.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( Rayetun_CRM_Scoring::CRON_HOOK, array( __CLASS__, 'send_due_reminders' ) );
	}

	/**
	 * Queries tasks with filters, sorting and pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Filters.
	 * @return array{items: array, total: int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$table = Rayetun_CRM_DB::table( 'tasks' );
		$defaults = array(
			'status'        => '',
			'type'          => '',
			'priority'      => '',
			'assigned_user' => 0,
			'contact_id'    => 0,
			'deal_id'       => 0,
			'due'           => '', // overdue | today | upcoming
			'search'        => '',
			'orderby'       => 'due',
			'order'         => 'ASC',
			'page'          => 1,
			'per_page'      => 50,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( in_array( $args['status'], self::statuses(), true ) ) {
			$where[]  = 't.status = %s';
			$params[] = $args['status'];
		}
		if ( in_array( $args['type'], self::types(), true ) ) {
			$where[]  = 't.type = %s';
			$params[] = $args['type'];
		}
		if ( in_array( $args['priority'], self::priorities(), true ) ) {
			$where[]  = 't.priority = %s';
			$params[] = $args['priority'];
		}
		if ( $args['assigned_user'] > 0 ) {
			$where[]  = 't.assigned_user = %d';
			$params[] = (int) $args['assigned_user'];
		}
		if ( $args['contact_id'] > 0 ) {
			$where[]  = 't.contact_id = %d';
			$params[] = (int) $args['contact_id'];
		}
		if ( $args['deal_id'] > 0 ) {
			$where[]  = 't.deal_id = %d';
			$params[] = (int) $args['deal_id'];
		}
		if ( '' !== $args['search'] ) {
			$where[]  = 't.title LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		$now = current_time( 'mysql' );
		if ( 'overdue' === $args['due'] ) {
			$where[]  = '( t.due IS NOT NULL AND t.due < %s AND t.status IN ( %s, %s ) )';
			$params[] = $now;
			$params[] = 'open';
			$params[] = 'in_progress';
		} elseif ( 'today' === $args['due'] ) {
			$where[]  = 'DATE( t.due ) = %s';
			$params[] = current_time( 'Y-m-d' );
		} elseif ( 'upcoming' === $args['due'] ) {
			$where[]  = '( t.due IS NOT NULL AND t.due >= %s )';
			$params[] = $now;
		}

		$where_sql = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], array( 'due', 'priority', 'created', 'status' ), true ) ? $args['orderby'] : 'due';
		$asc     = ( 'ASC' === strtoupper( (string) $args['order'] ) );

		$per_page = min( 100, max( 1, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$count_sql  = "SELECT COUNT(*) FROM %i t WHERE $where_sql";
		$count_args = array_merge( array( $table ), $params );
		$total      = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// NULL dues sort last when ascending by due date.
		$order_sql = ( 'due' === $orderby )
			? ( 't.due IS NULL, t.due ' . ( $asc ? 'ASC' : 'DESC' ) )
			: ( 't.' . $orderby . ' ' . ( $asc ? 'ASC' : 'DESC' ) );

		$list_sql  = "SELECT t.* FROM %i t WHERE $where_sql ORDER BY $order_sql LIMIT %d OFFSET %d";
		$list_args = array_merge( array( $table ), $params, array( $per_page, $offset ) );
		$rows      = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

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
	 * Fetches one task.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Task ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Rayetun_CRM_DB::table( 'tasks' ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ? self::format( $row ) : null;
	}

	/**
	 * Creates a task.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input.
	 * @return int|WP_Error
	 */
	public static function create( array $data ) {
		global $wpdb;

		$fields = self::sanitize( $data );

		if ( '' === $fields['title'] ) {
			return new WP_Error( 'rayetun_crm_task_title_required', __( 'A task title is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$fields['created'] = current_time( 'mysql' );
		if ( ! $fields['assigned_user'] ) {
			$fields['assigned_user'] = get_current_user_id();
		}

		$wpdb->insert( Rayetun_CRM_DB::table( 'tasks' ), $fields, self::formats_for( $fields ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a task.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Task ID.
	 * @param array $data Raw input.
	 * @return true|WP_Error
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id = (int) $id;
		if ( ! self::get( $id ) ) {
			return new WP_Error( 'rayetun_crm_task_not_found', __( 'Task not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$fields  = self::sanitize( $data );
		$update  = array();
		foreach ( self::writable_keys() as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$update[ $key ] = $fields[ $key ];
			}
		}

		if ( $update ) {
			$wpdb->update( Rayetun_CRM_DB::table( 'tasks' ), $update, array( 'id' => $id ), self::formats_for( $update ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return true;
	}

	/**
	 * Deletes a task.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Task ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( ! self::get( $id ) ) {
			return new WP_Error( 'rayetun_crm_task_not_found', __( 'Task not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$wpdb->delete( Rayetun_CRM_DB::table( 'tasks' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return true;
	}

	/**
	 * Returns dashboard-style counts (open, due today, overdue).
	 *
	 * @since 1.0.0
	 *
	 * @return array{open:int,today:int,overdue:int}
	 */
	public static function counts() {
		return array(
			'open'    => self::query( array( 'status' => 'open', 'per_page' => 1 ) )['total'],
			'today'   => self::query( array( 'due' => 'today', 'per_page' => 1 ) )['total'],
			'overdue' => self::query( array( 'due' => 'overdue', 'per_page' => 1 ) )['total'],
		);
	}

	/**
	 * Emails assignees about tasks due today (daily cron).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function send_due_reminders() {
		if ( ! apply_filters( 'rayetun_crm_task_email_reminders', true ) ) {
			return;
		}

		$due = self::query( array( 'due' => 'today', 'status' => 'open', 'per_page' => 100 ) );

		$by_user = array();
		foreach ( $due['items'] as $task ) {
			if ( $task['assigned_user'] ) {
				$by_user[ $task['assigned_user'] ][] = $task;
			}
		}

		foreach ( $by_user as $user_id => $tasks ) {
			$user = get_userdata( $user_id );
			if ( ! $user || ! is_email( $user->user_email ) ) {
				continue;
			}

			$lines = array( __( 'You have tasks due today:', 'rayetun-crm' ), '' );
			foreach ( $tasks as $task ) {
				$lines[] = '- ' . $task['title'];
			}

			wp_mail(
				$user->user_email,
				__( 'RayEtun CRM — tasks due today', 'rayetun-crm' ),
				implode( "\n", $lines )
			);
		}
	}

	/**
	 * Writable columns.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private static function writable_keys() {
		return array( 'contact_id', 'deal_id', 'type', 'title', 'notes', 'due', 'priority', 'status', 'assigned_user' );
	}

	/**
	 * Sanitises raw task input.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input.
	 * @return array
	 */
	private static function sanitize( array $data ) {
		$type = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'follow_up';
		if ( ! in_array( $type, self::types(), true ) ) {
			$type = 'follow_up';
		}
		$priority = isset( $data['priority'] ) ? sanitize_key( $data['priority'] ) : 'normal';
		if ( ! in_array( $priority, self::priorities(), true ) ) {
			$priority = 'normal';
		}
		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'open';
		if ( ! in_array( $status, self::statuses(), true ) ) {
			$status = 'open';
		}

		// Store the due date as a wall-clock local datetime string so it lines up
		// with current_time( 'mysql' ) for comparisons (no timezone conversion).
		$due = isset( $data['due'] ) ? sanitize_text_field( wp_unslash( $data['due'] ) ) : '';
		$due = str_replace( 'T', ' ', $due );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $due ) ) {
			if ( 10 === strlen( $due ) ) {
				$due .= ' 00:00:00';
			} elseif ( 16 === strlen( $due ) ) {
				$due .= ':00';
			}
		} else {
			$due = null;
		}

		return array(
			'contact_id'    => isset( $data['contact_id'] ) ? absint( $data['contact_id'] ) : 0,
			'deal_id'       => isset( $data['deal_id'] ) ? absint( $data['deal_id'] ) : 0,
			'type'          => $type,
			'title'         => isset( $data['title'] ) ? sanitize_text_field( wp_unslash( $data['title'] ) ) : '',
			'notes'         => isset( $data['notes'] ) ? sanitize_textarea_field( wp_unslash( $data['notes'] ) ) : '',
			'due'           => $due,
			'priority'      => $priority,
			'status'        => $status,
			'assigned_user' => isset( $data['assigned_user'] ) ? absint( $data['assigned_user'] ) : 0,
		);
	}

	/**
	 * Returns wpdb formats for a column map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Column map.
	 * @return string[]
	 */
	private static function formats_for( array $fields ) {
		$ints    = array( 'contact_id', 'deal_id', 'assigned_user' );
		$formats = array();
		foreach ( array_keys( $fields ) as $key ) {
			$formats[] = in_array( $key, $ints, true ) ? '%d' : '%s';
		}
		return $formats;
	}

	/**
	 * Shapes a raw task row for output.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function format( $row ) {
		$assignee = $row->assigned_user ? get_userdata( (int) $row->assigned_user ) : null;
		$contact  = $row->contact_id ? Rayetun_CRM_Contacts::get( (int) $row->contact_id ) : null;

		$overdue = false;
		if ( ! empty( $row->due ) && in_array( $row->status, array( 'open', 'in_progress' ), true ) ) {
			$overdue = $row->due < current_time( 'mysql' );
		}

		return array(
			'id'            => (int) $row->id,
			'contact_id'    => (int) $row->contact_id,
			'contact_name'  => $contact ? $contact['full_name'] : '',
			'deal_id'       => (int) $row->deal_id,
			'type'          => $row->type,
			'title'         => $row->title,
			'notes'         => $row->notes,
			'due'           => $row->due,
			'priority'      => $row->priority,
			'status'        => $row->status,
			'assigned_user' => (int) $row->assigned_user,
			'assignee_name' => $assignee ? $assignee->display_name : '',
			'overdue'       => $overdue,
			'created'       => $row->created,
		);
	}
}
