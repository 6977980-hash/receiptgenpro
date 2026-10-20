<?php
/**
 * Deploy helper checks (audit of 20 Oct 2026, Phase 4: #48, #56, #57):
 * staged copy and swap, removal of deleted files and empty folders, files
 * added by hand kept, roll back, empty repo refused, unsafe paths and
 * symlinks skipped, copy order, GitHub signature.
 * Runs without WordPress, on two temp folders. Run: php tests/php/deploy-test.php
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

$tmp = sys_get_temp_dir() . '/rgp-deploy-test-' . getmypid();
define( 'RGP_DEPLOY_REPO_DIR', $tmp . '/repo' );
define( 'WP_CONTENT_DIR', $tmp . '/live' );

$GLOBALS['rgp_options'] = array();
$GLOBALS['rgp_actions'] = array();
function add_action() {}
function add_filter() {}
function register_activation_hook() {}
function register_deactivation_hook() {}
function register_uninstall_hook() {}
function do_action( $hook ) {
	$GLOBALS['rgp_actions'][] = $hook;
}
function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['rgp_options'] ) ? $GLOBALS['rgp_options'][ $name ] : $default;
}
function update_option( $name, $value ) {
	$GLOBALS['rgp_options'][ $name ] = $value;
	return true;
}
function wp_cache_delete() {}
function wp_clean_themes_cache() {}
function wp_clean_plugins_cache() {}
function wp_normalize_path( $p ) {
	return str_replace( '\\', '/', $p );
}
function untrailingslashit( $p ) {
	return rtrim( $p, '/\\' );
}
function wp_mkdir_p( $dir ) {
	return is_dir( $dir ) || @mkdir( $dir, 0777, true );
}
function wp_generate_password( $len ) {
	return substr( md5( uniqid( '', true ) ), 0, $len );
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, $args );
}
class WP_Error {
	public $code;
	public $message;
	public function __construct( $code, $message ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() {
		return $this->message;
	}
}
function is_wp_error( $x ) {
	return $x instanceof WP_Error;
}
/** The lock: INSERT IGNORE / DELETE on the options table. */
class RGP_Test_DB {
	public $options = 'wp_options';
	private $lock   = false;
	public function prepare( $sql ) {
		return $sql;
	}
	public function query( $sql ) {
		if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
			if ( $this->lock ) {
				return 0;
			}
			$this->lock = true;
			return 1;
		}
		return 0;
	}
	public function delete() {
		$this->lock = false;
	}
}
$GLOBALS['wpdb'] = new RGP_Test_DB();

require dirname( __DIR__, 2 ) . '/rgp-deploy-helper.php';

$failures = 0;
function check( $ok, $message ) {
	global $failures;
	if ( ! $ok ) {
		$failures++;
		echo "FAIL: $message\n";
	}
}

/** Writes a file and dates it 2 minutes back, past the 60 s settle wait. */
function put( $path, $text ) {
	wp_mkdir_p( dirname( $path ) );
	file_put_contents( $path, $text );
	touch( $path, time() - 120 );
}
function rm_rf( $dir ) {
	if ( is_link( $dir ) || is_file( $dir ) ) {
		unlink( $dir );
		return;
	}
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $e ) {
		if ( '.' !== $e && '..' !== $e ) {
			rm_rf( $dir . '/' . $e );
		}
	}
	rmdir( $dir );
}

$repo  = RGP_DEPLOY_REPO_DIR . '/wp-content/themes/t';
$live  = WP_CONTENT_DIR . '/themes/t';
$prev  = WP_CONTENT_DIR . '/themes/.rgp-prev-t';
$stage = WP_CONTENT_DIR . '/themes/.rgp-stage-t';
rm_rf( $tmp );
mkdir( WP_CONTENT_DIR . '/themes', 0777, true );

// 1. Empty repo: refused, nothing deleted.
mkdir( RGP_DEPLOY_REPO_DIR . '/wp-content/themes', 0777, true );
$r = RGP_Deploy_Helper::sync( 'manual', true );
check( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'no theme or plugin files' ), 'an empty repo is refused even with "allow removal"' );

// 2. First deploy: the folder is built in staging and moved into place.
put( $repo . '/functions.php', 'f1' );
put( $repo . '/index.php', 'i1' );
put( $repo . '/inc/a.php', 'a1' );
put( $repo . '/inc/old/gone.php', 'g1' );
put( $repo . '/README.md', 'not published' );
for ( $n = 0; $n < 6; $n++ ) {
	put( $repo . '/inc/extra' . $n . '.php', 'x' ); // so one removal stays under 20 %
}
$r = RGP_Deploy_Helper::sync( 'manual' );
check( is_array( $r ) && empty( $r['errors'] ), 'first deploy has no errors: ' . json_encode( $r ) );
check( 'a1' === @file_get_contents( $live . '/inc/a.php' ), 'first deploy copies inc/a.php' );
check( ! file_exists( $live . '/README.md' ), 'Markdown notes are not published' );
check( array( 'themes/t' ) === $r['staged'], 'first deploy is staged' );
check( ! file_exists( $stage ), 'no staging folder is left behind' );
check( in_array( 'litespeed_purge_all', $GLOBALS['rgp_actions'], true ), 'the page cache is purged' );

