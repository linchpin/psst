/**
 * Root webpack config: the admin app only.
 *
 * Blocks build from the nested `blocks/` project because they need wp-scripts
 * flags (`--blocks-manifest`, `--webpack-copy-php`, `--experimental-modules`)
 * this single-object config cannot take. Same split as mantle and discovery.
 */
const path = require( 'path' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * wp-scripts turns an SVG imported from JavaScript into a React component
 * through SVGR, whose SVGO pass strips `viewBox` by default. Without it the
 * logos in src/admin/logos cannot be scaled from CSS, so keep it, and drop
 * the intrinsic width/height so the stylesheet is the only thing that sizes
 * them.
 *
 * @param {Object} rule A webpack module rule.
 * @return {Object} The rule, with SVGR options when it is the SVG-in-JS rule.
 */
function keepSvgViewBox( rule ) {
	const isSvgInJs =
		rule.test instanceof RegExp &&
		rule.test.test( 'logo.svg' ) &&
		rule.issuer instanceof RegExp &&
		rule.issuer.test( 'component.js' );

	if ( ! isSvgInJs ) {
		return rule;
	}

	return {
		...rule,
		use: rule.use.map( ( loader ) =>
			typeof loader === 'string' && loader.includes( '@svgr' )
				? {
						loader,
						options: {
							dimensions: false,
							svgoConfig: {
								plugins: [
									{
										name: 'preset-default',
										params: {
											overrides: { removeViewBox: false },
										},
									},
								],
							},
						},
					}
				: loader
		),
	};
}

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'src/admin/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	module: {
		...defaultConfig.module,
		rules: defaultConfig.module.rules.map( keepSvgViewBox ),
	},
	plugins: [ ...defaultConfig.plugins, new RemoveEmptyScriptsPlugin() ],
};
