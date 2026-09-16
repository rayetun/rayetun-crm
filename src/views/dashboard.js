/**
 * Dashboard — headline widgets aggregated from one endpoint.
 */
import { useState, useEffect } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { request } from '../lib/api';

function money( value, currency ) {
	return `${ currency || '' }${ Number( value || 0 ).toLocaleString( undefined, { maximumFractionDigits: 0 } ) }`;
}

function activityText( a ) {
	if ( a.type === 'note' ) {
		return __( 'Note added', 'rayetun-crm' );
	}
	if ( a.type === 'status_changed' ) {
		return __( 'Status changed', 'rayetun-crm' );
	}
	if ( a.type === 'created' ) {
		return __( 'Contact created', 'rayetun-crm' );
	}
	if ( a.type === 'form_submission' ) {
		return __( 'Form submitted', 'rayetun-crm' );
	}
	if ( a.type === 'email' ) {
		return __( 'Email sent', 'rayetun-crm' );
	}
	return a.type;
}

const QuickIcon = ( { d } ) => (
	<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
		<path d={ d } />
	</svg>
);

// Minimal line-icon paths (person+, dollar, chart, mail).
const ICON = {
	contact: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M19 8v6 M22 11h-6',
	deal: 'M12 1v22 M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
	report: 'M3 3v18h18 M7 14l3-4 3 3 4-6',
	template: 'M4 4h16v16H4z M4 9h16 M9 9v11',
};

