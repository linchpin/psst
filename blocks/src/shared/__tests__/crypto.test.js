/**
 * Vectors for the psst/2 envelope.
 *
 * Runs under Node's WebCrypto, the same primitives the browser uses.
 */
import {
	encrypt,
	decrypt,
	deriveKeys,
	fromBase64Url,
	toBase64Url,
	keyFromFragment,
	generateKey,
	WrongPassphraseError,
	CorruptedError,
	byteLength,
} from '../crypto';

const FIXTURE =
	'multi\nline\twith\ttabs  and  double  spaces\n\n  leading spaces\nünïcödé ✓';

/**
 * Turn a create body plus its key into what the reveal route returns.
 *
 * @param {Object} body The create body.
 * @return {Object} The stored envelope.
 */
function toEnvelope( body ) {
	return {
		v: 2,
		alg: 'A256GCM',
		aad: `psst/2;pp=${ body.has_passphrase ? '1' : '0' }`,
		iv: body.iv,
		ct: body.ciphertext,
		kdf: body.has_passphrase ? { ...body.kdf, salt: body.salt } : null,
		check: body.check,
	};
}

describe( 'base64url', () => {
	it( 'round-trips RFC 4648 vectors without padding', () => {
		const cases = [
			[ '', '' ],
			[ 'f', 'Zg' ],
			[ 'fo', 'Zm8' ],
			[ 'foo', 'Zm9v' ],
			[ 'foob', 'Zm9vYg' ],
			[ 'fooba', 'Zm9vYmE' ],
			[ 'foobar', 'Zm9vYmFy' ],
		];

		for ( const [ plain, encoded ] of cases ) {
			const bytes = new TextEncoder().encode( plain );
			expect( toBase64Url( bytes ) ).toBe( encoded );
			expect( Array.from( fromBase64Url( encoded ) ) ).toEqual(
				Array.from( bytes )
			);
		}
	} );

	it( 'uses - and _ rather than + and /', () => {
		expect( toBase64Url( new Uint8Array( [ 0xfb, 0xff ] ) ) ).toBe( '-_8' );
	} );

	it( 'rejects the standard alphabet', () => {
		expect( () => fromBase64Url( 'Zm9v+' ) ).toThrow( CorruptedError );
	} );
} );

describe( 'keyFromFragment', () => {
	it( 'reads a bare key and a k= key', () => {
		const key = generateKey();
		const encoded = toBase64Url( key );

		expect( Array.from( keyFromFragment( `#${ encoded }` ) ) ).toEqual(
			Array.from( key )
		);
		expect( Array.from( keyFromFragment( `#k=${ encoded }` ) ) ).toEqual(
			Array.from( key )
		);
	} );

	it( 'returns null for anything that is not 32 bytes', () => {
		expect( keyFromFragment( '' ) ).toBeNull();
		expect( keyFromFragment( '#' ) ).toBeNull();
		expect( keyFromFragment( '#short' ) ).toBeNull();
		expect( keyFromFragment( `#${ 'A'.repeat( 44 ) }` ) ).toBeNull();
	} );
} );

