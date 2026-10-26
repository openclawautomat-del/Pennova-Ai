<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telegram bot aur WhatsApp Agent (Meta Agent Platform) par alerts.
 */
class AIAP_Notify {

	const WA = 'https://api.whatsapp.com/agent/v1';

	/* ---------------- Channel status ---------------- */

	private static function tg_on( $s ) {
		return ! empty( $s['tg_enabled'] ) && $s['tg_chat_ids'] && ( $s['tg_webhook'] || $s['tg_token'] );
	}

	private static function wa_on( $s ) {
		return ! empty( $s['wa_enabled'] ) && $s['wa_key'] && $s['wa_to'];
	}

	/**
	 * Dono channels par bhejo. Errors array return karta hai (khali = sab theek).
	 */
	public static function dispatch( $tg_text, $wa_text ) {
		$s   = AIAP_Settings::all();
		$err = array();

		if ( self::tg_on( $s ) ) {
			$res = $s['tg_webhook']
				? self::telegram_webhook( $s['tg_webhook'], $tg_text, $s['tg_chat_ids'] )
				: self::telegram( $tg_text, $s['tg_token'], $s['tg_chat_ids'], $s['tg_api_base'] );
			if ( is_wp_error( $res ) ) {
				$err[] = 'Telegram: ' . $res->get_error_message();
			}
		}

		if ( self::wa_on( $s ) ) {
			$n = (int) get_transient( 'aiap_wa_n' );
			if ( $n >= 10 ) {
				$err[] = 'WhatsApp: 12 msg/minute limit ke qareeb, ye alert skip hua.';
			} else {
				set_transient( 'aiap_wa_n', $n + 1, MINUTE_IN_SECONDS );
				$res = self::whatsapp( $wa_text, $s['wa_key'], $s['wa_to'] );
				if ( is_wp_error( $res ) ) {
					$err[] = 'WhatsApp: ' . $res->get_error_message();
				}
			}
		}

		update_option( 'aiap_notify_last', $err ? array( 't' => time(), 'msg' => implode( ' | ', $err ) ) : '', false );
		return $err;
	}

	/**
	 * Har post ke baad (AIAP_Generator::run) call hota hai.
	 */
	public static function post( $keyword, $r ) {
		$s  = AIAP_Settings::all();
		$ok = ! is_wp_error( $r );
		if ( $ok && empty( $s['notify_success'] ) ) {
			return;
		}
		if ( ! $ok && empty( $s['notify_fail'] ) ) {
			return;
		}
		if ( ! self::tg_on( $s ) && ! self::wa_on( $s ) ) {
			return;
		}
		self::dispatch( self::text( $keyword, $r, 'tg' ), self::text( $keyword, $r, 'wa' ) );
	}

	/**
	 * Bulk run ke baad ek hi summary message.
	 */
	public static function summary( $post_ids, $fails ) {
		$s = AIAP_Settings::all();
		if ( ! self::tg_on( $s ) && ! self::wa_on( $s ) ) {
			return array();
		}
		return self::dispatch( self::summary_text( $post_ids, $fails, 'tg' ), self::summary_text( $post_ids, $fails, 'wa' ) );
	}

	/* ---------------- Message text ---------------- */

	private static function fmt( $ch ) {
		return array(
			'b' => function ( $t ) use ( $ch ) {
				return ( 'tg' === $ch ) ? '<b>' . htmlspecialchars( $t, ENT_NOQUOTES ) . '</b>' : '*' . $t . '*';
			},
			'e' => function ( $t ) use ( $ch ) {
				return ( 'tg' === $ch ) ? htmlspecialchars( (string) $t, ENT_NOQUOTES ) : (string) $t;
			},
		);
	}

	private static function status_label( $st ) {
		$labels = array( 'draft' => 'Draft', 'publish' => 'Published', 'pending' => 'Pending review', 'future' => 'Scheduled' );
		return isset( $labels[ $st ] ) ? $labels[ $st ] : $st;
	}

