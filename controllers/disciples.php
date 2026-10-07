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
			'has_login'    => __( 'Has a Disciple.Tools login', 'disciple-tools-autolink' ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'empty'        => sprintf( __( 'No disciples yet. People you coach, and the leaders and members of your %s, will appear here.', 'disciple-tools-autolink' ), strtolower( $churches_label ) ),
		];

		$add_disciple_link = $this->functions->get_app_link() . '?action=add-disciple';

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
	 * Two sources: the contacts this person coaches, and the leaders and members
	 * of the churches they hold. Someone can be both, so the relationships are
	 * collected per person rather than listing them twice.
	 *
	 * @return array
	 */
	private function disciples() {
		$actor      = $this->actor();
		$contact_id = $actor->contact_id();
		$disciples  = [];

		// One query matching either handle - see Actor::disciple_contacts(). Which
		// one each person matched on is read back off the record rather than being
		// inferred from which query returned them.
		foreach ( $actor->disciple_contacts() as $contact ) {
			$name = $contact['title'] ?? $contact['name'] ?? '';

			$this->add_disciple( $disciples, $contact['ID'], $name );

			foreach ( $this->contact_relationships( $contact, $actor ) as $relationship ) {
				$this->add_disciple( $disciples, $contact['ID'], $name, $relationship );
			}
		}

		foreach ( $actor->own_groups() as $group ) {
			$group_name = $group['post_title'] ?? $group['name'] ?? '';

			foreach ( $group['leaders'] ?? [] as $leader ) {
				/* translators: %s: the name of a church or group. */
				$this->add_disciple( $disciples, $leader['ID'], $leader['post_title'] ?? '', sprintf( __( 'Leads %s', 'disciple-tools-autolink' ), $group_name ) );
			}

			foreach ( $group['members'] ?? [] as $member ) {
				/* translators: %s: the name of a church or group. */
				$this->add_disciple( $disciples, $member['ID'], $member['post_title'] ?? '', sprintf( __( 'Member of %s', 'disciple-tools-autolink' ), $group_name ) );
			}
		}

		// The person using the app is on their own churches as a leader and a
		// member; they are not their own disciple.
		unset( $disciples[ $contact_id ] );

		foreach ( $disciples as $id => $disciple ) {
			$disciples[ $id ]['has_login'] = Disciple_Tools_Autolink_Actor::contact_has_login( $id );
			// Someone with their own Disciple.Tools login signs in there - handing
			// their coach a magic key into that account would hand over whatever
			// the account can reach.
			$disciples[ $id ]['link']      = Disciple_Tools_Autolink_Actor::offer_contact_app_link( $id );
		}

		uasort( $disciples, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return array_values( $disciples );
	}

	/**
	 * How this person is connected to the actor.
	 *
	 * @param array $contact
	 * @param Disciple_Tools_Autolink_Actor $actor
	 *
	 * @return string[]
	 */
	private function contact_relationships( $contact, $actor ) {
		$relationships = [];
		$contact_id    = $actor->contact_id();
		$user_id       = $actor->linked_user_id();

		foreach ( $contact['coached_by'] ?? [] as $coach ) {
			if ( (int) ( $coach['ID'] ?? 0 ) === $contact_id ) {
				$relationships[] = __( 'Coaching', 'disciple-tools-autolink' );
				break;
			}
		}

		if ( $user_id && (int) ( $contact['assigned_to']['id'] ?? 0 ) === $user_id ) {
			$relationships[] = __( 'Assigned to you', 'disciple-tools-autolink' );
		}

		return $relationships;
	}

	/**
	 * Record one person, merging the relationship if we have seen them already.
	 *
	 * @param array $disciples
	 * @param int $id
	 * @param string $name
	 * @param string $relationship
	 */
	private function add_disciple( &$disciples, $id, $name, $relationship = '' ) {
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

		if ( $relationship && ! in_array( $relationship, $disciples[ $id ]['relationships'], true ) ) {
			$disciples[ $id ]['relationships'][] = $relationship;
		}
	}
}
