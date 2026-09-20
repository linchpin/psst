/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	ExternalLink,
	Flex,
	FlexItem,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { useRoute } from '../hooks';

/**
 * A card with a title and an optional description.
 *
 * @param {Object}  props             Props.
 * @param {string}  props.title       Title.
 * @param {string}  props.description Description.
 * @param {Element} props.children    Fields.
 * @return {Element} Card.
 */
function Section( { title, description, children } ) {
	return (
		<Card className="psst-admin__section">
			<CardHeader>
				<div>
					<h2>{ title }</h2>
					{ description && <p>{ description }</p> }
				</div>
			</CardHeader>
			<CardBody>{ children }</CardBody>
		</Card>
	);
}

/**
 * The wp-config.php lines that lock a Turnstile key.
 *
 * @param {Object}  props           Props.
 * @param {Object}  props.turnstile The turnstile part of the settings payload.
 * @param {boolean} props.locked    Whether both keys are already constants.
 * @return {Element} Snippet.
 */
function TurnstileConstants( { turnstile, locked } ) {
	const lines = [];

	if ( ! turnstile.siteKeyByConstant ) {
		lines.push(
			`define( '${ turnstile.siteKeyConstant }', 'your-site-key' );`
		);
	}

	if ( ! turnstile.secretKeyByConstant ) {
		lines.push(
			`define( '${ turnstile.secretKeyConstant }', 'your-secret-key' );`
		);
	}

	return (
		<div className="psst-admin__constants">
			<p className="psst-admin__hint">
				{ locked
					? __(
							'Both keys are defined in wp-config.php, so the fields above are locked. Remove the constants to manage them here.',
							'psst'
						)
					: __(
							'Prefer to keep the keys out of the database? Define either or both in wp-config.php and the matching field locks itself:',
							'psst'
						) }
			</p>
			{ lines.length > 0 && (
				<pre className="psst-admin__code">
					<code>{ lines.join( '\n' ) }</code>
				</pre>
			) }
		</div>
	);
}

/**
 * The settings tab.
 *
 * @return {Element} View.
 */
