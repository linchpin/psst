<?php
/**
 * Pattern: the account page.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'slug'        => 'psst/account-page',
	'title'       => __( 'Your account', 'psst' ),
	'description' => __( 'A heading and the record of secrets a signed-in user has sent.', 'psst' ),
	'categories'  => [ 'psst' ],
	'keywords'    => [ 'psst', 'account', 'history', 'sent' ],
	'blockTypes'  => [ 'psst/account' ],
	'content'     => '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html__( 'Your account', 'psst' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:psst/account {"heading":""} /--></main>
<!-- /wp:group -->',
];