	private static function text( $keyword, $r, $ch ) {
		$f       = self::fmt( $ch );
		$b       = $f['b'];
		$e       = $f['e'];
		$compact = ( 'compact' === AIAP_Settings::get( 'notify_style' ) );

		if ( is_wp_error( $r ) ) {
			$why = mb_substr( $r->get_error_message(), 0, 400 );
			if ( $compact ) {
				return '❌ ' . $e( $keyword ) . ': ' . $e( $why );
			}
			return $b( '❌ Post fail hui' ) . "\n\nKeyword: " . $e( $keyword ) . "\nWajah: " . $e( $why ) . "\n\n" . $e( get_bloginfo( 'name' ) );
		}

		$status = self::status_label( $r['status'] );
		$link   = ( 'publish' === $r['status'] ) ? get_permalink( $r['id'] ) : $r['edit'];

		if ( $compact ) {
			return '✅ ' . $b( $r['title'] ) . "\n" . $e( $status ) . ' · ' . $link;
		}

		$t  = $b( '✅ Nayi post ban gayi' ) . "\n\n";
		$t .= 'Title: ' . $e( $r['title'] ) . "\n";
		$t .= 'Keyword: ' . $e( $keyword ) . "\n";
		$t .= 'Status: ' . $e( $status ) . "\n";
		if ( 'future' === $r['status'] ) {
			$t .= 'Schedule: ' . $e( get_post_field( 'post_date', $r['id'] ) ) . "\n";
		}
		$t .= 'Words: ~' . (int) $r['words'] . "\n";
		$t .= 'Image: ' . ( $r['image'] ? 'lag gayi' : 'nahi lagi' ) . "\n";
		if ( isset( $r['videos'] ) ) {
			$t .= 'Videos: ' . (int) $r['videos'] . "\n";
		}
		if ( ! empty( $r['sources'] ) ) {
			$t .= 'Research sources: ' . (int) $r['sources'] . "\n";
		}
		if ( 'publish' === $r['status'] ) {
			$t .= 'View: ' . get_permalink( $r['id'] ) . "\n";
		}
		$t .= 'Edit: ' . $r['edit'] . "\n\n" . $e( get_bloginfo( 'name' ) );
		return $t;
	}

	private static function summary_text( $post_ids, $fails, $ch ) {
		$f    = self::fmt( $ch );
		$b    = $f['b'];
		$e    = $f['e'];
		$ok_n = count( $post_ids );
		$bad  = count( $fails );

		$t = $b( ( $bad ? '⚠️' : '✅' ) . ' Bulk run mukammal' ) . "\n\n";
		$t .= $ok_n . ' post(s) bani' . ( $bad ? ', ' . $bad . ' fail' : '' ) . ".\n";

		$i = 0;
		foreach ( array_slice( $post_ids, 0, 15 ) as $id ) {
			$i++;
			$st = get_post_status( $id );
			$t .= "\n" . $i . '. ' . $e( get_the_title( $id ) ) . ' (' . $e( self::status_label( (string) $st ) ) . ")\n   " . ( 'publish' === $st ? get_permalink( $id ) : get_edit_post_link( $id, 'raw' ) );
		}
		if ( $ok_n > 15 ) {
			$t .= "\n… aur " . ( $ok_n - 15 ) . ' posts.';
		}
		foreach ( array_slice( $fails, 0, 5 ) as $m ) {
			$t .= "\n❌ " . $e( mb_substr( $m, 0, 200 ) );
		}
		return $t . "\n\n" . $e( get_bloginfo( 'name' ) );
	}

	/* ---------------- Telegram ---------------- */

