/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import { ExternalLink, Notice, Spinner } from '@wordpress/components';

/**
 * External dependencies
 */
import { SettingsCard } from '@linchpinagency/ui';

/**
 * Internal dependencies
 */
import { useRoute } from '../hooks';

/**
 * One row of the health table.
 *
 * @param {Object}  props        Props.
 * @param {string}  props.label  Label.
 * @param {Element} props.value  Value.
 * @param {string}  props.status ok, warn or info.
 * @return {Element} Row.
 */
function Row( { label, value, status = 'info' } ) {
	return (
		<div className={ `psst-admin__health-row is-${ status }` }>
			<dt>{ label }</dt>
			<dd>{ value }</dd>
		</div>
	);
}

/**
 * The health tab: read-only facts about the install.
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
			<dl className="psst-admin__health">
				<Row
					label={ __( 'Action Scheduler', 'psst' ) }
					status={ data.actionScheduler ? 'ok' : 'warn' }
					value={
						data.actionScheduler
							? __(
									'Available. Secrets expire on schedule.',
									'psst'
								)
							: __(
									'Not loaded. Expiry falls back to WP-Cron and read-time checks.',
									'psst'
								)
					}
				/>
				<Row
					label={ __( 'Next sweep', 'psst' ) }
					status={ data.nextSweep ? 'ok' : 'warn' }
					value={
						data.nextSweep
							? dateI18n( format, data.nextSweep )
							: __( 'Not scheduled yet.', 'psst' )
					}
				/>
				<Row
					label={ __( 'Active secrets', 'psst' ) }
					value={ String( data.activeSecrets ) }
				/>
				<Row
					label={ __( 'WP-Cron', 'psst' ) }
					status={ data.cronDisabled ? 'warn' : 'ok' }
					value={
						data.cronDisabled
							? __(
									'DISABLE_WP_CRON is set. Make sure a system cron runs wp-cron.php or wp action-scheduler run.',
									'psst'
								)
							: __( 'Enabled.', 'psst' )
					}
				/>
				<Row
					label={ __( 'Turnstile', 'psst' ) }
					value={
						data.turnstileEnabled
							? __( 'Enabled on the create form.', 'psst' )
							: __( 'Off.', 'psst' )
					}
				/>
				<Row
					label={ __( 'Legacy 1.x data', 'psst' ) }
					status={ data.legacyRemaining ? 'warn' : 'ok' }
					value={
						data.legacyRemaining
							? __(
									'Still being purged in the background.',
									'psst'
								)
							: __( 'None.', 'psst' )
					}
				/>
				<Row
					label={ __( 'Create page', 'psst' ) }
					value={
						<ExternalLink href={ data.createUrl }>
							{ data.createUrl }
						</ExternalLink>
					}
				/>
				<Row
					label={ __( 'Viewer page', 'psst' ) }
					status={ data.revealUrl ? 'ok' : 'warn' }
					value={
						data.revealUrl ? (
							<ExternalLink href={ data.revealUrl }>
								{ data.revealUrl }
							</ExternalLink>
						) : (
							__(
								'Not set. Secret links will not resolve.',
								'psst'
							)
						)
					}
				/>
				<Row label={ __( 'Version', 'psst' ) } value={ data.version } />
			</dl>
		</SettingsCard>
	);
}
