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
	$reset_text      = esc_html__( 'Retry', 'analyticsaudit' );
	$action_url      = esc_url( site_url() );
	$return_to       = esc_url( get_permalink() );
	$return_to_field = RETURN_TO_FIELD;

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
	<button type="submit">$reset_text</button>
</form>
EOT;

	$checkboxes = <<<EOT
<div><input type="checkbox" id="analytucsaudit_tableau"><label for="analytucsaudit_tableau">Tableau</label></div>
<div><input type="checkbox" id="analytucsaudit_datastudio"><label for="analytucsaudit_datastudio">Data Studio</label></div>
<div><input type="checkbox" id="analytucsaudit_bigquery"><label for="analytucsaudit_bigquery">Big Query</label></div>
<div><input type="checkbox" id="analytucsaudit_unsue"><label for="analytucsaudit_unsue">Unsure</label></div>
<div><input type="checkbox" id="analytucsaudit_gtm"><label for="analytucsaudit_gtm">Google Tag Manager</label></div>
EOT;

	if ( isset( $_COOKIE[ TOKEN_COOKIE ] ) ) {
		// Get GA accounts and views from the user's account.
		$response = wp_remote_get( 'https://www.googleapis.com/analytics/v3/management/accountSummaries', array(
			'headers' => array( 'Authorization' => 'Bearer ' . wp_unslash( $_COOKIE[ TOKEN_COOKIE ] ) ),
		) );
		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
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
							'id'       => $property->id,
							'name'     => $property->name,
							'url'      => $property->websiteUrl,
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

					// Add styling.
					$ret .= '<style>';
					ob_start();
					include __DIR__ . '/css/analyticsaudit.css';
					$ret .= ob_get_contents();
					ob_end_clean();
					$ret .= '</style>';
					$ret .= '<p class="analytucsaudit_accounts"><label for="analyticsaudit_account">Google Account</label><select id="analyticsaudit_account">';
					foreach ( $accounts as $account ) {
						$ret .= '<option value="' . esc_attr( $account['id'] ) . '">' . esc_html( $account['name'] ) . '</option>';
					}
					$ret .= '</select></p>';

					foreach ( $accounts as $account ) {
						$ret .= '<p class="analytucsaudit_properties" data-account=' . esc_attr( $account['id'] ) . '><label for="analyticsaudit_property-' . esc_attr( $account['id'] ) . '">Property (Website)</label><select id="analyticsaudit_property-' . esc_attr( $account['id'] ) . '">';
						foreach ( $account['properties'] as $property ) {
							$ret .= '<option value="' . esc_attr( $property['id'] ) . '">' . esc_html( $property['name'] ) . '</option>';
						}
						$ret .= '</select></p>';
					}

					foreach ( $accounts as $account ) {
						foreach ( $account['properties'] as $property ) {
							$property_id = esc_attr( $account['id'] ) . '-' . esc_attr( $property['id'] );

							$ret .= '<p class="analytucsaudit_profiles" data-property=' . $property_id . '><label for="analyticsaudit_profile-' . $property_id . '">View</label><select id="analyticsaudit_profile-' . $property_id . '">';
							foreach ( $property['profiles'] as $profile ) {
								$ret .= '<option value="' . esc_attr( $profile['id'] ) . '">' . esc_html( $profile['name'] ) . '</option>';
							}
							$ret .= '</select></p>';
						}
					}

					$ret  = '<div class="analytucsaudit_profile">' . $ret . '</div>';
					$ret .= '<div class="analytucsaudit_checkboxes">';
					$ret .= '<p>Other Data Tools Used (check all that apply)</p>';
					$ret .= $checkboxes . '</div>';
					$ret .= '<div class="analytucsaudit-buttons"><button class="fetch-button" type="button">' . $fetch_text . '</button>' . $retry_form . '</div>';
					$ret .= '<div id="analytucsaudit_message">Running the tests...</div>';
					$ret .= '<div id="analytucsaudit_results" style="display:none">';

					$options = get_option( 'analyticsauditsettings' );

					$header = array(
						'accurate '   => 'accurate_header',
						'actionable ' => 'actionable_header',
						'accessible ' => 'accessiable_header',
					);

					// header blurb.
					$ret .= '<div class="analytucsaudit_tests_header">';
					foreach ( $header as $id => $prefix ) {
						$ret .= '<div class="analytucsaudit_test_description" id="analytucsaudit_test_description_' . esc_attr( $id ) . '">';
						$ret .= '<h4>' . esc_html( $options[ $prefix . '_title' ] ) . '</h4>';
						$ret .= '<div>' . wpautop( wptexturize( $options[ $prefix . '_text' ] ) ) . '</div>';
						$ret .= '</div>';
					}
					$ret .= '<div style="clear:both"></div>';
					$ret .= '</div>';

					// Tests status.
					$tests = array(
						'accurate'   => array(
							'gtm'                 => 'gtm',
							'setup_correct'       => 'setup_issues',
							'filltering_spam'     => 'spam',
							'raw_or_testing_view' => 'testing_view',
						),
						'actionable' => array(
							'goals_set_up'               => 'goals',
							'demographic_data'           => 'tracking_demographic',
							'events'                     => 'tracking_events',
							'tracking_enhanced_ecomerce' => 'enhanced_ecommerce',
							'measuring_goal_values'      => 'measuring_goal_values',
						),
						'accessible' => array(
							'linked_search_console'   => 'search_console',
							'customize_channel_group' => 'channel_groups',
							'content_groups'          => 'content_groups',
							'tools'                   => 'tools',
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

					$ret .= '</div>';
				}
			} else {
				$ret = $form;
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

		if ( ! is_wp_error( $response ) && 200 === $response['response']['code'] ) {
			$json = json_decode( $response['body'] );
			if ( null !== $json ) {
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
	add_section( 'analyticsauditsettings_search_console_section', __( 'Search Console and AdWords', 'analyticsaudit' ), 'search_console' );
	add_section( 'analyticsauditsettings_channel_groups_section', __( 'Channel Groups', 'analyticsaudit' ), 'channel_groups' );
	add_section( 'analyticsauditsettings_content_groups_section', __( 'Content Groups', 'analyticsaudit' ), 'content_groups' );
	add_section( 'analyticsauditsettings_tools_section', __( 'Dashboard Tools', 'analyticsaudit' ), 'tools' );
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