	private static function tg_call( $method, $token, $base, $params = array() ) {
		$token = trim( $token );
		if ( ! preg_match( '/^\d+:[A-Za-z0-9_-]+$/', $token ) ) {
			return new WP_Error( 'aiap_tg', 'Bot token ka format galat hai (jaise 123456:ABC...).' );
		}
		$base = $base ? rtrim( $base, '/' ) : 'https://api.telegram.org';
		$resp = wp_remote_post( $base . '/bot' . $token . '/' . $method, array( 'timeout' => 20, 'body' => $params ) );
		if ( is_wp_error( $resp ) ) {
			$m = $resp->get_error_message();
			if ( false !== stripos( $m, 'resolve host' ) || false !== stripos( $m, 'cURL error 6' ) || false !== stripos( $m, 'cURL error 7' ) || false !== stripos( $m, 'cURL error 28' ) ) {
				$m .= ' → Aapka server Telegram tak nahi pahunch pa raha (hosting/DNS block). "Advanced" mein n8n relay URL lagao, ya hosting support se api.telegram.org unblock karwao.';
			}
			return new WP_Error( 'aiap_tg', $m );
		}
		$j = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $j['ok'] ) ) {
			$d = isset( $j['description'] ) ? $j['description'] : 'HTTP ' . wp_remote_retrieve_response_code( $resp );
			if ( false !== stripos( $d, 'unauthorized' ) ) {
				$d .= ' (token galat ya revoke ho chuka hai)';
			}
			return new WP_Error( 'aiap_tg', $d );
		}
		return $j;
	}

	public static function telegram( $text, $token, $chat_ids, $base = '' ) {
		$ids = preg_split( '/[\s,]+/', trim( $chat_ids ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $ids ) {
			return new WP_Error( 'aiap_tg', 'Chat/User ID khali hai.' );
		}
		$errors = array();
		foreach ( $ids as $cid ) {
			$r = self::tg_call(
				'sendMessage',
				$token,
				$base,
				array(
					'chat_id'                  => $cid,
					'text'                     => mb_substr( $text, 0, 4000 ),
					'parse_mode'               => 'HTML',
					'disable_web_page_preview' => 'true',
				)
			);
			if ( is_wp_error( $r ) ) {
				$d = $r->get_error_message();
				if ( false !== stripos( $d, 'chat not found' ) || false !== stripos( $d, 'can\'t initiate' ) ) {
					$d .= ' (pehle Telegram mein apne bot ko Start dabao, ya "Chat ID dhoondo" use karo)';
				}
				$errors[] = $cid . ': ' . $d;
			}
		}
		return $errors ? new WP_Error( 'aiap_tg', implode( '; ', $errors ) ) : true;
	}

	/**
	 * Bot check + jin chats ne bot ko message kiya unki list (Chat ID auto-detect).
	 */
	public static function tg_connect( $token, $base = '' ) {
		$me = self::tg_call( 'getMe', $token, $base );
		if ( is_wp_error( $me ) ) {
			return $me;
		}
		$bot = ! empty( $me['result']['username'] ) ? '@' . $me['result']['username'] : 'bot';
		$up  = self::tg_call( 'getUpdates', $token, $base, array( 'limit' => 50, 'timeout' => 0 ) );
		if ( is_wp_error( $up ) ) {
			$m = $up->get_error_message();
			if ( false !== stripos( $m, 'webhook' ) || false !== stripos( $m, 'conflict' ) ) {
				$m .= ' → Is bot par pehle se webhook laga hai (jaise n8n Telegram Trigger), isliye auto-detect nahi ho sakta. Chat ID @userinfobot se haath se daalo.';
			}
			return new WP_Error( 'aiap_tg', $m );
		}
		$chats = array();
		foreach ( isset( $up['result'] ) && is_array( $up['result'] ) ? $up['result'] : array() as $u ) {
			$msg = null;
			foreach ( array( 'message', 'edited_message', 'channel_post', 'my_chat_member' ) as $k ) {
				if ( ! empty( $u[ $k ]['chat']['id'] ) ) {
					$msg = $u[ $k ];
					break;
				}
			}
			if ( ! $msg ) {
				continue;
			}
			$c     = $msg['chat'];
			$id    = (string) $c['id'];
			$label = trim( ( isset( $c['first_name'] ) ? $c['first_name'] : '' ) . ' ' . ( isset( $c['last_name'] ) ? $c['last_name'] : '' ) );
			if ( '' === $label && ! empty( $c['title'] ) ) {
				$label = $c['title'];
			}
			if ( '' === $label && ! empty( $c['username'] ) ) {
				$label = '@' . $c['username'];
			}
			$chats[ $id ] = array( 'id' => $id, 'label' => $label ? $label : 'Chat', 'type' => isset( $c['type'] ) ? $c['type'] : '' );
		}
		return array( 'bot' => $bot, 'chats' => array_values( $chats ) );
	}

	public static function telegram_webhook( $url, $html, $chat_ids ) {
		$ids = preg_split( '/[\s,]+/', trim( $chat_ids ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! $ids ) {
			return new WP_Error( 'aiap_tg', 'Chat/User ID khali hai.' );
		}
		$errors = array();
		foreach ( $ids as $cid ) {
			$resp = wp_remote_post(
				$url,
				array(
					'timeout' => 20,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'source'     => 'ai-auto-poster',
							'site'       => get_bloginfo( 'name' ),
							'chat_id'    => $cid,
							'parse_mode' => 'HTML',
							'text'       => mb_substr( $html, 0, 4000 ),
							'text_plain' => html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES ),
						)
					),
				)
			);
			if ( is_wp_error( $resp ) ) {
				$errors[] = $cid . ': ' . $resp->get_error_message();
				continue;
			}
			$code = wp_remote_retrieve_response_code( $resp );
			if ( $code < 200 || $code >= 300 ) {
				$errors[] = $cid . ': webhook ne HTTP ' . $code . ' diya' . ( 404 === $code ? ' (n8n workflow active hai? Production URL use karo, test URL nahi)' : '' );
			}
		}
		return $errors ? new WP_Error( 'aiap_tg', implode( '; ', $errors ) ) : true;
	}

	/* ---------------- WhatsApp Agent Platform ---------------- */

	private static function wa_error( $resp ) {
		$code = wp_remote_retrieve_response_code( $resp );
		$j    = json_decode( wp_remote_retrieve_body( $resp ), true );
		$msg  = 'HTTP ' . $code;
		if ( is_array( $j ) && isset( $j['error'] ) ) {
			$msg .= ': ' . ( is_array( $j['error'] ) ? ( isset( $j['error']['message'] ) ? $j['error']['message'] : wp_json_encode( $j['error'] ) ) : $j['error'] );
		} else {
			$msg .= ' ' . mb_substr( wp_strip_all_tags( wp_remote_retrieve_body( $resp ) ), 0, 200 );
		}
		if ( in_array( $code, array( 401, 403 ), true ) ) {
			$msg .= ' (API key galat/regenerate ho chuki hai?)';
		}
		return $msg;
	}

	public static function whatsapp( $text, $key, $to ) {
		$resp = wp_remote_post(
			self::WA . '/messages',
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . trim( $key ), 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'messaging_product' => 'whatsapp',
						'to'                => trim( $to ),
						'type'              => 'text',
						'text'              => array( 'body' => mb_substr( $text, 0, 3900 ) ),
					)
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'aiap_wa', self::wa_error( $resp ) );
		}
		return true;
	}

	/**
	 * Agent ke inbound updates se creator ka "from" id nikalo.
	 */
	public static function wa_detect( $key ) {
		$resp = wp_remote_get(
			add_query_arg( array( 'timeout' => 5, 'limit' => 20 ), self::WA . '/updates' ),
			array( 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . trim( $key ) ) )
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( 409 === $code ) {
			return new WP_Error( 'aiap_wa', 'Is key pe koi aur client (jaise n8n/bot) already polling kar raha hai (HTTP 409). Usay thori der band karke dobara try karo, ya ID haath se daalo.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'aiap_wa', self::wa_error( $resp ) );
		}
		$j    = json_decode( wp_remote_retrieve_body( $resp ), true );
		$from = self::find_from( $j );
		if ( '' === $from ) {
			return new WP_Error( 'aiap_wa', 'Koi naya message nahi mila. WhatsApp mein apne agent ko ab ek message bhejo (jaise "hi"), phir 5 second baad dobara dabao.' );
		}
		return $from;
	}

	private static function find_from( $d ) {
		if ( is_array( $d ) ) {
			foreach ( $d as $k => $v ) {
				if ( 'from' === $k && is_string( $v ) && '' !== $v ) {
					return $v;
				}
				$f = self::find_from( $v );
				if ( '' !== $f ) {
					return $f;
				}
			}
		}
		return '';
	}
}
