<?php
/**
 * Third-party form integrations.
 *
 * Auto-captures leads from Contact Form 7, WPForms and Fluent Forms when those
 * plugins are active. Each adapter flattens the submission into a key => value
 * map, which a shared heuristic mapper turns into a lead for the capture core.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles supported form-plugin integrations.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Integrations {

	/**
	 * Hooks each integration whose plugin is active.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		if ( defined( 'WPCF7_VERSION' ) ) {
			add_action( 'wpcf7_mail_sent', array( __CLASS__, 'capture_cf7' ) );
		}

		if ( defined( 'WPFORMS_VERSION' ) ) {
			add_action( 'wpforms_process_complete', array( __CLASS__, 'capture_wpforms' ), 10, 4 );
		}

		if ( defined( 'FLUENTFORM_VERSION' ) || function_exists( 'wpFluentForm' ) ) {
			add_action( 'fluentform/submission_inserted', array( __CLASS__, 'capture_fluent' ), 10, 3 );
		}
	}

	/**
	 * Contact Form 7 handler.
	 *
	 * @since 1.0.0
	 *
	 * @param object $form CF7 form object.
	 * @return void
	 */
	public static function capture_cf7( $form ) {
		if ( ! class_exists( 'WPCF7_Submission' ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();
		$fields = array();

		foreach ( (array) $posted as $key => $value ) {
			if ( 0 === strpos( (string) $key, '_' ) ) {
				continue; // CF7 special fields.
			}
			$fields[ $key ] = is_array( $value ) ? implode( ' ', $value ) : $value;
		}

		$label = ( is_object( $form ) && method_exists( $form, 'title' ) ) ? $form->title() : __( 'Contact Form 7', 'rayetun-crm' );
		self::capture_from_fields( $fields, $label, __( 'Contact Form 7', 'rayetun-crm' ) );
	}

	/**
	 * WPForms handler.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields    Submitted fields.
	 * @param array $entry     Entry data.
	 * @param array $form_data Form configuration.
	 * @return void
	 */
	public static function capture_wpforms( $fields, $entry, $form_data ) {
		unset( $entry );

		$flat = array();
		foreach ( (array) $fields as $field ) {
			if ( ! isset( $field['value'] ) ) {
				continue;
			}
			$key          = isset( $field['type'] ) ? $field['type'] : ( isset( $field['name'] ) ? $field['name'] : 'field' );
			$flat[ $key ] = is_array( $field['value'] ) ? implode( ' ', $field['value'] ) : $field['value'];
		}

		$label = isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : __( 'WPForms', 'rayetun-crm' );
		self::capture_from_fields( $flat, $label, __( 'WPForms', 'rayetun-crm' ) );
	}

	/**
	 * Fluent Forms handler.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $entry_id  Entry ID.
	 * @param array $form_data Submitted data.
	 * @param object $form     Form object.
	 * @return void
	 */
	public static function capture_fluent( $entry_id, $form_data, $form ) {
		unset( $entry_id );

		$flat = array();
		foreach ( (array) $form_data as $key => $value ) {
			if ( is_array( $value ) ) {
				// Name fields arrive as first_name/last_name arrays.
				if ( isset( $value['first_name'] ) || isset( $value['last_name'] ) ) {
					$flat['first_name'] = isset( $value['first_name'] ) ? $value['first_name'] : '';
					$flat['last_name']  = isset( $value['last_name'] ) ? $value['last_name'] : '';
					continue;
				}
				$value = implode( ' ', array_filter( (array) $value ) );
			}
			$flat[ $key ] = $value;
		}

		$label = ( is_object( $form ) && isset( $form->title ) ) ? $form->title : __( 'Fluent Forms', 'rayetun-crm' );
		self::capture_from_fields( $flat, $label, __( 'Fluent Forms', 'rayetun-crm' ) );
	}

	/**
	 * Maps a flat field set into a lead and captures it.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $fields Flat key => value map.
	 * @param string $label  Form title (for the activity).
	 * @param string $source Source label.
	 * @return void
	 */
	private static function capture_from_fields( array $fields, $label, $source ) {
		$lead = self::extract_lead( $fields );

		if ( '' === $lead['email'] ) {
			return; // No email — nothing to capture.
		}

		$lead['form']   = $label;
		$lead['source'] = $source;

		Rayetun_CRM_Lead_Capture::capture( $lead );
	}

	/**
	 * Heuristically maps arbitrary form fields to lead fields.
	 *
	 * Recognises email by value/key, and name/phone/message by key name. Exposed
	 * for testability and left filterable so add-ons can refine mapping.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Flat key => value map.
	 * @return array Lead fields (email, first_name, last_name, phone, message).
	 */
	public static function extract_lead( array $fields ) {
		$lead = array(
			'email'      => '',
			'first_name' => '',
			'last_name'  => '',
			'phone'      => '',
			'message'    => '',
		);

		$name = '';

		foreach ( $fields as $key => $value ) {
			$key   = strtolower( (string) $key );
			$value = is_array( $value ) ? implode( ' ', $value ) : trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			if ( 'first_name' === $key && '' === $lead['first_name'] ) {
				$lead['first_name'] = $value;
				continue;
			}
			if ( 'last_name' === $key && '' === $lead['last_name'] ) {
				$lead['last_name'] = $value;
				continue;
			}

			if ( '' === $lead['email'] && is_email( $value ) ) {
				$lead['email'] = $value;
				continue;
			}

			if ( '' === $lead['phone'] && ( false !== strpos( $key, 'phone' ) || false !== strpos( $key, 'tel' ) ) ) {
				$lead['phone'] = $value;
				continue;
			}

			if ( '' === $lead['message'] && ( false !== strpos( $key, 'message' ) || false !== strpos( $key, 'comment' ) || false !== strpos( $key, 'msg' ) || false !== strpos( $key, 'textarea' ) ) ) {
				$lead['message'] = $value;
				continue;
			}

			if ( '' === $name && false !== strpos( $key, 'name' ) && false === strpos( $key, 'user' ) && false === strpos( $key, 'file' ) ) {
				$name = $value;
			}
		}

		// Split a combined name if we have no explicit first/last.
		if ( '' === $lead['first_name'] && '' !== $name ) {
			$parts              = preg_split( '/\s+/', $name, 2 );
			$lead['first_name'] = $parts[0];
			$lead['last_name']  = isset( $parts[1] ) ? $parts[1] : $lead['last_name'];
		}

		/**
		 * Filters the mapped lead before capture.
		 *
		 * @since 1.0.0
		 *
		 * @param array $lead   Mapped lead fields.
		 * @param array $fields Original flat field map.
		 */
		return apply_filters( 'rayetun_crm_extract_lead', $lead, $fields );
	}
}
