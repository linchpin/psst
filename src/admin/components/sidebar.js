/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { link, lock, trash } from '@wordpress/icons';

/**
 * External dependencies
 */
import {
	AboutLinchpinCard,
	FeatureListCard,
	HelpCard,
} from '@linchpinagency/ui';

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
 * The help column beside the work.
 *
 * Three cards, all of them the library's: what Psst does, where to get help,
 * and who makes it. Only the first carries copy of Psst's own — the other two
 * are the agency's, and they are components rather than markup here precisely
 * so a revision to either reaches this plugin through a version bump. The
 * `<aside>` around them belongs to `<LinchpinAdminLayout>`.
 *
 * @return {Element} The cards.
 */
export default function Sidebar() {
	return (
		<>
			<FeatureListCard
				title={ __( 'How a secret travels', 'psst' ) }
				items={ HOW_IT_WORKS }
			/>
			<HelpCard />
			<AboutLinchpinCard />
		</>
	);
}