export default function Dashboard( { onNavigate, canEdit, modules = {} } ) {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );

	const quickLinks = [
		canEdit && { key: 'contact', label: __( 'Add contact', 'rayetun-crm' ), icon: ICON.contact, go: () => onNavigate( 'contacts', 'new' ) },
		canEdit && modules.pipeline && { key: 'deal', label: __( 'Add deal', 'rayetun-crm' ), icon: ICON.deal, go: () => onNavigate( 'pipeline', 'new' ) },
		{ key: 'report', label: __( 'View reports', 'rayetun-crm' ), icon: ICON.report, go: () => onNavigate( 'reports' ) },
		{ key: 'template', label: __( 'Email templates', 'rayetun-crm' ), icon: ICON.template, go: () => onNavigate( 'settings' ) },
	].filter( Boolean );

	useEffect( () => {
		request( 'dashboard', 'GET' )
			.then( setData )
			.catch( ( err ) => setError( err.message || __( 'Failed to load the dashboard.', 'rayetun-crm' ) ) );
	}, [] );

	if ( error ) {
		return <div className="rtcrm-card rtcrm-card--error">{ error }</div>;
	}
	if ( ! data ) {
		return <div className="rtcrm-card">{ __( 'Loading…', 'rayetun-crm' ) }</div>;
	}

	const delta = data.leads_this_week - data.leads_last_week;
	const maxStage = Math.max( 1, ...data.pipeline_stages.map( ( s ) => s.value ) );

	return (
		<div className="rtcrm-dashboard">
			<div className="rtcrm-stats">
				<div className="rtcrm-stat">
					<span className="rtcrm-stat__label">{ __( 'Leads this week', 'rayetun-crm' ) }</span>
					<span className="rtcrm-stat__value">{ data.leads_this_week }</span>
					<span className="rtcrm-stat__sub">
						{ delta >= 0 ? '▲ ' : '▼ ' }
						{ sprintf(
							/* translators: %d: change vs last week. */
							__( '%d vs last week', 'rayetun-crm' ),
							Math.abs( delta )
						) }
					</span>
				</div>
				<div className="rtcrm-stat">
					<span className="rtcrm-stat__label">{ __( 'Open pipeline', 'rayetun-crm' ) }</span>
					<span className="rtcrm-stat__value">{ money( data.open_pipeline, data.currency ) }</span>
					<span className="rtcrm-stat__sub">{ __( 'in active deals', 'rayetun-crm' ) }</span>
				</div>
				<div className="rtcrm-stat">
					<span className="rtcrm-stat__label">{ __( 'Revenue this month', 'rayetun-crm' ) }</span>
					<span className="rtcrm-stat__value">{ money( data.revenue_this_month, data.currency ) }</span>
					<span className="rtcrm-stat__sub">{ __( 'from won deals', 'rayetun-crm' ) }</span>
				</div>
				<div className="rtcrm-stat">
					<span className="rtcrm-stat__label">{ __( 'Tasks due today', 'rayetun-crm' ) }</span>
					<span className="rtcrm-stat__value">{ data.tasks.today }</span>
					<span className="rtcrm-stat__sub">
						{ data.tasks.overdue > 0
							? sprintf(
								/* translators: %d: overdue tasks. */
								_n( '%d overdue', '%d overdue', data.tasks.overdue, 'rayetun-crm' ),
								data.tasks.overdue
							)
							: __( 'nothing overdue', 'rayetun-crm' ) }
					</span>
				</div>
			</div>

			{ onNavigate && quickLinks.length > 0 && (
				<div className="rtcrm-card rtcrm-quickcard">
					<h3 className="rtcrm-card__h">{ __( 'Quick actions', 'rayetun-crm' ) }</h3>
					<div className="rtcrm-quicklinks">
						{ quickLinks.map( ( q ) => (
							<button key={ q.key } className="rtcrm-quicklink" onClick={ q.go }>
								<span className="rtcrm-quicklink__icon"><QuickIcon d={ q.icon } /></span>
								<span className="rtcrm-quicklink__label">{ q.label }</span>
								<span className="rtcrm-quicklink__arrow" aria-hidden="true">→</span>
							</button>
						) ) }
					</div>
				</div>
			) }

			<div className="rtcrm-dashgrid">
				<div className="rtcrm-card">
					<h3 className="rtcrm-card__h">{ __( "Today's hot leads", 'rayetun-crm' ) }</h3>
					{ data.hot_leads.length === 0 && <p className="rtcrm-sub">{ __( 'No leads yet.', 'rayetun-crm' ) }</p> }
					<ul className="rtcrm-hotlist">
						{ data.hot_leads.map( ( c ) => (
							<li key={ c.id }>
								<span className={ `rtcrm-heat rtcrm-heat--${ c.heat }` } />
								<span className="rtcrm-hotlist__name">{ c.full_name }</span>
								<span className="rtcrm-hotlist__score">{ c.lead_score }</span>
							</li>
						) ) }
					</ul>
				</div>

				<div className="rtcrm-card">
					<h3 className="rtcrm-card__h">{ __( 'Pipeline value by stage', 'rayetun-crm' ) }</h3>
					<div className="rtcrm-bars">
						{ data.pipeline_stages.map( ( s, i ) => (
							<div className="rtcrm-bar" key={ i }>
								<span className="rtcrm-bar__label">{ s.name }</span>
								<span className="rtcrm-bar__track">
									<span
										className="rtcrm-bar__fill"
										style={ { width: `${ Math.round( ( s.value / maxStage ) * 100 ) }%`, background: s.color } }
									/>
								</span>
								<span className="rtcrm-bar__value">{ money( s.value, data.currency ) }</span>
							</div>
						) ) }
					</div>
				</div>
			</div>

			<div className="rtcrm-card">
				<h3 className="rtcrm-card__h">{ __( 'Recent activity', 'rayetun-crm' ) }</h3>
				{ data.recent_activity.length === 0 && <p className="rtcrm-sub">{ __( 'No activity yet.', 'rayetun-crm' ) }</p> }
				<ul className="rtcrm-feed">
					{ data.recent_activity.map( ( a ) => (
						<li key={ a.id } className={ `rtcrm-feed__item rtcrm-tl--${ a.type }` }>
							<span className="rtcrm-tl__dot" />
							<span className="rtcrm-feed__text">
								<strong>{ activityText( a ) }</strong>
								{ a.contact_name ? ` · ${ a.contact_name }` : '' }
							</span>
							<span className="rtcrm-feed__time">{ ( a.created || '' ).slice( 0, 16 ).replace( 'T', ' ' ) }</span>
						</li>
					) ) }
				</ul>
			</div>
		</div>
	);
}
