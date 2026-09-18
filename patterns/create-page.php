<?php
/**
 * Pattern: the create page.
 *
 * What the front page (or any page) holds to offer the secret form. The FAQ
 * copy inside the block is editable inner content.
 *
 * @package Linchpin\Psst
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return [
	'slug'        => 'psst/create-page',
	'title'       => __( 'Share a secret', 'psst' ),
	'description' => __( 'A heading, an introduction and the secret form.', 'psst' ),
	'categories'  => [ 'psst' ],
	'keywords'    => [ 'psst', 'secret', 'form' ],
	'blockTypes'  => [ 'psst/secret-form' ],
	'content'     => '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">' . esc_html__( 'Share a secret', 'psst' ) . '</h1>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Paste a password, a key, or anything else you do not want sitting in an inbox. It is encrypted in your browser before it leaves, and the link works exactly once.', 'psst' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:psst/secret-form -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Need some help?', 'psst' ) . '</h3>
<!-- /wp:heading -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">' . esc_html__( 'Why would I shred a secret?', 'psst' ) . '</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'Shredding deletes a secret before it has been read. If you send someone a link and shred the secret before they open it, the link stops working and, to them, it looks like the secret never existed.', 'psst' ) . '</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading">' . esc_html__( 'Why can I only see the link once?', 'psst' ) . '</h4>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>' . esc_html__( 'The link carries the only key that can decrypt your secret, and the shred button works only from this page. Neither is stored anywhere, so if you leave or refresh, they are gone. Copy the link before you go.', 'psst' ) . '</p>
<!-- /wp:paragraph -->
<!-- /wp:psst/secret-form --></main>
<!-- /wp:group -->',
];
