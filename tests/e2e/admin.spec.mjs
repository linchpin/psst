/**
 * The admin app, logged in as the Playground administrator.
 */
import { test, expect } from '@playwright/test';
import { createSecret, login } from './helpers.mjs';

test.describe( 'admin app', () => {
	test.beforeEach( async ( { page } ) => {
		await login( page );
	} );

	test( 'settings load, save, and drive the form', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=psst' );

		const app = page.locator( '#psst-admin' );
		await expect( app.getByRole( 'heading', { name: 'Psst' } ) ).toBeVisible();
		await expect( app.getByRole( 'heading', { name: 'Expiration' } ) ).toBeVisible();

		// Disable the five-minute choice, save, and the front-end select loses it.
		// setChecked rather than uncheck, so a previous run's leftover state cannot fail this one.
		const fiveMinutes = app.getByRole( 'checkbox', { name: '5 Minutes', exact: true } );
		await fiveMinutes.setChecked( false );
		await app.getByRole( 'button', { name: 'Save settings' } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Settings saved.' ) ).toBeVisible();

		await page.goto( '/' );
		await expect( page.locator( 'select[name="expiry"] option[value="5"]' ) ).toHaveCount( 0 );
		await expect( page.locator( 'select[name="expiry"] option[value="60"]' ) ).toHaveCount( 1 );

		// And back.
		await page.goto( '/wp-admin/options-general.php?page=psst' );
		await app.getByRole( 'checkbox', { name: '5 Minutes', exact: true } ).setChecked( true );
		await app.getByRole( 'button', { name: 'Save settings' } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( 'Settings saved.' ) ).toBeVisible();
	} );

	test( 'the secrets list shows metadata only and can shred', async ( { page } ) => {
		const url = await createSecret( page, { text: 'admin can see that I exist, not what I say' } );
		const id = url.match( /\/s\/([A-Za-z0-9_-]+)\// )[ 1 ];

		await page.goto( '/wp-admin/options-general.php?page=psst&tab=secrets' );

		const row = page.locator( '.dataviews-view-table__row', { hasText: id.slice( 0, 8 ) } ).first();
		await expect( row ).toBeVisible();

		const html = await page.locator( '#psst-admin' ).innerHTML();
		expect( html ).not.toContain( 'admin can see' );
		expect( html ).not.toContain( '"ct"' );

		await row.hover();
		await row.getByRole( 'button', { name: 'Shred' } ).click();
		await expect( page.locator( '.components-snackbar' ).getByText( /shredded/ ) ).toBeVisible();

		const response = await page.goto( url );
		expect( response.status() ).toBe( 404 );
	} );

	test( 'health reports the scheduler', async ( { page } ) => {
		await page.goto( '/wp-admin/options-general.php?page=psst&tab=health' );

		const health = page.locator( '.psst-admin__health' );
		await expect( health ).toContainText( 'Action Scheduler' );
		await expect( health ).toContainText( 'Available' );
		await expect( health ).toContainText( 'Viewer page' );
	} );
} );
