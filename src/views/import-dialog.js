/**
 * CSV import wizard: upload → map columns → choose duplicate handling → run.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../lib/api';
import useDialog from '../lib/use-dialog';

const TARGETS = [
	{ key: 'email', label: __( 'Email (required)', 'rayetun-crm' ) },
	{ key: 'first_name', label: __( 'First name', 'rayetun-crm' ) },
	{ key: 'last_name', label: __( 'Last name', 'rayetun-crm' ) },
	{ key: 'phone', label: __( 'Phone', 'rayetun-crm' ) },
	{ key: 'company', label: __( 'Company', 'rayetun-crm' ) },
	{ key: 'website', label: __( 'Website', 'rayetun-crm' ) },
	{ key: 'status', label: __( 'Status', 'rayetun-crm' ) },
	{ key: 'source', label: __( 'Source', 'rayetun-crm' ) },
	{ key: 'tags', label: __( 'Tags', 'rayetun-crm' ) },
];

/**
 * Parses CSV text into an array of rows (arrays of cell strings).
 *
 * @param {string} text Raw CSV.
 * @return {Array<Array<string>>} Parsed rows.
 */
function parseCSV( text ) {
	const rows = [];
	let row = [];
	let field = '';
	let inQuotes = false;
	const src = text.replace( /\r\n/g, '\n' ).replace( /\r/g, '\n' );

	for ( let i = 0; i < src.length; i++ ) {
		const ch = src[ i ];
		if ( inQuotes ) {
			if ( ch === '"' ) {
				if ( src[ i + 1 ] === '"' ) {
					field += '"';
					i++;
				} else {
					inQuotes = false;
				}
			} else {
				field += ch;
			}
		} else if ( ch === '"' ) {
			inQuotes = true;
		} else if ( ch === ',' ) {
			row.push( field );
			field = '';
		} else if ( ch === '\n' ) {
			row.push( field );
			rows.push( row );
			row = [];
			field = '';
		} else {
			field += ch;
		}
	}
	if ( field !== '' || row.length ) {
		row.push( field );
		rows.push( row );
	}

	return rows.filter( ( r ) => r.some( ( c ) => c.trim() !== '' ) );
}

function guess( target, headers ) {
	const norm = ( s ) => s.toLowerCase().replace( /[^a-z]/g, '' );
	const t = norm( target );
	const idx = headers.findIndex( ( h ) => norm( h ) === t );
	if ( idx !== -1 ) {
		return String( idx );
	}
	const partial = headers.findIndex( ( h ) => norm( h ).includes( t ) );
	return partial !== -1 ? String( partial ) : '';
}

