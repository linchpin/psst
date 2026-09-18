/**
 * The client side of the psst/2 envelope.
 *
 * The single implementation of the protocol the server validates in
 * includes/Model/Envelope.php. Plain WebCrypto, no dependencies, so it runs
 * unchanged in the browser and under Node's test runner.
 *
 *   ppBits = hasPassphrase ? PBKDF2(passphrase, salt, iterations) : empty
 *   aesKey = HKDF-SHA-256(ikm = urlKey, salt = ppBits, info = "psst/2/content")
 *   chkKey = HKDF-SHA-256(ikm = urlKey, salt = ppBits, info = "psst/2/check")
 *   check  = HMAC-SHA-256(chkKey, "psst")[0..16]
 *   ct     = AES-256-GCM(aesKey, iv, plaintext, aad = "psst/2;pp=0|1")
 *
 * The server never sees urlKey, the passphrase or the plaintext.
 */

export const VERSION = 2;
export const KEY_BYTES = 32;
export const IV_BYTES = 12;
export const SALT_BYTES = 16;
export const CHECK_BYTES = 16;
export const DEFAULT_ITERATIONS = 600000;

const encoder = new TextEncoder();
const decoder = new TextDecoder();

/**
 * The recipient typed the wrong pass phrase. Safe to retry locally.
 */
export class WrongPassphraseError extends Error {
	constructor() {
		super( 'wrong-passphrase' );
		this.name = 'WrongPassphraseError';
	}
}

/**
 * The envelope does not decrypt with this key: altered link, altered
 * ciphertext, or a key for a different secret.
 */
export class CorruptedError extends Error {
	constructor() {
		super( 'corrupted' );
		this.name = 'CorruptedError';
	}
}

/**
 * Whether this runtime can run the protocol at all.
 *
 * @return {boolean} True when WebCrypto and the text codecs exist.
 */
export function isSupported() {
	return (
		typeof globalThis.crypto !== 'undefined' &&
		typeof globalThis.crypto.subtle !== 'undefined' &&
		typeof globalThis.crypto.getRandomValues === 'function'
	);
}

const subtle = () => globalThis.crypto.subtle;

/**
 * Random bytes.
 *
 * @param {number} length Byte count.
 * @return {Uint8Array} Bytes.
 */
export function randomBytes( length ) {
	return globalThis.crypto.getRandomValues( new Uint8Array( length ) );
}

/**
 * base64url (RFC 4648 §5), no padding.
 *
 * @param {Uint8Array|ArrayBuffer} bytes Bytes.
 * @return {string} Encoded.
 */
