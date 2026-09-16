/**
 * Slide-over panel for creating and editing a deal.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../lib/api';
import useDialog from '../lib/use-dialog';

function toForm( deal, stageId ) {
	if ( ! deal ) {
		return {
			title: '',
			value: '',
			currency: '$',
			close_date: '',
			probability: 0,
			stage_id: stageId || 0,
			description: '',
			contact_id: 0,
		};
	}

	return {
		title: deal.title || '',
		value: deal.value || '',
		currency: deal.currency || '$',
		close_date: deal.close_date || '',
		probability: deal.probability || 0,
		stage_id: deal.stage_id || stageId || 0,
		description: deal.description || '',
		contact_id: deal.contacts && deal.contacts[ 0 ] ? deal.contacts[ 0 ].id : 0,
	};
}

export default function DealPanel( { deal, stageId, pipelineId, stages, contacts, canEdit, canManage = false, onClose, onSaved, onDeleted } ) {
	const isNew = ! deal || ! deal.id;
	const [ form, setForm ] = useState( toForm( deal, stageId ) );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const dialogRef = useDialog( onClose );

	useEffect( () => {
		setForm( toForm( deal, stageId ) );
		setError( null );
	}, [ deal, stageId ] );

	const set = ( key ) => ( e ) => setForm( ( p ) => ( { ...p, [ key ]: e.target.value } ) );

	const save = async () => {
		setSaving( true );
		setError( null );

		const body = {
			title: form.title,
			value: form.value === '' ? 0 : form.value,
			currency: form.currency,
			close_date: form.close_date,
			probability: form.probability,
			stage_id: parseInt( form.stage_id, 10 ),
			pipeline_id: pipelineId,
			description: form.description,
			contact_ids: form.contact_id ? [ parseInt( form.contact_id, 10 ) ] : [],
		};

		try {
			const saved = isNew
				? await request( 'deals', 'POST', body )
				: await request( `deals/${ deal.id }`, 'PUT', body );
			onSaved( saved );
		} catch ( err ) {
			setError( err.message || __( 'Could not save the deal.', 'rayetun-crm' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async () => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this deal? This cannot be undone.', 'rayetun-crm' ) ) ) {
			return;
		}
		setSaving( true );
		try {
			await request( `deals/${ deal.id }`, 'DELETE' );
			onDeleted( deal.id );
		} catch ( err ) {
			setError( err.message || __( 'Could not delete the deal.', 'rayetun-crm' ) );
			setSaving( false );
		}
	};

	return (
		<div className="rtcrm-slideover" role="dialog" aria-modal="true" aria-labelledby="rtcrm-deal-title">
			<div className="rtcrm-slideover__backdrop" onClick={ onClose } />
			<div className="rtcrm-slideover__panel" ref={ dialogRef }>
				<header className="rtcrm-slideover__head">
					<h2 id="rtcrm-deal-title">{ isNew ? __( 'New deal', 'rayetun-crm' ) : ( form.title || __( 'Edit deal', 'rayetun-crm' ) ) }</h2>
					<button className="rtcrm-iconbtn" onClick={ onClose } aria-label={ __( 'Close', 'rayetun-crm' ) }>×</button>
				</header>

				<div className="rtcrm-slideover__body">
					{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

					<label className="rtcrm-field">
						<span>{ __( 'Deal title', 'rayetun-crm' ) } *</span>
						<input type="text" value={ form.title } onChange={ set( 'title' ) } />
					</label>

					<div className="rtcrm-field-row">
						<label className="rtcrm-field">
							<span>{ __( 'Value', 'rayetun-crm' ) }</span>
							<input type="number" step="0.01" value={ form.value } onChange={ set( 'value' ) } />
						</label>
						<label className="rtcrm-field" style={ { flex: '0 0 90px' } }>
							<span>{ __( 'Currency', 'rayetun-crm' ) }</span>
							<input type="text" value={ form.currency } onChange={ set( 'currency' ) } maxLength={ 4 } />
						</label>
					</div>

					<div className="rtcrm-field-row">
						<label className="rtcrm-field">
							<span>{ __( 'Stage', 'rayetun-crm' ) }</span>
							<select value={ form.stage_id } onChange={ set( 'stage_id' ) }>
								{ stages.map( ( s ) => (
									<option key={ s.id } value={ s.id }>{ s.name }</option>
								) ) }
							</select>
						</label>
						<label className="rtcrm-field">
							<span>{ __( 'Probability %', 'rayetun-crm' ) }</span>
							<input type="number" min="0" max="100" value={ form.probability } onChange={ set( 'probability' ) } />
						</label>
					</div>

					<div className="rtcrm-field-row">
						<label className="rtcrm-field">
							<span>{ __( 'Expected close', 'rayetun-crm' ) }</span>
							<input type="date" value={ form.close_date || '' } onChange={ set( 'close_date' ) } />
						</label>
						<label className="rtcrm-field">
							<span>{ __( 'Contact', 'rayetun-crm' ) }</span>
							<select value={ form.contact_id } onChange={ set( 'contact_id' ) }>
								<option value={ 0 }>{ __( '— None —', 'rayetun-crm' ) }</option>
								{ contacts.map( ( c ) => (
									<option key={ c.id } value={ c.id }>{ c.full_name }</option>
								) ) }
							</select>
						</label>
					</div>

					<label className="rtcrm-field">
						<span>{ __( 'Notes', 'rayetun-crm' ) }</span>
						<textarea rows={ 4 } value={ form.description } onChange={ set( 'description' ) } />
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
							{ saving ? __( 'Saving…', 'rayetun-crm' ) : __( 'Save deal', 'rayetun-crm' ) }
						</button>
					) }
				</footer>
			</div>
		</div>
	);
}
