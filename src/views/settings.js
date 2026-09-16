/**
 * Settings — custom field manager.
 *
 * Lets an administrator define the custom fields that appear on the contact
 * form. Only the "manage" capability can create/delete definitions.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../lib/api';
import EmailTemplates from './email-templates';
import Modules from './modules';

const TYPES = [
	{ value: 'text', label: __( 'Text', 'rayetun-crm' ) },
	{ value: 'number', label: __( 'Number', 'rayetun-crm' ) },
	{ value: 'date', label: __( 'Date', 'rayetun-crm' ) },
	{ value: 'select', label: __( 'Dropdown', 'rayetun-crm' ) },
	{ value: 'checkbox', label: __( 'Checkbox', 'rayetun-crm' ) },
	{ value: 'url', label: __( 'URL', 'rayetun-crm' ) },
];

export default function Settings( { canManage, modules = {}, onModulesChanged } ) {
	const [ fields, setFields ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	const [ label, setLabel ] = useState( '' );
	const [ type, setType ] = useState( 'text' );
	const [ options, setOptions ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const load = () => {
		setLoading( true );
		request( 'fields', 'GET' )
			.then( setFields )
			.catch( ( err ) => setError( err.message || __( 'Failed to load fields.', 'rayetun-crm' ) ) )
			.finally( () => setLoading( false ) );
	};

	useEffect( load, [] );

	const add = async () => {
		if ( ! label.trim() ) {
			return;
		}
		setSaving( true );
		setError( null );
		try {
			await request( 'fields', 'POST', {
				label,
				type,
				options: type === 'select' ? options : '',
			} );
			setLabel( '' );
			setType( 'text' );
			setOptions( '' );
			load();
		} catch ( err ) {
			setError( err.message || __( 'Could not add the field.', 'rayetun-crm' ) );
		} finally {
			setSaving( false );
		}
	};

	const remove = async ( id ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Delete this field and all its stored values?', 'rayetun-crm' ) ) ) {
			return;
		}
		try {
			await request( `fields/${ id }`, 'DELETE' );
			load();
		} catch ( err ) {
			setError( err.message || __( 'Could not delete the field.', 'rayetun-crm' ) );
		}
	};

	return (
		<div className="rtcrm-settings">
			<Modules canManage={ canManage } onChanged={ onModulesChanged } />

			<div className="rtcrm-card">
				<h2>{ __( 'Custom fields', 'rayetun-crm' ) }</h2>
				<p className="rtcrm-sub">
					{ __( 'Add extra fields to every contact record.', 'rayetun-crm' ) }
				</p>

				{ error && <div className="rtcrm-inline-error">{ error }</div> }

				{ loading && <p className="rtcrm-sub">{ __( 'Loading…', 'rayetun-crm' ) }</p> }

				{ ! loading && (
					<table className="rtcrm-table rtcrm-table--flush">
						<thead>
							<tr>
								<th>{ __( 'Label', 'rayetun-crm' ) }</th>
								<th>{ __( 'Key', 'rayetun-crm' ) }</th>
								<th>{ __( 'Type', 'rayetun-crm' ) }</th>
								<th>{ __( 'Options', 'rayetun-crm' ) }</th>
								{ canManage && <th /> }
							</tr>
						</thead>
						<tbody>
							{ fields.length === 0 && (
								<tr>
									<td colSpan={ canManage ? 5 : 4 } className="rtcrm-table__empty">
										{ __( 'No custom fields yet.', 'rayetun-crm' ) }
									</td>
								</tr>
							) }
							{ fields.map( ( f ) => (
								<tr key={ f.id }>
									<td className="rtcrm-name">{ f.label }</td>
									<td><code>{ f.field_key }</code></td>
									<td>{ ( TYPES.find( ( t ) => t.value === f.type ) || {} ).label || f.type }</td>
									<td className="rtcrm-sub">{ f.options.join( ', ' ) || '—' }</td>
									{ canManage && (
										<td className="rtcrm-actions-col">
											<button className="rtcrm-btn rtcrm-btn--danger" onClick={ () => remove( f.id ) }>
												{ __( 'Delete', 'rayetun-crm' ) }
											</button>
										</td>
									) }
								</tr>
							) ) }
						</tbody>
					</table>
				) }

				{ canManage && (
					<div className="rtcrm-addfield">
						<div className="rtcrm-fieldgroup-label">{ __( 'Add a field', 'rayetun-crm' ) }</div>
						<div className="rtcrm-addfield__row">
							<input
								type="text"
								placeholder={ __( 'Label', 'rayetun-crm' ) }
								value={ label }
								onChange={ ( e ) => setLabel( e.target.value ) }
							/>
							<select value={ type } onChange={ ( e ) => setType( e.target.value ) }>
								{ TYPES.map( ( t ) => (
									<option key={ t.value } value={ t.value }>{ t.label }</option>
								) ) }
							</select>
							{ type === 'select' && (
								<input
									type="text"
									placeholder={ __( 'Options, comma separated', 'rayetun-crm' ) }
									value={ options }
									onChange={ ( e ) => setOptions( e.target.value ) }
								/>
							) }
							<button className="rtcrm-btn rtcrm-btn--primary" onClick={ add } disabled={ saving || ! label.trim() }>
								{ saving ? __( 'Adding…', 'rayetun-crm' ) : __( 'Add field', 'rayetun-crm' ) }
							</button>
						</div>
					</div>
				) }
			</div>

			{ modules.email && <EmailTemplates canEdit={ canManage } /> }
		</div>
	);
}
