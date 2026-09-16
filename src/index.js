/**
 * RayEtun CRM — admin SPA entry point.
 *
 * Mounts the React app into the container printed by the admin page. All
 * WordPress packages (element, components, data, i18n, api-fetch) are provided
 * as script dependencies via the generated build/index.asset.php — they are not
 * bundled here.
 */
import { createRoot } from '@wordpress/element';
import App from './app';
import './style.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'rayetun-crm-app' );

	if ( ! container ) {
		return;
	}

	createRoot( container ).render( <App /> );
} );
