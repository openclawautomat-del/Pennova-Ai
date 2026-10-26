<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AIAP_Images {

	/**
	 * Configured source se image laao, media library mein save karo, attachment ID return karo.
	 * $used = already use ho chuki photo IDs (duplicate se bachne ke liye).
	 */
	public static function fetch( $query, $alt, $post_id, &$used ) {
		$s = AIAP_Settings::all();
		switch ( $s['image_source'] ) {
			case 'unsplash':
				return self::unsplash( $s['unsplash_key'], $query, $alt, $post_id, $used );
			case 'pexels':
				return self::pexels( $s['pexels_key'], $query, $alt, $post_id, $used );
			case 'openai':
				return self::openai( $s, $query, $alt, $post_id );
		}
		return new WP_Error( 'aiap_img', 'Image source off hai.' );
	}

	public static function figure( $att_id ) {
		$img = wp_get_attachment_image( $att_id, 'large', false, array( 'loading' => 'lazy' ) );
		if ( ! $img ) {
			return '';
		}
		$cap  = wp_get_attachment_caption( $att_id );
		$html = '<figure class="wp-block-image aiap-figure">' . $img;
		if ( $cap ) {
			$html .= '<figcaption>' . wp_kses( $cap, array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) ) . '</figcaption>';
		}
		return $html . '</figure>';
	}

	private static function load_admin_files() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}

	private static function finish( $tmp, $post_id, $alt, $credit, $ext ) {
		$file   = array(
			'name'     => sanitize_file_name( sanitize_title( $alt ) . '-' . strtolower( wp_generate_password( 4, false ) ) . '.' . $ext ),
			'tmp_name' => $tmp,
		);
		$att_id = media_handle_sideload( $file, $post_id, $alt );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			return $att_id;
		}
		if ( $credit ) {
			wp_update_post( array( 'ID' => $att_id, 'post_excerpt' => $credit ) );
		}
		update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		return $att_id;
	}

	private static function from_url( $url, $post_id, $alt, $credit ) {
		self::load_admin_files();
		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}
		return self::finish( $tmp, $post_id, $alt, $credit, 'jpg' );
	}

	private static function unsplash( $key, $query, $alt, $post_id, &$used ) {
		if ( ! $key ) {
			return new WP_Error( 'aiap_img', 'Unsplash key set nahi hai.' );
		}
		$auth = array( 'Authorization' => 'Client-ID ' . $key, 'Accept-Version' => 'v1' );
		$resp = wp_remote_get(
			add_query_arg( array( 'query' => $query, 'per_page' => 10, 'orientation' => 'landscape' ), 'https://api.unsplash.com/search/photos' ),
			array( 'timeout' => 30, 'headers' => $auth )
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return new WP_Error( 'aiap_img', 'Unsplash API error (key ya rate limit check karo).' );
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['results'] ) ) {
			return new WP_Error( 'aiap_img', 'Unsplash pe "' . $query . '" ki image nahi mili.' );
		}
		$results = $body['results'];
		shuffle( $results );
		$photo = null;
		foreach ( $results as $r ) {
			if ( empty( $used[ 'u' . $r['id'] ] ) ) {
				$photo = $r;
				break;
			}
		}
		if ( ! $photo ) {
			$photo = $results[0];
		}
		$used[ 'u' . $photo['id'] ] = 1;

		$name   = isset( $photo['user']['name'] ) ? $photo['user']['name'] : 'Unsplash';
		$link   = isset( $photo['user']['links']['html'] ) ? $photo['user']['links']['html'] : 'https://unsplash.com';
		$link   = add_query_arg( array( 'utm_source' => 'ai_auto_poster', 'utm_medium' => 'referral' ), $link );
		$credit = sprintf(
			'Photo by <a href="%1$s" target="_blank" rel="noopener">%2$s</a> on <a href="%3$s" target="_blank" rel="noopener">Unsplash</a>',
			esc_url( $link ),
			esc_html( $name ),
			esc_url( 'https://unsplash.com/?utm_source=ai_auto_poster&utm_medium=referral' )
		);
		$url    = isset( $photo['urls']['regular'] ) ? $photo['urls']['regular'] : '';
		if ( ! $url ) {
			return new WP_Error( 'aiap_img', 'Unsplash image URL nahi mila.' );
		}
		$id = self::from_url( $url, $post_id, $alt, $credit );
		// Unsplash guideline: download track karna zaroori hai.
		if ( ! is_wp_error( $id ) && ! empty( $photo['links']['download_location'] ) ) {
			wp_remote_get( $photo['links']['download_location'], array( 'timeout' => 15, 'headers' => $auth, 'blocking' => false ) );
		}
		return $id;
	}

	private static function pexels( $key, $query, $alt, $post_id, &$used ) {
		if ( ! $key ) {
			return new WP_Error( 'aiap_img', 'Pexels key set nahi hai.' );
		}
		$resp = wp_remote_get(
			add_query_arg( array( 'query' => $query, 'per_page' => 10, 'orientation' => 'landscape' ), 'https://api.pexels.com/v1/search' ),
			array( 'timeout' => 30, 'headers' => array( 'Authorization' => $key ) )
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			return new WP_Error( 'aiap_img', 'Pexels API error (key check karo).' );
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['photos'] ) ) {
			return new WP_Error( 'aiap_img', 'Pexels pe "' . $query . '" ki image nahi mili.' );
		}
		$results = $body['photos'];
		shuffle( $results );
		$photo = null;
		foreach ( $results as $r ) {
			if ( empty( $used[ 'p' . $r['id'] ] ) ) {
				$photo = $r;
				break;
			}
		}
		if ( ! $photo ) {
			$photo = $results[0];
		}
		$used[ 'p' . $photo['id'] ] = 1;
		$url    = isset( $photo['src']['large2x'] ) ? $photo['src']['large2x'] : ( isset( $photo['src']['large'] ) ? $photo['src']['large'] : '' );
		if ( ! $url ) {
			return new WP_Error( 'aiap_img', 'Pexels image URL nahi mila.' );
		}
		$credit = sprintf(
			'Photo by <a href="%1$s" target="_blank" rel="noopener">%2$s</a> on <a href="%3$s" target="_blank" rel="noopener">Pexels</a>',
			esc_url( isset( $photo['photographer_url'] ) ? $photo['photographer_url'] : 'https://www.pexels.com' ),
			esc_html( isset( $photo['photographer'] ) ? $photo['photographer'] : 'Pexels' ),
			esc_url( 'https://www.pexels.com' )
		);
		return self::from_url( $url, $post_id, $alt, $credit );
	}

	private static function openai( $s, $query, $alt, $post_id ) {
		$key = $s['img_openai_key'] ? $s['img_openai_key'] : ( isset( $s['keys']['openai'] ) ? $s['keys']['openai'] : '' );
		if ( ! $key ) {
			return new WP_Error( 'aiap_img', 'OpenAI Images ki key set nahi hai.' );
		}
		$model = $s['img_model'] ? $s['img_model'] : 'dall-e-3';
		$body  = array(
			'model'  => $model,
			'prompt' => 'Professional editorial photograph, natural lighting, no text, no watermark, no logos: ' . $query,
			'n'      => 1,
		);
		if ( 0 === strpos( $model, 'gpt-image' ) ) {
			$body['size'] = '1536x1024';
		} elseif ( 'dall-e-3' === $model ) {
			$body['size']            = '1792x1024';
			$body['response_format'] = 'b64_json';
		} else {
			$body['size']            = '1024x1024';
			$body['response_format'] = 'b64_json';
		}
		$resp = wp_remote_post(
			'https://api.openai.com/v1/images/generations',
			array(
				'timeout' => 180,
				'headers' => array( 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			$m = isset( $json['error']['message'] ) ? $json['error']['message'] : 'HTTP ' . wp_remote_retrieve_response_code( $resp );
			return new WP_Error( 'aiap_img', 'OpenAI image error: ' . $m );
		}
		self::load_admin_files();
		$credit = 'AI-generated image';
		if ( ! empty( $json['data'][0]['b64_json'] ) ) {
			$tmp = wp_tempnam( 'aiap.png' );
			file_put_contents( $tmp, base64_decode( $json['data'][0]['b64_json'] ) ); // phpcs:ignore
			return self::finish( $tmp, $post_id, $alt, $credit, 'png' );
		}
		if ( ! empty( $json['data'][0]['url'] ) ) {
			$tmp = download_url( $json['data'][0]['url'], 60 );
			if ( is_wp_error( $tmp ) ) {
				return $tmp;
			}
			return self::finish( $tmp, $post_id, $alt, $credit, 'png' );
		}
		return new WP_Error( 'aiap_img', 'OpenAI se image data nahi mila.' );
	}
}