export default function ImportDialog( { onClose, onDone } ) {
	const [ step, setStep ] = useState( 'upload' );
	const [ headers, setHeaders ] = useState( [] );
	const [ dataRows, setDataRows ] = useState( [] );
	const [ mapping, setMapping ] = useState( {} );
	const [ duplicate, setDuplicate ] = useState( 'skip' );
	const [ running, setRunning ] = useState( false );
	const [ result, setResult ] = useState( null );
	const [ error, setError ] = useState( null );
	const dialogRef = useDialog( onClose );

	const onFile = async ( e ) => {
		const file = e.target.files && e.target.files[ 0 ];
		if ( ! file ) {
			return;
		}
		setError( null );
		try {
			const text = await file.text();
			const rows = parseCSV( text );
			if ( rows.length < 2 ) {
				setError( __( 'The file has no data rows.', 'rayetun-crm' ) );
				return;
			}
			const hdrs = rows[ 0 ];
			setHeaders( hdrs );
			setDataRows( rows.slice( 1 ) );
			const initial = {};
			TARGETS.forEach( ( t ) => {
				initial[ t.key ] = guess( t.key, hdrs );
			} );
			setMapping( initial );
			setStep( 'map' );
		} catch ( err ) {
			setError( err.message || __( 'Could not read the file.', 'rayetun-crm' ) );
		}
	};

	const run = async () => {
		if ( mapping.email === '' || mapping.email === undefined ) {
			setError( __( 'Map the Email column before importing.', 'rayetun-crm' ) );
			return;
		}
		setRunning( true );
		setError( null );

		const rows = dataRows.map( ( r ) => {
			const obj = {};
			TARGETS.forEach( ( t ) => {
				const col = mapping[ t.key ];
				if ( col !== '' && col !== undefined ) {
					obj[ t.key ] = r[ parseInt( col, 10 ) ] || '';
				}
			} );
			return obj;
		} );

		try {
			const res = await request( 'contacts/import', 'POST', { rows, duplicate } );
			setResult( res );
			setStep( 'result' );
		} catch ( err ) {
			setError( err.message || __( 'Import failed.', 'rayetun-crm' ) );
		} finally {
			setRunning( false );
		}
	};

	return (
		<div className="rtcrm-slideover" role="dialog" aria-modal="true" aria-labelledby="rtcrm-import-title">
			<div className="rtcrm-slideover__backdrop" onClick={ onClose } />
			<div className="rtcrm-modal" ref={ dialogRef }>
				<header className="rtcrm-slideover__head">
					<h2 id="rtcrm-import-title">{ __( 'Import contacts from CSV', 'rayetun-crm' ) }</h2>
					<button className="rtcrm-iconbtn" onClick={ onClose } aria-label={ __( 'Close', 'rayetun-crm' ) }>×</button>
				</header>

				<div className="rtcrm-slideover__body">
					{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

					{ step === 'upload' && (
						<div className="rtcrm-upload">
							<p className="rtcrm-sub">{ __( 'Choose a .csv file. The first row should be column headers.', 'rayetun-crm' ) }</p>
							<input type="file" accept=".csv,text/csv" onChange={ onFile } />
						</div>
					) }

					{ step === 'map' && (
						<div className="rtcrm-map">
							<p className="rtcrm-sub">
								{ sprintf(
									/* translators: %d: number of rows. */
									__( '%d rows detected. Match your columns to contact fields.', 'rayetun-crm' ),
									dataRows.length
								) }
							</p>
							{ TARGETS.map( ( t ) => (
								<label key={ t.key } className="rtcrm-map__row">
									<span>{ t.label }</span>
									<select
										value={ mapping[ t.key ] ?? '' }
										onChange={ ( e ) => setMapping( ( m ) => ( { ...m, [ t.key ]: e.target.value } ) ) }
									>
										<option value="">{ __( '— Not mapped —', 'rayetun-crm' ) }</option>
										{ headers.map( ( h, i ) => (
											<option key={ i } value={ String( i ) }>{ h }</option>
										) ) }
									</select>
								</label>
							) ) }

							<div className="rtcrm-fieldgroup-label">{ __( 'Duplicate emails', 'rayetun-crm' ) }</div>
							<label className="rtcrm-check">
								<input type="radio" name="dup" checked={ duplicate === 'skip' } onChange={ () => setDuplicate( 'skip' ) } />
								<span>{ __( 'Skip existing contacts', 'rayetun-crm' ) }</span>
							</label>
							<label className="rtcrm-check">
								<input type="radio" name="dup" checked={ duplicate === 'update' } onChange={ () => setDuplicate( 'update' ) } />
								<span>{ __( 'Update existing contacts', 'rayetun-crm' ) }</span>
							</label>
						</div>
					) }

					{ step === 'result' && result && (
						<div className="rtcrm-result">
							<ul className="rtcrm-facts">
								<li><span>{ __( 'Created', 'rayetun-crm' ) }</span><strong>{ result.created }</strong></li>
								<li><span>{ __( 'Updated', 'rayetun-crm' ) }</span><strong>{ result.updated }</strong></li>
								<li><span>{ __( 'Skipped', 'rayetun-crm' ) }</span><strong>{ result.skipped }</strong></li>
								<li><span>{ __( 'Errors', 'rayetun-crm' ) }</span><strong>{ result.errors }</strong></li>
							</ul>
						</div>
					) }
				</div>

				<footer className="rtcrm-slideover__foot">
					<div className="rtcrm-spacer" />
					{ step === 'map' && (
						<>
							<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ () => setStep( 'upload' ) } disabled={ running }>
								{ __( 'Back', 'rayetun-crm' ) }
							</button>
							<button className="rtcrm-btn rtcrm-btn--primary" onClick={ run } disabled={ running }>
								{ running ? __( 'Importing…', 'rayetun-crm' ) : __( 'Import', 'rayetun-crm' ) }
							</button>
						</>
					) }
					{ step === 'result' && (
						<button className="rtcrm-btn rtcrm-btn--primary" onClick={ onDone }>
							{ __( 'Done', 'rayetun-crm' ) }
						</button>
					) }
					{ step === 'upload' && (
						<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ onClose }>
							{ __( 'Cancel', 'rayetun-crm' ) }
						</button>
					) }
				</footer>
			</div>
		</div>
	);
}
