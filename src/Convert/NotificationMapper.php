<?php
/**
 * Converts Gravity Forms notifications into Elementor email actions.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Convert;

/**
 * Elementor forms have two email actions: "email" (settings email_*) and "email2"
 * (settings email_*_2). The first two active on-submission notifications fill them;
 * the rest are reported as dropped.
 */
final class NotificationMapper {

	/**
	 * Elementor action and settings-key suffix for each slot.
	 */
	private const SLOTS = array(
		array(
			'action' => 'email',
			'suffix' => '',
		),
		array(
			'action' => 'email2',
			'suffix' => '_2',
		),
	);

	/**
	 * Creates the mapper.
	 *
	 * @param MergeTagConverter                $tags   Merge tag converter.
	 * @param ConditionalLogicDescriber        $logic  Conditional logic describer.
	 * @param array<int, array<string, mixed>> $fields Form fields, for logic labels.
	 */
	public function __construct(
		private readonly MergeTagConverter $tags,
		private readonly ConditionalLogicDescriber $logic,
		private readonly array $fields
	) {
	}

	/**
	 * Maps a form's notifications.
	 *
	 * @param array<string, array<string, mixed>> $notifications GF notifications keyed by ID.
	 *
	 * @return SettingsResult Email settings, actions and entries.
	 */
	public function map( array $notifications ): SettingsResult {
		$settings = array();
		$actions  = array();
		$entries  = array();

		foreach ( $notifications as $notification ) {
			$skip = $this->skip_reason( $notification, count( $actions ) );

			if ( null !== $skip ) {
				$entries[] = new ReportEntry( 'notification', $this->name( $notification ), ResultStatus::Dropped, $skip );
				continue;
			}

			$slot                   = self::SLOTS[ count( $actions ) ];
			list( $values, $notes ) = $this->map_one( $notification, $slot['suffix'] );
			$settings               = array_merge( $settings, $values );
			$actions[]              = $slot['action'];
			$entries[]              = new ReportEntry( 'notification', $this->name( $notification ), $notes->status, implode( ' ', $notes->notes ), 'email' === $slot['action'] ? 'Email' : 'Email 2' );
		}

		return new SettingsResult( $settings, $actions, $entries );
	}

	/**
	 * Explains why a notification is not migrated, or returns null if it is.
	 *
	 * @param array<string, mixed> $notification GF notification.
	 * @param int                  $used_slots   Email actions already filled.
	 *
	 * @return string|null Reason, or null to migrate it.
	 */
	private function skip_reason( array $notification, int $used_slots ): ?string {
		$event = (string) ( $notification['event'] ?? 'form_submission' );

		if ( 'form_submission' !== $event ) {
			/* translators: %s: Gravity Forms notification event, e.g. "form_saved". */
			return sprintf( __( 'Sent on the "%s" event, not on submission.', 'webofd-gf-to-elementor' ), $event );
		}

		if ( isset( $notification['isActive'] ) && ! $notification['isActive'] ) {
			return __( 'Inactive in Gravity Forms.', 'webofd-gf-to-elementor' );
		}

		if ( $used_slots >= count( self::SLOTS ) ) {
			return __( 'Elementor forms have only two email actions; recreate this one by hand if it is still needed.', 'webofd-gf-to-elementor' );
		}

		return null;
	}

	/**
	 * Builds the settings for one notification.
	 *
	 * @param array<string, mixed> $notification GF notification.
	 * @param string               $suffix       Settings key suffix ("" or "_2").
	 *
	 * @return array{0: array<string, mixed>, 1: Mapped} Settings, and a Mapped carrying only the status and notes.
	 */
	private function map_one( array $notification, string $suffix ): array {
		$result = new Mapped( array() );
		$values = array(
			'email_to'           => $this->recipient( $notification, $result ),
			'email_subject'      => $this->text( (string) ( $notification['subject'] ?? '' ), $result ),
			'email_content'      => $this->text( (string) ( $notification['message'] ?? '' ), $result ),
			'email_from'         => $this->sender( $notification, $result ),
			'email_from_name'    => $this->text( (string) ( $notification['fromName'] ?? '' ), $result ),
			'email_reply_to'     => $this->reply_to( (string) ( $notification['replyTo'] ?? '' ), $result ),
			'email_to_cc'        => $this->text( (string) ( $notification['cc'] ?? '' ), $result ),
			'email_to_bcc'       => $this->text( (string) ( $notification['bcc'] ?? '' ), $result ),
			'email_content_type' => 'html',
			'form_metadata'      => array(),
		);

		$values = array_filter( $values, static fn( $value ): bool => '' !== $value );

		return array( $this->suffixed( $values, $suffix ), $this->add_notes( $result, $notification ) );
	}

