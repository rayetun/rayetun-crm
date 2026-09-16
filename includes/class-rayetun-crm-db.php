<?php
/**
 * Database schema and table helpers.
 *
 * @package Rayetun\CRM
 */

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plugin's custom tables: creation, versioning and name resolution.
 *
 * Tags are stored in a join table (not a JSON column) so membership filters
 * stay indexable at scale. Searchable/unique columns such as `email` are kept
 * in plaintext so they can be indexed and searched.
 *
 * @since 1.0.0
 */
final class Rayetun_CRM_DB {

	/**
	 * Schema version. Bump when a table definition changes; drives migrations.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.1.0';

	/**
	 * Unprefixed table slugs.
	 *
	 * @var string[]
	 */
	const TABLES = array(
		'contacts',
		'companies',
		'tags',
		'contact_tags',
		'custom_fields',
		'custom_field_values',
		'pipelines',
		'stages',
		'deals',
		'deal_contacts',
		'activities',
		'tasks',
		'forms',
		'form_maps',
		'email_log',
		'score_events',
		'files',
	);

	/**
	 * Returns the fully-prefixed table name for a slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Unprefixed table slug, e.g. 'contacts'.
	 * @return string Prefixed table name.
	 */
	public static function table( $slug ) {
		global $wpdb;

		return $wpdb->prefix . 'rayetun_crm_' . $slug;
	}

	/**
	 * Creates or updates all custom tables via dbDelta.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		foreach ( self::schema( $charset ) as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'rayetun_crm_db_version', self::DB_VERSION );
	}

	/**
	 * Runs pending schema migrations when the stored DB version is behind.
	 *
	 * Activation only fires on install and manual re-activation, so a plugin
	 * update that ships a schema change would otherwise never reach existing
	 * sites. Hooked on admin_init, this compares the stored version against
	 * DB_VERSION and re-runs dbDelta (which is additive and idempotent) when
	 * they differ.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'rayetun_crm_db_version' ) === self::DB_VERSION ) {
			return;
		}

		self::create_tables();
	}

	/**
	 * Returns the CREATE TABLE statements for every table.
	 *
	 * @since 1.0.0
	 *
	 * @param string $charset Charset/collation clause.
	 * @return string[] Array of CREATE TABLE statements.
	 */
	private static function schema( $charset ) {
		$p = self::table( '' ); // Base prefix + 'rayetun_crm_'.

		$statements = array();

		$statements[] = "CREATE TABLE {$p}contacts (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			email varchar(191) NOT NULL DEFAULT '',
			first_name varchar(191) NOT NULL DEFAULT '',
			last_name varchar(191) NOT NULL DEFAULT '',
			phone varchar(64) NOT NULL DEFAULT '',
			company_id bigint(20) unsigned NOT NULL DEFAULT 0,
			website varchar(191) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'lead',
			source varchar(191) NOT NULL DEFAULT '',
			lead_score int(11) NOT NULL DEFAULT 0,
			star tinyint(1) NOT NULL DEFAULT 0,
			email_status varchar(20) NOT NULL DEFAULT 'subscribed',
			owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			last_activity datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY status (status),
			KEY lead_score (lead_score),
			KEY company_id (company_id),
			KEY created (created)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}companies (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			website varchar(191) NOT NULL DEFAULT '',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY name (name)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}tags (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(120) NOT NULL DEFAULT '',
			slug varchar(120) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}contact_tags (
			contact_id bigint(20) unsigned NOT NULL,
			tag_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (contact_id,tag_id),
			KEY tag_id (tag_id)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}custom_fields (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entity varchar(20) NOT NULL DEFAULT 'contact',
			label varchar(191) NOT NULL DEFAULT '',
			field_key varchar(120) NOT NULL DEFAULT '',
			type varchar(20) NOT NULL DEFAULT 'text',
			options longtext NULL,
			sort int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY entity (entity)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}custom_field_values (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			field_id bigint(20) unsigned NOT NULL,
			entity_id bigint(20) unsigned NOT NULL,
			value longtext NULL,
			PRIMARY KEY  (id),
			KEY field_entity (field_id,entity_id)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}pipelines (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			is_default tinyint(1) NOT NULL DEFAULT 0,
			sort int(11) NOT NULL DEFAULT 0,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}stages (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			pipeline_id bigint(20) unsigned NOT NULL,
			name varchar(191) NOT NULL DEFAULT '',
			color varchar(20) NOT NULL DEFAULT '',
			sort int(11) NOT NULL DEFAULT 0,
			is_won tinyint(1) NOT NULL DEFAULT 0,
			is_lost tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY pipeline_id (pipeline_id)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}deals (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			pipeline_id bigint(20) unsigned NOT NULL,
			stage_id bigint(20) unsigned NOT NULL,
			title varchar(191) NOT NULL DEFAULT '',
			description longtext NULL,
			value decimal(18,2) NOT NULL DEFAULT 0.00,
			currency varchar(8) NOT NULL DEFAULT '',
			close_date date NULL,
			probability tinyint(3) unsigned NOT NULL DEFAULT 0,
			assigned_user bigint(20) unsigned NOT NULL DEFAULT 0,
			stage_since datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY pipeline_id (pipeline_id),
			KEY stage_id (stage_id),
			KEY assigned_user (assigned_user)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}deal_contacts (
			deal_id bigint(20) unsigned NOT NULL,
			contact_id bigint(20) unsigned NOT NULL,
			PRIMARY KEY  (deal_id,contact_id),
			KEY contact_id (contact_id)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}activities (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			deal_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(40) NOT NULL DEFAULT '',
			data longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY deal_id (deal_id),
			KEY type (type),
			KEY created (created)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}tasks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			deal_id bigint(20) unsigned NOT NULL DEFAULT 0,
			type varchar(20) NOT NULL DEFAULT 'follow_up',
			title varchar(191) NOT NULL DEFAULT '',
			notes longtext NULL,
			due datetime NULL,
			priority varchar(10) NOT NULL DEFAULT 'normal',
			status varchar(20) NOT NULL DEFAULT 'open',
			assigned_user bigint(20) unsigned NOT NULL DEFAULT 0,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY due (due),
			KEY status (status),
			KEY assigned_user (assigned_user)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}forms (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL DEFAULT '',
			config longtext NULL,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}form_maps (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_source varchar(40) NOT NULL DEFAULT '',
			external_form_id varchar(120) NOT NULL DEFAULT '',
			mapping longtext NULL,
			pipeline_id bigint(20) unsigned NOT NULL DEFAULT 0,
			stage_id bigint(20) unsigned NOT NULL DEFAULT 0,
			default_tags longtext NULL,
			PRIMARY KEY  (id),
			KEY form_source (form_source)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}email_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
			subject varchar(255) NOT NULL DEFAULT '',
			direction varchar(10) NOT NULL DEFAULT 'out',
			status varchar(20) NOT NULL DEFAULT '',
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY created (created)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}score_events (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			contact_id bigint(20) unsigned NOT NULL,
			event varchar(40) NOT NULL DEFAULT '',
			points int(11) NOT NULL DEFAULT 0,
			created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY contact_id (contact_id),
			KEY created (created)
		) $charset;";

		$statements[] = "CREATE TABLE {$p}files (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			deal_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(191) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY deal_id (deal_id)
		) $charset;";

		return $statements;
	}
}
