/**
 * Entry point for the WP Pro Devs Toolkit admin app.
 *
 * The same bundle is enqueued on every toolkit screen; it mounts the view that
 * matches the root element present on the page.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import App from './app';
import McpApp from './mcp-app';
import './style.scss';

domReady( () => {
	const features = document.getElementById( 'wppdt-admin-root' );
	if ( features ) {
		createRoot( features ).render( <App /> );
	}

	const mcp = document.getElementById( 'wppdt-mcp-root' );
	if ( mcp ) {
		createRoot( mcp ).render( <McpApp /> );
	}
} );