export function toBase64Url( bytes ) {
	const view = bytes instanceof Uint8Array ? bytes : new Uint8Array( bytes );
	let binary = '';

	for ( let i = 0; i < view.length; i++ ) {
		binary += String.fromCharCode( view[ i ] );
	}

	return btoa( binary )
		.replace( /\+/g, '-' )
		.replace( /\//g, '_' )
		.replace( /=+$/, '' );
}

/**
 * Decode base64url. Throws on anything outside the alphabet.
 *
 * @param {string} encoded Encoded.
 * @return {Uint8Array} Bytes.
 */
export function fromBase64Url( encoded ) {
	if ( typeof encoded !== 'string' || ! /^[A-Za-z0-9_-]*$/.test( encoded ) ) {
		throw new CorruptedError();
	}

	const padded =
		encoded.replace( /-/g, '+' ).replace( /_/g, '/' ) +
		'='.repeat( ( 4 - ( encoded.length % 4 ) ) % 4 );
	const binary = atob( padded );
	const bytes = new Uint8Array( binary.length );

	for ( let i = 0; i < binary.length; i++ ) {
		bytes[ i ] = binary.charCodeAt( i );
	}

	return bytes;
}

/**
 * A fresh URL key.
 *
 * @return {Uint8Array} 32 random bytes.
 */
export function generateKey() {
	return randomBytes( KEY_BYTES );
}

/**
 * The key for a URL fragment.
 *
 * @param {Uint8Array} key Raw key.
 * @return {string} base64url, 43 characters.
 */
export function exportKey( key ) {
	return toBase64Url( key );
}

/**
 * Read the key back out of a URL fragment.
 *
 * Accepts `#<key>` and, for forward compatibility, `#k=<key>`.
 *
 * @param {string} hash `location.hash`.
 * @return {Uint8Array|null} The key, or null when the fragment does not hold one.
 */
export function keyFromFragment( hash ) {
	if ( typeof hash !== 'string' ) {
		return null;
	}

	let value = hash.replace( /^#/, '' );

	if ( value.startsWith( 'k=' ) ) {
		value = value.slice( 2 );
	}

	if ( ! /^[A-Za-z0-9_-]{43}$/.test( value ) ) {
		return null;
	}

	try {
		const bytes = fromBase64Url( value );

		return bytes.length === KEY_BYTES ? bytes : null;
	} catch {
		return null;
	}
}

/**
 * The bytes a passphrase contributes, or none.
 *
 * @param {string|null} passphrase The pass phrase.
 * @param {Uint8Array}  salt       16 bytes.
 * @param {number}      iterations PBKDF2 iterations.
 * @return {Promise<Uint8Array>} 32 bytes, or an empty array without a passphrase.
 */
async function passphraseBits( passphrase, salt, iterations ) {
	if ( ! passphrase ) {
		return new Uint8Array( 0 );
	}

	const material = await subtle().importKey(
		'raw',
		encoder.encode( passphrase.normalize( 'NFKC' ) ),
		'PBKDF2',
		false,
		[ 'deriveBits' ]
	);

	const bits = await subtle().deriveBits(
		{ name: 'PBKDF2', hash: 'SHA-256', salt, iterations },
		material,
		256
	);

	return new Uint8Array( bits );
}

/**
 * Derive the content key and the check value.
 *
 * @param {Uint8Array}  urlKey     The 32-byte URL key.
 * @param {string|null} passphrase The pass phrase, or null.
 * @param {Uint8Array}  salt       The salt (ignored without a passphrase).
 * @param {number}      iterations PBKDF2 iterations.
 * @return {Promise<{aesKey: CryptoKey, check: string}>} Derived material.
 */
export async function deriveKeys( urlKey, passphrase, salt, iterations ) {
	const ppBits = await passphraseBits( passphrase, salt, iterations );

	const ikm = await subtle().importKey( 'raw', urlKey, 'HKDF', false, [
		'deriveKey',
	] );

	const aesKey = await subtle().deriveKey(
		{
			name: 'HKDF',
			hash: 'SHA-256',
			salt: ppBits,
			info: encoder.encode( 'psst/2/content' ),
		},
		ikm,
		{ name: 'AES-GCM', length: 256 },
		false,
		[ 'encrypt', 'decrypt' ]
	);

	const chkKey = await subtle().deriveKey(
		{
			name: 'HKDF',
			hash: 'SHA-256',
			salt: ppBits,
			info: encoder.encode( 'psst/2/check' ),
		},
		ikm,
		{ name: 'HMAC', hash: 'SHA-256', length: 256 },
		false,
		[ 'sign' ]
	);

	const mac = await subtle().sign( 'HMAC', chkKey, encoder.encode( 'psst' ) );

	return {
		aesKey,
		check: toBase64Url( new Uint8Array( mac ).slice( 0, CHECK_BYTES ) ),
	};
}

/**
 * The AAD string for an envelope.
 *
 * @param {boolean} hasPassphrase Whether a pass phrase is in play.
 * @return {string} AAD.
 */
export function aadFor( hasPassphrase ) {
	return `psst/2;pp=${ hasPassphrase ? '1' : '0' }`;
}

/**
 * Encrypt a plaintext for the server.
 *
 * @param {string}     plaintext            The secret.
 * @param {Object}     [options]            Options.
 * @param {string}     [options.passphrase] Optional pass phrase.
 * @param {number}     [options.iterations] PBKDF2 iterations.
 * @param {Uint8Array} [options.key]        Use this key instead of a fresh one (tests).
 * @return {Promise<{key: string, body: Object}>} The URL key and the create request body.
 */
export async function encrypt( plaintext, options = {} ) {
	const passphrase = options.passphrase ? String( options.passphrase ) : null;
	const iterations = options.iterations || DEFAULT_ITERATIONS;
	const urlKey = options.key || generateKey();
	const salt = passphrase ? randomBytes( SALT_BYTES ) : new Uint8Array( 0 );
	const iv = randomBytes( IV_BYTES );

	const { aesKey, check } = await deriveKeys(
		urlKey,
		passphrase,
		salt,
		iterations
	);

	const ciphertext = await subtle().encrypt(
		{
			name: 'AES-GCM',
			iv,
			additionalData: encoder.encode( aadFor( !! passphrase ) ),
			tagLength: 128,
		},
		aesKey,
		encoder.encode( plaintext )
	);

	const body = {
		version: VERSION,
		ciphertext: toBase64Url( ciphertext ),
		iv: toBase64Url( iv ),
		has_passphrase: !! passphrase,
		salt: passphrase ? toBase64Url( salt ) : null,
		kdf: passphrase
			? {
					name: 'PBKDF2',
					hash: 'SHA-256',
					iterations,
				}
			: null,
		check,
	};

	return { key: exportKey( urlKey ), body };
}

/**
 * Decrypt a stored envelope.
 *
 * The check runs first so a wrong pass phrase is told apart from a corrupted
 * envelope without a server round trip; reveal is one-shot, so the caller
 * holds the envelope and retries this locally.
 *
 * @param {Object}      envelope     As returned by the reveal route.
 * @param {Uint8Array}  urlKey       The key from the fragment.
 * @param {string|null} [passphrase] The pass phrase, if the envelope has one.
 * @return {Promise<string>} The plaintext.
 * @throws {WrongPassphraseError|CorruptedError}
 */
export async function decrypt( envelope, urlKey, passphrase = null ) {
	if ( ! envelope || envelope.v !== VERSION || envelope.alg !== 'A256GCM' ) {
		throw new CorruptedError();
	}

	const hasPassphrase = !! envelope.kdf;

	if ( envelope.aad !== aadFor( hasPassphrase ) ) {
		throw new CorruptedError();
	}

	if ( hasPassphrase && ! passphrase ) {
		throw new WrongPassphraseError();
	}

	const salt = hasPassphrase
		? fromBase64Url( envelope.kdf.salt )
		: new Uint8Array( 0 );
	const iterations = hasPassphrase
		? Number( envelope.kdf.iterations )
		: DEFAULT_ITERATIONS;

	const { aesKey, check } = await deriveKeys(
		urlKey,
		hasPassphrase ? passphrase : null,
		salt,
		iterations
	);

	if ( check !== envelope.check ) {
		throw hasPassphrase ? new WrongPassphraseError() : new CorruptedError();
	}

	let plaintext;

	try {
		plaintext = await subtle().decrypt(
			{
				name: 'AES-GCM',
				iv: fromBase64Url( envelope.iv ),
				additionalData: encoder.encode( envelope.aad ),
				tagLength: 128,
			},
			aesKey,
			fromBase64Url( envelope.ct )
		);
	} catch {
		throw new CorruptedError();
	}

	return decoder.decode( plaintext );
}

/**
 * UTF-8 byte length of a string, for the size cap.
 *
 * @param {string} text The text.
 * @return {number} Bytes.
 */
export function byteLength( text ) {
	return encoder.encode( text ).length;
}
