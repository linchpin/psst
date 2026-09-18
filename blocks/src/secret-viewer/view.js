/**
 * The secret viewer: fetch the envelope once, decrypt in the browser, destroy.
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
import {
	decrypt,
	isSupported,
	keyFromFragment,
	WrongPassphraseError,
} from '../shared/crypto';

const { state: shared } = store( 'psst' );

/*
 * Module scope, never context: the URL key and the fetched envelope. Reveal is
 * one-shot on the server, so a wrong pass phrase is retried against this copy.
 */
let held = { key: null, envelope: null };

const config = () => getConfig( 'psst' ) || {};
const i18n = () => shared.i18n || {};

const { state } = store( 'psst/secret-viewer', {
	state: {
		get isInterstitial() {
			const { status } = getContext();
			return (
				status === 'ready' ||
				status === 'protected' ||
				status === 'revealing' ||
				status === 'wrong-passphrase' ||
				status === 'error'
			);
		},
		get isBusy() {
			return getContext().status === 'revealing';
		},
		get isRevealed() {
			return getContext().status === 'revealed';
		},
		get isGone() {
			const { status } = getContext();
			return status === 'gone' || status === 'unsupported';
		},
		get needsPassphrase() {
			const { status } = getContext();
			return (
				status === 'protected' ||
				status === 'wrong-passphrase' ||
				( status === 'revealing' && held.envelope?.kdf )
			);
		},
		get hasError() {
			return getContext().error !== '';
		},
		get viewLabel() {
			return getContext().status === 'revealing'
				? i18n().revealing
				: i18n().view;
		},
		get copyLabel() {
			return getContext().copied ? i18n().copied : i18n().copySecret;
		},
		get copyAnnouncement() {
			return getContext().copied ? i18n().copied : '';
		},
		get goneMessage() {
			return getContext().status === 'unsupported'
				? i18n().unsupported
				: i18n().gone;
		},
	},

	actions: {
		updatePassphrase( event ) {
			getContext().passphrase = event.target.value;
			getContext().error = '';
		},

		submitOnEnter( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				// Click the button so the generator action runs in scope.
				getElement()
					.ref.closest( '.psst-viewer__interstitial' )
					?.querySelector( '.psst-viewer__actions button' )
					?.click();
			}
		},

		dismissWarning() {
			getContext().warningDismissed = true;
		},

		*reveal() {
			const context = getContext();

			if ( ! held.key ) {
				held.key = keyFromFragment( window.location.hash );
			}

			if ( ! held.key ) {
				context.error = i18n().missingKey;
				return;
			}

			const needsPassphrase =
				context.status === 'protected' ||
				context.status === 'wrong-passphrase' ||
				!! held.envelope?.kdf;

			if ( needsPassphrase && ! context.passphrase ) {
				context.error = i18n().passphraseNeeded;
				return;
			}

			context.status = 'revealing';
			context.error = '';

			try {
				if ( ! held.envelope ) {
					const response = yield fetch(
						`${ config().restUrl }secrets/${ context.id }/reveal`,
						{
							method: 'POST',
							credentials: 'omit',
							headers: {
								'Content-Type': 'application/json',
								Accept: 'application/json',
							},
							body: '{}',
						}
					);

					if ( response.status === 404 || response.status === 410 ) {
						context.status = 'gone';
						return;
					}

					if ( ! response.ok ) {
						context.status = 'error';
						context.error =
							response.status === 429
								? i18n().rateLimited
								: i18n().network;
						return;
					}

					const data = yield response.json();
					held.envelope = data.envelope;
				}

				const plaintext = yield decrypt(
					held.envelope,
					held.key,
					context.passphrase || null
				);

				context.plaintext = plaintext;
				context.passphrase = '';
				context.status = 'revealed';

				// The key leaves the browser history the moment it has done its job.
				if ( window.history?.replaceState ) {
					window.history.replaceState(
						null,
						'',
						window.location.pathname + window.location.search
					);
				}
			} catch ( error ) {
				if ( error instanceof WrongPassphraseError ) {
					context.status = 'wrong-passphrase';
					context.error = i18n().wrongPassphrase;
					return;
				}

				if ( held.envelope ) {
					// Fetched fine, will not decrypt: the link is wrong for this secret.
					context.status = 'error';
					context.error = i18n().corrupted;
					return;
				}

				context.status = 'error';
				context.error = i18n().network;
			}
		},

		*copy() {
			try {
				const context = getContext();
				yield navigator.clipboard.writeText( context.plaintext );
				context.copied = true;
				yield new Promise( ( resolve ) => setTimeout( resolve, 2000 ) );
				getContext().copied = false;
			} catch {
				const pre = getElement()
					.ref?.closest( '.psst-viewer__revealed' )
					?.querySelector( '.psst-secret' );

				if ( pre ) {
					const doc = pre.ownerDocument;
					const range = doc.createRange();
					range.selectNodeContents( pre );
					const selection = doc.defaultView.getSelection();
					selection.removeAllRanges();
					selection.addRange( range );
				}
			}
		},
	},

	callbacks: {
		checkKey() {
			if ( ! isSupported() ) {
				getContext().status = 'unsupported';
				return;
			}

			const context = getContext();

			if (
				context.status !== 'ready' &&
				context.status !== 'protected'
			) {
				return;
			}

			held = {
				key: keyFromFragment( window.location.hash ),
				envelope: null,
			};

			if ( ! held.key ) {
				context.error = i18n().missingKey;
			}
		},

		manageFocus() {
			const root = getElement().ref;

			if ( ! root ) {
				return;
			}

			const { status } = getContext();

			if ( status === 'revealed' ) {
				root.querySelector( '.psst-secret' )?.focus();
			}

			if ( status === 'wrong-passphrase' ) {
				root.querySelector( 'input[type="password"]' )?.focus();
			}
		},
	},
} );

export default state;
