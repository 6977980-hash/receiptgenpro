<?php
/**
 * Plugin Name: RGP Deploy Helper
 * Description: Copies the theme and plugin folders from this Git checkout (deployed by Hostinger) into the live wp-content folder whenever a new commit arrives. Deleting this plugin only stops deployment; everything already deployed stays as it is.
 * Version:     1.1.2
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

	public static function init() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall' ) );

		add_action( 'init', array( __CLASS__, 'maybe_auto_sync' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_post_rgp_deploy_sync', array( __CLASS__, 'handle_admin_sync' ) );
		add_action( 'admin_post_rgp_deploy_settings', array( __CLASS__, 'handle_admin_settings' ) );
	}

	public static function activate() {
		if ( ! get_option( self::OPT_SECRET ) ) {
			update_option( self::OPT_SECRET, wp_generate_password( 32, false ), false );
		}
	}

	/** Only the plugin's own bookkeeping is removed; deployed files are left alone. */
	public static function uninstall() {
		delete_option( self::OPT_STATE );
		delete_option( self::OPT_SECRET );
		delete_option( self::OPT_REPO_DIR );
		delete_option( self::LOCK );
		delete_transient( self::LOCK ); // lock used by 1.1.1 and older
		delete_transient( self::THROTTLE );
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

	public static function maybe_auto_sync() {
		if ( wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		if ( get_transient( self::THROTTLE ) ) {
			return;
		}
		set_transient( self::THROTTLE, 1, MINUTE_IN_SECONDS );

		if ( ! is_dir( self::source_root() ) ) {
			return;
		}
		$state = self::state();
		$rev   = self::current_revision();
		if ( '' !== $rev && $rev !== $state['revision'] ) {
			self::sync( 'auto' );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Sync                                                               */
	/* ------------------------------------------------------------------ */

	/** @return array<string,string> relative path => absolute source path */
	private static function list_source_files() {
		$root  = self::source_root();
		$files = array();

		foreach ( self::SECTIONS as $section ) {
			$dir = $root . '/' . $section;
			if ( ! is_dir( $dir ) ) {
				continue;
			}
			foreach ( scandir( $dir ) as $entry ) {
				if ( '.' === $entry[0] || ! is_dir( $dir . '/' . $entry ) ) {
					continue; // Only whole theme/plugin folders are deployed.
				}
				if ( 'plugins' === $section && self::SELF_SLUG === $entry ) {
					continue; // Never overwrite the running deploy helper.
				}
				$it = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( $dir . '/' . $entry, FilesystemIterator::SKIP_DOTS )
				);
				foreach ( $it as $file ) {
					if ( ! $file->isFile() ) {
						continue;
					}
					$abs = wp_normalize_path( $file->getPathname() );
					$rel = substr( $abs, strlen( $root ) + 1 );
					// Dotfiles/dot-folders (.git, .github, .DS_Store…), node_modules and
					// Markdown notes (CHANGELOG.md, README.md) are never published.
					if ( preg_match( '#(^|/)(\.[^/]*|node_modules)(/|$)#', $rel ) || preg_match( '/\.md$/i', $rel ) ) {
						continue;
					}
					$files[ $rel ] = $abs;
				}
			}
		}
		ksort( $files );

		// Copy every functions.php last, so a new inc/ file it requires is
		// always on disk before the code that loads it.
		$last = array();
		foreach ( $files as $rel => $abs ) {
			if ( 'functions.php' === basename( $rel ) ) {
				$last[ $rel ] = $abs;
				unset( $files[ $rel ] );
			}
		}
		return $files + $last;
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
		$busy  = self::checkout_busy( $files );
		if ( '' !== $busy ) {
			return self::refuse( $state, $trigger, $busy );
		}
		if ( ! $allow_removal ) {
			$problem = self::removal_problem( $state['files'], array_keys( $files ) );
			if ( '' !== $problem ) {
				return self::refuse( $state, $trigger, $problem . ' Check the repo folder, then use "Deploy now" with "Allow removing many files" if this is intended.' );
			}
		}

		$copied = 0;
		$errors = array();

		foreach ( $files as $rel => $abs ) {
			$dest = $target . '/' . $rel;
			if ( is_file( $dest ) && filesize( $dest ) === filesize( $abs ) && md5_file( $dest ) === md5_file( $abs ) ) {
				continue;
			}
			if ( ! wp_mkdir_p( dirname( $dest ) ) ) {
				$errors[] = 'Cannot create folder for ' . $rel;
				continue;
			}
			$tmp = $dest . '.rgp-tmp-' . wp_generate_password( 8, false );
			if ( @copy( $abs, $tmp ) && @rename( $tmp, $dest ) ) {
				$copied++;
			} else {
				@unlink( $tmp );
				$errors[] = 'Cannot write ' . $rel;
			}
		}

		// The checkout changed while copying: copy again on the next run.
		if ( self::current_revision() !== $rev || '' !== self::checkout_busy( $files ) ) {
			$errors[] = 'The repo folder changed during the deployment; it will run again.';
		}

		// Remove files this plugin deployed earlier that are gone from the repo.
		// Files it never deployed (uploads, other plugins, etc.) are never touched.
		$removed = 0;
		if ( empty( $errors ) ) {
			foreach ( array_diff( $state['files'], array_keys( $files ) ) as $rel ) {
				$dest = $target . '/' . $rel;
				if ( is_file( $dest ) && @unlink( $dest ) ) {
					$removed++;
				}
			}
		}

		// The revision only counts as deployed when everything went through;
		// otherwise it stays, and the next check runs the deployment again.
		$new_state = array(
			'revision' => empty( $errors ) ? $rev : $state['revision'],
			'files'    => empty( $errors ) ? array_keys( $files ) : array_values( array_unique( array_merge( $state['files'], array_keys( $files ) ) ) ),
			'time'     => time(),
			'trigger'  => $trigger,
			'copied'   => $copied,
			'removed'  => $removed,
			'errors'   => $errors,
		);
		update_option( self::OPT_STATE, $new_state, false );

		if ( $copied || $removed ) {
			if ( function_exists( 'opcache_reset' ) ) {
				@opcache_reset();
			}
			wp_clean_themes_cache();
			wp_clean_plugins_cache( false );

			// Clear the LiteSpeed page cache so visitors get the new version.
			do_action( 'litespeed_purge_all' );
		}

		return $new_state;
	}

	private static function state() {
		$state = get_option( self::OPT_STATE, array() );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'revision' => '',
				'files'    => array(),
				'time'     => 0,
				'trigger'  => '',
				'copied'   => 0,
				'removed'  => 0,
				'errors'   => array(),
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/* Webhook: POST /wp-json/rgp-deploy/v1/sync, header X-RGP-Key: SECRET */
	/* (GET/POST with ?key=SECRET also works)                             */
	/* ------------------------------------------------------------------ */

	public static function register_route() {
		register_rest_route(
			'rgp-deploy/v1',
			'/sync',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'permission_callback' => array( __CLASS__, 'check_key' ),
				'callback'            => function () {
					$result = self::sync( 'webhook' );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					return rest_ensure_response( $result );
				},
			)
		);
	}

	public static function check_key( WP_REST_Request $request ) {
		$secret = (string) get_option( self::OPT_SECRET, '' );
		// Prefer the X-RGP-Key header (keeps the key out of server logs);
		// ?key= still works so existing webhooks don't break.
		$given  = (string) $request->get_header( 'x_rgp_key' );
		if ( '' === $given ) {
			$given = (string) $request->get_param( 'key' );
		}
		return '' !== $secret && hash_equals( $secret, $given );
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

	public static function handle_admin_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'rgp_deploy_settings' );
		$dir = isset( $_POST['repo_dir'] ) ? sanitize_text_field( wp_unslash( $_POST['repo_dir'] ) ) : '';
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
		$webhook = add_query_arg( 'key', get_option( self::OPT_SECRET ), rest_url( 'rgp-deploy/v1/sync' ) );
		?>
		<div class="wrap">
			<h1>Deploy Helper</h1>
			<?php if ( isset( $_GET['synced'] ) ) : ?>
				<div class="notice notice-success"><p>Deployment ran.</p></div>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:900px">
				<tr><th>Repo folder</th><td><code><?php echo esc_html( $repo ); ?></code> <?php echo $found ? '&#10003; found' : '<strong style="color:#b32d2e">not found (no wp-content folder inside)</strong>'; ?></td></tr>
				<tr><th>Repo revision</th><td><code><?php echo esc_html( $rev ?: '-' ); ?></code></td></tr>
				<tr><th>Last deployed revision</th><td><code><?php echo esc_html( $state['revision'] ?: '-' ); ?></code></td></tr>
				<tr><th>Last run</th><td><?php echo $state['time'] ? esc_html( wp_date( 'Y-m-d H:i:s', $state['time'] ) . ' (' . $state['trigger'] . '), ' . $state['copied'] . ' copied, ' . $state['removed'] . ' removed' ) : 'never'; ?></td></tr>
				<tr><th>Files managed</th><td><?php echo (int) count( $state['files'] ); ?></td></tr>
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

			<h2>Instant deploy webhook (optional)</h2>
			<p>The site checks the repo folder once a minute on its own. For instant deploys, add this URL as a second GitHub webhook after Hostinger's one:</p>
			<p><input type="text" readonly class="large-text code" value="<?php echo esc_attr( $webhook ); ?>" onclick="this.select()"></p>
			<p class="description">Callers that can send headers (curl, a CI job) can POST to <code><?php echo esc_html( rest_url( 'rgp-deploy/v1/sync' ) ); ?></code> with the header <code>X-RGP-Key: &lt;key&gt;</code> instead, which keeps the key out of server logs.</p>

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
