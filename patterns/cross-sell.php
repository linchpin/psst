<?php
/**
 * Pattern: the cross-sell.
 *
 * "Have something to share?" with a button whose URL follows the create page
 * setting through the psst/create-url bindings source.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'slug'        => 'psst/cross-sell',
	'title'       => __( 'Create your own secret', 'psst' ),
	'description' => __( 'An invitation to create a secret, linking to the create page.', 'psst' ),
	'categories'  => [ 'psst' ],
	'keywords'    => [ 'psst', 'secret', 'cta' ],
	'content'     => '<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Have something to share?', 'psst' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Send it the safe way. Your secret is encrypted in your browser and the link works once.', 'psst' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"metadata":{"bindings":{"url":{"source":"psst/create-url"}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html__( 'Create your own secret', 'psst' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
];
