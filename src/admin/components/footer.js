/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { LINKS } from '../brand';

/**
 * The page footer.
 *
 * @param {Object} props         Props.
 * @param {string} props.version Plugin version.
 * @return {Element} Footer.
 */
export default function Footer( { version } ) {
	const links = [
		{
			href: LINKS.github,
			label: version
				? `${ __( 'Psst', 'psst' ) } ${ version }`
				: __( 'Psst', 'psst' ),
		},
		{ href: LINKS.linchpin, label: __( 'Linchpin', 'psst' ) },
		{ href: LINKS.support, label: __( 'Support', 'psst' ) },
		{ href: LINKS.issues, label: __( 'Report an issue', 'psst' ) },
	];

	return (
		<footer className="psst-admin__footer">
			{ links.map( ( item ) => (
				<a
					key={ item.href }
					href={ item.href }
					target="_blank"
					rel="noreferrer"
				>
					{ item.label }
				</a>
			) ) }
		</footer>
	);
}
