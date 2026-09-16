<?php
/**
 * Optional-module registry.
 *
 * Contacts, the dashboard, reports and settings are always-on core. Everything
 * else — the sales pipeline, lead-capture forms, tasks, email and the
 * WooCommerce sync — is an optional module a site can switch off from
 * Settings → Modules to keep the admin lean. Turning a module off suspends its
 * feature registration, REST routes, scheduled work and navigation; it never
 * deletes stored data, so a module can be turned back on later unchanged.
 *
 * The registry is filterable so the Pro add-on can register its own modules
 * (automations, sequences, booking …) against the same switchboard.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Module registry and on/off state.
 *
 * @since 1.1.0
 */
final class Rayetun_CRM_Modules {

	/**
	 * Option storing the per-module on/off overrides.
	 *
	 * @var string
	 */
	const OPTION = 'rayetun_crm_modules';

	/**
	 * Returns the module registry: key => definition.
	 *
	 * Each definition is an array of:
	 *   - label       (string) Display name.
	 *   - description (string) One-line explanation.
	 *   - default     (bool)   Whether it ships enabled.
	 *   - core        (bool)   Always-on; shown read-only and cannot be toggled.
	 *   - requires    (string) Optional dependency slug (e.g. 'woocommerce').
	 *
	 * @since 1.1.0
	 *
	 * @return array<string,array>
	 */
	public static function registry() {
		$modules = array(
			// Always-on core areas. Rendered as read-only cards (green check, no
			// toggle) so the Modules screen shows the full picture of what's active.
			'contacts'    => array(
				'label'       => __( 'Contacts', 'rayetun-crm' ),
				'description' => __( 'The contact database with custom fields, tags, notes and a full activity timeline.', 'rayetun-crm' ),
				'default'     => true,
				'core'        => true,
			),
			'dashboard'   => array(
				'label'       => __( 'Dashboard', 'rayetun-crm' ),
				'description' => __( 'At-a-glance KPIs, hot leads, pipeline value and a recent-activity feed.', 'rayetun-crm' ),
				'default'     => true,
				'core'        => true,
			),
			'reports'     => array(
				'label'       => __( 'Reports', 'rayetun-crm' ),
				'description' => __( 'Contacts over time, lead sources and pipeline conversion — each CSV-exportable.', 'rayetun-crm' ),
				'default'     => true,
				'core'        => true,
			),
			'scoring'     => array(
				'label'       => __( 'Lead scoring', 'rayetun-crm' ),
				'description' => __( 'Automatic heat scoring from form submissions, with a daily score-decay routine.', 'rayetun-crm' ),
				'default'     => true,
				'core'        => true,
			),
			'pipeline'    => array(
				'label'       => __( 'Sales pipeline', 'rayetun-crm' ),
				'description' => __( 'Kanban deal board, stages and pipeline analytics.', 'rayetun-crm' ),
				'default'     => true,
			),
			'lead_forms'  => array(
				'label'       => __( 'Lead capture forms', 'rayetun-crm' ),
				'description' => __( 'Native lead form (shortcode + block) plus Contact Form 7, WPForms and Fluent Forms auto-capture.', 'rayetun-crm' ),
				'default'     => true,
			),
			'tasks'       => array(
				'label'       => __( 'Tasks & reminders', 'rayetun-crm' ),
				'description' => __( 'Follow-up tasks with due dates, priorities and due-today email reminders.', 'rayetun-crm' ),
				'default'     => true,
			),
			'email'       => array(
				'label'       => __( 'Email', 'rayetun-crm' ),
				'description' => __( 'Log messages to the timeline and send one-to-one email with reusable templates.', 'rayetun-crm' ),
				'default'     => true,
			),
			'woocommerce' => array(
				'label'       => __( 'WooCommerce sync', 'rayetun-crm' ),
				'description' => __( 'Turn paid orders into contacts with purchase history and optional auto-deals.', 'rayetun-crm' ),
				'default'     => true,
				'requires'    => 'woocommerce',
			),
		);

		/**
		 * Filters the module registry.
		 *
		 * Add-ons may register their own optional modules here.
		 *
		 * @since 1.1.0
		 *
		 * @param array<string,array> $modules Module definitions keyed by slug.
		 */
		return (array) apply_filters( 'rayetun_crm_modules_registry', $modules );
	}

