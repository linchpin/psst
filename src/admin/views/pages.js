/**
 * The Pages tab.
 *
 * Psst needs up to five pages, and a page being selected in the settings is not
 * the same as that page working. A page can be trashed, left as a draft, or
 * have had its blocks removed, and every one of those looks identical from a
 * settings dropdown — the link just stops working. This screen reports what is
 * actually true of each page and offers the one action that fixes it.
 */

/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	ExternalLink,
	Flex,
	FlexItem,
	Notice,
	Spinner,
	ToggleControl,
} from '@wordpress/components';

/**
 * External dependencies
 */
import { SettingsCard } from '@linchpinagency/ui';

/**
 * Internal dependencies
 */
import { useRoute } from '../hooks';
import PagePicker from '../components/page-picker';

/**
 * Everything the screen says about each kind of page.
 *
 * Kept here rather than sent from the server because it is copy, not data. The
 * server reports what is true; this describes what each page is for.
 */
const CORE_KINDS = [ 'create', 'reveal' ];

const COPY = {
	create: {
		label: __( 'Create page', 'psst' ),
		help: __(
			'Holds the Secret Form block. Every "share a secret" link points here.',
			'psst'
		),
	},
	reveal: {
		label: __( 'Viewer page', 'psst' ),
		help: __(
			'Holds the Secret Viewer block. Its slug becomes the prefix of every secret link, so keep it short.',
			'psst'
		),
	},
	login: {
		label: __( 'Sign in page', 'psst' ),
		help: __(
			'Holds the Sign In Form block. wp-login.php redirects here, and so does wp_login_url().',
			'psst'
		),
	},
	register: {
		label: __( 'Create account page', 'psst' ),
		help: __(
			'Holds the Create Account Form block. Only reachable while registration is open in both Psst and WordPress.',
			'psst'
		),
	},
	account: {
		label: __( 'Account page', 'psst' ),
		help: __(
			'Holds the Account block. Also where a user blocked from wp-admin is sent.',
			'psst'
		),
	},
};

/**
 * The badge for a page's state.
 *
 * @param {Object} props      Props.
 * @param {Object} props.page A page row from the REST payload.
 * @return {Element} Status.
 */
function PageStatus( { page } ) {
	const states = {
		ok: {
			className: 'is-ok',
			label: __( 'Ready', 'psst' ),
		},
		missing: {
			className: 'is-problem',
			label: __( 'Not set', 'psst' ),
		},
		unpublished: {
			className: 'is-problem',
			label: __( 'Not published', 'psst' ),
		},
		no_block: {
			className: 'is-warning',
			label: __( 'Missing its block', 'psst' ),
		},
	};

	const state = states[ page.state ] || states.missing;

	return (
		<span className={ `psst-admin__badge ${ state.className }` }>
			{ state.label }
		</span>
	);
}

/**
 * The line explaining a page that is not ready, and what to do about it.
 *
 * @param {Object} props      Props.
 * @param {Object} props.page A page row from the REST payload.
 * @return {Element|null} Explanation.
 */
function PageProblem( { page } ) {
	if ( page.state === 'ok' ) {
		return null;
	}

	let message;

	switch ( page.state ) {
		case 'unpublished':
			message = sprintf(
				/* translators: %s: a post status, e.g. draft or trash. */
				__(
					'This page is %s, so visitors cannot reach it. Publish it, or choose another page.',
					'psst'
				),
				page.status
			);
			break;

		case 'no_block':
			message = sprintf(
				/* translators: %s: a block name, e.g. psst/secret-form. */
				__(
					'This page is published but does not contain the %s block, so it will render as an ordinary empty page. Edit it and add the block, or create a fresh one below.',
					'psst'
				),
				page.block
			);
			break;

		default:
			message = page.required
				? __(
						'Nothing is selected. Psst cannot work without this page.',
						'psst'
					)
				: __( 'Nothing is selected.', 'psst' );
	}

	return (
		<Notice
			status={ page.state === 'no_block' ? 'warning' : 'error' }
			isDismissible={ false }
		>
			{ message }
		</Notice>
	);
}

/**
 * One page slot.
 *
 * A page the accounts switch has turned off keeps its heading and its badge
 * — it is still one of the pages Psst has — but loses its controls. There is
 * nothing to decide about a page that is not in use, and three cards of
 * unusable inputs, each repeating why they were unusable, was most of the
 * screen.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.page     A page row from the REST payload.
 * @param {boolean}  props.isActive Whether the draft has this page in use.
 * @param {number}   props.value    The page id in the unsaved draft.
 * @param {Function} props.onChange Setter.
 * @return {Element} Card.
 */
function PageCard( { page, isActive, value, onChange } ) {
	const copy = COPY[ page.kind ] || { label: page.kind, help: '' };

	return (
		<SettingsCard
			className={ `psst-admin__page-card${ isActive ? '' : ' is-inactive' }` }
			title={
				<>
					{ copy.label } <PageStatus page={ page } />
				</>
			}
			description={ copy.help }
		>
			{ isActive && (
				<>
					<PageProblem page={ page } />

					<PagePicker
						kind={ page.kind }
						label={ __( 'Page', 'psst' ) }
						help={ __(
							'Search for an existing page, or create one that already has the right blocks in it.',
							'psst'
						) }
						value={ value }
						onChange={ onChange }
					/>

					{ page.page_id > 0 && (
						<Flex
							className="psst-admin__page-links"
							justify="flex-start"
							gap={ 3 }
							wrap
						>
							{ page.url && (
								<FlexItem>
									<ExternalLink href={ page.url }>
										{ __( 'View', 'psst' ) }
									</ExternalLink>
								</FlexItem>
							) }
							{ page.edit_url && (
								<FlexItem>
									<ExternalLink href={ page.edit_url }>
										{ __( 'Edit', 'psst' ) }
									</ExternalLink>
								</FlexItem>
							) }
							{ page.url && (
								<FlexItem>
									<code className="psst-admin__page-url">
										{ page.url }
									</code>
								</FlexItem>
							) }
						</Flex>
					) }
				</>
			) }
		</SettingsCard>
	);
}

