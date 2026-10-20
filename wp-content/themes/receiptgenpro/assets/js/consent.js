/**
 * ReceiptGenPro — cookie consent banner
 *
 * Minimal, dependency-free consent gate. Stores the visitor's choice in
 * localStorage (per-browser only, never sent anywhere) and dispatches a
 * "rfConsentGranted" event on Accept — inc/adsense.php listens for this
 * event before loading the AdSense script, so ads/analytics never load
 * before a visitor has actively consented.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'rf_cookie_consent'; // 'granted' | 'declined'

	function getStoredConsent() {
		try {
			return window.localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	}

	function setStoredConsent( value ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, value );
		} catch ( e ) {
			// localStorage unavailable (private browsing, blocked storage) —
			// the banner will simply reappear next visit, which is an
			// acceptable fallback rather than a broken experience.
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var banner  = document.getElementById( 'rf-consent-banner' );
		var accept  = document.getElementById( 'rf-consent-accept' );
		var decline = document.getElementById( 'rf-consent-decline' );
		if ( ! banner ) {
			return;
		}

		var stored = getStoredConsent();

		if ( stored === 'granted' ) {
			document.dispatchEvent( new Event( 'rfConsentGranted' ) );
			return;
		}
		if ( stored === 'declined' ) {
			return; // Respect the previous decline — don't nag again.
		}

		// No stored choice yet — show the banner. The page gets room at the
		// bottom while it shows, so it never covers the footer links.
		banner.hidden = false;
		function pad() {
			document.body.style.paddingBottom = banner.hidden ? '' : banner.offsetHeight + 'px';
		}
		function hide() {
			banner.hidden = true;
			window.removeEventListener( 'resize', pad );
			pad();
		}
		pad();
		window.addEventListener( 'resize', pad );

		if ( accept ) {
			accept.addEventListener( 'click', function () {
				setStoredConsent( 'granted' );
				hide();
				document.dispatchEvent( new Event( 'rfConsentGranted' ) );
			} );
		}
		if ( decline ) {
			decline.addEventListener( 'click', function () {
				setStoredConsent( 'declined' );
				hide();
			} );
		}
	} );
} )();
