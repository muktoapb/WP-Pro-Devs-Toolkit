/**
 * Entry point for the WP Pro Devs Toolkit admin app.
 */
import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import App from './app';
import './style.scss';

domReady( () => {
	const container = document.getElementById( 'wppdt-admin-root' );

	if ( container ) {
		createRoot( container ).render( <App /> );
	}
} );
