/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, Icon } from '@wordpress/components';
import { lifesaver, link, lock, trash } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { LINKS } from '../brand';
import { ReactComponent as LinchpinLogo } from '../logos/linchpin-logo-primary.svg';

const HOW_IT_WORKS = [
	{
		icon: lock,
		text: __(
			'Encrypted in the sender’s browser. WordPress stores ciphertext it has no key for.',
			'psst'
		),
	},
	{
		icon: link,
		text: __(
			'The key rides in the link’s #fragment, which browsers never send to a server.',
			'psst'
		),
	},
	{
		icon: trash,
		text: __(
			'Revealed once, then destroyed. Anything unread expires on schedule.',
			'psst'
		),
	},
];

/**
 * The column beside the tabs: what Psst does, where to get help, and who
 * makes it. The same role the ads column plays in linchpin-blocks and the
 * support card in Mantle.
 *
 * @return {Element} Sidebar.
 */
export default function Sidebar() {
	return (
		<aside
			className="psst-admin__aside"
			aria-label={ __( 'About Psst', 'psst' ) }
		>
			<Card className="psst-admin__aside-card" size="small">
				<CardBody>
					<h2 className="psst-admin__aside-title">
						{ __( 'How a secret travels', 'psst' ) }
					</h2>
					<ul className="psst-admin__aside-list">
						{ HOW_IT_WORKS.map( ( step, index ) => (
							<li key={ index }>
								<span
									className="psst-admin__aside-icon"
									aria-hidden="true"
								>
									<Icon icon={ step.icon } size={ 20 } />
								</span>
								<span>{ step.text }</span>
							</li>
						) ) }
					</ul>
				</CardBody>
			</Card>

			<Card className="psst-admin__aside-card" size="small">
				<CardBody>
					<h2 className="psst-admin__aside-title">
						<Icon icon={ lifesaver } size={ 20 } />
						{ __( 'Need a hand?', 'psst' ) }
					</h2>
					<p>
						{ __(
							'Questions, ideas or a bug report? The Linchpin team reads every message.',
							'psst'
						) }
					</p>
					<div className="psst-admin__aside-actions">
						<Button
							__next40pxDefaultSize
							variant="secondary"
							href={ LINKS.support }
						>
							{ LINKS.supportEmail }
						</Button>
						<Button
							__next40pxDefaultSize
							variant="link"
							href={ LINKS.issues }
							target="_blank"
							rel="noreferrer"
						>
							{ __( 'Open an issue on GitHub', 'psst' ) }
						</Button>
					</div>
				</CardBody>
			</Card>

			<Card className="psst-admin__aside-card" size="small">
				<CardBody>
					<a
						className="psst-admin__aside-logo"
						href={ LINKS.linchpin }
						target="_blank"
						rel="noreferrer"
						aria-label={ __(
							'Linchpin (opens in a new tab)',
							'psst'
						) }
					>
						<LinchpinLogo aria-hidden="true" focusable="false" />
					</a>
					<p>
						{ __(
							'Psst is designed, built and maintained by Linchpin, a digital agency that plans, builds and looks after WordPress platforms for organizations that need them to just work.',
							'psst'
						) }
					</p>
					<Button
						__next40pxDefaultSize
						variant="link"
						href={ LINKS.linchpin }
						target="_blank"
						rel="noreferrer"
					>
						{ __( 'Work with Linchpin', 'psst' ) }
					</Button>
				</CardBody>
			</Card>
		</aside>
	);
}