describe( 'encrypt / decrypt', () => {
	it( 'round-trips without a passphrase', async () => {
		const { key, body } = await encrypt( FIXTURE );

		expect( body.version ).toBe( 2 );
		expect( body.has_passphrase ).toBe( false );
		expect( body.salt ).toBeNull();
		expect( body.kdf ).toBeNull();
		expect( fromBase64Url( body.iv ).length ).toBe( 12 );
		expect( fromBase64Url( body.check ).length ).toBe( 16 );
		expect( key ).toMatch( /^[A-Za-z0-9_-]{43}$/ );

		const plaintext = await decrypt(
			toEnvelope( body ),
			keyFromFragment( `#${ key }` )
		);

		expect( plaintext ).toBe( FIXTURE );
	} );

	it( 'round-trips with a passphrase', async () => {
		const { key, body } = await encrypt( FIXTURE, {
			passphrase: 'correct horse',
			iterations: 1000,
		} );

		expect( body.has_passphrase ).toBe( true );
		expect( fromBase64Url( body.salt ).length ).toBe( 16 );
		expect( body.kdf ).toEqual( {
			name: 'PBKDF2',
			hash: 'SHA-256',
			iterations: 1000,
		} );

		const plaintext = await decrypt(
			toEnvelope( body ),
			fromBase64Url( key ),
			'correct horse'
		);

		expect( plaintext ).toBe( FIXTURE );
	} );

	it( 'tells a wrong passphrase apart from corruption, without decrypting', async () => {
		const { key, body } = await encrypt( 'x', {
			passphrase: 'right',
			iterations: 1000,
		} );
		const envelope = toEnvelope( body );

		await expect(
			decrypt( envelope, fromBase64Url( key ), 'wrong' )
		).rejects.toBeInstanceOf( WrongPassphraseError );

		await expect(
			decrypt( envelope, fromBase64Url( key ), null )
		).rejects.toBeInstanceOf( WrongPassphraseError );
	} );

	it( 'reports a flipped ciphertext byte as corruption', async () => {
		const { key, body } = await encrypt( 'payload' );
		const ct = fromBase64Url( body.ciphertext );
		ct[ 0 ] = ( ct[ 0 ] + 1 ) % 256;
		const envelope = { ...toEnvelope( body ), ct: toBase64Url( ct ) };

		await expect(
			decrypt( envelope, fromBase64Url( key ) )
		).rejects.toBeInstanceOf( CorruptedError );
	} );

	it( 'reports a flipped passphrase flag as corruption', async () => {
		const { key, body } = await encrypt( 'payload' );
		const envelope = { ...toEnvelope( body ), aad: 'psst/2;pp=1' };

		await expect(
			decrypt( envelope, fromBase64Url( key ) )
		).rejects.toBeInstanceOf( CorruptedError );
	} );

	it( 'reports a key for a different secret as corruption', async () => {
		const { body } = await encrypt( 'payload' );

		await expect(
			decrypt( toEnvelope( body ), generateKey() )
		).rejects.toBeInstanceOf( CorruptedError );
	} );

	it( 'produces a distinct check per key even for the same plaintext', async () => {
		const a = await encrypt( 'same' );
		const b = await encrypt( 'same' );

		expect( a.body.check ).not.toBe( b.body.check );
		expect( a.body.ciphertext ).not.toBe( b.body.ciphertext );
	} );
} );

describe( 'deriveKeys', () => {
	it( 'is deterministic for the same inputs', async () => {
		const key = generateKey();
		const salt = new Uint8Array( 16 );

		const a = await deriveKeys( key, 'pp', salt, 1000 );
		const b = await deriveKeys( key, 'pp', salt, 1000 );

		expect( a.check ).toBe( b.check );
	} );

	it( 'changes with the passphrase, the salt and the iterations', async () => {
		const key = generateKey();
		const salt = new Uint8Array( 16 );
		const otherSalt = new Uint8Array( 16 ).fill( 1 );

		const base = await deriveKeys( key, 'pp', salt, 1000 );

		expect( ( await deriveKeys( key, 'pq', salt, 1000 ) ).check ).not.toBe(
			base.check
		);
		expect(
			( await deriveKeys( key, 'pp', otherSalt, 1000 ) ).check
		).not.toBe( base.check );
		expect( ( await deriveKeys( key, 'pp', salt, 1001 ) ).check ).not.toBe(
			base.check
		);
	} );
} );

describe( 'byteLength', () => {
	it( 'counts UTF-8 bytes, not characters', () => {
		expect( byteLength( 'abc' ) ).toBe( 3 );
		expect( byteLength( 'ü' ) ).toBe( 2 );
		expect( byteLength( '✓' ) ).toBe( 3 );
	} );
} );
