<?php
/**
 * Plugin Name: RGP Deploy Helper
 * Description: Copies the theme and plugin folders from this Git checkout (deployed by Hostinger) into the live wp-content folder whenever a new commit arrives. Deleting this plugin only stops deployment; everything already deployed stays as it is.
 * Version:     1.2.0
 * Author:      receiptgenpro
 * License:     GPL-2.0-or-later
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

final class RGP_Deploy_Helper {

	const OPT_STATE    = 'rgp_deploy_state';
	const OPT_SECRET   = 'rgp_deploy_secret';
	const OPT_REPO_DIR = 'rgp_deploy_repo_dir';
	const LOCK         = 'rgp_deploy_lock';
	const THROTTLE     = 'rgp_deploy_checked';
	const SELF_SLUG    = 'rgp-deploy-helper';

	/** A lock older than this belongs to a run that died; it is cleared. */
	const LOCK_TTL = 600;

	/** Wait this long after the checkout last changed before copying it. */
	const SETTLE_SECONDS = 60;

	/** Refuse a run that would delete more than this share of managed files. */
	const MAX_REMOVE_SHARE = 0.2;

	/** Top-level folders inside the repo's wp-content that get copied. */
	const SECTIONS = array( 'themes', 'plugins', 'mu-plugins' );

	/** WP-Cron event that checks the checkout once a minute. */
	const CRON_HOOK = 'rgp_deploy_check';

	/** Last time a webhook still used ?key= (shown on the admin page). */
	const OPT_LEGACY_KEY = 'rgp_deploy_legacy_key';

	/** Staging and previous copies sit next to the live folder; WordPress skips dot folders. */
	const STAGE_PREFIX = '.rgp-stage-';
	const PREV_PREFIX  = '.rgp-prev-';

	public static function init() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

		// 1.2.0 (audit 20 Oct, #48): no check or copy inside a visitor's
		// page request. Deploys run from WP-Cron (its own request), the
		// webhook or an admin screen.
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_check' ) );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_check' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_rgp_deploy_sync', array( __CLASS__, 'handle_admin_sync' ) );
		add_action( 'admin_post_rgp_deploy_rollback', array( __CLASS__, 'handle_admin_rollback' ) );
		add_action( 'admin_post_rgp_deploy_settings', array( __CLASS__, 'handle_admin_settings' ) );
	}

	public static function activate() {
		if ( ! get_option( self::OPT_SECRET ) ) {
			update_option( self::OPT_SECRET, wp_generate_password( 32, false ), false );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function cron_schedules( $schedules ) {
		$schedules['rgp_minute'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'Every minute (RGP Deploy Helper)',
		);
		return $schedules;
	}

	/** The activation hook does not run on an update, so the event is checked here (the cron array is autoloaded). */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'rgp_minute', self::CRON_HOOK );
		}
	}

	public static function cron_check() {
		self::maybe_auto_sync( 'cron' );
	}

	/** Admin screens check too (once a minute), for sites where WP-Cron is off. */
	public static function admin_check() {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || get_transient( self::THROTTLE ) ) {
			return;
		}
		set_transient( self::THROTTLE, 1, MINUTE_IN_SECONDS );
		self::maybe_auto_sync( 'admin' );
	}

	/** Only the plugin's own bookkeeping is removed; deployed files are left alone. */
	public static function uninstall() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_SECRET );
		delete_option( self::OPT_REPO_DIR );
		delete_option( self::OPT_LEGACY_KEY );
		delete_option( self::LOCK );
		delete_transient( self::LOCK ); // lock used by 1.1.1 and older
		delete_transient( self::THROTTLE );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/* ------------------------------------------------------------------ */
	/* Paths                                                              */
	/* ------------------------------------------------------------------ */

	public static function repo_dir() {
		if ( defined( 'RGP_DEPLOY_REPO_DIR' ) ) {
			$dir = RGP_DEPLOY_REPO_DIR;
		} else {
			$dir = get_option( self::OPT_REPO_DIR, '' );
			if ( '' === $dir ) {
				// Hostinger deploys the repo straight into this plugin's folder.
				$dir = __DIR__;
			}
		}
		return untrailingslashit( wp_normalize_path( $dir ) );
	}

	private static function source_root() {
		return self::repo_dir() . '/wp-content';
	}

	private static function target_root() {
		return untrailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );
	}

	/* ------------------------------------------------------------------ */
	/* Change detection                                                   */
	/* ------------------------------------------------------------------ */

	/** Commit hash of the checkout, or a fingerprint of the files when .git is missing. */
	public static function current_revision() {
		$repo = self::repo_dir();
		$head = $repo . '/.git/HEAD';

		if ( is_readable( $head ) ) {
			$ref = trim( (string) file_get_contents( $head ) );
			if ( 0 === strpos( $ref, 'ref:' ) ) {
				$ref_name = trim( substr( $ref, 4 ) );
				$ref_file = $repo . '/.git/' . $ref_name;
				if ( is_readable( $ref_file ) ) {
					return trim( (string) file_get_contents( $ref_file ) );
				}
				$packed = $repo . '/.git/packed-refs';
				if ( is_readable( $packed ) ) {
					foreach ( file( $packed, FILE_IGNORE_NEW_LINES ) as $line ) {
						$parts = explode( ' ', $line, 2 );
						if ( 2 === count( $parts ) && $parts[1] === $ref_name ) {
							return $parts[0];
						}
					}
				}
			} elseif ( preg_match( '/^[0-9a-f]{40,64}$/', $ref ) ) {
				return $ref;
			}
		}

		$files = self::list_source_files();
		if ( empty( $files ) ) {
			return '';
		}
		$sig = '';
		foreach ( $files as $rel => $abs ) {
			$sig .= $rel . ':' . filesize( $abs ) . ':' . filemtime( $abs ) . "\n";
		}
		return 'files-' . md5( $sig );
	}

	/** @param string $trigger cron or admin. */
	public static function maybe_auto_sync( $trigger = 'cron' ) {
		if ( ! is_dir( self::source_root() ) ) {
			return;
		}
		$state = self::state();
		$rev   = self::current_revision();
		if ( '' !== $rev && $rev !== $state['revision'] ) {
			self::sync( $trigger );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Sync                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * @return array<string,string> relative path => absolute source path.
	 * Symlinks, paths with "..", and anything that resolves outside the
	 * repo's wp-content are never listed (#56).
	 */
	private static function list_source_files() {
		$root  = self::source_root();
		$real  = wp_normalize_path( (string) realpath( $root ) );
		$files = array();
		if ( '' === $real ) {
			return $files;
		}

		foreach ( self::SECTIONS as $section ) {
			$dir = $root . '/' . $section;
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( scandir( $dir ) as $entry ) {
				if ( '.' === $entry[0] || ! is_dir( $dir . '/' . $entry ) || is_link( $dir . '/' . $entry ) ) {
					continue; // Only whole (real) theme/plugin folders are deployed.
				}
				if ( 'plugins' === $section && self::SELF_SLUG === $entry ) {
					continue; // Never overwrite the running deploy helper.
				}
				$it = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $dir . '/' . $entry, FilesystemIterator::SKIP_DOTS )
				);
				foreach ( $it as $file ) {
					if ( $file->isLink() || ! $file->isFile() ) {
						continue;
					}
					$abs = wp_normalize_path( $file->getPathname() );
					$rel = substr( $abs, strlen( $root ) + 1 );
					if ( ! self::safe_rel( $rel ) || 0 !== strpos( wp_normalize_path( (string) realpath( $abs ) ), $real . '/' ) ) {
						continue;
					}
					// Dotfiles/dot-folders (.git, .github, .DS_Store…), node_modules and
					// Markdown notes (CHANGELOG.md, README.md) are never published.
					if ( preg_match( '#(^|/)(\.[^/]*|node_modules)(/|$)#', $rel ) || preg_match( '/\.md$/i', $rel ) ) {
						continue;
					}
					$files[ $rel ] = $abs;
				}
			}
		}
		uksort( $files, array( __CLASS__, 'copy_order' ) );
		return $files;
	}

	/**
	 * Copy order inside each theme/plugin folder, for the in-place fallback
	 * (#48): files in sub-folders (inc/, assets/…) first, then functions.php,
	 * then the other top-level files (templates). A new inc/ file is on disk
	 * before the functions.php that loads it, and that functions.php before
	 * a template that calls it.
	 */
	public static function copy_order( $a, $b ) {
		$key = function ( $rel ) {
			$parts = explode( '/', $rel, 3 );
			$unit  = $parts[0] . '/' . ( $parts[1] ?? '' );
			$inner = $parts[2] ?? '';
			if ( false !== strpos( $inner, '/' ) ) {
				$rank = 0;
			} elseif ( 'functions.php' === $inner ) {
				$rank = 1;
			} else {
				$rank = 2;
			}
			return array( $unit, $rank, $rel );
		};
		return $key( $a ) <=> $key( $b );
	}

	/** A relative path with no "..", ".", empty or backslash parts. */
	public static function safe_rel( $rel ) {
		if ( '' === $rel || false !== strpos( $rel, '\\' ) || false !== strpos( $rel, "\0" ) ) {
			return false;
		}
		foreach ( explode( '/', $rel ) as $part ) {
			if ( '' === $part || '.' === $part || '..' === $part ) {
				return false;
			}
		}
		return true;
	}

	/** "themes/receiptgenpro" for "themes/receiptgenpro/inc/x.php". */
	public static function unit_of( $rel ) {
		$parts = explode( '/', $rel );
		return count( $parts ) > 2 ? $parts[0] . '/' . $parts[1] : '';
	}

	/**
	 * Atomic lock: INSERT IGNORE succeeds for exactly one request, unlike a
	 * transient get/set, where two requests can both see "no lock".
	 */
	private static function acquire_lock() {
		global $wpdb;
		// A run that crashed never released its lock; clear it after LOCK_TTL.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d", self::LOCK, time() - self::LOCK_TTL ) );
		$got = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK, (string) time() ) );
		wp_cache_delete( self::LOCK, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		return 1 === (int) $got;
	}

	private static function release_lock() {
		global $wpdb;
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK ) );
		wp_cache_delete( self::LOCK, 'options' );
	}

	/**
	 * Why the checkout must not be copied yet, or '' when it is safe.
	 * Hostinger's git pull writes files one by one; while it runs (index.lock)
	 * or just after it changed HEAD, the folder can be half old, half new.
	 *
	 * @param array<string,string> $files Source files, for checkouts without .git.
	 */
	private static function checkout_busy( array $files ) {
		$git = self::repo_dir() . '/.git';
		if ( file_exists( $git . '/index.lock' ) ) {
			return 'Git is still updating the repo folder (.git/index.lock exists).';
		}
		$newest = 0;
		if ( is_dir( $git ) ) {
			$paths = array( $git . '/HEAD', $git . '/index', $git . '/packed-refs' );
			$head  = is_readable( $git . '/HEAD' ) ? trim( (string) file_get_contents( $git . '/HEAD' ) ) : '';
			if ( 0 === strpos( $head, 'ref:' ) ) {
				$paths[] = $git . '/' . trim( substr( $head, 4 ) );
			}
		} else {
			$paths = array_values( $files );
		}
		foreach ( $paths as $path ) {
			$mtime = @filemtime( $path );
			if ( $mtime && $mtime > $newest ) {
				$newest = $mtime;
			}
		}
		$age = time() - $newest;
		if ( $newest && $age < self::SETTLE_SECONDS ) {
			return sprintf( 'The repo folder changed %d seconds ago; waiting until it is %d seconds old.', max( 0, $age ), self::SETTLE_SECONDS );
		}
		return '';
	}

	/**
	 * Why this run would delete too much, or '' when it is fine. A checkout
	 * that is missing a whole theme/plugin folder, or a big share of its
	 * files, is far more likely broken than a real change.
	 *
	 * @param string[] $old Files deployed last time.
	 * @param string[] $new Files in the repo now.
	 */
	public static function removal_problem( array $old, array $new ) {
		if ( empty( $old ) ) {
			return '';
		}
		$folder = function ( $rel ) {
			$parts = explode( '/', $rel );
			return count( $parts ) > 2 ? $parts[0] . '/' . $parts[1] : '';
		};
		$now = array_flip( array_filter( array_map( $folder, $new ) ) );
		foreach ( array_unique( array_filter( array_map( $folder, $old ) ) ) as $dir ) {
			if ( ! isset( $now[ $dir ] ) ) {
				return 'The whole folder ' . $dir . ' is missing from the repo.';
			}
		}
		$remove = count( array_diff( $old, $new ) );
		if ( $remove > count( $old ) * self::MAX_REMOVE_SHARE ) {
			return sprintf( '%d of %d deployed files would be deleted.', $remove, count( $old ) );
		}
		return '';
	}

	/** Records why a run did not happen; the revision stays, so it is retried. */
	private static function refuse( array $state, $trigger, $reason ) {
		$state['time']    = time();
		$state['trigger'] = $trigger;
		$state['copied']  = 0;
		$state['removed'] = 0;
		$state['errors']  = array( 'Not deployed: ' . $reason );
		update_option( self::OPT_STATE, $state, false );
		return new WP_Error( 'rgp_refused', $reason );
	}

	/**
	 * @param string $trigger       auto, webhook or manual.
	 * @param bool   $allow_removal Skip the mass-delete check (admin's choice).
	 */
	public static function sync( $trigger, $allow_removal = false ) {
		if ( ! self::acquire_lock() ) {
			return new WP_Error( 'rgp_locked', 'A deployment is already running.' );
		}
		try {
			return self::run_sync( $trigger, $allow_removal );
		} finally {
			self::release_lock();
		}
	}

	private static function run_sync( $trigger, $allow_removal ) {
		$source = self::source_root();
		$target = self::target_root();
		$state  = self::state();

		if ( ! is_dir( $source ) ) {
			return self::refuse( $state, $trigger, 'Repo folder not found: ' . $source );
		}
		$src_real = wp_normalize_path( (string) realpath( $source ) );
		$dst_real = wp_normalize_path( (string) realpath( $target ) );
		// The repo normally lives inside wp-content/plugins/; only the same
		// folder (copying files onto themselves) is refused.
		if ( '' === $dst_real || $src_real === $dst_real ) {
			return self::refuse( $state, $trigger, 'The repo folder is the same as the live wp-content folder.' );
		}

		$rev   = self::current_revision();
		$files = self::list_source_files();
		// An empty checkout is never a reason to delete everything (#56).
		if ( empty( $files ) ) {
			return self::refuse( $state, $trigger, 'The repo folder has no theme or plugin files.' );
		}
		$busy = self::checkout_busy( $files );
		if ( '' !== $busy ) {
			return self::refuse( $state, $trigger, $busy );
		}
		if ( ! $allow_removal ) {
			$problem = self::removal_problem( $state['files'], array_keys( $files ) );
			if ( '' !== $problem ) {
				return self::refuse( $state, $trigger, $problem . ' Check the repo folder, then use "Deploy now" with "Allow removing many files" if this is intended.' );
			}
		}

		// One theme/plugin folder at a time.
		$units = array();
		foreach ( $files as $rel => $abs ) {
			$units[ self::unit_of( $rel ) ][ $rel ] = $abs;
		}
		$old_by_unit = array();
		foreach ( $state['files'] as $rel ) {
			$old_by_unit[ self::unit_of( $rel ) ][] = $rel;
		}

		$copied  = 0;
		$removed = 0;
		$errors  = array();
		$staged  = array();
		$notes   = array();
		foreach ( $units as $unit => $unit_files ) {
			$old    = $old_by_unit[ $unit ] ?? array();
			$result = self::deploy_unit( $unit, $unit_files, $old );
			$copied  += $result['copied'];
			$removed += $result['removed'];
			$errors   = array_merge( $errors, $result['errors'] );
			if ( $result['staged'] ) {
				$staged[] = $unit;
			}
			if ( $result['note'] ) {
				$notes[] = $result['note'];
			}
		}

		// The checkout changed while copying: copy again on the next run.
		if ( self::current_revision() !== $rev || '' !== self::checkout_busy( $files ) ) {
			$errors[] = 'The repo folder changed during the deployment; it will run again.';
		}

		// Whole theme/plugin folders deployed before but gone from the repo
		// (only reached with "Allow removing many files"): their deployed
		// files are removed, files this plugin never deployed stay.
		if ( empty( $errors ) ) {
			foreach ( array_diff( array_keys( $old_by_unit ), array_keys( $units ) ) as $unit ) {
				if ( '' !== $unit ) {
					$removed += self::remove_files( $old_by_unit[ $unit ] );
				}
			}
		}

		// The revision only counts as deployed when everything went through;
		// otherwise it stays, and the next check runs the deployment again.
		$new_state = array(
			'revision'   => empty( $errors ) ? $rev : $state['revision'],
			'files'      => empty( $errors ) ? array_keys( $files ) : array_values( array_unique( array_merge( $state['files'], array_keys( $files ) ) ) ),
			// A run that changed nothing keeps the last roll back point.
			'prev_files' => ( $copied || $removed ) ? $state['files'] : $state['prev_files'],
			'staged'     => ( $copied || $removed ) ? $staged : $state['staged'],
			'notes'      => $notes,
			'time'       => time(),
			'trigger'    => $trigger,
			'copied'     => $copied,
			'removed'    => $removed,
			'errors'     => $errors,
		);
		update_option( self::OPT_STATE, $new_state, false );

		if ( $copied || $removed ) {
			self::after_change();
		}
		if ( empty( $errors ) ) {
			/** Fires after a revision was fully deployed. */
			do_action( 'rgp_deploy_synced', $new_state );
		}

		return $new_state;
	}

	private static function after_change() {
		if ( function_exists( 'opcache_reset' ) ) {
			@opcache_reset();
		}
		wp_clean_themes_cache();
		wp_clean_plugins_cache( false );

		// Clear the LiteSpeed page cache so visitors get the new version.
		do_action( 'litespeed_purge_all' );
	}

	/**
	 * Deploys one theme/plugin folder (#48). The new version is built in a
	 * staging folder next to the live one (new files from the repo, plus
	 * any live files this plugin never deployed), then swapped in with two
	 * rename() calls, so visitors never run a half-copied theme. The old
	 * folder stays as .rgp-prev-<name> for "Roll back". If staging is not
	 * possible, files are copied in place in copy_order().
	 *
	 * @param string               $unit  e.g. themes/receiptgenpro.
	 * @param array<string,string> $files rel => abs source path, this unit only.
	 * @param string[]             $old   rel paths deployed last time, this unit only.
	 * @return array{copied:int,removed:int,errors:string[],staged:bool,note:string}
	 */
	private static function deploy_unit( $unit, array $files, array $old ) {
		$target = self::target_root();
		$live   = $target . '/' . $unit;
		$out    = array( 'copied' => 0, 'removed' => 0, 'errors' => array(), 'staged' => false, 'note' => '' );

		$changed = array();
		foreach ( $files as $rel => $abs ) {
			$dest = $target . '/' . $rel;
			if ( ! ( is_file( $dest ) && filesize( $dest ) === filesize( $abs ) && md5_file( $dest ) === md5_file( $abs ) ) ) {
				$changed[] = $rel;
			}
		}
		$gone = array();
		foreach ( array_diff( $old, array_keys( $files ) ) as $rel ) {
			if ( is_file( $target . '/' . $rel ) ) {
				$gone[] = $rel;
			}
		}
		if ( ! $changed && ! $gone ) {
			return $out;
		}

		$staged = is_link( $live ) ? 'The live folder is a symlink' : self::stage_and_swap( $unit, $files, $old );
		if ( true === $staged ) {
			$out['copied']  = count( $changed );
			$out['removed'] = count( $gone );
			$out['staged']  = true;
			return $out;
		}
		$out['note'] = $unit . ': copied in place (' . $staged . ').';

		// Fallback: in place, in copy_order() (inc/ → functions.php → templates).
		foreach ( $changed as $rel ) {
			$dest = $target . '/' . $rel;
			if ( ! wp_mkdir_p( dirname( $dest ) ) ) {
				$out['errors'][] = 'Cannot create folder for ' . $rel;
				continue;
			}
			$tmp = $dest . '.rgp-tmp-' . wp_generate_password( 8, false );
			if ( @copy( $files[ $rel ], $tmp ) && @rename( $tmp, $dest ) ) {
				$out['copied']++;
			} else {
				@unlink( $tmp );
				$out['errors'][] = 'Cannot write ' . $rel;
			}
		}
		if ( empty( $out['errors'] ) ) {
			$out['removed'] = self::remove_files( $gone );
		}
		return $out;
	}

	/** @return true|string true when swapped in, else why not. */
	private static function stage_and_swap( $unit, array $files, array $old ) {
		$target = self::target_root();
		$live   = $target . '/' . $unit;
		$parent = dirname( $live );
		$name   = basename( $live );
		$stage  = $parent . '/' . self::STAGE_PREFIX . $name;
		$prev   = $parent . '/' . self::PREV_PREFIX . $name;

		if ( ! wp_mkdir_p( $parent ) ) {
			return 'Cannot create ' . $parent;
		}
		self::remove_tree( $stage ); // left over from a run that died
		if ( ! wp_mkdir_p( $stage ) ) {
			return 'Cannot create the staging folder';
		}

		// Live files this plugin never deployed (an .htaccess, a file added
		// by hand) are carried over. Files it deployed that are gone from
		// the repo are left out, which is how they are removed.
		$managed = array_flip( $old );
		if ( is_dir( $live ) ) {
			$it = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $live, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $it as $file ) {
				$inner = substr( wp_normalize_path( $file->getPathname() ), strlen( $live ) + 1 );
				$rel   = $unit . '/' . $inner;
				if ( $file->isLink() ) {
					self::remove_tree( $stage );
					return 'A symlink in the live folder (' . $rel . ')';
				}
				if ( $file->isDir() || isset( $files[ $rel ] ) || isset( $managed[ $rel ] ) ) {
					continue;
				}
				if ( ! wp_mkdir_p( dirname( $stage . '/' . $inner ) ) || ! @copy( $file->getPathname(), $stage . '/' . $inner ) ) {
					self::remove_tree( $stage );
					return 'Cannot copy ' . $rel . ' into the staging folder';
				}
			}
		}
		foreach ( $files as $rel => $abs ) {
			$inner = substr( $rel, strlen( $unit ) + 1 );
			if ( ! wp_mkdir_p( dirname( $stage . '/' . $inner ) ) || ! @copy( $abs, $stage . '/' . $inner ) ) {
				self::remove_tree( $stage );
				return 'Cannot copy ' . $rel . ' into the staging folder';
			}
		}

		if ( ! is_dir( $live ) ) {
			return @rename( $stage, $live ) ? true : 'Cannot move the staging folder into place';
		}
		self::remove_tree( $prev );
		if ( ! @rename( $live, $prev ) ) {
			self::remove_tree( $stage );
			return 'Cannot move the live folder aside';
		}
		if ( ! @rename( $stage, $live ) ) {
			@rename( $prev, $live ); // put the old one back
			self::remove_tree( $stage );
			return 'Cannot move the staging folder into place';
		}
		return true;
	}

	/**
	 * Deletes files this plugin deployed, then folders they leave empty
	 * (#56). Only plain files inside the live wp-content, never a symlink
	 * or a path with "..".
	 *
	 * @param string[] $rels
	 * @return int files removed.
	 */
	private static function remove_files( array $rels ) {
		$target  = self::target_root();
		$real    = wp_normalize_path( (string) realpath( $target ) );
		$removed = 0;
		$dirs    = array();
		foreach ( $rels as $rel ) {
			$dest = $target . '/' . $rel;
			if ( ! self::safe_rel( $rel ) || '' === self::unit_of( $rel ) || is_link( $dest ) || ! is_file( $dest ) ) {
				continue;
			}
			if ( 0 !== strpos( wp_normalize_path( (string) realpath( dirname( $dest ) ) ) . '/', $real . '/' ) ) {
				continue;
			}
			if ( @unlink( $dest ) ) {
				$removed++;
				$dirs[ dirname( $rel ) ] = true;
			}
		}
		// Deepest first; stop at the theme/plugin folder itself.
		foreach ( $dirs as $dir => $unused ) {
			while ( substr_count( $dir, '/' ) >= 2 ) {
				$abs = $target . '/' . $dir;
				if ( is_link( $abs ) || ! is_dir( $abs ) || count( (array) @scandir( $abs ) ) > 2 || ! @rmdir( $abs ) ) {
					break;
				}
				$dir = dirname( $dir );
			}
		}
		return $removed;
	}

	/** Removes a staging/previous folder. Refuses anything not named like one. */
	private static function remove_tree( $dir ) {
		$base = basename( $dir );
		if ( 0 !== strpos( $base, self::STAGE_PREFIX ) && 0 !== strpos( $base, self::PREV_PREFIX ) ) {
			return;
		}
		if ( is_link( $dir ) ) {
			@unlink( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $file ) {
			if ( $file->isDir() && ! $file->isLink() ) {
				@rmdir( $file->getPathname() );
			} else {
				@unlink( $file->getPathname() );
			}
		}
		@rmdir( $dir );
	}

	/**
	 * Swaps every folder of the last staged deploy with its .rgp-prev-
	 * copy. Running it again swaps back. The deployed revision stays, so
	 * only the next new commit deploys again.
	 *
	 * @return string[] what was rolled back.
	 */
	public static function rollback() {
		if ( ! self::acquire_lock() ) {
			return array();
		}
		$done = array();
		try {
			$state  = self::state();
			$target = self::target_root();
			foreach ( $state['staged'] as $unit ) {
				if ( ! self::safe_rel( $unit ) || '' === $unit ) {
					continue;
				}
				$live = $target . '/' . $unit;
				$prev = dirname( $live ) . '/' . self::PREV_PREFIX . basename( $live );
				$tmp  = dirname( $live ) . '/' . self::STAGE_PREFIX . basename( $live );
				if ( ! is_dir( $prev ) || is_link( $prev ) || ! is_dir( $live ) || is_link( $live ) ) {
					continue;
				}
				self::remove_tree( $tmp );
				if ( @rename( $live, $tmp ) ) {
					if ( @rename( $prev, $live ) ) {
						@rename( $tmp, $prev );
						$done[] = $unit;
					} else {
						@rename( $tmp, $live );
					}
				}
			}
			if ( $done ) {
				// Both versions' files count as deployed, so the next deploy
				// removes whatever the repo no longer has.
				$state['files']       = array_values( array_unique( array_merge( $state['files'], $state['prev_files'] ) ) );
				$state['rolled_back'] = time();
				update_option( self::OPT_STATE, $state, false );
				self::after_change();
			}
		} finally {
			self::release_lock();
		}
		return $done;
	}

	private static function state() {
		$state = get_option( self::OPT_STATE, array() );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'revision'    => '',
				'files'       => array(),
				'prev_files'  => array(),
				'staged'      => array(),
				'notes'       => array(),
				'rolled_back' => 0,
				'time'        => 0,
				'trigger'  => '',
				'copied'   => 0,
				'removed'  => 0,
				'errors'   => array(),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Webhook: POST /wp-json/rgp-deploy/v1/sync (#57)                    */
	/*  - a GitHub webhook whose Secret is the deploy key (signed with     */
	/*    X-Hub-Signature-256), or                                         */
	/*  - any caller sending the header X-RGP-Key: <key>.                  */
	/* ?key= still works for now (POST only) and shows a warning in admin. */
	/* ------------------------------------------------------------------ */

	public static function register_route() {
		register_rest_route(
			'rgp-deploy/v1',
			'/sync',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( __CLASS__, 'check_key' ),
				'callback'            => array( __CLASS__, 'handle_webhook' ),
			)
		);
	}

	public static function handle_webhook( WP_REST_Request $request ) {
		if ( 'ping' === $request->get_header( 'x_github_event' ) ) {
			return rest_ensure_response( array( 'ok' => true, 'message' => 'pong' ) );
		}
		$result = self::sync( 'webhook' );
		if ( is_wp_error( $result ) ) {
			// Usually Git is still writing; the once-a-minute check deploys it.
			return new WP_REST_Response(
				array(
					'deployed' => false,
					'reason'   => $result->get_error_message(),
					'retry'    => 'The once-a-minute check will deploy it.',
				),
				202
			);
		}
		return rest_ensure_response( self::summary( $result ) );
	}

	/** What a webhook caller gets back: no file list. */
	public static function summary( array $state ) {
		return array(
			'deployed' => empty( $state['errors'] ),
			'revision' => $state['revision'],
			'copied'   => (int) $state['copied'],
			'removed'  => (int) $state['removed'],
			'errors'   => array_values( $state['errors'] ),
		);
	}

	public static function check_key( WP_REST_Request $request ) {
		$secret = (string) get_option( self::OPT_SECRET, '' );
		if ( '' === $secret ) {
			return false;
		}
		$signature = (string) $request->get_header( 'x_hub_signature_256' );
		if ( '' !== $signature ) {
			return self::valid_signature( $secret, $request->get_body(), $signature );
		}
		$given = (string) $request->get_header( 'x_rgp_key' );
		if ( '' !== $given ) {
			return hash_equals( $secret, $given );
		}
		// Old webhook URL with ?key= (puts the key in server logs).
		$given = (string) $request->get_param( 'key' );
		if ( '' !== $given && hash_equals( $secret, $given ) ) {
			update_option( self::OPT_LEGACY_KEY, time(), false );
			return true;
		}
		return false;
	}

	/** GitHub's X-Hub-Signature-256: "sha256=" + HMAC-SHA256 of the raw body. */
	public static function valid_signature( $secret, $body, $header ) {
		if ( 0 !== strpos( $header, 'sha256=' ) ) {
			return false;
		}
		return hash_equals( 'sha256=' . hash_hmac( 'sha256', (string) $body, $secret ), $header );
	}

	/* ------------------------------------------------------------------ */
	/* Admin page: Tools > Deploy Helper                                  */
	/* ------------------------------------------------------------------ */

	public static function admin_menu() {
		add_management_page( 'Deploy Helper', 'Deploy Helper', 'manage_options', 'rgp-deploy', array( __CLASS__, 'render_page' ) );
	}

	public static function handle_admin_sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'rgp_deploy_sync' );
		self::sync( 'manual', ! empty( $_POST['allow_removal'] ) );
		wp_safe_redirect( admin_url( 'tools.php?page=rgp-deploy&synced=1' ) );
		exit;
	}

	public static function handle_admin_rollback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'rgp_deploy_rollback' );
		$done = self::rollback();
		wp_safe_redirect( admin_url( 'tools.php?page=rgp-deploy&rolledback=' . count( $done ) ) );
		exit;
	}

	public static function handle_admin_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'rgp_deploy_settings' );
		$dir = isset( $_POST['repo_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['repo_dir'] ) ) : '';
		if ( $dir !== (string) get_option( self::OPT_REPO_DIR, '' ) ) {
			// Another repo (#56): the files listed for the old one are not
			// this repo's, so nothing is deleted on their account, and the
			// new repo deploys on the next check.
			$state             = self::state();
			$state['revision'] = '';
			$state['files']    = array();
			$state['staged']   = array();
			update_option( self::OPT_STATE, $state, false );
		}
		update_option( self::OPT_REPO_DIR, $dir, false );
		if ( ! empty( $_POST['new_key'] ) ) {
			update_option( self::OPT_SECRET, wp_generate_password( 32, false ), false );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=rgp-deploy&saved=1' ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::activate(); // Makes sure a key exists even if the plugin was copied in by hand.

		$state   = self::state();
		$repo    = self::repo_dir();
		$found   = is_dir( self::source_root() );
		$rev     = $found ? self::current_revision() : '';
		$webhook = rest_url( 'rgp-deploy/v1/sync' );
		$legacy  = (int) get_option( self::OPT_LEGACY_KEY, 0 );
		$can_rb  = false;
		foreach ( $state['staged'] as $unit ) {
			$live = self::target_root() . '/' . $unit;
			if ( is_dir( dirname( $live ) . '/' . self::PREV_PREFIX . basename( $live ) ) ) {
				$can_rb = true;
			}
		}
		?>
		<div class="wrap">
			<h1>Deploy Helper</h1>
			<?php if ( isset( $_GET['synced'] ) ) : ?>
				<div class="notice notice-success"><p>Deployment ran.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['rolledback'] ) ) : ?>
				<div class="notice notice-<?php echo (int) $_GET['rolledback'] ? 'success' : 'warning'; ?>"><p><?php echo (int) $_GET['rolledback'] ? 'Rolled back to the previous version. The next new commit deploys again.' : 'Nothing to roll back.'; ?></p></div>
			<?php endif; ?>
			<?php if ( $legacy ) : ?>
				<div class="notice notice-warning"><p>A webhook still sends the key in the URL (<code>?key=</code>, last on <?php echo esc_html( wp_date( 'Y-m-d H:i', $legacy ) ); ?>). It keeps working for now; switch it to the GitHub Secret below so the key stays out of server logs.</p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:900px">
				<tr><th>Repo folder</th><td><code><?php echo esc_html( $repo ); ?></code> <?php echo $found ? '&#10003; found' : '<strong style="color:#b32d2e">not found (no wp-content folder inside)</strong>'; ?></td></tr>
				<tr><th>Repo revision</th><td><code><?php echo esc_html( $rev ?: '-' ); ?></code></td></tr>
				<tr><th>Last deployed revision</th><td><code><?php echo esc_html( $state['revision'] ?: '-' ); ?></code></td></tr>
				<tr><th>Last run</th><td><?php echo $state['time'] ? esc_html( wp_date( 'Y-m-d H:i:s', $state['time'] ) . ' (' . $state['trigger'] . '), ' . $state['copied'] . ' copied, ' . $state['removed'] . ' removed' ) : 'never'; ?></td></tr>
				<tr><th>Files managed</th><td><?php echo (int) count( $state['files'] ); ?></td></tr>
				<?php if ( $state['staged'] ) : ?>
					<tr><th>Swapped in whole</th><td><?php echo esc_html( implode( ', ', $state['staged'] ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $state['notes'] ) : ?>
					<tr><th>Notes</th><td><?php echo esc_html( implode( ' | ', $state['notes'] ) ); ?></td></tr>
				<?php endif; ?>
				<?php if ( $state['errors'] ) : ?>
					<tr><th>Errors</th><td style="color:#b32d2e"><?php echo esc_html( implode( ' | ', $state['errors'] ) ); ?></td></tr>
				<?php endif; ?>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0">
				<input type="hidden" name="action" value="rgp_deploy_sync">
				<?php wp_nonce_field( 'rgp_deploy_sync' ); ?>
				<?php submit_button( 'Deploy now', 'primary', 'submit', false ); ?>
				<label style="margin-left:12px"><input type="checkbox" name="allow_removal" value="1"> Allow removing many files (only when you deleted a theme/plugin folder or many files on purpose)</label>
			</form>

			<?php if ( $can_rb ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 16px">
					<input type="hidden" name="action" value="rgp_deploy_rollback">
					<?php wp_nonce_field( 'rgp_deploy_rollback' ); ?>
					<?php submit_button( 'Roll back the last deploy', 'secondary', 'submit', false ); ?>
					<span class="description" style="margin-left:8px">Puts back the folders as they were before the last deploy (<?php echo esc_html( implode( ', ', $state['staged'] ) ); ?>). Click again to undo.</span>
				</form>
			<?php endif; ?>

			<h2>Instant deploy webhook (optional)</h2>
			<p>A WP-Cron task checks the repo folder once a minute (in its own background request, never while a visitor's page loads). For instant deploys, add a second GitHub webhook after Hostinger's one (repo Settings → Webhooks → Add webhook):</p>
			<table class="form-table" style="max-width:900px">
				<tr><th>Payload URL</th><td><input type="text" readonly class="large-text code" value="<?php echo esc_attr( $webhook ); ?>" onclick="this.select()"></td></tr>
				<tr><th>Content type</th><td><code>application/json</code></td></tr>
				<tr><th>Secret</th><td><input type="text" readonly class="large-text code" value="<?php echo esc_attr( get_option( self::OPT_SECRET ) ); ?>" onclick="this.select()"></td></tr>
				<tr><th>Events</th><td>Just the push event</td></tr>
			</table>
			<p class="description">GitHub signs each delivery with the Secret (<code>X-Hub-Signature-256</code>); the key itself is never sent. Other callers (curl, a CI job) can POST to the same URL with the header <code>X-RGP-Key: &lt;key&gt;</code>. Only POST is accepted. The first delivery after a push usually answers 202 because Git is still writing; the once-a-minute check then deploys it.</p>

			<h2>Settings</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rgp_deploy_settings">
				<?php wp_nonce_field( 'rgp_deploy_settings' ); ?>
				<p><label>Repo folder (absolute path, leave empty for this plugin's own folder <code><?php echo esc_html( wp_normalize_path( __DIR__ ) ); ?></code>)<br>
				<input type="text" name="repo_dir" class="large-text code" value="<?php echo esc_attr( get_option( self::OPT_REPO_DIR, '' ) ); ?>" <?php disabled( defined( 'RGP_DEPLOY_REPO_DIR' ) ); ?>></label></p>
				<p><label><input type="checkbox" name="new_key" value="1"> Generate a new webhook key</label></p>
				<?php submit_button( 'Save settings', 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}

RGP_Deploy_Helper::init();
