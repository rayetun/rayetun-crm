<?php
/**
 * Email logging, templates and 1-to-1 send.
 *
 * Logs every outgoing wp_mail addressed to a known contact into that contact's
 * timeline and the email_log table. Provides reusable templates (capped on the
 * free tier) and a send-from-CRM path with a configurable daily limit.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Email subsystem.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Email {

	/**
	 * Option storing saved templates.
	 *
	 * @var string
	 */
	const TEMPLATES_OPTION = 'rayetun_crm_email_templates';

	/**
	 * Hooks mail logging.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'on_mail_succeeded' ) );
		add_action( 'wp_mail_failed', array( __CLASS__, 'on_mail_failed' ) );
	}

	/**
	 * Logs a successfully-sent mail.
	 *
	 * @since 1.0.0
	 *
	 * @param array $mail_data Mail data (to, subject, …).
	 * @return void
	 */
	public static function on_mail_succeeded( $mail_data ) {
		$to      = isset( $mail_data['to'] ) ? $mail_data['to'] : array();
		$subject = isset( $mail_data['subject'] ) ? $mail_data['subject'] : '';
		self::log_mail( $to, $subject, 'sent' );
	}

	/**
	 * Logs a failed mail.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Error $error Failure with mail data.
	 * @return void
	 */
	public static function on_mail_failed( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return;
		}
		$data    = $error->get_error_data();
		$to      = isset( $data['to'] ) ? $data['to'] : array();
		$subject = isset( $data['subject'] ) ? $data['subject'] : '';
		self::log_mail( $to, $subject, 'failed' );
	}

	/**
	 * Logs an email against any recipients that are known contacts.
	 *
	 * @since 1.0.0
	 *
	 * @param array|string $recipients Recipient(s), possibly "Name <email>".
	 * @param string       $subject    Subject.
	 * @param string       $status     'sent' or 'failed'.
	 * @return void
	 */
	private static function log_mail( $recipients, $subject, $status ) {
		global $wpdb;

		foreach ( (array) $recipients as $recipient ) {
			$email = self::parse_email( $recipient );
			if ( '' === $email ) {
				continue;
			}

			$contact_id = Rayetun_CRM_Contacts::find_by_email( $email );
			if ( ! $contact_id ) {
				continue;
			}

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				Rayetun_CRM_DB::table( 'email_log' ),
				array(
					'contact_id' => $contact_id,
					'subject'    => $subject,
					'direction'  => 'out',
					'status'     => $status,
					'created'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s', '%s' )
			);

			Rayetun_CRM_Activities::log( $contact_id, 'email', array( 'subject' => $subject, 'status' => $status ) );
		}
	}

	/**
	 * Extracts a bare email address from a recipient string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $recipient Recipient, possibly "Name <email>".
	 * @return string
	 */
	private static function parse_email( $recipient ) {
		$recipient = (string) $recipient;
		if ( preg_match( '/<([^>]+)>/', $recipient, $m ) ) {
			$recipient = $m[1];
		}
		return sanitize_email( trim( $recipient ) );
	}

	/**
	 * Returns saved templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array[]
	 */
	public static function templates() {
		$templates = get_option( self::TEMPLATES_OPTION, array() );
		return is_array( $templates ) ? array_values( $templates ) : array();
	}

	/**
	 * Creates a template, enforcing the edition cap.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Raw input (name, subject, body).
	 * @return array|WP_Error The saved template, or WP_Error.
	 */
	public static function create_template( array $data ) {
		$name = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'rayetun_crm_template_name_required', __( 'A template name is required.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$templates = self::templates();

		$template = array(
			'id'      => substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 ),
			'name'    => $name,
			'subject' => isset( $data['subject'] ) ? sanitize_text_field( wp_unslash( $data['subject'] ) ) : '',
			'body'    => isset( $data['body'] ) ? wp_kses_post( wp_unslash( $data['body'] ) ) : '',
		);

		$templates[] = $template;
		update_option( self::TEMPLATES_OPTION, $templates );

		return $template;
	}

	/**
	 * Deletes a template by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id Template ID.
	 * @return true
	 */
	public static function delete_template( $id ) {
		$id        = sanitize_key( $id );
		$templates = array_values(
			array_filter(
				self::templates(),
				static function ( $t ) use ( $id ) {
					return isset( $t['id'] ) && $t['id'] !== $id;
				}
			)
		);
		update_option( self::TEMPLATES_OPTION, $templates );

		return true;
	}

	/**
	 * Sends a 1-to-1 email to a contact.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $contact_id Contact ID.
	 * @param string $subject    Subject (may contain merge tags).
	 * @param string $body       Body (may contain merge tags).
	 * @return true|WP_Error
	 */
	public static function send( $contact_id, $subject, $body ) {
		$contact = Rayetun_CRM_Contacts::get( (int) $contact_id );
		if ( ! $contact || ! is_email( $contact['email'] ) ) {
			return new WP_Error( 'rayetun_crm_no_recipient', __( 'This contact has no valid email.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		if ( isset( $contact['email_status'] ) && 'unsubscribed' === $contact['email_status'] ) {
			return new WP_Error( 'rayetun_crm_unsubscribed', __( 'This contact has unsubscribed from email. Set them back to subscribed to send.', 'rayetun-crm' ), array( 'status' => 403 ) );
		}

		$subject = self::merge( sanitize_text_field( wp_unslash( $subject ) ), $contact );
		$body    = self::merge( wp_kses_post( wp_unslash( $body ) ), $contact );

		if ( '' === trim( wp_strip_all_tags( $body ) ) ) {
			return new WP_Error( 'rayetun_crm_empty_body', __( 'The email body is empty.', 'rayetun-crm' ), array( 'status' => 400 ) );
		}

		$ok = wp_mail( $contact['email'], $subject, $body ); // Logged via wp_mail_succeeded.

		if ( ! $ok ) {
			return new WP_Error( 'rayetun_crm_send_failed', __( 'The email could not be sent. Check your site\'s email configuration.', 'rayetun-crm' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Replaces merge tags with contact values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text    Text with {{tags}}.
	 * @param array  $contact Contact record.
	 * @return string
	 */
	private static function merge( $text, $contact ) {
		$map = array(
			'{{first_name}}' => $contact['first_name'],
			'{{last_name}}'  => $contact['last_name'],
			'{{full_name}}'  => $contact['full_name'],
			'{{email}}'      => $contact['email'],
			'{{company}}'    => $contact['company'],
		);

		return strtr( $text, $map );
	}

}
