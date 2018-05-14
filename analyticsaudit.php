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

const DB_VERSION       = '1.3';
const DB_TABLE         = 'analyticsaudit';
const CLIENT_ID        = '538562510513-d99f5i1li5uc47vlb68llgmom3n48931.apps.googleusercontent.com';
const CLIENT_SECRET    = 'aenqu6W4e_VcEeix20JoreQB';
const SCOPE_ANALYTICS  = 'https://www.googleapis.com/auth/analytics.readonly';
const SCOPE_EMAIL      = 'https://www.googleapis.com/auth/userinfo.email';
const RETURN_TO_FIELD  = 'analyticsudit_return_to';
const RESET_FIELD      = 'analyticsudit_reset';
const ERROR_COOKIE     = 'analyticsudit_error';
const TOKEN_COOKIE     = 'analyticsudit_token';
const OAUTH2_PARAMETER = 'code';
const API_URL          = 'https://analytics-audit.herokuapp.com/api/';

/**
 * Enqueue styling and JS resource.
 *
 * @since 1.0
 */
function enqueue_resources() {

	// JS controlling the "form".
	wp_register_script( 'analyticsaudit-js', plugin_dir_url( __FILE__ ) . 'js/analyticsaudit.js', array( 'jquery' ), '1.0' );
	wp_enqueue_script( 'analyticsaudit-js' );
	wp_localize_script( 'analyticsaudit-js', 'analyticsaudit_vars', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
	) );
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
	$start_text      = esc_html__( 'Get Started', 'analyticsaudit' );
	$fetch_text      = esc_html__( 'Check Health', 'analyticsaudit' );
	$reset_text      = esc_html__( 'Retry with another account', 'analyticsaudit' );
	$action_url      = esc_url( site_url() );
	$return_to       = esc_url( get_permalink() );
	$return_to_field = RETURN_TO_FIELD;
	$reset_field     = RESET_FIELD;

	enqueue_resources();

	$form = <<<EOT
<form class="analytucsaudit" action="$action_url" method="post">
	<input type="hidden" name="$return_to_field" value="$return_to">
	<div class="start-button">
		<button type="submit">$start_text</button>
	</div>
</form>
EOT;

	$retry_form = <<<EOT
<form action="$action_url" method="post">
	<input type="hidden" name="$return_to_field" value="$return_to">
	<input type="hidden" name="$reset_field" value="1">
	<button type="submit">$reset_text</button>
</form>
EOT;

	$checkboxes = <<<EOT
<div><input type="checkbox" id="analytucsaudit_tableau"><label for="analytucsaudit_tableau">Tableau</label></div>
<div><input type="checkbox" id="analytucsaudit_datastudio"><label for="analytucsaudit_datastudio">Data Studio</label></div>
<div><input type="checkbox" id="analytucsaudit_bigquery"><label for="analytucsaudit_bigquery">Big Query</label></div>
<div><input type="checkbox" id="analytucsaudit_unsue"><label for="analytucsaudit_unsure">Unsure</label></div>
<div><input type="checkbox" id="analytucsaudit_gtm"><label for="analytucsaudit_gtm">Google Tag Manager</label></div>
EOT;

	$websitetype_checkboxes = <<<EOT
