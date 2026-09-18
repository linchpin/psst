/**
 * ESLint flat config for the blocks project.
 */
const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpScriptsConfig,
	{
		ignores: [ '**/build/**', '**/node_modules/**', '**/*.config.js' ],
	},
	{
		// The crypto module and its tests use the WebCrypto and TextEncoder
		// globals directly.
		languageOptions: {
			globals: {
				crypto: 'readonly',
				TextEncoder: 'readonly',
				TextDecoder: 'readonly',
			},
		},
	},
];
