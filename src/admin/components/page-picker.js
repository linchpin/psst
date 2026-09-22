/**
 * Choose, or create, one of the pages Psst needs.
 *
 * Used by the Pages tab for all five page slots. Kept out of the views so the
 * "search for a page, or make one that already has the right blocks in it"
 * behaviour is defined once.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import { Button, ComboboxControl } from '@wordpress/components';
import { plus } from '@wordpress/icons';

/**
 * A page's title, or a placeholder when it has none.
 *
 * @param {Object} page A REST page record.
 * @return {string} Label.
 */
export function pageLabel( page ) {
	const title = page?.title?.rendered || `#${ page?.id }`;

	// Two pages can share a title (a second "Secret" page, say); the slug is
	// what tells them apart, and it is also what the share links will show.
	return page?.slug ? `${ title } (/${ page.slug }/)` : title;
}

/**
 * Choose a page by searching for it, or make a new one that already holds
 * the blocks this kind of page needs.
 *
 * The search runs on the server: a site with hundreds of pages must not be
 * handed a truncated list to scroll through. The selected page is fetched by
 * id so its title shows whether or not it is in the current results.
 *
 * @param {Object}   props          Props.
 * @param {string}   props.label    Label.
 * @param {string}   props.help     Help text.
 * @param {string}   props.kind     Page kind for the new-page route: 'create',
 *                                  'reveal', 'login', 'register' or 'account'.
 * @param {number}   props.value    Page id.
 * @param {Function} props.onChange Setter.
 * @return {Element} Control.
 */
export default function PagePicker( { label, help, kind, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );
	const [ results, setResults ] = useState( [] );
	const [ isSearching, setSearching ] = useState( false );
	const [ isCreating, setCreating ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const selected = useSelect(
		( select ) =>
			value
				? select( coreStore ).getEntityRecord(
						'postType',
						'page',
						value,
						{ _fields: 'id,title,slug' }
					)
				: null,
		[ value ]
	);

	useEffect( () => {
		let cancelled = false;
		setSearching( true );

		const query = new URLSearchParams( {
			per_page: '20',
			status: 'publish',
			orderby: search ? 'relevance' : 'title',
			order: 'asc',
			_fields: 'id,title,slug',
		} );

		if ( search ) {
			query.set( 'search', search );
		}

		const timer = setTimeout( () => {
			apiFetch( { path: `/wp/v2/pages?${ query.toString() }` } )
				.then( ( pages ) => {
					if ( ! cancelled ) {
						setResults( Array.isArray( pages ) ? pages : [] );
					}
				} )
				.catch( () => {
					if ( ! cancelled ) {
						setResults( [] );
					}
				} )
				.finally( () => {
					if ( ! cancelled ) {
						setSearching( false );
					}
				} );
		}, 250 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ search ] );

	const options = useMemo( () => {
		const list = results.map( ( page ) => ( {
			value: String( page.id ),
			label: pageLabel( page ),
		} ) );

		if (
			selected &&
			! list.some( ( o ) => o.value === String( selected.id ) )
		) {
			list.unshift( {
				value: String( selected.id ),
				label: pageLabel( selected ),
			} );
		}

		return list;
	}, [ results, selected ] );

	const createPage = async () => {
		setCreating( true );

		try {
			const page = await apiFetch( {
				path: '/psst/v1/settings/pages',
				method: 'POST',
				data: { kind },
			} );

			onChange( Number( page.id ) );
			createSuccessNotice(
				__(
					'Page created and selected. Save settings to start using it.',
					'psst'
				),
				{
					type: 'snackbar',
					actions: page.link
						? [
								{
									label: __( 'View page', 'psst' ),
									url: page.link,
								},
							]
						: [],
				}
			);
		} catch ( err ) {
			createErrorNotice(
				err?.message || __( 'The page could not be created.', 'psst' ),
				{ type: 'snackbar' }
			);
		} finally {
			setCreating( false );
		}
	};

	/*
	 * Both ways of filling the slot sit on one line, because they are one
	 * choice. The "or" is decorative: the help text below says it in a
	 * sentence.
	 */
	return (
		<div className="psst-admin__page-picker">
			<div className="psst-admin__page-picker-search">
				<ComboboxControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					value={ value ? String( value ) : '' }
					options={ options }
					isLoading={ isSearching }
					onFilterValueChange={ setSearch }
					onChange={ ( next ) =>
						onChange( next ? Number( next ) : 0 )
					}
					placeholder={ __( 'Search pages…', 'psst' ) }
					allowReset
				/>
			</div>
			<span className="psst-admin__page-picker-or" aria-hidden="true">
				{ __( 'or', 'psst' ) }
			</span>
			<Button
				__next40pxDefaultSize
				variant="secondary"
				icon={ plus }
				isBusy={ isCreating }
				disabled={ isCreating }
				onClick={ createPage }
			>
				{ __( 'Create a new page', 'psst' ) }
			</Button>
			{ help && <p className="psst-admin__page-picker-help">{ help }</p> }
		</div>
	);
}
