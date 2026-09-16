<?php
/**
 * Native lead-capture form.
 *
 * Renders a server-side HTML form via the [rayetun_crm_form] shortcode — no
 * front-end JavaScript. UTM parameters present on the landing page are captured
 * into hidden fields at render time, so they survive the POST without scripts.
 * A hidden honeypot field blocks basic spam bots.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and processes the native lead form.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_Forms {

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'rayetun_crm_lead';

	/**
	 * Registers the shortcode, submit handlers and assets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( 'rayetun_crm_form', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_nopriv_rayetun_crm_lead', array( __CLASS__, 'handle_submit' ) );
		add_action( 'admin_post_rayetun_crm_lead', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		self::register_block();
	}

	/**
	 * Registers the Gutenberg lead-form block (dynamic; rendered in PHP).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'rayetun-crm-lead-form-block',
			RAYETUN_CRM_URL . 'admin/js/lead-form-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			RAYETUN_CRM_VERSION,
			true
		);
		wp_set_script_translations( 'rayetun-crm-lead-form-block', 'rayetun-crm', RAYETUN_CRM_PATH . 'languages' );

		wp_register_style( 'rayetun-crm-lead-form-block-editor', RAYETUN_CRM_URL . 'admin/css/block-editor.css', array(), RAYETUN_CRM_VERSION );

		// Load the front-end form styles inside the editor so the live preview
		// matches the front end.
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_form_style' ) );

		register_block_type(
			'rayetun-crm/lead-form',
			array(
				'api_version'     => 2,
				'editor_script'   => 'rayetun-crm-lead-form-block',
				'editor_style'    => 'rayetun-crm-lead-form-block-editor',
				'render_callback' => array( __CLASS__, 'render_block' ),
				'attributes'      => array(
					'title'          => array( 'type' => 'string', 'default' => __( 'Get in touch', 'rayetun-crm' ) ),
					'button'         => array( 'type' => 'string', 'default' => __( 'Send', 'rayetun-crm' ) ),
					'tags'           => array( 'type' => 'string', 'default' => '' ),
					'source'         => array( 'type' => 'string', 'default' => '' ),
					'show_last_name' => array( 'type' => 'boolean', 'default' => true ),
					'show_phone'     => array( 'type' => 'boolean', 'default' => true ),
					'show_message'   => array( 'type' => 'boolean', 'default' => true ),
				),
			)
		);
	}

	/**
	 * Enqueues the front-end form stylesheet in the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue_editor_form_style() {
		if ( ! wp_style_is( 'rayetun-crm-form', 'registered' ) ) {
			wp_register_style( 'rayetun-crm-form', RAYETUN_CRM_URL . 'public/css/rayetun-crm-form.css', array(), RAYETUN_CRM_VERSION );
		}
		wp_enqueue_style( 'rayetun-crm-form' );
	}

	/**
	 * Block render callback — delegates to the shortcode renderer.
	 *
	 * @since 1.0.0
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return self::render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * Registers (but does not enqueue) the form stylesheet.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_assets() {
		wp_register_style( 'rayetun-crm-form', RAYETUN_CRM_URL . 'public/css/rayetun-crm-form.css', array(), RAYETUN_CRM_VERSION );
	}

	/**
	 * Renders the form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'title'          => __( 'Get in touch', 'rayetun-crm' ),
				'button'         => __( 'Send', 'rayetun-crm' ),
				'tags'           => '',
				'success'        => __( 'Thanks! We will be in touch shortly.', 'rayetun-crm' ),
				'source'         => '',
				'show_last_name' => true,
				'show_phone'     => true,
				'show_message'   => true,
			),
			$atts,
			'rayetun_crm_form'
		);

		$show_last    = self::truthy( $atts['show_last_name'] );
		$show_phone   = self::truthy( $atts['show_phone'] );
		$show_message = self::truthy( $atts['show_message'] );

		wp_enqueue_style( 'rayetun-crm-form' );

		// Success state after a redirect back to this page.
		$status = isset( $_GET['rtcrm_form'] ) ? sanitize_key( wp_unslash( $_GET['rtcrm_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();

		if ( 'ok' === $status ) {
			printf( '<div class="rtcrm-form-notice rtcrm-form-notice--ok">%s</div>', esc_html( $atts['success'] ) );
			return ob_get_clean();
		}

		$landing = home_url( add_query_arg( array() ) );
		?>
		<form class="rtcrm-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php if ( '' !== $atts['title'] ) : ?>
				<h3 class="rtcrm-form__title"><?php echo esc_html( $atts['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( 'err' === $status ) : ?>
				<div class="rtcrm-form-notice rtcrm-form-notice--err"><?php esc_html_e( 'Please check your details and try again.', 'rayetun-crm' ); ?></div>
			<?php endif; ?>

			<div class="rtcrm-form__row">
				<label class="rtcrm-form__field">
					<span><?php esc_html_e( 'First name', 'rayetun-crm' ); ?></span>
					<input type="text" name="rtcrm_first_name" autocomplete="given-name" />
				</label>
				<?php if ( $show_last ) : ?>
					<label class="rtcrm-form__field">
						<span><?php esc_html_e( 'Last name', 'rayetun-crm' ); ?></span>
						<input type="text" name="rtcrm_last_name" autocomplete="family-name" />
					</label>
				<?php endif; ?>
			</div>

			<label class="rtcrm-form__field">
				<span><?php esc_html_e( 'Email', 'rayetun-crm' ); ?> *</span>
				<input type="email" name="rtcrm_email" required autocomplete="email" />
			</label>

			<?php if ( $show_phone ) : ?>
				<label class="rtcrm-form__field">
					<span><?php esc_html_e( 'Phone', 'rayetun-crm' ); ?></span>
					<input type="text" name="rtcrm_phone" autocomplete="tel" />
				</label>
			<?php endif; ?>

			<?php if ( $show_message ) : ?>
				<label class="rtcrm-form__field">
					<span><?php esc_html_e( 'Message', 'rayetun-crm' ); ?></span>
					<textarea name="rtcrm_message" rows="4"></textarea>
				</label>
			<?php endif; ?>

			<?php
			// Honeypot: real users leave this empty; hidden from view via CSS.
			?>
			<div class="rtcrm-form__hp" aria-hidden="true">
				<label><?php esc_html_e( 'Leave this field empty', 'rayetun-crm' ); ?>
					<input type="text" name="rtcrm_website" tabindex="-1" autocomplete="off" />
				</label>
			</div>

			<?php wp_nonce_field( self::NONCE_ACTION, 'rayetun_crm_lead_nonce' ); ?>
			<input type="hidden" name="action" value="rayetun_crm_lead" />
			<input type="hidden" name="rtcrm_tags" value="<?php echo esc_attr( $atts['tags'] ); ?>" />
			<input type="hidden" name="rtcrm_source" value="<?php echo esc_attr( $atts['source'] ); ?>" />
			<input type="hidden" name="rtcrm_landing" value="<?php echo esc_url( $landing ); ?>" />
			<?php
			foreach ( Rayetun_CRM_Lead_Capture::utm_keys() as $key ) {
				$value = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( '' !== $value ) {
					printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $key ), esc_attr( $value ) );
				}
			}
			?>

			<button type="submit" class="rtcrm-form__submit"><?php echo esc_html( $atts['button'] ); ?></button>
		</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * Interprets a mixed value as a boolean (defaults to true unless explicitly
	 * falsey), so both block booleans and shortcode strings work.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private static function truthy( $value ) {
		return ! in_array( $value, array( false, 'false', '0', 0, '', 'no', 'off' ), true );
	}

	/**
	 * Processes a submitted form.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function handle_submit() {
		$nonce = isset( $_POST['rayetun_crm_lead_nonce'] ) ? sanitize_key( wp_unslash( $_POST['rayetun_crm_lead_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::redirect_back( 'err' );
		}

		// Honeypot filled → silently treat as success without storing anything.
		if ( ! empty( $_POST['rtcrm_website'] ) ) {
			self::redirect_back( 'ok' );
		}

		$utm = array();
		foreach ( Rayetun_CRM_Lead_Capture::utm_keys() as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$utm[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}

		$tags_raw = isset( $_POST['rtcrm_tags'] ) ? sanitize_text_field( wp_unslash( $_POST['rtcrm_tags'] ) ) : '';
		$tags     = array_values( array_filter( array_map( 'trim', explode( ',', $tags_raw ) ) ) );

		$lead = array(
			'email'        => isset( $_POST['rtcrm_email'] ) ? sanitize_email( wp_unslash( $_POST['rtcrm_email'] ) ) : '',
			'first_name'   => isset( $_POST['rtcrm_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rtcrm_first_name'] ) ) : '',
			'last_name'    => isset( $_POST['rtcrm_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rtcrm_last_name'] ) ) : '',
			'phone'        => isset( $_POST['rtcrm_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['rtcrm_phone'] ) ) : '',
			'message'      => isset( $_POST['rtcrm_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rtcrm_message'] ) ) : '',
			'source'       => isset( $_POST['rtcrm_source'] ) ? sanitize_text_field( wp_unslash( $_POST['rtcrm_source'] ) ) : '',
			'landing_page' => isset( $_POST['rtcrm_landing'] ) ? esc_url_raw( wp_unslash( $_POST['rtcrm_landing'] ) ) : '',
			'tags'         => $tags,
			'utm'          => $utm,
			'form'         => __( 'Native form', 'rayetun-crm' ),
		);

		$result = Rayetun_CRM_Lead_Capture::capture( $lead );

		self::redirect_back( is_wp_error( $result ) ? 'err' : 'ok' );
	}

	/**
	 * Redirects back to the submitting page with a status flag.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status 'ok' or 'err'.
	 * @return void
	 */
	private static function redirect_back( $status ) {
		$target = isset( $_POST['rtcrm_landing'] ) ? esc_url_raw( wp_unslash( $_POST['rtcrm_landing'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( '' === $target ) {
			$referer = wp_get_referer();
			$target  = $referer ? $referer : home_url();
		}

		wp_safe_redirect( add_query_arg( 'rtcrm_form', $status, remove_query_arg( array( 'rtcrm_form' ), $target ) ) );
		exit;
	}
}
