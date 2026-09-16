/**
 * Reports — contacts over time, lead sources, and pipeline conversion, each
 * exportable to CSV.
 */
import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { request } from '../lib/api';
import { LineChart, DonutChart } from '../lib/charts';
import { downloadCSV } from '../lib/csv';

export default function Reports() {
	const [ period, setPeriod ] = useState( 'week' );
	const [ overTime, setOverTime ] = useState( null );
	const [ sources, setSources ] = useState( null );
	const [ pipeline, setPipeline ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		request( `reports/contacts-over-time?period=${ period }`, 'GET' )
			.then( setOverTime )
			.catch( ( err ) => setError( err.message ) );
	}, [ period ] );

	useEffect( () => {
		Promise.all( [
			request( 'reports/sources', 'GET' ),
			request( 'reports/pipeline', 'GET' ),
		] )
			.then( ( [ s, p ] ) => { setSources( s ); setPipeline( p ); } )
			.catch( ( err ) => setError( err.message ) );
	}, [] );

	const exportOverTime = () =>
		downloadCSV( 'contacts-over-time.csv', [ __( 'Period', 'rayetun-crm' ), __( 'Contacts', 'rayetun-crm' ) ], ( overTime || [] ).map( ( d ) => [ d.label, d.count ] ) );

	const exportSources = () =>
		downloadCSV( 'lead-sources.csv', [ __( 'Source', 'rayetun-crm' ), __( 'Contacts', 'rayetun-crm' ) ], ( sources || [] ).map( ( d ) => [ d.label, d.value ] ) );

	const exportPipeline = () =>
		downloadCSV( 'pipeline-report.csv', [ __( 'Stage', 'rayetun-crm' ), __( 'Deals', 'rayetun-crm' ), __( 'Value', 'rayetun-crm' ) ], ( ( pipeline && pipeline.stages ) || [] ).map( ( s ) => [ s.name, s.count, s.value ] ) );

	const maxFunnel = pipeline ? Math.max( 1, ...pipeline.stages.map( ( s ) => s.count ) ) : 1;

	return (
		<div className="rtcrm-reports">
			{ error && <div className="rtcrm-inline-error">{ error }</div> }

			<div className="rtcrm-card">
				<div className="rtcrm-report__head">
					<h3 className="rtcrm-card__h">{ __( 'Contacts added over time', 'rayetun-crm' ) }</h3>
					<div className="rtcrm-spacer" />
					<div className="rtcrm-seg">
						<button className={ period === 'week' ? 'is-active' : '' } onClick={ () => setPeriod( 'week' ) }>{ __( 'Weekly', 'rayetun-crm' ) }</button>
						<button className={ period === 'month' ? 'is-active' : '' } onClick={ () => setPeriod( 'month' ) }>{ __( 'Monthly', 'rayetun-crm' ) }</button>
					</div>
					<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ exportOverTime } disabled={ ! overTime }>{ __( 'Export CSV', 'rayetun-crm' ) }</button>
				</div>
				{ overTime ? <LineChart data={ overTime } /> : <p className="rtcrm-sub">{ __( 'Loading…', 'rayetun-crm' ) }</p> }
			</div>

			<div className="rtcrm-dashgrid">
				<div className="rtcrm-card">
					<div className="rtcrm-report__head">
						<h3 className="rtcrm-card__h">{ __( 'Lead sources', 'rayetun-crm' ) }</h3>
						<div className="rtcrm-spacer" />
						<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ exportSources } disabled={ ! sources }>{ __( 'Export CSV', 'rayetun-crm' ) }</button>
					</div>
					{ sources ? ( sources.length ? <DonutChart data={ sources } /> : <p className="rtcrm-sub">{ __( 'No data yet.', 'rayetun-crm' ) }</p> ) : <p className="rtcrm-sub">{ __( 'Loading…', 'rayetun-crm' ) }</p> }
				</div>

				<div className="rtcrm-card">
					<div className="rtcrm-report__head">
						<h3 className="rtcrm-card__h">{ __( 'Pipeline conversion', 'rayetun-crm' ) }</h3>
						<div className="rtcrm-spacer" />
						<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ exportPipeline } disabled={ ! pipeline }>{ __( 'Export CSV', 'rayetun-crm' ) }</button>
					</div>
					{ pipeline ? (
						<>
							<div className="rtcrm-funnel">
								{ pipeline.stages.map( ( s, i ) => (
									<div className="rtcrm-funnel__row" key={ i }>
										<span className="rtcrm-funnel__label">{ s.name }</span>
										<span className="rtcrm-funnel__track">
											<span className="rtcrm-funnel__fill" style={ { width: `${ Math.round( ( s.count / maxFunnel ) * 100 ) }%`, background: s.color } }>{ s.count }</span>
										</span>
									</div>
								) ) }
							</div>
							<p className="rtcrm-sub rtcrm-winrate">
								{ sprintf(
									/* translators: 1: win rate, 2: won, 3: lost. */
									__( 'Win rate %1$d%% · %2$d won · %3$d lost', 'rayetun-crm' ),
									pipeline.win_rate,
									pipeline.won,
									pipeline.lost
								) }
							</p>
						</>
					) : <p className="rtcrm-sub">{ __( 'Loading…', 'rayetun-crm' ) }</p> }
				</div>
			</div>
		</div>
	);
}
