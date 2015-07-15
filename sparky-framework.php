<?php

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Sparky Framework's Main Instance Class.
 *
 * @package     Sparky
 * @author      Sparky Dev Team
 * @copyright   Copyright (c) 2015 - 2015, Michael Beers | Online Media en Design
 * @since       Version 1.0.0
 */
final class Sparky {

	/**
	 * Initializing phase active?
	 * 
	 * @var boolean 
	 */
	public static $init = false;

	/**
	 * Framework instances.
	 * 
	 * @var array 
	 */
	public static $instances = array();

	// -------------------------------------------------------------------------

	/**
	 * The token.
	 * 
	 * @var string
	 */
	public $token;

	/**
	 * The settings.
	 * 
	 * @var array 
	 */
	public $settings = array();

	/**
	 * The default settings for the Sparky Framework.
	 * 
	 * @var array 
	 */
	public $defaultSettings = array(
		'css' => 'generate',
		'tracking' => false
	);

	// -------------------------------------------------------------------------

	/**
	 * The admin panel screens.
	 * 
	 * @var array 
	 */
	private $adminPanels = array();

	/**
	 * The admin panel meta boxes.
	 * 
	 * @var array 
	 */
	private $metaBoxes = array();

	/**
	 * The theme customizer sections.
	 * 
	 * @var array 
	 */
	private $customizerSections = array();

	/**
	 * The widget areas.
	 * 
	 * @var array
	 */
	private $widgetAreas = array();

	/**
	 * The google font options.
	 * 
	 * @var array
	 */
	private $googleFontOptions = array();

	// -------------------------------------------------------------------------

	/**
	 * All the options from all instances.
	 * 
	 * @var array
	 */
	private $options = array();

	/**
	 * All the option IDs from all instances.
	 * 
	 * @var array 
	 */
	private $optionIDs = array();

	/**
	 * All the used options.
	 * 
	 * @var array 
	 */
	private $optionsUsed = array();

	/**
	 * All the options to remove.
	 * 
	 * @var array
	 */
	private $optionsToRemove = array();

	/* ---------------------------------------------------------------------- */
	/* Constructors, Destructors, Init and Magic methods. */
	/* ---------------------------------------------------------------------- */

	/**
	 * Returns an instance of the framework.
	 * 
	 * @param string $token
	 * @return \Sparky
	 */
	public static function getInstance($token) {
		$newToken = static::satanizeToken($token);
		foreach (self::$instances as $instance) {
			if ($instance->token === $newToken) {
				return $instance;
			}
		}

		$newInstance = new Sparky($newToken);
		self::$instances[] = $newInstance;
		return $newInstance;
	}

	/**
	 * Class constructor.
	 * 
	 * @param string $token
	 */
	private function __construct($token) {
		$this->token = static::satanizeToken($token);
		$this->settings = $this->defaultSettings;

		// Call the initializing hooks.
		do_action('sf_init', $this);
		do_action('sf_init_' . $this->token, $this);

		// Initialize all the options after setting up the theme.
		add_action('after_setup_theme', array($this, 'initOptions'), 7);

		// Clean all the options for database listing.
		add_action('init', array($this, 'cleanOptionsForDBListing'), 12);

		// More cleaning when you are in the admin panel.
		if (is_admin()) {
			add_action('init', array($this, 'cleanThemeForModListing'), 12);
			add_action('init', array($this, 'cleanMetaForDbListing'), 12);
			add_action('sf_create_option_' . $this->token, array($this, "verifyUniqueIDs"));
		}

		add_action('admin_enqueue_scripts', array($this, "initAdminScripts"));
		add_action('wp_enqueue_scripts', array($this, "initFrontEndScripts"));
		add_action('sf_create_option_' . $this->token, array($this, "rememberGoogleFonts"));
		add_action('sf_create_option_' . $this->token, array($this, "rememberAllOptions"));
		add_filter('sf_create_option_continue_' . $this->token, array($this, "removeChildThemeOptions"), 10, 2);

		// Create a save option filter for customizer options
		add_filter('pre_update_option', array($this, 'addCustomizerSaveFilter'), 10, 3);
	}

