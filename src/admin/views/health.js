/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { ExternalLink, Notice, Spinner } from '@wordpress/components';

/**
 * External dependencies
 */
import { FactList, SettingsCard } from '@linchpinagency/ui';

/**
 * Internal dependencies
 */
import { useRoute } from '../hooks';

/**
 * The health tab: read-only facts about the install, as linchpin/ui's
 * FactList. A fact that is fine gets a quiet mark, one that wants attention
 * is toned as a warning, and an informational one is left plain.
 *
 * @return {Element} View.
 */
export default function HealthView() {
	const { data, error, isLoading } = useRoute( '/psst/v1/admin/health' );

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error.message || __( 'Health could not be loaded.', 'psst' ) }
			</Notice>
		);
	}

	if ( isLoading || ! data ) {
		return (
			<div className="psst-admin__loading">
				<Spinner />
			</div>
		);
	}

	const format = `${ getDateSettings().formats.date } ${
		getDateSettings().formats.time
	}`;

	return (
		<SettingsCard>
			<FactList
				items={ [
					{
						id: 'action-scheduler',
						label: __( 'Action Scheduler', 'psst' ),
						tone: data.actionScheduler ? 'ok' : 'warning',
						value: data.actionScheduler
							? __(
									'Available. Secrets expire on schedule.',
									'psst'
								)
							: __(
									'Not loaded. Expiry falls back to WP-Cron and read-time checks.',
									'psst'
								),
					},
					{
						id: 'next-sweep',
						label: __( 'Next sweep', 'psst' ),
						tone: data.nextSweep ? 'ok' : 'warning',
						value: data.nextSweep
							? dateI18n( format, data.nextSweep )
							: __( 'Not scheduled yet.', 'psst' ),
					},
					{
						id: 'active-secrets',
						label: __( 'Active secrets', 'psst' ),
						value: String( data.activeSecrets ),
					},
					{
						id: 'wp-cron',
						label: __( 'WP-Cron', 'psst' ),
						tone: data.cronDisabled ? 'warning' : 'ok',
						value: data.cronDisabled
							? __(
									'DISABLE_WP_CRON is set. Make sure a system cron runs wp-cron.php or wp action-scheduler run.',
									'psst'
								)
							: __( 'Enabled.', 'psst' ),
					},
					{
						id: 'turnstile',
						label: __( 'Turnstile', 'psst' ),
						value: data.turnstileEnabled
							? __( 'Enabled on the create form.', 'psst' )
							: __( 'Off.', 'psst' ),
					},
					{
						id: 'legacy',
						label: __( 'Legacy 1.x data', 'psst' ),
						tone: data.legacyRemaining ? 'warning' : 'ok',
						value: data.legacyRemaining
							? __(
									'Still being purged in the background.',
									'psst'
								)
							: __( 'None.', 'psst' ),
					},
					{
						id: 'create-page',
						label: __( 'Create page', 'psst' ),
						value: data.createUrl ? (
							<ExternalLink href={ data.createUrl }>
								{ data.createUrl }
							</ExternalLink>
						) : null,
					},
					{
						id: 'viewer-page',
						label: __( 'Viewer page', 'psst' ),
						tone: data.revealUrl ? 'ok' : 'warning',
						value: data.revealUrl ? (
							<ExternalLink href={ data.revealUrl }>
								{ data.revealUrl }
							</ExternalLink>
						) : (
							__(
								'Not set. Secret links will not resolve.',
								'psst'
							)
						),
					},
					{
						id: 'version',
						label: __( 'Version', 'psst' ),
						value: data.version,
					},
				] }
			/>
		</SettingsCard>
	);
}
