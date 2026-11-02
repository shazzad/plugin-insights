<?php
/**
 * Loads Plugin Insights without Composer.
 *
 * For a plugin that bundles this package by hand instead of through
 * Composer: `require_once __DIR__ . '/lib/plugin-insights/load.php';`.
 * Every class keeps its own class_exists() guard, so two plugins on one
 * site loading their own copies never collide.
 *
 * @package Shazzad\PluginInsights
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/V4/Insights/Consent.php';
require_once __DIR__ . '/src/V4/Insights/Collector.php';
require_once __DIR__ . '/src/V4/Insights/Client.php';
require_once __DIR__ . '/src/V4/Insights/Scheduler.php';
require_once __DIR__ . '/src/V4/Insights/Notice.php';
require_once __DIR__ . '/src/V4/Insights.php';
