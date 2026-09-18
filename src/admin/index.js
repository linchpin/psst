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
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import './../scss/admin.scss';
import Masthead from './components/masthead';
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
 * The app.
 *
 * @return {Element} The app.
 */
function App() {
	return (
		<SlotFillProvider>
			<div className="psst-admin__shell">
				<Masthead version={ boot.version } />
				<Notices />
				<TabPanel
					className="psst-admin__tabs"
					activeClass="is-active"
					initialTabName={ initialTab() }
					tabs={ TABS }
					onSelect={ rememberTab }
				>
					{ ( tab ) => {
						switch ( tab.name ) {
							case 'secrets':
								return <SecretsView />;
							case 'health':
								return <HealthView />;
							default:
								return <SettingsView />;
						}
					} }
				</TabPanel>
				<Footer version={ boot.version } />
			</div>
		</SlotFillProvider>
	);
}

domReady( () => {
	const mount = document.getElementById( 'psst-admin' );

	if ( ! mount ) {
		return;
	}

	createRoot( mount ).render( <App /> );
} );
