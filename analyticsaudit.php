<?php
/**
 * Main file of the plugin
 *
 * @package mashmetrics\analyticsaudit.
 *
 * @since 1.0
 */

/*
Plugin Name: MashMetrics Analytics Audit Tool
Description: Add a tool to audit google analytics settings of sites.
Author: MashMetrics
Version: 1.0
Author URI: http://mashmetrics.com
*/

namespace mashmetrics\analyticsaudit;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 403 );
}

const CLIENT_ID        = '538562510513-d99f5i1li5uc47vlb68llgmom3n48931.apps.googleusercontent.com';
const CLIENT_SECRET    = 'aenqu6W4e_VcEeix20JoreQB';
const SCOPE_ANALYTICS  = 'https://www.googleapis.com/auth/analytics.readonly';
const SCOPE_EMAIL      = 'https://www.googleapis.com/auth/userinfo.email';
const RETURN_TO_FIELD  = 'analyticsudit_return_to';
const ERROR_COOKIE     = 'analyticsudit_error';
const TOKEN_COOKIE     = 'analyticsudit_token';
const OAUTH2_PARAMETER = 'code';

/**
 * Enqueue styling and JS resource.
 *
 * @since 1.0
 */
function enqueue_resources() {

	// JS controlling the "form".
	wp_register_script( 'analyticsaudit-js', plugin_dir_url( __FILE__ ) . 'js/analyticsaudit.js', array('jquery'), '1.0' );
	wp_enqueue_script( 'analyticsaudit-js' );
}

/**
 * Implement the shortcode.
 *
 * @since 1.0
 *
 * @param array  $attr The attributes of the shortcode.
 * @param string $content The content enclosed by the shortcode.
 *
 * @return string The HTML of the "form" which drives the audit.
 */
function shortcode( $attr, $content ) {
	$start_text      = esc_html__( 'Start', 'analyticsaudit' );
	$action_url      = esc_url( site_url() );
	$return_to       = esc_url( get_permalink() );
	$return_to_field = RETURN_TO_FIELD;

	enqueue_resources();

	if ( isset( $_COOKIE[ TOKEN_COOKIE ] ) ) {
		// Get GA accounts and views from the user's account.
		$response = wp_remote_get( 'https://www.googleapis.com/analytics/v3/management/accountSummaries', array(
			'headers' => array( 'Authorization' => 'Bearer ' . $_COOKIE[ TOKEN_COOKIE ] ),
		) );
		setcookie( TOKEN_COOKIE, '', 0, COOKIEPATH, COOKIE_DOMAIN );
		if (! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			$data = json_decode( $response['body'] );
			if ( null !== $data ) {
				$user     = $data->username;
				$accounts = array();
				foreach ( $data->items as $item ) {
					$properties = array();
					foreach ( $item->webProperties as $property ) {
						$profiles = array();
						foreach ( $property->profiles as $profile ) {
							$profile    = array(
								'id'   => $profile->id,
								'name' => $profile->name,
							);
							$profiles[] = $profile;
						}
						$property     = array(
							'id'   => $property->id,
							'name' => $property->name,
							'url'  => $property->websiteUrl,
							'profiles' => $profiles,
						);
						$properties[] = $property;
					}
					$accounts[] = array(
						'id'         => $item->id,
						'name'       => $item->name,
						'properties' => $properties,
					);
				}

				if ( empty( $accounts ) ) {
					$ret = '<p>There are no Google Analytics accounts associated with this user ' . esc_html( $user ) . '</p>';
				} else {
					$ret = '';
					$ret .= '<p class="analytucsaudit_accounts">Account: <select id="analyticsaudit_account">';
					foreach ( $accounts as $account ) {
						$ret .= '<option value="' . esc_attr( $account['id'] ) . '">' . esc_html( $account['name'] ) . '</option>';
					}
					$ret .= '</select></p>';

					foreach ( $accounts as $account ) {
						$ret .= '<p class="analytucsaudit_properties" data-account=' . esc_attr( $account['id'] ) . '>Property: <select id="analyticsaudit_account">';
						foreach ( $account['properties'] as $property ) {
							$ret .= '<option value="' . esc_attr( $property['id'] ) . '">' . esc_html( $property['name'] ) . '</option>';
						}
						$ret .= '</select></p>';
					}

					foreach ( $accounts as $account ) {
						foreach ( $account['properties'] as $property ) {
							$ret .= '<p class="analytucsaudit_profiles" data-property=' . esc_attr( $account['id'] ) . '-' . esc_attr( $property['id'] ) . '>Profile: <select id="analyticsaudit_account">';
							foreach ( $property['profiles'] as $profile ) {
								$ret .= '<option value="' . esc_attr( $profile['id'] ) . '">' . esc_html( $profile['name'] ) . '</option>';
							}
							$ret .= '</select></p>';
						}
					}

					$ret  = '<div class="analytucsaudit_profile">' . $ret . '</div>';
					$ret .= '<div class="analytucsaudit_results"></div>';
				}
			}
		}
	} else {
		$ret = <<<EOT
<form class="analytucsaudit" action="$action_url" method="post">
	<input type="hidden" name="$return_to_field" value="$return_to">
	<div class="start-button">
		<button type="submit">$start_text</button>
	</div>
	<div class="results">
	</div>
</form>
EOT;
	}

	return $ret;
}

