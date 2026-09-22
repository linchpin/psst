/**
 * The Psst admin app: settings, pages, the secrets list, health and About
 * Linchpin.
 *
 * Mounted on the page Controller\Admin\Admin_Page registers. Every piece of
 * chrome around the views — the brand bar, the page header, the two-column
 * body, the help column and the footer — comes from @linchpinagency/ui, so
 * what is left in this file is Psst's: which sections exist, what the header
 * says, and where its two buttons go.
 */

/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { external } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

/**
 * External dependencies
 */
import {
	AboutLinchpinPage,
	currentSection,
	LinchpinAdminFooter,
	LinchpinAdminFrame,
	LinchpinAdminLayout,
	LinchpinAdminPage,
	LinchpinAdminTopBar,
	LinchpinNotices,
	sectionNavigation,
} from '@linchpinagency/ui';
import '@linchpinagency/ui/style.css';

/**
 * Internal dependencies
 */
import './../scss/admin.scss';
import { BRAND, LINKS } from './brand';
import Sidebar from './components/sidebar';
import { ReactComponent as PsstLogo } from './logos/psst.svg';
import SettingsView from './views/settings';
import PagesView from './views/pages';
import SecretsView from './views/secrets';
import HealthView from './views/health';

const boot = window.psstAdmin || {};

if ( boot.restUrl ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( boot.restUrl ) );
}

if ( boot.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

const PLUGIN = {
	name: __( 'Psst', 'psst' ),
	slug: 'psst',
	version: boot.version,
};

/*
 * Sections are links, not tab state: `sectionNavigation()` builds one href
 * per section and `currentSection()` reads the active one back out of the
 * query string. So a section is linkable and bookmarkable, a save that
 * reloads the screen lands where it started, and the `?tab=` URLs the
 * end-to-end suite already navigates to keep working.
 */
const SECTIONS = [
	{ name: 'settings', label: __( 'Settings', 'psst' ) },
	{ name: 'pages', label: __( 'Pages', 'psst' ) },
	{ name: 'secrets', label: __( 'Secrets', 'psst' ) },
	{ name: 'health', label: __( 'Health', 'psst' ) },
	{ name: 'about', label: __( 'About', 'psst' ) },
];

/*
 * Two sections render without the help column.
 *
 * The secrets table has eight columns and an actions cell; beside a 300px
 * sidebar half of them have to be scrolled to, which is a poor trade for a
 * panel of copy the reader has seen on every other section. The About page
 * carries the agency's own help and contact links, so the help column beside
 * it would say the same thing twice.
 */
const FULL_WIDTH = [ 'secrets', 'about' ];

/**
 * The view behind a section.
 *
 * @param {Object} props         Props.
 * @param {string} props.section Section name.
 * @return {Element} View.
 */
function View( { section } ) {
	switch ( section ) {
		case 'pages':
			return <PagesView />;
		case 'secrets':
			return <SecretsView />;
		case 'health':
			return <HealthView />;
		case 'about':
			return <AboutLinchpinPage />;
		default:
			return <SettingsView />;
	}
}

/**
 * The app.
 *
 * @return {Element} The app.
 */
function App() {
	const section = currentSection( { sections: SECTIONS } );

	return (
		<LinchpinAdminFrame
			plugin={ PLUGIN }
			brand={ BRAND }
			links={ LINKS }
			topBar={
				<LinchpinAdminTopBar
					logo={
						<PsstLogo
							role="img"
							aria-label={ __( 'Psst', 'psst' ) }
						/>
					}
				/>
			}
		>
			<LinchpinAdminPage
				subTitle={ __(
					'One-time secrets, encrypted in the browser. The server never sees the contents, and neither does this screen.',
					'psst'
				) }
				navigation={ sectionNavigation( { sections: SECTIONS } ) }
				actions={
					<>
						{ boot.createUrl && (
							<Button
								__next40pxDefaultSize
								variant="primary"
								href={ boot.createUrl }
								target="_blank"
								rel="noreferrer"
								icon={ external }
								iconPosition="right"
							>
								{ __( 'Share a secret', 'psst' ) }
							</Button>
						) }
						<Button
							__next40pxDefaultSize
							variant="secondary"
							href={ LINKS.readme }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Documentation', 'psst' ) }
						</Button>
					</>
				}
			>
				<LinchpinNotices />

				<LinchpinAdminLayout
					label={ __( 'About Psst', 'psst' ) }
					sidebar={
						FULL_WIDTH.includes( section ) ? undefined : <Sidebar />
					}
				>
					<View section={ section } />
				</LinchpinAdminLayout>
			</LinchpinAdminPage>

			<LinchpinAdminFooter />
		</LinchpinAdminFrame>
	);
}

domReady( () => {
	const mount = document.getElementById( 'psst-admin' );

	if ( ! mount ) {
		return;
	}

	/*
	 * No ThemeProvider and no SlotFillProvider here: `<LinchpinAdminFrame>`
	 * owns both. It seeds the design system from Psst's brand rather than the
	 * administrator's colour scheme, and hoists the resolved tokens to the
	 * document so snackbars and popovers portalled out of this tree read the
	 * same values.
	 */
	createRoot( mount ).render( <App /> );
} );
