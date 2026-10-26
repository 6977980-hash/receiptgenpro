<?php
/**
 * ReceiptGenPro — setup work that writes to the database
 *
 * Creating the tool pages (inc/feature-pages.php), the one-time content
 * migration (inc/content-patches.php) and the rewrite flush
 * (inc/seo-fixes.php) used to run on `init`, inside whichever visitor
 * request came first after a deploy (audit 20 Oct, #47 and #55). Now a
 * visitor request only checks three autoloaded options and, when one is
 * out of date, schedules a WP-Cron event; the work itself runs in that
 * event or on admin_init.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function rf_setup_needed() {
	return get_option( 'rf_feature_pages_hash' ) !== rf_feature_pages_hash()
		|| ! rf_migration_done()
		|| ! rf_migration_v135_done()
		|| ! rf_migration_v137_done()
		|| ! rf_migration_v140_done()
		|| ! rf_migration_v141_done()
		|| ! rf_migration_v143_done()
		|| get_option( 'rf_rewrite_version' ) !== RF_THEME_VERSION;
}

function rf_run_setup_tasks() {
	if ( wp_installing() || ! rf_setup_needed() ) {
		return;
	}
	rf_ensure_feature_pages();
	rf_migrate_phase5_content();
	rf_migrate_v135_content();
	rf_migrate_v137_content();
	rf_migrate_v140_content();
	rf_migrate_v141_content();
	rf_migrate_v143_content();
	if ( rf_flush_rewrites_v110() ) {
		rf_setup_once_per_version();
	}
}
add_action( 'admin_init', 'rf_run_setup_tasks' );
add_action( 'rf_setup_tasks', 'rf_run_setup_tasks' );

function rf_schedule_setup_tasks() {
	if ( wp_installing() || wp_doing_cron() || is_admin() ) {
		return;
	}
	if ( rf_setup_needed() && ! wp_next_scheduled( 'rf_setup_tasks' ) ) {
		wp_schedule_single_event( time(), 'rf_setup_tasks' );
	}
}
add_action( 'init', 'rf_schedule_setup_tasks', 30 );

/**
 * Housekeeping after a theme update (#54, #55): the flags read on every
 * request are autoloaded, so they cost no extra database query, and the
 * flags of the removed one-shot flushes are deleted.
 */
function rf_setup_once_per_version() {
	$names = array( 'rf_feature_pages_hash', 'rf_migration_phase5', 'rf_migration_v135', 'rf_migration_v137', 'rf_migration_v140', 'rf_migration_v141', 'rf_migration_v143', 'rf_rewrite_version' );
	if ( function_exists( 'wp_set_option_autoload_values' ) ) {
		wp_set_option_autoload_values( array_fill_keys( $names, true ) );
	} else {
		global $wpdb;
		$in = implode( ',', array_fill( 0, count( $names ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders only.
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET autoload = 'yes' WHERE option_name IN ($in)", $names ) );
		wp_cache_delete( 'alloptions', 'options' );
	}
	delete_option( 'rf_webmanifest_flushed' );
	delete_option( 'rf_ads_txt_flushed' );
}
