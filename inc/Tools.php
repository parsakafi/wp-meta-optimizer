<?php

namespace WPMetaOptimizer;

class Tools {
	public static $instance = null;
	protected $Options;

	function __construct() {
		$this->Options = Options::getInstance();
		add_filter( 'wp_dashboard_setup', [ $this, 'disableQuickDraftDashboardWidget' ] );
	}

	function disableQuickDraftDashboardWidget() {
		global $wp_meta_boxes;
		if ( $this->Options->getOption( 'disable_quick_draft_widget', false ) == 1 )
			unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] );
	}

	/**
	 * Returns an instance of class
	 *
	 * @return Tools
	 */
	static function getInstance() {
		if ( self::$instance == null )
			self::$instance = new Tools();

		return self::$instance;
	}
}