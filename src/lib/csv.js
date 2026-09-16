/**
 * Client-side CSV download helper.
 */

function escapeCell( value ) {
	const s = String( value == null ? '' : value );
	return /[",\r\n]/.test( s ) ? `"${ s.replace( /"/g, '""' ) }"` : s;
}

/**
 * Triggers a CSV download from headers + rows.
 *
 * @param {string}   filename Download filename.
 * @param {string[]} headers  Column headers.
 * @param {Array}    rows     Array of row arrays.
 */
export function downloadCSV( filename, headers, rows ) {
	const lines = [ headers, ...rows ].map( ( r ) => r.map( escapeCell ).join( ',' ) );
	const blob = new Blob( [ lines.join( '\r\n' ) ], { type: 'text/csv;charset=utf-8' } );
	const url = URL.createObjectURL( blob );
	const a = document.createElement( 'a' );
	a.href = url;
	a.download = filename;
	document.body.appendChild( a );
	a.click();
	a.remove();
	URL.revokeObjectURL( url );
}
