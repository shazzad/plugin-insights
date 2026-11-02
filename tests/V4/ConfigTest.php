<?php
namespace Shazzad\PluginInsights\Tests\V4;

use Shazzad\PluginInsights\V4\Insights;
use Shazzad\PluginInsights\V4\Insights\Client as InsightsClient;
use WP_Error;

/**
 * Configurations no request can succeed with: an empty `api_url`, or a
 * missing or malformed `product_uid`. None of them draws the consent
 * notice, stores anything or makes an HTTP call.
 */
class ConfigTest extends TestCase {

	/** @test */
	public function an_empty_api_url_draws_no_notice() {
		$insights = $this->create_insights( [ 'api_url' => '' ] );

		$this->assertNull( $insights->notice );
		$this->assertSame( 'wprepo_insights_no_api_url', $insights->client->get_config_error()->get_error_code() );
	}

	/** @test */
	public function a_trailing_slash_on_api_url_does_not_double_the_route_slash() {
		$insights = $this->create_insights( [ 'api_url' => 'https://repo.example.com/wp-json/wp-repo/v4/' ] );

		$this->assertSame( [], $this->doing_it_wrong );
		$this->assertSame(
			'https://repo.example.com/wp-json/wp-repo/v4/plugins/prod_abc/track',
			$insights->client->get_url( 'track' )
		);
	}

	/**
	 * @test
	 * @dataProvider provide_malformed_uids
	 */
	public function a_malformed_uid_is_treated_as_missing( string $uid ) {
		$insights = $this->create_insights( [ 'product_uid' => $uid ] );

		$this->assertCount( 1, $this->doing_it_wrong );
		$this->assertStringContainsString( 'not a valid uid', $this->doing_it_wrong[0] );
		$this->assertNull( $insights->notice );
		$this->assertSame( 'wprepo_insights_no_product_uid', $insights->client->get_config_error()->get_error_code() );
		$this->assertSame( [], $this->http );
	}

	public function provide_malformed_uids(): array {
		return [
			'upper case'  => [ 'PROD_ABC' ],
			'hyphen'      => [ 'prod-abc' ],
			'no suffix'   => [ 'prod_' ],
			'numeric id'  => [ '12' ],
			'inner space' => [ 'prod_abc def' ],
			'path chars'  => [ 'prod_abc/../x' ],
			'trailing nl' => [ "prod_abc\n" ],
		];
	}

	/** @test */
	public function valid_uids_pass_the_shape_check() {
		$this->assertTrue( InsightsClient::is_valid_uid( 'prod_9f3k2m9q4x8z7w1t2r5s' ) );
		$this->assertTrue( InsightsClient::is_valid_uid( 'prod_abc' ) );
		$this->assertFalse( InsightsClient::is_valid_uid( null ) );
		$this->assertFalse( InsightsClient::is_valid_uid( 12 ) );
	}

	/**
	 * @test
	 * @dataProvider provide_unusable_configs
	 */
	public function opt_in_stores_nothing_when_nothing_could_be_sent( array $overrides, string $code ) {
		$insights = $this->create_insights( $overrides );

		$result = $insights->opt_in();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( $code, $result->get_error_code() );
		$this->assertSame( [], $this->options, 'No consent, no token.' );
		$this->assertSame( [], $this->cron );
		$this->assertSame( [], $this->http );
		$this->assertFalse( $insights->has_consent() );
	}

	public function provide_unusable_configs(): array {
		return [
			'no api_url' => [ [ 'api_url' => '' ], 'wprepo_insights_no_api_url' ],
			'no uid'     => [ [ 'product_uid' => '' ], 'wprepo_insights_no_product_uid' ],
			'bad uid'    => [ [ 'product_uid' => 'prod-abc' ], 'wprepo_insights_no_product_uid' ],
		];
	}

	/** @test */
	public function config_errors_are_not_retryable_optout_failures() {
		foreach ( InsightsClient::CONFIG_ERROR_CODES as $code ) {
			$this->assertFalse( Insights\Scheduler::optout_should_retry( new WP_Error( $code, 'x' ) ), $code );
		}

		$this->assertTrue( Insights\Scheduler::optout_should_retry( new WP_Error( 'http_request_failed', 'x' ) ) );
	}
}
