/**
 * Slide-over panel for creating and editing a task.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../lib/api';
import useDialog from '../lib/use-dialog';

const TYPES = [ 'call', 'email', 'meeting', 'follow_up', 'custom' ];
const PRIORITIES = [ 'high', 'normal', 'low' ];
const STATUSES = [ 'open', 'in_progress', 'complete', 'cancelled' ];

function label( s ) {
	return s ? s.replace( /_/g, ' ' ).replace( /^\w/, ( c ) => c.toUpperCase() ) : s;
}

function toForm( task ) {
	if ( ! task ) {
		return { title: '', type: 'follow_up', priority: 'normal', status: 'open', due: '', contact_id: 0, notes: '' };
	}
	return {
		title: task.title || '',
		type: task.type || 'follow_up',
		priority: task.priority || 'normal',
		status: task.status || 'open',
		due: task.due ? task.due.replace( ' ', 'T' ).slice( 0, 16 ) : '',
		contact_id: task.contact_id || 0,
		notes: task.notes || '',
	};
}

export default function TaskPanel( { task, contacts, canEdit, canManage = false, onClose, onSaved, onDeleted } ) {
	const isNew = ! task || ! task.id;
	const [ form, setForm ] = useState( toForm( task ) );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const dialogRef = useDialog( onClose );

	useEffect( () => {
		setForm( toForm( task ) );
		setError( null );
	}, [ task ] );

	const set = ( key ) => ( e ) => setForm( ( p ) => ( { ...p, [ key ]: e.target.value } ) );

	const save = async () => {
		setSaving( true );
		setError( null );
		const body = { ...form, contact_id: parseInt( form.contact_id, 10 ) || 0 };
		try {
			const saved = isNew
				? await request( 'tasks', 'POST', body )
				: await request( `tasks/${ task.id }`, 'PUT', body );
			onSaved( saved );
		} catch ( err ) {
			setError( err.message || __( 'Could not save the task.', 'rayetun-crm' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this task?', 'rayetun-crm' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			await request( `tasks/${ task.id }`, 'DELETE' );
			onDeleted( task.id );
		} catch ( err ) {
			setError( err.message || __( 'Could not delete the task.', 'rayetun-crm' ) );
			setSaving( false );
		}
	};

	return (
		<div className="rtcrm-slideover" role="dialog" aria-modal="true" aria-labelledby="rtcrm-task-title">
			<div className="rtcrm-slideover__backdrop" onClick={ onClose } />
			<div className="rtcrm-slideover__panel" ref={ dialogRef }>
				<header className="rtcrm-slideover__head">
					<h2 id="rtcrm-task-title">{ isNew ? __( 'New task', 'rayetun-crm' ) : ( form.title || __( 'Edit task', 'rayetun-crm' ) ) }</h2>
					<button className="rtcrm-iconbtn" onClick={ onClose } aria-label={ __( 'Close', 'rayetun-crm' ) }>×</button>
				</header>

				<div className="rtcrm-slideover__body">
					{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

					<label className="rtcrm-field">
						<span>{ __( 'Title', 'rayetun-crm' ) } *</span>
						<input type="text" value={ form.title } onChange={ set( 'title' ) } />
					</label>

					<div className="rtcrm-field-row">
						<label className="rtcrm-field">
							<span>{ __( 'Type', 'rayetun-crm' ) }</span>
							<select value={ form.type } onChange={ set( 'type' ) }>
								{ TYPES.map( ( t ) => <option key={ t } value={ t }>{ label( t ) }</option> ) }
							</select>
						</label>
						<label className="rtcrm-field">
							<span>{ __( 'Priority', 'rayetun-crm' ) }</span>
							<select value={ form.priority } onChange={ set( 'priority' ) }>
								{ PRIORITIES.map( ( p ) => <option key={ p } value={ p }>{ label( p ) }</option> ) }
							</select>
						</label>
					</div>

					<div className="rtcrm-field-row">
						<label className="rtcrm-field">
							<span>{ __( 'Due', 'rayetun-crm' ) }</span>
							<input type="datetime-local" value={ form.due } onChange={ set( 'due' ) } />
						</label>
						<label className="rtcrm-field">
							<span>{ __( 'Status', 'rayetun-crm' ) }</span>
							<select value={ form.status } onChange={ set( 'status' ) }>
								{ STATUSES.map( ( s ) => <option key={ s } value={ s }>{ label( s ) }</option> ) }
							</select>
						</label>
					</div>

					<label className="rtcrm-field">
						<span>{ __( 'Contact', 'rayetun-crm' ) }</span>
						<select value={ form.contact_id } onChange={ set( 'contact_id' ) }>
							<option value={ 0 }>{ __( '— None —', 'rayetun-crm' ) }</option>
							{ contacts.map( ( c ) => <option key={ c.id } value={ c.id }>{ c.full_name }</option> ) }
						</select>
					</label>

					<label className="rtcrm-field">
						<span>{ __( 'Notes', 'rayetun-crm' ) }</span>
						<textarea rows={ 4 } value={ form.notes } onChange={ set( 'notes' ) } />
					</label>
				</div>

				<footer className="rtcrm-slideover__foot">
					{ ! isNew && canManage && (
						<button className="rtcrm-btn rtcrm-btn--danger" onClick={ remove } disabled={ saving }>
							{ __( 'Delete', 'rayetun-crm' ) }
						</button>
					) }
					<div className="rtcrm-spacer" />
					<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ onClose } disabled={ saving }>
						{ __( 'Cancel', 'rayetun-crm' ) }
					</button>
					{ canEdit && (
						<button className="rtcrm-btn rtcrm-btn--primary" onClick={ save } disabled={ saving || ! form.title.trim() }>
							{ saving ? __( 'Saving…', 'rayetun-crm' ) : __( 'Save task', 'rayetun-crm' ) }
						</button>
					) }
				</footer>
			</div>
		</div>
	);
}
