/**
 * RayEtun CRM — root application component.
 *
 * M0 ships the SaaS-style shell (dark navy top bar + sidebar, light canvas) and
 * a bootstrap fetch that proves the internal REST API and capability gating are
 * wired end to end. Feature routes are filled in from M1 onward.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import Dashboard from './views/dashboard';
import Contacts from './views/contacts';
import Pipeline from './views/pipeline';
import Tasks from './views/tasks';
import Reports from './views/reports';
import Settings from './views/settings';
import Onboarding from './views/onboarding';
import BrandMark from './brand-mark';

const NAV = [
	{ key: 'dashboard', label: __( 'Dashboard', 'rayetun-crm' ), subtitle: __( 'Your CRM at a glance.', 'rayetun-crm' ) },
	{ key: 'contacts', label: __( 'Contacts', 'rayetun-crm' ), subtitle: __( 'People and companies you work with.', 'rayetun-crm' ) },
	{ key: 'pipeline', label: __( 'Pipeline', 'rayetun-crm' ), subtitle: __( 'Move deals through your stages.', 'rayetun-crm' ), module: 'pipeline' },
	{ key: 'tasks', label: __( 'Tasks', 'rayetun-crm' ), subtitle: __( 'Follow-ups and reminders.', 'rayetun-crm' ), module: 'tasks' },
	{ key: 'reports', label: __( 'Reports', 'rayetun-crm' ), subtitle: __( 'Pipeline and lead insights.', 'rayetun-crm' ) },
	{ key: 'settings', label: __( 'Settings', 'rayetun-crm' ), subtitle: __( 'Configure RayEtun CRM.', 'rayetun-crm' ) },
];

export default function App() {
	const [ view, setView ] = useState( 'dashboard' );
	const [ intent, setIntent ] = useState( null );
	const [ boot, setBoot ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ onboardDismissed, setOnboardDismissed ] = useState( false );

	// Navigate to a view, optionally carrying a one-shot intent (e.g. open the
	// "new contact" panel). The target view consumes and clears it.
	const go = ( target, targetIntent = null ) => {
		setIntent( targetIntent );
		setView( target );
	};

	// Loads (or reloads) the bootstrap payload. Reused after a module toggle so
	// the nav and per-module gating update live, without a full page reload.
	const loadBoot = useCallback( () => {
		return apiFetch( { path: 'rayetun-crm/v1/bootstrap' } )
			.then( setBoot )
			.catch( ( err ) =>
				setError( err.message || __( 'Failed to load.', 'rayetun-crm' ) )
			);
	}, [] );

	useEffect( () => {
		loadBoot();
	}, [ loadBoot ] );

	const modules = ( boot && boot.modules ) || {};
	const visibleNav = boot ? NAV.filter( ( n ) => ! n.module || modules[ n.module ] ) : NAV;
	const active = NAV.find( ( n ) => n.key === view );

	return (
		<div className="rtcrm-app">
			<header className="rtcrm-topbar">
				<span className="rtcrm-brand">
					<BrandMark />
					RayEtun CRM
				</span>

				<nav className="rtcrm-topnav">
					{ visibleNav.map( ( item ) => {
						const overdue = item.key === 'tasks' && boot && boot.task_counts ? boot.task_counts.overdue : 0;
						return (
							<button
								key={ item.key }
								className={
									'rtcrm-topnav__item' +
									( view === item.key ? ' is-active' : '' )
								}
								aria-current={ view === item.key ? 'page' : undefined }
								onClick={ () => go( item.key ) }
							>
								{ item.label }
								{ overdue > 0 && (
									<span className="rtcrm-navbadge">
										{ overdue }
										<span className="rtcrm-sr-only">{ __( 'overdue tasks', 'rayetun-crm' ) }</span>
									</span>
								) }
							</button>
						);
					} ) }
				</nav>
			</header>

			<main className="rtcrm-main">
					<div className="rtcrm-page">
						<div className="rtcrm-page__header">
							<h1 className="rtcrm-page__title">{ active?.label }</h1>
							<p className="rtcrm-page__subtitle">{ active?.subtitle }</p>
						</div>

						{ error && (
							<div className="rtcrm-card rtcrm-card--error">
								{ error }
							</div>
						) }

						{ ! error && ! boot && (
							<div className="rtcrm-card">
								{ __( 'Loading…', 'rayetun-crm' ) }
							</div>
						) }

						{ boot && view === 'dashboard' && (
							<Dashboard onNavigate={ go } canEdit={ !! boot.capabilities?.edit } modules={ modules } />
						) }

						{ boot && view === 'contacts' && (
							<Contacts
								canEdit={ !! boot.capabilities?.edit }
								canManage={ !! boot.capabilities?.manage }
								canEmail={ !! modules.email }
								intent={ intent }
								onIntentDone={ () => setIntent( null ) }
							/>
						) }

						{ boot && view === 'pipeline' && ( modules.pipeline ? (
							<Pipeline
								canEdit={ !! boot.capabilities?.edit }
								canManage={ !! boot.capabilities?.manage }
								intent={ intent }
								onIntentDone={ () => setIntent( null ) }
							/>
						) : (
							<div className="rtcrm-card">{ __( 'The Sales pipeline module is turned off. Enable it under Settings → Modules.', 'rayetun-crm' ) }</div>
						) ) }

						{ boot && view === 'tasks' && ( modules.tasks ? (
							<Tasks canEdit={ !! boot.capabilities?.edit } canManage={ !! boot.capabilities?.manage } />
						) : (
							<div className="rtcrm-card">{ __( 'The Tasks module is turned off. Enable it under Settings → Modules.', 'rayetun-crm' ) }</div>
						) ) }

						{ boot && view === 'settings' && (
							<Settings canManage={ !! boot.capabilities?.manage } modules={ modules } onModulesChanged={ loadBoot } />
						) }

						{ boot && view === 'reports' && (
							<Reports />
						) }
					</div>
			</main>

			{ boot && ! boot.onboarded && ! onboardDismissed && (
				<Onboarding
					shortcode={ boot.form_shortcode || '[rayetun_crm_form]' }
					onDone={ () => setOnboardDismissed( true ) }
				/>
			) }
		</div>
	);
}
