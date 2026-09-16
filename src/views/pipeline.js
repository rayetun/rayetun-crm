/**
 * Pipeline module — a drag-and-drop Kanban board of deals across stages.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { request, fetchCollection } from '../lib/api';
import DealPanel from './deal-panel';

function money( value, currency ) {
	const n = Number( value || 0 );
	const formatted = n.toLocaleString( undefined, { maximumFractionDigits: 0 } );
	return `${ currency || '' }${ formatted }`;
}

// Turns a REST error into a readable message. A missing route means the
// pipeline module was switched off (usually in another tab); say so plainly
// instead of surfacing the raw "No route was found" string.
function errText( err, fallback ) {
	if ( err && err.code === 'rest_no_route' ) {
		return __( 'The Sales pipeline module is turned off. Enable it under Settings → Modules, then reload this page.', 'rayetun-crm' );
	}
	return ( err && err.message ) || fallback;
}

export default function Pipeline( { canEdit, canManage = false, intent, onIntentDone } ) {
	const [ board, setBoard ] = useState( null );
	const [ analytics, setAnalytics ] = useState( null );
	const [ pipelineId, setPipelineId ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ panel, setPanel ] = useState( null );
	const [ contacts, setContacts ] = useState( [] );
	const [ dragId, setDragId ] = useState( 0 );
	const [ dropStage, setDropStage ] = useState( 0 );
	const [ pendingNew, setPendingNew ] = useState( false );

	const loadBoard = useCallback( ( id ) => {
		setLoading( true );
		Promise.all( [
			request( `pipelines/${ id }/board`, 'GET' ),
			request( `pipelines/${ id }/analytics`, 'GET' ),
		] )
			.then( ( [ boardRes, statsRes ] ) => {
				setBoard( boardRes.pipeline );
				setAnalytics( statsRes );
				setError( null );
			} )
			.catch( ( err ) => setError( errText( err, __( 'Failed to load the pipeline.', 'rayetun-crm' ) ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		request( 'pipelines', 'GET' )
			.then( ( list ) => {
				if ( ! list.length ) {
					setError( __( 'No pipeline found.', 'rayetun-crm' ) );
					setLoading( false );
					return;
				}
				setPipelineId( list[ 0 ].id );
				loadBoard( list[ 0 ].id );
			} )
			.catch( ( err ) => {
				setError( errText( err, __( 'Failed to load pipelines.', 'rayetun-crm' ) ) );
				setLoading( false );
			} );

		fetchCollection( 'contacts', { per_page: 100, orderby: 'first_name', order: 'asc' } )
			.then( ( res ) => setContacts( res.items ) )
			.catch( () => setContacts( [] ) );
	}, [ loadBoard ] );

	// Arrived via a dashboard quick link: remember the request, then open the
	// new-deal panel once the board (and its stages) have loaded.
	useEffect( () => {
		if ( intent === 'new' && canEdit ) {
			setPendingNew( true );
		}
		if ( intent ) {
			onIntentDone && onIntentDone();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ intent ] );

	useEffect( () => {
		if ( pendingNew && board && board.stages && board.stages.length ) {
			setPanel( { deal: null, stageId: board.stages[ 0 ].id } );
			setPendingNew( false );
		}
	}, [ pendingNew, board ] );

	const onDrop = async ( stageId ) => {
		setDropStage( 0 );
		const dealId = dragId;
		setDragId( 0 );
		if ( ! dealId ) {
			return;
		}
		try {
			await request( `deals/${ dealId }/move`, 'PUT', { stage_id: stageId } );
			loadBoard( pipelineId );
		} catch ( err ) {
			setError( err.message || __( 'Could not move the deal.', 'rayetun-crm' ) );
		}
	};

	const onSaved = () => {
		setPanel( null );
		loadBoard( pipelineId );
	};

	if ( loading && ! board ) {
		return <div className="rtcrm-card">{ __( 'Loading…', 'rayetun-crm' ) }</div>;
	}

	if ( error && ! board ) {
		return <div className="rtcrm-card rtcrm-card--error">{ error }</div>;
	}

	const stages = board ? board.stages : [];

	return (
		<div className="rtcrm-pipeline">
			<div className="rtcrm-toolbar">
				<strong className="rtcrm-pipeline__name">{ board && board.name }</strong>
				<div className="rtcrm-spacer" />
				{ canEdit && (
					<button
						className="rtcrm-btn rtcrm-btn--primary"
						onClick={ () => setPanel( { deal: null, stageId: stages[ 0 ] ? stages[ 0 ].id : 0 } ) }
					>
						{ __( '+ Add deal', 'rayetun-crm' ) }
					</button>
				) }
			</div>

			{ error && <div className="rtcrm-inline-error" role="alert">{ error }</div> }

			{ analytics && (
				<div className="rtcrm-stats">
					<div className="rtcrm-stat">
						<span className="rtcrm-stat__label">{ __( 'Open pipeline', 'rayetun-crm' ) }</span>
						<span className="rtcrm-stat__value">{ money( analytics.open_value, analytics.currency ) }</span>
						<span className="rtcrm-stat__sub">{ sprintf(
							/* translators: %d: number of open deals. */
							_n( '%d open deal', '%d open deals', analytics.open_count, 'rayetun-crm' ),
							analytics.open_count
						) }</span>
					</div>
					<div className="rtcrm-stat">
						<span className="rtcrm-stat__label">{ __( 'Win rate', 'rayetun-crm' ) }</span>
						<span className="rtcrm-stat__value">{ analytics.win_rate }%</span>
						<span className="rtcrm-stat__sub">{ sprintf(
							/* translators: 1: number won, 2: number lost. */
							__( '%1$d won · %2$d lost', 'rayetun-crm' ),
							analytics.won_count,
							analytics.lost_count
						) }</span>
					</div>
					<div className="rtcrm-stat">
						<span className="rtcrm-stat__label">{ __( 'Avg. days to close', 'rayetun-crm' ) }</span>
						<span className="rtcrm-stat__value">{ analytics.avg_days_to_close }</span>
						<span className="rtcrm-stat__sub">{ __( 'won deals', 'rayetun-crm' ) }</span>
					</div>
					<div className="rtcrm-stat">
						<span className="rtcrm-stat__label">{ __( 'Won this month', 'rayetun-crm' ) }</span>
						<span className="rtcrm-stat__value">{ money( analytics.won_this_month_value, analytics.currency ) }</span>
						<span className="rtcrm-stat__sub">{ sprintf(
							/* translators: %d: number of deals. */
							_n( '%d deal', '%d deals', analytics.won_this_month_count, 'rayetun-crm' ),
							analytics.won_this_month_count
						) }</span>
					</div>
				</div>
			) }

			{ canEdit && (
				<p className="rtcrm-draghint">{ __( 'Tip: drag a card to move it between stages — or press Enter on a card to open it and change its stage.', 'rayetun-crm' ) }</p>
			) }

			<div className="rtcrm-board">
				{ stages.map( ( stage ) => (
					<div
						key={ stage.id }
						className={ 'rtcrm-col' + ( dropStage === stage.id ? ' is-dropping' : '' ) }
						role="group"
						aria-label={ sprintf(
							/* translators: 1: stage name, 2: number of deals. */
							_n( '%1$s, %2$d deal', '%1$s, %2$d deals', stage.count, 'rayetun-crm' ),
							stage.name,
							stage.count
						) }
						onDragOver={ ( e ) => { e.preventDefault(); if ( dropStage !== stage.id ) { setDropStage( stage.id ); } } }
						onDragLeave={ () => setDropStage( ( s ) => ( s === stage.id ? 0 : s ) ) }
						onDrop={ () => onDrop( stage.id ) }
					>
						<div className="rtcrm-col__head">
							<span className="rtcrm-col__dot" style={ { '--dot': stage.color } } />
							<span className="rtcrm-col__name">{ stage.name }</span>
							<span className="rtcrm-col__count">{ stage.count }</span>
						</div>
						<div className="rtcrm-col__total">{ money( stage.total, stage.deals[ 0 ] ? stage.deals[ 0 ].currency : '' ) }</div>

						<div className="rtcrm-col__cards">
							{ stage.deals.map( ( deal ) => (
								<div
									key={ deal.id }
									className={ 'rtcrm-deal' + ( dragId === deal.id ? ' is-dragging' : '' ) }
									role="button"
									tabIndex={ 0 }
									aria-label={ sprintf(
										/* translators: 1: deal title, 2: deal value. */
										__( '%1$s, %2$s. Press Enter to open and change its stage.', 'rayetun-crm' ),
										deal.title,
										money( deal.value, deal.currency )
									) }
									draggable={ canEdit }
									onDragStart={ () => setDragId( deal.id ) }
									onDragEnd={ () => { setDragId( 0 ); setDropStage( 0 ); } }
									onClick={ () => setPanel( { deal, stageId: deal.stage_id } ) }
									onKeyDown={ ( e ) => {
										if ( e.key === 'Enter' || e.key === ' ' ) {
											e.preventDefault();
											setPanel( { deal, stageId: deal.stage_id } );
										}
									} }
								>
									<div className="rtcrm-deal__top">
										{ canEdit && <span className="rtcrm-deal__grip" aria-hidden="true">⠿</span> }
										{ deal.probability > 0 && (
											<span className="rtcrm-deal__prob">{ deal.probability }%</span>
										) }
									</div>
									<div className="rtcrm-deal__title">{ deal.title }</div>
									<div className="rtcrm-deal__value">{ money( deal.value, deal.currency ) }</div>
									<div className="rtcrm-deal__meta">
										{ deal.contacts[ 0 ] && <span className="rtcrm-deal__contact">{ deal.contacts[ 0 ].name }</span> }
										<span className="rtcrm-deal__days">
											{ sprintf(
												/* translators: %d: number of days. */
												_n( '%d day in stage', '%d days in stage', deal.days_in_stage, 'rayetun-crm' ),
												deal.days_in_stage
											) }
										</span>
									</div>
								</div>
							) ) }

							{ stage.deals.length === 0 && (
								<div className="rtcrm-col__empty">{ __( 'No deals', 'rayetun-crm' ) }</div>
							) }
						</div>
					</div>
				) ) }
			</div>

			{ panel && (
				<DealPanel
					deal={ panel.deal }
					stageId={ panel.stageId }
					pipelineId={ pipelineId }
					stages={ stages }
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
