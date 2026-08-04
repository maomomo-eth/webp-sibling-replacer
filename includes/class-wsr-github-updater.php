<?php
/**
 * GitHub Release 一键更新配置。
 *
 * @package WebP_Sibling_Replacer
 */

defined( 'ABSPATH' ) || exit;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api;

final class WSR_GitHub_Updater {
	const REPOSITORY_URL = 'https://github.com/maomomo-eth/webp-sibling-replacer/';
	const PLUGIN_SLUG    = 'webp-sibling-replacer';

	public static function init( $plugin_file ) {
		$checker = PucFactory::buildUpdateChecker( self::REPOSITORY_URL, $plugin_file, self::PLUGIN_SLUG );
		$checker->setBranch( 'main' );
		$checker->getVcsApi()->enableReleaseAssets( '/^webp-sibling-replacer\.zip$/i', Api::REQUIRE_RELEASE_ASSETS );
		$checker->addFilter( 'vcs_update_detection_strategies', array( __CLASS__, 'only_latest_release' ) );
	}

	public static function only_latest_release( $strategies ) {
		if ( ! isset( $strategies[ Api::STRATEGY_LATEST_RELEASE ] ) ) {
			return array();
		}
		return array( Api::STRATEGY_LATEST_RELEASE => $strategies[ Api::STRATEGY_LATEST_RELEASE ] );
	}
}
