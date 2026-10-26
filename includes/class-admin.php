<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'generate', 'ideas', 'queue', 'existing', 'test', 'models', 'notify_test', 'wa_detect', 'yt_test', 'tg_detect', 'notify_batch', 'you_test' ) as $a ) {
			add_action( 'wp_ajax_aiap_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
	}

	private static function menu_icon() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path fill="black" fill-rule="evenodd" d="M30 6 L47 35 L30 58 L13 35 Z M28.4 26 h3.2 v15 h-3.2 Z"/><path fill="black" d="M49 4 l2.4 6.2 6.2 2.4 -6.2 2.4 -2.4 6.2 -2.4 -6.2 -6.2 -2.4 6.2 -2.4 Z"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore
	}

	public static function menu() {
		add_menu_page( AIAP_NAME, AIAP_NAME, 'manage_options', 'aiap', array( __CLASS__, 'page_dashboard' ), self::menu_icon(), 30 );
		add_submenu_page( 'aiap', 'Dashboard', 'Dashboard', 'manage_options', 'aiap', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'aiap', 'Writer', 'Writer', 'manage_options', 'aiap-writer', array( __CLASS__, 'page_writer' ) );
		add_submenu_page( 'aiap', 'Existing posts', 'Existing posts', 'manage_options', 'aiap-existing', array( __CLASS__, 'page_existing' ) );
		add_submenu_page( 'aiap', 'Queue & History', 'Queue & History', 'manage_options', 'aiap-queue', array( __CLASS__, 'page_queue' ) );
		add_submenu_page( 'aiap', 'Settings', 'Settings', 'manage_options', 'aiap-settings', array( 'AIAP_Settings', 'page' ) );
	}

	public static function assets() {
		if ( empty( $_GET['page'] ) || 0 !== strpos( sanitize_key( wp_unslash( $_GET['page'] ) ), 'aiap' ) ) { // phpcs:ignore
			return;
		}
		wp_enqueue_style( 'aiap', AIAP_URL . 'assets/admin.css', array(), AIAP_VER );
		wp_enqueue_script( 'aiap', AIAP_URL . 'assets/admin.js', array(), AIAP_VER, true );
		wp_localize_script(
			'aiap',
			'AIAP',
			array(
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aiap_nonce' ),
				'provider' => AIAP_Settings::get( 'provider' ),
				'summary'  => ( AIAP_Settings::get( 'notify_bulk_summary' ) && ( AIAP_Settings::get( 'tg_enabled' ) || AIAP_Settings::get( 'wa_enabled' ) ) ) ? 1 : 0,
			)
		);
	}

	/* ---------------- History log ---------------- */

	public static function log( $kw, $r ) {
		$h = get_option( 'aiap_history', array() );
		$h = is_array( $h ) ? $h : array();
		array_unshift(
			$h,
			array(
				't'     => time(),
				'kw'    => $kw,
				'ok'    => ! is_wp_error( $r ),
				'id'    => is_wp_error( $r ) ? 0 : (int) $r['id'],
				'title' => is_wp_error( $r ) ? '' : $r['title'],
				'msg'   => is_wp_error( $r ) ? $r->get_error_message() : '',
			)
		);
		update_option( 'aiap_history', array_slice( $h, 0, 50 ), false );
	}

	/* ====================== Shell (header + nav) ====================== */

	public static function shell_open( $active, $title, $sub = '' ) {
		$nav = array(
			'aiap'          => 'Dashboard',
			'aiap-writer'   => 'Writer',
			'aiap-existing' => 'Existing posts',
			'aiap-queue'    => 'Queue & History',
			'aiap-settings' => 'Settings',
		);
		$cfg = AIAP_Providers::config();
		$s   = AIAP_Settings::all();
		$on  = function ( $b ) {
			return $b ? ' on' : '';
		};
		$img_ok = ( 'none' !== $s['image_source'] );
		echo '<div class="pn-wrap"><h1 class="pn-sr">' . esc_html( AIAP_NAME ) . '</h1><div class="pn">';
		echo '<header class="pn-head"><div class="pn-top">';
		echo '<a class="pn-brand" href="' . esc_url( admin_url( 'admin.php?page=aiap' ) ) . '"><img src="' . esc_url( AIAP_URL . 'assets/logo-light.svg' ) . '" alt="' . esc_attr( AIAP_NAME ) . '"><span class="pn-ver">v' . esc_html( AIAP_VER ) . '</span></a>';
		echo '<div class="pn-chips">';
		echo '<span class="pn-chip on" title="Active AI provider">🤖 ' . esc_html( $cfg['label'] ) . ( $cfg['model'] ? ' · ' . esc_html( $cfg['model'] ) : '' ) . '</span>';
		echo '<span class="pn-chip' . $on( $img_ok ) . '">🖼️ ' . esc_html( ucfirst( $s['image_source'] ) ) . '</span>'; // phpcs:ignore
		echo '<span class="pn-chip' . $on( (int) $s['videos'] > 0 && $s['yt_key'] ) . '">▶ YouTube</span>'; // phpcs:ignore
		echo '<span class="pn-chip' . $on( 'off' !== $s['you_mode'] && $s['you_key'] ) . '">🧠 You.com</span>'; // phpcs:ignore
		echo '<span class="pn-chip' . $on( $s['tg_enabled'] ) . '">✈ Telegram</span>'; // phpcs:ignore
		echo '<span class="pn-chip' . $on( $s['wa_enabled'] ) . '">💬 WhatsApp</span>'; // phpcs:ignore
		echo '</div></div><nav class="pn-nav">';
		foreach ( $nav as $slug => $label ) {
			echo '<a class="' . ( $active === $slug ? 'on' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav></header><main class="pn-body">';
		echo '<div class="pn-title"><h2>' . esc_html( $title ) . '</h2>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div>';
	}

	public static function shell_close() {
		echo '</main><footer class="pn-foot">' . esc_html( AIAP_NAME ) . ' v' . esc_html( AIAP_VER ) . ' · SEO Autoblogger</footer></div></div>';
	}

	/* ====================== Helpers ====================== */

	private static function sel( $name, $opts, $cur ) {
		$h = '<select name="' . esc_attr( $name ) . '">';
		foreach ( $opts as $v => $l ) {
			$h .= '<option value="' . esc_attr( $v ) . '" ' . selected( (string) $cur, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function sw( $name, $on, $label, $hint = '' ) {
		return '<label class="pn-sw"><input type="checkbox" name="' . esc_attr( $name ) . '" ' . checked( ! empty( $on ), true, false ) . '><span class="pn-sw-t"></span><span class="pn-sw-l"><b>' . esc_html( $label ) . '</b>' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</span></label>';
	}

	private static function field( $label, $html ) {
		return '<div class="pn-f"><label>' . esc_html( $label ) . '</label>' . $html . '</div>';
	}

	/* ====================== Dashboard ====================== */

	public static function page_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = AIAP_Settings::all();
		$cfg = AIAP_Providers::config();

		$base  = array( 'post_type' => array( 'post', 'page' ), 'post_status' => 'any', 'meta_key' => '_aiap_generated', 'meta_value' => '1' ); // phpcs:ignore
		$all   = new WP_Query( array_merge( $base, array( 'posts_per_page' => 6, 'no_found_rows' => false ) ) );
		$week  = new WP_Query( array_merge( $base, array( 'posts_per_page' => 1, 'date_query' => array( array( 'after' => '7 days ago' ) ) ) ) );
		$hist  = get_option( 'aiap_history', array() );
		$hist  = is_array( $hist ) ? $hist : array();
		$okc   = 0;
		foreach ( $hist as $h ) {
			if ( ! empty( $h['ok'] ) ) {
				$okc++;
			}
		}
		$rate  = $hist ? round( $okc / count( $hist ) * 100 ) . '%' : '-';
		$queue = count( AIAP_Queue::items() );

		$img_key = array( 'unsplash' => 'unsplash_key', 'pexels' => 'pexels_key', 'openai' => 'img_openai_key' );
		$img_ok  = ( 'none' === $s['image_source'] ) || ( isset( $img_key[ $s['image_source'] ] ) && ( $s[ $img_key[ $s['image_source'] ] ] || ( 'openai' === $s['image_source'] && ! empty( $s['keys']['openai'] ) ) ) );
		$steps   = array(
			array( 'AI provider ki key', ( $cfg['key'] || $cfg['nokey'] ), 'aiap-settings#provider' ),
			array( 'Image source key', $img_ok, 'aiap-settings#media' ),
			array( 'YouTube key (videos ke liye)', (bool) $s['yt_key'], 'aiap-settings#media' ),
			array( 'You.com research key (optional)', (bool) $s['you_key'], 'aiap-settings#research' ),
			array( 'Telegram / WhatsApp alerts', ( $s['tg_enabled'] || $s['wa_enabled'] ), 'aiap-settings#alerts' ),
			array( 'Auto-pilot queue', (bool) $s['queue_enabled'], 'aiap-settings#auto' ),
		);

		self::shell_open( 'aiap', 'Dashboard', 'Aapke AI blog ka poora haal.' );
		?>
		<div class="pn-hero">
			<div>
				<h3>Naya SEO article, 1 minute mein ✨</h3>
				<p>Keyword do, <?php echo esc_html( AIAP_NAME ); ?> article, images, YouTube videos, FAQ aur schema ke saath post bana dega.</p>
			</div>
			<a class="pn-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=aiap-writer' ) ); ?>">✍️ Article likho</a>
		</div>

		<div class="pn-stats">
			<div class="pn-stat"><span>AI posts (kul)</span><b><?php echo (int) $all->found_posts; ?></b></div>
			<div class="pn-stat"><span>Pichle 7 din</span><b><?php echo (int) $week->found_posts; ?></b></div>
			<div class="pn-stat"><span>Queue mein</span><b><?php echo (int) $queue; ?></b></div>
			<div class="pn-stat"><span>Success rate</span><b><?php echo esc_html( $rate ); ?></b></div>
		</div>

		<div class="pn-grid2">
			<div class="pn-card">
				<h3>Setup checklist</h3>
				<ul class="pn-check">
					<?php foreach ( $steps as $st ) : ?>
						<li class="<?php echo $st[1] ? 'ok' : ''; ?>">
							<span class="pn-dot"><?php echo $st[1] ? '✓' : ''; ?></span>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $st[2] ) ); ?>"><?php echo esc_html( $st[0] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="pn-card">
				<h3>Haal hi ki posts</h3>
				<?php if ( ! $all->have_posts() ) : ?>
					<p class="pn-empty">Abhi koi AI post nahi. Writer mein pehla article banao.</p>
				<?php else : ?>
					<ul class="pn-list">
						<?php
						while ( $all->have_posts() ) :
							$all->the_post();
							?>
							<li>
								<a href="<?php echo esc_url( get_edit_post_link( get_the_ID() ) ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
								<span class="pn-pill s-<?php echo esc_attr( get_post_status() ); ?>"><?php echo esc_html( get_post_status() ); ?></span>
							</li>
							<?php
						endwhile;
						wp_reset_postdata();
						?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
		self::shell_close();
	}

	/* ====================== Writer ====================== */

	public static function page_writer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = AIAP_Settings::all();
		self::shell_open( 'aiap-writer', 'Writer', 'Keyword likho, baqi sab Pennova sambhal lega.' );
		?>
		<form id="aiap-form" onsubmit="return false;" class="pn-writer">
			<div class="pn-main">

				<div class="pn-card">
					<div class="pn-step"><i>1</i> Kya likhna hai?</div>
					<textarea name="keywords" rows="5" placeholder="Har line mein ek keyword/topic. Jaise:&#10;best solar panels in Pakistan&#10;how to reduce electricity bill"></textarea>
					<div class="pn-seg">
						<label><input type="radio" name="mode" value="now" checked><span>⚡ Abhi banao</span></label>
						<label><input type="radio" name="mode" value="queue"><span>🕒 Queue mein daalo</span></label>
					</div>
					<details class="pn-details">
						<summary>💡 Keyword ideas (AI se)</summary>
						<div class="pn-inline">
							<input type="text" id="aiap-seed" placeholder="Topic/niche, jaise: solar energy Pakistan">
							<button type="button" class="pn-btn ghost" id="aiap-ideas-run">Ideas do</button>
						</div>
						<p class="pn-note">Ye AI ke ideas hain, search volume data nahi.</p>
						<div id="aiap-ideas"></div>
					</details>
				</div>

				<div class="pn-card">
					<div class="pn-step"><i>2</i> Style</div>
					<div class="pn-cols">
						<?php
						echo self::field( 'Article type', self::sel( 'article_type', array_combine( AIAP_Settings::article_types(), AIAP_Settings::article_types() ), $s['article_type'] ) ); // phpcs:ignore
						echo self::field( 'Word count', '<input type="number" name="word_count" min="300" max="5000" step="100" value="' . esc_attr( $s['word_count'] ) . '">' ); // phpcs:ignore
						echo self::field( 'Language', '<input type="text" name="language" value="' . esc_attr( $s['language'] ) . '">' ); // phpcs:ignore
						echo self::field( 'Tone', '<input type="text" name="tone" value="' . esc_attr( $s['tone'] ) . '">' ); // phpcs:ignore
						echo self::field( 'Point of view', self::sel( 'pov', array_combine( AIAP_Settings::povs(), AIAP_Settings::povs() ), $s['pov'] ) ); // phpcs:ignore
						?>
					</div>
				</div>

				<div class="pn-card">
					<div class="pn-step"><i>3</i> SEO &amp; media</div>
					<div class="pn-cols pn-switches">
						<?php
						echo self::sw( 'toc', $s['toc'], 'Table of Contents' ); // phpcs:ignore
						echo self::sw( 'takeaways', $s['takeaways'], 'Key Takeaways' ); // phpcs:ignore
						echo self::sw( 'faq', $s['faq'], 'FAQ + schema' ); // phpcs:ignore
						echo self::sw( 'internal_links', $s['internal_links'], 'Internal links' ); // phpcs:ignore
						echo self::sw( 'outline_first', 0, 'Pehle outline', 'Behtar structure, thora zyada time.' ); // phpcs:ignore
						?>
					</div>
					<div class="pn-cols">
						<?php
						echo self::field( 'Article ke andar images', '<input type="number" name="inline_images" min="0" max="5" value="' . esc_attr( $s['inline_images'] ) . '">' ); // phpcs:ignore
						echo self::field( 'YouTube videos', '<input type="number" name="videos" min="0" max="3" value="' . esc_attr( $s['videos'] ) . '">' ); // phpcs:ignore
						echo self::field( 'Web research (You.com)', self::sel( 'research', array( 'off' => 'Off', 'serp' => 'SERP insights', 'research' => 'Cited research + Sources' ), $s['you_mode'] ) ); // phpcs:ignore
						?>
					</div>
				</div>

				<div class="pn-card">
					<div class="pn-step"><i>4</i> Publishing</div>
					<div class="pn-cols">
						<?php
						echo self::field( 'Status', self::sel( 'post_status', array( 'draft' => 'Draft', 'pending' => 'Pending', 'publish' => 'Publish' ), $s['post_status'] ) ); // phpcs:ignore
						echo self::field( 'Post type', self::sel( 'post_type', array( 'post' => 'Post', 'page' => 'Page' ), $s['post_type'] ) ); // phpcs:ignore
						ob_start();
						wp_dropdown_categories( array( 'name' => 'category', 'selected' => (int) $s['category'], 'hide_empty' => 0, 'show_option_none' => 'Default', 'option_none_value' => 0 ) );
						echo self::field( 'Category', ob_get_clean() ); // phpcs:ignore
						echo self::field( 'Schedule gap (ghante)', '<input type="number" name="schedule_gap" min="0" value="' . esc_attr( $s['schedule_gap'] ) . '">' ); // phpcs:ignore
						?>
					</div>
				</div>

				<details class="pn-card pn-adv">
					<summary>⚙️ Advanced (custom title, instructions, source)</summary>
					<?php
					echo self::field( 'Custom title (sirf ek keyword ho to)', '<input type="text" name="title" placeholder="Khali = AI title likhega">' ); // phpcs:ignore
					echo self::field( 'Extra instructions', '<textarea name="extra" rows="2" placeholder="Jaise: Pakistani readers ke liye, PKR mein rates, 3 examples..."></textarea>' ); // phpcs:ignore
					echo self::field( 'Source URL', '<input type="url" name="source_url" placeholder="Kisi article ka URL, AI original words mein dobara likhega (copy nahi)">' ); // phpcs:ignore
					echo self::field( 'Source text / transcript', '<textarea name="source_text" rows="3" placeholder="Notes ya YouTube transcript paste karo"></textarea>' ); // phpcs:ignore
					?>
				</details>

				<div class="pn-runbar">
					<span>Kaam shuru karne ke liye dabao</span>
					<button type="button" class="pn-btn big" id="aiap-run">✨ Generate</button>
				</div>
			</div>

			<aside class="pn-side">
				<div class="pn-card pn-sticky">
					<h3>Live activity</h3>
					<div id="aiap-log" class="pn-log"><div class="pn-empty">Natije yahan nazar aayenge.</div></div>
				</div>
			</aside>
		</form>
		<?php
		self::shell_close();
	}

	/* ====================== Existing posts ====================== */

	public static function page_existing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$posts = get_posts( array( 'numberposts' => 100, 'post_status' => array( 'publish', 'draft', 'pending', 'future' ), 'post_type' => array( 'post', 'page' ) ) );
		self::shell_open( 'aiap-existing', 'Existing posts', 'Purani post ko SEO ke liye behtar banao.' );
		?>
		<div class="pn-card pn-narrow">
			<div class="pn-f"><label>Post chuno</label>
				<select id="aiap-ex-post">
					<?php foreach ( $posts as $p ) : ?>
						<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html( $p->post_title . ' (' . $p->post_status . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="pn-choices">
				<label class="pn-choice"><input type="radio" name="aiap-ex-action" value="meta" checked><span><b>SEO meta</b><small>Meta title, description aur focus keyword generate karo.</small></span></label>
				<label class="pn-choice"><input type="radio" name="aiap-ex-action" value="faq"><span><b>FAQ + schema</b><small>Post ke end mein FAQ section aur FAQPage schema.</small></span></label>
				<label class="pn-choice"><input type="radio" name="aiap-ex-action" value="rewrite"><span><b>Rewrite / improve</b><small>Content behtar karo. Purana version Revisions mein rahega.</small></span></label>
			</div>
			<button type="button" class="pn-btn" id="aiap-ex-run">Chalao</button>
			<div id="aiap-ex-log" class="pn-log"></div>
		</div>
		<?php
		self::shell_close();
	}

	/* ====================== Queue & History ====================== */

	public static function page_queue() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$q = AIAP_Queue::items();
		$h = get_option( 'aiap_history', array() );
		$h = is_array( $h ) ? $h : array();
		self::shell_open( 'aiap-queue', 'Queue & History', 'Auto-pilot ke keywords aur pichle natije.' );
		?>
		<div class="pn-card">
			<div class="pn-between">
				<h3>Auto-pilot queue (<?php echo count( $q ); ?>)</h3>
				<div>
					<button type="button" class="pn-btn ghost sm aiap-q" data-op="run_now">▶ Agla item abhi chalao</button>
					<button type="button" class="pn-btn ghost sm aiap-q" data-op="clear">🗑 Khali karo</button>
				</div>
			</div>
			<p class="pn-note">Queue chalane ke liye Settings → Auto-pilot on karo.</p>
			<div id="aiap-qlog" class="pn-log"></div>
			<div class="pn-tablewrap">
				<table class="pn-table">
					<thead><tr><th>#</th><th>Keyword</th><th>Type</th><th></th></tr></thead>
					<tbody>
					<?php if ( ! $q ) : ?>
						<tr><td colspan="4" class="pn-empty">Queue khali hai.</td></tr>
					<?php endif; ?>
					<?php foreach ( $q as $i => $it ) : ?>
						<tr>
							<td><?php echo (int) $i + 1; ?></td>
							<td><?php echo esc_html( $it['kw'] ); ?></td>
							<td><?php echo esc_html( isset( $it['args']['article_type'] ) ? $it['args']['article_type'] : '' ); ?></td>
							<td><button type="button" class="pn-link aiap-q" data-op="remove" data-i="<?php echo (int) $i; ?>">Hatao</button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="pn-card">
			<h3>History (aakhri 50)</h3>
			<div class="pn-tablewrap">
				<table class="pn-table">
					<thead><tr><th>Waqt</th><th>Keyword</th><th>Natija</th></tr></thead>
					<tbody>
					<?php if ( ! $h ) : ?>
						<tr><td colspan="3" class="pn-empty">Abhi koi history nahi.</td></tr>
					<?php endif; ?>
					<?php foreach ( $h as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd M Y, H:i', $row['t'] ) ); ?></td>
							<td><?php echo esc_html( $row['kw'] ); ?></td>
							<td>
								<?php
								if ( $row['ok'] ) {
									echo '✅ <a href="' . esc_url( get_edit_post_link( $row['id'] ) ) . '">' . esc_html( $row['title'] ) . '</a>';
								} else {
									echo '<span class="pn-err">❌ ' . esc_html( $row['msg'] ) . '</span>';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
		self::shell_close();
	}

	/* ====================== AJAX ====================== */

	private static function guard() {
		check_ajax_referer( 'aiap_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission nahi hai.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore
		}
	}

	private static function read_args() {
		$map = array(
			'article_type'  => 'text', 'word_count' => 'int', 'language' => 'text', 'tone' => 'text', 'pov' => 'text',
			'post_status'   => 'status', 'post_type' => 'ptype', 'category' => 'int', 'schedule_gap' => 'int',
			'extra'         => 'area', 'source_url' => 'url', 'source_text' => 'area',
			'outline_first' => 'bool', 'faq' => 'bool', 'toc' => 'bool', 'takeaways' => 'bool', 'internal_links' => 'bool',
			'inline_images' => 'int', 'videos' => 'int', 'title' => 'text', 'index' => 'int', 'quiet' => 'bool', 'research' => 'text',
		);
		$a = array();
		foreach ( $map as $k => $t ) {
			if ( ! isset( $_POST[ $k ] ) ) { // phpcs:ignore
				continue;
			}
			$v = wp_unslash( $_POST[ $k ] ); // phpcs:ignore
			switch ( $t ) {
				case 'int':
					$a[ $k ] = absint( $v );
					break;
				case 'bool':
					$a[ $k ] = $v ? 1 : 0;
					break;
				case 'area':
					$a[ $k ] = sanitize_textarea_field( $v );
					break;
				case 'url':
					$a[ $k ] = $v ? esc_url_raw( $v ) : '';
					break;
				case 'status':
					$a[ $k ] = in_array( $v, array( 'draft', 'pending', 'publish' ), true ) ? $v : 'draft';
					break;
				case 'ptype':
					$a[ $k ] = ( 'page' === $v ) ? 'page' : 'post';
					break;
				default:
					$a[ $k ] = sanitize_text_field( $v );
			}
		}
		if ( isset( $a['research'] ) && ! in_array( $a['research'], array( 'off', 'serp', 'research' ), true ) ) {
			$a['research'] = 'off';
		}
		if ( isset( $a['word_count'] ) ) {
			$a['word_count'] = max( 300, min( 5000, $a['word_count'] ) );
		}
		if ( isset( $a['inline_images'] ) ) {
			$a['inline_images'] = min( 5, $a['inline_images'] );
		}
		if ( isset( $a['videos'] ) ) {
			$a['videos'] = min( 3, $a['videos'] );
		}
		return $a;
	}

	public static function ajax_generate() {
		self::guard();
		$kw = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : ''; // phpcs:ignore
		$r  = AIAP_Generator::run( $kw, self::read_args() );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}

	public static function ajax_ideas() {
		self::guard();
		$seed = isset( $_POST['seed'] ) ? sanitize_text_field( wp_unslash( $_POST['seed'] ) ) : ''; // phpcs:ignore
		$lang = isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : 'English'; // phpcs:ignore
		if ( '' === $seed ) {
			wp_send_json_error( 'Topic likho.' );
		}
		$r = AIAP_Generator::ideas( $seed, $lang );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}

	public static function ajax_queue() {
		self::guard();
		$op = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : ''; // phpcs:ignore
		if ( 'add' === $op ) {
			$raw = isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : ''; // phpcs:ignore
			$kws = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $raw ) ) ) );
			if ( ! $kws ) {
				wp_send_json_error( 'Keywords khali hain.' );
			}
			$total = AIAP_Queue::add( $kws, self::read_args() );
			wp_send_json_success( array( 'added' => count( $kws ), 'total' => $total ) );
		}
		if ( 'clear' === $op ) {
			AIAP_Queue::clear();
			wp_send_json_success( 'Queue khali ho gayi.' );
		}
		if ( 'remove' === $op ) {
			AIAP_Queue::remove( isset( $_POST['i'] ) ? absint( $_POST['i'] ) : -1 ); // phpcs:ignore
			wp_send_json_success( 'Hata diya.' );
		}
		if ( 'run_now' === $op ) {
			$n = AIAP_Queue::run( true );
			wp_send_json_success( $n ? 'Ek item process hua. History dekho.' : 'Queue khali hai (ya pichla run abhi chal raha hai).' );
		}
		wp_send_json_error( 'Unknown op.' );
	}

	public static function ajax_existing() {
		self::guard();
		$id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore
		$ac = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( $_POST['op'] ) ) : ''; // phpcs:ignore
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error( 'Post chuno.' );
		}
		$r = AIAP_Generator::improve( $id, $ac );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}

	private static function read_override() {
		$o = array();
		foreach ( array( 'provider', 'key', 'model', 'format' ) as $k ) {
			if ( isset( $_POST[ 'o_' . $k ] ) ) { // phpcs:ignore
				$o[ $k ] = sanitize_text_field( wp_unslash( $_POST[ 'o_' . $k ] ) ); // phpcs:ignore
			}
		}
		if ( isset( $_POST['o_base'] ) ) { // phpcs:ignore
			$o['base'] = esc_url_raw( wp_unslash( $_POST['o_base'] ) ); // phpcs:ignore
		}
		return $o;
	}

	private static function post_or_saved( $post_key, $saved ) {
		if ( isset( $_POST[ $post_key ] ) && '' !== trim( (string) $_POST[ $post_key ] ) ) { // phpcs:ignore
			return sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ); // phpcs:ignore
		}
		return $saved;
	}

	public static function ajax_test() {
		self::guard();
		$cfg = AIAP_Providers::config( self::read_override() );
		$r   = AIAP_Providers::chat( 'You are a connection tester.', 'Reply with the single word: OK', array( 'max_tokens' => 600, 'temperature' => 0, 'cfg' => $cfg ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $cfg['label'] . ' / ' . $cfg['model'] . ' → ' . mb_substr( trim( wp_strip_all_tags( $r ) ), 0, 80 ) );
	}

	public static function ajax_models() {
		self::guard();
		$r = AIAP_Providers::list_models( AIAP_Providers::config( self::read_override() ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}

	public static function ajax_yt_test() {
		self::guard();
		$key = self::post_or_saved( 'o_yt_key', AIAP_Settings::get( 'yt_key' ) );
		$r   = AIAP_YouTube::find( 'wordpress seo tutorial', 1, $key );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( 'Key theek hai. Example video: ' . $r[0]['title'] );
	}

	public static function ajax_notify_test() {
		self::guard();
		$s    = AIAP_Settings::all();
		$ch   = isset( $_POST['ch'] ) ? sanitize_key( wp_unslash( $_POST['ch'] ) ) : ''; // phpcs:ignore
		$text = '✅ ' . AIAP_NAME . " test: notification theek chal rahi hai.\n" . get_bloginfo( 'name' );
		if ( 'tg' === $ch ) {
			$hook = esc_url_raw( self::post_or_saved( 'o_tg_webhook', $s['tg_webhook'] ) );
			$ids  = self::post_or_saved( 'o_tg_chat_ids', $s['tg_chat_ids'] );
			if ( $hook ) {
				$r = AIAP_Notify::telegram_webhook( $hook, htmlspecialchars( $text, ENT_NOQUOTES ), $ids );
			} else {
				$r = AIAP_Notify::telegram( htmlspecialchars( $text, ENT_NOQUOTES ), self::post_or_saved( 'o_tg_token', $s['tg_token'] ), $ids, esc_url_raw( self::post_or_saved( 'o_tg_api_base', $s['tg_api_base'] ) ) );
			}
		} elseif ( 'wa' === $ch ) {
			$key = self::post_or_saved( 'o_wa_key', $s['wa_key'] );
			$to  = self::post_or_saved( 'o_wa_to', $s['wa_to'] );
			if ( '' === $key || '' === $to ) {
				wp_send_json_error( 'Agent API key aur Recipient ID dono chahiye (Auto-detect use karo).' );
			}
			$r = AIAP_Notify::whatsapp( $text, $key, $to );
		} else {
			wp_send_json_error( 'Unknown channel.' );
		}
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		$okst        = get_option( 'aiap_alert_ok', array() );
		$okst        = is_array( $okst ) ? $okst : array();
		$okst[ $ch ] = time();
		update_option( 'aiap_alert_ok', $okst, false );
		wp_send_json_success( 'Test message bhej diya. Apna ' . ( 'tg' === $ch ? 'Telegram' : 'WhatsApp' ) . ' check karo.' );
	}

	public static function ajax_you_test() {
		self::guard();
		$key = self::post_or_saved( 'o_you_key', AIAP_Settings::get( 'you_key' ) );
		$r   = AIAP_You::search( 'wordpress seo checklist', 1, $key );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( 'Key theek hai. Example result: ' . $r[0]['title'] );
	}

	public static function ajax_tg_detect() {
		self::guard();
		$s   = AIAP_Settings::all();
		$tok = self::post_or_saved( 'o_tg_token', $s['tg_token'] );
		if ( '' === $tok ) {
			wp_send_json_error( 'Pehle Bot Token daalo.' );
		}
		$r = AIAP_Notify::tg_connect( $tok, esc_url_raw( self::post_or_saved( 'o_tg_api_base', $s['tg_api_base'] ) ) );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}

	public static function ajax_notify_batch() {
		self::guard();
		$ids = array();
		$raw = isset( $_POST['ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ids'] ) ) : ''; // phpcs:ignore
		foreach ( explode( ',', $raw ) as $i ) {
			$i = absint( $i );
			if ( $i && current_user_can( 'edit_post', $i ) ) {
				$ids[] = $i;
			}
		}
		$fails = isset( $_POST['fails'] ) ? json_decode( wp_unslash( $_POST['fails'] ), true ) : array(); // phpcs:ignore
		$fails = is_array( $fails ) ? array_map( 'sanitize_text_field', array_map( 'strval', $fails ) ) : array();
		$err   = AIAP_Notify::summary( $ids, $fails );
		if ( $err ) {
			wp_send_json_error( implode( ' | ', $err ) );
		}
		wp_send_json_success( 'Summary alert bhej di.' );
	}

	public static function ajax_wa_detect() {
		self::guard();
		$key = self::post_or_saved( 'o_wa_key', AIAP_Settings::get( 'wa_key' ) );
		if ( '' === $key ) {
			wp_send_json_error( 'Pehle Agent API key daalo.' );
		}
		$r = AIAP_Notify::wa_detect( $key );
		if ( is_wp_error( $r ) ) {
			wp_send_json_error( $r->get_error_message() );
		}
		wp_send_json_success( $r );
	}
}
