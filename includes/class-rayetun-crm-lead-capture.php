<?php
/**
 * Lead-capture core.
 *
 * A single entry point that every capture source (the native form, and the
 * Contact Form 7 / WPForms / Fluent Forms integrations) funnels through. It
 * finds-or-creates the contact, applies tags and source, and records a
 * form-submission activity carrying the message and any UTM data.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns an inbound lead into a CRM contact + activity.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Lead_Capture {

	/**
	 * Recognised UTM keys.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function utm_keys() {
		return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' );
	}

	/**
	 * Captures a lead.
	 *
	 * @since 1.0.0
	 *
	 * @param array $lead {
	 *     Lead data.
	 *
	 *     @type string   $email        Required. Lead email.
	 *     @type string   $first_name   Optional.
	 *     @type string   $last_name    Optional.
	 *     @type string   $phone        Optional.
	 *     @type string   $company      Optional.
	 *     @type string   $source       Optional. Human source label.
	 *     @type string[] $tags         Optional. Tags to apply.
	 *     @type string   $message      Optional. Submitted message.
	 *     @type array    $utm          Optional. UTM key => value map.
	 *     @type string   $landing_page Optional. URL the lead came from.
	 *     @type string   $form         Optional. Form label for the activity.
	 * }
	 * @return int|WP_Error Contact ID, or WP_Error.
	 */
	public static function capture( array $lead ) {
		$email = isset( $lead['email'] ) ? sanitize_email( $lead['email'] ) : '';

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'rayetun_crm_lead_no_email', __( 'A valid email is required.', 'rayetun-crm' ) );
		}

		$utm    = self::clean_utm( isset( $lead['utm'] ) && is_array( $lead['utm'] ) ? $lead['utm'] : array() );
		$tags   = isset( $lead['tags'] ) && is_array( $lead['tags'] ) ? $lead['tags'] : array();
		$source = isset( $lead['source'] ) && '' !== $lead['source']
			? $lead['source']
			: ( isset( $utm['utm_source'] ) ? $utm['utm_source'] : __( 'Website form', 'rayetun-crm' ) );

		$existing = Rayetun_CRM_Contacts::find_by_email( $email );

		if ( $existing ) {
			$contact_id = $existing;
			if ( ! empty( $tags ) ) {
				Rayetun_CRM_Contacts::add_tags( $contact_id, $tags );
			}
		} else {
			$contact_id = Rayetun_CRM_Contacts::create(
				array(
					'email'      => $email,
					'first_name' => isset( $lead['first_name'] ) ? $lead['first_name'] : '',
					'last_name'  => isset( $lead['last_name'] ) ? $lead['last_name'] : '',
					'phone'      => isset( $lead['phone'] ) ? $lead['phone'] : '',
					'company'    => isset( $lead['company'] ) ? $lead['company'] : '',
					'source'     => $source,
					'tags'       => $tags,
				)
			);

			if ( is_wp_error( $contact_id ) ) {
				return $contact_id;
			}
		}

		Rayetun_CRM_Activities::log(
			$contact_id,
			'form_submission',
			array(
				'form'         => isset( $lead['form'] ) ? sanitize_text_field( $lead['form'] ) : '',
				'message'      => isset( $lead['message'] ) ? sanitize_textarea_field( $lead['message'] ) : '',
				'utm'          => $utm,
				'landing_page' => isset( $lead['landing_page'] ) ? esc_url_raw( $lead['landing_page'] ) : '',
			)
		);

		/**
		 * Fires after a lead has been captured from any source.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $contact_id Captured contact ID.
		 * @param array $lead       The inbound lead data.
		 */
		do_action( 'rayetun_crm_lead_captured', $contact_id, $lead );

		return $contact_id;
	}

	/**
	 * Sanitises a UTM map to recognised keys and safe values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $utm Raw UTM map.
	 * @return array<string,string>
	 */
	private static function clean_utm( array $utm ) {
		$clean = array();

		foreach ( self::utm_keys() as $key ) {
			if ( ! empty( $utm[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( wp_unslash( $utm[ $key ] ) );
			}
		}

		return $clean;
	}
}
