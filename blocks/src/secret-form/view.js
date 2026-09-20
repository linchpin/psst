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
 * Cookie-less by default, which is what makes an anonymous secret anonymous:
 * the server is told nothing about who is sending it, because it is sent
 * nothing to be told with. A nonce is only ever present for a signed-in sender
 * on a site that has turned accounts on, and only then do credentials go with
 * the request — because only then is there a session the server needs.
 *
 * @param {string} path    Route, relative to the namespace.
 * @param {Object} body    JSON body.
 * @param {Object} headers Extra headers.
 * @param {string} method  HTTP method.
 * @param {string} nonce   REST nonce, or '' to stay anonymous.
 * @return {Promise<{ok: boolean, status: number, data: any}>} The response.
 */
async function request(
	path,
	body,
	headers = {},
	method = 'POST',
	nonce = ''
) {
	const response = await fetch( `${ config().restUrl }${ path }`, {
		method,
		credentials: nonce ? 'same-origin' : 'omit',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			...( nonce ? { 'X-WP-Nonce': nonce } : {} ),
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

/**
 * Ask the server to email the share link.
 *
 * Only the key fragment is sent, never a whole URL: the server rebuilds the
 * rest from the id it already has, so this cannot be used to mail an arbitrary
 * link from the site's own domain.
 *
 * @param {Object} context The block context.
 * @param {string} key     The key fragment.
 * @return {Promise<void>} Resolves once the attempt has been reported.
 */
async function sendShareEmail( context, key ) {
	context.emailState = 'sending';

	try {
		const result = await request(
			`secrets/${ secrets.id }/notify`,
			{
				recipient: context.recipient,
				fragment: key,
				note: context.note || '',
			},
			{ 'X-Psst-Manage-Token': secrets.token },
			'POST',
			context.nonce || ''
		);

		context.emailState = result.ok ? 'sent' : 'failed';
	} catch {
		context.emailState = 'failed';
	}
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
		get hasEmailState() {
			return getContext().emailState !== '';
		},
		get emailMessage() {
			const { emailState, recipient } = getContext();

			if ( emailState === 'sending' ) {
				return i18n().emailSending;
			}

			if ( emailState === 'sent' ) {
				return ( i18n().emailSent || '' ).replace( '%s', recipient );
			}

			if ( emailState === 'failed' ) {
				return i18n().emailFailed;
			}

			return '';
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
		updateRecipient( event ) {
			getContext().recipient = event.target.value;
		},
		updateNote( event ) {
			getContext().note = event.target.value;
		},
		toggleSendEmail( event ) {
			getContext().sendEmail = event.target.checked;
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

				const result = yield request(
					'secrets',
					{
						...body,
						ttl_minutes: context.expiry,
						hp: context.hp || '',
						challenge: turnstile ? turnstile.value : null,
						recipient: context.recipient || '',
					},
					{},
					'POST',
					context.nonce || ''
				);

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

				/*
				 * The one place a key is deliberately handed to the server, and
				 * only when the sender ticked the box that says so. It happens
				 * after the secret exists, so a mail failure costs the email and
				 * not the secret — the link is already on screen to copy.
				 */
				if ( context.sendEmail && context.recipient ) {
					yield sendShareEmail( context, key );
				}
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
				'DELETE',
				context.nonce || ''
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
			context.recipient = '';
			context.note = '';
			context.sendEmail = false;
			context.emailState = '';
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
