/**
 * The Psst admin app: settings, the secrets list, and health.
 *
 * Mounted on the page Controller\Admin\Admin_Page registers, the same shape as
 * mantle and linchpin-blocks.
 */

/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';
import { SlotFillProvider, TabPanel } from '@wordpress/components';
import { ThemeProvider } from '@wordpress/theme';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import './../scss/admin.scss';
import { BRAND, brandStyle } from './brand';
import TopBar from './components/topbar';
import Masthead from './components/masthead';
import Sidebar from './components/sidebar';
import Footer from './components/footer';
import Notices from './components/notices';
import SettingsView from './views/settings';
import SecretsView from './views/secrets';
import HealthView from './views/health';

const boot = window.psstAdmin || {};

if ( boot.restUrl ) {
	apiFetch.use( apiFetch.createRootURLMiddleware( boot.restUrl ) );
}

if ( boot.nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( boot.nonce ) );
}

const TABS = [
	{
		name: 'settings',
		title: __( 'Settings', 'psst' ),
		className: 'psst-admin__tab',
	},
	{
		name: 'secrets',
		title: __( 'Secrets', 'psst' ),
		className: 'psst-admin__tab',
	},
	{
		name: 'health',
		title: __( 'Health', 'psst' ),
		className: 'psst-admin__tab',
	},
];

/**
 * Which tab the URL asks for.
 *
 * @return {string} Tab name.
 */
function initialTab() {
	const requested = new URLSearchParams( window.location.search ).get(
		'tab'
	);

	return TABS.some( ( tab ) => tab.name === requested )
		? requested
		: 'settings';
}

/**
 * Keep the tab in the URL so a reload lands on the same one.
 *
 * @param {string} tabName Tab name.
 */
function rememberTab( tabName ) {
	const params = new URLSearchParams( window.location.search );
	params.set( 'tab', tabName );
	window.history.replaceState(
		null,
		'',
		`${ window.location.pathname }?${ params.toString() }`
	);
}

/**
 * The view behind a tab.
 *
 * @param {Object} tab The active tab.
 * @return {Element} View.
 */
function renderTab( tab ) {
	let view;

	switch ( tab.name ) {
		case 'secrets':
			view = <SecretsView />;
			break;
		case 'health':
			view = <HealthView />;
			break;
		default:
			view = <SettingsView />;
	}

	return (
		<div className="psst-admin__body">
			<main className="psst-admin__main">{ view }</main>
			<Sidebar />
		</div>
	);
}

/**
 * The app.
 *
 * @return {Element} The app.
 */
function App() {
	return (
		<div className="psst-admin__frame" style={ brandStyle() }>
			<TopBar version={ boot.version } />
			<div className="psst-admin__shell">
				<Masthead createUrl={ boot.createUrl } />
				<Notices />
				<TabPanel
					className="psst-admin__tabs"
					activeClass="is-active"
					initialTabName={ initialTab() }
					tabs={ TABS }
					onSelect={ rememberTab }
				>
					{ renderTab }
				</TabPanel>
				<Footer version={ boot.version } />
			</div>
		</div>
	);
}

domReady( () => {
	const mount = document.getElementById( 'psst-admin' );

	if ( ! mount ) {
		return;
	}

	/*
	 * Seed the design system from Psst's own brand rather than the admin
	 * colour scheme, the way Mantle does: the top bar is already a fixed brand
	 * gradient, so following the profile would put a stranger's accent right
	 * beneath it. `primary` only; the default light background stays. `isRoot`
	 * hoists the resolved tokens to the document so the snackbar notices and
	 * any popover portalled out of this tree read the same values. Exactly one
	 * root provider is allowed per document, so this is the only place it may
	 * be set.
	 */
	createRoot( mount ).render(
		<ThemeProvider
			isRoot
			color={ { primary: BRAND.primary } }
			cornerRadius="subtle"
		>
			<SlotFillProvider>
				<App />
			</SlotFillProvider>
		</ThemeProvider>
	);
} );
