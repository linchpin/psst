/**
 * The secret form: encrypt in the browser, post the envelope, show the link.
 */

/**
 * WordPress dependencies
 */
import {
	store,
	getContext,
	getElement,
	getConfig,
} from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { encrypt, isSupported, byteLength } from '../shared/crypto';

const { state: shared } = store( 'psst' );

/*
 * The key and the management token live here, in module scope, and nowhere
 * else: never in context, so no directive can print them into the DOM. The
 * share URL is the one key-bearing value in context, and it has to be.
 */
let secrets = { id: null, token: null };

const config = () => getConfig( 'psst' ) || {};
const i18n = () => shared.i18n || {};

/**
 * POST JSON to a psst/v1 route.
 *
 * @param {string} path    Route, relative to the namespace.
 * @param {Object} body    JSON body.
 * @param {Object} headers Extra headers.
 * @param {string} method  HTTP method.
 * @return {Promise<{ok: boolean, status: number, data: any}>} The response.
 */
async function request( path, body, headers = {}, method = 'POST' ) {
	const response = await fetch( `${ config().restUrl }${ path }`, {
		method,
		credentials: 'omit',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			...headers,
		},
		body: body ? JSON.stringify( body ) : undefined,
	} );

	let data = null;

	try {
		data = await response.json();
	} catch {
		data = null;
	}

	return { ok: response.ok, status: response.status, data };
}

const { state } = store( 'psst/secret-form', {
	state: {
		get isIdle() {
			const { status } = getContext();
			return status === 'idle' || status === 'busy' || status === 'error';
		},
		get isBusy() {
			return getContext().status === 'busy';
		},
		get isConfirmed() {
			return getContext().status === 'confirmed';
		},
		get isShredded() {
			return getContext().status === 'shredded';
		},
		get isUnsupported() {
			return getContext().status === 'unsupported';
		},
		get hasError() {
			return getContext().error !== '';
		},
		get remainingLabel() {
			const { message, maxLength } = getContext();
			const used = byteLength( message || '' );
			const remaining = maxLength - used;

			if ( remaining < 0 ) {
				return `${ Math.abs( remaining ).toLocaleString() } over the limit`;
			}

			return used === 0
				? ''
				: `${ remaining.toLocaleString() } characters remaining`;
		},
		get submitLabel() {
			return getContext().status === 'busy'
				? i18n().creating
				: i18n().create;
		},
		get copyLabel() {
			return getContext().copied ? i18n().copied : i18n().copy;
		},
		get copyAnnouncement() {
			return getContext().copied ? i18n().copied : '';
		},
	},

	actions: {
		updateMessage( event ) {
			getContext().message = event.target.value;
			getContext().error = '';
		},
		updatePassphrase( event ) {
			getContext().passphrase = event.target.value;
		},
		updateExpiry( event ) {
			getContext().expiry = Number( event.target.value );
		},
		updateHp( event ) {
			getContext().hp = event.target.value;
		},
		dismissTip() {
			getContext().tipDismissed = true;
		},
		selectAll( event ) {
			event.target.select();
		},

		*submit( event ) {
			event.preventDefault();

			const context = getContext();
			const message = ( context.message || '' ).replace( /\r\n/g, '\n' );

			if ( message.trim() === '' ) {
				context.error = i18n().required;
				return;
			}

			if ( byteLength( message ) > context.maxLength ) {
				context.error = i18n().tooLong;
				return;
			}

			context.status = 'busy';
			context.error = '';

			const form = getElement().ref;
			const turnstile = form.querySelector(
				'[name="cf-turnstile-response"]'
			);

			try {
				const { key, body } = yield encrypt( message, {
					passphrase: context.passphrase || null,
					iterations: config().kdfIterations,
				} );

				const result = yield request( 'secrets', {
					...body,
					ttl_minutes: context.expiry,
					hp: context.hp || '',
					challenge: turnstile ? turnstile.value : null,
				} );

				if ( ! result.ok ) {
					context.status = 'error';
					context.error =
						result.status === 429
							? i18n().rateLimited
							: result.data?.message || i18n().network;

					if ( window.turnstile ) {
						window.turnstile.reset();
					}

					return;
				}

				secrets = {
					id: result.data.id,
					token: result.data.manage_token,
				};

				context.shareUrl = `${ result.data.url }#${ key }`;
				context.expiresLabel = result.data.expires_label
					? `Expires: ${ result.data.expires_label }`
					: '';
				context.message = '';
				context.passphrase = '';
				context.copied = false;
				context.status = 'confirmed';
			} catch {
				context.status = 'error';
				context.error = i18n().network;
			}
		},

		*copy() {
			try {
				const context = getContext();
				yield navigator.clipboard.writeText( context.shareUrl );
				context.copied = true;
				yield new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
				getContext().copied = false;
			} catch {
				// Clipboard refused: select the field so a manual copy works.
				const input = getElement()
					.ref?.closest( '.psst-form__share' )
					?.querySelector( 'input' );
				input?.focus();
				input?.select();
			}
		},

		*shred() {
			if ( ! secrets.id || ! secrets.token ) {
				return;
			}

			const context = getContext();
			context.status = 'busy';

			const result = yield request(
				`secrets/${ secrets.id }`,
				null,
				{ 'X-Psst-Manage-Token': secrets.token },
				'DELETE'
			);

			// A 404 means it was already read or expired: from here that is the same outcome.
			if ( result.ok || result.status === 404 ) {
				secrets = { id: null, token: null };
				context.shareUrl = '';
				context.status = 'shredded';
				return;
			}

			context.status = 'confirmed';
			context.error = result.data?.message || i18n().network;
		},

		reset() {
			const context = getContext();

			secrets = { id: null, token: null };
			context.message = '';
			context.passphrase = '';
			context.error = '';
			context.shareUrl = '';
			context.expiresLabel = '';
			context.copied = false;
			context.hp = '';
			context.status = 'idle';

			if ( window.turnstile ) {
				window.turnstile.reset();
			}
		},
	},

	callbacks: {
		checkSupport() {
			if ( ! isSupported() ) {
				getContext().status = 'unsupported';
			}
		},

		manageFocus() {
			const root = getElement().ref;

			if ( ! root ) {
				return;
			}

			const { status } = getContext();

			if ( status === 'confirmed' || status === 'shredded' ) {
				const heading = root.querySelector(
					status === 'confirmed'
						? '.psst-form__confirmation .psst-form__heading'
						: '.psst-form__shredded .psst-form__heading'
				);
				heading?.focus();
			}

			if ( status === 'error' ) {
				root.querySelector( 'textarea' )?.focus();
			}
		},
	},
} );

export default state;
