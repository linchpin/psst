/**
 * Psst's brand, in one place.
 *
 * The colours come from the plugin's own artwork: the mint and violet are the
 * two fills in logos/psst.svg, the deep green is the right-hand stop of the
 * WordPress.org icon's gradient, and the primary seed is the midpoint of that
 * gradient. The ThemeProvider derives the rest of the design system's colour
 * ramps from `primary`; the SCSS reads the others as `--psst-brand-*` custom
 * properties that the shell sets from this object, so there is exactly one
 * source to change.
 */
export const BRAND = {
	primary: '#318873',
	mint: '#5beece',
	violet: '#6d3efb',
	deep: '#082318',
};

/**
 * Where the marketing links go. UTM parameters mirror linchpin-blocks so the
 * agency site can tell which plugin sent a visitor.
 */
const CAMPAIGN = 'utm_source=psst&utm_medium=plugin&utm_campaign=admin';

export const LINKS = {
	linchpin: `https://linchpin.com/?${ CAMPAIGN }`,
	github: 'https://github.com/linchpin/psst',
	issues: 'https://github.com/linchpin/psst/issues',
	readme: 'https://github.com/linchpin/psst#readme',
	support: 'mailto:sayhi@linchpin.com',
	supportEmail: 'sayhi@linchpin.com',
};

/**
 * The `--psst-brand-*` custom properties, ready for a `style` prop.
 *
 * @return {Object} CSS custom properties keyed by name.
 */
export function brandStyle() {
	return Object.fromEntries(
		Object.entries( BRAND ).map( ( [ name, value ] ) => [
			`--psst-brand-${ name }`,
			value,
		] )
	);
}
