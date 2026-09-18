/**
 * Playwright against WordPress Playground.
 *
 * `webServer` boots Playground from the blueprint; `reuseExistingServer` lets a
 * developer keep `npm run playground:start` running between runs.
 */
import { defineConfig, devices } from '@playwright/test';

const PORT = 9400;
const BASE_URL = `http://localhost:${ PORT }`;

export default defineConfig( {
	testDir: '.',
	testMatch: /.*\.spec\.mjs/,
	timeout: process.env.CI ? 120_000 : 60_000,
	expect: { timeout: process.env.CI ? 30_000 : 10_000 },
	fullyParallel: false,
	workers: 1,
	retries: process.env.CI ? 1 : 0,
	forbidOnly: !! process.env.CI,
	reporter: process.env.CI
		? [ [ 'html', { outputFolder: '../../playwright-report', open: 'never' } ], [ 'github' ] ]
		: 'line',
	outputDir: '../../test-results',
	use: {
		baseURL: BASE_URL,
		trace: 'on-first-retry',
		viewport: { width: 1280, height: 900 },
		...devices[ 'Desktop Chrome' ],
	},
	webServer: {
		command: 'npm run playground:start',
		cwd: '../..',
		url: `${ BASE_URL }/wp-json/psst/v1/config`,
		reuseExistingServer: ! process.env.CI,
		timeout: 180_000,
		stdout: 'ignore',
		stderr: 'pipe',
	},
} );
