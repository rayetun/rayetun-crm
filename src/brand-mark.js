/**
 * RayEtun CRM brand mark — a sales-pipeline board with a "won" stage, matching
 * the plugin's directory icon. Rendered inside the indigo `.rtcrm-brand__mark`
 * tile; the glyph scales with the tile size.
 */
import { __ } from '@wordpress/i18n';

export default function BrandMark( { className = 'rtcrm-brand__mark' } ) {
	return (
		<span className={ className } role="img" aria-label={ __( 'RayEtun CRM', 'rayetun-crm' ) }>
			<svg viewBox="0 0 24 24" width="70%" height="70%" fill="none" aria-hidden="true" focusable="false">
				<rect x="3" y="6" width="5" height="13" rx="1.8" fill="#ffffff" fillOpacity="0.85" />
				<rect x="9.5" y="6" width="5" height="13" rx="1.8" fill="#ffffff" fillOpacity="0.85" />
				<rect x="16" y="6" width="5" height="13" rx="1.8" fill="#ffffff" fillOpacity="0.85" />
				<rect x="14.6" y="3.4" width="7.4" height="7.4" rx="2.3" fill="#34D399" />
				<path d="M16.5 7.1 l1.4 1.4 l2.4 -2.9" fill="none" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
			</svg>
		</span>
	);
}
