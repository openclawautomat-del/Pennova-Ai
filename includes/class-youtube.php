<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keyword ke hisaab se relevant, embeddable YouTube videos dhoondta hai (YouTube Data API v3).
 * Quota: search = 100 units, videos.list = 1 unit; free daily quota 10,000 units. Results 12 ghante cache hote hain.
 */
class AIAP_YouTube {

	private static function api_error( $resp ) {
		$code = wp_remote_retrieve_response_code( $resp );
		$j    = json_decode( wp_remote_retrieve_body( $resp ), true );
		$msg  = 'HTTP ' . $code;
		if ( isset( $j['error']['message'] ) ) {
			$msg = $j['error']['message'];
		}
		$reason = isset( $j['error']['errors'][0]['reason'] ) ? $j['error']['errors'][0]['reason'] : '';
		if ( 'quotaExceeded' === $reason ) {
			$msg = 'YouTube ka daily quota khatam ho gaya (kal dobara chalega).';
		} elseif ( 'keyInvalid' === $reason || false !== stripos( $msg, 'API key not valid' ) ) {
			$msg = 'YouTube API key galat hai.';
		} elseif ( 'accessNotConfigured' === $reason || false !== stripos( $msg, 'has not been used' ) ) {
			$msg = 'Google Cloud mein "YouTube Data API v3" enable karo.';
		} elseif ( false !== stripos( $msg, 'referer' ) || false !== stripos( $msg, 'blocked' ) ) {
			$msg .= ' (API key ki restriction "None" ya "IP addresses" rakho, "HTTP referrers" nahi.)';
		}
		return new WP_Error( 'aiap_yt', 'YouTube: ' . $msg );
	}

	private static function seconds( $iso ) {
		if ( preg_match( '/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', (string) $iso, $m ) ) {
			return ( isset( $m[1] ) ? (int) $m[1] : 0 ) * 3600 + ( isset( $m[2] ) ? (int) $m[2] : 0 ) * 60 + ( isset( $m[3] ) ? (int) $m[3] : 0 );
		}
		return 0;
	}

	/**
	 * @return array|WP_Error  list of array( id, title, channel, views )
	 */
	public static function find( $query, $count = 1, $key = '' ) {
		$s     = AIAP_Settings::all();
		$key   = $key ? $key : $s['yt_key'];
		$query = trim( (string) $query );
		if ( ! $key ) {
			return new WP_Error( 'aiap_yt', 'YouTube API key set nahi hai.' );
		}
		if ( '' === $query ) {
			return new WP_Error( 'aiap_yt', 'YouTube search query khali hai.' );
		}

		$ck     = 'aiap_yt_' . md5( $query . '|' . $s['yt_lang'] . '|' . (int) $s['yt_min_views'] );
		$cached = get_transient( $ck );

		if ( ! is_array( $cached ) ) {
			$args = array(
				'part'            => 'snippet',
				'type'            => 'video',
				'videoEmbeddable' => 'true',
				'videoSyndicated' => 'true',
				'safeSearch'      => 'moderate',
				'maxResults'      => 15,
				'q'               => $query,
				'key'             => $key,
			);
			if ( $s['yt_lang'] ) {
				$args['relevanceLanguage'] = $s['yt_lang'];
			}
			$r = wp_remote_get( add_query_arg( $args, 'https://www.googleapis.com/youtube/v3/search' ), array( 'timeout' => 20 ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			if ( 200 !== wp_remote_retrieve_response_code( $r ) ) {
				return self::api_error( $r );
			}
			$j     = json_decode( wp_remote_retrieve_body( $r ), true );
			$ids   = array();
			$meta  = array();
			$items = isset( $j['items'] ) && is_array( $j['items'] ) ? $j['items'] : array();
			foreach ( $items as $it ) {
				if ( empty( $it['id']['videoId'] ) ) {
					continue;
				}
				$vid          = $it['id']['videoId'];
				$ids[]        = $vid;
				$meta[ $vid ] = array(
					'title'   => html_entity_decode( isset( $it['snippet']['title'] ) ? $it['snippet']['title'] : 'Video', ENT_QUOTES ),
					'channel' => html_entity_decode( isset( $it['snippet']['channelTitle'] ) ? $it['snippet']['channelTitle'] : '', ENT_QUOTES ),
				);
			}

			$list = array();
			if ( $ids ) {
				$r2 = wp_remote_get(
					add_query_arg(
						array( 'part' => 'contentDetails,statistics,status', 'id' => implode( ',', $ids ), 'key' => $key ),
						'https://www.googleapis.com/youtube/v3/videos'
					),
					array( 'timeout' => 20 )
				);
				if ( is_wp_error( $r2 ) ) {
					return $r2;
				}
				if ( 200 !== wp_remote_retrieve_response_code( $r2 ) ) {
					return self::api_error( $r2 );
				}
				$j2      = json_decode( wp_remote_retrieve_body( $r2 ), true );
				$details = array();
				foreach ( isset( $j2['items'] ) && is_array( $j2['items'] ) ? $j2['items'] : array() as $v ) {
					$details[ $v['id'] ] = $v;
				}
				// Search ka relevance order barqarar rakho, par bekaar videos hata do.
				foreach ( $ids as $vid ) {
					if ( empty( $details[ $vid ] ) ) {
						continue;
					}
					$v     = $details[ $vid ];
					$emb   = ! empty( $v['status']['embeddable'] );
					$pub   = isset( $v['status']['privacyStatus'] ) && 'public' === $v['status']['privacyStatus'];
					$secs  = self::seconds( isset( $v['contentDetails']['duration'] ) ? $v['contentDetails']['duration'] : '' );
					$views = isset( $v['statistics']['viewCount'] ) ? (int) $v['statistics']['viewCount'] : 0;
					if ( ! $emb || ! $pub || $secs < 60 || $secs > 5400 || $views < (int) $s['yt_min_views'] ) {
						continue;
					}
					$list[] = array(
						'id'      => $vid,
						'title'   => $meta[ $vid ]['title'],
						'channel' => $meta[ $vid ]['channel'],
						'views'   => $views,
					);
				}
			}
			$cached = $list;
			set_transient( $ck, $cached, 12 * HOUR_IN_SECONDS );
		}

		if ( ! $cached ) {
			return new WP_Error( 'aiap_yt', 'YouTube pe "' . $query . '" ke liye munasib video nahi mili (min views/length filter lagay hain).' );
		}
		return array_slice( $cached, 0, max( 1, (int) $count ) );
	}

	/**
	 * Post content mein jaane wala shortcode. (iframe kses se strip ho jata, shortcode nahi.)
	 */
	public static function shortcode_tag( $v ) {
		$clean = function ( $t ) {
			return trim( str_replace( array( '"', '[', ']', "\n", "\r" ), ' ', wp_strip_all_tags( $t ) ) );
		};
		return '[aiap_youtube id="' . preg_replace( '/[^A-Za-z0-9_-]/', '', $v['id'] ) . '" title="' . $clean( $v['title'] ) . '" channel="' . $clean( $v['channel'] ) . '"]';
	}
}
