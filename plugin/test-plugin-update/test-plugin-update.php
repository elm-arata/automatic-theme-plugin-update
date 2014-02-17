<?php
/*
Plugin Name: Test Plugin Update
Plugin URI: http://clark-technet.com
Description: Test plugin updates
Version: 0.9
Author: Jeremy Clark
Author URI: http://clark-technet.com
*/


/*
// TEMP: Enable update check on every request. Normally you don't need this! This is for testing only!
// NOTE: The
//	if (empty($checked_data->checked))
//		return $checked_data;
// lines will need to be commented in the check_for_plugin_update function as well.

set_site_transient('update_plugins', null);

// TEMP: Show which variables are being requested when query plugin API
add_filter('plugins_api_result', 'aaa_result', 10, 3);
function aaa_result($res, $action, $args) {
	print_r($res);
	return $res;
}
// NOTE: All variables and functions will need to be prefixed properly to allow multiple plugins to be updated
*/


class ATPU_Plugin {
	private $api_url = '';
	private $plugin_slug = '';

	public function __construct( $api_url = '', $plugin_slug = '' ) {
		if ( !$api_url ) {
			die( 'Please set $api_url.' );
		}
		$this->api_url = esc_url( $api_url );

		if ( !$plugin_slug ) {
			die( 'Please set $plugin_slug.' );
		}
		$this->plugin_slug = $plugin_slug;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );

		if ( is_admin() ) {
			$current = get_transient( 'update_themes' );
		}
	}

	public function check_for_plugin_update( $checked_data ) {
		global $wp_version;

		if ( empty( $checked_data->checked ) )
			return $checked_data;

		$args = array(
			'slug' => $this->plugin_slug,
			'version' => $checked_data->checked[$this->plugin_slug . '/' . $this->plugin_slug . '.php'],
		);
		$request_string = array(
			'body' => array(
				'action' => 'basic_check',
				'request' => serialize( $args ),
				'api-key' => md5( home_url() ),
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		);
		$raw_response = wp_remote_post( $this->api_url, $request_string );
		if ( !is_wp_error( $raw_response ) && $raw_response['response']['code'] == 200 ) {
			$response = unserialize( $raw_response['body'] );
		}

		if ( isset( $response ) && is_object( $response ) && !empty( $response ) ) {
			$checked_data->response[$this->plugin_slug . '/' . $this->plugin_slug . '.php'] = $response;
		}

		return $checked_data;
	}

	public function plugin_api_call( $def, $action, $args ) {
		global $wp_version;
		if ( !isset( $args->slug ) || $args->slug != $this->plugin_slug )
			return false;

		$plugin_info = get_site_transient( 'update_plugins' );
		$current_version = $plugin_info->checked[$this->plugin_slug . '/' . $this->plugin_slug . '.php'];
		$args->version = $current_version;

		$request_string = array(
			'body' => array(
				'action' => $action,
				'request' => serialize( $args ),
				'api-key' => md5( home_url() ),
			),
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		);
		$request = wp_remote_post( $this->api_url, $request_string );

		if ( is_wp_error( $request ) ) {
			$error_message = __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' );
			$res = new WP_Error( 'plugins_api_failed', $error_message, $request->get_error_message() );
		} else {
			$res = unserialize( $request['body'] );
			if ( $res === false ) {
				$res = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $request['body'] );
			}
		}

		return $res;
	}
}
$ATPU_Plugin = new ATPU_Plugin( 'http://wordpress.local/api/', 'ypur-plugin-slug' );


