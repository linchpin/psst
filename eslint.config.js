/**
 * ESLint flat config for the root admin app. The blocks project has its own.
 */
const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpScriptsConfig,
	{
		ignores: [
			'**/build/**',
			'**/node_modules/**',
			'**/vendor/**',
			'**/*.config.js',
			'blocks/**',
			'scripts/**',
			'tests/e2e/**',
		],
	},
];