<div><input type="checkbox" id="analytucsaudit_ecom"><label for="analytucsaudit_ecom">Buy Something</label></div>
<div><input type="checkbox" id="analytucsaudit_lead"><label for="analytucsaudit_lead">Fill out a Form</label></div>
<div><input type="checkbox" id="analytucsaudit_publisher"><label for="analytucsaudit_publisher">Read my Blog</label></div>
EOT;

	$user     = '';
	$api_fail = false;
	if ( isset( $_COOKIE[ TOKEN_COOKIE ] ) ) {
		$email = '';
		// Get user email.
		$response = wp_remote_get( 'https://www.googleapis.com/plus/v1/people/me', array(
			'headers' => array( 'Authorization' => 'Bearer ' . wp_unslash( $_COOKIE[ TOKEN_COOKIE ] ) ),
		) );
		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$data  = json_decode( $response['body'] );
			$email = $data->emails[0]->value;
		} else {
			$api_fail = true;
		}

		// Get GA accounts and views from the user's account.
		// This needs to be a loop as the number of items in a response is limited to 1000,
		// and there are accounts with more than that.
		$start_index = 1;
		$accounts    = array();
		while ( ! $api_fail ) { // to exit code need to break, or an api faulue happened.
			$response = wp_remote_get( 'https://www.googleapis.com/analytics/v3/management/accountSummaries?start-index=' . $start_index .'&max-results=1000', array(
				'headers' => array( 'Authorization' => 'Bearer ' . wp_unslash( $_COOKIE[ TOKEN_COOKIE ] ) ),
			) );
			if ( is_wp_error( $response ) || 200 !== $response['response']['code'] ) {
				$api_fail = true;
				break;
			}
			$data = json_decode( $response['body'] );

			if ( null !== $data ) {
				$user = $data->username;
				foreach ( $data->items as $item ) {
					$properties = array();
					foreach ( $item->webProperties as $property ) {
						if ( ! isset( $property->profiles ) ) {
							// If the property has no profiles, it is of no interest.
							break;
						}
						$profiles = array();
						foreach ( $property->profiles as $profile ) {
							$inner_profile = array(
								'id'   => $profile->id,
								'name' => $profile->name,
							);

							$profiles[ $profile->id ] = $inner_profile;
						}
						if ( ! isset( $property->websiteUrl ) ) {
							// If the property has no URL, it is probably not fully configured and therefor of no interest.
							break;
						}

						if ( 0 < count( $profiles ) ) {
							// Skip showing properties with no profiles.
							$inner_property = array(
								'id'       => $property->id,
								'name'     => $property->name,
								'url'      => str_replace( array( 'http://', 'https://' ), '', $property->websiteUrl ),
								'profiles' => $profiles,
							);

							$properties[ $property->id ] = $inner_property;
						}
					}
					if ( 0 < count( $properties ) ) {
						// Skip showing accounts with no useful properties.
						$accounts[ $item->id ] = array(
							'id'         => $item->id,
							'name'       => $item->name,
							'properties' => $properties,
						);
					}
				}
				$start_index += count( $data->items );
				if ( $start_index >= $data->totalResults ) {
					// process everything, time to bail.
					break;
				}
			} else {
				// some error, bail.
				break;
			}
		}

		if ( ! $api_fail ) {
			if ( empty( $accounts ) ) {
				$ret = '<p>There are no Google Analytics accounts associated with this user ' . esc_html( $user ) . ', please retry</p>';
				$ret .= '<div class="analytucsaudit-buttons">' . $retry_form . '</div>';
			} else {
				$ret = '';

				// Add styling.
				$ret .= '<style>';
				ob_start();
				include __DIR__ . '/css/analyticsaudit.css';
				$ret .= ob_get_contents();
				ob_end_clean();

				$ret .= '</style>';
				$ret .= '<div class="analytucsaudit_profile">';
				$ret .= '<p class="analytucsaudit_accounts"><label for="analyticsaudit_account">Google Account</label><select id="analyticsaudit_account">';
				foreach ( $accounts as $account ) {
					$ret .= '<option value="' . esc_attr( $account['id'] ) . '">' . esc_html( $account['name'] ) . '</option>';
				}
				$ret .= '</select></p>';

				foreach ( $accounts as $account ) {
					$ret .= '<p class="analytucsaudit_properties" data-account=' . esc_attr( $account['id'] ) . '><label for="analyticsaudit_property-' . esc_attr( $account['id'] ) . '">Property (Website)</label><select id="analyticsaudit_property-' . esc_attr( $account['id'] ) . '">';
					foreach ( $account['properties'] as $property ) {
						$ret .= '<option data-url="' . esc_attr( $property['url'] ) . '" value="' . esc_attr( $property['id'] ) . '">' . esc_html( $property['name'] ) . '</option>';
					}
					$ret .= '</select></p>';
				}

				$ret .= '<div  class="analytucsaudit_profiles">';
				foreach ( $accounts as $account ) {
					foreach ( $account['properties'] as $property ) {
						$property_id = esc_attr( $account['id'] ) . '-' . esc_attr( $property['id'] );

						$ret .= '<p data-property="' . $property_id . '"><label for="aa_profile-' . $property_id . '">View</label><select id="aa_profile-' . $property_id . '">';
						foreach ( $property['profiles'] as $profile ) {
							$ret .= '<option value="' . esc_attr( $profile['id'] ) . '">' . esc_html( $profile['name'] ) . '</option>';
						}
						$ret .= '</select></p>';
					}
				}
				$ret .= '</div>';
				$ret .= '<div id="analytucsaudit_email" data-email="' . esc_attr( $email ) . '"></div>';
				$ret .= '</div>';
				$ret .= '<div class="analytucsaudit_sitetype_checkboxes">';
				$ret .= '<p>What do you want people to do on your website? (Check all that Apply)</p>';
				$ret .= $websitetype_checkboxes . '<div class="sf"></div></div>';
				$ret .= '<div class="analytucsaudit_checkboxes">';
				$ret .= '<p>Other Data Tools Used (check all that apply)</p>';
				$ret .= $checkboxes . '</div>';
				$ret .= '<div class="analytucsaudit-buttons"><button class="fetch-button" type="button">' . $fetch_text . '</button>' . $retry_form . '</div>';
				$ret .= '<div id="analytucsaudit_message"><img src="' . plugins_url( 'img/loading.gif', __FILE__ ) . '" class="loading-icon">Running the tests...</div>';
				$ret .= '<div id="analytucsaudit_results" style="display:none">';

				$options = get_option( 'analyticsauditsettings' );

				$header = array(
					'accurate '   => 'accurate_header',
					'actionable ' => 'actionable_header',
					'accessible ' => 'accessiable_header',
				);

				// Data points HTML.
				$ret .= '<h2 class="text-center">Audit Results</h2>';
				$ret .= '<div id="indicator-box">';
				$ret .= '<div class="indicator-value">Total Sessions:<span id="analytucsaudit_datapoint_total_sessions"></span></div>';
				$ret .= '<div class="indicator-value">Bounce Rate:<span id="analytucsaudit_datapoint_bounce_rate"></span></div>';
				$ret .= '<div class="indicator-value">Top Hostname:<span id="analytucsaudit_datapoint_top_hostname"></span></div>';
				$ret .= '</div>';
				//$ret .= '<div>Traffic Majority:</div>';
				//$ret .= '<div>Channel:<span id="analytucsaudit_datapoint_channel"></span></div>';
				//$ret .= '<div>Sessions:<span id="analytucsaudit_datapoint_sessions"></span></div>';
				//$ret .= '<div>Percentage:<span id="analytucsaudit_datapoint_percentage"></span></div>';

				// header blurb.
				
				foreach ( $header as $id => $prefix ) {
					$ret .= '<div class="analytucsaudit_tests_header">';
					$ret .= '<div class="analytucsaudit_test_description" id="analytucsaudit_test_description_' . esc_attr( $id ) . '">';
					$ret .= '<h4>' . esc_html( $options[ $prefix . '_title' ] ) . '</h4>';
					$ret .= '<div>' . wpautop( wptexturize( $options[ $prefix . '_text' ] ) ) . '</div>';
					$ret .= '</div>';
					$ret .= '</div>';
				}
				$ret .= '<div style="clear:both"></div>';
				

				// Tests status.
				$tests = array(
					'accurate'   => array(
						'gtm'                 => 'gtm',
						'setup_correct'       => 'setup_issues',
						'filltering_spam'     => 'spam',
						'raw_or_testing_view' => 'testing_view',
					),
					'actionable' => array(
						'goals_set_up'       => 'goals',
						'demographic_data'   => 'tracking_demographic',
						'events'             => 'tracking_events',
						'enhanced_ecommerce' => 'enhanced_ecommerce',
						'goal_value'         => 'measuring_goal_values',
					),
					'accessible' => array(
						'adwords_linked' => 'adwords_linked',
						'channel_groups' => 'channel_groups',
						'content_groups' => 'content_groups',
						'tools'          => 'tools',
					),
				);
				foreach ( $tests as $type => $typetests ) {
					$ret .= '<div class="analytucsaudit_test_type" id="analytucsaudit_test_type-' . esc_attr( $type ) . '">';
					foreach ( $typetests as $id => $prefix ) {
						$ret .= '<div class="analytucsaudit_test" id="analytucsaudit_test_' . esc_attr( $id ) . '">';
						$ret .= '<h4>' . esc_html( $options[ $prefix . '_title' ] ) . '</h4>';
						$ret .= '<div>' . wpautop( wptexturize( $options[ $prefix . '_text' ] ) ) . '</div>';
						$ret .= '</div>';
					}
					$ret .= '</div>';
				}
				$ret .= '<div style="clear:both"></div>';

				$ret .= '</div>';
			}
		} else {
			$ret = $form;
		}
	} else {
		$ret = $form;
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
	if ( isset( $_POST[ RETURN_TO_FIELD ] ) || isset( $_POST[ RESET_FIELD ] ) ) {
		// sent from the form generated by the shortcode, start authorization.
		$url  = 'https://accounts.google.com/o/oauth2/v2/auth'; // google oauth handling URL.
		$url .= '?client_id=' . CLIENT_ID; // Add our client id.
		$url .= '&redirect_uri=' . rawurlencode( site_url() ); // google will redirect to there.
		$url .= '&scope=' . rawurlencode( SCOPE_ANALYTICS . ' ' . SCOPE_EMAIL ); // the permission scope we ask for.
		$url .= '&response_type=' . OAUTH2_PARAMETER; // The url parameter that google will use to pass values back to us.

		// Set a cookie to store where the user should be returned to after authorization.
		setcookie( RETURN_TO_FIELD, wp_unslash( $_POST[ RETURN_TO_FIELD ] ), 0, COOKIEPATH, COOKIE_DOMAIN );
		if ( isset( $_POST[ RESET_FIELD ] ) && isset( $_COOKIE[ TOKEN_COOKIE ] ) ) {
			// Need to revoke old token.
			wp_remote_get( 'https://accounts.google.com/o/oauth2/revoke?token=' . $_COOKIE[ TOKEN_COOKIE ] );
			setcookie( TOKEN_COOKIE, '', 0, COOKIEPATH, COOKIE_DOMAIN );
		}
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

		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$json = json_decode( $response['body'] );
			if ( null !== $json ) {
				$access_token = $json->access_token;
				setcookie( TOKEN_COOKIE, $access_token, 0, COOKIEPATH, COOKIE_DOMAIN );
				wp_redirect( wp_unslash( $_COOKIE[ RETURN_TO_FIELD ] ) );
				die();
			}
		}

		echo '<p>could not get the token<br>Please report the following data</p>';
		var_dump( $response );
		die();
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

/**
 * Utility function to create the parammeters that needs to be appended to
 * the URLs of the API end points.
 *
 * @since 1.0
 *
 * return string
 */
function query_vars() {
	$ret  = '?view_id=' . rawurlencode( wp_unslash( $_POST['profile'] ) );
	$ret .= '&token=' . rawurlencode( wp_unslash( $_COOKIE[ TOKEN_COOKIE ] ) );
	$ret .= '&domain=' . rawurlencode( wp_unslash( $_POST['domain'] ) );
	$ret .= '&property=' . rawurlencode( wp_unslash( $_POST['property'] ) );
	return $ret;
}

/**
 * Handler for the actionable ajax request. Sends the relevant request to the API
 * server and output the response as part of the data if the request was successful,
 * or an error indication.
 *
 * @since 1.0
 */
function actionable() {
	$response = wp_remote_get( API_URL . 'actionable' . query_vars(), array( 'timeout' => 28 ) );
	if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
		wp_send_json_success( $response['body'] );
	} else {
		wp_send_json_error( $response );
	}
	die();
}

