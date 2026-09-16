/**
 * Tasks module — filterable task list with quick due-date views and a
 * slide-over for creating/editing.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { fetchCollection, request } from '../lib/api';
import TaskPanel from './task-panel';

const DUE_VIEWS = [
	{ key: '', label: __( 'All', 'rayetun-crm' ) },
	{ key: 'today', label: __( 'Due today', 'rayetun-crm' ) },
	{ key: 'overdue', label: __( 'Overdue', 'rayetun-crm' ) },
	{ key: 'upcoming', label: __( 'Upcoming', 'rayetun-crm' ) },
];
const STATUSES = [ '', 'open', 'in_progress', 'complete', 'cancelled' ];

function label( s ) {
	return s ? s.replace( /_/g, ' ' ).replace( /^\w/, ( c ) => c.toUpperCase() ) : s;
}

export default function Tasks( { canEdit, canManage = false } ) {
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ due, setDue ] = useState( '' );
	const [ status, setStatus ] = useState( 'open' );
	const [ panel, setPanel ] = useState( null );
	const [ contacts, setContacts ] = useState( [] );

	useEffect( () => {
		fetchCollection( 'contacts', { per_page: 100, orderby: 'first_name', order: 'asc' } )
			.then( ( res ) => setContacts( res.items ) )
			.catch( () => setContacts( [] ) );
	}, [] );

	const load = useCallback( () => {
		setLoading( true );
		fetchCollection( 'tasks', { due, status, per_page: 100 } )
			.then( ( res ) => { setItems( res.items ); setError( null ); } )
			.catch( ( err ) => setError( err.message || __( 'Failed to load tasks.', 'rayetun-crm' ) ) )
			.finally( () => setLoading( false ) );
	}, [ due, status ] );

	useEffect( load, [ load ] );

	const toggleComplete = async ( task, e ) => {
		e.stopPropagation();
		const next = task.status === 'complete' ? 'open' : 'complete';
		try {
			await request( `tasks/${ task.id }`, 'PUT', { status: next } );
			load();
		} catch ( err ) {
			setError( err.message || __( 'Could not update the task.', 'rayetun-crm' ) );
		}
	};

	const onSaved = () => { setPanel( null ); load(); };

	return (
		<div className="rtcrm-tasks">
			<div className="rtcrm-viewtabs">
				{ DUE_VIEWS.map( ( v ) => (
					<button
						key={ v.key || 'all' }
						className={ 'rtcrm-viewtab' + ( due === v.key ? ' is-active' : '' ) }
						onClick={ () => setDue( v.key ) }
					>
						{ v.label }
					</button>
				) ) }
			</div>

			<div className="rtcrm-toolbar">
				<select className="rtcrm-select" value={ status } onChange={ ( e ) => setStatus( e.target.value ) }>
					{ STATUSES.map( ( s ) => (
						<option key={ s || 'all' } value={ s }>{ s ? label( s ) : __( 'All statuses', 'rayetun-crm' ) }</option>
					) ) }
				</select>
				<div className="rtcrm-spacer" />
				{ canEdit && (
					<button className="rtcrm-btn rtcrm-btn--primary" onClick={ () => setPanel( { task: null } ) }>
						{ __( '+ Add task', 'rayetun-crm' ) }
					</button>
				) }
			</div>

			{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

			<div className="rtcrm-tablewrap">
				<table className="rtcrm-table">
					<thead>
						<tr>
							{ canEdit && <th className="rtcrm-check-col" /> }
							<th>{ __( 'Task', 'rayetun-crm' ) }</th>
							<th>{ __( 'Type', 'rayetun-crm' ) }</th>
							<th>{ __( 'Priority', 'rayetun-crm' ) }</th>
							<th>{ __( 'Due', 'rayetun-crm' ) }</th>
							<th>{ __( 'Contact', 'rayetun-crm' ) }</th>
							<th>{ __( 'Status', 'rayetun-crm' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ loading && (
							<tr><td colSpan={ canEdit ? 7 : 6 } className="rtcrm-table__empty">{ __( 'Loading…', 'rayetun-crm' ) }</td></tr>
						) }
						{ ! loading && items.length === 0 && (
							<tr><td colSpan={ canEdit ? 7 : 6 } className="rtcrm-table__empty">{ __( 'No tasks here.', 'rayetun-crm' ) }</td></tr>
						) }
						{ ! loading && items.map( ( t ) => (
							<tr key={ t.id } className={ 'rtcrm-row' + ( t.status === 'complete' ? ' is-done' : '' ) } onClick={ () => setPanel( { task: t } ) }>
								{ canEdit && (
									<td className="rtcrm-check-col" onClick={ ( e ) => e.stopPropagation() }>
										<input type="checkbox" checked={ t.status === 'complete' } onChange={ ( e ) => toggleComplete( t, e ) } aria-label={ __( 'Complete task', 'rayetun-crm' ) } />
									</td>
								) }
								<td>
									<button
										type="button"
										className="rtcrm-rowbtn rtcrm-name"
										onClick={ ( e ) => { e.stopPropagation(); setPanel( { task: t } ); } }
									>
										{ t.title }
									</button>
								</td>
								<td>{ label( t.type ) }</td>
								<td><span className={ `rtcrm-pri rtcrm-pri--${ t.priority }` }>{ label( t.priority ) }</span></td>
								<td className={ t.overdue ? 'rtcrm-due-over' : '' }>{ t.due ? t.due.slice( 0, 16 ).replace( 'T', ' ' ) : '—' }</td>
								<td>{ t.contact_name || '—' }</td>
								<td><span className={ `rtcrm-badge rtcrm-badge--${ t.status }` }>{ label( t.status ) }</span></td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			{ panel && (
				<TaskPanel
					task={ panel.task }
					contacts={ contacts }
					canEdit={ canEdit }
					canManage={ canManage }
					onClose={ () => setPanel( null ) }
					onSaved={ onSaved }
					onDeleted={ onSaved }
				/>
			) }
		</div>
	);
}
