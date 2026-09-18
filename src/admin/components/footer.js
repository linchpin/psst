/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * The page footer.
 *
 * @param {Object} props         Props.
 * @param {string} props.version Plugin version.
 * @return {Element} Footer.
 */
export default function Footer( { version } ) {
	return (
		<footer className="psst-admin__footer">
			<a href="https://linchpin.com" target="_blank" rel="noreferrer">
				{ __( 'Linchpin', 'psst' ) }
			</a>
			<a
				href="https://github.com/linchpin/psst"
				target="_blank"
				rel="noreferrer"
			>
				{ __( 'GitHub', 'psst' ) }
			</a>
			{ version && <span>{ `Psst ${ version }` }</span> }
		</footer>
	);
}
