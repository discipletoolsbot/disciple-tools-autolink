<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Autolink_Magic_Contact_Link_App
 *
 * The whole Autolink app, served to a contact who has no Disciple.Tools login.
 *
 * The user app at `autolink/app/<key>` runs inside a real WP session. This one,
 * at `autolink/contact/<key>`, runs with no session at all: holding the key
 * proves which contact record the visitor is, and nothing more. There is no
 * capability grant - every record the app reads or writes is checked against
 * the actor's own churches and the churches of the people they coach.
 *
 * @see Disciple_Tools_Autolink_Magic_User_App
 * @see Disciple_Tools_Autolink_Actor
 */
class Disciple_Tools_Autolink_Magic_Contact_Link_App extends DT_Magic_Url_Base {
	use Disciple_Tools_Autolink_App_Routes;

	private static $_instance = null;
	public $page_title = 'Autolink';
	public $page_description = 'Autolink contact app';
	public $root = 'autolink';
	public $type = 'contact';
	public $type_name = 'Autolink App';
	public $post_type = 'contacts';
	public $show_bulk_send = true;
	public $show_app_tile = true; // show this magic link in the Apps tile on the contact record
	public $meta = [];

	/**
	 * A contact has no user locale to read, so fall back to the languages on the
	 * record. `?lang=` still wins, as it does everywhere else.
	 */
	public $translatable = [ 'query', 'contact' ];

	private $meta_key = 'autolink-contact';

	public function __construct() {
		/**
		 * Specify metadata structure, specific to the processing of current
		 * magic link type.
		 *
		 * - meta:              Magic link plugin related data.
		 *      - app_type:     Flag indicating type to be processed by magic link plugin.
		 *      - post_type     Magic link type post type.
		 *      - contacts_only:    Boolean flag indicating how magic link type user assignments are to be handled within magic link plugin.
		 *                          If True, lookup field to be provided within plugin for contacts only searching.
		 *                          If false, Dropdown option to be provided for user, team or group selection.
		 *      - fields:       List of fields to be displayed within magic link frontend form.
		 */
		$this->meta = [
			'app_type'      => 'magic_link',
			'post_type'     => $this->post_type,
			'contacts_only' => true,
			'fields'        => [
				[
					'id'    => 'name',
					'label' => 'Name'
				]
			]
		];

		$this->meta_key = $this->root . '_' . $this->type . '_magic_key';

		parent::__construct();

		$this->init_app();
		$this->maybe_load_genmap();

		// The updatable field whitelist is registered globally by the user app,
		// so both links share one list.
		add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );
		// Registered before the url check below, because the request this opens up
		// is a REST call whose url never matches this link's own path.
		add_filter( 'dt_allow_rest_access', [ $this, 'authorize_genmap_rest_url' ], 10, 1 );

		/**
		 * tests if other URL
		 */
		$url         = dt_get_url_path();
		$current_url = $this->root . '/' . $this->type;

		if ( strpos( $url, $current_url ) === false ) {
			return;
		}

		/**
		 * tests magic link parts are registered and have valid elements
		 */
		if ( ! $this->check_parts_match() ) {
			return;
		}

		$this->become_contact();

		add_action( 'dt_blank_body', function () {
			$this->ready();
			$this->routes();
		} );
		add_filter( 'dt_magic_url_base_allowed_css', [ $this->functions, 'dt_magic_url_base_allowed_css' ], 10, 1 );
		add_filter( 'dt_magic_url_base_allowed_js', [ $this->functions, 'dt_magic_url_base_allowed_js' ], 10, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Act as the contact the link belongs to.
	 *
	 * The visitor stays logged out. The anonymous WP_User is given the
	 * `magic_link` cap and the contact's name purely so anything DT writes on
	 * their behalf - comments, activity rows - is attributed to a person rather
	 * than to nobody. It carries no Disciple.Tools capability, which is why
	 * every DT_Posts call made for this actor passes `$check_permissions = false`
	 * and is scoped by Disciple_Tools_Autolink_Actor instead.
	 */
	private function become_contact() {
		$contact_id = (int) $this->parts['post_id'];
		$actor      = Disciple_Tools_Autolink_Actor::set_contact( $contact_id );
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );

		if ( is_user_logged_in() ) {
			return;
		}

		wp_set_current_user( 0 );
		$current_user = wp_get_current_user();
		$current_user->add_cap( 'magic_link' );
		$current_user->display_name = $actor->display_name();
	}

	public function ready() {
		$this->become_contact();
	}

	/**
	 * The actor a verified REST call is acting as.
	 *
	 * The permission callback has already matched the submitted key to this
	 * post id, so it - not the session - decides whose data the call may touch.
	 *
	 * @param array $parts
	 *
	 * @return Disciple_Tools_Autolink_Actor
	 */
	public function rest_actor( $parts ) {
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );
		$actor = Disciple_Tools_Autolink_Actor::set_contact( (int) ( $parts['post_id'] ?? 0 ) );

		if ( ! is_user_logged_in() ) {
			wp_set_current_user( 0 );
			$current_user = wp_get_current_user();
			$current_user->add_cap( 'magic_link' );
			$current_user->display_name = $actor->display_name();
		}

		return $actor;
	}

	/**
	 * Handle the header style hook
	 *
	 * Register the mapbox search widget css
	 */
	public function header_style() {
		DT_Mapbox_API::mapbox_search_widget_css();
	}
}

Disciple_Tools_Autolink_Magic_Contact_Link_App::instance();
