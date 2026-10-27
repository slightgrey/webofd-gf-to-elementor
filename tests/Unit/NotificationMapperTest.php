<?php
/**
 * Tests for NotificationMapper.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ConditionalLogicDescriber;
use GFToElementor\Convert\MergeTagConverter;
use GFToElementor\Convert\NotificationMapper;
use GFToElementor\Convert\ResultStatus;
use GFToElementor\Convert\SettingsResult;

/**
 * Notifications become Email / Email 2 actions; the rest are reported.
 */
final class NotificationMapperTest extends FieldMapperTestCase {

	/**
	 * Maps a fixture form's notifications (fields first, so the field map exists).
	 *
	 * @param array<string, mixed> $form Form.
	 *
	 * @return SettingsResult Result.
	 */
	private function map_notifications( array $form ): SettingsResult {
		$this->mapper()->map_form( $form );
		$tags = new MergeTagConverter( $this->ids->map(), 'admin@example.test', (string) $form['title'] );

		return ( new NotificationMapper( $tags, new ConditionalLogicDescriber(), $form['fields'] ) )->map( $form['notifications'] );
	}

	/**
	 * No notifications, no email actions.
	 *
	 * @return void
	 */
	public function test_no_notifications(): void {
		$form                  = $this->form( 'form-1' );
		$form['notifications'] = array();
		$result                = $this->map_notifications( $form );

		$this->assertSame( array(), $result->actions );
		$this->assertSame( array(), $result->settings );
	}

	/**
	 * Form #1's real admin notification becomes the Email action.
	 *
	 * @return void
	 */
	public function test_real_notification(): void {
		$result = $this->map_notifications( $this->form( 'form-1' ) );

		$this->assertSame( array( 'email' ), $result->actions );
		$this->assertSame(
			array(
				'email_to'           => 'info@rentabode.com.au',
				'email_subject'      => 'New submission from Contact Form',
				'email_content'      => '[all-fields]',
				'email_from'         => 'admin@example.test',
				'email_content_type' => 'html',
				'form_metadata'      => array(),
			),
			$result->settings
		);
		$this->assertSame( ResultStatus::Converted, $result->entries[0]->status );
	}

	/**
	 * Kitchen sink: two notifications fill Email and Email 2; the third, the inactive and the
	 * save-and-continue ones are dropped with reasons.
	 *
	 * @return void
	 */
	public function test_two_slots_and_dropped_ones(): void {
		$result = $this->map_notifications( $this->form( 'kitchen-sink' ) );
		$status = array_map( static fn( $entry ): string => $entry->gf_ref . '=' . $entry->status->value, $result->entries );

		$this->assertSame( array( 'email', 'email2' ), $result->actions );
		$this->assertSame(
			array( 'Admin Notification=approximated', 'User Confirmation=approximated', 'Sales routing=dropped', 'Old disabled=dropped', 'Save and continue link=dropped' ),
			$status
		);
		$this->assertStringContainsString( 'only two email actions', $result->entries[2]->reason );
		$this->assertStringContainsString( 'Inactive', $result->entries[3]->reason );
		$this->assertStringContainsString( '"form_saved"', $result->entries[4]->reason );
	}

	/**
	 * The first notification's merge tags, reply-to field and cc carry over; {entry_id} is reported.
	 *
	 * @return void
	 */
	public function test_first_notification_settings(): void {
		$result = $this->map_notifications( $this->form( 'kitchen-sink' ) );

		$this->assertSame( 'admin@example.test', $result->settings['email_to'] );
		$this->assertSame( 'New: Kitchen Sink from [field id="your_name_first"]', $result->settings['email_subject'] );
		$this->assertSame( "[all-fields]\nEntry: \nPhone: [field id=\"phone\"]", $result->settings['email_content'] );
		$this->assertSame( 'email', $result->settings['email_reply_to'] );
		$this->assertSame( 'cc@example.test', $result->settings['email_to_cc'] );
		$this->assertSame( 'Site', $result->settings['email_from_name'] );
		$this->assertStringContainsString( '{entry_id}', $result->entries[0]->reason );
	}

	/**
	 * The second notification uses the _2 keys, sends to the email field, and reports the
	 * literal reply-to and its conditional logic.
	 *
	 * @return void
	 */
	public function test_second_notification_settings(): void {
		$result = $this->map_notifications( $this->form( 'kitchen-sink' ) );

		$this->assertSame( '[field id="email"]', $result->settings['email_to_2'] );
		$this->assertSame( 'Thanks [field id="your_name_first"]', $result->settings['email_subject_2'] );
		$this->assertSame( 'info@example.test', $result->settings['email_from_2'] );
		$this->assertSame( 'audit@example.test', $result->settings['email_to_bcc_2'] );
		$this->assertArrayNotHasKey( 'email_reply_to_2', $result->settings );
		$this->assertSame( array(), $result->settings['form_metadata_2'] );
		$this->assertStringContainsString( 'Reply-to "info@example.test" dropped', $result->entries[1]->reason );
		$this->assertStringContainsString( 'Conditional logic dropped, so it is now always sent (was: Only if "Terms" is "1")', $result->entries[1]->reason );
	}

	/**
	 * A routed notification goes to every routed address, with the change reported.
	 *
	 * @return void
	 */
	public function test_routing_sends_to_all(): void {
		$form                  = $this->form( 'kitchen-sink' );
		$form['notifications'] = array( 'n3' => $form['notifications']['n3'] );
		$result                = $this->map_notifications( $form );

		$this->assertSame( 'sales@example.test,help@example.test', $result->settings['email_to'] );
		$this->assertStringContainsString( 'Routing rules dropped', $result->entries[0]->reason );
	}
}
