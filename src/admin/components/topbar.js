/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { LINKS } from '../brand';
import { ReactComponent as PsstLogo } from '../logos/psst.svg';
import { ReactComponent as LinchpinLogo } from '../logos/linchpin-logo-white.svg';

/**
 * The brand bar above the page, the same arrangement as Mantle: the product on
 * the left, Linchpin and the version on the right.
 *
 * @param {Object} props         Props.
 * @param {string} props.version Plugin version.
 * @return {Element} Top bar.
 */
export default function TopBar( { version } ) {
	return (
		<div className="psst-admin__topbar">
			<div className="psst-admin__topbar-brand">
				<PsstLogo
					className="psst-admin__topbar-logo"
					role="img"
					aria-label={ __( 'Psst', 'psst' ) }
				/>
			</div>
			<div className="psst-admin__topbar-meta">
				{ version && (
					<span className="psst-admin__topbar-version">
						{ `v${ version }` }
					</span>
				) }
				<a
					className="psst-admin__topbar-linchpin"
					href={ LINKS.linchpin }
					target="_blank"
					rel="noreferrer"
					aria-label={ __( 'Linchpin (opens in a new tab)', 'psst' ) }
				>
					<LinchpinLogo aria-hidden="true" focusable="false" />
				</a>
			</div>
		</div>
	);
}