	/**
	 * Initialize and Synchonize the given options with the existing options
	 * and store them into the $options variable.
	 * 
	 * @return array
	 */
	public function initOptions() {
		if (empty($this->options)) {
			$this->options = array();
		}

		if (empty($this->options[$this->token])) {
			$this->options[$this->token] = array();
		} else {
			return $this->options[$this->token];
		}

		// Check if we have options saved already.
		$currentOptions = get_option($this->token . '_options');

		// First time run, this action hook can be used to trigger something.
		if ($currentOptions === false) {
			do_action('sf_init_no_options_' . $this->token);
		}

		// Put all the available options in our global variable for future 
		// checking.
		if (!empty($currentOptions) && !count($this->options[$this->token])) {
			$this->options[$this->token] = unserialize($currentOptions);
		}

		return $this->options[$this->token];
	}

	/**
	 * Initialize the admin scripts.
	 * 
	 * @TODO Create method.
	 */
	public function initAdminScripts() {
		
	}

	/**
	 * Initialize the front end scripts.
	 * 
	 * @TODO Create method.
	 */
	public function initFrontEndScripts() {
		
	}

	/* ---------------------------------------------------------------------- */
	/* Public methods. */
	/* ---------------------------------------------------------------------- */

	/**
	 * Checks all the ids and shows a warning when multiple occurances of an id 
	 * is found. This is to ensure that there won't be any option conflicts.
	 * 
	 * @param mixed $option
	 */
	public function verifyUniqueIDs($option) {
		if (empty($option->settings['id'])) {
			return;
		}

		// During initialization don't display ID errors.
		if (self::$init) {
			return;
		}

		if (in_array($option->settings['id'], $this->OptionIDs)) {
			self::displayFrameworkError(sprintf(__('All option IDs must be unique. The id %s has been used multiple times.', SF_I18N), '<code>' . $option->settings['id'] . '</code>'));
		} else {
			$this->OptionIDs[] = $option->settings['id'];
		}
	}

	/**
	 * Adds a 'tf_save_option_{namespace}_{optionID}' filter to all Customizer options
	 * which are just about to be saved
	 * 
	 * This uses the `pre_update_option` filter to check all the options being saved if it's
	 * a theme_mod option. It further checks whether these are Titan customizer options,
	 * then attaches the new hook into those.
	 *
	 * @param	$value mixed The value to be saved in the options
	 * @param	$optionName string The option name
	 * @param	$oldValue mixed The previously stored value
	 * @return	mixed The modified value to save
	 * @since   1.8
	 * @see		pre_update_option filter
	 */
	public function addCustomizerSaveFilter($value, $optionName, $oldValue) {
		$theme = get_option('stylesheet');

		// Intercept theme mods only.
		if (!preg_match('/^theme_mods_' . $theme . '/', $optionName)) {
			return $value;
		}

		// We expect theme mods to be an array.
		if (!is_array($value)) {
			return $value;
		}

		// Checks whether a Sparky customizer is in place.
		$customizerUsed = false;

		// Go through all our customizer options and filter them for saving.
		$optionIDs = array();
		foreach ($this->customizerSections as $customizer) {
			foreach ($customizer->options as $option) {
				if (!empty($option->settings['id'])) {
					$optionID = $option->settings['id'];
					$themeModName = $this->token . '_' . $option->settings['id'];

					if (!array_key_exists($themeModName, $value)) {
						continue;
					}

					$customizerUsed = true;

					// Try and unserialize if possible.
					$tempValue = $value[$themeModName];
					if (is_serialized($tempValue)) {
						$tempValue = unserialize($tempValue);
					}

					// Hook 'tf_save_option_{namespace}'.
					$newValue = apply_filters('sf_save_option_' . $this->token, $tempValue, $option->settings['id']);

					// Hook 'tf_save_option_{namespace}_{optionID}'.
					$newValue = apply_filters('sf_save_option_' . $themeModName, $tempValue);

					// We mainly check for equality here so that we won't have 
					// to serialize IF the value wasn't touched anyway.
					if ($newValue != $tempValue) {
						if (is_array($newValue)) {
							$newValue = serialize($newValue);
						}

						$value[$themeModName] = $newValue;
					}
				}
			}
		}

		// Hook 'sf_pre_save_options_{namespace}' - action pre-saving.
		if ($customizerUsed) {
			do_action('sf_pre_save_options_' . $this->token, $this->customizerSections);
		}

		return $value;
	}

	/**
	 * Saves all the options for the current instance.
	 * 
	 * @return array
	 */
	public function saveOptions() {
		update_option($this->token . '_options', serialize($this->options[$this->token]));
		do_action('sf_save_options_' . $this->token);
		return $this->options[$this->token];
	}

