<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Providers {

	/**
	 * Provider presets. 'format' = openai (chat/completions compatible) or anthropic (native).
	 * Model names editable hain; "Fetch models" se live list bhi mil jati hai.
	 */
	public static function presets() {
		return array(
			'openai'     => array(
				'label' => 'OpenAI', 'format' => 'openai', 'base' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini',
				'keyurl' => 'https://platform.openai.com/api-keys',
				'models' => array( 'gpt-4o-mini', 'gpt-4o', 'gpt-4.1', 'gpt-4.1-mini', 'o4-mini' ),
			),
			'anthropic'  => array(
				'label' => 'Anthropic (Claude)', 'format' => 'anthropic', 'base' => 'https://api.anthropic.com/v1', 'model' => 'claude-sonnet-5-5',
				'keyurl' => 'https://console.anthropic.com/settings/keys',
				'models' => array( 'claude-sonnet-5-5', 'claude-opus-5-5', 'claude-haiku-5-5', 'claude-sonnet-4-6' ),
			),
			'gemini'     => array(
				'label' => 'Google Gemini', 'format' => 'openai', 'base' => 'https://generativelanguage.googleapis.com/v1beta/openai', 'model' => 'gemini-2.5-flash',
				'keyurl' => 'https://aistudio.google.com/apikey',
				'models' => array( 'gemini-2.5-flash', 'gemini-2.5-pro', 'gemini-2.0-flash' ),
			),
			'openrouter' => array(
				'label' => 'OpenRouter (300+ models)', 'format' => 'openai', 'base' => 'https://openrouter.ai/api/v1', 'model' => 'openai/gpt-4o-mini',
				'keyurl' => 'https://openrouter.ai/keys',
				'models' => array( 'openai/gpt-4o-mini', 'anthropic/claude-sonnet-4.5', 'google/gemini-2.5-flash', 'meta-llama/llama-3.3-70b-instruct', 'deepseek/deepseek-chat', 'mistralai/mistral-large' ),
			),
			'groq'       => array(
				'label' => 'Groq', 'format' => 'openai', 'base' => 'https://api.groq.com/openai/v1', 'model' => 'llama-3.3-70b-versatile',
				'keyurl' => 'https://console.groq.com/keys',
				'models' => array( 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant' ),
			),
			'deepseek'   => array(
				'label' => 'DeepSeek', 'format' => 'openai', 'base' => 'https://api.deepseek.com/v1', 'model' => 'deepseek-chat',
				'keyurl' => 'https://platform.deepseek.com/api_keys',
				'models' => array( 'deepseek-chat', 'deepseek-reasoner' ),
			),
			'mistral'    => array(
				'label' => 'Mistral AI', 'format' => 'openai', 'base' => 'https://api.mistral.ai/v1', 'model' => 'mistral-large-latest',
				'keyurl' => 'https://console.mistral.ai/api-keys',
				'models' => array( 'mistral-large-latest', 'mistral-small-latest' ),
			),
			'xai'        => array(
				'label' => 'xAI (Grok)', 'format' => 'openai', 'base' => 'https://api.x.ai/v1', 'model' => 'grok-3-mini',
				'keyurl' => 'https://console.x.ai',
				'models' => array( 'grok-3', 'grok-3-mini' ),
			),
			'together'   => array(
				'label' => 'Together AI', 'format' => 'openai', 'base' => 'https://api.together.xyz/v1', 'model' => 'meta-llama/Llama-3.3-70B-Instruct-Turbo',
				'keyurl' => 'https://api.together.ai/settings/api-keys',
				'models' => array( 'meta-llama/Llama-3.3-70B-Instruct-Turbo', 'deepseek-ai/DeepSeek-V3' ),
			),
			'perplexity' => array(
				'label' => 'Perplexity', 'format' => 'openai', 'base' => 'https://api.perplexity.ai', 'model' => 'sonar',
				'keyurl' => 'https://www.perplexity.ai/settings/api',
				'models' => array( 'sonar', 'sonar-pro' ),
			),
			'fireworks'  => array(
				'label' => 'Fireworks AI', 'format' => 'openai', 'base' => 'https://api.fireworks.ai/inference/v1', 'model' => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
				'keyurl' => 'https://fireworks.ai/account/api-keys',
				'models' => array( 'accounts/fireworks/models/llama-v3p3-70b-instruct' ),
			),
			'cerebras'   => array(
				'label' => 'Cerebras', 'format' => 'openai', 'base' => 'https://api.cerebras.ai/v1', 'model' => 'llama-3.3-70b',
				'keyurl' => 'https://cloud.cerebras.ai',
				'models' => array( 'llama-3.3-70b' ),
			),
			'cohere'     => array(
				'label' => 'Cohere', 'format' => 'openai', 'base' => 'https://api.cohere.ai/compatibility/v1', 'model' => 'command-r-plus',
				'keyurl' => 'https://dashboard.cohere.com/api-keys',
				'models' => array( 'command-r-plus', 'command-r' ),
			),
			'ollama'     => array(
				'label' => 'Ollama (local)', 'format' => 'openai', 'base' => 'http://localhost:11434/v1', 'model' => 'llama3.1',
				'keyurl' => '', 'nokey' => true,
				'models' => array( 'llama3.1', 'qwen2.5' ),
			),
			'lmstudio'   => array(
				'label' => 'LM Studio (local)', 'format' => 'openai', 'base' => 'http://localhost:1234/v1', 'model' => 'local-model',
				'keyurl' => '', 'nokey' => true,
				'models' => array(),
			),
			'custom'     => array(
				'label' => 'Custom (apna URL)', 'format' => 'openai', 'base' => '', 'model' => '',
				'keyurl' => '', 'nokey' => true,
				'models' => array(),
			),
		);
	}

	/**
	 * Active provider config. $ov = unsaved form values (test/fetch models ke liye).
	 */
	public static function config( $ov = array() ) {
		$s       = AIAP_Settings::all();
		$presets = self::presets();
		$pid     = ! empty( $ov['provider'] ) ? $ov['provider'] : $s['provider'];
		$id      = isset( $presets[ $pid ] ) ? $pid : 'openai';
		$p       = $presets[ $id ];
		$base    = $p['base'];
		$format  = $p['format'];
		if ( 'custom' === $id ) {
			$base   = ( isset( $ov['base'] ) && '' !== $ov['base'] ) ? $ov['base'] : $s['custom_base'];
			$fmt    = ( isset( $ov['format'] ) && '' !== $ov['format'] ) ? $ov['format'] : $s['custom_format'];
			$format = ( 'anthropic' === $fmt ) ? 'anthropic' : 'openai';
		}
		$model = ! empty( $ov['model'] ) ? $ov['model'] : ( ! empty( $s['models'][ $id ] ) ? $s['models'][ $id ] : $p['model'] );
		$key   = ! empty( $ov['key'] ) ? $ov['key'] : ( isset( $s['keys'][ $id ] ) ? $s['keys'][ $id ] : '' );
		return array(
			'id'     => $id,
			'label'  => $p['label'],
			'format' => $format,
			'base'   => rtrim( $base, '/' ),
			'model'  => $model,
			'key'    => $key,
			'nokey'  => ! empty( $p['nokey'] ),
		);
	}

	/**
	 * Ek system + user prompt bhejo, text wapas lo.
	 */
	public static function chat( $system, $user, $opts = array() ) {
		$cfg = isset( $opts['cfg'] ) ? $opts['cfg'] : self::config();
		if ( '' === $cfg['base'] ) {
			return new WP_Error( 'aiap_cfg', 'Provider ka Base URL set nahi hai (Settings mein daalo).' );
		}
		if ( '' === $cfg['model'] ) {
			return new WP_Error( 'aiap_cfg', 'Model ka naam Settings mein daalo.' );
		}
		if ( '' === $cfg['key'] && ! $cfg['nokey'] ) {
			return new WP_Error( 'aiap_key', $cfg['label'] . ' ki API key Settings mein daalo.' );
		}
		$s    = AIAP_Settings::all();
		$max  = isset( $opts['max_tokens'] ) ? (int) $opts['max_tokens'] : (int) $s['max_tokens'];
		$temp = isset( $opts['temperature'] ) ? (float) $opts['temperature'] : (float) $s['temperature'];

		return ( 'anthropic' === $cfg['format'] )
			? self::call_anthropic( $cfg, $system, $user, $max, $temp )
			: self::call_openai( $cfg, $system, $user, $max, $temp );
	}

	private static function error_message( $resp, $code, $json ) {
		$msg = 'HTTP ' . $code;
		if ( is_array( $json ) && isset( $json['error'] ) ) {
			if ( is_array( $json['error'] ) ) {
				$msg = isset( $json['error']['message'] ) ? $json['error']['message'] : wp_json_encode( $json['error'] );
			} else {
				$msg = (string) $json['error'];
			}
		} else {
			$msg .= ' ' . mb_substr( wp_strip_all_tags( wp_remote_retrieve_body( $resp ) ), 0, 200 );
		}
		return $msg;
	}

	private static function call_anthropic( $cfg, $system, $user, $max, $temp ) {
		$resp = wp_remote_post(
			$cfg['base'] . '/messages',
			array(
				'timeout' => 240,
				'headers' => array(
					'x-api-key'         => $cfg['key'],
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $cfg['model'],
						'max_tokens'  => $max,
						'temperature' => min( 1, $temp ),
						'system'      => $system,
						'messages'    => array( array( 'role' => 'user', 'content' => $user ) ),
					)
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( 200 !== $code ) {
			return new WP_Error( 'aiap_api', $cfg['label'] . ' error: ' . self::error_message( $resp, $code, $json ) );
		}
		$out = '';
		if ( ! empty( $json['content'] ) && is_array( $json['content'] ) ) {
			foreach ( $json['content'] as $block ) {
				if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
					$out .= $block['text'];
				}
			}
		}
		return $out;
	}

	/**
	 * Message se text nikalo. Reasoning models (Nemotron, DeepSeek-R1, o-series...) kabhi content null
	 * chhod ke reasoning field mein likhte hain, ya <think> tags inline dete hain.
	 */
	private static function extract_text( $msg ) {
		$text = '';
		if ( isset( $msg['content'] ) ) {
			if ( is_string( $msg['content'] ) ) {
				$text = $msg['content'];
			} elseif ( is_array( $msg['content'] ) ) {
				foreach ( $msg['content'] as $part ) {
					if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) ) {
						$text .= $part['text'];
					} elseif ( is_string( $part ) ) {
						$text .= $part;
					}
				}
			}
		}
		$text = trim( preg_replace( '#<think>.*?</think>#is', '', $text ) );
		if ( '' === $text ) {
			foreach ( array( 'reasoning_content', 'reasoning' ) as $f ) {
				if ( isset( $msg[ $f ] ) && is_string( $msg[ $f ] ) && ( false !== strpos( $msg[ $f ], '[[TITLE]]' ) || false !== strpos( $msg[ $f ], '[[CONTENT]]' ) ) ) {
					return trim( $msg[ $f ] );
				}
			}
		}
		return $text;
	}

	private static function call_openai( $cfg, $system, $user, $max, $temp ) {
		$body = array(
			'model'    => $cfg['model'],
			'messages' => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $user ),
			),
		);
		// OpenAI ke naye reasoning/gpt-5 models alag parameters mangte hain.
		if ( 'openai' === $cfg['id'] && preg_match( '/^(o\d|gpt-5)/i', $cfg['model'] ) ) {
			$body['max_completion_tokens'] = $max;
		} else {
			$body['max_tokens']  = $max;
			$body['temperature'] = $temp;
		}
		$headers = array( 'Content-Type' => 'application/json' );
		if ( '' !== $cfg['key'] ) {
			$headers['Authorization'] = 'Bearer ' . $cfg['key'];
		}
		if ( 'openrouter' === $cfg['id'] ) {
			$headers['HTTP-Referer'] = home_url();
			$headers['X-Title']      = get_bloginfo( 'name' );
		}

		$bumped = false;
		$empty  = 0;
		for ( $try = 0; $try < 5; $try++ ) {
			$resp = wp_remote_post(
				$cfg['base'] . '/chat/completions',
				array( 'timeout' => 240, 'headers' => $headers, 'body' => wp_json_encode( $body ) )
			);
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$code = wp_remote_retrieve_response_code( $resp );
			$json = json_decode( wp_remote_retrieve_body( $resp ), true );

			// OpenRouter kabhi HTTP 200 ke andar error bhejta hai.
			if ( 200 === $code && is_array( $json ) && isset( $json['error'] ) && empty( $json['choices'] ) ) {
				return new WP_Error( 'aiap_api', $cfg['label'] . ' error: ' . self::error_message( $resp, 0, $json ) );
			}

			if ( 200 === $code && is_array( $json ) && ! empty( $json['choices'][0] ) ) {
				$choice = $json['choices'][0];
				$text   = self::extract_text( isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : array() );
				if ( '' !== $text ) {
					return $text;
				}
				$finish = isset( $choice['finish_reason'] ) ? (string) $choice['finish_reason'] : '';
				// Token limit reasoning mein khatam ho gayi: ek baar limit double karke retry.
				if ( 'length' === $finish && ! $bumped ) {
					$bumped = true;
					$new    = min( 32000, $max * 2 );
					if ( isset( $body['max_completion_tokens'] ) ) {
						$body['max_completion_tokens'] = $new;
					} else {
						$body['max_tokens'] = $new;
					}
					$max = $new;
					continue;
				}
				// Khali jawab: ek do dafa dobara try (free models aksar aisa karte hain).
				if ( 'length' !== $finish && $empty < 2 ) {
					$empty++;
					sleep( 2 );
					continue;
				}
				$why = ( 'length' === $finish )
					? 'Model ka jawab token limit mein khatam ho gaya (shayad reasoning model hai jo sochne mein tokens kharch karta hai). Settings mein "Max output tokens" 16000+ karo, ya non-reasoning model use karo.'
					: 'Model ne khali jawab diya' . ( '' !== $finish ? ' (finish_reason: ' . $finish . ')' : '' ) . '. Free models aksar aisa karte hain; dobara try karo ya koi aur model chuno.';
				return new WP_Error( 'aiap_api', $cfg['label'] . ' (' . $cfg['model'] . '): ' . $why );
			}

			$msg = self::error_message( $resp, $code, $json );
			// Parameter mismatch ho to ek baar adjust karke retry.
			if ( stripos( $msg, 'max_completion_tokens' ) !== false && isset( $body['max_tokens'] ) ) {
				unset( $body['max_tokens'] );
				$body['max_completion_tokens'] = $max;
				continue;
			}
			if ( stripos( $msg, 'temperature' ) !== false && isset( $body['temperature'] ) ) {
				unset( $body['temperature'] );
				continue;
			}
			return new WP_Error( 'aiap_api', $cfg['label'] . ' error: ' . $msg );
		}
		return new WP_Error( 'aiap_api', $cfg['label'] . ' se jawab nahi mila.' );
	}

	/**
	 * Provider ke /models endpoint se live model list.
	 */
	public static function list_models( $cfg = null ) {
		$cfg = $cfg ? $cfg : self::config();
		if ( '' === $cfg['base'] ) {
			return new WP_Error( 'aiap_cfg', 'Base URL set nahi hai.' );
		}
		$headers = array();
		if ( 'anthropic' === $cfg['format'] ) {
			$headers['x-api-key']         = $cfg['key'];
			$headers['anthropic-version'] = '2023-06-01';
			$url                          = $cfg['base'] . '/models?limit=100';
		} else {
			if ( '' !== $cfg['key'] ) {
				$headers['Authorization'] = 'Bearer ' . $cfg['key'];
			}
			$url = $cfg['base'] . '/models';
		}
		$resp = wp_remote_get( $url, array( 'timeout' => 30, 'headers' => $headers ) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( 200 !== $code ) {
			return new WP_Error( 'aiap_api', self::error_message( $resp, $code, $json ) );
		}
		$ids = array();
		if ( isset( $json['data'] ) && is_array( $json['data'] ) ) {
			foreach ( $json['data'] as $m ) {
				if ( is_array( $m ) && ! empty( $m['id'] ) ) {
					$ids[] = preg_replace( '#^models/#', '', $m['id'] );
				}
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}
}
