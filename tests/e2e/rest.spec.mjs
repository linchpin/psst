/**
 * The REST contract, driven directly.
 */
import { test, expect } from '@playwright/test';
import { encrypt } from '../../blocks/src/shared/crypto.js';

const JSON_HEADERS = { 'Content-Type': 'application/json', Accept: 'application/json' };

/**
 * A valid create body.
 *
 * @param {Object} [overrides] Field overrides.
 * @return {Promise<{key: string, body: Object}>} The key and body.
 */
async function validBody( overrides = {} ) {
	const { key, body } = await encrypt( 'rest fixture', { iterations: 600000 } );

	return { key, body: { ...body, ttl_minutes: 60, hp: '', ...overrides } };
}

test.describe( 'REST', () => {
	test( 'create, reveal once, then 404', async ( { request } ) => {
		const { body } = await validBody();

		const created = await request.post( '/wp-json/psst/v1/secrets', { headers: JSON_HEADERS, data: body } );
		expect( created.status() ).toBe( 201 );

		const payload = await created.json();
		expect( payload.id ).toMatch( /^[A-Za-z0-9_-]{22}$/ );
		expect( payload.manage_token ).toMatch( /^[A-Za-z0-9_-]{43}$/ );
		expect( payload.url ).toContain( `/s/${ payload.id }/` );
		expect( created.headers()[ 'cache-control' ] ).toContain( 'no-store' );

		// The page never contains the ciphertext.
		const page = await request.get( payload.url );
		expect( page.status() ).toBe( 200 );
		expect( page.headers()[ 'x-robots-tag' ] ).toContain( 'noindex' );
		expect( page.headers()[ 'cache-control' ] ).toContain( 'no-store' );
		expect( await page.text() ).not.toContain( body.ciphertext );

		const revealed = await request.post( `/wp-json/psst/v1/secrets/${ payload.id }/reveal`, { headers: JSON_HEADERS, data: {} } );
		expect( revealed.status() ).toBe( 200 );

		const envelope = ( await revealed.json() ).envelope;
		expect( envelope.ct ).toBe( body.ciphertext );
		expect( envelope.iv ).toBe( body.iv );
		expect( envelope.check ).toBe( body.check );
		expect( envelope.aad ).toBe( 'psst/2;pp=0' );

		const again = await request.post( `/wp-json/psst/v1/secrets/${ payload.id }/reveal`, { headers: JSON_HEADERS, data: {} } );
		expect( again.status() ).toBe( 404 );
	} );

	test( 'reveal refuses anything that is not JSON', async ( { request } ) => {
		const { body } = await validBody();
		const created = await ( await request.post( '/wp-json/psst/v1/secrets', { headers: JSON_HEADERS, data: body } ) ).json();

		const form = await request.post( `/wp-json/psst/v1/secrets/${ created.id }/reveal`, { form: { x: '1' } } );
		expect( form.status() ).toBe( 415 );

		// Still there.
		const revealed = await request.post( `/wp-json/psst/v1/secrets/${ created.id }/reveal`, { headers: JSON_HEADERS, data: {} } );
		expect( revealed.status() ).toBe( 200 );
	} );

	test( 'shred needs the right token', async ( { request } ) => {
		const { body } = await validBody();
		const created = await ( await request.post( '/wp-json/psst/v1/secrets', { headers: JSON_HEADERS, data: body } ) ).json();

		const wrong = await request.delete( `/wp-json/psst/v1/secrets/${ created.id }`, { headers: { ...JSON_HEADERS, 'X-Psst-Manage-Token': 'nope' } } );
		expect( wrong.status() ).toBe( 403 );

		const right = await request.delete( `/wp-json/psst/v1/secrets/${ created.id }`, { headers: { ...JSON_HEADERS, 'X-Psst-Manage-Token': created.manage_token } } );
		expect( right.status() ).toBe( 200 );

		const revealed = await request.post( `/wp-json/psst/v1/secrets/${ created.id }/reveal`, { headers: JSON_HEADERS, data: {} } );
		expect( revealed.status() ).toBe( 404 );
	} );

	test( 'the envelope is validated field by field', async ( { request } ) => {
		const cases = [
			[ { version: 1 }, 400, 'version' ],
			[ { iv: 'AAECAwQFBgcICQo' }, 400, 'iv' ],
			[ { ttl_minutes: 61 }, 400, null ],
			[ { hp: 'bot' }, 400, null ],
			[ { passphrase: 'leak' }, 400, 'passphrase' ],
			[ { has_passphrase: true }, 400, 'salt' ],
		];

		for ( const [ overrides, status, field ] of cases ) {
			const { body } = await validBody( overrides );
			const response = await request.post( '/wp-json/psst/v1/secrets', { headers: JSON_HEADERS, data: body } );
			expect( response.status(), JSON.stringify( overrides ) ).toBe( status );

			if ( field ) {
				expect( ( await response.json() ).data.field ).toBe( field );
			}
		}
	} );

	test( 'oversized bodies are refused before decoding', async ( { request } ) => {
		const { body } = await validBody( { ciphertext: 'A'.repeat( 70000 ) } );
		const response = await request.post( '/wp-json/psst/v1/secrets', { headers: JSON_HEADERS, data: body } );
		expect( response.status() ).toBe( 413 );
	} );

	test( 'nothing about the type leaks through core', async ( { request } ) => {
		expect( ( await request.get( '/wp-json/wp/v2/psst_secret' ) ).status() ).toBe( 404 );
		expect( ( await request.get( '/wp-sitemap-posts-psst_secret-1.xml' ) ).status() ).toBe( 404 );
		const archive = await request.get( '/?post_type=psst_secret' );
		expect( await archive.text() ).not.toContain( 'psst_secret' );
	} );

	test( 'admin routes need a login', async ( { request } ) => {
		const anonymous = await request.get( '/wp-json/psst/v1/settings' );
		expect( anonymous.status() ).toBe( 401 );
	} );

	test( 'config is public and cacheable', async ( { request } ) => {
		const response = await request.get( '/wp-json/psst/v1/config' );
		expect( response.status() ).toBe( 200 );

		const config = await response.json();
		expect( config.ttlOptions ).toHaveProperty( '10080' );
		expect( config.kdfIterations ).toBe( 600000 );
		expect( config.createUrl ).toMatch( /^http/ );
	} );
} );
