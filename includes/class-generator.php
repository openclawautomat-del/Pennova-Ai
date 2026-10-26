<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Generator {

	/* ---------------- Args ---------------- */

	public static function args( $in = array() ) {
		$s = AIAP_Settings::all();
		$d = array(
			'article_type'   => $s['article_type'],
			'word_count'     => $s['word_count'],
			'language'       => $s['language'],
			'tone'           => $s['tone'],
			'pov'            => $s['pov'],
			'post_status'    => $s['post_status'],
			'post_type'      => $s['post_type'],
			'category'       => $s['category'],
			'schedule_gap'   => $s['schedule_gap'],
			'extra'          => '',
			'source_url'     => '',
			'source_text'    => '',
			'outline_first'  => 0,
			'faq'            => $s['faq'],
			'toc'            => $s['toc'],
			'takeaways'      => $s['takeaways'],
			'internal_links' => $s['internal_links'],
			'inline_images'  => $s['inline_images'],
			'videos'         => $s['videos'],
			'research'       => $s['you_mode'],
			'title'          => '',
			'index'          => 1,
		);
		return array_merge( $d, array_intersect_key( (array) $in, $d ) );
	}

	/* ---------------- Public entry points ---------------- */

	public static function run( $keyword, $a = array() ) {
		$r = self::generate( $keyword, $a );
		AIAP_Admin::log( $keyword, $r );
		// quiet = bulk run (JS ek hi summary bhejta hai).
		if ( ! ( is_array( $a ) && ! empty( $a['quiet'] ) ) ) {
			try {
				AIAP_Notify::post( $keyword, $r );
			} catch ( \Throwable $e ) { // phpcs:ignore
				// Notification fail hone se post ka natija kharab nahi hona chahiye.
			}
		}
		return $r;
	}

	private static function generate( $keyword, $a ) {
		$a       = self::args( $a );
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return new WP_Error( 'aiap', 'Keyword khali hai.' );
		}
		$source = self::source_material( $a );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		// You.com research / SERP insights (fail ho to article phir bhi banta hai).
		$intel = array( 'text' => '', 'sources' => array(), 'note' => '' );
		$ys    = AIAP_Settings::all();
		if ( 'off' !== $a['research'] && $ys['you_key'] ) {
			$ir = AIAP_You::intel( $keyword, $a, $ys );
			if ( is_wp_error( $ir ) ) {
				$intel['note'] = $ir->get_error_message();
			} else {
				$intel = array_merge( $intel, $ir );
			}
		} elseif ( 'off' !== $a['research'] ) {
			$intel['note'] = 'You.com key set nahi hai';
		}
		$system  = self::system( $a );
		$outline = '';
		if ( ! empty( $a['outline_first'] ) ) {
			$o = AIAP_Providers::chat(
				$system,
				"Create a detailed SEO outline for a {$a['article_type']} targeting the keyword \"{$keyword}\" (about {$a['word_count']} words, language: {$a['language']}). Return only a plain list of H2 headings with H3 sub-points under them. No intro text.",
				array( 'max_tokens' => 1200 )
			);
			if ( ! is_wp_error( $o ) ) {
				$outline = trim( $o );
			}
		}
		$raw = AIAP_Providers::chat( $system, self::article_prompt( $keyword, $a, $source, $outline, $intel['text'] ) );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$d = self::parse( $raw );
		if ( empty( $d['TITLE'] ) || empty( $d['CONTENT'] ) ) {
			return new WP_Error( 'aiap', 'AI ka jawab expected format mein nahi aaya (ya cut gaya). Max tokens barhao ya doosra model try karo.' );
		}
		return self::publish( $keyword, $d, $a, $intel );
	}

	public static function ideas( $seed, $language ) {
		$a    = self::args( array( 'language' => $language ) );
		$serp = '';
		$ysk  = AIAP_Settings::get( 'you_key' );
		if ( $ysk ) {
			$sr = AIAP_You::search( $seed, 8, $ysk );
			if ( ! is_wp_error( $sr ) ) {
				$serp = "Pages ranking right now for this topic (use them to spot gaps and real search language):\n";
				foreach ( $sr as $x ) {
					$serp .= '- ' . $x['title'] . "\n";
				}
			}
		}
		$raw = AIAP_Providers::chat(
			'You are an SEO strategist. Return only the requested lines, nothing else.',
			"Seed topic: {$seed}\nKeyword language: {$language}\n{$serp}Give 20 blog keyword ideas: mix of long-tail, question-style and commercial-intent keywords. One per line, format: keyword | intent (informational, commercial or transactional). No numbering. These are ideas only, you do not have search volume data.",
			array( 'max_tokens' => 1200, 'temperature' => 0.8 )
		);
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$out = array();
		foreach ( preg_split( '/\R/', $raw ) as $line ) {
			$line = trim( preg_replace( '/^[\-\*\d\.\)\s]+/', '', $line ) );
			if ( '' === $line ) {
				continue;
			}
			$p     = array_map( 'trim', explode( '|', $line ) );
			$out[] = array( 'kw' => sanitize_text_field( $p[0] ), 'intent' => isset( $p[1] ) ? sanitize_text_field( $p[1] ) : '' );
		}
		return array_slice( $out, 0, 30 );
	}

	/* ---------------- Prompts ---------------- */

	private static function system( $a ) {
		$s = AIAP_Settings::all();
		$t = "You are a senior SEO content writer and editor. Write original, accurate, people-first content that follows Google's E-E-A-T and helpful-content guidance. Never invent statistics, studies, quotes, prices, awards or URLs; when unsure, stay general. Write in {$a['language']}. Tone: {$a['tone']}. Point of view: {$a['pov']}. Return ONLY the tagged format requested, with no markdown code fences and no commentary.";
		if ( $s['brand_voice'] ) {
			$t .= ' Brand voice / business context: ' . $s['brand_voice'];
		}
		if ( $s['custom_instructions'] ) {
			$t .= ' Always follow these rules: ' . $s['custom_instructions'];
		}
		return $t;
	}

	private static function source_material( $a ) {
		$parts = array();
		$ys  = AIAP_Settings::all();
		$got = false;
		if ( '' !== $a['source_url'] && $ys['you_key'] && ! empty( $ys['you_contents'] ) ) {
			$c = AIAP_You::contents( array( $a['source_url'] ), $ys['you_key'] );
			if ( ! is_wp_error( $c ) && $c ) {
				$parts[] = mb_substr( $c[0]['markdown'], 0, 8000 );
				$got     = true;
			}
		}
		if ( '' !== $a['source_url'] && ! $got ) {
			$r = wp_safe_remote_get( esc_url_raw( $a['source_url'] ), array( 'timeout' => 30 ) );
			if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
				return new WP_Error( 'aiap', 'Source URL fetch nahi ho saka.' );
			}
			$html = wp_remote_retrieve_body( $r );
			$html = preg_replace( '#<(script|style|nav|header|footer|aside)\b.*?</\1>#is', ' ', $html );
			$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
			$parts[] = mb_substr( $text, 0, 8000 );
		}
		if ( '' !== $a['source_text'] ) {
			$parts[] = mb_substr( $a['source_text'], 0, 8000 );
		}
		return implode( "\n---\n", $parts );
	}

	private static function internal_links( $kw ) {
		$seen  = array();
		$lines = array();
		$sets  = array(
			get_posts( array( 'numberposts' => 8, 'post_status' => 'publish', 'post_type' => array( 'post', 'page' ), 's' => $kw ) ),
			get_posts( array( 'numberposts' => 30, 'post_status' => 'publish', 'post_type' => 'post', 'orderby' => 'date' ) ),
		);
		foreach ( $sets as $set ) {
			foreach ( $set as $p ) {
				if ( isset( $seen[ $p->ID ] ) || count( $lines ) >= 30 ) {
					continue;
				}
				$seen[ $p->ID ] = 1;
				$lines[]        = $p->post_title . ' — ' . get_permalink( $p );
			}
		}
		return implode( "\n", $lines );
	}

	private static function article_prompt( $kw, $a, $source, $outline, $intel = '' ) {
		$s = AIAP_Settings::all();
		$p = "Primary keyword: {$kw}\nArticle type: {$a['article_type']}\nTarget length: about {$a['word_count']} words for the main body (FAQ and key takeaways not counted).\n";
		if ( '' !== $a['extra'] ) {
			$p .= "Extra instructions: {$a['extra']}\n";
		}
		$p .= "\nSEO rules: put the primary keyword in the title, in the first 100 words, in at least one H2 and in the conclusion; keep natural keyword density around 1%; cover related semantic terms and the search intent; short paragraphs; descriptive H2/H3 headings; use lists or a table where they help; meta title max 60 characters; meta description 140-160 characters including the keyword.\n";
		if ( ! empty( $a['internal_links'] ) ) {
			$links = self::internal_links( $kw );
			if ( $links ) {
				$p .= "\nInternal links: where genuinely relevant, add 2-4 links using ONLY these URLs, written as <a href=\"URL\">descriptive anchor text</a>:\n{$links}\n";
			}
		}
		if ( '' !== $source ) {
			$p .= "\nSource material (use only for ideas and facts, rewrite completely in your own words, never copy sentences):\n{$source}\n";
		}
		if ( '' !== $intel ) {
			$p .= "\n" . $intel . "\n";
		}
		if ( '' !== $outline ) {
			$p .= "\nFollow this outline:\n{$outline}\n";
		}
		$n  = max( 0, (int) $a['inline_images'] );
		$p .= "\nOutput format. Put each tag on its own line, followed by its content, in this order:\n";
		$p .= "[[TITLE]]\nPost title, max 65 characters, includes the keyword\n";
		$p .= "[[SLUG]]\nshort-url-slug-with-keyword (latin letters and hyphens only)\n";
		$p .= "[[META_TITLE]]\nSEO title, max 60 characters\n";
		$p .= "[[META_DESCRIPTION]]\n140-160 characters\n";
		$p .= "[[EXCERPT]]\n1-2 sentence summary\n";
		$p .= "[[TAGS]]\n3-6 comma separated tags\n";
		$p .= "[[IMAGE_QUERIES]]\n" . ( $n + 1 ) . " different ENGLISH stock-photo search queries (2-4 words each), one per line; the first is for the featured image\n";
		if ( (int) $a['videos'] > 0 ) {
			$p .= "[[VIDEO_QUERY]]\n2-6 word YouTube search query that would find helpful, trustworthy videos for this exact topic (write it in the language the audience searches in)\n";
		}
		$p .= "[[LABELS]]\ntoc: 'Table of Contents' translated into the article language\ntakeaways: 'Key Takeaways' translated\nfaq: 'Frequently Asked Questions' translated\nsources: 'Sources' translated\n";
		if ( ! empty( $a['takeaways'] ) ) {
			$p .= "[[KEY_TAKEAWAYS]]\n3-5 lines, each starting with '- '\n";
		}
		$p .= "[[CONTENT]]\nThe article body as clean HTML using only h2, h3, p, ul, ol, li, strong, em, blockquote, table (thead, tbody, tr, th, td) and a tags. Never use h1. Begin with an engaging introduction that contains the primary keyword. Do NOT include a table of contents, key takeaways or FAQ here. End with an h2 Conclusion and a short call to action.\n";
		if ( ! empty( $a['faq'] ) ) {
			$p .= "[[FAQ]]\n" . (int) $s['faq_count'] . " questions real people search for, in this exact form (blank line between pairs):\nQ: question\nA: 2-3 sentence answer\n";
		}
		return $p;
	}

	/* ---------------- Parsing ---------------- */

	private static function parse( $text ) {
		$text = trim( $text );
		preg_match_all( '/\[\[([A-Z_]+)\]\]\s*(.*?)(?=\[\[[A-Z_]+\]\]|\z)/s', $text, $m, PREG_SET_ORDER );
		$o = array();
		foreach ( $m as $x ) {
			$v = trim( $x[2] );
			$v = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', $v );
			$o[ $x[1] ] = trim( $v );
		}
		return $o;
	}

	private static function parse_faq( $text ) {
		$out = array();
		if ( preg_match_all( '/Q:\s*(.+?)\s*\R\s*A:\s*(.+?)(?=\R\s*Q:|\z)/s', $text, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$q = sanitize_text_field( wp_strip_all_tags( $x[1] ) );
				$a = trim( preg_replace( '/\s+/', ' ', wp_kses( $x[2], array( 'a' => array( 'href' => true ), 'strong' => array(), 'em' => array() ) ) ) );
				if ( $q && $a ) {
					$out[] = array( 'q' => $q, 'a' => $a );
				}
			}
		}
		return $out;
	}

	private static function label( $labels, $key, $default ) {
		if ( $labels && preg_match( '/^' . preg_quote( $key, '/' ) . ':\s*(.+)$/mi', $labels, $m ) ) {
			return sanitize_text_field( trim( $m[1], " '\"" ) );
		}
		return $default;
	}

	private static function sources_html( $sources, $heading ) {
		$seen = array();
		$li   = '';
		foreach ( $sources as $sr ) {
			if ( empty( $sr['url'] ) || isset( $seen[ $sr['url'] ] ) ) {
				continue;
			}
			$seen[ $sr['url'] ] = 1;
			$li                .= '<li><a href="' . esc_url( $sr['url'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $sr['title'] ? $sr['title'] : $sr['url'] ) . '</a></li>';
		}
		return $li ? '<h2 id="sources">' . esc_html( $heading ) . '</h2><ul class="aiap-sources">' . $li . '</ul>' : '';
	}

	private static function faq_html( $faq, $heading ) {
		if ( ! $faq ) {
			return '';
		}
		$h = '<h2 id="faq">' . esc_html( $heading ) . '</h2>';
		foreach ( $faq as $f ) {
			$h .= '<h3>' . esc_html( $f['q'] ) . '</h3><p>' . $f['a'] . '</p>';
		}
		return $h;
	}

	/* ---------------- Content helpers ---------------- */

	private static function add_heading_ids( $c ) {
		$toc  = array();
		$seen = array();
		$c    = preg_replace_callback(
			'/<h2([^>]*)>(.*?)<\/h2>/is',
			function ( $m ) use ( &$toc, &$seen ) {
				$text = trim( wp_strip_all_tags( $m[2] ) );
				$id   = sanitize_title( $text );
				if ( '' === $id || false !== strpos( $id, '%' ) ) {
					$id = 'section-' . ( count( $toc ) + 1 );
				}
				$base = $id;
				$i    = 2;
				while ( isset( $seen[ $id ] ) ) {
					$id = $base . '-' . $i++;
				}
				$seen[ $id ] = 1;
				$toc[]       = array( $id, $text );
				return '<h2 id="' . esc_attr( $id ) . '">' . $m[2] . '</h2>';
			},
			$c
		);
		return array( $c, $toc );
	}

	private static function insert_figures( $c, $figs ) {
		$figs = array_values( array_filter( $figs ) );
		if ( ! $figs ) {
			return $c;
		}
		$parts = preg_split( '/(?=<h2\b)/i', $c, -1, PREG_SPLIT_NO_EMPTY );
		$cnt   = count( $parts );
		if ( $cnt < 2 ) {
			return $c . implode( '', $figs );
		}
		$secs = $cnt - 1;
		$n    = count( $figs );
		foreach ( $figs as $j => $f ) {
			$idx            = (int) max( 1, min( $secs, round( $secs * ( $j + 1 ) / ( $n + 1 ) ) ) );
			$parts[ $idx ] .= $f;
		}
		return implode( '', $parts );
	}

	private static function oneline( $t ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $t ) ) );
	}

	private static function fallback_author() {
		$u = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
		return $u ? (int) $u[0] : 1;
	}

	public static function write_seo_meta( $id, $title, $desc, $kw ) {
		update_post_meta( $id, '_aiap_meta_title', $title );
		update_post_meta( $id, '_aiap_meta_description', $desc );
		// Yoast SEO
		update_post_meta( $id, '_yoast_wpseo_title', $title );
		update_post_meta( $id, '_yoast_wpseo_metadesc', $desc );
		update_post_meta( $id, '_yoast_wpseo_focuskw', $kw );
		// Rank Math
		update_post_meta( $id, 'rank_math_title', $title );
		update_post_meta( $id, 'rank_math_description', $desc );
		update_post_meta( $id, 'rank_math_focus_keyword', $kw );
		// All in One SEO
		update_post_meta( $id, '_aioseo_title', $title );
		update_post_meta( $id, '_aioseo_description', $desc );
	}

	/* ---------------- Publish ---------------- */

	private static function publish( $keyword, $d, $a, $intel = array() ) {
		$s = AIAP_Settings::all();

		$title   = ( '' !== $a['title'] ) ? sanitize_text_field( $a['title'] ) : sanitize_text_field( self::oneline( $d['TITLE'] ) );
		$content = wp_kses_post( $d['CONTENT'] );
		// Tables mobile par scroll hon, page ko chorra na karein.
		$content = preg_replace( '/<table\b.*?<\/table>/is', '<div class="aiap-table-wrap">$0</div>', $content );
		list( $content, $toc ) = self::add_heading_ids( $content );

		$labels = isset( $d['LABELS'] ) ? $d['LABELS'] : '';
		$faq    = ( ! empty( $a['faq'] ) && ! empty( $d['FAQ'] ) ) ? self::parse_faq( $d['FAQ'] ) : array();

		$status = $a['post_status'];
		$extra  = array();
		if ( (int) $a['schedule_gap'] > 0 ) {
			$ts                     = time() + (int) $a['schedule_gap'] * HOUR_IN_SECONDS * max( 1, (int) $a['index'] );
			$extra['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $ts );
			$extra['post_date']     = get_date_from_gmt( $extra['post_date_gmt'] );
			$status                 = 'future';
		}
		$author = (int) $s['author'] ? (int) $s['author'] : ( get_current_user_id() ? get_current_user_id() : self::fallback_author() );

		$postarr = array_merge(
			array(
				'post_title'   => wp_slash( $title ),
				'post_content' => wp_slash( $content ),
				'post_excerpt' => wp_slash( isset( $d['EXCERPT'] ) ? sanitize_text_field( self::oneline( $d['EXCERPT'] ) ) : '' ),
				'post_name'    => ! empty( $d['SLUG'] ) ? sanitize_title( self::oneline( $d['SLUG'] ) ) : '',
				'post_status'  => $status,
				'post_type'    => $a['post_type'],
				'post_author'  => $author,
			),
			$extra
		);
		if ( 'post' === $a['post_type'] && (int) $a['category'] ) {
			$postarr['post_category'] = array( (int) $a['category'] );
		}
		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		if ( 'post' === $a['post_type'] && ! empty( $d['TAGS'] ) ) {
			wp_set_post_tags( $post_id, array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $d['TAGS'] ) ) ) );
		}

		// Images.
		$featured   = false;
		$image_note = '';
		$figs       = array();
		if ( 'none' !== $s['image_source'] ) {
			$queries = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', isset( $d['IMAGE_QUERIES'] ) ? $d['IMAGE_QUERIES'] : '' ) ) ) );
			if ( ! $queries ) {
				$queries = array( $keyword );
			}
			$used = array();
			$fid  = AIAP_Images::fetch( $queries[0], $title, $post_id, $used );
			if ( is_wp_error( $fid ) ) {
				$image_note = $fid->get_error_message();
			} else {
				set_post_thumbnail( $post_id, $fid );
				$featured = true;
			}
			$n = min( 5, max( 0, (int) $a['inline_images'] ) );
			for ( $i = 1; $i <= $n; $i++ ) {
				$q   = isset( $queries[ $i ] ) ? $queries[ $i ] : $keyword . ' ' . $i;
				$iid = AIAP_Images::fetch( $q, $keyword . ' - ' . $q, $post_id, $used );
				if ( ! is_wp_error( $iid ) ) {
					$figs[] = AIAP_Images::figure( $iid );
				} elseif ( '' === $image_note ) {
					$image_note = $iid->get_error_message();
				}
			}
		}
		// YouTube videos (shortcode, taake kses strip na kare).
		$vids       = array();
		$video_note = '';
		if ( (int) $a['videos'] > 0 ) {
			if ( ! $s['yt_key'] ) {
				$video_note = 'YouTube key set nahi hai';
			} else {
				$vq    = ! empty( $d['VIDEO_QUERY'] ) ? self::oneline( $d['VIDEO_QUERY'] ) : $keyword;
				$found = AIAP_YouTube::find( $vq, (int) $a['videos'] );
				if ( is_wp_error( $found ) && $vq !== $keyword ) {
					$found = AIAP_YouTube::find( $keyword, (int) $a['videos'] );
				}
				if ( is_wp_error( $found ) ) {
					$video_note = $found->get_error_message();
				} else {
					foreach ( $found as $v ) {
						$vids[] = "\n" . AIAP_YouTube::shortcode_tag( $v ) . "\n";
					}
				}
			}
		}
		// Images aur videos ko baari baari article mein failao.
		$media = array();
		$mx    = max( count( $figs ), count( $vids ) );
		for ( $i = 0; $i < $mx; $i++ ) {
			if ( isset( $figs[ $i ] ) ) {
				$media[] = $figs[ $i ];
			}
			if ( isset( $vids[ $i ] ) ) {
				$media[] = $vids[ $i ];
			}
		}
		$content = self::insert_figures( $content, $media );

		// Takeaways + TOC (pehle H2 se pehle).
		$faq_label = self::label( $labels, 'faq', 'Frequently Asked Questions' );
		if ( $faq ) {
			$toc[] = array( 'faq', $faq_label );
		}
		$top = '';
		if ( ! empty( $a['takeaways'] ) && ! empty( $d['KEY_TAKEAWAYS'] ) ) {
			$items = array();
			foreach ( preg_split( '/\R/', $d['KEY_TAKEAWAYS'] ) as $line ) {
				$line = trim( preg_replace( '/^[\-\*•\s]+/u', '', $line ) );
				if ( '' !== $line ) {
					$items[] = '<li>' . esc_html( $line ) . '</li>';
				}
			}
			if ( $items ) {
				$top .= '<div class="aiap-takeaways"><p><strong>' . esc_html( self::label( $labels, 'takeaways', 'Key Takeaways' ) ) . '</strong></p><ul>' . implode( '', $items ) . '</ul></div>';
			}
		}
		if ( ! empty( $a['toc'] ) && count( $toc ) >= 3 ) {
			$li = '';
			foreach ( $toc as $t ) {
				$li .= '<li><a href="#' . esc_attr( $t[0] ) . '">' . esc_html( $t[1] ) . '</a></li>';
			}
			$top .= '<div class="aiap-toc"><p><strong>' . esc_html( self::label( $labels, 'toc', 'Table of Contents' ) ) . '</strong></p><ul>' . $li . '</ul></div>';
		}
		if ( '' !== $top ) {
			$pos     = stripos( $content, '<h2' );
			$content = ( false === $pos ) ? $content . $top : substr_replace( $content, $top, $pos, 0 );
		}
		$content .= self::faq_html( $faq, $faq_label );
		$src      = ( isset( $intel['sources'] ) && is_array( $intel['sources'] ) ) ? $intel['sources'] : array();
		if ( $src && ! empty( $s['you_sources'] ) ) {
			$content .= self::sources_html( $src, self::label( $labels, 'sources', 'Sources' ) );
		}

		wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $content ) ) );

		// Meta + SEO.
		update_post_meta( $post_id, '_aiap_generated', 1 );
		update_post_meta( $post_id, '_aiap_keyword', $keyword );
		if ( $faq ) {
			update_post_meta( $post_id, '_aiap_faq', $faq );
		}
		$mt = isset( $d['META_TITLE'] ) ? sanitize_text_field( self::oneline( $d['META_TITLE'] ) ) : $title;
		$md = isset( $d['META_DESCRIPTION'] ) ? sanitize_text_field( self::oneline( $d['META_DESCRIPTION'] ) ) : '';
		self::write_seo_meta( $post_id, $mt, $md, $keyword );

		return array(
			'id'         => $post_id,
			'title'      => get_the_title( $post_id ),
			'status'     => get_post_status( $post_id ),
			'edit'       => esc_url_raw( get_edit_post_link( $post_id, 'raw' ) ),
			'image'      => $featured,
			'image_note' => $image_note,
			'words'      => str_word_count( wp_strip_all_tags( $content ) ),
			'sources'    => ( $src && ! empty( $s['you_sources'] ) ) ? count( $src ) : 0,
			'research_note' => isset( $intel['note'] ) ? $intel['note'] : '',
			'videos'     => count( $vids ),
			'video_note' => $video_note,
		);
	}

	/* ---------------- Existing posts ---------------- */

	public static function improve( $post_id, $action ) {
		$p = get_post( $post_id );
		if ( ! $p ) {
			return new WP_Error( 'aiap', 'Post nahi mila.' );
		}
		$a      = self::args( array() );
		$system = self::system( $a );
		$text   = mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $p->post_content ) ) ), 0, 9000 );
		$base   = array( 'id' => $post_id, 'title' => $p->post_title, 'status' => $p->post_status, 'edit' => esc_url_raw( get_edit_post_link( $post_id, 'raw' ) ) );

		if ( 'meta' === $action ) {
			$raw = AIAP_Providers::chat(
				$system,
				"Post title: {$p->post_title}\nContent: {$text}\n\nReturn:\n[[FOCUS_KEYWORD]]\nbest primary keyword for this post\n[[META_TITLE]]\nmax 60 characters\n[[META_DESCRIPTION]]\n140-160 characters, includes the keyword\n[[EXCERPT]]\n1-2 sentences",
				array( 'max_tokens' => 800, 'temperature' => 0.4 )
			);
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			$d = self::parse( $raw );
			if ( empty( $d['META_TITLE'] ) || empty( $d['META_DESCRIPTION'] ) ) {
				return new WP_Error( 'aiap', 'AI ka jawab samajh nahi aaya.' );
			}
			$kw = isset( $d['FOCUS_KEYWORD'] ) ? sanitize_text_field( self::oneline( $d['FOCUS_KEYWORD'] ) ) : '';
			self::write_seo_meta( $post_id, sanitize_text_field( self::oneline( $d['META_TITLE'] ) ), sanitize_text_field( self::oneline( $d['META_DESCRIPTION'] ) ), $kw );
			update_post_meta( $post_id, '_aiap_generated', 1 );
			if ( '' === trim( $p->post_excerpt ) && ! empty( $d['EXCERPT'] ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => wp_slash( sanitize_text_field( self::oneline( $d['EXCERPT'] ) ) ) ) );
			}
			$base['note'] = 'Meta title/description + focus keyword (' . $kw . ') save ho gaye.';
			return $base;
		}

		if ( 'faq' === $action ) {
			$s   = AIAP_Settings::all();
			$raw = AIAP_Providers::chat(
				$system,
				"Post title: {$p->post_title}\nContent: {$text}\n\nReturn:\n[[LABELS]]\nfaq: 'Frequently Asked Questions' translated into the post language\n[[FAQ]]\n" . (int) $s['faq_count'] . " questions real people search for about this topic, exactly as:\nQ: question\nA: 2-3 sentence answer\n(blank line between pairs)",
				array( 'max_tokens' => 1500, 'temperature' => 0.5 )
			);
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			$d   = self::parse( $raw );
			$faq = isset( $d['FAQ'] ) ? self::parse_faq( $d['FAQ'] ) : array();
			if ( ! $faq ) {
				return new WP_Error( 'aiap', 'FAQ parse nahi ho saka.' );
			}
			$html = self::faq_html( $faq, self::label( isset( $d['LABELS'] ) ? $d['LABELS'] : '', 'faq', 'Frequently Asked Questions' ) );
			wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( $p->post_content . $html ) ) );
			update_post_meta( $post_id, '_aiap_faq', $faq );
			update_post_meta( $post_id, '_aiap_generated', 1 );
			$base['note'] = count( $faq ) . ' FAQs post ke end mein add hue (schema ke saath).';
			return $base;
		}

		if ( 'rewrite' === $action ) {
			$html = mb_substr( $p->post_content, 0, 12000 );
			$raw  = AIAP_Providers::chat(
				$system,
				"Improve the following article for SEO and readability. Keep the same topic, facts, language and overall structure; improve clarity, flow, headings and keyword usage; fix errors; do not invent facts. Keep existing images, links and ids. Return ONLY:\n[[CONTENT]]\nthe full improved article as clean HTML\n\nARTICLE:\n{$html}"
			);
			if ( is_wp_error( $raw ) ) {
				return $raw;
			}
			$d = self::parse( $raw );
			if ( empty( $d['CONTENT'] ) ) {
				return new WP_Error( 'aiap', 'AI ka jawab samajh nahi aaya.' );
			}
			wp_update_post( array( 'ID' => $post_id, 'post_content' => wp_slash( wp_kses_post( $d['CONTENT'] ) ) ) );
			$base['note'] = 'Content rewrite ho gaya. Purana version Revisions mein mehfooz hai.';
			return $base;
		}

		return new WP_Error( 'aiap', 'Unknown action.' );
	}
}