add_action( 'wp_ajax_analyticsaudit_actionable', __NAMESPACE__ . '\actionable' );
add_action( 'wp_ajax_nopriv_analyticsaudit_actionable', __NAMESPACE__ . '\actionable' );

/**
 * Handler for the accessable ajax request. Sends the relevant request to the API
 * server and output the response as part of the data if the request was successful,
 * or an error indication.
 *
 * @since 1.0
 */
function accessable() {
	$response = wp_remote_get( API_URL . 'accessible' . query_vars(), array( 'timeout' => 28 ) );
	if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
		wp_send_json_success( $response['body'] );
	} else {
		wp_send_json_error( $response );
	}
	die();
}

add_action( 'wp_ajax_analyticsaudit_accessable', __NAMESPACE__ . '\accessable' );
add_action( 'wp_ajax_nopriv_analyticsaudit_accessable', __NAMESPACE__ . '\accessable' );

/**
 * Handler for the accurate ajax request. Sends the relevant request to the API
 * server and output the response as part of the data if the request was successful,
 * or an error indication.
 *
 * @since 1.0
 */
function accurate() {
	$response = wp_remote_get( API_URL . 'accurate' . query_vars(), array( 'timeout' => 28 ) );
	if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
		wp_send_json_success( $response['body'] );
	} else {
		wp_send_json_error( $response );
	}
	die();
}