	/**
	 * Resolves the "send to" setting for each GF recipient type.
	 *
	 * @param array<string, mixed> $notification GF notification.
	 * @param Mapped               $result       Collects notes (reassigned through the reference).
	 *
	 * @return string Elementor email_to value.
	 */
	private function recipient( array $notification, Mapped &$result ): string {
		$type = (string) ( $notification['toType'] ?? 'email' );

		if ( 'field' === $type ) {
			$field_id = $this->tags->single_field_id( '{:' . (string) ( $notification['toField'] ?? $notification['to'] ?? '' ) . '}' );

			if ( null !== $field_id ) {
				return '[field id="' . $field_id . '"]';
			}

			$result = $result->with_note( __( 'The recipient field no longer exists, so it is sent to the site admin.', 'webofd-gf-to-elementor' ) );

			return $this->tags->convert( '{admin_email}' )->text;
		}

		if ( 'routing' === $type ) {
			return $this->routing_recipients( $notification, $result );
		}

		return $this->text( (string) ( $notification['to'] ?? '' ), $result );
	}

	/**
	 * Sends a routed notification to every routed address, and reports the rules.
	 *
	 * @param array<string, mixed> $notification GF notification.
	 * @param Mapped               $result       Collects notes.
	 *
	 * @return string Comma-separated addresses.
	 */
	private function routing_recipients( array $notification, Mapped &$result ): string {
		$emails = array();

		foreach ( (array) ( $notification['routing'] ?? array() ) as $rule ) {
			if ( is_array( $rule ) && '' !== (string) ( $rule['email'] ?? '' ) ) {
				$emails[] = $this->tags->convert( (string) $rule['email'] )->text;
			}
		}

		$emails = array_values( array_unique( $emails ) );

		/* translators: %s: comma-separated email addresses. */
		$result = $result->with_note( sprintf( __( 'Routing rules dropped: every submission now goes to all routed addresses (%s).', 'webofd-gf-to-elementor' ), implode( ', ', $emails ) ) );

		return implode( ',', $emails );
	}

	/**
	 * Resolves the From address, defaulting to the admin email like GF does.
	 *
	 * @param array<string, mixed> $notification GF notification.
	 * @param Mapped               $result       Collects notes.
	 *
	 * @return string From address.
	 */
	private function sender( array $notification, Mapped &$result ): string {
		$from = trim( (string) ( $notification['from'] ?? '' ) );

		return $this->text( '' === $from ? '{admin_email}' : $from, $result );
	}

	/**
	 * Resolves reply-to, which Elementor only accepts as a form field.
	 *
	 * @param string $reply_to GF reply-to value.
	 * @param Mapped $result   Collects notes.
	 *
	 * @return string Elementor field custom_id, or "".
	 */
	private function reply_to( string $reply_to, Mapped &$result ): string {
		if ( '' === trim( $reply_to ) ) {
			return '';
		}

		$field_id = $this->tags->single_field_id( $reply_to );

		if ( null === $field_id ) {
			/* translators: %s: Gravity Forms reply-to value. */
			$result = $result->with_note( sprintf( __( 'Reply-to "%s" dropped: Elementor only accepts a form field as reply-to.', 'webofd-gf-to-elementor' ), $reply_to ) );

			return '';
		}

		return $field_id;
	}

	/**
	 * Converts merge tags in a setting and notes any tags that could not be converted.
	 *
	 * @param string $text   GF setting value.
	 * @param Mapped $result Collects notes.
	 *
	 * @return string Converted value.
	 */
	private function text( string $text, Mapped &$result ): string {
		$converted = $this->tags->convert( $text );

		if ( $converted->has_problems() ) {
			/* translators: %s: comma-separated Gravity Forms merge tags. */
			$result = $result->with_note( sprintf( __( 'Merge tags removed or simplified: %s.', 'webofd-gf-to-elementor' ), implode( ', ', $converted->problems ) ) );
		}

		return $converted->text;
	}

	/**
	 * Adds notes for conditional logic and attachments, which are not carried over.
	 *
	 * @param Mapped               $result       Result so far.
	 * @param array<string, mixed> $notification GF notification.
	 *
	 * @return Mapped Result with notes.
	 */
	private function add_notes( Mapped $result, array $notification ): Mapped {
		if ( ConditionalLogicDescriber::has_rules( $notification['conditionalLogic'] ?? null ) ) {
			/* translators: %s: the rule in words. */
			$result = $result->with_note( sprintf( __( 'Conditional logic dropped, so it is now always sent (was: %s).', 'webofd-gf-to-elementor' ), $this->logic->describe( $notification['conditionalLogic'], $this->fields, true ) ) );
		}

		if ( ! empty( $notification['enableAttachments'] ) ) {
			$result = $result->with_note( __( 'Uploaded files are no longer attached; the email links to them instead.', 'webofd-gf-to-elementor' ) );
		}

		return $result;
	}

	/**
	 * Appends the slot suffix to every settings key.
	 *
	 * @param array<string, mixed> $values Settings with base keys.
	 * @param string               $suffix "" or "_2".
	 *
	 * @return array<string, mixed> Settings with suffixed keys.
	 */
	private function suffixed( array $values, string $suffix ): array {
		$suffixed = array();

		foreach ( $values as $key => $value ) {
			$suffixed[ $key . $suffix ] = $value;
		}

		return $suffixed;
	}

	/**
	 * Returns a notification's display name.
	 *
	 * @param array<string, mixed> $notification GF notification.
	 *
	 * @return string Name.
	 */
	private function name( array $notification ): string {
		return (string) ( $notification['name'] ?? $notification['id'] ?? '' );
	}
}
