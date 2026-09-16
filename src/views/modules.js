/**
 * Settings → Modules.
 *
 * Lets an administrator switch optional CRM modules on or off. Turning a module
 * off suspends its features, routes and navigation but never deletes data.
 * Because module state changes what REST routes and nav exist, a change prompts
 * a reload so the whole app re-boots consistently.
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../lib/api';

function CoreCheck() {
	return (
		<span className="rtcrm-module__core" title={ __( 'Always on', 'rayetun-crm' ) } aria-label={ __( 'Always on', 'rayetun-crm' ) }>
			<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
				<path d="M20 6 9 17l-5-5" />
			</svg>
		</span>
	);
}

function ModuleSwitch( { on, disabled, onToggle, label } ) {
	return (
		<button
			type="button"
			className={ 'rtcrm-switch' + ( on ? ' is-on' : '' ) }
			role="switch"
			aria-checked={ on }
			aria-label={ label }
			disabled={ disabled }
			onClick={ onToggle }
		>
			<span className="rtcrm-switch__track"><span className="rtcrm-switch__thumb" /></span>
		</button>
	);
}

export default function Modules( { canManage, onChanged } ) {
	const [ rows, setRows ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( '' );

	useEffect( () => {
		request( 'modules', 'GET' )
			.then( setRows )
			.catch( ( err ) => setError( err.message || __( 'Failed to load modules.', 'rayetun-crm' ) ) );
	}, [] );

	const toggle = async ( row ) => {
		if ( ! canManage || ! row.available || saving ) {
			return;
		}
		const next = ! row.enabled;
		setSaving( row.key );
		setError( null );
		try {
			const updated = await request( 'modules', 'POST', { modules: { [ row.key ]: next } } );
			setRows( updated );
			// Refresh the app shell so the navigation and per-module gating update
			// immediately — the toggled module's tab appears or disappears at once.
			if ( onChanged ) {
				onChanged();
			}
		} catch ( err ) {
			setError( err.message || __( 'Could not update the module.', 'rayetun-crm' ) );
		} finally {
			setSaving( '' );
		}
	};

	return (
		<div className="rtcrm-card">
			<h2>{ __( 'Modules', 'rayetun-crm' ) }</h2>
			<p className="rtcrm-sub">
				{ __( 'Turn optional features on or off to keep your CRM lean. Changes apply instantly — switching a module off hides it everywhere but never deletes your data, so you can turn it back on any time.', 'rayetun-crm' ) }
			</p>

			{ error && <div className="rtcrm-inline-error">{ error }</div> }

			{ ! rows && ! error && <p className="rtcrm-sub">{ __( 'Loading…', 'rayetun-crm' ) }</p> }

			{ rows && (
				<ul className="rtcrm-modules">
					{ rows.map( ( row ) => {
						const unavailable = ! row.available;
						return (
							<li key={ row.key } className={ 'rtcrm-module' + ( row.enabled ? ' is-on' : '' ) + ( row.core ? ' is-core' : '' ) + ( unavailable ? ' is-muted' : '' ) }>
								<div className="rtcrm-module__head">
									<span className="rtcrm-module__title">
										{ row.label }
										{ row.core && <span className="rtcrm-module__badge rtcrm-module__badge--core">{ __( 'Core', 'rayetun-crm' ) }</span> }
										{ unavailable && <span className="rtcrm-module__badge rtcrm-module__badge--muted">{ __( 'Unavailable', 'rayetun-crm' ) }</span> }
									</span>
									{ row.core ? (
										<CoreCheck />
									) : (
										<ModuleSwitch
											on={ row.enabled }
											disabled={ ! canManage || ! row.available || saving === row.key }
											onToggle={ () => toggle( row ) }
											label={ row.label }
										/>
									) }
								</div>
								<div className="rtcrm-module__desc">{ row.description }</div>
								{ unavailable && row.requires === 'woocommerce' && (
									<div className="rtcrm-module__hint">{ __( 'Install and activate WooCommerce to use this module.', 'rayetun-crm' ) }</div>
								) }
							</li>
						);
					} ) }
				</ul>
			) }
		</div>
	);
}
