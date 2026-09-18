/**
 * Jest config for the crypto vectors.
 *
 * Node 24 exposes WebCrypto on `globalThis.crypto`, so the tests run against
 * the same primitives the browser uses; the wp-scripts default environment is
 * jsdom, whose crypto lacks `subtle`.
 */
const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	testEnvironment: 'node',
	testMatch: [ '<rootDir>/src/**/__tests__/**/*.test.js' ],
};
