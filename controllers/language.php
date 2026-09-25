<?php

class Disciple_Tools_Autolink_Language_Controller extends Disciple_Tools_Autolink_Controller {
	/**
	 * Switch the current user's locale.
	 *
	 * The app is rendered server side and the theme filters `locale` with the
	 * user's own locale on the front end, so persisting it here is all that is
	 * needed - the caller reloads to see the new language.
	 *
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param int $user_id
	 */
	public function update( WP_REST_Request $request, $params, $user_id ) {
		$body   = $request->get_json_params();
		$locale = sanitize_text_field( wp_unslash( $body['locale'] ?? '' ) );

		if ( ! $locale ) {
			wp_send_json_error( [ 'message' => __( 'No language was given.', 'disciple-tools-autolink' ) ] );
		}

		// Only accept a locale the site actually ships, so a crafted request
		// can't write an arbitrary string onto the user.
		if ( ! in_array( $locale, wp_list_pluck( dt_get_available_languages(), 'language' ), true ) ) {
			wp_send_json_error( [ 'message' => __( 'That language is not available.', 'disciple-tools-autolink' ) ] );
		}

		$result = Disciple_Tools_Users::update_user_locale( $user_id, $locale );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'locale' => $locale ] );
	}
}
