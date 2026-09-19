/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { external } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { LINKS } from '../brand';

/**
 * The page header: title, what the screen is for, and the two places an
 * administrator most often wants to go next.
 *
 * @param {Object} props           Props.
 * @param {string} props.createUrl The page holding the create form.
 * @return {Element} Masthead.
 */
export default function Masthead( { createUrl } ) {
	return (
		<header className="psst-admin__masthead">
			<div className="psst-admin__masthead-copy">
				<h1 className="psst-admin__title">{ __( 'Psst', 'psst' ) }</h1>
				<p className="psst-admin__tagline">
					{ __(
						'One-time secrets, encrypted in the browser. The server never sees the contents, and neither does this screen.',
						'psst'
					) }
				</p>
			</div>
			<div className="psst-admin__masthead-actions">
				{ createUrl && (
					<Button
						__next40pxDefaultSize
						variant="primary"
						href={ createUrl }
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
			</div>
		</header>
	);
}
