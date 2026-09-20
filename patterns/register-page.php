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
	// No heading of its own; see the note in sign-in-page.php.
	'content'     => '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:psst/register-form {"heading":""} /--></main>
<!-- /wp:group -->',
];
