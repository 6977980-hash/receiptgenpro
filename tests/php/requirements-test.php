<?php
/**
 * Checks that every requirement check can pass on the template it points
 * to (audit of 20 Oct 2026, Phase 3, #32: the restaurant and payment
 * receipts had no field for "Place of the expense"), and that the widget's
 * auto-height script matches the one widget-builder.js builds.
 * Runs without WordPress. Run: php tests/php/requirements-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'RF_BRAND_NAME', 'ReceiptGenPro' );

function add_action() {}
function add_filter() {}
function remove_action() {}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function wp_json_encode( $data, $options = 0 ) {
	return json_encode( $data, $options );
}

$theme = dirname( __DIR__, 2 ) . '/wp-content/themes/receiptgenpro';
foreach ( array( 'category-data', 'requirements', 'feature-pages' ) as $file ) {
	require $theme . '/inc/' . $file . '.php';
}

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

/** Extra field ids of a template's form. */
function rf_test_extra_ids( $slug ) {
	$cat = rf_get_category_data( $slug );
	return $cat ? array_map(
		function ( $f ) {
			return $f['id'];
		},
		$cat['form']['extra'] ?? array()
	) : array();
}

/**
 * Required x: fields of a purpose that a template cannot fill: for an
 * "any of" check at least one field must exist, for an 'all' check every one.
 */
function rf_test_missing_fields( $purpose, $slug ) {
	$ids     = rf_test_extra_ids( $slug );
	$missing = array();
	foreach ( $purpose['checks'] as $c ) {
		if ( ! empty( $c['optional'] ) || ! empty( $c['manual'] ) || empty( $c['field'] ) ) {
			continue;
		}
		$x = array();
		foreach ( (array) $c['field'] as $f ) {
			if ( 0 === strpos( $f, 'x:' ) ) {
				$x[] = substr( $f, 2 );
			}
		}
		if ( ! $x || count( $x ) < count( (array) $c['field'] ) && empty( $c['all'] ) ) {
			continue; // not only extra fields, or a core field also counts
		}
		$have = array_intersect( $x, $ids );
		if ( ! empty( $c['all'] ) ? count( $have ) < count( $x ) : ! $have ) {
			$missing[] = $c['label'];
		}
	}
	return $missing;
}

foreach ( rf_receipt_purposes() as $key => $purpose ) {
	check( null !== rf_get_category_data( $purpose['template'] ), "purpose $key points to a missing template {$purpose['template']}" );
	foreach ( rf_test_missing_fields( $purpose, $purpose['template'] ) as $label ) {
		check( false, "purpose $key: \"$label\" has no field on its template {$purpose['template']}" );
	}
}

// Every template that selects a purpose by default can pass its checks.
foreach ( rf_receipt_categories() as $slug => $cat ) {
	if ( empty( $cat['purpose'] ) ) {
		continue;
	}
	$purpose = rf_get_receipt_purpose( $cat['purpose'] );
	check( null !== $purpose, "/$slug/ selects an unknown purpose {$cat['purpose']}" );
	if ( $purpose ) {
		foreach ( rf_test_missing_fields( $purpose, $slug ) as $label ) {
			check( false, "/$slug/ ({$cat['purpose']}): \"$label\" has no field" );
		}
	}
}

// The PHP and JS widget scripts are the same.
$js = shell_exec( 'node -e ' . escapeshellarg( 'process.stdout.write(require(' . json_encode( $theme . '/assets/js/widget-builder.js' ) . ').heightScript("https://example.com/"))' ) );
if ( null !== $js && '' !== $js ) {
	check( rf_widget_height_script( 'https://example.com/' ) === $js, "widget height script differs from widget-builder.js:\n" . rf_widget_height_script( 'https://example.com/' ) . "\n$js" );
}

if ( $failures ) {
	echo "$failures check(s) failed.\n";
	exit( 1 );
}
echo "Requirement checks OK.\n";
