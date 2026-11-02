<?php
namespace Shazzad\PluginInsights\Tests\V4;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * The package ships inside wordpress.org plugins, whose review rejects any
 * plugin carrying its own updater. These checks run the same greps a
 * reviewer (and Adminkeep's `bin/build`) runs, against every shipped PHP
 * file: `src/` and `load.php`.
 */
class WporgPolicyTest extends PHPUnitTestCase {

	/**
	 * @return string[] Absolute paths of every shipped PHP file.
	 */
	private function files(): array {
		$root  = dirname( __DIR__, 2 );
		$files = array_merge(
			[ $root . '/load.php', $root . '/src/V4/Insights.php' ],
			glob( $root . '/src/V4/Insights/*.php' )
		);

		$this->assertGreaterThanOrEqual( 7, count( $files ), 'Package files not found.' );

		return $files;
	}

	/** @test */
	public function no_update_hooks_or_updater_code() {
		$needles = '/site_transient_update_plugins|site_transient_update_themes|pre_set_site_transient'
			. '|auto_update_plugin|auto_update_theme|update_plugins_|update_themes_|automatic_updater_disabled'
			. '|auto_update_core|plugins_api|upgrader_pre_download|upgrader_package_options'
			. '|PluginUpdater|plugin-updater|vendor\/autoload|wp-repo\/v3|build:updater/';

		foreach ( $this->files() as $file ) {
			$code = file_get_contents( $file );

			$this->assertSame( 0, preg_match( $needles, $code, $match ), basename( $file ) . ' contains ' . ( $match[0] ?? '' ) );
		}
	}

	/** @test */
	public function no_reference_to_update_or_license_classes() {
		$forbidden = '/\b(Updater|LicensePage|Integration|Tracker|Store|UpdateMessage|Notices)\b|\\\\Admin\\\\/';

		foreach ( $this->files() as $file ) {
			$code = file_get_contents( $file );

			$this->assertSame( 0, preg_match( $forbidden, $code, $match ), basename( $file ) . ' references ' . ( $match[0] ?? '' ) );
		}
	}

	/** @test */
	public function imports_stay_inside_the_package() {
		foreach ( $this->files() as $file ) {
			preg_match_all( '/^use\s+([^;]+);/m', file_get_contents( $file ), $matches );

			foreach ( $matches[1] as $import ) {
				$this->assertMatchesRegularExpression(
					'/^(WP_Error|Shazzad\\\\PluginInsights\\\\V4\\\\Insights(\\\\(Client|Collector|Consent|Notice|Scheduler))?)$/',
					trim( $import ),
					basename( $file ) . " imports {$import}"
				);
			}
		}
	}

	/** @test */
	public function every_file_keeps_the_abspath_guard_and_classes_their_class_exists_guard() {
		foreach ( $this->files() as $file ) {
			$code = file_get_contents( $file );

			// Plain defined(): Plugin Check's direct-file-access check (it scans
			// vendor/) does not recognise the namespaced \defined() form.
			$this->assertStringContainsString( "if ( ! defined( 'ABSPATH' ) ) {", $code, basename( $file ) );
			$this->assertStringNotContainsString( "\\defined( 'ABSPATH' )", $code, basename( $file ) );

			if ( 'load.php' !== basename( $file ) ) {
				$this->assertStringContainsString( "if ( ! class_exists( __NAMESPACE__ . '\\\\", $code, basename( $file ) );
			}
		}
	}

	/** @test */
	public function load_php_requires_every_source_file() {
		$root   = dirname( __DIR__, 2 );
		$loader = file_get_contents( $root . '/load.php' );
		$src    = array_merge( [ $root . '/src/V4/Insights.php' ], glob( $root . '/src/V4/Insights/*.php' ) );

		foreach ( $src as $file ) {
			$relative = substr( $file, strlen( $root ) );
			$this->assertStringContainsString( "require_once __DIR__ . '{$relative}';", $loader, $relative );
		}
	}
}
