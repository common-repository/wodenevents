<?php
/*
    Plugin Name: WODEN Events
    Plugin URI: https://www.wodenevents.com
    Description: Wordpress integration for WodenEvents.com
    Version: 1.2.3
    Author: Woden LLC
    Text Domain: wodenevents
 */

namespace WodenEvents;

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WODEN_EVENTS_VERSION', '1.2.3' );


require plugin_dir_path( __FILE__ ) . 'includes/wodenevents.php';

function run_woden_events() {
	$plugin = new Includes\WodenEvents();
	$plugin->run();
}

run_woden_events();
