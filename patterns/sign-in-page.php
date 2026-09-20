<?php
/**
 * Pattern: the sign-in page.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'slug'        => 'psst/sign-in-page',
	'title'       => __( 'Sign in', 'psst' ),
	'description' => __( 'A heading and the front end sign-in form.', 'psst' ),
	'categories'  => [ 'psst' ],
	'keywords'    => [ 'psst', 'login', 'sign in', 'account' ],
	'blockTypes'  => [ 'psst/login-form' ],

	/*
	 * No heading of its own. Install creates the page with a title, and a theme
	 * renders that title above the content — a heading here as well shows the
	 * visitor the same words twice. The block's own `heading` attribute covers
	 * the case where someone drops it onto a page that has no title showing.
	 */
	'content'     => '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:paragraph -->
<p>' . esc_html__( 'Signing in is optional. You can share a secret without an account — an account just keeps a record of what you have sent.', 'psst' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:psst/login-form {"heading":""} /--></main>
<!-- /wp:group -->',
];
