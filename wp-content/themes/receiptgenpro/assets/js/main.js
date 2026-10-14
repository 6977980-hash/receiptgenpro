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
		toggle.addEventListener( 'click', function () {
			var expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
			toggle.setAttribute( 'aria-expanded', String( ! expanded ) );
			menu.classList.toggle( 'is-open' );
		} );
	} );
} )();