/**
 * The Pages tab.
 *
 * @return {Element} View.
 */
export default function PagesView() {
	const { data, error, isLoading, refetch } = useRoute(
		'/psst/v1/settings/pages'
	);
	const [ draft, setDraft ] = useState( null );
	const [ isSaving, setSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		if ( data?.pages ) {
			setDraft( {
				...Object.fromEntries(
					data.pages.map( ( page ) => [ page.setting, page.page_id ] )
				),
				accounts_enabled: !! data.accounts_enabled,
			} );
		}
	}, [ data ] );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error.message ||
					__( 'The pages could not be loaded.', 'psst' ) }
			</Notice>
		);
	}

	if ( isLoading || ! draft ) {
		return (
			<div className="psst-admin__loading">
				<Spinner />
				<span>{ __( 'Loading pages…', 'psst' ) }</span>
			</div>
		);
	}

	const accountsOn = !! draft.accounts_enabled;

	/*
	 * Whether a page is in use follows the draft switch, not the `active` the
	 * server sent, so turning accounts on reveals the three pages and counts
	 * their problems immediately rather than after a save.
	 */
	const isActive = ( page ) => CORE_KINDS.includes( page.kind ) || accountsOn;

	const isDirty =
		accountsOn !== !! data.accounts_enabled ||
		data.pages.some( ( page ) => draft[ page.setting ] !== page.page_id );

	const problems = data.pages.filter(
		( page ) => isActive( page ) && page.state !== 'ok'
	);

	const save = async () => {
		setSaving( true );

		try {
			/*
			 * A partial settings object on purpose. Settings::sanitize() keeps
			 * every key it is not given at its stored value, so this screen can
			 * save the five page ids without having to hold, and risk
			 * overwriting, the rest of the settings.
			 */
			await apiFetch( {
				path: '/psst/v1/settings',
				method: 'POST',
				data: { settings: draft },
			} );

			createSuccessNotice( __( 'Pages saved.', 'psst' ), {
				type: 'snackbar',
			} );
			refetch();
		} catch ( err ) {
			createErrorNotice(
				err?.message || __( 'The pages could not be saved.', 'psst' ),
				{ type: 'snackbar' }
			);
		} finally {
			setSaving( false );
		}
	};

	return (
		<>
			{ problems.length > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: how many pages need attention. */
						_n(
							'%d page needs attention before it will work.',
							'%d pages need attention before they will work.',
							problems.length,
							'psst'
						),
						problems.length
					) }
				</Notice>
			) }

			{ data.pages
				.filter( ( page ) => CORE_KINDS.includes( page.kind ) )
				.map( ( page ) => (
					<PageCard
						key={ page.kind }
						page={ page }
						isActive
						value={ draft[ page.setting ] }
						onChange={ ( next ) =>
							setDraft( ( current ) => ( {
								...current,
								[ page.setting ]: next,
							} ) )
						}
					/>
				) ) }

			{ /*
			 * The same setting as the one on the Settings section, shown
			 * again here because this is where its three pages are. Deciding
			 * whether to run accounts and setting up the pages they need is
			 * one job, and it was split across two sections.
			 */ }
			<SettingsCard
				title={ __( 'Front end accounts', 'psst' ) }
				description={ __(
					'Sign in, registration and account pages. The same switch as the one on the Settings section.',
					'psst'
				) }
			>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Enable front end accounts', 'psst' ) }
					help={
						accountsOn
							? __(
									'The three pages below are in use. Each one needs a published page with its block on it.',
									'psst'
								)
							: __(
									'The three pages below are not in use, so there is nothing to set on them. Turning this on for the first time creates them.',
									'psst'
								)
					}
					checked={ accountsOn }
					onChange={ ( next ) =>
						setDraft( ( current ) => ( {
							...current,
							accounts_enabled: next,
						} ) )
					}
				/>
			</SettingsCard>

			{ data.pages
				.filter( ( page ) => ! CORE_KINDS.includes( page.kind ) )
				.map( ( page ) => (
					<PageCard
						key={ page.kind }
						page={ page }
						isActive={ accountsOn }
						value={ draft[ page.setting ] }
						onChange={ ( next ) =>
							setDraft( ( current ) => ( {
								...current,
								[ page.setting ]: next,
							} ) )
						}
					/>
				) ) }

			<SettingsCard
				title={ __( 'Secret links', 'psst' ) }
				description={ __(
					'Built from the viewer page slug. Changing that page changes every future link; links already shared keep working only while the old slug still resolves.',
					'psst'
				) }
			>
				<code className="psst-admin__page-url">
					{ `${ data.secret_url }{id}/` }
				</code>
			</SettingsCard>

			<Flex
				className="psst-admin__actions"
				justify="flex-start"
				gap={ 3 }
			>
				<Button
					__next40pxDefaultSize
					variant="primary"
					isBusy={ isSaving }
					disabled={ isSaving || ! isDirty }
					onClick={ save }
				>
					{ __( 'Save pages', 'psst' ) }
				</Button>
				{ isDirty && (
					<span className="psst-admin__hint">
						{ __( 'You have unsaved changes.', 'psst' ) }
					</span>
				) }
			</Flex>
		</>
	);
}
