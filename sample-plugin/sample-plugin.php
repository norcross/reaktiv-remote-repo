<?php
/*
Plugin Name: Sample Plugin
Plugin URI: https://mysample.com
Description: Testing My Update
Version: 1.0.0
Author: Reaktiv Studios
Author URI:  http://andrewnorcross.com
Contributors: norcross
*/

DEFINE( 'RKV_UPDATE_URL', 'http://localhost/updater.loc/update/' );
DEFINE( 'RKV_ITEM', 'Sample Plugin' );
DEFINE( 'RKV_VERS', '1.0.0' );

if ( ! class_exists( 'RKV_Remote_Updater' ) )
	include( 'RKV_Remote_Updater.php' );


add_action ( 'admin_init', 'rkv_auto_updater' );

function rkv_auto_updater() {
	// Setup the updater
	$updater = new RKV_Remote_Updater( RKV_UPDATE_URL, __FILE__, array(
		'item'		=> RKV_ITEM,
		'version'   => RKV_VERS,
		)
	);

}