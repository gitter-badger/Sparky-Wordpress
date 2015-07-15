<?php

/**
 * Base class for a single option within Sparky Framework.
 *
 * @package     Sparky
 * @author      Sparky Dev Team
 * @copyright   Copyright (c) 2015 - 2015, Michael Beers | Online Media en Design
 * @since       Version 1.0.0
 */
abstract class Sparky_Option {

	const TYPE_ADMIN = 'admin_panel';
	const TYPE_META = 'meta_box';
	const TYPE_CUSTOMIZER = 'customizer_section';
	const TYPE_WIDGET = 'widget_area';

	private static $rowIndex = 0;
	private static $defaultSettings = array(
		'id' => '',
		'type' => 'text',
		'name' => '',
		'desc' => 'desc',
		'default' => '',
		'example' => '',
		'livepreview' => '',
		'hidden' => false
	);

}
