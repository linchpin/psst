<?php
/**
 * Uninstall.
 *
 * Secrets are ephemeral by definition, so the default is to remove everything.
 * Action Scheduler is not initialised in this context, so its rows are removed
 * with direct queries; its tables are shared and never dropped. The pages are
 * the site's content and stay, as do any user accounts — those belong to the
 * site, not to this plugin, and deleting people because a plugin was removed
 * would be an extraordinary thing for a plugin to do.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$psst_settings = get_option( 'psst_settings', [] );

if ( is_array( $psst_settings ) && array_key_exists( 'delete_on_uninstall', $psst_settings ) && ! $psst_settings['delete_on_uninstall'] ) {
	return;
}

global $wpdb;

/*
 * Every secret (2.x and legacy) and every sent-secret history row. The history
 * is metadata about secrets that no longer exist, which is exactly as
 * ephemeral as the secrets were, so it goes the same way.
 */
$psst_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('psst_secret', 'secret', 'psst_sent')" );

foreach ( $psst_ids as $psst_id ) {
	wp_delete_post( (int) $psst_id, true );
}

// Options.
foreach ( [ 'psst_settings', 'psst_turnstile_secret', 'psst_flush_rewrite_rules', 'psst_db_version', 'psst_caps_added', 'psst_options', 'psst_version', 'psst_activation' ] as $psst_option ) {
	delete_option( $psst_option );
}

// Transients: rate-limit counters and the sweep throttle.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_psst\_%' OR option_name LIKE '\_transient\_timeout\_psst\_%'" );

// Capabilities.
foreach ( wp_roles()->role_objects as $psst_role ) {
	foreach ( [ 'edit_psst_secrets', 'edit_others_psst_secrets', 'edit_published_psst_secrets', 'edit_private_psst_secrets', 'delete_psst_secrets', 'delete_others_psst_secrets', 'delete_published_psst_secrets', 'delete_private_psst_secrets', 'read_private_psst_secrets', 'publish_psst_secrets' ] as $psst_cap ) {
		$psst_role->remove_cap( $psst_cap );
	}
}

// WP-Cron fallbacks.
wp_clear_scheduled_hook( 'psst_sweep_secrets' );
wp_clear_scheduled_hook( 'psst_purge_legacy' );

// Action Scheduler rows in this plugin's group.
$psst_groups_table  = $wpdb->prefix . 'actionscheduler_groups';
$psst_actions_table = $wpdb->prefix . 'actionscheduler_actions';
$psst_logs_table    = $wpdb->prefix . 'actionscheduler_logs';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $psst_groups_table ) ) === $psst_groups_table ) {
	$psst_group_id = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$psst_groups_table} WHERE slug = %s", 'psst' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix.

	if ( $psst_group_id ) {
		$psst_action_ids = $wpdb->get_col( $wpdb->prepare( "SELECT action_id FROM {$psst_actions_table} WHERE group_id = %d", $psst_group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix.

		if ( ! empty( $psst_action_ids ) && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $psst_logs_table ) ) === $psst_logs_table ) {
			$psst_in = implode( ',', array_map( 'intval', $psst_action_ids ) );
			$wpdb->query( "DELETE FROM {$psst_logs_table} WHERE action_id IN ({$psst_in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- integers cast above, table name from $wpdb->prefix.
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$psst_actions_table} WHERE group_id = %d", $psst_group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$psst_groups_table} WHERE group_id = %d", $psst_group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name built from $wpdb->prefix.
	}
}
