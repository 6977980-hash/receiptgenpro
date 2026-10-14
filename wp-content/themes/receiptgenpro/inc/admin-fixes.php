<?php
/**
 * ReceiptGenPro — Tools → ReceiptGenPro Fixes
 *
 * One-click, previewable DATABASE fixes from the Sept 2026 audit. Theme
 * code can't change what is stored in posts/pages/users, so these run
 * only when an administrator clicks them:
 *
 *   A. Page text      — placeholders (C1), issuer framing (C5)
 *   B. Internal links — /blog/<slug>/ and /#templates stored in content (H1)
 *   C. Dates          — backdated publish dates (C2)
 *   D. Author slug    — the "6977980gmail-com" login slug (C6)
 *   E. Editorial Policy page draft (H8)
 *
 * Every content change goes through wp_update_post(), so WordPress keeps
 * a revision you can restore from the editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RF_DOMAIN_REGISTERED', '2026-09-16' ); // RDAP registration date of receiptgenpro.com
define( 'RF_CONTENT_IMPORTED', '2026-09-19 23:59:59' ); // all 21 posts were created/imported by 19 Sept (UTC)

function rf_fixes_menu() {
	add_management_page( 'ReceiptGenPro Fixes', 'ReceiptGenPro Fixes', 'manage_options', 'rf-fixes', 'rf_fixes_page' );
}
add_action( 'admin_menu', 'rf_fixes_menu' );

/* --------------------------------------------------------------------------
   Helpers
   -------------------------------------------------------------------------- */

function rf_fixes_all_content() {
	return get_posts(
		array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => array( 'publish', 'draft', 'private', 'future' ),
			'numberposts' => -1,
		)
	);
}

function rf_fixes_apply_patch( $content, $patch ) {
	if ( ! empty( $patch['skip'] ) ) {
		return array( $content, 'skipped' );
	}
	// 'unless': the patch has already been applied (or written by hand).
	if ( ! empty( $patch['unless'] ) && false !== strpos( $content, $patch['unless'] ) ) {
		return array( $content, 'not-found' );
	}
	if ( ! empty( $patch['regex'] ) ) {
		if ( ! preg_match( $patch['find'], $content ) ) {
			return array( $content, 'not-found' );
		}
		// 'backrefs' keeps $1 etc. in the replacement working.
		$replace = empty( $patch['backrefs'] ) ? str_replace( '$', '\$', $patch['replace'] ) : $patch['replace'];
		return array( preg_replace( $patch['find'], $replace, $content, 1 ), 'applied' );
	}
	if ( false === strpos( $content, $patch['find'] ) ) {
		return array( $content, 'not-found' );
	}
	return array( str_replace( $patch['find'], $patch['replace'], $content ), 'applied' );
}

function rf_fixes_owner_input() {
	$saved = get_option( 'rf_owner_details', array() );
	$in    = array();
	foreach ( array( 'owner', 'role', 'location', 'law', 'reply' ) as $k ) {
		$in[ $k ] = isset( $_POST[ 'rf_' . $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'rf_' . $k ] ) ) : ( $saved[ $k ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( '' === $in['role'] ) {
		$in['role'] = 'web developer';
	}
	if ( '' === $in['reply'] ) {
		$in['reply'] = '2 business days';
	}
	return $in;
}

function rf_fixes_backdated() {
	global $wpdb;
	$cutoff = RF_DOMAIN_REGISTERED . ' 00:00:00';
	return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ID, post_type, post_title, post_date_gmt, post_modified_gmt FROM {$wpdb->posts}
			 WHERE post_type IN ('post','page') AND post_status IN ('publish','draft','private')
			 AND post_date_gmt > '1000-01-01' AND post_date_gmt < %s ORDER BY post_date_gmt ASC",
			$cutoff
		)
	);
}

