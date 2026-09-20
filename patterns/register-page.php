<?php
/**
 * Pattern: the registration page.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'slug'        => 'psst/register-page',
	'title'       => __( 'Create an account', 'psst' ),
	'description' => __( 'A heading and the front end registration form.', 'psst' ),
	'categories'  => [ 'psst' ],
	'keywords'    => [ 'psst', 'register', 'sign up', 'account' ],
	'blockTypes'  => [ 'psst/register-form' ],
	'content'     => '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html__( 'Create an account', 'psst' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:psst/register-form {"heading":""} /--></main>
<!-- /wp:group -->',
];