export default function SettingsView() {
	const { data, error, isLoading, refetch } = useRoute( '/psst/v1/settings' );
	const [ draft, setDraft ] = useState( null );
	const [ turnstileSecret, setTurnstileSecret ] = useState( null );
	const [ isSaving, setSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		if ( data?.settings ) {
			setDraft( data.settings );
			setTurnstileSecret( null );
		}
	}, [ data ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error.message ||
					__( 'The settings could not be loaded.', 'psst' ) }
			</Notice>
		);
	}

	if ( isLoading || ! draft ) {
		return (
			<div className="psst-admin__loading">
				<Spinner />
				<span>{ __( 'Loading settings…', 'psst' ) }</span>
			</div>
		);
	}

	const catalog = data.ttlCatalog || {};
	const set = ( key ) => ( value ) =>
		setDraft( ( current ) => ( { ...current, [ key ]: value } ) );

	const turnstile = data.turnstile || {};

	let secretHelp = __( 'No secret key stored.', 'psst' );

	if ( turnstile.secretKeyByConstant ) {
		secretHelp = `${ __( 'Defined in wp-config.php by', 'psst' ) } ${
			turnstile.secretKeyConstant
		}`;
	} else if ( data.hasTurnstileSecret ) {
		secretHelp = __(
			'A secret key is stored. Enter a new one to replace it, or clear the field and save to remove it.',
			'psst'
		);
	}

	const enabledTtls = draft.ttl_options || [];
	const ttlDefaultOptions = Object.entries( catalog )
		.filter( ( [ minutes ] ) => enabledTtls.includes( Number( minutes ) ) )
		.sort( ( a, b ) => Number( b[ 0 ] ) - Number( a[ 0 ] ) )
		.map( ( [ minutes, label ] ) => ( {
			value: Number( minutes ),
			label,
		} ) );

	const toggleTtl = ( minutes ) => ( checked ) => {
		const next = checked
			? [ ...enabledTtls, minutes ]
			: enabledTtls.filter( ( m ) => m !== minutes );

		if ( next.length === 0 ) {
			return;
		}

		set( 'ttl_options' )( next );
	};

	const save = async () => {
		setSaving( true );

		try {
			const body = { settings: draft };

			if ( turnstileSecret !== null ) {
				body.turnstile_secret = turnstileSecret;
			}

			await apiFetch( {
				path: '/psst/v1/settings',
				method: 'POST',
				data: body,
			} );

			createSuccessNotice( __( 'Settings saved.', 'psst' ), {
				type: 'snackbar',
			} );
			refetch();
		} catch ( err ) {
			createErrorNotice(
				err?.message ||
					__( 'The settings could not be saved.', 'psst' ),
				{ type: 'snackbar' }
			);
		} finally {
			setSaving( false );
		}
	};

	const reset = async () => {
		setSaving( true );

		try {
			await apiFetch( {
				path: '/psst/v1/settings/reset',
				method: 'POST',
			} );
			createSuccessNotice( __( 'Settings reset to defaults.', 'psst' ), {
				type: 'snackbar',
			} );
			refetch();
		} catch ( err ) {
			createErrorNotice( err?.message || __( 'Reset failed.', 'psst' ), {
				type: 'snackbar',
			} );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="psst-admin__view">
			<Section
				title={ __( 'Expiration', 'psst' ) }
				description={ __(
					'Which lifetimes a sender may choose, and which is pre-selected.',
					'psst'
				) }
			>
				<div className="psst-admin__checkbox-grid">
					{ Object.entries( catalog )
						.sort( ( a, b ) => Number( b[ 0 ] ) - Number( a[ 0 ] ) )
						.map( ( [ minutes, label ] ) => (
							<CheckboxControl
								__nextHasNoMarginBottom
								key={ minutes }
								label={ label }
								checked={ enabledTtls.includes(
									Number( minutes )
								) }
								onChange={ toggleTtl( Number( minutes ) ) }
							/>
						) ) }
				</div>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Default expiration', 'psst' ) }
					value={ draft.ttl_default }
					options={ ttlDefaultOptions }
					onChange={ ( value ) =>
						set( 'ttl_default' )( Number( value ) )
					}
				/>
			</Section>

			<Section
				title={ __( 'Limits', 'psst' ) }
				description={ __(
					'Size and rate limits. Rate limits are per hour and per visitor; zero disables one.',
					'psst'
				) }
			>
				<Flex gap={ 4 } wrap align="flex-start">
					<FlexItem isBlock>
						<TextControl
							type="number"
							__next40pxDefaultSize
							label={ __(
								'Maximum secret size (bytes)',
								'psst'
							) }
							min={ 1024 }
							max={ 262144 }
							step={ 1024 }
							value={ draft.max_plaintext_bytes }
							onChange={ ( value ) =>
								set( 'max_plaintext_bytes' )( Number( value ) )
							}
						/>
					</FlexItem>
					<FlexItem isBlock>
						<TextControl
							type="number"
							__next40pxDefaultSize
							label={ __( 'Creates per visitor', 'psst' ) }
							min={ 0 }
							value={ draft.rate_limit_create_per_hour }
							onChange={ ( value ) =>
								set( 'rate_limit_create_per_hour' )(
									Number( value )
								)
							}
						/>
					</FlexItem>
					<FlexItem isBlock>
						<TextControl
							type="number"
							__next40pxDefaultSize
							label={ __( 'Creates site-wide', 'psst' ) }
							min={ 0 }
							value={ draft.rate_limit_create_global_per_hour }
							onChange={ ( value ) =>
								set( 'rate_limit_create_global_per_hour' )(
									Number( value )
								)
							}
						/>
					</FlexItem>
					<FlexItem isBlock>
						<TextControl
							type="number"
							__next40pxDefaultSize
							label={ __( 'Reveals per visitor', 'psst' ) }
							min={ 0 }
							value={ draft.rate_limit_reveal_per_hour }
							onChange={ ( value ) =>
								set( 'rate_limit_reveal_per_hour' )(
									Number( value )
								)
							}
						/>
					</FlexItem>
				</Flex>
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Trusted proxy', 'psst' ) }
					help={ __(
						'Behind Cloudflare, read the visitor address from its header so rate limits count visitors rather than the proxy.',
						'psst'
					) }
					value={ draft.trusted_proxy_header }
					options={ [
						{ value: '', label: __( 'None', 'psst' ) },
						{
							value: 'cloudflare',
							label: __( 'Cloudflare', 'psst' ),
						},
					] }
					onChange={ set( 'trusted_proxy_header' ) }
				/>
			</Section>

			<Section
				title={ __( 'Turnstile', 'psst' ) }
				description={ __(
					'Optional Cloudflare Turnstile challenge on the create form. Needs both keys. The secret key is stored write-only and never shown again.',
					'psst'
				) }
			>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Site key', 'psst' ) }
					help={
						turnstile.siteKeyByConstant
							? `${ __( 'Defined in wp-config.php by', 'psst' ) } ${
									turnstile.siteKeyConstant
								}`
							: undefined
					}
					value={
						turnstile.siteKeyByConstant
							? turnstile.siteKey
							: draft.turnstile_site_key
					}
					onChange={ set( 'turnstile_site_key' ) }
					disabled={ turnstile.siteKeyByConstant }
					autoComplete="off"
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					type="password"
					label={ __( 'Secret key', 'psst' ) }
					help={ secretHelp }
					placeholder={
						turnstile.secretKeyByConstant || data.hasTurnstileSecret
							? '••••••••••••'
							: ''
					}
					value={ turnstileSecret ?? '' }
					onChange={ setTurnstileSecret }
					disabled={ turnstile.secretKeyByConstant }
					autoComplete="new-password"
				/>
				<TurnstileConstants
					turnstile={ turnstile }
					locked={
						turnstile.siteKeyByConstant &&
						turnstile.secretKeyByConstant
					}
				/>
				<ExternalLink href="https://developers.cloudflare.com/turnstile/">
					{ __( 'About Turnstile', 'psst' ) }
				</ExternalLink>
			</Section>

			<Section
				title={ __( 'Accounts', 'psst' ) }
				description={ __(
					'Optional. Lets people sign in on the front end and keep a record of the secrets they have sent. Everything here is off until you turn it on, and sending a secret never requires an account unless you say so.',
					'psst'
				) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Enable front end accounts', 'psst' ) }
					help={ __(
						'Turning this on for the first time creates a sign in, a create account and an account page.',
						'psst'
					) }
					checked={ !! draft.accounts_enabled }
					onChange={ set( 'accounts_enabled' ) }
				/>

				{ !! draft.accounts_enabled && (
					<>
						<p className="psst-admin__hint">
							{ __(
								'The sign in, create account and account pages are managed on the Pages tab, along with the create and viewer pages.',
								'psst'
							) }
						</p>

						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Let visitors create an account',
								'psst'
							) }
							help={ __(
								'WordPress still decides: if registration is closed in Settings → General, or network-wide on multisite, it stays closed.',
								'psst'
							) }
							checked={ !! draft.allow_registration }
							onChange={ set( 'allow_registration' ) }
						/>

						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Require an account to send a secret',
								'psst'
							) }
							help={ __(
								'Off means anyone can still send one anonymously, which is how Psst works by default.',
								'psst'
							) }
							checked={ !! draft.require_login_to_create }
							onChange={ set( 'require_login_to_create' ) }
						/>

						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Keep users out of the WordPress admin',
								'psst'
							) }
							help={ __(
								'Anyone without a content role is redirected to the account page, their profile screen included. Administrators, editors and network administrators are unaffected.',
								'psst'
							) }
							checked={ !! draft.block_admin_access }
							onChange={ set( 'block_admin_access' ) }
						/>

						{ !! draft.block_admin_access && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'If you lock yourself out, wp-login.php?psst=bypass always shows the normal WordPress login.',
									'psst'
								) }
							</Notice>
						) }

						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Record the secrets a signed-in user sends',
								'psst'
							) }
							help={ __(
								'Metadata only: when it was sent, who it was for, when it expires and whether it has been read. Never the secret, which this server cannot read either.',
								'psst'
							) }
							checked={ !! draft.history_enabled }
							onChange={ set( 'history_enabled' ) }
						/>

						{ !! draft.history_enabled && (
							<TextControl
								type="number"
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								label={ __(
									'Keep records for (days)',
									'psst'
								) }
								help={ __(
									'Records are deleted after this long, whatever happened to the secret itself.',
									'psst'
								) }
								min={ 1 }
								max={ 3650 }
								value={ draft.history_retention_days }
								onChange={ ( value ) =>
									set( 'history_retention_days' )(
										Number( value )
									)
								}
							/>
						) }

						<ToggleControl
							__nextHasNoMarginBottom
							label={ __(
								'Let senders email the link from this site',
								'psst'
							) }
							help={ __(
								'Adds an optional "email this to them" box to the form.',
								'psst'
							) }
							checked={ !! draft.email_delivery_enabled }
							onChange={ set( 'email_delivery_enabled' ) }
						/>

						{ !! draft.email_delivery_enabled && (
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'This weakens the guarantee the rest of Psst makes. Normally the decryption key never reaches this server — it lives in the link fragment, which browsers do not send. To write the email, the server has to be given the key, and the key then sits in the recipient’s mailbox. Senders are told this before they tick the box, and it stays off unless they do.',
									'psst'
								) }
							</Notice>
						) }
					</>
				) }
			</Section>

			<Section title={ __( 'Uninstall', 'psst' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __(
						'Delete all secrets and settings on uninstall',
						'psst'
					) }
					help={ __(
						'Secrets are ephemeral by definition. The two pages stay either way.',
						'psst'
					) }
					checked={ !! draft.delete_on_uninstall }
					onChange={ set( 'delete_on_uninstall' ) }
				/>
			</Section>

			<Flex
				className="psst-admin__actions"
				justify="flex-start"
				gap={ 3 }
			>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving }
					onClick={ save }
				>
					{ __( 'Save settings', 'psst' ) }
				</Button>
				<Button
					__next40pxDefaultSize
					variant="tertiary"
					isDestructive
					disabled={ isSaving }
					onClick={ reset }
				>
					{ __( 'Reset to defaults', 'psst' ) }
				</Button>
			</Flex>
		</div>
	);
}
