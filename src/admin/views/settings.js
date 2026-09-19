/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
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
import { plus } from '@wordpress/icons';

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
 * A page's title, or a placeholder when it has none.
 *
 * @param {Object} page A REST page record.
 * @return {string} Label.
 */
function pageLabel( page ) {
	const title = page?.title?.rendered || `#${ page?.id }`;

	// Two pages can share a title (a second "Secret" page, say); the slug is
	// what tells them apart, and it is also what the share links will show.
	return page?.slug ? `${ title } (/${ page.slug }/)` : title;
}

/**
 * Choose a page by searching for it, or make a new one that already holds
 * the blocks this kind of page needs.
 *
 * The search runs on the server: a site with hundreds of pages must not be
 * handed a truncated list to scroll through. The selected page is fetched by
 * id so its title shows whether or not it is in the current results.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string}   props.help     Help text.
 * @param {string}   props.kind     'create' or 'reveal', for the new-page route.
 * @param {number}   props.value    Page id.
 * @param {Function} props.onChange Setter.
 * @return {Element} Control.
 */
function PagePicker( { label, help, kind, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ isSearching, setSearching ] = useState( false );
	const [ isCreating, setCreating ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const selected = useSelect(
		( select ) =>
			value
				? select( coreStore ).getEntityRecord(
						'postType',
						'page',
						value,
						{ _fields: 'id,title,slug' }
					)
				: null,
		[ value ]
	);

	useEffect( () => {
		let cancelled = false;
		setSearching( true );

		const query = new URLSearchParams( {
			per_page: '20',
			status: 'publish',
			orderby: search ? 'relevance' : 'title',
			order: 'asc',
			_fields: 'id,title,slug',
		} );

		if ( search ) {
			query.set( 'search', search );
		}

		const timer = setTimeout( () => {
			apiFetch( { path: `/wp/v2/pages?${ query.toString() }` } )
				.then( ( pages ) => {
					if ( ! cancelled ) {
						setResults( Array.isArray( pages ) ? pages : [] );
					}
				} )
				.catch( () => {
					if ( ! cancelled ) {
						setResults( [] );
					}
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setSearching( false );
					}
				} );
		}, 250 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ search ] );

	const options = useMemo( () => {
		const list = results.map( ( page ) => ( {
			value: String( page.id ),
			label: pageLabel( page ),
		} ) );

		if (
			selected &&
			! list.some( ( o ) => o.value === String( selected.id ) )
		) {
			list.unshift( {
				value: String( selected.id ),
				label: pageLabel( selected ),
			} );
		}

		return list;
	}, [ results, selected ] );

	const createPage = async () => {
		setCreating( true );

		try {
			const page = await apiFetch( {
				path: '/psst/v1/settings/pages',
				method: 'POST',
				data: { kind },
			} );

			onChange( Number( page.id ) );
			createSuccessNotice(
				__(
					'Page created and selected. Save settings to start using it.',
					'psst'
				),
				{
					type: 'snackbar',
					actions: page.link
						? [
								{
									label: __( 'View page', 'psst' ),
									url: page.link,
								},
							]
						: [],
				}
			);
		} catch ( err ) {
			createErrorNotice(
				err?.message || __( 'The page could not be created.', 'psst' ),
				{ type: 'snackbar' }
			);
		} finally {
			setCreating( false );
		}
	};

	return (
		<div className="psst-admin__page-picker">
			<ComboboxControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				help={ help }
				value={ value ? String( value ) : '' }
				options={ options }
				isLoading={ isSearching }
				onFilterValueChange={ setSearch }
				onChange={ ( next ) => onChange( next ? Number( next ) : 0 ) }
				placeholder={ __( 'Search pages…', 'psst' ) }
				allowReset
			/>
			<Button
				__next40pxDefaultSize
				variant="tertiary"
				icon={ plus }
				isBusy={ isCreating }
				disabled={ isCreating }
				onClick={ createPage }
			>
				{ __( 'Create a new page', 'psst' ) }
			</Button>
		</div>
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
				title={ __( 'Pages', 'psst' ) }
				description={ __(
					'Secret links point at the viewer page; the form lives on the create page. Both were created on activation and can be changed here.',
					'psst'
				) }
			>
				<Flex gap={ 4 } wrap>
					<FlexItem isBlock>
						<PagePicker
							kind="create"
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
							kind="reveal"
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
