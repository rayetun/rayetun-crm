/**
 * Contacts module — list with search, filter, sort, pagination, bulk actions
 * and a slide-over for viewing/editing a record.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { fetchCollection, request } from '../lib/api';
import ContactPanel from './contact-panel';
import ImportDialog from './import-dialog';

const PER_PAGE = 25;

const COLUMNS = [
	{ key: 'first_name', label: __( 'Name', 'rayetun-crm' ), sortable: true },
	{ key: 'status', label: __( 'Status', 'rayetun-crm' ), sortable: true },
	{ key: 'source', label: __( 'Source', 'rayetun-crm' ), sortable: true },
	{ key: 'lead_score', label: __( 'Score', 'rayetun-crm' ), sortable: true },
	{ key: 'tags', label: __( 'Tags', 'rayetun-crm' ), sortable: false },
	{ key: 'created', label: __( 'Added', 'rayetun-crm' ), sortable: true },
];

const STATUSES = [ 'lead', 'prospect', 'client', 'inactive', 'archived' ];
const STATUS_FILTERS = [ '', ...STATUSES ];

const HEAT_LABELS = {
	cold: __( 'Cold', 'rayetun-crm' ),
	warm: __( 'Warm', 'rayetun-crm' ),
	hot: __( 'Hot', 'rayetun-crm' ),
	very_hot: __( 'Very hot', 'rayetun-crm' ),
};

function cap( s ) {
	return s ? s.charAt( 0 ).toUpperCase() + s.slice( 1 ) : s;
}

function StatusBadge( { status } ) {
	return <span className={ `rtcrm-badge rtcrm-badge--${ status }` }>{ status }</span>;
}

export default function Contacts( { canEdit, canManage = false, canEmail = true, intent, onIntentDone } ) {
	const [ items, setItems ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const [ search, setSearch ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ orderby, setOrderby ] = useState( 'created' );
	const [ order, setOrder ] = useState( 'desc' );
	const [ page, setPage ] = useState( 1 );

	const [ selected, setSelected ] = useState( [] );
	const [ bulkStatus, setBulkStatus ] = useState( 'lead' );
	const [ bulkTag, setBulkTag ] = useState( '' );
	const [ working, setWorking ] = useState( false );

	const [ panel, setPanel ] = useState( null );
	const [ fields, setFields ] = useState( [] );
	const [ importOpen, setImportOpen ] = useState( false );
	const [ exporting, setExporting ] = useState( false );
	const [ views, setViews ] = useState( [] );
	const [ activeView, setActiveView ] = useState( '' );

	const loadViews = () => request( 'views', 'GET' ).then( setViews ).catch( () => setViews( [] ) );

	useEffect( () => {
		request( 'fields', 'GET' ).then( setFields ).catch( () => setFields( [] ) );
		loadViews();
	}, [] );

	// Open the new-contact panel when arrived here via a dashboard quick link.
	useEffect( () => {
		if ( intent === 'new' && canEdit ) {
			setPanel( { contact: null } );
		}
		if ( intent ) {
			onIntentDone && onIntentDone();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ intent ] );

	const applyView = ( v ) => {
		setActiveView( v.id );
		setSearch( v.filters.search || '' );
		setStatus( v.filters.status || '' );
		setOrderby( v.filters.orderby || 'created' );
		setOrder( v.filters.order || 'desc' );
		setPage( 1 );
	};

	const showAll = () => {
		setActiveView( '' );
		setSearch( '' );
		setStatus( '' );
		setOrderby( 'created' );
		setOrder( 'desc' );
		setPage( 1 );
	};

	const saveView = async () => {
		// eslint-disable-next-line no-alert
		const name = window.prompt( __( 'Name this view', 'rayetun-crm' ) );
		if ( ! name ) {
			return;
		}
		try {
			await request( 'views', 'POST', { name, filters: { search, status, orderby, order } } );
			loadViews();
		} catch ( err ) {
			setError( err.message || __( 'Could not save the view.', 'rayetun-crm' ) );
		}
	};

	const deleteView = async ( id, e ) => {
		e.stopPropagation();
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this saved view?', 'rayetun-crm' ) ) ) {
			return;
		}
		try {
			await request( `views/${ id }`, 'DELETE' );
			if ( activeView === id ) {
				showAll();
			}
			loadViews();
		} catch ( err ) {
			setError( err.message || __( 'Could not delete the view.', 'rayetun-crm' ) );
		}
	};

	const load = useCallback( () => {
		setLoading( true );
		fetchCollection( 'contacts', { search, status, orderby, order, page, per_page: PER_PAGE } )
			.then( ( res ) => {
				setItems( res.items );
				setTotal( res.total );
				setError( null );
			} )
			.catch( ( err ) => setError( err.message || __( 'Failed to load contacts.', 'rayetun-crm' ) ) )
			.finally( () => setLoading( false ) );
	}, [ search, status, orderby, order, page ] );

	useEffect( () => {
		const t = setTimeout( load, search ? 300 : 0 );
		return () => clearTimeout( t );
	}, [ load, search ] );

	const toggleSort = ( key ) => {
		if ( orderby === key ) {
			setOrder( order === 'asc' ? 'desc' : 'asc' );
		} else {
			setOrderby( key );
			setOrder( 'asc' );
		}
		setActiveView( '' );
		setPage( 1 );
	};

	const clearSelection = () => setSelected( [] );

	const toggleSelect = ( id ) =>
		setSelected( ( prev ) => ( prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ] ) );

	const allOnPageSelected = items.length > 0 && items.every( ( c ) => selected.includes( c.id ) );

	const toggleSelectAll = () => {
		if ( allOnPageSelected ) {
			setSelected( ( prev ) => prev.filter( ( id ) => ! items.some( ( c ) => c.id === id ) ) );
		} else {
			setSelected( ( prev ) => [ ...new Set( [ ...prev, ...items.map( ( c ) => c.id ) ] ) ] );
		}
	};

	const runBulk = async ( action, value ) => {
		if ( action === 'delete' ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( sprintf(
				/* translators: %d: number of contacts. */
				__( 'Delete %d selected contact(s)? This cannot be undone.', 'rayetun-crm' ),
				selected.length
			) ) ) {
				return;
			}
		}

		setWorking( true );
		setError( null );
		try {
			await request( 'contacts/bulk', 'POST', { action, ids: selected, value } );
			clearSelection();
			setBulkTag( '' );
			load();
		} catch ( err ) {
			setError( err.message || __( 'Bulk action failed.', 'rayetun-crm' ) );
		} finally {
			setWorking( false );
		}
	};

	const doExport = async () => {
		setExporting( true );
		setError( null );
		try {
			const path = addQueryArgs( 'contacts/export', { search, status } );
			const { filename, csv } = await request( path, 'GET' );
			const blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } );
			const url = URL.createObjectURL( blob );
			const a = document.createElement( 'a' );
			a.href = url;
			a.download = filename || 'contacts.csv';
			document.body.appendChild( a );
			a.click();
			a.remove();
			URL.revokeObjectURL( url );
		} catch ( err ) {
			setError( err.message || __( 'Export failed.', 'rayetun-crm' ) );
		} finally {
			setExporting( false );
		}
	};

	const onSaved = () => {
		setPanel( null );
		load();
	};
	const onDeleted = () => {
		setPanel( null );
		load();
	};

	const pages = Math.max( 1, Math.ceil( total / PER_PAGE ) );

	return (
		<div className="rtcrm-contacts">
			<div className="rtcrm-viewtabs">
				<button className={ 'rtcrm-viewtab' + ( activeView === '' ? ' is-active' : '' ) } onClick={ showAll }>
					{ __( 'All contacts', 'rayetun-crm' ) }
				</button>
				{ views.map( ( v ) => (
					<button
						key={ v.id }
						className={ 'rtcrm-viewtab' + ( activeView === v.id ? ' is-active' : '' ) }
						onClick={ () => applyView( v ) }
					>
						{ v.name }
						<span className="rtcrm-viewtab__x" onClick={ ( e ) => deleteView( v.id, e ) } aria-label={ __( 'Delete view', 'rayetun-crm' ) }>×</span>
					</button>
				) ) }
				<button className="rtcrm-viewtab rtcrm-viewtab--save" onClick={ saveView }>
					{ __( '+ Save view', 'rayetun-crm' ) }
				</button>
			</div>

			<div className="rtcrm-toolbar">
				<input
					className="rtcrm-search"
					type="search"
					value={ search }
					onChange={ ( e ) => { setSearch( e.target.value ); setActiveView( '' ); setPage( 1 ); } }
					placeholder={ __( 'Search name or email…', 'rayetun-crm' ) }
				/>
				<select
					className="rtcrm-select"
					value={ status }
					onChange={ ( e ) => { setStatus( e.target.value ); setActiveView( '' ); setPage( 1 ); } }
				>
					{ STATUS_FILTERS.map( ( s ) => (
						<option key={ s || 'all' } value={ s }>
							{ s ? cap( s ) : __( 'All statuses', 'rayetun-crm' ) }
						</option>
					) ) }
				</select>
				<div className="rtcrm-spacer" />
				<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ doExport } disabled={ exporting }>
					{ exporting ? __( 'Exporting…', 'rayetun-crm' ) : __( 'Export', 'rayetun-crm' ) }
				</button>
				{ canEdit && (
					<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ () => setImportOpen( true ) }>
						{ __( 'Import', 'rayetun-crm' ) }
					</button>
				) }
				{ canEdit && (
					<button className="rtcrm-btn rtcrm-btn--primary" onClick={ () => setPanel( { contact: null } ) }>
						{ __( '+ Add contact', 'rayetun-crm' ) }
					</button>
				) }
			</div>

			{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

			{ canEdit && selected.length > 0 && (
				<div className="rtcrm-bulkbar">
					<span className="rtcrm-bulkbar__count">
						{ sprintf(
							/* translators: %d: number selected. */
							_n( '%d selected', '%d selected', selected.length, 'rayetun-crm' ),
							selected.length
						) }
					</span>
					<div className="rtcrm-bulkbar__group">
						<select value={ bulkStatus } onChange={ ( e ) => setBulkStatus( e.target.value ) }>
							{ STATUSES.map( ( s ) => <option key={ s } value={ s }>{ cap( s ) }</option> ) }
						</select>
						<button className="rtcrm-btn rtcrm-btn--ghost" disabled={ working } onClick={ () => runBulk( 'status', bulkStatus ) }>
							{ __( 'Set status', 'rayetun-crm' ) }
						</button>
					</div>
					<div className="rtcrm-bulkbar__group">
						<input
							type="text"
							placeholder={ __( 'Tag(s), comma separated', 'rayetun-crm' ) }
							value={ bulkTag }
							onChange={ ( e ) => setBulkTag( e.target.value ) }
						/>
						<button className="rtcrm-btn rtcrm-btn--ghost" disabled={ working || ! bulkTag.trim() } onClick={ () => runBulk( 'tag', bulkTag ) }>
							{ __( 'Add tag', 'rayetun-crm' ) }
						</button>
					</div>
					<div className="rtcrm-spacer" />
					{ canManage && (
						<button className="rtcrm-btn rtcrm-btn--danger" disabled={ working } onClick={ () => runBulk( 'delete' ) }>
							{ __( 'Delete', 'rayetun-crm' ) }
						</button>
					) }
					<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ clearSelection }>
						{ __( 'Clear', 'rayetun-crm' ) }
					</button>
				</div>
			) }

			<div className="rtcrm-tablewrap">
				<table className="rtcrm-table">
					<thead>
						<tr>
							{ canEdit && (
								<th className="rtcrm-check-col">
									<input type="checkbox" checked={ allOnPageSelected } onChange={ toggleSelectAll } aria-label={ __( 'Select all', 'rayetun-crm' ) } />
								</th>
							) }
							{ COLUMNS.map( ( col ) => {
								const sorted = orderby === col.key;
								if ( ! col.sortable ) {
									return <th key={ col.key }>{ col.label }</th>;
								}
								return (
									<th key={ col.key } aria-sort={ sorted ? ( order === 'asc' ? 'ascending' : 'descending' ) : 'none' }>
										<button type="button" className="rtcrm-th-sort" onClick={ () => toggleSort( col.key ) }>
											{ col.label }
											{ sorted && <span aria-hidden="true">{ order === 'asc' ? ' ▲' : ' ▼' }</span> }
										</button>
									</th>
								);
							} ) }
						</tr>
					</thead>
					<tbody>
						{ loading && (
							<tr><td colSpan={ COLUMNS.length + ( canEdit ? 1 : 0 ) } className="rtcrm-table__empty">{ __( 'Loading…', 'rayetun-crm' ) }</td></tr>
						) }
						{ ! loading && items.length === 0 && (
							<tr><td colSpan={ COLUMNS.length + ( canEdit ? 1 : 0 ) } className="rtcrm-table__empty">{ __( 'No contacts yet.', 'rayetun-crm' ) }</td></tr>
						) }
						{ ! loading && items.map( ( c ) => (
							<tr key={ c.id } className={ 'rtcrm-row' + ( selected.includes( c.id ) ? ' is-selected' : '' ) } onClick={ () => setPanel( { contact: c } ) }>
								{ canEdit && (
									<td className="rtcrm-check-col" onClick={ ( e ) => e.stopPropagation() }>
										<input type="checkbox" checked={ selected.includes( c.id ) } onChange={ () => toggleSelect( c.id ) } aria-label={ __( 'Select contact', 'rayetun-crm' ) } />
									</td>
								) }
								<td>
									<div className="rtcrm-namecell">
										{ c.star && <span className="rtcrm-star" aria-hidden="true">★</span> }
										<div>
											<button
												type="button"
												className="rtcrm-rowbtn rtcrm-name"
												onClick={ ( e ) => { e.stopPropagation(); setPanel( { contact: c } ); } }
											>
												{ c.full_name }
											</button>
											<div className="rtcrm-sub">{ c.email }</div>
										</div>
									</div>
								</td>
								<td><StatusBadge status={ c.status } /></td>
								<td>{ c.source || '—' }</td>
								<td>
									<span className="rtcrm-scorecell">
										<span className={ `rtcrm-heat rtcrm-heat--${ c.heat }` } title={ HEAT_LABELS[ c.heat ] } />
										{ c.lead_score }
									</span>
								</td>
								<td>
									<div className="rtcrm-tags">
										{ c.tags.map( ( t ) => <span key={ t.id } className="rtcrm-tag">{ t.name }</span> ) }
									</div>
								</td>
								<td>{ ( c.created || '' ).slice( 0, 10 ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="rtcrm-pagination">
				<span className="rtcrm-count">
					{ sprintf(
						/* translators: %d: total contacts. */
						_n( '%d contact', '%d contacts', total, 'rayetun-crm' ),
						total
					) }
				</span>
				<div className="rtcrm-spacer" />
				<button className="rtcrm-btn rtcrm-btn--ghost" disabled={ page <= 1 } onClick={ () => setPage( page - 1 ) }>{ __( 'Prev', 'rayetun-crm' ) }</button>
				<span className="rtcrm-pageinfo">{ page } / { pages }</span>
				<button className="rtcrm-btn rtcrm-btn--ghost" disabled={ page >= pages } onClick={ () => setPage( page + 1 ) }>{ __( 'Next', 'rayetun-crm' ) }</button>
			</div>

			{ panel && (
				<ContactPanel
					contact={ panel.contact }
					canEdit={ canEdit }
					canManage={ canManage }
					canEmail={ canEmail }
					fields={ fields }
					onClose={ () => setPanel( null ) }
					onSaved={ onSaved }
					onDeleted={ onDeleted }
				/>
			) }

			{ importOpen && (
				<ImportDialog
					onClose={ () => setImportOpen( false ) }
					onDone={ () => { setImportOpen( false ); load(); } }
				/>
			) }
		</div>
	);
}
