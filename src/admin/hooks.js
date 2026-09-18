/**
 * Data hooks for the admin app.
 */

/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Fetch a psst/v1 route once, with a refetch handle.
 *
 * @param {string} path Route, relative to the namespace.
 * @return {{data: any, error: Error|null, isLoading: boolean, refetch: Function}} State.
 */
export function useRoute( path ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isLoading, setLoading ] = useState( true );
	const [ tick, setTick ] = useState( 0 );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );

		apiFetch( { path } )
			.then( ( result ) => {
				if ( ! cancelled ) {
					setData( result );
					setError( null );
				}
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
	}, [ path, tick ] );

	const refetch = useCallback( () => setTick( ( t ) => t + 1 ), [] );

	return { data, error, isLoading, refetch };
}