add_action( 'wp_ajax_analyticsaudit_accurate', __NAMESPACE__ . '\accurate' );
add_action( 'wp_ajax_nopriv_analyticsaudit_accurate', __NAMESPACE__ . '\accurate' );

/**
 * Handle data pull ajax requests. Send the relevant request to the API
 * server and output the response as part of the data if the request was successful,
 * or an error indication.
 *
 * @since 1.0
 */
function data_pull() {
	$response = wp_remote_get( API_URL . 'other_data_pulls' . query_vars(), array( 'timeout' => 28 ) );
	if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
		wp_send_json_success( $response['body'] );
	} else {
		wp_send_json_error( $response );
	}
	die();
}

add_action( 'wp_ajax_analyticsaudit_data_pull', __NAMESPACE__ . '\data_pull' );
add_action( 'wp_ajax_nopriv_analyticsaudit_data_pull', __NAMESPACE__ . '\data_pull' );

/*
 * Settings related
 */

add_action( 'admin_menu', __NAMESPACE__ . '\admin_menu' );
add_action( 'admin_init', __NAMESPACE__ . '\settings_init' );

/**
 * Set the setting page in the menu.
 *
 * @since 1.0
 */
function admin_menu() {
	add_options_page( 'Analytics Audit Settings', 'Analytics Audit', 'manage_options', 'analyticsauditsettings', __NAMESPACE__ . '\options_page' );
	add_management_page( 'Export Analytics Audit', 'Export Analytics Audit', 'manage_options', 'analyticsauditexport', __NAMESPACE__ . '\export_page' );
}

