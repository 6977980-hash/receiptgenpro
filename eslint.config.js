// ESLint for the theme's browser scripts (audit 20 Oct, #69): only rules
// that catch real bugs (an undefined name, a duplicate key, dead code).
// CI runs it; locally: npx eslint@9.39.5 wp-content/themes/receiptgenpro/assets/js/
'use strict';

const browser = [
	'window', 'document', 'navigator', 'location', 'history', 'localStorage', 'sessionStorage',
	'console', 'setTimeout', 'clearTimeout', 'requestAnimationFrame', 'getComputedStyle', 'matchMedia',
	'Event', 'CustomEvent', 'MouseEvent', 'Image', 'Blob', 'FileReader', 'URL', 'HTMLElement',
	'MutationObserver', 'ResizeObserver', 'TextDecoder', 'TextEncoder', 'atob', 'btoa', 'Intl',
	'Promise', 'Map', 'Set', 'Uint8Array', 'parent', 'module'
];

module.exports = [
	{
		files: [ 'wp-content/themes/receiptgenpro/assets/js/**/*.js' ],
		languageOptions: {
			ecmaVersion: 2018,
			sourceType: 'script',
			globals: Object.fromEntries( browser.map( ( g ) => [ g, 'readonly' ] ) )
		},
		rules: {
			'no-undef': 'error',
			'no-redeclare': 'error',
			'no-dupe-keys': 'error',
			'no-duplicate-case': 'error',
			'no-unreachable': 'error',
			'no-unused-vars': [ 'error', { caughtErrors: 'none' } ]
		}
	}
];
