<?php

/**
 * The people around this leader: those they coach, and the leaders and members
 * of the churches they hold.
 */
class Disciple_Tools_Autolink_Contacts_Controller extends Disciple_Tools_Autolink_Controller {
	const NONCE = 'dt_autolink_contact';


	/**
	 * Show the contacts template
	 */
	public function show( $params = [] ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action    = 'contacts';
		$contacts = $this->gather_contacts();

		$translations = [
			// Whatever this site calls contacts - some rename them to "Disciples".
			'heading'   => $contacts_label,
			/* translators: %s: lowercased plural name of the group post type, e.g. "churches". */
			'help_text' => sprintf( __( 'The people you coach or are assigned, and the leaders and members of your %s.', 'disciple-tools-autolink' ), strtolower( $churches_label ) ),
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'add'       => sprintf( __( 'Add %s', 'disciple-tools-autolink' ), $contact_label ),
			// One label whichever kind of link it is. What differs is where the
			// link goes, which is our problem rather than theirs.
			'has_login' => __( 'Has a Disciple.Tools login', 'disciple-tools-autolink' ),
			'copy_link' => __( 'Copy link', 'disciple-tools-autolink' ),
			'copied'    => __( 'Copied', 'disciple-tools-autolink' ),
			/* translators: 1: lowercased plural name of the contact post type. 2: lowercased plural name of the group post type. */
			'empty'     => sprintf(
				__( 'No %1$s yet. People you coach, and the leaders and members of your %2$s, will appear here.', 'disciple-tools-autolink' ),
				strtolower( $contacts_label ),
				strtolower( $churches_label )
			),
		];

		$add_contact_link  = $this->functions->get_app_link() . '?action=add-contact';
		$relationship_icons = $this->relationship_icons();

		include __DIR__ . '/../templates/contacts.php';
	}

	/**
	 * Show the form for adding a contact.
	 */
	public function create( $params = [] ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action       = 'contacts';
		$form_action  = $this->functions->get_app_link() . '?action=add-contact';
		$nonce        = self::NONCE;
		$error        = $params['error'] ?? '';
		$name         = $params['name'] ?? '';
		$cancel_url   = $this->functions->get_app_link() . '?action=contacts';
		$translations = [
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'heading'      => sprintf( __( 'Add a %s', 'disciple-tools-autolink' ), strtolower( $contact_label ) ),
			'help_text'    => __( 'They will be added to the people you coach, and get their own link into this app.', 'disciple-tools-autolink' ),
			'name_label'   => __( 'Name', 'disciple-tools-autolink' ),
			'submit_label' => __( 'Add', 'disciple-tools-autolink' ),
			'cancel_label' => __( 'Cancel', 'disciple-tools-autolink' ),
		];

		include __DIR__ . '/../templates/contact-form.php';
	}

	/**
	 * Create a contact.
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

		wp_redirect( $this->functions->get_app_link() . '?action=contacts' );
	}

	/**
	 * Gather the contacts, deduplicated, with how each one is connected.
	 *
	 * Everyone arrives from one query - see Actor::related_contacts() - and every
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
	private function gather_contacts() {
		$actor      = $this->actor();
		$contact_id = $actor->contact_id();
		$contacts  = [];

		foreach ( $actor->related_contacts() as $contact ) {
			$id = (int) $contact['ID'];

			$this->add_contact( $contacts, $id, $contact['title'] ?? $contact['name'] ?? '' );

			foreach ( $this->contact_relationships( $contact, $actor ) as $type ) {
				$this->add_relationship( $contacts, $id, $type );
			}

			foreach ( $contact['group_leader'] ?? [] as $church ) {
				$this->add_relationship( $contacts, $id, 'leader', $church['ID'], $church['post_title'] ?? '' );
			}

			foreach ( $contact['groups'] ?? [] as $church ) {
				$this->add_relationship( $contacts, $id, 'member', $church['ID'], $church['post_title'] ?? '' );
			}
		}

		// The person using the app is on their own churches as a leader and a
		// member; they are not their own contact.
		unset( $contacts[ $contact_id ] );

		$general_link = $this->functions->get_link_url();

		foreach ( $contacts as $id => $contact_row ) {
			$has_login = Disciple_Tools_Autolink_Actor::contact_has_login( $id );

			$contacts[ $id ]['has_login']     = $has_login;
			// Someone with their own Disciple.Tools login signs in rather than
			// following a key - handing their coach a magic key into that account
			// would hand over whatever the account can reach. They get the ordinary
			// sign-in address instead, which is still useful to pass on.
			$contacts[ $id ]['link']          = $has_login ? $general_link : Disciple_Tools_Autolink_Actor::contact_app_link( $id );
			$contacts[ $id ]['relationships'] = $this->shape_relationships( $contact_row['relationships'] );
		}

		uasort( $contacts, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return array_values( $contacts );
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
	 * @param array $contacts
	 * @param int $id
	 * @param string $name
	 */
	private function add_contact( &$contacts, $id, $name ) {
		$id = (int) $id;

		if ( ! $id ) {
			return;
		}

		if ( ! isset( $contacts[ $id ] ) ) {
			$contacts[ $id ] = [
				'id'            => $id,
				'name'          => $name,
				'relationships' => [],
			];
		}

		if ( ! $contacts[ $id ]['name'] && $name ) {
			$contacts[ $id ]['name'] = $name;
		}
	}

	/**
	 * Note one kind of connection, keeping the churches it came from.
	 *
	 * Churches are keyed by id rather than collected as a plain list, so that the
	 * leader and member sets can be compared without two churches that happen to
	 * share a name being treated as one.
	 *
	 * @param array $contacts
	 * @param int $id
	 * @param string $type
	 * @param int $church_id where the connection is through a church
	 * @param string $church_name
	 */
	private function add_relationship( &$contacts, $id, $type, $church_id = 0, $church_name = '' ) {
		$id = (int) $id;

		if ( ! isset( $contacts[ $id ] ) ) {
			return;
		}

		if ( ! isset( $contacts[ $id ]['relationships'][ $type ] ) ) {
			$contacts[ $id ]['relationships'][ $type ] = [];
		}

		if ( $church_id ) {
			$contacts[ $id ]['relationships'][ $type ][ (int) $church_id ] = $church_name;
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
		$sources = apply_filters( 'dt_autolink_contact_relationship_icons', [
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
