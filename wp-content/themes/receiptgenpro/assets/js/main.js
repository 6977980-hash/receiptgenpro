/**
 * ReceiptGenPro — sitewide JS (mobile nav toggle only).
 * Kept deliberately tiny — no framework, no jQuery dependency.
 */
( function () {
	'use strict';
	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.getElementById( 'rf-nav-toggle' );
		var menu   = document.getElementById( 'rf-primary-nav-mobile' );
		if ( ! toggle || ! menu ) {
			return;
		}
		function setOpen( open ) {
			toggle.setAttribute( 'aria-expanded', String( open ) );
			menu.classList.toggle( 'is-open', open );
		}
		toggle.addEventListener( 'click', function () {
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
		// Close on Escape (focus back on the button) or when a link is tapped.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && menu.classList.contains( 'is-open' ) ) {
				setOpen( false );
				toggle.focus();
			}
		} );
		menu.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				setOpen( false );
			}
		} );
	} );
} )();