	/**
	 * Removes a option. Use this method only for child themes. This will 
	 * prevent to create some options by the parent theme only.
	 * 
	 * @param string $optionName
	 */
	public function removeOption($optionName) {
		$this->optionsToRemove[] = $optionName;
	}

	/**
	 * Hook to the tf_create_option_continue filter, to check whether or not to 
	 * continue adding an option (if the option id was used in 
	 * $sparky->removeOption).
	 * 
	 * @param boolean $continueCreating
	 * @param array $optionSettings
	 * @return boolean
	 */
	public function removeChildThemeOptions($continueCreating, $optionSettings) {
		if (!count($this->optionsToRemove)) {
			return $continueCreating;
		}

		if (empty($optionSettings['id'])) {
			return $continueCreating;
		}

		if (in_array($optionSettings['id'], $this->optionsToRemove)) {
			return false;
		}

		return $continueCreating;
	}

	/**
	 * Action hook on sf_create_option to remember all the options, used to
	 * ensure that our serialized option does not get cluttered with unused
	 * options
	 * 
	 * @param Sparky_Option $option
	 */
	public function rememberAllOptions($option) {
		if (!empty($option->settings['id'])) {
			$this->optionsUsed[$option->settings['id']] = $option;
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Building methods. */
	/* ---------------------------------------------------------------------- */

	/**
	 * Create a new admin panel for a plugin/theme.
	 * 
	 * @param array $settings
	 */
	public function createAdminPanel($settings) {
		$obj = new Sparky_AdminPanel($settings, $this);
		$this->adminPanels[] = $obj;

		do_action('sf_create_admin_panel_' . $this->token, $obj);

		return $obj;
	}

	/**
	 * Create a new meta box for a plugin/theme.
	 * 
	 * @param array $settings
	 */
	public function createMetaBox($settings) {
		$obj = new Sparky_MetaBox($settings, $this);
		$this->metaBoxes[] = $obj;

		do_action('sf_create_meta_box_' . $this->token, $obj);

		return $obj;
	}

	/**
	 * Create a new customizer section for a plugin/theme.
	 * 
	 * @param array $settings
	 */
	public function createCustomizerSection($settings) {
		$obj = new Sparky_CustomizerSection($settings, $this);
		$this->customizerSections[] = $obj;

		do_action('sf_create_customizer_section_' . $this->token, $obj);

		return $obj;
	}

	/**
	 * Create a new widget area for a plugin/theme.
	 * 
	 * @param array $settings
	 */
	public function createWidgetArea($settings) {
		$obj = new Sparky_WdigetArea($settings, $this);
		$this->widgetAreas[] = $obj;

		do_action('sf_create_widget_area_' . $this->token, $obj);

		return $obj;
	}

	/**
	 * Create a new shortcode for a plugin/theme.
	 * 
	 * @param array $settings
	 */
	public function createShortcode($settings) {
		do_action('sf_create_shortcode', $settings);
		do_action('sf_create_shortcode_' . $this->token, $settings);
	}

	/**
	 * Create new css for a plugin/theme.
	 * 
	 * @param string $css
	 * @TODO Create method.
	 */
	public function createCSS($css) {
		
	}

	/* ---------------------------------------------------------------------- */
	/* Cleaning methods. */
	/* ---------------------------------------------------------------------- */

	/**
	 * Cleans up the options present in the database for our namespace. Remove 
	 * unused stuff and add in the default values for new stuff.
	 */
	public function cleanOptionsForDBListing() {
		// Get also a list of all option keys.
		$allOptionKeys = array();
		if (!empty($this->options[$this->token])) {
			$allOptionKeys = array_fill_keys(array_keys($this->options[$this->token]), null);
		}

		// Check whether options have changed / added.
		$changed = false;
		foreach ($this->adminPanels as $panel) {
			// Check existing options.
			foreach ($panel->options as $option) {
				if (empty($option->settings['id'])) {
					continue;
				}

				if (!isset($this->options[$this->token][$option->settings['id']])) {
					$this->options[$this->token][$option->settings['id']] = $option->settings['default'];
					$changed = true;
				}

				unset($allOptionKeys[$option->settings['id']]);

				// Clean the value for retrieval
				$this->options[$this->token][$option->settings['id']] = $option->cleanValueForGetting($this->options[$this->token][$option->settings['id']]);
			}

			// Check existing options.
			foreach ($panel->tabs as $tab) {
				foreach ($tab->options as $option) {
					if (empty($option->settings['id'])) {
						continue;
					}

					if (!isset($this->options[$this->token][$option->settings['id']])) {
						$this->options[$this->token][$option->settings['id']] = $option->settings['default'];
						$changed = true;
					}

					unset($allOptionKeys[$option->settings['id']]);

					// Clean the value for retrieval
					$this->options[$this->token][$option->settings['id']] = $option->cleanValueForGetting($this->options[$this->token][$option->settings['id']]);
				}
			}
		}

		// Remove all unused keys.
		if (count($allOptionKeys)) {
			foreach ($allOptionKeys as $optionName => $dummy) {
				unset($this->options[$this->token][$optionName]);
			}

			$changed = true;
		}

		// New options have been added, save the default values.
		if ($changed) {
			update_option($this->token . '_options', serialize($this->options[$this->token]));
		}
	}

	/**
	 * Cleans up the meta options in the database for our namespace. Remove 
	 * unused stuff and add in the default values for new stuff.
	 */
	public function cleanMetaForDbListing() {
		// Does nothing now...
	}

	/**
	 * Cleans up the theme mods in the database for our namespace. Remove unused 
	 * stuff and add in the default values for new stuff.
	 */
	public function cleanThemeForModListing() {
		$allThemeMods = get_theme_mods();

		// For fresh installs there won't be any theme mods yet.
		if ($allThemeMods === false) {
			$allThemeMods = array();
		}

		$allThemeModKeys = array_fill_keys(array_keys($allThemeMods), null);

		// Check existing theme mods.
		foreach ($this->customizerSections as $section) {
			foreach ($section->options as $option) {
				if (!isset($allThemeMods[$option->getID()])) {
					set_theme_mod($option->getID(), $option->settings['default']);
				}

				unset($allThemeModKeys[$option->getID()]);
			}
		}

		// Remove all unused theme mods
		if (count($allThemeModKeys)) {
			foreach ($allThemeModKeys as $optionName => $dummy) {
				// Only remove theme mods that the framework created
				if (stripos($optionName, $this->token . '_') === 0) {
					remove_theme_mod($optionName);
				}
			}
		}
	}

	/* ---------------------------------------------------------------------- */
	/* Misc methods. */
	/* ---------------------------------------------------------------------- */

	/**
	 * Acts the same way as plugins_url( 'script', __FILE__ ) but returns the 
	 * correct url when called from inside a theme.
	 * 
	 * @param string $script
	 * @param string $file
	 * @return string
	 */
	public static function getURL($script, $file) {
		$parentTheme = trailingslashit(get_template_directory());
		$childTheme = trailingslashit(get_stylesheet_directory());
		$plugin = trailingslashit(dirname($file));

		// Windows sometimes mixes up forward and back slashes, ensure forward 
		// slash for correct URL output.
		$parentTheme = str_replace('\\', '/', $parentTheme);
		$childTheme = str_replace('\\', '/', $childTheme);
		$file = str_replace('\\', '/', $file);

		// Whether the Framework is in a theme or child theme.
		if (stripos($file, $parentTheme) !== false) {
			// Framework is in a parent theme.
			$dir = trailingslashit(dirname(str_replace($parentTheme, '', $file)));
			if ($dir == './') {
				$dir = '';
			}

			return trailingslashit(get_template_directory_uri()) . $dir . $script;
		} else if (stripos($file, $childTheme) !== false) {
			// Framework is in a child theme
			$dir = trailingslashit(dirname(str_replace($childTheme, '', $file)));
			if ($dir == './') {
				$dir = '';
			}

			return trailingslashit(get_stylesheet_directory_uri()) . $dir . $script;
		}

		// Framework is a or in a plugin
		return plugins_url($script, $file);
	}

	public static function displayFrameworkError($message, $errorObject = null) {
		// Clean up the debug object for display. e.g. If this is a setting, we 
		// can have lots of blank values.
		if (is_array($errorObject)) {
			foreach ($errorObject as $key => $val) {
				if ($val === '') {
					unset($errorObject[$key]);
				}
			}
		}

		// Display an error message
		echo '<div style="margin: 20px"><strong>' . SF_NAME . ' Error</strong>';
		echo $message;
		if (!empty($errorObject)) {
			echo '<pre><code style="display: inline-block; padding: 10px">' . print_r($errorObject, true) . '</code></pre>';
		}
		echo '</div>';
	}

	/**
	 * Satanize the token.
	 * 
	 * @param string $token
	 * @return string
	 */
	public static function satanizeToken($token) {
		return str_replace(' ', '-', trim(strtolower($token)));
	}

}