/**
 * Utility function to add sections of title and text for tests to be used in the settings page.
 *
 * @since 1.0
 *
 * @param string $id     The id to be used for the section.
 * @param string $title  The title of the section.
 * @param string $prefix The prefix to be used whenaccessing the title and text
 *                       in the option.
 */
function add_section( $id, $title, $prefix ) {

	add_settings_section(
		$id,
		$title,
		'',
		'analyticsauditsettings'
	);

	add_settings_field(
		'analyticsauditsettings_gtm_title',
		__( 'Title', 'analyticsaudit' ),
		__NAMESPACE__ . '\title_setting',
		'analyticsauditsettings',
		$id,
		array( 'section' => $prefix )
	);

	add_settings_field(
		'analyticsauditsettings_gtm_text',
		__( 'Text', 'analyticsaudit' ),
		__NAMESPACE__ . '\text_setting',
		'analyticsauditsettings',
		$id,
		array( 'section' => $prefix )
	);

}

/**
 * Register the option, sections and field to be used in the settings page.
 *
 * @since 1.0
 */
function settings_init() {
	register_setting( 'analyticsauditsettings', 'analyticsauditsettings' );

	add_section( 'analyticsauditsettings_accessiable_header_section', __( 'Accessable Header', 'analyticsaudit' ), 'accessiable_header' );
	add_section( 'analyticsauditsettings_accurate_header_section', __( 'Accurate Header', 'analyticsaudit' ), 'accurate_header' );
	add_section( 'analyticsauditsettings_actionable_header_section', __( 'Actionable Header', 'analyticsaudit' ), 'actionable_header' );
	add_section( 'analyticsauditsettings_gtm_section', __( 'Google Tag Mananger usage', 'analyticsaudit' ), 'gtm' );
	add_section( 'analyticsauditsettings_setup_issues_section', __( 'Potential Setup Issues', 'analyticsaudit' ), 'setup_issues' );
	add_section( 'analyticsauditsettings_spam_section', __( 'Spam', 'analyticsaudit' ), 'spam' );
	add_section( 'analyticsauditsettings_testing_view_section', __( 'Raw or Testing View', 'analyticsaudit' ), 'testing_view' );
	add_section( 'analyticsauditsettings_goals_section', __( 'Goals', 'analyticsaudit' ), 'goals' );
	add_section( 'analyticsauditsettings_tracking_demographic_section', __( 'Tracking Demographic', 'analyticsaudit' ), 'tracking_demographic' );
	add_section( 'analyticsauditsettings_tracking_events_section', __( 'Tracking Events', 'analyticsaudit' ), 'tracking_events' );
	add_section( 'analyticsauditsettings_enhanced_ecommerce_section', __( 'Enhanced Ecommerce', 'analyticsaudit' ), 'enhanced_ecommerce' );
	add_section( 'analyticsauditsettings_measuring_goal_values_section', __( 'Measuring Goal Values', 'analyticsaudit' ), 'measuring_goal_values' );
	add_section( 'analyticsauditsettings_adwords_linked_section', __( 'AdWords Linked', 'analyticsaudit' ), 'adwords_linked' );
	add_section( 'analyticsauditsettings_channel_groups_section', __( 'Channel Groups', 'analyticsaudit' ), 'channel_groups' );
	add_section( 'analyticsauditsettings_content_groups_section', __( 'Content Groups', 'analyticsaudit' ), 'content_groups' );
	add_section( 'analyticsauditsettings_tools_section', __( 'Dashboard Tools', 'analyticsaudit' ), 'tools' );

	// Add email notificationsection.
	add_settings_section(
		'analyticsauditsettings_email_section',
		'Email notification for completed test',
		'',
		'analyticsauditsettings'
	);

	add_settings_field(
		'analyticsauditsettings_email_section',
		__( 'Address', 'analyticsaudit' ),
		__NAMESPACE__ . '\email_address',
		'analyticsauditsettings',
		'analyticsauditsettings_email_section'
	);

	add_settings_field(
		'analyticsauditsettings_tools_section_email_subject',
		__( 'Subject', 'analyticsaudit' ),
		__NAMESPACE__ . '\email_subject',
		'analyticsauditsettings',
		'analyticsauditsettings_email_section'
	);

}

