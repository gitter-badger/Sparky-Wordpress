<?php

/*
 * Plugin Name: Sparky Framework
 * Plugin URI: http://michaelbeers.nl
 * Author: Sparky Dev Team
 * Version: 1.0.0
 * 
 * Description: Sparky Framework allows theme and plugin developers to create
 * admin pages, options, meta boxes and theme customizers.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/*
 * Define some constants that can be used.
 */
defined('SF') || define('SF', 'sparky-framework');
defined('SF_NAME') || define('SF_NAME', 'Sparky Framework');
defined('SF_VERSION') || define('SF_VERSION', 1.0);
defined('SF_I18N') || define('SF_I18N', 'sparky-framework');
defined('SF_PATH') || define('SF_PATH', trailingslashit(dirname(__FILE__)));

/*
 * Include all the components Sparky Framework is using.
 */
require_once(SF_PATH . 'customizer/sparky-option.php');

require_once(SF_PATH . 'sparky-framework.php');

/**
 * Sparky Framework's Plugin Class.
 * 
 * This class is used when Sparky Framework is loaded as a plugin. Otherwise you
 * can use the sparky-embedder.php to intergrate Sparky Framework into a theme.
 *
 * @package     Sparky
 * @author      Sparky Dev Team
 * @copyright   Copyright (c) 2015 - 2015, Michael Beers | Online Media en Design
 * @since       Version 1.0.0
 */
class Sparky_Plugin {
	
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action('plugins_loaded', array($this, 'loadTextDomain'));
		add_action('plugins_loaded', array($this, 'forceLoadingFirst'), 10, 1);
		add_filter('plugin_row_meta', array($this, 'addPluginLinks'), 10, 2);

		// Initialize options, but do not really create them yet
		add_action('after_setup_theme', array($this, 'triggerOptionCreation'), 5);
		// Create the options
		add_action('init', array($this, 'triggerOptionCreation'), 11);
	}

	/**
	 * Load the plugins translation files.
	 * 
	 * Translations are located in the ./assetes/languages folder prefixed by 
	 * sparky-framework-*.po
	 */
	public function loadTextDomain() {
		load_plugin_textdomain(SF_I18N, false, basename(dirname(__FILE__)) . '/assets/languages/');
	}

	/**
	 * Forces Sparky Framework to be loaded first. This is to ensure that 
	 * plugins that use the framework have access to this class.
	 */
	public function forceLoadingFirst() {
		$tfFileName = basename(__FILE__);
		if ($plugins = get_option('active_plugins')) {
			foreach ($plugins as $key => $pluginPath) {
				// If we are the first one to load already, don't do anything
				// Else force it!
				if (strpos($pluginPath, $tfFileName) !== false && $key == 0) {
					break;
				} else if (strpos($pluginPath, $tfFileName) !== false) {
					array_splice($plugins, $key, 1);
					array_unshift($plugins, $pluginPath);
					update_option('active_plugins', $plugins);
					break;
				}
			}
		}
	}

	/**
	 * Add plugin links to the plugin overview.
	 * 
	 * @param array $pluginMeta
	 * @param string $pluginFile
	 * @return array
	 * @todo Change the links.
	 */
	public function addPluginLinks($pluginMeta, $pluginFile) {
		if ($pluginFile == plugin_basename(__FILE__)) {
			$pluginMeta[] = sprintf("<a href='%s' target='_blank'>%s</a>", "#", __("View Documentation", SF_I18N));
			$pluginMeta[] = sprintf("<a href='%s' target='_blank'>%s</a>", "#", __("View GitHub Repo", SF_I18N));
			$pluginMeta[] = sprintf("<a href='%s' target='_blank'>%s</a>", "#", __("View Issue Tracker", SF_I18N));
		}

		return $pluginMeta;
	}

	/**
	 * Trigger that allows loading all the options.
	 */
	public function triggerOptionCreation() {
		// The after_setup_theme is the initialization stage
		if (current_filter() == 'after_setup_theme') {
			Sparky::$init = true;
		}

		do_action('sf_create_options');

		Sparky::$init = false;
	}

}

// Run Sparky Framework as Plugin.
new Sparky_Plugin();

// TODO remove this test.
$sparky = Sparky::getInstance('my-theme');
