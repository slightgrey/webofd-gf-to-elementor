<?php
/**
 * Tests for ConfirmationMapper.
 *
 * @package GFToElementor
 */

declare( strict_types=1 );

namespace GFToElementor\Tests\Unit;

use GFToElementor\Convert\ConditionalLogicDescriber;
use GFToElementor\Convert\ConfirmationMapper;
use GFToElementor\Convert\MergeTagConverter;
use GFToElementor\Convert\ResultStatus;
use GFToElementor\Convert\SettingsResult;

/**
 * The default confirmation becomes a success message or a redirect.
 */
final class ConfirmationMapperTest extends FieldMapperTestCase {

	/**
	 * Maps confirmations against the kitchen-sink fields; page 42 resolves, others don't.
	 *
	 * @param array<string, array<string, mixed>> $confirmations Confirmations.
	 *
	 * @return SettingsResult Result.
	 */
	private function map( array $confirmations ): SettingsResult {
		$form = $this->form( 'kitchen-sink' );
		$this->mapper()->map_form( $form );
		$tags      = new MergeTagConverter( $this->ids->map(), 'admin@example.test', 'Kitchen Sink' );
		$permalink = static fn( int $id ): string|false => 42 === $id ? 'https://example.test/thanks/' : false;

		return ( new ConfirmationMapper( $tags, new ConditionalLogicDescriber(), $form['fields'], $permalink ) )->map( $confirmations );
	}

	/**
	 * A plain message becomes the success message.
	 *
	 * @return void
	 */
	public function test_real_message(): void {
		$result = $this->map( $this->form( 'form-1' )['confirmations'] );

		$this->assertSame(
			array(
				'success_message' => 'Thanks for contacting us! We will get in touch with you shortly.',
				'custom_messages' => 'yes',
			),
			$result->settings
		);
		$this->assertSame( array(), $result->actions );
		$this->assertSame( ResultStatus::Converted, $result->entries[0]->status );
	}

	/**
	 * HTML and merge tags are stripped from a message, and both are reported.
	 *
	 * @return void
	 */
	public function test_message_with_html_and_tags(): void {
		$result = $this->map(
			array(
				'c' => array(
					'isDefault' => true,
					'type'      => 'message',
					'message'   => '<p>Thanks <strong>{Your Name (First):1.3}</strong>!</p><p>We\'ll be in touch.</p>',
				),
			)
		);

		$this->assertSame( "Thanks ! We'll be in touch.", $result->settings['success_message'] );
		$this->assertSame( ResultStatus::Approximated, $result->entries[0]->status );
		$this->assertStringContainsString( 'Merge tags were removed', $result->entries[0]->reason );
		$this->assertStringContainsString( 'HTML formatting was removed', $result->entries[0]->reason );
	}

	/**
	 * A page confirmation redirects to the page with the decoded query string and field shortcodes;
	 * the conditional redirect is dropped with its rule.
	 *
	 * @return void
	 */
	public function test_page_redirect_and_conditional(): void {
		$result = $this->map( $this->form( 'kitchen-sink' )['confirmations'] );

		$this->assertSame( array( 'redirect' ), $result->actions );
		$this->assertSame( 'https://example.test/thanks/?ref=gf&name=[field id="your_name_first"]', $result->settings['redirect_to'] );
		$this->assertSame( ResultStatus::Converted, $result->entries[0]->status );
		$this->assertSame( ResultStatus::Dropped, $result->entries[1]->status );
		$this->assertStringContainsString( 'This one applied only if "Topic" is "sales".', $result->entries[1]->reason );
	}

	/**
	 * A redirect keeps an existing query string and appends with "&".
	 *
	 * @return void
	 */
	public function test_redirect_with_query_string(): void {
		$result = $this->map(
			array(
				'c' => array(
					'isDefault'   => true,
					'type'        => 'redirect',
					'url'         => 'https://example.test/next?step=2',
					'queryString' => 'src=gf',
				),
			)
		);

		$this->assertSame( 'https://example.test/next?step=2&src=gf', $result->settings['redirect_to'] );
	}

	/**
	 * A page that no longer exists falls back to the default message and is reported.
	 *
	 * @return void
	 */
	public function test_missing_page(): void {
		$result = $this->map(
			array(
				'c' => array(
					'isDefault' => true,
					'type'      => 'page',
					'pageId'    => '7',
				),
			)
		);

		$this->assertSame( array(), $result->settings );
		$this->assertSame( array(), $result->actions );
		$this->assertSame( ResultStatus::Dropped, $result->entries[0]->status );
	}
}