// 3. Second deploy: a change, a removal (and its emptied folder), a file
// added on the server by hand is kept, the old version stays for roll back.
put( $live . '/hand-made.txt', 'mine' );
put( $repo . '/inc/a.php', 'a2' );
unlink( $repo . '/inc/old/gone.php' );
rmdir( $repo . '/inc/old' );
$r = RGP_Deploy_Helper::sync( 'manual' );
check( is_array( $r ) && empty( $r['errors'] ), 'second deploy has no errors: ' . json_encode( $r ) );
check( 'a2' === @file_get_contents( $live . '/inc/a.php' ), 'second deploy updates inc/a.php' );
check( ! file_exists( $live . '/inc/old' ), 'a deleted file and its emptied folder are gone' );
check( 'mine' === @file_get_contents( $live . '/hand-made.txt' ), 'a file the helper never deployed is kept' );
check( 'a1' === @file_get_contents( $prev . '/inc/a.php' ), 'the previous version is kept for roll back' );
check( 1 === $r['removed'], 'one file counted as removed' );

// A run that changes nothing keeps the roll back point.
RGP_Deploy_Helper::sync( 'webhook' );
check( array( 'themes/t' ) === get_option( RGP_Deploy_Helper::OPT_STATE )['staged'], 'a no-change run keeps the roll back point' );

// 4. Roll back and forward again.
check( array( 'themes/t' ) === RGP_Deploy_Helper::rollback(), 'roll back swaps the folder' );
check( 'a1' === @file_get_contents( $live . '/inc/a.php' ), 'roll back restores the old file' );
check( 'g1' === @file_get_contents( $live . '/inc/old/gone.php' ), 'roll back restores a removed file' );
RGP_Deploy_Helper::rollback();
check( 'a2' === @file_get_contents( $live . '/inc/a.php' ), 'a second roll back swaps forward again' );

// 5. Symlinks in the repo are not deployed.
if ( function_exists( 'symlink' ) && @symlink( '/etc/hostname', $repo . '/inc/link.php' ) ) {
	put( $repo . '/inc/a.php', 'a3' );
	RGP_Deploy_Helper::sync( 'manual' );
	check( ! file_exists( $live . '/inc/link.php' ) && ! is_link( $live . '/inc/link.php' ), 'a symlink in the repo is skipped' );
	check( 'a3' === @file_get_contents( $live . '/inc/a.php' ), 'the deploy with a symlink still runs' );
	unlink( $repo . '/inc/link.php' );
}

// 6. Pure helpers.
check( ! RGP_Deploy_Helper::safe_rel( 'themes/t/../../wp-config.php' ), '".." is rejected' );
check( ! RGP_Deploy_Helper::safe_rel( 'themes//t.php' ), 'an empty part is rejected' );
check( ! RGP_Deploy_Helper::safe_rel( 'themes\\t.php' ), 'a backslash is rejected' );
check( RGP_Deploy_Helper::safe_rel( 'themes/t/inc/a.php' ), 'a normal path is accepted' );

$order = array( 'themes/t/single.php', 'themes/t/functions.php', 'themes/t/inc/z.php', 'themes/t/assets/x.css', 'plugins/p/p.php' );
usort( $order, array( 'RGP_Deploy_Helper', 'copy_order' ) );
check(
	array( 'plugins/p/p.php', 'themes/t/assets/x.css', 'themes/t/inc/z.php', 'themes/t/functions.php', 'themes/t/single.php' ) === $order,
	'copy order: sub-folders, then functions.php, then templates: ' . implode( ', ', $order )
);

$body = '{"ref":"refs/heads/main"}';
$sig  = 'sha256=' . hash_hmac( 'sha256', $body, 'k3y' );
check( RGP_Deploy_Helper::valid_signature( 'k3y', $body, $sig ), 'a correct GitHub signature passes' );
check( ! RGP_Deploy_Helper::valid_signature( 'other', $body, $sig ), 'a signature made with another secret fails' );
check( ! RGP_Deploy_Helper::valid_signature( 'k3y', $body . ' ', $sig ), 'a changed body fails' );
check( ! RGP_Deploy_Helper::valid_signature( 'k3y', $body, hash_hmac( 'sha256', $body, 'k3y' ) ), 'a signature without "sha256=" fails' );

$sum = RGP_Deploy_Helper::summary( get_option( RGP_Deploy_Helper::OPT_STATE ) );
check( ! isset( $sum['files'] ), 'the webhook summary has no file list' );

rm_rf( $tmp );

if ( $failures ) {
	echo "$failures check(s) failed\n";
	exit( 1 );
}
echo "Deploy helper: all checks passed\n";
