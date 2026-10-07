<?php

/**
 * The people around this leader: those they coach, and the leaders and members
 * of the churches they hold.
 */
class Disciple_Tools_Autolink_Disciples_Controller extends Disciple_Tools_Autolink_Controller {
	const NONCE = 'dt_autolink_disciple';


	/**
	 * Show the disciples template
	 */
	public function show( $params = [] ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action    = 'disciples';
		$disciples = $this->disciples();

		$translations = [
			'heading'      => __( 'Disciples', 'disciple-tools-autolink' ),
			'help_text'    => __( 'The people you coach or are assigned, and the leaders and members of your churches.', 'disciple-tools-autolink' ),
			'add'          => __( 'Add disciple', 'disciple-tools-autolink' ),
			'link_label'   => __( 'Their link', 'disciple-tools-autolink' ),
			'link_help'    => __( 'Send this to them. They can use the app without a Disciple.Tools login.', 'disciple-tools-autolink' ),
			// One label whichever kind of link it is. What differs is where the
			// link goes, which is our problem rather than theirs.
			'has_login'       => __( 'Has a Disciple.Tools login', 'disciple-tools-autolink' ),
			'copy_link'       => __( 'Copy link', 'disciple-tools-autolink' ),
			'copied'          => __( 'Copied', 'disciple-tools-autolink' ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'empty'        => sprintf( __( 'No disciples yet. People you coach, and the leaders and members of your %s, will appear here.', 'disciple-tools-autolink' ), strtolower( $churches_label ) ),
		];

		$add_disciple_link  = $this->functions->get_app_link() . '?action=add-disciple';
		$relationship_icons = $this->relationship_icons();

		include __DIR__ . '/../templates/disciples.php';
	}

	/**
	 * Show the form for adding a disciple.
	 */
	public function create( $params = [] ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action       = 'disciples';
		$form_action  = $this->functions->get_app_link() . '?action=add-disciple';
		$nonce        = self::NONCE;
		$error        = $params['error'] ?? '';
		$name         = $params['name'] ?? '';
		$cancel_url   = $this->functions->get_app_link() . '?action=disciples';
		$translations = [
			'heading'      => __( 'Add a disciple', 'disciple-tools-autolink' ),
			'help_text'    => __( 'They will be added to the people you coach, and get their own link into this app.', 'disciple-tools-autolink' ),
			'name_label'   => __( 'Name', 'disciple-tools-autolink' ),
			'submit_label' => __( 'Add', 'disciple-tools-autolink' ),
			'cancel_label' => __( 'Cancel', 'disciple-tools-autolink' ),
		];

		include __DIR__ . '/../templates/disciple-form.php';
	}

	/**
	 * Create a disciple.
	 *
	 * This is the answer to "how does someone new join the chain" - rather than a
	 * link anyone can use to create records, the person doing the coaching adds
	 * them here and hands over the link that comes back. Creation stays behind a
	 * key that already belongs to someone.
	 */
	public function store( $params = [] ) {
		$nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce = $nonce && wp_verify_nonce( $nonce, self::NONCE );
		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$actor        = $this->actor();

		if ( ! $verify_nonce ) {
			$this->create( [ 'error' => __( 'Unable to verify request. Please try again.', 'disciple-tools-autolink' ) ] );

			return;
		}

		if ( ! $name ) {
			$this->create( [ 'error' => __( 'Please enter a name.', 'disciple-tools-autolink' ) ] );

			return;
		}

		if ( ! $actor->contact_id() ) {
			$this->create( [ 'error' => __( 'Your record could not be found.', 'disciple-tools-autolink' ), 'name' => $name ] );

			return;
		}

		$fields = [
			'name'       => $name,
			'coached_by' => [
				'values' => [
					[ 'value' => $actor->contact_id() ],
				],
			],
		];

		$assigned_to = $actor->assigned_to_for_new_records();
		if ( $assigned_to ) {
			$fields['assigned_to'] = $assigned_to;
		}

		$contact = DT_Posts::create_post( 'contacts', $fields, true, false );

		if ( is_wp_error( $contact ) ) {
			$this->create( [ 'error' => $contact->get_error_message(), 'name' => $name ] );

			return;
		}

		wp_publish_post( $contact['ID'] );
		// Mint their link now, so it is waiting on the list they land back on.
		Disciple_Tools_Autolink_Actor::offer_contact_app_link( $contact['ID'] );

		wp_redirect( $this->functions->get_app_link() . '?action=disciples' );
	}

	/**
	 * Gather the disciples, deduplicated, with how each one is connected.
	 *
	 * Everyone arrives from one query - see Actor::disciple_contacts() - and every
	 * connection is then read off their own record. Reading the church
	 * relationships from the person rather than from the actor's churches is what
	 * makes the card complete: it can say that someone leads a church the actor
	 * has no part in, which walking the actor's own churches could never show.
	 *
	 * Someone can be connected several ways at once, so connections are collected
	 * per person and grouped by kind - one "Member" badge naming three churches
	 * rather than three rows.
	 *
	 * @return array
	 */
	private function disciples() {
		$actor      = $this->actor();
		$contact_id = $actor->contact_id();
		$disciples  = [];

		foreach ( $actor->disciple_contacts() as $contact ) {
			$id = (int) $contact['ID'];

			$this->add_disciple( $disciples, $id, $contact['title'] ?? $contact['name'] ?? '' );

			foreach ( $this->contact_relationships( $contact, $actor ) as $type ) {
				$this->add_relationship( $disciples, $id, $type );
			}

			foreach ( $contact['group_leader'] ?? [] as $church ) {
				$this->add_relationship( $disciples, $id, 'leader', $church['ID'], $church['post_title'] ?? '' );
			}

			foreach ( $contact['groups'] ?? [] as $church ) {
				$this->add_relationship( $disciples, $id, 'member', $church['ID'], $church['post_title'] ?? '' );
			}
		}

		// The person using the app is on their own churches as a leader and a
		// member; they are not their own disciple.
		unset( $disciples[ $contact_id ] );

		$general_link = $this->functions->get_link_url();

		foreach ( $disciples as $id => $disciple ) {
			$has_login = Disciple_Tools_Autolink_Actor::contact_has_login( $id );

			$disciples[ $id ]['has_login']     = $has_login;
			// Someone with their own Disciple.Tools login signs in rather than
			// following a key - handing their coach a magic key into that account
			// would hand over whatever the account can reach. They get the ordinary
			// sign-in address instead, which is still useful to pass on.
			$disciples[ $id ]['link']          = $has_login ? $general_link : Disciple_Tools_Autolink_Actor::contact_app_link( $id );
			$disciples[ $id ]['relationships'] = $this->shape_relationships( $disciple['relationships'] );
		}

		uasort( $disciples, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return array_values( $disciples );
	}

	/**
	 * Which kinds of connection this person has to the actor.
	 *
	 * @param array $contact
	 * @param Disciple_Tools_Autolink_Actor $actor
	 *
	 * @return string[]
	 */
	private function contact_relationships( $contact, $actor ) {
		$types      = [];
		$contact_id = $actor->contact_id();
		$user_id    = $actor->linked_user_id();

		foreach ( $contact['coached_by'] ?? [] as $coach ) {
			if ( (int) ( $coach['ID'] ?? 0 ) === $contact_id ) {
				$types[] = 'coaching';
				break;
			}
		}

		if ( $user_id && (int) ( $contact['assigned_to']['id'] ?? 0 ) === $user_id ) {
			$types[] = 'assigned';
		}

		return $types;
	}

	/**
	 * Record one person.
	 *
	 * @param array $disciples
	 * @param int $id
	 * @param string $name
	 */
	private function add_disciple( &$disciples, $id, $name ) {
		$id = (int) $id;

		if ( ! $id ) {
			return;
		}

		if ( ! isset( $disciples[ $id ] ) ) {
			$disciples[ $id ] = [
				'id'            => $id,
				'name'          => $name,
				'relationships' => [],
			];
		}

		if ( ! $disciples[ $id ]['name'] && $name ) {
			$disciples[ $id ]['name'] = $name;
		}
	}

	/**
	 * Note one kind of connection, keeping the churches it came from.
	 *
	 * Churches are keyed by id rather than collected as a plain list, so that the
	 * leader and member sets can be compared without two churches that happen to
	 * share a name being treated as one.
	 *
	 * @param array $disciples
	 * @param int $id
	 * @param string $type
	 * @param int $church_id where the connection is through a church
	 * @param string $church_name
	 */
	private function add_relationship( &$disciples, $id, $type, $church_id = 0, $church_name = '' ) {
		$id = (int) $id;

		if ( ! isset( $disciples[ $id ] ) ) {
			return;
		}

		if ( ! isset( $disciples[ $id ]['relationships'][ $type ] ) ) {
			$disciples[ $id ]['relationships'][ $type ] = [];
		}

		if ( $church_id ) {
			$disciples[ $id ]['relationships'][ $type ][ (int) $church_id ] = $church_name;
		}
	}

	/**
	 * Turn the collected connections into badges, plus the lines underneath them.
	 *
	 * The badge says what kind of connection it is; the line below names the
	 * churches it came from. The names are on the card rather than in a tooltip -
	 * there is no hover on a phone, so anything only reachable that way is not
	 * there at all.
	 *
	 * Ordered deliberately: the direct relationships first, then the ones that
	 * reach this person through a church.
	 *
	 * @param array $relationships type => list of church names
	 *
	 * @return array
	 */
	private function shape_relationships( $relationships ) {
		$labels = [
			'coaching' => __( 'Coaching', 'disciple-tools-autolink' ),
			'assigned' => __( 'Assigned', 'disciple-tools-autolink' ),
			'leader'   => __( 'Leader', 'disciple-tools-autolink' ),
			'member'   => __( 'Member', 'disciple-tools-autolink' ),
		];

		$with_churches = [
			/* translators: %s: a list of church names. */
			'leader' => __( 'Leads %s', 'disciple-tools-autolink' ),
			/* translators: %s: a list of church names. */
			'member' => __( 'Member of %s', 'disciple-tools-autolink' ),
		];

		// Leading a church means being a member of it too, and DT records both, so
		// an unfiltered card says the same thing twice. The leader line is the one
		// that carries information, so a church someone leads drops out of their
		// member list - and if that empties it, the member badge goes with it.
		if ( ! empty( $relationships['leader'] ) && ! empty( $relationships['member'] ) ) {
			$relationships['member'] = array_diff_key( $relationships['member'], $relationships['leader'] );
		}

		$badges = [];

		foreach ( array_keys( $labels ) as $type ) {
			if ( ! isset( $relationships[ $type ] ) ) {
				continue;
			}

			$churches = $relationships[ $type ];
			$detail   = '';

			if ( isset( $with_churches[ $type ] ) ) {
				if ( empty( $churches ) ) {
					continue;
				}

				$detail = sprintf( $with_churches[ $type ], implode( ', ', $churches ) );
			}

			$badges[] = [
				'type'   => $type,
				'label'  => $labels[ $type ],
				'detail' => $detail,
			];
		}

		return $badges;
	}

	/**
	 * The icon for each kind of connection, taken from the Disciple.Tools field it
	 * stands for rather than picked by us, so that customising the field's icon
	 * customises this too.
	 *
	 * The two direct relationships read from contact fields. The two that reach a
	 * person through a church read from the church count fields instead - the same
	 * marks the Group Health tab already uses for leaders and members, so one
	 * symbol means one thing across both tabs. That is a deliberate trade: an
	 * admin who restyles the *contact* "Leader of Group" icon will not see it
	 * here. Point this filter at the contact fields to swap that back.
	 *
	 * There is no field behind "has a login" - corresponds_to_user is hidden and
	 * carries no icon - so that one mark stays ours, in the component.
	 *
	 * @return array
	 */
	private function relationship_icons() {
		$sources = apply_filters( 'dt_autolink_disciple_relationship_icons', [
			'coaching' => [ 'contacts', 'coaching' ],      // "Is Coaching"
			'assigned' => [ 'contacts', 'assigned_to' ],   // "Assigned"
			'leader'   => [ 'groups', 'leader_count' ],    // as per the Group Health row
			'member'   => [ 'groups', 'member_count' ],    // as per the Group Health row
		] );

		$settings = [];
		$icons    = [];

		foreach ( $sources as $type => $source ) {
			list( $post_type, $field_key ) = $source;

			if ( ! isset( $settings[ $post_type ] ) ) {
				$settings[ $post_type ] = DT_Posts::get_post_field_settings( $post_type );
			}

			$field = $settings[ $post_type ][ $field_key ] ?? [];

			$icons[ $type ] = [
				'icon'      => $field['icon'] ?? '',
				'font_icon' => $field['font-icon'] ?? '',
				'name'      => $field['name'] ?? '',
			];
		}

		return $icons;
	}
}
