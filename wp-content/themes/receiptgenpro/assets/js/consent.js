/**
 * ReceiptGenPro — cookie consent banner
 *
 * Minimal, dependency-free consent gate. Stores the visitor's choice in
 * localStorage (per-browser only, never sent anywhere) and dispatches a
 * "rfConsentGranted" event on Accept — inc/adsense.php listens for this
 * event before loading the AdSense script, so ads/analytics never load
 * before a visitor has actively consented.
 *
 * Audit 20 Oct (#66):
 *  - the stored choice carries a version ("granted@2"); a choice saved
 *    under another version (or the old bare "granted"/"declined") asks
 *    again, so changing what the banner covers means bumping VERSION;
 *  - any [data-rf-consent-open] button (the footer's "Cookie settings")
 *    shows the banner again, so a visitor can withdraw or give consent;
 *    withdrawing reloads the page, since scripts already loaded can't be
 *    unloaded;
 *  - Accept and Decline update Google Consent Mode v2 (the default-denied
 *    stub is printed in <head> by inc/adsense.php).
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'rf_cookie_consent'; // 'granted@2' | 'declined@2'
	var VERSION     = '2';

	function getStoredConsent() {
		try {
			return parseConsent( window.localStorage.getItem( STORAGE_KEY ) );
		} catch ( e ) {
			return null;
		}
	}

	// 'granted' or 'declined' when the stored value is of this VERSION.
	function parseConsent( value ) {
		var m = /^(granted|declined)@(.+)$/.exec( value || '' );
		return m && m[2] === VERSION ? m[1] : null;
	}

	function setStoredConsent( value ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, value + '@' + VERSION );
		} catch ( e ) {
			// localStorage unavailable (private browsing, blocked storage) —
			// the banner will simply reappear next visit, which is an
			// acceptable fallback rather than a broken experience.
		}
	}

	function consentMode( granted ) {
		if ( typeof window.gtag !== 'function' ) {
			return;
		}
		var v = granted ? 'granted' : 'denied';
		window.gtag( 'consent', 'update', {
			ad_storage: v,
			ad_user_data: v,
			ad_personalization: v,
			analytics_storage: v
		} );
	}

	if ( typeof module !== 'undefined' && module.exports ) {
		module.exports = { parseConsent: parseConsent, VERSION: VERSION };
	}
	if ( typeof document === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var banner  = document.getElementById( 'rf-consent-banner' );
		var accept  = document.getElementById( 'rf-consent-accept' );
		var decline = document.getElementById( 'rf-consent-decline' );
		if ( ! banner ) {
			return;
		}

		var stored = getStoredConsent();

		// The page gets room at the bottom while the banner shows, so it
		// never covers the footer links.
		function pad() {
			document.body.style.paddingBottom = banner.hidden ? '' : banner.offsetHeight + 'px';
		}
		function show() {
			banner.hidden = false;
			pad();
			window.addEventListener( 'resize', pad );
		}
		function hide() {
			banner.hidden = true;
			window.removeEventListener( 'resize', pad );
			pad();
		}

		if ( stored === 'granted' ) {
			consentMode( true );
			document.dispatchEvent( new Event( 'rfConsentGranted' ) );
		} else if ( stored !== 'declined' ) {
			show(); // No choice of this version yet.
		}

		Array.prototype.forEach.call( document.querySelectorAll( '[data-rf-consent-open]' ), function ( btn ) {
			btn.hidden = false;
			btn.addEventListener( 'click', function () {
				show();
				if ( accept ) {
					accept.focus();
				}
			} );
		} );

		if ( accept ) {
			accept.addEventListener( 'click', function () {
				setStoredConsent( 'granted' );
				hide();
				consentMode( true );
				document.dispatchEvent( new Event( 'rfConsentGranted' ) );
			} );
		}
		if ( decline ) {
			decline.addEventListener( 'click', function () {
				var was = getStoredConsent();
				setStoredConsent( 'declined' );
				hide();
				consentMode( false );
				if ( was === 'granted' ) {
					window.location.reload();
				}
			} );
		}
	} );
} )();