add_shortcode( 'analyticsaudit', __NAMESPACE__ . '\shortcode' );

/*
 * oAuth2 authentication, getting access permission and tokens.
 */

/**
 * Triggered by the WordPress init hook, used to check if the url being accessed
 * belongs to the oAuth2 authorization process and handle it correctly.
 *
 * @since 1.0
 */
function init() {
	if ( isset( $_POST[ RETURN_TO_FIELD ] ) ) {
		// sent from the form generated by the shortcode, start authorization.
		$url  = 'https://accounts.google.com/o/oauth2/v2/auth'; // google oauth handling URL.
		$url .= '?client_id=' . CLIENT_ID; // Add our client id.
		$url .= '&redirect_uri=' . rawurlencode( site_url() ); // google will redirect to there.
		$url .= '&scope=' . rawurlencode( SCOPE_ANALYTICS . ' ' . SCOPE_EMAIL ); // the permission scope we ask for.
		$url .= '&response_type=' . OAUTH2_PARAMETER; // The url parameter that google will use to pass values back to us.

		// Set a cookie to store where the user should be returned to after authorization.
		setcookie( RETURN_TO_FIELD, wp_unslash( $_POST[ RETURN_TO_FIELD ] ), 0, COOKIEPATH, COOKIE_DOMAIN );
		wp_redirect( $url );
		die();
	}

	if ( isset( $_GET[ OAUTH2_PARAMETER ] ) ) {
		// We are handling successful authorization. Get the token.
		$response = wp_safe_remote_post( 'https://www.googleapis.com/oauth2/v4/token', array(
			'body'    => array(
				'code'          => wp_unslash( $_GET[ OAUTH2_PARAMETER ] ),
				'client_id'     => CLIENT_ID,
				'client_secret' => CLIENT_SECRET,
				'redirect_uri'  => site_url(),
				'grant_type'    => 'authorization_code',
			),
			'headers' => array( 'Content-type' => 'application/x-www-form-urlencoded' ),
		) );

		if (! is_wp_error( $response ) && 200 == $response['response']['code'] ) {
			$json = json_decode( $response['body']);
			if ( NULL !== $json ) {
				$access_token = $json->access_token;
				setcookie( TOKEN_COOKIE, $access_token, 0, COOKIEPATH, COOKIE_DOMAIN );
				wp_redirect( wp_unslash( $_COOKIE[ RETURN_TO_FIELD ] ) );
				die();
			}
		}
	}

	// Check for authorization errors. since google uses geenric parameter name
	// make sure that the cookie is set before doing anything.
	if ( isset( $_GET['error'] ) && isset( $_COOKIE[ RETURN_TO_FIELD ] ) ) {
		secookie( ERROR_COOKIE, $_GET[ 'error' ] );
		wp_redirect( wp_unslash( $_COOKIE[ RETURN_TO_FIELD ] ) );
		die();
	}
}

add_action( 'init', __NAMESPACE__ . '\init' );

/*
 * Ajax handlers.
 */
