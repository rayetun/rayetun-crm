<?php
/**
 * WooCommerce integration.
 *
 * When a WooCommerce order is paid, the buyer is synced into the CRM as a
 * contact (tagged "Customer"), the order is recorded on the contact's timeline,
 * and — for orders at or above a configurable threshold — a won deal is created.
 * The contact record can surface the buyer's purchase history.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Syncs WooCommerce orders into the CRM.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_WooCommerce {

	/**
	 * Whether WooCommerce is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_order' );
	}

	/**
	 * Hooks order sync when WooCommerce is present.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'sync_order' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'sync_order' ) );
	}

	/**
	 * Syncs a single order into the CRM (idempotent).
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function sync_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( '' === $email || ! is_email( $email ) ) {
			return;
		}

		$contact_id = Rayetun_CRM_Contacts::find_by_email( $email );

		if ( ! $contact_id ) {
			$contact_id = Rayetun_CRM_Contacts::create(
				array(
					'email'      => $email,
					'first_name' => $order->get_billing_first_name(),
					'last_name'  => $order->get_billing_last_name(),
					'phone'      => $order->get_billing_phone(),
					'company'    => $order->get_billing_company(),
					'status'     => 'client',
					'source'     => __( 'WooCommerce', 'rayetun-crm' ),
					'tags'       => array( 'Customer' ),
				)
			);

			if ( is_wp_error( $contact_id ) ) {
				return;
			}
		} else {
			Rayetun_CRM_Contacts::add_tags( $contact_id, array( 'Customer' ) );
		}

		// Record the order and create a deal only once per order.
		if ( $order->get_meta( '_rayetun_crm_synced' ) ) {
			return;
		}

		Rayetun_CRM_Activities::log(
			$contact_id,
			'order',
			array(
				'order_id' => (int) $order_id,
				'number'   => $order->get_order_number(),
				'total'    => (float) $order->get_total(),
				'currency' => html_entity_decode( get_woocommerce_currency_symbol( $order->get_currency() ), ENT_QUOTES ),
			)
		);

		/**
		 * Filters the order total at/above which a won deal is auto-created.
		 *
		 * @since 1.0.0
		 *
		 * @param float $threshold Minimum order total. Default 100.
		 */
		$threshold = (float) apply_filters( 'rayetun_crm_woo_deal_threshold', 100 );

		if ( (float) $order->get_total() >= $threshold ) {
			self::create_deal_for_order( $contact_id, $order );
		}

		$order->update_meta_data( '_rayetun_crm_synced', 1 );
		$order->save();

		/**
		 * Fires after an order has been synced to the CRM.
		 *
		 * @since 1.0.0
		 *
		 * @param int      $contact_id Synced contact ID.
		 * @param WC_Order $order      The order.
		 */
		do_action( 'rayetun_crm_order_synced', $contact_id, $order );
	}

	/**
	 * Creates a won deal representing an order.
	 *
	 * @since 1.0.0
	 *
	 * @param int      $contact_id Contact ID.
	 * @param WC_Order $order      Order.
	 * @return void
	 */
	private static function create_deal_for_order( $contact_id, $order ) {
		$pipeline_id = Rayetun_CRM_Pipelines::default_pipeline_id();

		$won_stage = 0;
		foreach ( Rayetun_CRM_Pipelines::stages( $pipeline_id ) as $stage ) {
			if ( $stage['is_won'] ) {
				$won_stage = $stage['id'];
				break;
			}
		}

		Rayetun_CRM_Deals::create(
			array(
				'title'       => sprintf(
					/* translators: %s: order number. */
					__( 'Order #%s', 'rayetun-crm' ),
					$order->get_order_number()
				),
				'value'       => (float) $order->get_total(),
				'currency'    => html_entity_decode( get_woocommerce_currency_symbol( $order->get_currency() ), ENT_QUOTES ),
				'pipeline_id' => $pipeline_id,
				'stage_id'    => $won_stage,
				'probability' => 100,
				'contact_ids' => array( $contact_id ),
			)
		);
	}

	/**
	 * Returns a contact's WooCommerce purchase summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email Contact email.
	 * @return array
	 */
	public static function purchase_summary( $email ) {
		if ( ! self::is_active() ) {
			return array( 'available' => false );
		}

		$orders = wc_get_orders(
			array(
				'limit'    => 20,
				'customer' => $email,
				'status'   => array( 'wc-processing', 'wc-completed' ),
				'orderby'  => 'date',
				'order'    => 'DESC',
			)
		);

		$total   = 0.0;
		$count   = 0;
		$list    = array();
		$symbol  = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES );

		foreach ( (array) $orders as $order ) {
			$count++;
			$total += (float) $order->get_total();
			if ( count( $list ) < 5 ) {
				$list[] = array(
					'number' => $order->get_order_number(),
					'total'  => (float) $order->get_total(),
					'status' => $order->get_status(),
					'date'   => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d' ) : '',
				);
			}
		}

		return array(
			'available'   => true,
			'currency'    => $symbol,
			'order_count' => $count,
			'total_spent' => $total,
			'orders'      => $list,
		);
	}
}