/**
 * Emit the input element for the title part of the test description.
 *
 * @since 1.0
 *
 * @param array $args Include the prefix of the relevant option in the "section" element.
 */
function title_setting( $args ) {
	$options = get_option( 'analyticsauditsettings', array() );
	$value   = '';
	$index   = $args['section'] . '_title';
	if ( isset( $options[ $index ] ) ) {
		$value = $options[ $index ];
	}

	echo '<input name="analyticsauditsettings[' . esc_attr( $index ) . ']" value="' . esc_attr( $value ) . '">';
}

/**
 * Emit the editor element for the text part of the test description.
 *
 * @since 1.0
 *
 * @param array $args Include the prefix of the relevant option in the "section" element.
 */
function text_setting( $args ) {
	$options = get_option( 'analyticsauditsettings', array() );
	$index   = $args['section'] . '_text';
	$content = isset( $options[ $index ] ) ? $options[ $index ] : '';
	wp_editor( $content, 'analyticsauditsettings-' . $index, array(
		'textarea_name' => 'analyticsauditsettings[' . $index . ']',
		'media_buttons' => false,
		'textarea_rows' => 2,
		'teeny'         => true,
	) );
}

/**
 * Emit the input element for the email address for notifications.
 *
 * @since 1.0
 */
function email_address() {
	$options = get_option( 'analyticsauditsettings', array() );
	$value   = '';
	if ( isset( $options['email_address'] ) ) {
		$value = $options['email_address'];
	}

	echo '<input name="analyticsauditsettings[email_address]" value="' . esc_attr( $value ) . '">';
}

/**
 * Emit the input element for the email address for notifications.
 *
 * @since 1.0
 */
function email_subject() {
	$options = get_option( 'analyticsauditsettings', array() );
	$value   = '';
	if ( isset( $options['email_subject'] ) ) {
		$value = $options['email_subject'];
	}

	echo '<input name="analyticsauditsettings[email_subject]" value="' . esc_attr( $value ) . '">';
}

/**
 *  Convert the internal value of a test result to a human readable text.
 *
 *  @since 1.0
 *
 *  @param string $test The internal name of a test.
 *  @param mixed  $value  The internal value of a test.
 *
 *  @return string The human readable value of the test
 */
function test_result_to_text( $test, $value ) {
	$test_text_conversion = array(
		'email'                    => 'none',
		'website'                  => 'none',
		'gtm'                      => 'onoff',
		'tableau'                  => 'onoff',
		'bigquery'                 => 'onoff',
		'datastudio'               => 'onoff',
		'unsure'                   => 'onoff',
		'ecom'                     => 'onoff',
		'lead'                     => 'onoff',
		'publisher'                => 'onoff',
		'test_setup'               => 'passfail',
		'test_spam'                => 'passfail',
		'test_raw'                 => 'passfail',
		'test_demographic'         => 'passfail',
		'test_ecom'                => 'passfail',
		'test_events'              => 'passfail',
		'test_goals'               => 'passfail',
		'test_goal_value'          => 'passfail',
		'test_adwords'             => 'passfail',
		'test_channelgroups'       => 'passfail',
		'test_contentgroups'       => 'passfail',
		'test_sessions'            => 'none',
		'test_bouncerate'          => 'none',
		'test_tophost'             => 'none',
		'test_majority_channel'    => 'none',
		'test_majority_session'    => 'none',
		'test_majority_percentage' => 'none',
	);

	if ( isset( $test_text_conversion[ $test ] ) ) {
		switch ( $test_text_conversion[ $test ] ) {
			case 'onoff':
				$value = ( $value ) ? 'On' : 'Off';
				break;
			case 'passfail':
				$value = ( $value ) ? 'Pass' : 'Fail';
				break;
		}
	}

	return $value;
}

/**
 * Output the settings page.
 *
 * @since 1.0
 */
function options_page() {
	?>
	<div class="wrap">
		<h2>Analytics Audit Settings</h2>
		<form method="post" action="options.php">
	<?php
		settings_fields( 'analyticsauditsettings' );
		do_settings_sections( 'analyticsauditsettings' );
		submit_button();
		echo '</form>';
		echo '</div>';
}

/**
 *  Storing data in the DB and exporting it.
 */

