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

module.exports = {
	...defaultConfig,
	entry: {
		admin: path.resolve( __dirname, 'src/admin/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
	plugins: [ ...defaultConfig.plugins, new RemoveEmptyScriptsPlugin() ],
};
