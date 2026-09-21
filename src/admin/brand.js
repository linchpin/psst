/**
 * Psst's brand and links, in one place.
 *
 * Both come from @linchpinagency/ui, so this file names what is Psst's and
 * nothing else. `defineBrand()` turns the three colours into the
 * `--lp-brand-*` custom properties the chrome paints with, and seeds the
 * design system from `primary` — every button, tab and card icon on the
 * screen follows it without being told. `linchpinLinks()` builds the four
 * links every Linchpin admin screen carries, with the UTM parameters the
 * agency site reads.
 */

/**
 * External dependencies
 */
import { defineBrand, linchpinLinks } from '@linchpinagency/ui';

/**
 * The colours come from the plugin's own artwork: the deep green is the
 * left-hand stop of the WordPress.org icon's gradient and `deepEnd` its
 * right, and the primary seed is the midpoint between them. The mint and
 * violet in logos/psst.svg are the mark's own and stay in the artwork.
 */
export const BRAND = defineBrand( {
	primary: '#318873',
	deep: '#082318',
	deepEnd: '#164a3b',
} );

/**
 * `linchpin/psst` and `sayhi@linchpin.com` are the defaults, so the slug is
 * the only thing this has to say.
 */
export const LINKS = linchpinLinks( { plugin: 'psst' } );
