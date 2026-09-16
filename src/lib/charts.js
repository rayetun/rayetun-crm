/**
 * Lightweight inline-SVG charts (no external dependency).
 */
import { __ } from '@wordpress/i18n';

export const PALETTE = [ '#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6' ];

/**
 * Area + line chart for a small time series.
 *
 * @param {Object}   props        Props.
 * @param {Array}    props.data   [{ label, count }].
 * @return {JSX.Element} Chart.
 */
export function LineChart( { data } ) {
	const w = 520;
	const h = 160;
	const pad = 24;
	const max = Math.max( 1, ...data.map( ( d ) => d.count ) );
	const stepX = data.length > 1 ? ( w - pad * 2 ) / ( data.length - 1 ) : 0;

	const points = data.map( ( d, i ) => {
		const x = pad + i * stepX;
		const y = h - pad - ( d.count / max ) * ( h - pad * 2 );
		return [ x, y ];
	} );

	const line = points.map( ( p ) => p.join( ',' ) ).join( ' ' );
	const area = `${ pad },${ h - pad } ${ line } ${ pad + ( data.length - 1 ) * stepX },${ h - pad }`;

	return (
		<div className="rtcrm-chart">
			<svg viewBox={ `0 0 ${ w } ${ h }` } width="100%" role="img" aria-label={ __( 'Contacts over time', 'rayetun-crm' ) }>
				<polygon points={ area } fill="rgba(99,102,241,0.12)" />
				<polyline points={ line } fill="none" stroke="#6366f1" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
				{ points.map( ( p, i ) => (
					<circle key={ i } cx={ p[ 0 ] } cy={ p[ 1 ] } r="3" fill="#6366f1" />
				) ) }
				{ data.map( ( d, i ) => (
					<text key={ i } x={ pad + i * stepX } y={ h - 6 } textAnchor="middle" fontSize="10" fill="#6b7280">
						{ d.label }
					</text>
				) ) }
			</svg>
		</div>
	);
}

/**
 * Donut chart with legend.
 *
 * @param {Object} props      Props.
 * @param {Array}  props.data [{ label, value }].
 * @return {JSX.Element} Chart.
 */
export function DonutChart( { data } ) {
	const total = data.reduce( ( sum, d ) => sum + d.value, 0 );
	const r = 52;
	const c = 2 * Math.PI * r;
	let offset = 0;

	return (
		<div className="rtcrm-donut">
			<svg viewBox="0 0 140 140" width="140" height="140" role="img" aria-label={ __( 'Lead sources', 'rayetun-crm' ) }>
				<g transform="translate(70,70) rotate(-90)">
					<circle r={ r } fill="none" stroke="#eef0f6" strokeWidth="16" />
					{ total > 0 && data.map( ( d, i ) => {
						const len = ( d.value / total ) * c;
						const seg = (
							<circle
								key={ i }
								r={ r }
								fill="none"
								stroke={ PALETTE[ i % PALETTE.length ] }
								strokeWidth="16"
								strokeDasharray={ `${ len } ${ c - len }` }
								strokeDashoffset={ -offset }
							/>
						);
						offset += len;
						return seg;
					} ) }
				</g>
			</svg>
			<ul className="rtcrm-legend">
				{ data.map( ( d, i ) => (
					<li key={ i }>
						<span className="rtcrm-legend__dot" style={ { background: PALETTE[ i % PALETTE.length ] } } />
						<span className="rtcrm-legend__label">{ d.label }</span>
						<span className="rtcrm-legend__value">{ d.value }</span>
					</li>
				) ) }
			</ul>
		</div>
	);
}
