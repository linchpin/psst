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
	'content'     => '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html__( 'Sign in', 'psst' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Signing in is optional. You can share a secret without an account — an account just keeps a record of what you have sent.', 'psst' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:psst/login-form {"heading":""} /--></main>
<!-- /wp:group -->',
];
