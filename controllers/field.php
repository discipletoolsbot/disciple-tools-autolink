<?php

class Disciple_Tools_Autolink_Field_Controller extends Disciple_Tools_Autolink_Controller {
    /**
     * Update a field
     */
    public function update( WP_REST_Request $request, $params, $user_id ) {
        $body      = $request->get_json_params();
        $whitelist = apply_filters( 'autolink_updatable_group_fields', [] );
        if ( ! isset( $body['id'] ) || ! isset( $body['value'] ) ) {
            wp_send_json_error( [ "message" => "Invalid request" ] );
        }

        $id         = sanitize_key( wp_unslash( $body['id'] ) );
        $field_info = explode( "_", $id );

        if ( ! is_array( $field_info ) || count( $field_info ) < 3 ) {
            wp_send_json_error( [ "message" => "Invalid request" ] );
        }

        $post_type = array_shift( $field_info );
        $id        = array_shift( $field_info );
        $field     = implode( "_", $field_info );

        $value = wp_unslash( $body['value'] );
        if ( ! is_array( $value ) ) {
            $value = sanitize_text_field( $value );
        }

        $is_allowed = in_array( $field, $whitelist );

        if ( ! $is_allowed ) {
            wp_send_json_error( [ "message" => "Invalid request" ] );
        }

        $payload = [
            $field => $this->format_value( $post_type, $field, $value )
        ];

        try {
            $result = DT_Posts::update_post( $post_type, $id, $payload );
        } catch ( Exception $e ) {
            wp_send_json_error( [ "message" => $e->getMessage() ] );
        }


        if ( ! is_wp_error( $result ) ) {
            wp_send_json_success( $result );
        }

        wp_send_json_error( [ "message" => $result->get_error_message() ] );
    }

    /**
     * Translate a submitted value into the format DT_Posts::update_post expects.
     *
     * dt-multi-select (which dt-church-health-circle extends) emits a flat
     * array of keys, prefixing removed values with "-". Multi select fields in
     * DT instead take [ 'values' => [ [ 'value' => x, 'delete' => bool ] ] ].
     * Values that already arrive in the DT shape are passed through untouched.
     *
     * @param string $post_type
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    private function format_value( $post_type, $field, $value ) {
        if ( ! is_array( $value ) || isset( $value['values'] ) ) {
            return $value;
        }

        $field_settings = DT_Posts::get_post_field_settings( $post_type );
        $field_type     = $field_settings[ $field ]['type'] ?? '';

        if ( $field_type !== 'multi_select' ) {
            return $value;
        }

        $values = [];

        foreach ( $value as $item ) {
            if ( ! is_string( $item ) || $item === '' ) {
                continue;
            }

            if ( strpos( $item, '-' ) === 0 ) {
                $values[] = [
                    'value'  => substr( $item, 1 ),
                    'delete' => true,
                ];
            } else {
                $values[] = [ 'value' => $item ];
            }
        }

        return [ 'values' => $values ];
    }
}
