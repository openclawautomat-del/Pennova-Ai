<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Settings {

	const OPT     = 'aiap_settings';
	const SECRETS = array( 'unsplash_key', 'pexels_key', 'img_openai_key', 'yt_key', 'you_key', 'tg_token', 'wa_key' );

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		register_setting( 'aiap_group', self::OPT, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function defaults() {
		return array(
			'provider'            => 'openai',
			'keys'                => array(),
			'models'              => array(),
			'custom_base'         => '',
			'custom_format'       => 'openai',
			'temperature'         => 0.7,
			'max_tokens'          => 8000,
			'brand_voice'         => '',
			'custom_instructions' => '',
			'image_source'        => 'unsplash',
			'unsplash_key'        => '',
			'pexels_key'          => '',
			'img_openai_key'      => '',
			'img_model'           => 'dall-e-3',
			'inline_images'       => 2,
			'videos'              => 1,
			'yt_key'              => '',
			'yt_min_views'        => 500,
			'yt_lang'             => '',
			'you_key'             => '',
			'you_mode'            => 'off',
			'you_effort'          => 'lite',
			'you_contents'        => 1,
			'you_sources'         => 1,
			'post_status'         => 'draft',
			'post_type'           => 'post',
			'category'            => 0,
			'author'              => 0,
			'schedule_gap'        => 0,
			'language'            => 'English',
			'tone'                => 'professional but friendly',
			'pov'                 => 'second person (you)',
			'article_type'        => 'blog post',
			'word_count'          => 1200,
			'toc'                 => 1,
			'takeaways'           => 1,
			'faq'                 => 1,
			'faq_count'           => 5,
			'internal_links'      => 1,
			'schema'              => 1,
			'queue_enabled'       => 0,
			'queue_interval'      => 'daily',
			'queue_per_run'       => 1,
			'tg_enabled'          => 0,
			'tg_token'            => '',
			'tg_chat_ids'         => '',
			'tg_api_base'         => 'https://api.telegram.org',
			'tg_webhook'          => '',
			'wa_enabled'          => 0,
			'wa_key'              => '',
			'wa_to'               => '',
			'notify_fail'         => 0,
			'notify_success'      => 1,
			'notify_bulk_summary' => 1,
			'notify_style'        => 'detailed',
		);
	}

	public static function article_types() {
		return array( 'blog post', 'how-to guide', 'listicle', 'ultimate guide', 'product review', 'comparison (X vs Y)', 'news article', 'case study', 'opinion piece', 'step-by-step tutorial' );
	}

	public static function povs() {
		return array( 'first person singular (I)', 'first person plural (we)', 'second person (you)', 'third person' );
	}

	/* ---- API keys database mein encrypted (AES-256, WordPress salts se) ---- */

	public static function encrypt( $v ) {
		if ( '' === $v || 0 === strpos( $v, 'enc:' ) || ! function_exists( 'openssl_encrypt' ) ) {
			return $v;
		}
		$iv = openssl_random_pseudo_bytes( 16 );
		$ct = openssl_encrypt( $v, 'aes-256-cbc', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, $iv );
		return ( false === $ct ) ? $v : 'enc:' . base64_encode( $iv . $ct ); // phpcs:ignore
	}

	public static function decrypt( $v ) {
		if ( ! is_string( $v ) || 0 !== strpos( $v, 'enc:' ) ) {
			return $v;
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $v, 4 ) ); // phpcs:ignore
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$pt = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', hash( 'sha256', wp_salt( 'auth' ), true ), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return ( false === $pt ) ? '' : $pt;
	}

	public static function all() {
		$o = wp_parse_args( get_option( self::OPT, array() ), self::defaults() );
		if ( is_array( $o['keys'] ) ) {
			foreach ( $o['keys'] as $k => $v ) {
				$o['keys'][ $k ] = self::decrypt( $v );
			}
		}
		foreach ( self::SECRETS as $k ) {
			$o[ $k ] = self::decrypt( $o[ $k ] );
		}
		return $o;
	}

	public static function get( $k ) {
		$a = self::all();
		return isset( $a[ $k ] ) ? $a[ $k ] : null;
	}

	private static function secret_in( $in, $k, $old ) {
		if ( ! empty( $in['clear_secrets'][ $k ] ) ) {
			return '';
		}
		$v = isset( $in[ $k ] ) ? trim( (string) $in[ $k ] ) : '';
		if ( 0 === strpos( $v, 'enc:' ) ) {
			$v = self::decrypt( $v );
		}
		if ( '' === $v ) {
			return $old;
		}
		return ( '-' === $v ) ? '' : sanitize_text_field( $v );
	}

	public static function sanitize( $in ) {
		$in  = is_array( $in ) ? $in : array();
		$old = self::all();
		$d   = self::defaults();
		$out = array();

		$ints  = array( 'category', 'author', 'schedule_gap', 'inline_images', 'videos', 'yt_min_views', 'faq_count', 'queue_per_run', 'max_tokens', 'word_count' );
		$bools = array( 'toc', 'takeaways', 'faq', 'internal_links', 'schema', 'queue_enabled', 'tg_enabled', 'wa_enabled', 'notify_fail', 'notify_success', 'notify_bulk_summary', 'you_contents', 'you_sources' );

		foreach ( $d as $k => $def ) {
			if ( 'keys' === $k || 'models' === $k ) {
				continue;
			}
			if ( in_array( $k, $bools, true ) ) {
				$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
			} elseif ( in_array( $k, $ints, true ) ) {
				$out[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : $def;
			} elseif ( 'temperature' === $k ) {
				$out[ $k ] = isset( $in[ $k ] ) ? max( 0, min( 1.5, (float) $in[ $k ] ) ) : $def;
			} elseif ( in_array( $k, self::SECRETS, true ) ) {
				$out[ $k ] = self::secret_in( $in, $k, $old[ $k ] );
			} elseif ( 'brand_voice' === $k || 'custom_instructions' === $k ) {
				$out[ $k ] = isset( $in[ $k ] ) ? sanitize_textarea_field( $in[ $k ] ) : $def;
			} elseif ( 'custom_base' === $k ) {
				$out[ $k ] = isset( $in[ $k ] ) ? esc_url_raw( trim( $in[ $k ] ) ) : '';
			} else {
				$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : $def;
			}
		}

		// Per-provider keys aur models.
		$out['keys']   = array();
		$out['models'] = array();
		foreach ( AIAP_Providers::presets() as $id => $p ) {
			$nk = isset( $in['keys'][ $id ] ) ? trim( (string) $in['keys'][ $id ] ) : '';
			if ( 0 === strpos( $nk, 'enc:' ) ) {
				$nk = self::decrypt( $nk );
			}
			$ok = isset( $old['keys'][ $id ] ) ? $old['keys'][ $id ] : '';
			if ( ! empty( $in['clear_keys'][ $id ] ) ) {
				$val = '';
			} else {
				$val = ( '' === $nk ) ? $ok : ( '-' === $nk ? '' : sanitize_text_field( $nk ) );
			}
			$out['keys'][ $id ]   = self::encrypt( $val );
			$out['models'][ $id ] = isset( $in['models'][ $id ] ) ? sanitize_text_field( $in['models'][ $id ] ) : '';
		}
		foreach ( self::SECRETS as $sk ) {
			$out[ $sk ] = self::encrypt( $out[ $sk ] );
		}

		$out['tg_chat_ids'] = preg_replace( '/[^0-9A-Za-z_@,\-\s]/', '', $out['tg_chat_ids'] );
		$out['wa_to']       = preg_replace( '/[^0-9A-Za-z_:\-\.@+]/', '', $out['wa_to'] );
		$base               = esc_url_raw( rtrim( trim( $out['tg_api_base'] ), '/' ) );
		$out['tg_api_base'] = $base ? $base : 'https://api.telegram.org';
		$out['tg_webhook']  = esc_url_raw( trim( $out['tg_webhook'] ) );
		$out['yt_lang']     = substr( preg_replace( '/[^a-zA-Z\-]/', '', $out['yt_lang'] ), 0, 5 );

		// Enums / limits.
		if ( ! isset( AIAP_Providers::presets()[ $out['provider'] ] ) ) {
			$out['provider'] = 'openai';
		}
		if ( ! in_array( $out['custom_format'], array( 'openai', 'anthropic' ), true ) ) {
			$out['custom_format'] = 'openai';
		}
		if ( ! in_array( $out['image_source'], array( 'none', 'unsplash', 'pexels', 'openai' ), true ) ) {
			$out['image_source'] = 'unsplash';
		}
		if ( ! in_array( $out['post_status'], array( 'draft', 'pending', 'publish' ), true ) ) {
			$out['post_status'] = 'draft';
		}
		if ( ! in_array( $out['post_type'], array( 'post', 'page' ), true ) ) {
			$out['post_type'] = 'post';
		}
		if ( ! in_array( $out['queue_interval'], array( 'hourly', 'twicedaily', 'daily', 'weekly' ), true ) ) {
			$out['queue_interval'] = 'daily';
		}
		if ( ! in_array( $out['you_mode'], array( 'off', 'serp', 'research' ), true ) ) {
			$out['you_mode'] = 'off';
		}
		if ( ! in_array( $out['you_effort'], array( 'lite', 'standard', 'deep' ), true ) ) {
			$out['you_effort'] = 'lite';
		}
		if ( ! in_array( $out['notify_style'], array( 'detailed', 'compact' ), true ) ) {
			$out['notify_style'] = 'detailed';
		}
		$out['word_count']    = max( 300, min( 5000, $out['word_count'] ) );
		$out['max_tokens']    = max( 1000, min( 32000, $out['max_tokens'] ) );
		$out['inline_images'] = min( 5, $out['inline_images'] );
		$out['videos']        = min( 3, $out['videos'] );
		$out['yt_min_views']  = min( 10000000, $out['yt_min_views'] );
		$out['faq_count']     = max( 1, min( 10, $out['faq_count'] ) );
		$out['queue_per_run'] = max( 1, min( 5, $out['queue_per_run'] ) );
		return $out;
	}

	/* ====================== Page helpers (return HTML strings) ====================== */

	private static function f_select( $name, $opts, $cur ) {
		$h = '<select name="' . esc_attr( self::OPT . '[' . $name . ']' ) . '">';
		foreach ( $opts as $v => $l ) {
			$h .= '<option value="' . esc_attr( $v ) . '" ' . selected( (string) $cur, (string) $v, false ) . '>' . esc_html( $l ) . '</option>';
		}
		return $h . '</select>';
	}

	private static function f_text( $name, $s, $type = 'text', $attrs = '' ) {
		return '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( self::OPT . '[' . $name . ']' ) . '" value="' . esc_attr( $s[ $name ] ) . '" ' . $attrs . '>';
	}

	private static function f_secret( $name, $s, $ph = 'API key' ) {
		$h = '<input type="password" autocomplete="off" name="' . esc_attr( self::OPT . '[' . $name . ']' ) . '" value="" placeholder="' . esc_attr( $s[ $name ] ? 'saved ✓  (blank = same rakho)' : $ph ) . '">';
		if ( $s[ $name ] ) {
			$h .= '<label class="pn-clear"><input type="checkbox" name="' . esc_attr( self::OPT . '[clear_secrets][' . $name . ']' ) . '" value="1"> key hatao</label>';
		}
		return $h;
	}

	private static function f_switch( $name, $s, $label, $hint = '' ) {
		return '<label class="pn-sw"><input type="checkbox" name="' . esc_attr( self::OPT . '[' . $name . ']' ) . '" value="1" ' . checked( ! empty( $s[ $name ] ), true, false ) . '><span class="pn-sw-t"></span><span class="pn-sw-l"><b>' . esc_html( $label ) . '</b>' . ( $hint ? '<small>' . esc_html( $hint ) . '</small>' : '' ) . '</span></label>';
	}

	private static function row( $label, $html, $hint = '' ) {
		echo '<div class="pn-row"><div class="pn-row-l"><label>' . esc_html( $label ) . '</label>' . ( $hint ? '<small>' . wp_kses_post( $hint ) . '</small>' : '' ) . '</div><div class="pn-row-f">' . $html . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ====================== Page ====================== */

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s       = self::all();
		$n       = self::OPT;
		$presets = AIAP_Providers::presets();
		$users   = get_users( array( 'capability' => 'edit_posts', 'fields' => array( 'ID', 'display_name' ) ) );
		$panes   = array(
			'provider' => array( 'AI Provider', '🤖' ),
			'writing'  => array( 'Writing', '✍️' ),
			'seo'      => array( 'SEO', '🔎' ),
			'research' => array( 'Research (You.com)', '🧠' ),
			'media'    => array( 'Images & Videos', '🖼️' ),
			'publish'  => array( 'Publishing', '🚀' ),
			'auto'     => array( 'Auto-pilot', '⚡' ),
			'alerts'   => array( 'Alerts', '🔔' ),
		);

		AIAP_Admin::shell_open( 'aiap-settings', 'Settings', 'Provider, SEO, media aur alerts, sab ek jagah.' );
		?>
		<form method="post" action="options.php" class="pn-settings">
			<?php settings_fields( 'aiap_group' ); ?>
			<div class="pn-split">
				<nav class="pn-subnav" aria-label="Settings sections">
					<?php foreach ( $panes as $id => $p ) : ?>
						<button type="button" data-pane="<?php echo esc_attr( $id ); ?>"><span><?php echo esc_html( $p[1] ); ?></span> <?php echo esc_html( $p[0] ); ?></button>
					<?php endforeach; ?>
				</nav>

				<div class="pn-panes">

					<!-- ============ PROVIDER ============ -->
					<section class="pn-pane" id="pane-provider">
						<div class="pn-card">
							<h2>AI Provider</h2>
							<p class="pn-sub">Jis AI se articles likhwane hain usay chuno. OpenRouter ya Custom se koi bhi model chal sakta hai.</p>
							<div class="pn-tiles">
								<?php foreach ( $presets as $id => $p ) : ?>
									<label class="pn-tile" style="--h:<?php echo (int) ( abs( crc32( $id ) ) % 360 ); ?>">
										<input type="radio" name="<?php echo esc_attr( $n ); ?>[provider]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $s['provider'], $id ); ?>>
										<span class="pn-mono"><?php echo esc_html( strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $p['label'] ), 0, 2 ) ) ); ?></span>
										<span class="pn-tile-l"><?php echo esc_html( $p['label'] ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>

						<?php foreach ( $presets as $id => $p ) : ?>
							<div class="pn-card aiap-prov" data-p="<?php echo esc_attr( $id ); ?>">
								<h3><?php echo esc_html( $p['label'] ); ?> setup</h3>
								<?php
								if ( 'custom' === $id ) {
									self::row( 'Base URL', '<input type="url" name="' . esc_attr( $n ) . '[custom_base]" value="' . esc_attr( $s['custom_base'] ) . '" placeholder="https://api.example.com/v1">', 'Jahan <code>/chat/completions</code> (ya <code>/messages</code>) lagta ho, us se pehle tak ka URL.' );
									self::row( 'API format', self::f_select( 'custom_format', array( 'openai' => 'OpenAI-compatible (chat/completions)', 'anthropic' => 'Anthropic-compatible (messages)' ), $s['custom_format'] ) );
								}
								$has  = ! empty( $s['keys'][ $id ] );
								$keyh = '<input type="password" autocomplete="off" name="' . esc_attr( $n ) . '[keys][' . esc_attr( $id ) . ']" value="" placeholder="' . esc_attr( $has ? 'saved ✓  (blank = same rakho)' : ( ! empty( $p['nokey'] ) ? 'optional' : 'API key' ) ) . '">';
								if ( $has ) {
									$keyh .= '<label class="pn-clear"><input type="checkbox" name="' . esc_attr( $n ) . '[clear_keys][' . esc_attr( $id ) . ']" value="1"> key hatao</label>';
								}
								if ( ! empty( $p['keyurl'] ) ) {
									$keyh .= '<a class="pn-link" href="' . esc_url( $p['keyurl'] ) . '" target="_blank" rel="noopener">Key kahan se milegi? ↗</a>';
								}
								self::row( 'API key', $keyh );
								$dl = '<datalist id="aiap-models-' . esc_attr( $id ) . '">';
								foreach ( $p['models'] as $m ) {
									$dl .= '<option value="' . esc_attr( $m ) . '"></option>';
								}
								$dl .= '</datalist>';
								$mh  = '<input type="text" list="aiap-models-' . esc_attr( $id ) . '" name="' . esc_attr( $n ) . '[models][' . esc_attr( $id ) . ']" value="' . esc_attr( isset( $s['models'][ $id ] ) ? $s['models'][ $id ] : '' ) . '" placeholder="' . esc_attr( $p['model'] ) . '">' . $dl;
								$mh .= '<button type="button" class="pn-btn ghost sm aiap-fetch" data-p="' . esc_attr( $id ) . '">Models fetch karo</button>';
								self::row( 'Model', $mh, 'Blank = default <code>' . esc_html( $p['model'] ? $p['model'] : '-' ) . '</code>. Koi bhi model ka naam type kar sakte ho.' );
								?>
							</div>
						<?php endforeach; ?>

						<div class="pn-card">
							<h3>Generation</h3>
							<?php
							self::row( 'Temperature', '<input type="number" step="0.1" min="0" max="1.5" name="' . esc_attr( $n ) . '[temperature]" value="' . esc_attr( $s['temperature'] ) . '">', '0 = precise, 1+ = creative.' );
							self::row( 'Max output tokens', '<input type="number" min="1000" max="32000" step="500" name="' . esc_attr( $n ) . '[max_tokens]" value="' . esc_attr( $s['max_tokens'] ) . '">', 'Lambi articles aur reasoning models ke liye 12000-16000 rakho.' );
							?>
							<div class="pn-actions">
								<button type="button" class="pn-btn ghost" id="aiap-test">⚡ Connection test</button>
								<span id="aiap-test-out" class="pn-out"></span>
							</div>
							<p class="pn-note">API keys database mein encrypted save hoti hain. Test Save kiye bina bhi form ki current values se chalta hai.</p>
						</div>
					</section>

					<!-- ============ WRITING ============ -->
					<section class="pn-pane" id="pane-writing">
						<div class="pn-card">
							<h2>Writing defaults</h2>
							<?php
							self::row( 'Article type', self::f_select( 'article_type', array_combine( self::article_types(), self::article_types() ), $s['article_type'] ) );
							self::row( 'Language', self::f_text( 'language', $s ), 'English, Urdu, Roman Urdu, Arabic, Hindi, Spanish...' );
							self::row( 'Tone', self::f_text( 'tone', $s ) );
							self::row( 'Point of view', self::f_select( 'pov', array_combine( self::povs(), self::povs() ), $s['pov'] ) );
							self::row( 'Word count', '<input type="number" min="300" max="5000" step="100" name="' . esc_attr( $n ) . '[word_count]" value="' . esc_attr( $s['word_count'] ) . '">' );
							self::row( 'Brand voice / business info', '<textarea rows="3" name="' . esc_attr( $n ) . '[brand_voice]" placeholder="Jaise: Hum Karachi ki solar company hain, simple Urdu-English mix mein baat karte hain...">' . esc_textarea( $s['brand_voice'] ) . '</textarea>' );
							self::row( 'Hamesha follow karne wali rules', '<textarea rows="3" name="' . esc_attr( $n ) . '[custom_instructions]" placeholder="Jaise: Short paragraphs. Kabhi &quot;in conclusion&quot; mat likho.">' . esc_textarea( $s['custom_instructions'] ) . '</textarea>' );
							?>
						</div>
					</section>

					<!-- ============ SEO ============ -->
					<section class="pn-pane" id="pane-seo">
						<div class="pn-card">
							<h2>SEO features</h2>
							<p class="pn-sub">Meta title/description + focus keyword Yoast, Rank Math aur AIOSEO ke fields mein khud save hote hain.</p>
							<?php
							echo self::f_switch( 'toc', $s, 'Table of Contents', 'H2 headings se clickable fehrist.' ); // phpcs:ignore
							echo self::f_switch( 'takeaways', $s, 'Key Takeaways box', 'Article ke shuru mein 3-5 nukaat.' ); // phpcs:ignore
							echo self::f_switch( 'faq', $s, 'FAQ section + schema', 'Google rich results ke liye FAQPage schema.' ); // phpcs:ignore
							echo self::f_switch( 'internal_links', $s, 'Internal links', 'Aapki existing posts se 2-4 links.' ); // phpcs:ignore
							echo self::f_switch( 'schema', $s, 'Schema markup', 'FAQPage; Article sirf tab jab koi SEO plugin active na ho.' ); // phpcs:ignore
							self::row( 'FAQ count', '<input type="number" min="1" max="10" name="' . esc_attr( $n ) . '[faq_count]" value="' . esc_attr( $s['faq_count'] ) . '">' );
							?>
						</div>
					</section>

					<!-- ============ RESEARCH (You.com) ============ -->
					<section class="pn-pane" id="pane-research">
						<div class="pn-card">
							<h2>You.com research <span class="pn-badge">NEW</span></h2>
							<p class="pn-sub">Live web research se articles zyada sahi aur fresh bante hain, aur SERP dekh kar outline behtar hota hai.</p>
							<?php
							self::row( 'You.com API key', self::f_secret( 'you_key', $s ) . '<a class="pn-link" href="https://you.com/platform" target="_blank" rel="noopener">Key banao ↗</a>', 'Pay-as-you-go API key (header <code>X-API-Key</code>).' );
							self::row(
								'Default research mode',
								self::f_select(
									'you_mode',
									array(
										'off'      => 'Off',
										'serp'     => 'SERP insights (top ranking pages dekh kar behtar outline)',
										'research' => 'Cited research (web research + article ke end mein Sources list)',
									),
									$s['you_mode']
								),
								'Writer mein har run ke liye alag se bhi chun sakte ho.'
							);
							self::row( 'Research depth', self::f_select( 'you_effort', array( 'lite' => 'Lite (tez, sasta)', 'standard' => 'Standard (balanced)', 'deep' => 'Deep (zyada gehra, zyada waqt)' ), $s['you_effort'] ), 'Docs ke mutabiq Lite aam taur par 10 second se kam leta hai. Deep mein kai minute lag sakte hain aur shared hosting par timeout ho sakta hai.' );
							echo self::f_switch( 'you_sources', $s, 'Article ke end mein Sources list', 'Sirf "Cited research" mode mein, jin pages se research hui unke links.' ); // phpcs:ignore
							echo self::f_switch( 'you_contents', $s, 'Source URL ke liye You.com Contents API', 'Writer ke "Source URL" ka text saaf Markdown mein aata hai (JS wale pages bhi).' ); // phpcs:ignore
							?>
							<div class="pn-actions">
								<button type="button" class="pn-btn ghost" id="aiap-you-test">🧠 You.com key test</button>
								<span id="aiap-you-out" class="pn-out"></span>
							</div>
							<p class="pn-note">You.com docs ke mutabiq: Search lagbhag $5 per 1,000 calls, Contents $1 per 1,000 pages, Research Lite $12 per 1,000 se shuru (deeper levels zyada). Taaza rates aur apna usage you.com/platform par dekho. Research mode har article par ek research request karta hai.</p>
						</div>
					</section>

					<!-- ============ MEDIA ============ -->
					<section class="pn-pane" id="pane-media">
						<div class="pn-card">
							<h2>Images</h2>
							<?php
							self::row( 'Image source', self::f_select( 'image_source', array( 'none' => 'Koi image nahi', 'unsplash' => 'Unsplash (free stock)', 'pexels' => 'Pexels (free stock)', 'openai' => 'AI generated (OpenAI Images)' ), $s['image_source'] ) );
							self::row( 'Unsplash Access Key', self::f_secret( 'unsplash_key', $s ) . '<a class="pn-link" href="https://unsplash.com/developers" target="_blank" rel="noopener">Key banao ↗</a>' );
							self::row( 'Pexels API Key', self::f_secret( 'pexels_key', $s ) . '<a class="pn-link" href="https://www.pexels.com/api/" target="_blank" rel="noopener">Key banao ↗</a>' );
							self::row( 'OpenAI Images key', self::f_secret( 'img_openai_key', $s ), 'Blank = OpenAI provider wali key.' );
							self::row( 'Image model', self::f_text( 'img_model', $s ), 'dall-e-3 ya gpt-image-1' );
							self::row( 'Article ke andar images', '<input type="number" min="0" max="5" name="' . esc_attr( $n ) . '[inline_images]" value="' . esc_attr( $s['inline_images'] ) . '">', 'Featured image alag se lagti hai. Sab images mobile par responsive hoti hain.' );
							?>
						</div>
						<div class="pn-card">
							<h2>YouTube videos <span class="pn-badge">NEW</span></h2>
							<p class="pn-sub">Keyword ke hisaab se relevant, embeddable video dhoondh kar article mein khud lagti hai (click-to-play, tez loading).</p>
							<?php
							self::row( 'Videos per article', '<input type="number" min="0" max="3" name="' . esc_attr( $n ) . '[videos]" value="' . esc_attr( $s['videos'] ) . '">', '0 = band.' );
							self::row( 'YouTube Data API key', self::f_secret( 'yt_key', $s ) . '<a class="pn-link" href="https://console.cloud.google.com/apis/library/youtube.googleapis.com" target="_blank" rel="noopener">Enable karo ↗</a>', 'Google Cloud → YouTube Data API v3 enable → API key. Key ki restriction "None" ya "IP" rakho.' );
							self::row( 'Minimum views', '<input type="number" min="0" step="100" name="' . esc_attr( $n ) . '[yt_min_views]" value="' . esc_attr( $s['yt_min_views'] ) . '">', 'Is se kam views wali videos skip.' );
							self::row( 'Video language', self::f_text( 'yt_lang', $s, 'text', 'maxlength="5" placeholder="en, ur, hi..."' ), 'Optional. Is zubaan ki videos ko tarjeeh.' );
							?>
							<div class="pn-actions">
								<button type="button" class="pn-btn ghost" id="aiap-yt-test">▶ YouTube key test</button>
								<span id="aiap-yt-out" class="pn-out"></span>
							</div>
							<p class="pn-note">Free quota 10,000 units/din hai; har naye search ka 100 units, to roz lagbhag 100 articles. Results 12 ghante cache hote hain.</p>
						</div>
					</section>

					<!-- ============ PUBLISH ============ -->
					<section class="pn-pane" id="pane-publish">
						<div class="pn-card">
							<h2>Publishing defaults</h2>
							<?php
							self::row( 'Post status', self::f_select( 'post_status', array( 'draft' => 'Draft', 'pending' => 'Pending review', 'publish' => 'Publish' ), $s['post_status'] ) );
							self::row( 'Post type', self::f_select( 'post_type', array( 'post' => 'Post', 'page' => 'Page' ), $s['post_type'] ) );
							ob_start();
							wp_dropdown_categories( array( 'name' => $n . '[category]', 'selected' => (int) $s['category'], 'hide_empty' => 0, 'show_option_none' => 'Default', 'option_none_value' => 0 ) );
							self::row( 'Category', ob_get_clean() );
							$au = '<select name="' . esc_attr( $n ) . '[author]"><option value="0">Jo user generate kare</option>';
							foreach ( $users as $u ) {
								$au .= '<option value="' . (int) $u->ID . '" ' . selected( (int) $s['author'], (int) $u->ID, false ) . '>' . esc_html( $u->display_name ) . '</option>';
							}
							self::row( 'Author', $au . '</select>' );
							self::row( 'Schedule gap (ghante)', '<input type="number" min="0" name="' . esc_attr( $n ) . '[schedule_gap]" value="' . esc_attr( $s['schedule_gap'] ) . '">', '0 = foran. 24 = har post pichli se 24 ghante baad schedule.' );
							?>
						</div>
					</section>

					<!-- ============ AUTO ============ -->
					<section class="pn-pane" id="pane-auto">
						<div class="pn-card">
							<h2>Auto-pilot queue</h2>
							<p class="pn-sub">Keywords queue mein daalo, plugin khud waqt par articles banata rahega.</p>
							<?php
							echo self::f_switch( 'queue_enabled', $s, 'Auto-pilot on', 'Queue ke keywords se automatically posts.' ); // phpcs:ignore
							self::row( 'Kitni baar', self::f_select( 'queue_interval', array( 'hourly' => 'Har ghante', 'twicedaily' => 'Har 12 ghante', 'daily' => 'Roz', 'weekly' => 'Har hafte' ), $s['queue_interval'] ) );
							self::row( 'Har run mein posts', '<input type="number" min="1" max="5" name="' . esc_attr( $n ) . '[queue_per_run]" value="' . esc_attr( $s['queue_per_run'] ) . '">' );
							?>
							<p class="pn-note">WP-Cron site visitors par chalta hai. Pakki timing ke liye server cron se <code>wp-cron.php</code> hit karwao. Free hostings par lambi AI requests timeout ho sakti hain.</p>
						</div>
					</section>

					<!-- ============ ALERTS ============ -->
					<section class="pn-pane" id="pane-alerts">
						<?php
						$okst = get_option( 'aiap_alert_ok', array() );
						$okst = is_array( $okst ) ? $okst : array();
						$pill = function ( $k ) use ( $okst ) {
							return isset( $okst[ $k ] )
								? '<span class="pn-status ok">✅ Connected · test ' . esc_html( wp_date( 'd M, H:i', (int) $okst[ $k ] ) ) . '</span>'
								: '<span class="pn-status">Abhi test nahi hua</span>';
						};
						?>
						<div class="pn-card">
							<div class="pn-between"><h2>✈ Telegram alerts</h2><?php echo $pill( 'tg' ); // phpcs:ignore ?></div>
							<ol class="pn-steps">
								<li><a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a> kholo → <code>/newbot</code> → bot ka <b>token</b> copy karo.</li>
								<li>Apne naye bot ko Telegram mein kholo aur <b>Start</b> dabao.</li>
								<li>Neeche token paste karo → <b>Chat ID dhoondo</b> dabao → Save → Test.</li>
							</ol>
							<?php
							echo self::f_switch( 'tg_enabled', $s, 'Telegram alerts on', 'Band karne par bhi settings save rehti hain.' ); // phpcs:ignore
							self::row( 'Bot Token', self::f_secret( 'tg_token', $s, '123456:ABC...' ) );
							self::row( 'Aapki Chat ID', self::f_text( 'tg_chat_ids', $s, 'text', 'placeholder="123456789"' ) . '<button type="button" class="pn-btn ghost sm" id="aiap-tg-detect">🔍 Chat ID dhoondo</button><div id="aiap-tg-chats" class="pn-chats"></div>', 'Group ke liye ID <code>-100...</code> hoti hai. Kai IDs comma se alag karo.' );
							?>
							<div class="pn-actions">
								<button type="button" class="pn-btn ghost" id="aiap-tg-test">📨 Telegram test bhejo</button>
								<span id="aiap-tg-out" class="pn-out"></span>
							</div>
							<details class="pn-details">
								<summary>🔧 Advanced: server Telegram block karta hai? (relay)</summary>
								<?php
								self::row( 'Webhook relay URL (n8n)', self::f_text( 'tg_webhook', $s, 'url', 'placeholder="https://n8n.example.com/webhook/..."' ), 'Hosting Telegram block kare (jaise InfinityFree) to apne n8n ka webhook yahan daalo. Tab Bot Token ki zaroorat nahi. Dekho: docs/telegram-n8n-relay.md' );
								self::row( 'API Base URL', self::f_text( 'tg_api_base', $s, 'url', 'placeholder="https://api.telegram.org"' ), 'Normal case mein default rehne do.' );
								?>
							</details>
						</div>

						<div class="pn-card">
							<div class="pn-between"><h2>💬 WhatsApp alerts</h2><?php echo $pill( 'wa' ); // phpcs:ignore ?></div>
							<ol class="pn-steps">
								<li>WhatsApp → Settings → <b>Agents</b> → apna agent kholo → Chat info se <b>API key</b> copy karo.</li>
								<li>Apne agent ko WhatsApp mein ek message bhejo (jaise "hi").</li>
								<li>Neeche key paste karo → <b>Auto-detect</b> dabao → Save → Test.</li>
							</ol>
							<?php
							echo self::f_switch( 'wa_enabled', $s, 'WhatsApp alerts on', 'Meta ke WhatsApp Agent API se.' ); // phpcs:ignore
							self::row( 'Agent API key', self::f_secret( 'wa_key', $s ) );
							self::row( 'Recipient ID', self::f_text( 'wa_to', $s, 'text', 'placeholder="user:xxxxxxxx"' ) . '<button type="button" class="pn-btn ghost sm" id="aiap-wa-detect">🔍 Auto-detect</button>', 'Meta ka agent sirf apne creator (aap) ko message bhej sakta hai, aur recipient phone number se nahi balke <code>user:&lt;id&gt;</code> se pehchana jata hai.' );
							?>
							<div class="pn-actions">
								<button type="button" class="pn-btn ghost" id="aiap-wa-test">📨 WhatsApp test bhejo</button>
								<span id="aiap-wa-out" class="pn-out"></span>
							</div>
						</div>

						<div class="pn-card">
							<h3>Alert kab aur kaise?</h3>
							<?php
							echo self::f_switch( 'notify_success', $s, 'Har nayi post par alert', 'Post ban jane par title aur link.' ); // phpcs:ignore
							echo self::f_switch( 'notify_fail', $s, 'Fail hone par bhi batao', 'Post nahi ban saki to wajah ke saath alert.' ); // phpcs:ignore
							echo self::f_switch( 'notify_bulk_summary', $s, 'Bulk mein ek hi summary bhejo', 'Kai keywords ek saath chalao to har post ka alag message nahi, bas ek summary (WhatsApp ki limit se bhi bachta hai).' ); // phpcs:ignore
							self::row( 'Message ka andaz', self::f_select( 'notify_style', array( 'detailed' => 'Detailed (title, status, words, image, videos, links)', 'compact' => 'Compact (title + link)' ), $s['notify_style'] ) );
							$last = get_option( 'aiap_notify_last', '' );
							if ( is_array( $last ) && ! empty( $last['msg'] ) ) {
								echo '<p class="pn-err">Aakhri alert error (' . esc_html( wp_date( 'd M H:i', $last['t'] ) ) . '): ' . esc_html( $last['msg'] ) . '</p>';
							}
							?>
						</div>
					</section>

				</div>
			</div>

			<div class="pn-savebar">
				<span>Badlav Save karna na bhoolo.</span>
				<?php submit_button( 'Settings save karo', 'primary pn-btn', 'submit', false ); ?>
			</div>
		</form>
		<?php
		AIAP_Admin::shell_close();
	}
}
