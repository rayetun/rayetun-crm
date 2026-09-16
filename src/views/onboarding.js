/**
 * First-run onboarding — a short welcome shown until the user gets started.
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { request } from '../lib/api';
import BrandMark from '../brand-mark';
import useDialog from '../lib/use-dialog';

const HIGHLIGHTS = [
	{ icon: '📇', title: __( 'Manage contacts', 'rayetun-crm' ), text: __( 'Custom fields, tags, notes and a full activity timeline for every lead and customer.', 'rayetun-crm' ) },
	{ icon: '📊', title: __( 'Work your pipeline', 'rayetun-crm' ), text: __( 'Drag deals across stages on a visual Kanban board with live totals and analytics.', 'rayetun-crm' ) },
	{ icon: '✉️', title: __( 'Capture leads', 'rayetun-crm' ), text: __( 'Auto-capture from Contact Form 7, WPForms and Fluent Forms, or use the built-in form.', 'rayetun-crm' ) },
	{ icon: '🛒', title: __( 'Sync WooCommerce', 'rayetun-crm' ), text: __( 'Paid orders become contacts with purchase history and deals — automatically.', 'rayetun-crm' ) },
];

export default function Onboarding( { shortcode, onDone } ) {
	const [ copied, setCopied ] = useState( false );
	const [ saving, setSaving ] = useState( false );

	const copy = () => {
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( shortcode ).then( () => {
				setCopied( true );
				setTimeout( () => setCopied( false ), 1600 );
			} );
		}
	};

	const finish = async () => {
		setSaving( true );
		try {
			await request( 'onboarding', 'POST' );
		} catch ( e ) {
			// Non-blocking: dismiss regardless.
		}
		onDone();
	};

	const dialogRef = useDialog( finish );

	return (
		<div className="rtcrm-slideover" role="dialog" aria-modal="true" aria-labelledby="rtcrm-onboard-title">
			<div className="rtcrm-slideover__backdrop" />
			<div className="rtcrm-onboard" ref={ dialogRef }>
				<div className="rtcrm-onboard__brand">
					<BrandMark />
					<span id="rtcrm-onboard-title">{ __( 'Welcome to RayEtun CRM', 'rayetun-crm' ) }</span>
				</div>
				<p className="rtcrm-onboard__lead">
					{ __( 'Your self-hosted CRM — leads, pipeline, tasks and reports, all in WordPress. Here is what you can do:', 'rayetun-crm' ) }
				</p>

				<div className="rtcrm-onboard__grid">
					{ HIGHLIGHTS.map( ( h, i ) => (
						<div className="rtcrm-onboard__item" key={ i }>
							<span className="rtcrm-onboard__icon" aria-hidden="true">{ h.icon }</span>
							<div>
								<strong>{ h.title }</strong>
								<p>{ h.text }</p>
							</div>
						</div>
					) ) }
				</div>

				<div className="rtcrm-onboard__shortcode">
					<span className="rtcrm-onboard__sc-label">{ __( 'Drop this on any page to capture leads:', 'rayetun-crm' ) }</span>
					<code>{ shortcode }</code>
					<button className="rtcrm-btn rtcrm-btn--ghost" onClick={ copy }>
						{ copied ? __( 'Copied!', 'rayetun-crm' ) : __( 'Copy', 'rayetun-crm' ) }
					</button>
				</div>

				<div className="rtcrm-onboard__foot">
					<button className="rtcrm-btn rtcrm-btn--primary" onClick={ finish } disabled={ saving }>
						{ __( 'Get started', 'rayetun-crm' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
