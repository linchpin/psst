/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * The page header.
 *
 * @param {Object} props         Props.
 * @param {string} props.version Plugin version.
 * @return {Element} Masthead.
 */
export default function Masthead( { version } ) {
	return (
		<header className="psst-admin__masthead">
			<div>
				<h1 className="psst-admin__title">
					<span className="psst-admin__logo" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="28" height="28">
							<path
								d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 5a3 3 0 1 1 6 0v3H9V7Z"
								fill="currentColor"
							/>
						</svg>
					</span>
					{ __( 'Psst', 'psst' ) }
					{ version && (
						<span className="psst-admin__version">{ version }</span>
					) }
				</h1>
				<p className="psst-admin__tagline">
					{ __(
						'One-time secrets, encrypted in the browser. The server never sees the contents, and neither does this screen.',
						'psst'
					) }
				</p>
			</div>
		</header>
	);
}
