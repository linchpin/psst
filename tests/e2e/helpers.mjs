/**
 * Shared helpers for the end-to-end suite.
 */
import { expect } from '@playwright/test';

export const FIXTURE =
	'multi\nline\twith\ttabs  and  double  spaces\n\n  leading spaces\nünïcödé ✓';

/**
 * Create a secret through the form and return the share URL.
 *
 * @param {import('@playwright/test').Page} page       A page on the create page.
 * @param {Object}                          [options]  Options.
 * @param {string}                          [options.text]       Secret text.
 * @param {string}                          [options.passphrase] Pass phrase.
 * @param {string}                          [options.expiry]     Option value, in minutes.
 * @return {Promise<string>} The share URL, fragment included.
 */
export async function createSecret( page, options = {} ) {
	await page.goto( '/' );

	const form = page.locator( '.wp-block-psst-secret-form form' );
	await expect( form ).toBeVisible();

	await form.locator( 'textarea[name="message"]' ).fill( options.text ?? FIXTURE );

	if ( options.passphrase ) {
		await form.locator( 'input[name="passphrase"]' ).fill( options.passphrase );
	}

	if ( options.expiry ) {
		await form.locator( 'select[name="expiry"]' ).selectOption( options.expiry );
	}

	await form.getByRole( 'button', { name: 'Create Secret Link' } ).click();

	const urlField = page.locator( '.psst-form__url' );
	await expect( urlField ).toBeVisible();

	const url = await urlField.inputValue();
	expect( url ).toMatch( /\/s\/[A-Za-z0-9_-]{22,}\/#[A-Za-z0-9_-]{43}$/ );

	return url;
}

/**
 * Open a share URL in a fresh, cookie-less context.
 *
 * @param {import('@playwright/test').Browser} browser Browser.
 * @param {string}                             url     The share URL.
 * @return {Promise<{page: import('@playwright/test').Page, context: import('@playwright/test').BrowserContext, reveals: Array}>} The recipient page and the reveal requests it made.
 */
export async function openAsRecipient( browser, url ) {
	const context = await browser.newContext();
	const page = await context.newPage();
	const reveals = [];

	page.on( 'request', ( request ) => {
		if ( request.url().includes( '/psst/v1/secrets/' ) && request.url().endsWith( '/reveal' ) ) {
			reveals.push( request );
		}
	} );

	await page.goto( url );

	return { page, context, reveals };
}

/**
 * Log in as the Playground administrator.
 *
 * @param {import('@playwright/test').Page} page Page.
 * @return {Promise<void>} Resolves once wp-admin loads.
 */
export async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}
