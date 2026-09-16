/**
 * Settings — reusable email templates (free tier capped at 3).
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../lib/api';

export default function EmailTemplates( { canEdit } ) {
	const [ templates, setTemplates ] = useState( [] );
	const [ error, setError ] = useState( null );
	const [ name, setName ] = useState( '' );
	const [ subject, setSubject ] = useState( '' );
	const [ body, setBody ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const load = () => {
		request( 'email-templates', 'GET' )
			.then( ( t ) => setTemplates( Array.isArray( t ) ? t : [] ) )
			.catch( ( err ) => setError( err.message || __( 'Failed to load templates.', 'rayetun-crm' ) ) );
	};

	useEffect( load, [] );

	const add = async () => {
		if ( ! name.trim() ) {
			return;
		}
		setSaving( true );
		setError( null );
		try {
			await request( 'email-templates', 'POST', { name, subject, body } );
			setName( '' );
			setSubject( '' );
			setBody( '' );
			load();
		} catch ( err ) {
			setError( err.message || __( 'Could not save the template.', 'rayetun-crm' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this template?', 'rayetun-crm' ) ) ) {
			return;
		}
		try {
			await request( `email-templates/${ id }`, 'DELETE' );
			load();
		} catch ( err ) {
			setError( err.message || __( 'Could not delete the template.', 'rayetun-crm' ) );
		}
	};

	return (
		<div className="rtcrm-card">
			<h2>{ __( 'Email templates', 'rayetun-crm' ) }</h2>
			<p className="rtcrm-sub">
				{ __( 'Reusable messages for 1-to-1 email. Variables: {{first_name}}, {{last_name}}, {{full_name}}, {{company}}, {{email}}.', 'rayetun-crm' ) }
			</p>

			{ error && <div className="rtcrm-inline-error">{ error }</div> }

			<table className="rtcrm-table rtcrm-table--flush">
				<thead>
					<tr>
						<th>{ __( 'Name', 'rayetun-crm' ) }</th>
						<th>{ __( 'Subject', 'rayetun-crm' ) }</th>
						{ canEdit && <th /> }
					</tr>
				</thead>
				<tbody>
					{ templates.length === 0 && (
						<tr>
							<td colSpan={ canEdit ? 3 : 2 } className="rtcrm-table__empty">
								{ __( 'No templates yet.', 'rayetun-crm' ) }
							</td>
						</tr>
					) }
					{ templates.map( ( t ) => (
						<tr key={ t.id }>
							<td className="rtcrm-name">{ t.name }</td>
							<td className="rtcrm-sub">{ t.subject || '—' }</td>
							{ canEdit && (
								<td className="rtcrm-actions-col">
									<button className="rtcrm-btn rtcrm-btn--danger" onClick={ () => remove( t.id ) }>
										{ __( 'Delete', 'rayetun-crm' ) }
									</button>
								</td>
							) }
						</tr>
					) ) }
				</tbody>
			</table>

			{ canEdit && (
				<div className="rtcrm-addfield">
					<div className="rtcrm-fieldgroup-label">{ __( 'Add a template', 'rayetun-crm' ) }</div>
					<div className="rtcrm-addfield__row">
						<input type="text" placeholder={ __( 'Name', 'rayetun-crm' ) } value={ name } onChange={ ( e ) => setName( e.target.value ) } />
						<input type="text" placeholder={ __( 'Subject', 'rayetun-crm' ) } value={ subject } onChange={ ( e ) => setSubject( e.target.value ) } />
					</div>
					<textarea
						className="rtcrm-template-body"
						rows={ 4 }
						placeholder={ __( 'Message body…', 'rayetun-crm' ) }
						value={ body }
						onChange={ ( e ) => setBody( e.target.value ) }
					/>
					<button className="rtcrm-btn rtcrm-btn--primary" onClick={ add } disabled={ saving || ! name.trim() }>
						{ saving ? __( 'Saving…', 'rayetun-crm' ) : __( 'Add template', 'rayetun-crm' ) }
					</button>
				</div>
			) }
		</div>
	);
}
