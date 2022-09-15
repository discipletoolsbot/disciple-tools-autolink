<?php

class Disciple_Tools_Autolink_Magic_Functions {

    private static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function dt_magic_url_base_allowed_js( $allowed_js ) {
        $allowed_js[] = 'magic_link_scripts';
        return $allowed_js;
    }

    public function dt_magic_url_base_allowed_css( $allowed_css ) {
        $allowed_css[] = 'magic_link_css';
        return $allowed_css;
    }

    public function wp_enqueue_scripts(){
        wp_enqueue_script( 'magic_link_scripts', trailingslashit( plugin_dir_url( __FILE__ ) ) . '../dist/magic-link.js', [
            'jquery',
            'lodash',
        ], filemtime( plugin_dir_path( __FILE__ ) . 'magic-link.js' ), true );
        wp_localize_script(
            'magic_link_scripts', 'app', [
                'map_key' => DT_Mapbox_API::get_key(),
                'rest_base' => esc_url( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'translations' => [
                    'add' => __( 'Add Magic', 'disciple-tools-autolink' ),
                ]
            ]
        );
        wp_enqueue_style( 'magic_link_css', trailingslashit( plugin_dir_url( __FILE__ ) ) . '../dist/magic-link.css', [], filemtime( plugin_dir_path( __FILE__ ) . 'magic-link.css' ) );
    }

    /**
     * Activate the app if it's not already activated
     */
    public function activate() {
        $value = get_user_option( 'autolink-user' );
        if ( $value === '' || $value === false || $value === '0' ) {
            Disciple_Tools_Users::app_switch( get_current_user_id(), 'autolink-user' );
        }
    }

    /**
     * Get the magic link url
     * @return string
     */
    private function get_app_link() {
        $app_public_key = get_user_option( DT_Magic_URL::get_public_key_meta_key('autolink', 'app') );
        return DT_Magic_URL::get_link_url('autolink', 'app', $app_public_key);
    }

    public function redirect_to_app() {
        wp_redirect( $this->get_app_link() );
        exit;
    }

    public function redirect_to_link() {
        wp_redirect( '/autolink' );
        exit;
    }

    public function fetch_logo() {
        $logo_url = $dt_nav_tabs['admin']['site']['icon'] ?? get_template_directory_uri() . '/dt-assets/images/disciple-tools-logo-white.png';
        $custom_logo_url = get_option( 'custom_logo_url' );
        if ( !empty( $custom_logo_url ) ) {
            $logo_url = $custom_logo_url;
        }
        return $logo_url;
    }

}
