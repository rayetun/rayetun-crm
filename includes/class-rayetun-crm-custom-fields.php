<?php
/**
 * Custom fields model.
 *
 * Defines extra, admin-configurable fields on an entity (contacts for now) and
 * stores their per-record values. Definitions live in one table; values in a
 * companion table keyed by field and entity id.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Repository for custom field definitions and values.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Custom_Fields {

	/**
	 * Supported field types.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( 'text', 'number', 'date', 'select', 'checkbox', 'url' );
	}

	/**
	 * Returns all field definitions for an entity, ordered.
	 *
	 * @since 1.0.0
	 *
	 * @param string $entity Entity slug. Default 'contact'.
	 * @return array[] List of definition arrays.
	 */
	public static function definitions( $entity = 'contact' ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT * FROM %i WHERE entity = %s ORDER BY sort ASC, id ASC',
			Rayetun_CRM_DB::table( 'custom_fields' ),
			sanitize_key( $entity )
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = self::format_definition( $row );
		}

		return $out;
	}

	/**
	 * Returns a single definition.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Field ID.
	 * @return array|null
	 */
	public static function get_definition( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Rayetun_CRM_DB::table( 'custom_fields' ), (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return $row ? self::format_definition( $row ) : null;
	}

	/**
	 * Creates a field definition.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input.
	 * @return int|WP_Error New field ID, or WP_Error.
	 */
	public static function create_field( array $data ) {
		global $wpdb;

		$label = isset( $data['label'] ) ? sanitize_text_field( wp_unslash( $data['label'] ) ) : '';
		if ( '' === $label ) {
			return new WP_Error( 'rayetun_crm_field_label_required', __( 'A field label is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$type = isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'text';
		if ( ! in_array( $type, self::types(), true ) ) {
			$type = 'text';
		}

		$entity  = isset( $data['entity'] ) ? sanitize_key( $data['entity'] ) : 'contact';
		$options = self::sanitize_options( isset( $data['options'] ) ? $data['options'] : array() );
		$key     = self::unique_key( $label, $entity );

		$next_sort = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(MAX(sort),0)+1 FROM %i WHERE entity = %s', Rayetun_CRM_DB::table( 'custom_fields' ), $entity ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			Rayetun_CRM_DB::table( 'custom_fields' ),
			array(
				'entity'    => $entity,
				'label'     => $label,
				'field_key' => $key,
				'type'      => $type,
				'options'   => wp_json_encode( $options ),
				'sort'      => $next_sort,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Updates a field definition (label, type, options).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id   Field ID.
	 * @param array $data Raw input.
	 * @return true|WP_Error
	 */
	public static function update_field( $id, array $data ) {
		global $wpdb;

		$def = self::get_definition( $id );
		if ( ! $def ) {
			return new WP_Error( 'rayetun_crm_field_not_found', __( 'Field not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$update = array();
		$format = array();

		if ( isset( $data['label'] ) ) {
			$label = sanitize_text_field( wp_unslash( $data['label'] ) );
			if ( '' !== $label ) {
				$update['label'] = $label;
				$format[]        = '%s';
			}
		}

		if ( isset( $data['type'] ) ) {
			$type = sanitize_key( $data['type'] );
			if ( in_array( $type, self::types(), true ) ) {
				$update['type'] = $type;
				$format[]       = '%s';
			}
		}

		if ( isset( $data['options'] ) ) {
			$update['options'] = wp_json_encode( self::sanitize_options( $data['options'] ) );
			$format[]          = '%s';
		}

		if ( $update ) {
			$wpdb->update( Rayetun_CRM_DB::table( 'custom_fields' ), $update, array( 'id' => (int) $id ), $format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return true;
	}

	/**
	 * Deletes a field definition and all its stored values.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Field ID.
	 * @return true|WP_Error
	 */
	public static function delete_field( $id ) {
		global $wpdb;

		if ( ! self::get_definition( $id ) ) {
			return new WP_Error( 'rayetun_crm_field_not_found', __( 'Field not found.', 'rayetun-crm' ), array( 'status' => 404 ) );
		}

		$wpdb->delete( Rayetun_CRM_DB::table( 'custom_fields' ), array( 'id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Rayetun_CRM_DB::table( 'custom_field_values' ), array( 'field_id' => (int) $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		return true;
	}

	/**
	 * Returns an entity's custom values keyed by field_key.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $entity_id Entity (contact) ID.
	 * @param string $entity    Entity slug. Default 'contact'.
	 * @return array<string,string>
	 */
	public static function get_values( $entity_id, $entity = 'contact' ) {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			'SELECT f.field_key, v.value FROM %i v INNER JOIN %i f ON f.id = v.field_id WHERE v.entity_id = %d AND f.entity = %s',
			Rayetun_CRM_DB::table( 'custom_field_values' ),
			Rayetun_CRM_DB::table( 'custom_fields' ),
			(int) $entity_id,
			sanitize_key( $entity )
		) );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row->field_key ] = $row->value;
		}

		return $out;
	}

	/**
	 * Saves custom values for an entity (keyed by field_key), typed per field.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $entity_id Entity ID.
	 * @param array  $values    Map of field_key => raw value.
	 * @param string $entity    Entity slug. Default 'contact'.
	 * @return void
	 */
	public static function save_values( $entity_id, array $values, $entity = 'contact' ) {
		global $wpdb;

		$val_table = Rayetun_CRM_DB::table( 'custom_field_values' );

		foreach ( self::definitions( $entity ) as $def ) {
			$key = $def['field_key'];

			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$clean = self::sanitize_value( $def, $values[ $key ] );

			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE field_id = %d AND entity_id = %d', $val_table, (int) $def['id'], (int) $entity_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( $existing ) {
				$wpdb->update( $val_table, array( 'value' => $clean ), array( 'id' => $existing ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				$wpdb->insert( $val_table, array( 'field_id' => (int) $def['id'], 'entity_id' => (int) $entity_id, 'value' => $clean ), array( '%d', '%d', '%s' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}
	}

	/**
	 * Sanitises a value according to its field definition.
	 *
	 * @since 1.0.0
	 *
	 * @param array $def   Field definition.
	 * @param mixed $value Raw value.
	 * @return string Stored string representation.
	 */
	private static function sanitize_value( $def, $value ) {
		switch ( $def['type'] ) {
			case 'number':
				return is_numeric( $value ) ? (string) ( $value + 0 ) : '';
			case 'date':
				$value = sanitize_text_field( wp_unslash( (string) $value ) );
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
			case 'checkbox':
				return ! empty( $value ) && 'false' !== $value ? '1' : '0';
			case 'url':
				return esc_url_raw( wp_unslash( (string) $value ) );
			case 'select':
				$value = sanitize_text_field( wp_unslash( (string) $value ) );
				return in_array( $value, $def['options'], true ) ? $value : '';
			case 'text':
			default:
				return sanitize_text_field( wp_unslash( (string) $value ) );
		}
	}

	/**
	 * Sanitises a list of select options.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $options Raw options (array or comma string).
	 * @return string[]
	 */
	private static function sanitize_options( $options ) {
		if ( is_string( $options ) ) {
			$options = explode( ',', $options );
		}

		if ( ! is_array( $options ) ) {
			return array();
		}

		$clean = array();
		foreach ( $options as $opt ) {
			$opt = sanitize_text_field( wp_unslash( (string) $opt ) );
			if ( '' !== trim( $opt ) ) {
				$clean[] = trim( $opt );
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Builds a unique field_key from a label within an entity.
	 *
	 * @since 1.0.0
	 *
	 * @param string $label  Field label.
	 * @param string $entity Entity slug.
	 * @return string
	 */
	private static function unique_key( $label, $entity ) {
		global $wpdb;

		$base = sanitize_key( str_replace( '-', '_', sanitize_title( $label ) ) );
		if ( '' === $base ) {
			$base = 'field';
		}

		$table = Rayetun_CRM_DB::table( 'custom_fields' );
		$key   = $base;
		$i     = 2;

		while ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE entity = %s AND field_key = %s', $table, $entity, $key ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$key = $base . '_' . $i;
			$i++;
		}

		return $key;
	}

	/**
	 * Shapes a raw definition row for output.
	 *
	 * @since 1.0.0
	 *
	 * @param object $row Raw row.
	 * @return array
	 */
	private static function format_definition( $row ) {
		$options = json_decode( (string) $row->options, true );

		return array(
			'id'        => (int) $row->id,
			'entity'    => $row->entity,
			'label'     => $row->label,
			'field_key' => $row->field_key,
			'type'      => $row->type,
			'options'   => is_array( $options ) ? $options : array(),
			'sort'      => (int) $row->sort,
		);
	}
}
