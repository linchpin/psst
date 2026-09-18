/**
 * The sender-to-recipient flow, end to end, on a stock block theme.
 */
import { test, expect } from '@playwright/test';
import { FIXTURE, createSecret, openAsRecipient } from './helpers.mjs';

test.describe( 'secret flow', () => {
	test( 'create, view once, gone', async ( { page, browser } ) => {
		const url = await createSecret( page, { expiry: '60' } );

		await expect( page.locator( '.psst-form__expires' ) ).toContainText( 'Expires:' );

		const recipient = await openAsRecipient( browser, url );

		// The interstitial: nothing has been consumed.
		await expect( recipient.page.getByRole( 'heading', { name: 'Your Shared Secret' } ) ).toBeVisible();
		await expect( recipient.page.getByRole( 'button', { name: 'View Secret' } ) ).toBeVisible();
		expect( recipient.reveals ).toHaveLength( 0 );

		// A reload is still the interstitial.
		await recipient.page.reload();
		await expect( recipient.page.getByRole( 'button', { name: 'View Secret' } ) ).toBeVisible();
		expect( recipient.reveals ).toHaveLength( 0 );

		// Reveal: byte-exact text, warning shown, key gone from the URL.
		await recipient.page.getByRole( 'button', { name: 'View Secret' } ).click();
		const secret = recipient.page.locator( 'pre.psst-secret' );
		await expect( secret ).toBeVisible();
		expect( await secret.textContent() ).toBe( FIXTURE );
		await expect( recipient.page.locator( '.psst-callout--warning' ) ).toBeVisible();
		expect( recipient.page.url() ).not.toContain( '#' );
		expect( recipient.reveals ).toHaveLength( 1 );

		// A second visit is a 404 with the gone state, not the theme's 404.
		// (A goto() to the same path with a fragment is a same-document hop, so reload.)
		const response = await recipient.page.reload();
		expect( response.status() ).toBe( 404 );
		await expect( recipient.page.locator( '.psst-viewer__gone' ) ).toContainText( 'no longer available' );
		await expect( recipient.page.getByRole( 'link', { name: 'Create your own secret' } ) ).toBeVisible();

		await recipient.context.close();
	} );

	test( 'a pass phrase is checked locally', async ( { page, browser } ) => {
		const url = await createSecret( page, { text: 'guarded', passphrase: 'open sesame' } );
		const recipient = await openAsRecipient( browser, url );

		const passphrase = recipient.page.locator( 'input[type="password"]' );
		await expect( passphrase ).toBeVisible();

		await passphrase.fill( 'wrong' );
		await recipient.page.getByRole( 'button', { name: 'View Secret' } ).click();
		await expect( recipient.page.locator( '.psst-viewer__error' ) ).toContainText( 'did not unlock' );
		expect( recipient.reveals ).toHaveLength( 1 );

		// The retry decrypts the held copy: no second request.
		await passphrase.fill( 'open sesame' );
		await recipient.page.getByRole( 'button', { name: 'View Secret' } ).click();
		await expect( recipient.page.locator( 'pre.psst-secret' ) ).toHaveText( 'guarded' );
		expect( recipient.reveals ).toHaveLength( 1 );

		await recipient.context.close();
	} );

	test( 'shredding kills the link before it is read', async ( { page, browser } ) => {
		const url = await createSecret( page, { text: 'oops' } );

		await page.getByRole( 'button', { name: 'Shred Secret Link' } ).click();
		await expect( page.locator( '.psst-form__shredded' ) ).toBeVisible();

		const recipient = await openAsRecipient( browser, url );
		await expect( recipient.page.locator( '.psst-viewer__gone' ) ).toBeVisible();
		await expect( recipient.page.getByRole( 'button', { name: 'View Secret' } ) ).toHaveCount( 0 );

		await recipient.context.close();
	} );

	test( 'an unknown id is the gone state with a 404', async ( { page } ) => {
		const response = await page.goto( '/s/AAAAAAAAAAAAAAAAAAAAAA/' );

		expect( response.status() ).toBe( 404 );
		await expect( page.locator( '.psst-viewer__gone' ) ).toBeVisible();
		await expect( page.locator( '.psst-viewer__interstitial' ) ).toHaveCount( 0 );
	} );

	test( 'a legacy 1.x link answers 410', async ( { page } ) => {
		const response = await page.goto( '/secret/view/anything/' );

		expect( response.status() ).toBe( 410 );
		await expect( page.locator( '.psst-viewer__gone' ) ).toBeVisible();
	} );

	test( 'the empty viewer page is a 404 that points at the create page', async ( { page } ) => {
		const response = await page.goto( '/s/' );

		expect( response.status() ).toBe( 404 );
		await expect( page.getByRole( 'link', { name: 'Create your own secret' } ) ).toHaveAttribute( 'href', /\/$/ );
	} );
} );
