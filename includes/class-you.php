<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * You.com APIs: Web Search, Contents, Research (auth header: X-API-Key).
 * Search/Contents: https://ydc-index.io   Research: https://api.you.com
 */
class AIAP_You {

	const IDX = 'https://ydc-index.io';
	const API = 'https://api.you.com';

	private static function call( $base, $path, $body, $key, $timeout = 30 ) {
		if ( '' === trim( (string) $key ) ) {
			return new WP_Error( 'aiap_you', 'You.com API key set nahi hai.' );
		}
		$resp = wp_remote_post(
			$base . $path,
			array(
				'timeout' => $timeout,
				'headers' => array( 'X-API-Key' => trim( $key ), 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = 'HTTP ' . $code;
			if ( is_array( $json ) ) {
				if ( isset( $json['message'] ) && is_string( $json['message'] ) ) {
					$msg .= ': ' . $json['message'];
				} elseif ( isset( $json['detail'] ) && is_string( $json['detail'] ) ) {
					$msg .= ': ' . $json['detail'];
				} elseif ( isset( $json['error'] ) ) {
					$msg .= ': ' . ( is_string( $json['error'] ) ? $json['error'] : wp_json_encode( $json['error'] ) );
				}
			} else {
				$msg .= ' ' . mb_substr( wp_strip_all_tags( wp_remote_retrieve_body( $resp ) ), 0, 160 );
			}
			if ( in_array( $code, array( 401, 403 ), true ) ) {
				$msg .= ' (key galat hai ya is API ka access nahi)';
			} elseif ( 402 === $code ) {
				$msg .= ' (You.com billing/credits check karo)';
			} elseif ( 429 === $code ) {
				$msg .= ' (rate limit, thori der baad try karo)';
			}
			return new WP_Error( 'aiap_you', 'You.com: ' . $msg );
		}
		return is_array( $json ) ? $json : array();
	}

	/**
	 * Web Search API. Returns list of array( title, url, description, snippet ).
	 */
	public static function search( $query, $count = 8, $key = '' ) {
		$key = $key ? $key : AIAP_Settings::get( 'you_key' );
		$j   = self::call( self::IDX, '/v1/search', array( 'query' => (string) $query, 'count' => max( 1, min( 20, (int) $count ) ) ), $key, 30 );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$out = array();
		$web = isset( $j['results']['web'] ) && is_array( $j['results']['web'] ) ? $j['results']['web'] : array();
		foreach ( $web as $r ) {
			if ( empty( $r['url'] ) ) {
				continue;
			}
			$snip = '';
			if ( ! empty( $r['snippets'][0] ) ) {
				$snip = $r['snippets'][0];
			} elseif ( ! empty( $r['contents']['highlights'][0] ) ) {
				$snip = $r['contents']['highlights'][0];
			}
			$out[] = array(
				'title'       => isset( $r['title'] ) ? wp_strip_all_tags( $r['title'] ) : $r['url'],
				'url'         => $r['url'],
				'description' => isset( $r['description'] ) ? wp_strip_all_tags( $r['description'] ) : '',
				'snippet'     => wp_strip_all_tags( $snip ),
			);
		}
		if ( ! $out ) {
			return new WP_Error( 'aiap_you', 'You.com search se koi result nahi mila.' );
		}
		return $out;
	}

	/**
	 * Contents API: URLs -> clean Markdown. Returns list of array( url, title, markdown ).
	 */
	public static function contents( $urls, $key = '' ) {
		$key = $key ? $key : AIAP_Settings::get( 'you_key' );
		$j   = self::call( self::IDX, '/v1/contents', array( 'urls' => array_values( (array) $urls ), 'formats' => array( 'markdown' ) ), $key, 45 );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		$list = isset( $j[0] ) ? $j : ( isset( $j['results'] ) && is_array( $j['results'] ) ? $j['results'] : array() );
		$out  = array();
		foreach ( $list as $p ) {
			if ( ! empty( $p['markdown'] ) ) {
				$out[] = array(
					'url'      => isset( $p['url'] ) ? $p['url'] : '',
					'title'    => isset( $p['title'] ) ? $p['title'] : '',
					'markdown' => (string) $p['markdown'],
				);
			}
		}
		if ( ! $out ) {
			return new WP_Error( 'aiap_you', 'You.com Contents se page ka text nahi mila.' );
		}
		return $out;
	}

	/**
	 * Research API: multi-step cited answer. Returns array( content, sources[ url, title ] ).
	 */
	public static function research( $input, $effort = 'lite', $key = '' ) {
		$key    = $key ? $key : AIAP_Settings::get( 'you_key' );
		$effort = in_array( $effort, array( 'lite', 'standard', 'deep' ), true ) ? $effort : 'lite';
		$timeout = array( 'lite' => 60, 'standard' => 120, 'deep' => 240 );
		$j      = self::call( self::API, '/v1/research', array( 'input' => (string) $input, 'research_effort' => $effort ), $key, $timeout[ $effort ] );
		if ( is_wp_error( $j ) ) {
			return $j;
		}
		if ( empty( $j['output']['content'] ) ) {
			return new WP_Error( 'aiap_you', 'You.com Research se jawab nahi mila.' );
		}
		$sources = array();
		foreach ( isset( $j['output']['sources'] ) && is_array( $j['output']['sources'] ) ? $j['output']['sources'] : array() as $s ) {
			if ( ! empty( $s['url'] ) ) {
				$sources[] = array( 'url' => $s['url'], 'title' => ! empty( $s['title'] ) ? wp_strip_all_tags( $s['title'] ) : $s['url'] );
			}
		}
		return array( 'content' => (string) $j['output']['content'], 'sources' => $sources );
	}

	/**
	 * Article ke liye research/SERP intel. Returns array( text, sources[] ) ya WP_Error.
	 */
	public static function intel( $keyword, $a, $s ) {
		$mode = isset( $a['research'] ) ? $a['research'] : 'off';
		$key  = $s['you_key'];
		if ( 'off' === $mode || ! $key ) {
			return array( 'text' => '', 'sources' => array() );
		}

		if ( 'serp' === $mode ) {
			$res = self::search( $keyword, 8, $key );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$t = "SERP insights for \"{$keyword}\" (pages that rank right now). Study what they cover, then write something MORE complete and more useful: include the common subtopics, fill the gaps they leave, add unique value. Do not copy sentences or structure blindly.\n";
			foreach ( $res as $i => $r ) {
				$host = wp_parse_url( $r['url'], PHP_URL_HOST );
				$t   .= ( $i + 1 ) . '. ' . $r['title'] . ' (' . $host . ")\n";
				if ( $r['description'] ) {
					$t .= '   ' . mb_substr( $r['description'], 0, 220 ) . "\n";
				}
				if ( $r['snippet'] ) {
					$t .= '   ' . mb_substr( $r['snippet'], 0, 300 ) . "\n";
				}
			}
			// SERP mode competitor pages ko "Sources" mein nahi daalta.
			return array( 'text' => $t, 'sources' => array() );
		}

		// Cited research.
		$input = "Research the topic for a comprehensive, accurate SEO article targeting the keyword \"{$keyword}\" (audience language: {$a['language']}). Cover: key facts and current statistics with sources, main subtopics, common questions people ask, and recent developments. Be factual and concise.";
		$r     = self::research( $input, $s['you_effort'], $key );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$brief = preg_replace( '/\[\[[\d,\s]+\]\]/', '', $r['content'] );
		$brief = trim( mb_substr( $brief, 0, 7000 ) );
		$t     = "Web research brief (gathered live; base facts and statistics ONLY on this brief, rewrite in your own words, never invent numbers beyond it, and do not write citation markers):\n" . $brief . "\n";
		$src   = array_slice( $r['sources'], 0, 8 );
		return array( 'text' => $t, 'sources' => $src );
	}
}
