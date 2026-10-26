<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Frontend {

	private static $yt_used = false;

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ), 20 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ), 20 );
		add_shortcode( 'aiap_youtube', array( __CLASS__, 'shortcode' ) );
	}

	public static function has_seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
	}

	public static function title( $t ) {
		if ( ! is_singular() || self::has_seo_plugin() ) {
			return $t;
		}
		$m = get_post_meta( get_the_ID(), '_aiap_meta_title', true );
		return $m ? $m : $t;
	}

	/**
	 * Responsive YouTube: pehle thumbnail (tez), click par iframe (youtube-nocookie).
	 */
	public static function shortcode( $atts ) {
		$a  = shortcode_atts( array( 'id' => '', 'title' => 'Video', 'channel' => '' ), $atts, 'aiap_youtube' );
		$id = preg_replace( '/[^A-Za-z0-9_-]/', '', $a['id'] );
		if ( 11 !== strlen( $id ) ) {
			return '';
		}
		self::$yt_used = true;
		$watch         = 'https://www.youtube.com/watch?v=' . $id;
		$title         = $a['title'] ? $a['title'] : 'Video';
		$h             = '<figure class="aiap-yt-wrap">';
		$h            .= '<div class="aiap-yt" data-id="' . esc_attr( $id ) . '" data-title="' . esc_attr( $title ) . '">';
		$h            .= '<a class="aiap-yt-link" href="' . esc_url( $watch ) . '" target="_blank" rel="noopener">';
		$h            .= '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" decoding="async">';
		$h            .= '<span class="aiap-yt-play" aria-hidden="true"></span><span class="aiap-sr">' . esc_html( 'Play: ' . $title ) . '</span></a></div>';
		$h            .= '<figcaption>▶ <a href="' . esc_url( $watch ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>' . ( $a['channel'] ? ' · ' . esc_html( $a['channel'] ) : '' ) . '</figcaption></figure>';
		return $h;
	}

	public static function footer() {
		if ( ! self::$yt_used ) {
			return;
		}
		echo '<script id="aiap-yt-js">(function(){document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest(".aiap-yt a.aiap-yt-link");if(!a){return;}e.preventDefault();var w=a.parentNode,id=w.getAttribute("data-id"),f=document.createElement("iframe");f.src="https://www.youtube-nocookie.com/embed/"+id+"?autoplay=1&rel=0&modestbranding=1";f.title=w.getAttribute("data-title")||"YouTube video";f.allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share";f.setAttribute("allowfullscreen","");w.innerHTML="";w.appendChild(f);});})();</script>' . "\n";
	}

	private static function css() {
		return '.aiap-toc,.aiap-takeaways{border:1px solid rgba(128,128,128,.35);background:rgba(128,128,128,.07);padding:1em 1.25em;margin:1.5em 0;border-radius:8px;max-width:100%}'
			. '.aiap-toc ul,.aiap-takeaways ul{margin:.5em 0 0 1.2em}'
			. '.aiap-figure{margin:1.75em 0;max-width:100%}'
			. '.aiap-figure img{display:block;max-width:100%!important;width:auto;height:auto!important;margin:0 auto;border-radius:8px}'
			. '.aiap-figure figcaption{font-size:.8em;opacity:.75;text-align:center;margin-top:.5em}'
			. '.aiap-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;margin:1.5em 0}'
			. '.aiap-table-wrap table{width:100%;min-width:480px;margin:0}'
			. '.aiap-yt-wrap{margin:1.75em 0;max-width:100%}'
			. '.aiap-yt{position:relative;width:100%;aspect-ratio:16/9;background:#000;border-radius:10px;overflow:hidden}'
			. '.aiap-yt img,.aiap-yt iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0;object-fit:cover;margin:0;max-width:none}'
			. '.aiap-yt-link{display:block;width:100%;height:100%}'
			. '.aiap-yt-play{position:absolute;left:50%;top:50%;width:68px;height:48px;margin:-24px 0 0 -34px;background:#f00;border-radius:12px;transition:transform .15s}'
			. '.aiap-yt-play::after{content:"";position:absolute;left:27px;top:14px;border-style:solid;border-width:10px 0 10px 18px;border-color:transparent transparent transparent #fff}'
			. '.aiap-yt-link:hover .aiap-yt-play{transform:scale(1.08)}'
			. '.aiap-yt-wrap figcaption{font-size:.85em;opacity:.8;margin-top:.5em;text-align:center}'
			. '.aiap-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}'
			. '@media(max-width:600px){.aiap-yt-play{width:56px;height:40px;margin:-20px 0 0 -28px}.aiap-yt-play::after{left:22px;top:11px;border-width:9px 0 9px 15px}}';
	}

	public static function head() {
		if ( ! is_singular() ) {
			return;
		}
		$id  = get_the_ID();
		$gen = (bool) get_post_meta( $id, '_aiap_generated', true );
		if ( ! $gen && ! has_shortcode( (string) get_post_field( 'post_content', $id ), 'aiap_youtube' ) ) {
			return;
		}

		echo '<style id="aiap-css">' . self::css() . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( ! $gen ) {
			return;
		}

		if ( ! self::has_seo_plugin() ) {
			$desc = get_post_meta( $id, '_aiap_meta_description', true );
			if ( $desc ) {
				echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
			}
		}

		if ( ! AIAP_Settings::get( 'schema' ) ) {
			return;
		}
		$schemas = array();
		$faq     = get_post_meta( $id, '_aiap_faq', true );
		if ( is_array( $faq ) && $faq ) {
			$entities = array();
			foreach ( $faq as $f ) {
				$entities[] = array(
					'@type'          => 'Question',
					'name'           => $f['q'],
					'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $f['a'] ) ),
				);
			}
			$schemas[] = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities );
		}
		if ( ! self::has_seo_plugin() ) {
			$post    = get_post( $id );
			$article = array(
				'@context'         => 'https://schema.org',
				'@type'            => 'Article',
				'headline'         => get_the_title( $id ),
				'description'      => get_post_meta( $id, '_aiap_meta_description', true ),
				'datePublished'    => get_the_date( 'c', $id ),
				'dateModified'     => get_the_modified_date( 'c', $id ),
				'mainEntityOfPage' => get_permalink( $id ),
				'author'           => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ),
			);
			$img     = get_the_post_thumbnail_url( $id, 'full' );
			if ( $img ) {
				$article['image'] = $img;
			}
			$schemas[] = $article;
		}
		foreach ( $schemas as $sc ) {
			echo '<script type="application/ld+json">' . str_replace( '</', '<\/', wp_json_encode( $sc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
