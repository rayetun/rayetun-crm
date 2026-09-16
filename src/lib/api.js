/**
 * Thin helpers over @wordpress/api-fetch for the CRM REST API.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const BASE = 'rayetun-crm/v1';

/**
 * Fetches a collection and its total count from an X-WP-Total header.
 *
 * @param {string} path  REST path relative to the plugin namespace.
 * @param {Object} query Query args.
 * @return {Promise<{items: Array, total: number}>} Items and total.
 */
export async function fetchCollection( path, query = {} ) {
	const response = await apiFetch( {
		path: addQueryArgs( `${ BASE }/${ path }`, query ),
		parse: false,
	} );

	const total = parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 );
	const items = await response.json();

	return { items, total };
}

/**
 * Performs a JSON request against the plugin namespace.
 *
 * @param {string} path   REST path relative to the plugin namespace.
 * @param {string} method HTTP method.
 * @param {Object} [data] JSON body.
 * @return {Promise<Object>} Parsed response.
 */
export function request( path, method, data ) {
	return apiFetch( {
		path: `${ BASE }/${ path }`,
		method,
		data,
	} );
}
