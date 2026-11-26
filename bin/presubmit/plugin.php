<?php
/**
 * What the pre-submission runtime check exercises in Coverfold. The only file
 * in bin/presubmit/ to edit for this plugin; runtime.php does the rest.
 *
 * Every key is optional. Callbacks run on a clean site with WP_DEBUG, as the
 * administrator, after activation. Callbacks that return text add it to the
 * results line as "key=value" pairs.
 *
 * Run by bin/presubmit/run.sh inside WordPress Playground; not part of the plugin.
 */

/**
 * Outgoing requests are blocked; this records the hosts the plugin tried.
 *
 * @param string|null $host Host to add, or null to read the list.
 * @return string[]
 */
function coverfold_presubmit_http( $host = null ) {
	static $hosts = array();
	if ( null !== $host ) {
		$hosts[] = $host;
	}
	return array_values( array_unique( $hosts ) );
}

return array(
	// Options, transients (coverfold_{version}_{hash}) and the video refresh
	// cron hook all start with "coverfold".
	'prefix'            => 'coverfold',

	// Two categories, two albums with images, one with YouTube and Vimeo links.
	'setup'             => static function () {
		add_filter(
			'pre_http_request',
			static function ( $response, $args, $url ) {
				coverfold_presubmit_http( (string) wp_parse_url( $url, PHP_URL_HOST ) );
				return new WP_Error( 'presubmit', 'Blocked by the pre-submission check.' );
			},
			10,
			3
		);

		// A 1x1 PNG, embedded: Playground's WordPress leaves out some core images.
		$upload = wp_upload_bits( 'presubmit.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC' ) );
		if ( ! empty( $upload['error'] ) ) {
			throw new Exception( 'Upload failed: ' . $upload['error'] );
		}
		$media = array();
		for ( $i = 1; $i <= 2; $i++ ) {
			$media[] = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/png',
					'post_title'     => 'Image ' . $i,
					'post_excerpt'   => 'Caption <em>' . $i . '</em> & more',
					'post_status'    => 'inherit',
				),
				$upload['file']
			);
		}
		foreach ( array( 'Weddings', 'Travel & Trips' ) as $index => $name ) {
			$term  = wp_insert_term( $name, Coverfold_Post_Type::TAXONOMY );
			$album = wp_insert_post(
				array(
					'post_type'   => Coverfold_Post_Type::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $name . ' album',
				),
				true
			);
			if ( is_wp_error( $term ) || is_wp_error( $album ) ) {
				throw new Exception( 'Sample album failed.' );
			}
			wp_set_object_terms( $album, array( (int) $term['term_id'] ), Coverfold_Post_Type::TAXONOMY );
			set_post_thumbnail( $album, $media[0] );
			update_post_meta( $album, Coverfold_Album::META_MEDIA, implode( ',', $media ) );
			if ( 0 === $index ) {
				update_post_meta( $album, Coverfold_Album::META_VIDEOS, "https://www.youtube.com/watch?v=aqz-KE-bpKQ\nhttps://vimeo.com/76979871" );
			}
		}
	},

	// The block, the shortcode and the old Album Station shortcode.
	'render'            => array(
		'<!-- wp:coverfold/album-grid {"filters":true,"showCount":true} /-->',
		'[coverfold filters="yes" count="yes"]',
		'[feature_album]',
	),

	'check_render'      => static function ( $html ) {
		return 'grids=' . substr_count( $html, 'coverfold-grid' )
			. ' cards=' . preg_match_all( '/class="[^"]*\bcoverfold-item\b/', $html )
			. ' filters=' . substr_count( $html, 'class="coverfold-filter' )
			. ' legacy_shortcode=' . ( shortcode_exists( 'feature_album' ) ? 'registered' : 'not-registered' );
	},

	'settings_page'     => static function () {
		$GLOBALS['plugin_page'] = Coverfold_Settings::PAGE;
		Coverfold_Settings::register();
		Coverfold_Settings::render_page();
	},

	// A queued video refresh, which deactivation must remove.
	'before_deactivate' => static function () {
		wp_schedule_single_event( time() + HOUR_IN_SECONDS, Coverfold_Videos::REFRESH_HOOK, array( 'youtube', 'presubmit' ) );
	},

	// Uninstall always removes settings and cached video data: give it some.
	'before_uninstall'  => static function () {
		update_option( Coverfold_Settings::OPTION, array( 'youtube_api_key' => 'presubmit' ) );
		set_transient( 'coverfold_1_presubmit', array( 'cached' => true ), HOUR_IN_SECONDS );
	},

	// Albums are the user's content and stay; also report the hosts contacted.
	'after_uninstall'   => static function () {
		global $wpdb;
		$albums = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'coverfold_album' ) );
		$hosts  = coverfold_presubmit_http();
		return 'albums_kept=' . $albums . ' http_hosts=' . ( $hosts ? implode( ',', $hosts ) : 'none' );
	},
);