/**
 *  Create the table in the DB.
 */
function create_table() {
	global $wpdb;

	$options = get_option( 'analyticsauditsettings', array() );

	// check if we need to update the table structure.
	if ( ! isset( $options['db_version'] ) || version_compare( $options['db_version'], DB_VERSION ) < 0 ) {

		$table_name = $wpdb->prefix . DB_TABLE;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
		  id MEDIUMINT NOT NULL AUTO_INCREMENT,
		  time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		  email VARCHAR(255) NOT NULL,
		  website VARCHAR(255) NOT NULL,
		  gtm BOOLEAN NOT NULL DEFAULT FALSE,
		  tableau BOOLEAN NOT NULL DEFAULT FALSE,
		  bigquery BOOLEAN NOT NULL DEFAULT FALSE,
		  datastudio BOOLEAN NOT NULL DEFAULT FALSE,
		  unsure BOOLEAN NOT NULL DEFAULT FALSE,
		  ecom BOOLEAN NOT NULL DEFAULT FALSE,
		  lead BOOLEAN NOT NULL DEFAULT FALSE,
		  publisher BOOLEAN NOT NULL DEFAULT FALSE,
		  test_setup BOOLEAN NOT NULL DEFAULT FALSE,
		  test_spam BOOLEAN NOT NULL DEFAULT FALSE,
		  test_raw BOOLEAN NOT NULL DEFAULT FALSE,
		  test_demographic BOOLEAN NOT NULL DEFAULT FALSE,
		  test_ecom BOOLEAN NOT NULL DEFAULT FALSE,
		  test_events BOOLEAN NOT NULL DEFAULT FALSE,
		  test_goals BOOLEAN NOT NULL DEFAULT FALSE,
		  test_goal_value BOOLEAN NOT NULL DEFAULT FALSE,
		  test_adwords BOOLEAN NOT NULL DEFAULT FALSE,
		  test_channelgroups BOOLEAN NOT NULL DEFAULT FALSE,
		  test_contentgroups BOOLEAN NOT NULL DEFAULT FALSE,
		  test_sessions INT UNSIGNED NOT NULL,
		  test_bouncerate DECIMAL(5,2) NOT NULL,
		  test_tophost VARCHAR(255) NOT NULL,
		  test_majority_channel VARCHAR(255) NOT NULL,
		  test_majority_session INT UNSIGNED NOT NULL,
		  test_majority_percentage DECIMAL(5,2) NOT NULL,
		  PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

/**
 * Handler to store results in the DB.
 *
 * @since 1.0
 */
function save() {

	global $wpdb;

	$data = array();

	$fields = array(
		'email'                    => 'string',
		'website'                  => 'string',
		'gtm'                      => 'bool',
		'tableau'                  => 'bool',
		'bigquery'                 => 'bool',
		'datastudio'               => 'bool',
		'unsure'                   => 'bool',
		'ecom'                     => 'bool',
		'lead'                     => 'bool',
		'publisher'                => 'bool',
		'test_setup'               => 'bool',
		'test_spam'                => 'bool',
		'test_raw'                 => 'bool',
		'test_demographic'         => 'bool',
		'test_ecom'                => 'bool',
		'test_events'              => 'bool',
		'test_goals'               => 'bool',
		'test_goal_value'          => 'bool',
		'test_adwords'             => 'bool',
		'test_channelgroups'       => 'bool',
		'test_contentgroups'       => 'bool',
		'test_sessions'            => 'int',
		'test_bouncerate'          => 'percentage',
		'test_tophost'             => 'string',
		'test_majority_channel'    => 'string',
		'test_majority_session'    => 'int',
		'test_majority_percentage' => 'percentage',
	);

	$test_text = array(
		'email'                    => 'Email',
		'website'                  => 'Website',
		'gtm'                      => 'GTM Checkbox',
		'tableau'                  => 'Tableau Checkbox',
		'bigquery'                 => 'BigQuery Checkbox',
		'datastudio'               => 'DataStudio Checkbox',
		'unsure'                   => 'Unsure Checkbox',
		'ecom'                     => 'Ecommerce Checkbox',
		'lead'                     => 'Lead Generation Checkbox',
		'publisher'                => 'Publisher Checkbox',
		'test_setup'               => 'Setup Test',
		'test_spam'                => 'Spam Test',
		'test_raw'                 => 'Raw Test',
		'test_demographic'         => 'Demographoc Test',
		'test_ecom'                => 'Ecommerce Test',
		'test_events'              => 'Events Test',
		'test_goals'               => 'Goals Setup Test',
		'test_goal_value'          => 'Goal Value Test',
		'test_adwords'             => 'Adwords Linked Test',
		'test_channelgroups'       => 'Channel Groups Test',
		'test_contentgroups'       => 'Content Groups Test',
		'test_sessions'            => 'Number of Sessions',
		'test_bouncerate'          => 'Bouncerate',
		'test_tophost'             => 'Top Host',
		'test_majority_channel'    => 'Name of Majority Channel',
		'test_majority_session'    => 'Number of Majority Sessions',
		'test_majority_percentage' => 'Percentage of Majority Sessions',
	);

	create_table();

	foreach ( $fields as $field => $type ) {
		$value = wp_unslash( $_POST[ $field ] );
		switch ( $type ) {
			case 'bool':
				$value = ( 'false' === $value ) ? 0 : 1;
				break;
			case 'int':
				$value = intval( $value );
				break;
			case 'percentage':
				$value = trim( $value, '%' );
				$value = floatval( $value );
				break;
		}
		$data[ $field ] = $value;
	}

	$o = get_option( 'analyticsauditsettings', array() );
	if ( isset( $o['email_address'] ) ) {
		$content = '';
		foreach ( $data as $k => $v ) {
			$content .= $test_text[ $k ] . ' : ' . test_result_to_text( $k, $v ) . "\r\n";
		}
		wp_mail( $o['email_address'], $o['email_subject'], $content );
	}
	$wpdb->show_errors();
	$wpdb->insert( $wpdb->prefix . DB_TABLE, $data );
	die();
}

add_action( 'wp_ajax_analyticsaudit_save', __NAMESPACE__ . '\save' );
add_action( 'wp_ajax_nopriv_analyticsaudit_save', __NAMESPACE__ . '\save' );

/**
 *  Export CSV
 */

/**
 * Output the export page.
 *
 * @since 1.0
 */
function export_page() {
	?>
	<div class="wrap">
		<h2>Analytics Audit Export</h2>
		<p><a href="<?php echo esc_url( wp_nonce_url( site_url( '?aa_export_csv=1' ) ) ); ?>">Export CSV</a></p>
	</div>
	<?php
}

/**
 *  Check if csv export is triggered and generate the CSV.
 *
 *  @since 1.0
 */
function export_csv() {
	global $wpdb;

	if ( isset( $_GET['aa_export_csv'] ) ) {
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ) ) ) {
			// nonce test failed, bail.
			return;
		}
		$filename = 'Analytics-Audit-CSV-' . date( 'm/d/Y-h:i' ) . '.csv';
		header( 'Content-type: text/csv' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$columns = array(
			'time'                     => 'Time',
			'email'                    => 'Email',
			'website'                  => 'Website',
			'gtm'                      => 'GTM Checkbox',
			'tableau'                  => 'Tableau Checkbox',
			'bigquery'                 => 'BigQuery Checkbox',
			'datastudio'               => 'DataStudio Checkbox',
			'unsure'                   => 'Unsure Checkbox',
			'ecom'                     => 'Ecommerce Checkbox',
			'lead'                     => 'Lead Generation Checkbox',
			'publisher'                => 'Publisher Checkbox',
			'test_setup'               => 'Setup Test',
			'test_spam'                => 'Spam Test',
			'test_raw'                 => 'Raw Test',
			'test_demographic'         => 'Demographoc Test',
			'test_ecom'                => 'Ecommerce Test',
			'test_events'              => 'Events Test',
			'test_goals'               => 'Goals Setup Test',
			'test_goal_value'          => 'Goal Value Test',
			'test_adwords'             => 'Adwords Linked Test',
			'test_channelgroups'       => 'Channel Groups Test',
			'test_contentgroups'       => 'Content Groups Test',
			'test_sessions'            => 'Number of Sessions',
			'test_bouncerate'          => 'Bouncerate',
			'test_tophost'             => 'Top Host',
			'test_majority_channel'    => 'Name of Majority Channel',
			'test_majority_session'    => 'Number of Majority Sessions',
			'test_majority_percentage' => 'Percentage of Majority Sessions',
		);

		$out = fopen('php://output', 'w');
		$headers = array();
		foreach ( $columns as $c ) {
			$headers[] = $c;
		}
		fputcsv( $out, $headers );

		$rows = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . DB_TABLE );
		foreach ( $rows as $row ) {
			$values = array();
			foreach ( $columns as $test => $c ) {
				$values[] = test_result_to_text( $test, $row->{$test} );
			}
			fputcsv( $out, $values );
		}
		fclose( $out );

		die();
	}
}

add_action( 'init', __NAMESPACE__ . '\export_csv', 1 );
