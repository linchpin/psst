/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	ComboboxControl,
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
 * Choose a page by title.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string}   props.help     Help text.
 * @param {number}   props.value    Page id.
 * @param {Function} props.onChange Setter.
 * @return {Element} Control.
 */
function PagePicker( { label, help, value, onChange } ) {
	const pages = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords( 'postType', 'page', {
				per_page: 100,
				status: 'publish',
				orderby: 'title',
				order: 'asc',
				_fields: 'id,title',
			} ),
		[]
	);

	const options = useMemo(
		() =>
			( pages || [] ).map( ( page ) => ( {
				value: String( page.id ),
				label: page.title?.rendered || `#${ page.id }`,
			} ) ),
		[ pages ]
	);

	return (
		<ComboboxControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			value={ value ? String( value ) : '' }
			options={ options }
			onChange={ ( next ) => onChange( next ? Number( next ) : 0 ) }
			allowReset
		/>
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
				title={ __( 'Pages', 'psst' ) }
				description={ __(
					'Secret links point at the viewer page; the form lives on the create page. Both were created on activation and can be changed here.',
					'psst'
				) }
			>
				<Flex gap={ 4 } wrap>
					<FlexItem isBlock>
						<PagePicker
							label={ __( 'Create page', 'psst' ) }
							help={ __(
								'The page holding the Secret Form block. "Create a new secret" links go here.',
								'psst'
							) }
							value={ draft.create_page_id }
							onChange={ set( 'create_page_id' ) }
						/>
					</FlexItem>
					<FlexItem isBlock>
						<PagePicker
							label={ __( 'Viewer page', 'psst' ) }
							help={ __(
								'The page holding the Secret Viewer block. Its slug becomes the link prefix, so keep it short.',
								'psst'
							) }
							value={ draft.reveal_page_id }
							onChange={ set( 'reveal_page_id' ) }
						/>
					</FlexItem>
				</Flex>
			</Section>

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
					'Optional Cloudflare Turnstile challenge on the create form. Needs both keys; the secret key is stored write-only and never shown again.',
					'psst'
				) }
			>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Site key', 'psst' ) }
					value={ draft.turnstile_site_key }
					onChange={ set( 'turnstile_site_key' ) }
					autoComplete="off"
				/>
				{ data.turnstileByConstant ? (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'The secret key is defined by PSST_TURNSTILE_SECRET_KEY in wp-config.php.',
							'psst'
						) }
					</Notice>
				) : (
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						type="password"
						label={ __( 'Secret key', 'psst' ) }
						help={
							data.hasTurnstileSecret
								? __(
										'A secret key is stored. Enter a new one to replace it, or clear the field and save to remove it.',
										'psst'
									)
								: __( 'No secret key stored.', 'psst' )
						}
						placeholder={
							data.hasTurnstileSecret ? '••••••••' : ''
						}
						value={ turnstileSecret ?? '' }
						onChange={ setTurnstileSecret }
						autoComplete="new-password"
					/>
				) }
				<ExternalLink href="https://developers.cloudflare.com/turnstile/">
					{ __( 'About Turnstile', 'psst' ) }
				</ExternalLink>
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
				<span className="psst-admin__hint">
					{ sprintf(
						/* translators: %d: number of enabled expiration choices. */
						__( '%d expiration choices enabled.', 'psst' ),
						enabledTtls.length
					) }
				</span>
			</Flex>
		</div>
	);
}
