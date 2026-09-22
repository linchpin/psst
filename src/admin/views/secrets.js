/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { dateI18n, getSettings as getDateSettings } from '@wordpress/date';
import apiFetch from '@wordpress/api-fetch';
import { DataViews } from '@wordpress/dataviews';
import { Notice } from '@wordpress/components';
import { trash } from '@wordpress/icons';

const DEFAULT_VIEW = {
	type: 'table',
	page: 1,
	perPage: 20,
	sort: { field: 'created_at', direction: 'desc' },
	fields: [
		'created_at',
		'expires_at',
		'status',
		'has_passphrase',
		'size',
		'ttl_minutes',
	],
	titleField: 'short_id',
	layout: {},
};

/**
 * A human ttl label.
 *
 * @param {number} minutes Minutes.
 * @return {string} Label.
 */
function ttlLabel( minutes ) {
	if ( ! minutes ) {
		return '—';
	}

	if ( minutes % 1440 === 0 ) {
		const days = minutes / 1440;
		return sprintf(
			/* translators: %d: days */
			_n( '%d day', '%d days', days, 'psst' ),
			days
		);
	}

	if ( minutes % 60 === 0 ) {
		const hours = minutes / 60;
		return sprintf(
			/* translators: %d: hours */
			_n( '%d hour', '%d hours', hours, 'psst' ),
			hours
		);
	}

	return sprintf(
		/* translators: %d: minutes */
		_n( '%d minute', '%d minutes', minutes, 'psst' ),
		minutes
	);
}

/**
 * Bytes, humanized.
 *
 * @param {number} bytes Bytes.
 * @return {string} Label.
 */
function sizeLabel( bytes ) {
	if ( ! bytes ) {
		return '—';
	}

	if ( bytes < 1024 ) {
		return `${ bytes } B`;
	}

	return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
}

/**
 * The secrets tab: metadata only, never content.
 *
 * @return {Element} View.
 */
export default function SecretsView() {
	const [ view, setView ] = useState( DEFAULT_VIEW );
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ isLoading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ tick, setTick ] = useState( 0 );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );

		const orderby =
			view.sort?.field === 'expires_at' ? 'expires' : 'created';
		const order = view.sort?.direction === 'asc' ? 'asc' : 'desc';

		apiFetch( {
			path: `/psst/v1/admin/secrets?page=${ view.page }&per_page=${ view.perPage }&orderby=${ orderby }&order=${ order }`,
			parse: false,
		} )
			.then( async ( response ) => {
				const data = await response.json();

				if ( cancelled ) {
					return;
				}

				setItems( Array.isArray( data ) ? data : [] );
				setTotal( Number( response.headers.get( 'X-WP-Total' ) ) || 0 );
				setError( null );
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [
		view.page,
		view.perPage,
		view.sort?.field,
		view.sort?.direction,
		tick,
	] );

	const refresh = useCallback( () => setTick( ( t ) => t + 1 ), [] );

	const dateFormat = `${ getDateSettings().formats.date } ${
		getDateSettings().formats.time
	}`;

	const fields = useMemo(
		() => [
			{
				id: 'short_id',
				label: __( 'ID', 'psst' ),
				enableSorting: false,
				enableHiding: false,
				render: ( { item } ) => (
					<code
						title={ __(
							'The first characters of the link id',
							'psst'
						) }
					>
						{ item.short_id }…
					</code>
				),
			},
			{
				id: 'created_at',
				label: __( 'Created', 'psst' ),
				enableSorting: true,
				render: ( { item } ) =>
					item.created_at
						? dateI18n( dateFormat, item.created_at )
						: '—',
			},
			{
				id: 'expires_at',
				label: __( 'Expires', 'psst' ),
				enableSorting: true,
				render: ( { item } ) => (
					<span
						className={ item.expired ? 'psst-admin__expired' : '' }
					>
						{ item.expires_at
							? dateI18n( dateFormat, item.expires_at )
							: '—' }
					</span>
				),
			},
			{
				id: 'status',
				label: __( 'Status', 'psst' ),
				enableSorting: false,
				elements: [
					{ value: 'active', label: __( 'Active', 'psst' ) },
					{ value: 'expired', label: __( 'Expired', 'psst' ) },
					{
						value: 'pending_delete',
						label: __( 'Pending delete', 'psst' ),
					},
				],
			},
			{
				id: 'has_passphrase',
				label: __( 'Pass phrase', 'psst' ),
				enableSorting: false,
				render: ( { item } ) =>
					item.has_passphrase
						? __( 'Yes', 'psst' )
						: __( 'No', 'psst' ),
			},
			{
				id: 'size',
				label: __( 'Size', 'psst' ),
				enableSorting: false,
				render: ( { item } ) => sizeLabel( item.size ),
			},
			{
				id: 'ttl_minutes',
				label: __( 'Lifetime', 'psst' ),
				enableSorting: false,
				render: ( { item } ) => ttlLabel( item.ttl_minutes ),
			},
		],
		[ dateFormat ]
	);

	const shred = useCallback(
		async ( selected ) => {
			const results = await Promise.allSettled(
				selected.map( ( item ) =>
					apiFetch( {
						path: `/psst/v1/secrets/${ item.public_id }`,
						method: 'DELETE',
					} )
				)
			);

			const failed = results.filter( ( r ) => r.status === 'rejected' );

			if ( failed.length ) {
				createErrorNotice(
					sprintf(
						/* translators: %d: number of secrets. */
						_n(
							'%d secret could not be shredded.',
							'%d secrets could not be shredded.',
							failed.length,
							'psst'
						),
						failed.length
					),
					{ type: 'snackbar' }
				);
			} else {
				createSuccessNotice(
					sprintf(
						/* translators: %d: number of secrets. */
						_n(
							'%d secret shredded.',
							'%d secrets shredded.',
							selected.length,
							'psst'
						),
						selected.length
					),
					{ type: 'snackbar' }
				);
			}

			refresh();
		},
		[ createErrorNotice, createSuccessNotice, refresh ]
	);

	const actions = useMemo(
		() => [
			{
				id: 'shred',
				label: __( 'Shred', 'psst' ),
				icon: trash,
				isPrimary: true,
				isDestructive: true,
				supportsBulk: true,
				callback: shred,
			},
		],
		[ shred ]
	);

	if ( error ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error.message ||
					__( 'The secrets could not be loaded.', 'psst' ) }
			</Notice>
		);
	}

	return (
		<>
			<p className="psst-admin__lead">
				{ __(
					'Every live secret, by its metadata. The contents are ciphertext that only the link can open, so there is nothing here to read.',
					'psst'
				) }
			</p>
			<div className="psst-admin__secrets">
				<DataViews
					data={ items }
					fields={ fields }
					view={ view }
					onChangeView={ setView }
					actions={ actions }
					isLoading={ isLoading }
					paginationInfo={ {
						totalItems: total,
						totalPages: Math.max(
							1,
							Math.ceil( total / view.perPage )
						),
					} }
					defaultLayouts={ { table: {} } }
					getItemId={ ( item ) => String( item.id ) }
					search={ false }
				/>
			</div>
		</>
	);
}
