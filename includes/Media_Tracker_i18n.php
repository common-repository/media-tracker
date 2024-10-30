<?php

namespace Media_Tracker;

/**
 * Support language
 *
 * @since    1.0.0
 */
class Media_Tracker_i18n {

	/**
	 * Call language method
	 *
	 * @since	1.0.0
	 * @access	public
	 * @param	none
	 * @return	void
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_plugin_textdomain' ) );
	}

	/**
	 * Load language file from directory
	 *
	 * @since	1.0.0
	 * @access	public
	 * @param	none
	 * @return	void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'media-tracker', false, dirname( plugin_basename( _FILE_ ) ) . '/languages' );
	}
}
