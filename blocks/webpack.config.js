/**
 * Webpack config for the `blocks/` project.
 *
 * Blocks build here rather than from the repository root because they need
 * three wp-scripts flags the root build cannot take: `--blocks-manifest`,
 * `--webpack-copy-php` and `--experimental-modules`. The last makes
 * `@wordpress/scripts/config/webpack.config` export an array of configs
 * (classic scripts + script modules). This file adds exactly one plugin and
 * leaves the module half untouched, as mantle's does.
 */
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const { getAsBooleanFromENV } = require( '@wordpress/scripts/utils' );

const hasExperimentalModulesFlag = getAsBooleanFromENV(
	'WP_EXPERIMENTAL_MODULES'
);

let scriptConfig;
let moduleConfig;

if ( hasExperimentalModulesFlag ) {
	[
		scriptConfig,
		moduleConfig,
	] = require( '@wordpress/scripts/config/webpack.config' );
} else {
	scriptConfig = require( '@wordpress/scripts/config/webpack.config' );
}

const scripts = {
	...scriptConfig,
	entry: {
		...scriptConfig.entry(),
	},
	plugins: [
		...scriptConfig.plugins,
		// A block whose only style entry is SCSS would otherwise ship an empty
		// .js file next to the stylesheet.
		new RemoveEmptyScriptsPlugin(),
	],
};

const customExports = [ scripts ];

if ( hasExperimentalModulesFlag ) {
	// Passed through as-is. Its DependencyExtractionWebpackPlugin writes
	// view.asset.php; a second copy silently empties the dependency list.
	customExports.push( moduleConfig );
}

module.exports = customExports;