function rf_fixes_email_users() {
	return array_filter(
		get_users( array( 'fields' => array( 'ID', 'user_nicename', 'display_name' ) ) ),
		function ( $u ) {
			return (bool) preg_match( '/gmail|yahoo|hotmail|outlook|@|\d{6,}/i', $u->user_nicename );
		}
	);
}

/* --------------------------------------------------------------------------
   Actions
   -------------------------------------------------------------------------- */

function rf_fixes_handle() {
	if ( empty( $_POST['rf_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return array();
	}
	check_admin_referer( 'rf_fixes' );
	$action = sanitize_key( $_POST['rf_action'] );
	$apply  = ! empty( $_POST['rf_apply'] );
	$log    = array();

	switch ( $action ) {

		case 'content':
			$owner = rf_fixes_owner_input();
			update_option( 'rf_owner_details', $owner, false );
			$patches = rf_content_patches( $owner );
			foreach ( $patches as $slug => $list ) {
				// Published page only (WordPress also keeps a draft "privacy-policy"
				// page from installation, which get_page_by_path() may return).
				$found = get_posts( array( 'name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1 ) );
				$page  = $found ? $found[0] : null;
				if ( ! $page ) {
					$log[] = array( 'warn', "/$slug/ — page not found" );
					continue;
				}
				$content = $page->post_content;
				foreach ( $list as $patch ) {
					list( $content, $status ) = rf_fixes_apply_patch( $content, $patch );
					$icon  = 'applied' === $status ? ( $apply ? 'ok' : 'todo' ) : 'info';
					$text  = 'applied' === $status ? ( $apply ? 'Done' : 'Will change' ) : ( 'skipped' === $status ? 'Skipped (fill in the field above)' : 'Already fixed / text not found' );
					$log[] = array( $icon, "/$slug/ — {$patch['label']}: $text" );
				}
				if ( $apply && $content !== $page->post_content ) {
					wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
				}
			}
			if ( '' === $owner['owner'] ) {
				$log[] = array( 'warn', 'Owner name is empty: the About/Contact "[Site owner]" notes will simply be removed. Fill in your name to add a real "Who runs ReceiptGenPro" section instead.' );
			}
			break;

		case 'links':
			$changed = 0;
			foreach ( rf_fixes_all_content() as $p ) {
				$new = rf_rewrite_internal_links( $p->post_content );
				if ( $new !== $p->post_content ) {
					$before = preg_match_all( '#/blog/(?!page/)[a-z0-9\-]+|/\#templates#i', $p->post_content );
					$log[]  = array( $apply ? 'ok' : 'todo', sprintf( '%s — %d link(s)', get_permalink( $p ), $before ) );
					$changed++;
					if ( $apply ) {
						wp_update_post( array( 'ID' => $p->ID, 'post_content' => $new ) );
					}
				}
			}
			$log[] = array( 'info', $changed ? "$changed post(s)/page(s) " . ( $apply ? 'updated.' : 'will be updated.' ) : 'No stored /blog/ or /#templates links left.' );
			break;

		case 'dates':
			$rows = rf_fixes_backdated();
			$floor = RF_DOMAIN_REGISTERED . ' 12:00:00';
			global $wpdb;
			foreach ( $rows as $r ) {
				// Real date = when it was actually created on this domain: its
				// last-modified time if that falls between registration and the
				// 19 Sept import, else clamped into that window.
				$new_gmt = max( $floor, min( $r->post_modified_gmt, RF_CONTENT_IMPORTED ) );
				$log[]   = array( $apply ? 'ok' : 'todo', sprintf( '%s "%s": %s → %s (UTC)', $r->post_type, $r->post_title, $r->post_date_gmt, $new_gmt ) );
				if ( $apply ) {
					$data = array(
						'post_date_gmt' => $new_gmt,
						'post_date'     => get_date_from_gmt( $new_gmt ),
					);
					if ( $r->post_modified_gmt < $new_gmt ) {
						$data['post_modified_gmt'] = $new_gmt;
						$data['post_modified']     = get_date_from_gmt( $new_gmt );
					}
					$wpdb->update( $wpdb->posts, $data, array( 'ID' => $r->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					clean_post_cache( (int) $r->ID );
				}
			}
			$log[] = array( 'info', $rows ? count( $rows ) . ' item(s) dated before the domain existed.' : 'No backdated posts or pages.' );
			break;

		case 'author':
			$slug = isset( $_POST['rf_new_slug'] ) ? sanitize_title( wp_unslash( $_POST['rf_new_slug'] ) ) : '';
			if ( ! $slug ) {
				$log[] = array( 'warn', 'Enter a new slug.' );
				break;
			}
			foreach ( rf_fixes_email_users() as $u ) {
				$target = $slug;
				$n      = 2;
				while ( ( $other = get_user_by( 'slug', $target ) ) && (int) $other->ID !== (int) $u->ID ) {
					$target = $slug . '-' . $n++;
				}
				$log[] = array( $apply ? 'ok' : 'todo', "User #{$u->ID}: {$u->user_nicename} → $target" );
				if ( $apply ) {
					wp_update_user( array( 'ID' => $u->ID, 'user_nicename' => $target ) );
				}
			}
			if ( ! rf_fixes_email_users() && ! $apply ) {
				$log[] = array( 'info', 'No user slug contains an email address any more.' );
			}
			break;

		case 'titles':
			foreach ( rf_seo_title_patches() as $slug => $title ) {
				$found = get_posts( array( 'name' => $slug, 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ) );
				if ( ! $found ) {
					$log[] = array( 'warn', "/$slug/ — post not found" );
					continue;
				}
				$current = get_post_meta( $found[0]->ID, 'rf_seo_title', true );
				if ( $current === $title ) {
					$log[] = array( 'info', "/$slug/ — already set" );
					continue;
				}
				$log[] = array( $apply ? 'ok' : 'todo', sprintf( '/%s/ — "%s" → "%s" (%d characters)', $slug, $current ? $current : get_the_title( $found[0] ), $title, mb_strlen( $title ) ) );
				if ( $apply ) {
					update_post_meta( $found[0]->ID, 'rf_seo_title', $title );
				}
			}
			break;

		case 'editorial':
			$existing = get_page_by_path( 'editorial-policy', OBJECT, 'page' );
			if ( $existing ) {
				$log[] = array( 'info', 'An Editorial Policy page already exists (status: ' . $existing->post_status . ').' );
				break;
			}
			if ( $apply ) {
				$owner = get_option( 'rf_owner_details', array() );
				$id    = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'draft',
						'post_title'   => 'Editorial Policy',
						'post_name'    => 'editorial-policy',
						'post_content' => rf_editorial_policy_content( $owner['owner'] ?? '' ),
					)
				);
				$log[] = array( 'ok', 'Draft created — review it, then click Publish: ' . get_edit_post_link( $id, 'raw' ) );
			} else {
				$log[] = array( 'todo', 'Will create a DRAFT page at /editorial-policy/ for you to review and publish.' );
			}
			break;
	}
	return array( 'action' => $action, 'apply' => $apply, 'log' => $log );
}

/* --------------------------------------------------------------------------
   Screen
   -------------------------------------------------------------------------- */

function rf_fixes_status() {
	$ph = 0;
	$risky = 0;
	$links = 0;
	foreach ( rf_fixes_all_content() as $p ) {
		$ph    += preg_match_all( '#\[(Site owner|add the date)#i', $p->post_content );
		$risky += preg_match_all( '#reconstruct(ing)? a lost|recreate a lost|never-issued|best estimate#i', $p->post_content );
		$links += preg_match_all( '#href=["\'](https?://[^"\']+)?/blog/(?!page/)[a-z0-9\-]+|href=["\'][^"\']*/\#templates#i', $p->post_content );
	}
	$hub = get_option( 'rf_hub_page_id' );
	return array(
		array( 'Placeholder notes stored in pages ("[Site owner…]", "[add the date…]")', $ph, '2' ),
		array( 'Risky "recreate a lost receipt" wording', $risky, '2' ),
		array( 'Stored /blog/… and /#templates links', $links, '3' ),
		array( 'Posts/pages dated before the domain was registered (' . RF_DOMAIN_REGISTERED . ')', count( rf_fixes_backdated() ), '1' ),
		array( 'User slugs containing an email address', count( rf_fixes_email_users() ), '4' ),
		array( 'Editorial Policy page published', rf_editorial_policy_url() ? 0 : 1, '5' ),
		array( 'Receipt Templates hub page', ( $hub && get_post( $hub ) ) ? 0 : 1, 'auto' ),
	);
}

function rf_fixes_page() {
	$result = rf_fixes_handle();
	$owner  = rf_fixes_owner_input();
	$status = rf_fixes_status();
	$icons  = array( 'ok' => '✅', 'todo' => '➡️', 'info' => 'ℹ️', 'warn' => '⚠️' );

	$form_open = function ( $action ) {
		echo '<form method="post" style="margin:0 0 8px">';
		wp_nonce_field( 'rf_fixes' );
		echo '<input type="hidden" name="rf_action" value="' . esc_attr( $action ) . '">';
	};
	$buttons = function () {
		echo '<p><button class="button" name="rf_apply" value="">Preview</button> ';
		echo '<button class="button button-primary" name="rf_apply" value="1" onclick="return confirm(\'Apply these changes? Content edits create revisions you can restore.\')">Apply</button></p></form>';
	};
	?>
	<div class="wrap">
		<h1>ReceiptGenPro — Audit Fixes (theme v<?php echo esc_html( RF_THEME_VERSION ); ?>)</h1>
		<p>Database fixes from the September 2026 SEO audit. Work through the sections in order (1 → 5). Click <strong>Preview</strong> first to see exactly what will change, then <strong>Apply</strong>. Code-level fixes (schema, robots, sitemap, JPEG export, template forms, etc.) are already active with this theme version.</p>

		<?php if ( $result ) : ?>
			<div class="notice notice-<?php echo $result['apply'] ? 'success' : 'info'; ?>" style="padding:10px 14px">
				<p><strong><?php echo $result['apply'] ? 'Applied' : 'Preview'; ?> — <?php echo esc_html( strtoupper( $result['action'] ) ); ?></strong></p>
				<ul style="margin:0">
					<?php foreach ( $result['log'] as $line ) : ?>
						<li><?php echo esc_html( $icons[ $line[0] ] . ' ' . $line[1] ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<h2>Status</h2>
		<table class="widefat striped" style="max-width:900px">
			<thead><tr><th>Check</th><th>Remaining</th><th>Fix</th></tr></thead>
			<tbody>
			<?php foreach ( $status as $row ) : ?>
				<tr><td><?php echo esc_html( $row[0] ); ?></td><td><?php echo $row[1] ? '⚠️ ' . (int) $row[1] : '✅ 0'; ?></td><td><?php echo esc_html( $row[2] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<h2>1. Publish dates <small>(run this first)</small></h2>
		<p>Posts and pages carry publish dates from March–September 2026, but the domain was registered on <?php echo esc_html( RF_DOMAIN_REGISTERED ); ?>. Each backdated item gets a real date between <?php echo esc_html( RF_DOMAIN_REGISTERED ); ?> and 19 Sept 2026 (its original last-modified time when that falls in this window). Nothing else changes.</p>
		<?php $form_open( 'dates' ); $buttons(); ?>

		<h2>2. Page text — placeholders &amp; issuer framing</h2>
		<p>Fixes About, Contact, Terms, Privacy and Cookie placeholders, and rewrites the taxi, hotel and restaurant copy that suggested recreating lost receipts for expense claims. Oct 2026: also cross-links the rent and HRA rent pages in their first paragraph and adds a short "what we never store" section to About and a "how to reach us" section to Contact.</p>
		<?php $form_open( 'content' ); ?>
			<table class="form-table" style="max-width:900px">
				<tr><th><label for="rf_owner">Your name (shown on About / Contact)</label></th><td><input class="regular-text" id="rf_owner" name="rf_owner" value="<?php echo esc_attr( $owner['owner'] ); ?>" placeholder="e.g. Ali Ahmad"> <p class="description">Leave empty to just remove the notes. After applying, open Pages → About Us and add a few lines about yourself.</p></td></tr>
				<tr><th><label for="rf_role">Role</label></th><td><input class="regular-text" id="rf_role" name="rf_role" value="<?php echo esc_attr( $owner['role'] ); ?>"></td></tr>
				<tr><th><label for="rf_location">Location</label></th><td><input class="regular-text" id="rf_location" name="rf_location" value="<?php echo esc_attr( $owner['location'] ); ?>" placeholder="e.g. Punjab, Pakistan"></td></tr>
				<tr><th><label for="rf_law">Governing law (Terms)</label></th><td><input class="regular-text" id="rf_law" name="rf_law" value="<?php echo esc_attr( $owner['law'] ); ?>" placeholder="e.g. Pakistan"></td></tr>
				<tr><th><label for="rf_reply">Typical reply time</label></th><td><input class="regular-text" id="rf_reply" name="rf_reply" value="<?php echo esc_attr( $owner['reply'] ); ?>"></td></tr>
			</table>
		<?php $buttons(); ?>

		<h2>3. Internal links</h2>
		<p>Rewrites stored links such as <code>/blog/how-long-should-you-keep-business-receipts/</code> to the real URL <code>/how-long-should-you-keep-business-receipts/</code>, and <code>/#templates</code> to <code>/receipt-templates/</code>. (The theme already fixes them on the fly; this makes it permanent.)</p>
		<?php $form_open( 'links' ); $buttons(); ?>

		<h2>4. Author slug</h2>
		<p>Replaces login slugs that contain an email address. Author archives already redirect to About Us, and the REST users list is hidden from visitors.</p>
		<?php $form_open( 'author' ); ?>
			<p><label>New slug: <input name="rf_new_slug" value="<?php echo esc_attr( sanitize_title( $owner['owner'] ?: 'site-owner' ) ); ?>"></label></p>
		<?php $buttons(); ?>

		<h2>5. Editorial Policy page</h2>
		<p>Creates a <strong>draft</strong> at /editorial-policy/ explaining who writes the guides, which official sources are used, how often they are checked, and how to report errors. Read it before you publish — it describes commitments you'll need to keep. Links to it appear automatically once it is published.</p>
		<?php $form_open( 'editorial' ); $buttons(); ?>

		<h2>6. Shorter search titles</h2>
		<p>Sets the SEO title (<code>rf_seo_title</code>) of three guides whose titles are longer than 60 characters, so Google shows them in full. The post headline on the page doesn't change.</p>
		<?php $form_open( 'titles' ); $buttons(); ?>

		<h2>After applying</h2>
		<ol>
			<li>Settings → Permalinks → <em>Save</em> (refreshes rewrite rules for /llms.txt and IndexNow; the theme also does this once automatically).</li>
			<li>Search Console → Sitemaps → submit <code><?php echo esc_html( home_url( '/wp-sitemap.xml' ) ); ?></code>, then URL Inspection → Request indexing for the homepage, /receipt-templates/ and the template pages.</li>
			<li>Bing Webmaster Tools → import the site from Search Console. IndexNow is already active (key file: <code><?php echo esc_html( home_url( '/' . rf_indexnow_key() . '.txt' ) ); ?></code>).</li>
			<li>Optional: set <code>RF_GA4_ID</code> in <code>inc/adsense.php</code> to turn on consent-gated Google Analytics.</li>
		</ol>
	</div>
	<?php
}
