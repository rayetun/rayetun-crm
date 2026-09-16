<?php
/**
 * Contacts data model.
 *
 * Encapsulates all database access for contacts, their tags and the light
 * company records derived from the company field. Every query is built with
 * $wpdb->prepare() using %i identifier placeholders for table names; tags live
 * in a join table for indexable filtering.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repository for contact records.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Contacts {

	/**
	 * Allowed contact statuses.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array( 'lead', 'prospect', 'client', 'inactive', 'archived' );
	}

	/**
	 * Columns that list queries may be ordered by.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private static function sortable() {
		return array( 'first_name', 'last_name', 'email', 'status', 'source', 'lead_score', 'created', 'last_activity' );
	}

	/**
	 * Queries contacts with search, filtering, sorting and pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string $search   Full-text term matched against name and email.
	 *     @type string $status   Restrict to a status.
	 *     @type int    $tag_id   Restrict to contacts carrying this tag.
	 *     @type string $orderby  Column to sort by. Default 'created'.
	 *     @type string $order    ASC or DESC. Default 'DESC'.
	 *     @type int    $page     1-based page number. Default 1.
	 *     @type int    $per_page Rows per page (1-100). Default 25.
	 * }
	 * @return array {
	 *     @type array $items Formatted contact rows.
	 *     @type int   $total Total rows matching the filters.
	 * }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$table    = Rayetun_CRM_DB::table( 'contacts' );
		$c_tags   = Rayetun_CRM_DB::table( 'contact_tags' );
		$defaults = array(
			'search'   => '',
			'status'   => '',
			'tag_id'   => 0,
			'orderby'  => 'created',
			'order'    => 'DESC',
			'page'     => 1,
			'per_page' => 25,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '( c.first_name LIKE %s OR c.last_name LIKE %s OR c.email LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( in_array( $args['status'], self::statuses(), true ) ) {
			$where[]  = 'c.status = %s';
			$params[] = $args['status'];
		}

		$has_tag = $args['tag_id'] > 0;

		// FROM clause with %i table identifiers; join only when filtering by tag.
		$from      = 'FROM %i c';
		$from_args = array( $table );
		if ( $has_tag ) {
			$from        = 'FROM %i c INNER JOIN %i ct ON ct.contact_id = c.id';
			$from_args[] = $c_tags;
		}

		$tag_where = '';
		$tag_args  = array();
		if ( $has_tag ) {
			$tag_where  = ' AND ct.tag_id = %d';
			$tag_args[] = (int) $args['tag_id'];
		}

		$where_sql = implode( ' AND ', $where );

		$orderby = in_array( $args['orderby'], self::sortable(), true ) ? $args['orderby'] : 'created';
		$asc     = ( 'ASC' === strtoupper( (string) $args['order'] ) );

		$per_page = min( 100, max( 1, (int) $args['per_page'] ) );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		// Total count. $count_sql is assembled only from literal SQL fragments
		// and %i/%s/%d placeholders (built above); every user-supplied value is
		// passed through $count_args to $wpdb->prepare(). PHPCS cannot trace the
		// conditional assembly, so the prepared-SQL sniff is suppressed here.
		$count_sql  = "SELECT COUNT(DISTINCT c.id) $from WHERE $where_sql$tag_where";
		$count_args = array_merge( $from_args, $params, $tag_args );
		$total      = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Page of rows. Same guarantees as the count query: the ORDER BY column
		// is a validated identifier passed as %i, and the direction is a
		// hardcoded literal chosen from a boolean — no user input in the string.
		$list_sql  = "SELECT DISTINCT c.* $from WHERE $where_sql$tag_where ORDER BY %i " . ( $asc ? 'ASC' : 'DESC' ) . ' LIMIT %d OFFSET %d';
		$list_args = array_merge( $from_args, $params, $tag_args, array( $orderby, $per_page, $offset ) );
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
	 * Fetches a single contact, formatted for output.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Contact ID.
	 * @return array|null Formatted contact, or null if not found.
	 */
	public static function get( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Rayetun_CRM_DB::table( 'contacts' ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ? self::format( $row ) : null;
	}

	/**
	 * Creates a contact. Rejects a duplicate email.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw, unsanitised input.
	 * @return int|WP_Error New contact ID, or WP_Error on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$fields = self::sanitize( $data );

		if ( '' === $fields['email'] ) {
			return new WP_Error( 'rayetun_crm_email_required', __( 'A contact email is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		if ( self::email_exists( $fields['email'] ) ) {
			return new WP_Error( 'rayetun_crm_duplicate_email', __( 'A contact with this email already exists.', 'rayetun-crm' ), array( 'status' => 409 ) );
		}

		$now                     = current_time( 'mysql' );
		$fields['created']       = $now;
		$fields['updated']       = $now;
		$fields['last_activity'] = $now;
		$fields['owner_id']      = get_current_user_id();

		$ok = $wpdb->insert( Rayetun_CRM_DB::table( 'contacts' ), $fields, self::formats_for( $fields ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( false === $ok ) {
			return new WP_Error( 'rayetun_crm_insert_failed', __( 'Could not create the contact.', 'rayetun-crm' ), array( 'status' => 500 ) );
		}

		$id = (int) $wpdb->insert_id;

		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			self::set_tags( $id, $data['tags'] );
		}

		if ( isset( $data['custom'] ) && is_array( $data['custom'] ) ) {
			Rayetun_CRM_Custom_Fields::save_values( $id, $data['custom'] );
		}

		/**
		 * Fires after a contact is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $id     New contact ID.
		 * @param array $fields Inserted, sanitised fields.
		 */
		do_action( 'rayetun_crm_contact_created', $id, $fields );

		return $id;
	}

	/**
	 * Updates a contact.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Contact ID.
	 * @param array $data Raw, unsanitised input (partial allowed).
	 * @return true|WP_Error True on success, WP_Error otherwise.
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$id      = (int) $id;
		$current = self::get( $id );

		if ( ! $current ) {
			return new WP_Error( 'rayetun_crm_not_found', __( 'Contact not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$fields = self::sanitize( $data );

		if ( '' !== $fields['email'] && strtolower( $fields['email'] ) !== strtolower( $current['email'] ) && self::email_exists( $fields['email'] ) ) {
			return new WP_Error( 'rayetun_crm_duplicate_email', __( 'A contact with this email already exists.', 'rayetun-crm' ), array( 'status' => 409 ) );
		}

		// Only persist keys actually provided in the request.
		$update = array();
		foreach ( self::writable_keys() as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$update[ $key ] = $fields[ $key ];
			}
		}

		$update['updated'] = current_time( 'mysql' );

		if ( $update ) {
			$wpdb->update( Rayetun_CRM_DB::table( 'contacts' ), $update, array( 'id' => $id ), self::formats_for( $update ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			self::set_tags( $id, $data['tags'] );
		}

		if ( isset( $data['custom'] ) && is_array( $data['custom'] ) ) {
			Rayetun_CRM_Custom_Fields::save_values( $id, $data['custom'] );
		}

		/**
		 * Fires after a contact is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $id       Contact ID.
		 * @param array $changes  Changed fields (may include 'updated').
		 * @param array $previous Previous contact state.
		 */
		do_action( 'rayetun_crm_contact_updated', $id, $update, $current );

		return true;
	}

	/**
	 * Deletes a contact and its tag relationships.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Contact ID.
	 * @return true|WP_Error
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = (int) $id;

		if ( ! self::get( $id ) ) {
			return new WP_Error( 'rayetun_crm_not_found', __( 'Contact not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$wpdb->delete( Rayetun_CRM_DB::table( 'contacts' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Rayetun_CRM_DB::table( 'contact_tags' ), array( 'contact_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return true;
	}

	/**
	 * Builds a CSV export of contacts matching the given filters.
	 *
	 * Includes standard columns plus one column per custom field.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query filters (search, status, tag_id).
	 * @return string CSV text (CRLF line endings).
	 */
	public static function export_csv( array $args = array() ) {
		$defs = Rayetun_CRM_Custom_Fields::definitions( 'contact' );

		$headers = array( 'email', 'first_name', 'last_name', 'phone', 'company', 'website', 'status', 'source', 'lead_score', 'star', 'tags', 'created' );
		foreach ( $defs as $def ) {
			$headers[] = $def['label'];
		}

		$lines = array( self::csv_row( $headers ) );

		$page = 1;
		do {
			$res = self::query( array_merge( $args, array( 'page' => $page, 'per_page' => 100 ) ) );

			foreach ( $res['items'] as $c ) {
				$tags = implode( ', ', array_map( static function ( $t ) {
					return $t['name'];
				}, $c['tags'] ) );

				$row = array(
					$c['email'],
					$c['first_name'],
					$c['last_name'],
					$c['phone'],
					$c['company'],
					$c['website'],
					$c['status'],
					$c['source'],
					$c['lead_score'],
					$c['star'] ? '1' : '0',
					$tags,
					$c['created'],
				);

				foreach ( $defs as $def ) {
					$row[] = isset( $c['custom'][ $def['field_key'] ] ) ? $c['custom'][ $def['field_key'] ] : '';
				}

				$lines[] = self::csv_row( $row );
			}

			$page++;
		} while ( count( $res['items'] ) > 0 && ( ( $page - 1 ) * 100 ) < $res['total'] );

		return implode( "\r\n", $lines );
	}

	/**
	 * Escapes and joins one CSV row.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Cell values.
	 * @return string
	 */
	private static function csv_row( array $fields ) {
		$cells = array();

		foreach ( $fields as $value ) {
			$value = (string) $value;
			if ( preg_match( '/[",\r\n]/', $value ) ) {
				$value = '"' . str_replace( '"', '""', $value ) . '"';
			}
			$cells[] = $value;
		}

		return implode( ',', $cells );
	}

	/**
	 * Imports an array of mapped rows.
	 *
	 * Each row is a map of contact field => value (tags as a comma string).
	 * Duplicate emails are skipped or updated per $duplicate.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $rows      Mapped rows.
	 * @param string $duplicate 'skip' or 'update'.
	 * @return array{created:int,updated:int,skipped:int,errors:int}
	 */
	public static function import_rows( array $rows, $duplicate = 'skip' ) {
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = 0;

		foreach ( $rows as $raw ) {
			if ( ! is_array( $raw ) ) {
				$errors++;
				continue;
			}

			$email = isset( $raw['email'] ) ? sanitize_email( wp_unslash( $raw['email'] ) ) : '';
			if ( '' === $email ) {
				$errors++;
				continue;
			}

			if ( isset( $raw['tags'] ) && is_string( $raw['tags'] ) ) {
				$raw['tags'] = array_values( array_filter( array_map( 'trim', explode( ',', $raw['tags'] ) ) ) );
			}

			$existing = self::id_by_email( $email );

			if ( $existing ) {
				if ( 'update' === $duplicate ) {
					$result = self::update( $existing, $raw );
					if ( true === $result ) {
						$updated++;
					} else {
						$errors++;
					}
				} else {
					$skipped++;
				}
			} else {
				$result = self::create( $raw );
				if ( is_wp_error( $result ) ) {
					$errors++;
				} else {
					$created++;
				}
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	/**
	 * Returns the contact ID matching an email, or 0. Public wrapper used by
	 * lead-capture integrations.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	public static function find_by_email( $email ) {
		return self::id_by_email( sanitize_email( $email ) );
	}

	/**
	 * Returns a contact ID for an email, or 0.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	private static function id_by_email( $email ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE email = %s', Rayetun_CRM_DB::table( 'contacts' ), $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Applies a bulk action to a set of contacts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action One of: delete, status, tag.
	 * @param int[]  $ids    Contact IDs.
	 * @param string $value  Action value (status slug, or comma-separated tags).
	 * @return int|WP_Error Number of contacts affected, or WP_Error.
	 */
	public static function bulk( $action, array $ids, $value = '' ) {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		if ( empty( $ids ) ) {
			return new WP_Error( 'rayetun_crm_no_ids', __( 'No contacts were selected.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$affected = 0;

		switch ( $action ) {
			case 'delete':
				foreach ( $ids as $id ) {
					if ( true === self::delete( $id ) ) {
						$affected++;
					}
				}
				break;

			case 'status':
				$status = sanitize_key( $value );
				if ( ! in_array( $status, self::statuses(), true ) ) {
					return new WP_Error( 'rayetun_crm_bad_status', __( 'Invalid status.', 'rayetun-crm' ), array( 'status' => 400 ) );
				}
				foreach ( $ids as $id ) {
					if ( true === self::update( $id, array( 'status' => $status ) ) ) {
						$affected++;
					}
				}
				break;

			case 'tag':
				$names = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
				if ( empty( $names ) ) {
					return new WP_Error( 'rayetun_crm_no_tags', __( 'No tags were provided.', 'rayetun-crm' ), array( 'status' => 400 ) );
				}
				foreach ( $ids as $id ) {
					if ( self::get( $id ) ) {
						self::add_tags( $id, $names );
						$affected++;
					}
				}
				break;

			default:
				return new WP_Error( 'rayetun_crm_bad_action', __( 'Unknown bulk action.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		return $affected;
	}

	/**
	 * Writable contact columns (excludes id, timestamps, owner).
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	private static function writable_keys() {
		return array( 'email', 'first_name', 'last_name', 'phone', 'company_id', 'website', 'status', 'source', 'lead_score', 'star', 'email_status' );
	}

	/**
	 * Sanitises raw input into a full column map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input.
	 * @return array Sanitised column => value map.
	 */
	private static function sanitize( array $data ) {
		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'lead';
		if ( ! in_array( $status, self::statuses(), true ) ) {
			$status = 'lead';
		}

		$company_id = isset( $data['company_id'] ) ? absint( $data['company_id'] ) : 0;
		if ( ! $company_id && ! empty( $data['company'] ) ) {
			$company_id = self::resolve_company( sanitize_text_field( wp_unslash( $data['company'] ) ) );
		}

		$email_status = isset( $data['email_status'] ) ? sanitize_key( $data['email_status'] ) : 'subscribed';
		if ( ! in_array( $email_status, array( 'subscribed', 'unsubscribed' ), true ) ) {
			$email_status = 'subscribed';
		}

		return array(
			'email'      => isset( $data['email'] ) ? sanitize_email( wp_unslash( $data['email'] ) ) : '',
			'first_name' => isset( $data['first_name'] ) ? sanitize_text_field( wp_unslash( $data['first_name'] ) ) : '',
			'last_name'  => isset( $data['last_name'] ) ? sanitize_text_field( wp_unslash( $data['last_name'] ) ) : '',
			'phone'      => isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( $data['phone'] ) ) : '',
			'company_id' => $company_id,
			'website'    => isset( $data['website'] ) ? esc_url_raw( wp_unslash( $data['website'] ) ) : '',
			'status'     => $status,
			'source'     => isset( $data['source'] ) ? sanitize_text_field( wp_unslash( $data['source'] ) ) : '',
			'lead_score' => isset( $data['lead_score'] ) ? (int) $data['lead_score'] : 0,
			'star'       => ! empty( $data['star'] ) ? 1 : 0,
			'email_status' => $email_status,
		);
	}

	/**
	 * Returns wpdb format specifiers matching the given column map.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Column => value map.
	 * @return string[] Ordered format specifiers.
	 */
	private static function formats_for( array $fields ) {
		$int_cols = array( 'company_id', 'lead_score', 'star', 'owner_id' );
		$formats  = array();

		foreach ( array_keys( $fields ) as $key ) {
			$formats[] = in_array( $key, $int_cols, true ) ? '%d' : '%s';
		}

		return $formats;
	}

	/**
	 * Whether a contact already exists with the given email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	private static function email_exists( $email ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE email = %s', Rayetun_CRM_DB::table( 'contacts' ), $email ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Finds or creates a company by name, returning its ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Company name.
	 * @return int Company ID (0 when name is empty).
	 */
	private static function resolve_company( $name ) {
		global $wpdb;

		if ( '' === $name ) {
			return 0;
		}

		$table = Rayetun_CRM_DB::table( 'companies' );
		$id    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE name = %s', $table, $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $id ) {
			return $id;
		}

		$wpdb->insert( $table, array( 'name' => $name, 'created' => current_time( 'mysql' ) ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return (int) $wpdb->insert_id;
	}

	/**
	 * Replaces a contact's tags, creating tag rows as needed.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $contact_id Contact ID.
	 * @param string[] $tag_names  Tag names.
	 * @return void
	 */
	private static function set_tags( $contact_id, array $tag_names ) {
		global $wpdb;

		$tags_table = Rayetun_CRM_DB::table( 'tags' );
		$rel_table  = Rayetun_CRM_DB::table( 'contact_tags' );

		$wpdb->delete( $rel_table, array( 'contact_id' => $contact_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $tag_names as $name ) {
			$name = sanitize_text_field( wp_unslash( $name ) );
			if ( '' === $name ) {
				continue;
			}

			$slug   = sanitize_title( $name );
			$tag_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE slug = %s', $tags_table, $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $tag_id ) {
				$wpdb->insert( $tags_table, array( 'name' => $name, 'slug' => $slug ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tag_id = (int) $wpdb->insert_id;
			}

			$wpdb->insert( $rel_table, array( 'contact_id' => $contact_id, 'tag_id' => $tag_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Adds tags to a contact without removing existing ones.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $contact_id Contact ID.
	 * @param string[] $tag_names  Tag names to add.
	 * @return void
	 */
	public static function add_tags( $contact_id, array $tag_names ) {
		global $wpdb;

		$tags_table = Rayetun_CRM_DB::table( 'tags' );
		$rel_table  = Rayetun_CRM_DB::table( 'contact_tags' );

		foreach ( $tag_names as $name ) {
			$name = sanitize_text_field( wp_unslash( $name ) );
			if ( '' === $name ) {
				continue;
			}

			$slug   = sanitize_title( $name );
			$tag_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE slug = %s', $tags_table, $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $tag_id ) {
				$wpdb->insert( $tags_table, array( 'name' => $name, 'slug' => $slug ), array( '%s', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$tag_id = (int) $wpdb->insert_id;
			}

			$exists = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE contact_id = %d AND tag_id = %d', $rel_table, (int) $contact_id, $tag_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $exists ) {
				$wpdb->insert( $rel_table, array( 'contact_id' => (int) $contact_id, 'tag_id' => $tag_id ), array( '%d', '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
	}

	/**
	 * Returns a contact's tags as id/name pairs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $contact_id Contact ID.
	 * @return array[] List of array{ id:int, name:string }.
	 */
	private static function get_tags( $contact_id ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT t.id, t.name FROM %i t INNER JOIN %i ct ON ct.tag_id = t.id WHERE ct.contact_id = %d ORDER BY t.name ASC',
			Rayetun_CRM_DB::table( 'tags' ),
			Rayetun_CRM_DB::table( 'contact_tags' ),
			(int) $contact_id
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'id'   => (int) $row->id,
				'name' => $row->name,
			);
		}

		return $out;
	}

	/**
	 * Returns a company's name by ID (empty string when none).
	 *
	 * @since 1.0.0
	 *
	 * @param int $company_id Company ID.
	 * @return string
	 */
	private static function company_name( $company_id ) {
		global $wpdb;

		if ( ! $company_id ) {
			return '';
		}

		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM %i WHERE id = %d', Rayetun_CRM_DB::table( 'companies' ), $company_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Shapes a raw DB row into the REST representation.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw contact row.
	 * @return array
	 */
	private static function format( $row ) {
		$name = trim( $row->first_name . ' ' . $row->last_name );

		return array(
			'id'            => (int) $row->id,
			'email'         => $row->email,
			'first_name'    => $row->first_name,
			'last_name'     => $row->last_name,
			'full_name'     => '' !== $name ? $name : $row->email,
			'phone'         => $row->phone,
			'company_id'    => (int) $row->company_id,
			'company'       => self::company_name( (int) $row->company_id ),
			'website'       => $row->website,
			'status'        => $row->status,
			'source'        => $row->source,
			'lead_score'    => (int) $row->lead_score,
			'heat'          => Rayetun_CRM_Scoring::heat( (int) $row->lead_score ),
			'star'          => (bool) $row->star,
			'email_status'  => isset( $row->email_status ) && $row->email_status ? $row->email_status : 'subscribed',
			'tags'          => self::get_tags( $row->id ),
			'custom'        => Rayetun_CRM_Custom_Fields::get_values( (int) $row->id ),
			'created'       => $row->created,
			'updated'       => $row->updated,
			'last_activity' => $row->last_activity,
		);
	}
}
