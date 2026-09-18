<?php
/**
 * Seed the Playground site for the end-to-end suite.
 *
 * Activation already created the two pages and flagged a rewrite flush; this
 * makes the create page the front page, sets a short rate limit so the abuse
 * spec can trip it quickly, and flushes rules so the first request resolves.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$psst_settings = \Linchpin\Psst\Model\Settings::all();

if ( (int) $psst_settings['create_page_id'] > 0 ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $psst_settings['create_page_id'] );
}

$psst_settings['rate_limit_create_per_hour'] = 0;
$psst_settings['rate_limit_create_global_per_hour'] = 0;
$psst_settings['rate_limit_reveal_per_hour'] = 0;
\Linchpin\Psst\Model\Settings::save( $psst_settings );

\Linchpin\Psst\Controller\Install::ensure_pages();
delete_option( \Linchpin\Psst\Model\Settings::OPTION_FLUSH );
( new \Linchpin\Psst\Controller\Rewrites() )->add_rules();
flush_rewrite_rules( false );

echo 'seeded';