	/**
	 * Resolved on/off state for every registered module.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string,bool>
	 */
	public static function states() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$states = array();
		foreach ( self::registry() as $key => $def ) {
			$states[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : ! empty( $def['default'] );
		}

		return $states;
	}

	/**
	 * Whether a module is active right now.
	 *
	 * Core areas are always active. A module with an unmet dependency (e.g.
	 * WooCommerce not installed) is inactive.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key Module slug.
	 * @return bool
	 */
	public static function is_enabled( $key ) {
		$registry = self::registry();
		if ( ! isset( $registry[ $key ] ) ) {
			return false;
		}

		$def = $registry[ $key ];

		// Core areas are always on and cannot be switched off.
		if ( ! empty( $def['core'] ) ) {
			return true;
		}

		if ( ! empty( $def['requires'] ) && ! self::dependency_met( $def['requires'] ) ) {
			return false;
		}

		$states  = self::states();
		$enabled = isset( $states[ $key ] ) ? $states[ $key ] : ! empty( $def['default'] );

		/**
		 * Filters whether a specific module is enabled.
		 *
		 * @since 1.1.0
		 *
		 * @param bool   $enabled Whether the module is on.
		 * @param string $key     Module slug.
		 */
		return (bool) apply_filters( 'rayetun_crm_module_enabled', $enabled, $key );
	}

	/**
	 * Persists on/off changes for optional modules.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string,bool> $changes Slug => desired state.
	 * @return array<string,bool> The resulting states.
	 */
	public static function update( array $changes ) {
		$registry = self::registry();
		$stored   = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		foreach ( $changes as $key => $on ) {
			if ( ! isset( $registry[ $key ] ) ) {
				continue;
			}
			// Core areas are always on and can't be toggled.
			if ( ! empty( $registry[ $key ]['core'] ) ) {
				continue;
			}
			$stored[ $key ] = (bool) $on;
		}

		update_option( self::OPTION, $stored );

		return self::states();
	}

	/**
	 * Enabled state for every module, for the admin SPA to gate its UI.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string,bool>
	 */
	public static function enabled_map() {
		$map = array();
		foreach ( array_keys( self::registry() ) as $key ) {
			$map[ $key ] = self::is_enabled( $key );
		}

		return $map;
	}

	/**
	 * Full descriptors + state for the Settings → Modules screen.
	 *
	 * @since 1.1.0
	 *
	 * @return array[] List of display rows.
	 */
	public static function for_display() {
		$rows = array();
		foreach ( self::registry() as $key => $def ) {
			$available = empty( $def['requires'] ) || self::dependency_met( $def['requires'] );

			$rows[] = array(
				'key'         => $key,
				'label'       => isset( $def['label'] ) ? $def['label'] : $key,
				'description' => isset( $def['description'] ) ? $def['description'] : '',
				'core'        => ! empty( $def['core'] ),
				'enabled'     => self::is_enabled( $key ),
				'available'   => $available,
				'requires'    => isset( $def['requires'] ) ? $def['requires'] : '',
			);
		}

		return $rows;
	}

	/**
	 * Whether an optional dependency is satisfied.
	 *
	 * @since 1.1.0
	 *
	 * @param string $dependency Dependency slug.
	 * @return bool
	 */
	private static function dependency_met( $dependency ) {
		if ( 'woocommerce' === $dependency ) {
			return class_exists( 'WooCommerce' );
		}

		/**
		 * Filters whether a module dependency is met.
		 *
		 * @since 1.1.0
		 *
		 * @param bool   $met        Whether the dependency is satisfied. Default true.
		 * @param string $dependency Dependency slug.
		 */
		return (bool) apply_filters( 'rayetun_crm_module_dependency_met', true, $dependency );
	}
}
