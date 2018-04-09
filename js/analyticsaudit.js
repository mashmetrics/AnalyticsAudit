function analyticsaudit() {

	function refresh_selection() {
		var account = jQuery( '#analyticsaudit_account' ).val();
		var property = jQuery( '[data-account=' + account + '] select' ).val();
		var profile = jQuery( '[data-property=' + account + '-' + property + '] select' ).val();

		jQuery( '.analytucsaudit_properties' ).hide();
		jQuery( '.analytucsaudit_profiles' ).hide();

		jQuery( '[data-account=' + account + ']' ).show();
		jQuery( '[data-property=' + account + '-' + property + ']' ). show();
	}

	function equal_tests_height() {
		var max = 0;
		jQuery( '.analytucsaudit_test' ).each( function () {
			if ( jQuery(this).height() > max ) {
				max = jQuery(this).height();
			}
 		});
		jQuery( '.analytucsaudit_test' ).height( max );
	}

	jQuery(document).ready( function () {
		refresh_selection();
	});

	jQuery('.analytucsaudit_profile select').on( 'change', function () {
		refresh_selection();
	});

	jQuery( '.fetch-button' ).on( 'click', function () {
		jQuery( '#analytucsaudit_results').hide();
		var account = jQuery( '#analyticsaudit_account' ).val();
		var property = jQuery( '[data-account=' + account + '] select' ).val();
		var domain = jQuery( '[data-account=' + account + '] select option:selected' ).text();
		var profile = jQuery( '[data-property=' + account + '-' + property + '] select' ).val();

		var replies = 0;

		/**
		 *  Called from th reply handlers to indicate complition of ajax processing
		 *  once all replies are in expose the test results.
		 */
		function reply_processed() {
			replies++;
			if ( 4 === replies ) {
				jQuery( '#analytucsaudit_message').hide();
				jQuery( '#analytucsaudit_results').show();
				equal_tests_height();
			}
		}

		function error_handler(response) {
			jQuery( '#analytucsaudit_message').addClass( 'error' );
			jQuery( '#analytucsaudit_message').html( 'Something went wrong :( please retry the whole process' );
		}

		jQuery( '#analytucsaudit_message').removeClass( 'error' );
		jQuery( '#analytucsaudit_message').text('Running the tests...');
		jQuery( '#analytucsaudit_message').show();
		jQuery( '.analytucsaudit_test' ).removeClass( 'passed' ).removeClass( 'failed' );

		// Mark GTM test as failed if GTM checkbox is not set
		var gtm = jQuery('#analytucsaudit_gtm').is(":checked");
		if (gtm) {
			jQuery( '#analytucsaudit_test_gtm').addClass( 'passed' ).show();
		} else {
			jQuery( '#analytucsaudit_test_gtm').addClass( 'failed' ).show();
		}

		// Mark tools test as failed if non of the tools checkbox is set
		var tableau = jQuery('#analytucsaudit_tableau').is(":checked");
		var datastudio = jQuery('#analytucsaudit_datastudio').is(":checked");
		var bigquery = jQuery('#analytucsaudit_bigquery').is(":checked");
		if (tableau || datastudio || bigquery) {
			jQuery( '#analytucsaudit_test_tools').addClass( 'passed' ).show();
		} else {
			jQuery( '#analytucsaudit_test_tools').addClass( 'failed' ).show();
		}

		// get ecom and events setup to be used when deciding if they need to be displayed.
		var ecom_on = jQuery('#analytucsaudit_ecom').is(":checked");
		var event_on = jQuery('#analytucsaudit_lead').is(":checked") || jQuery('#analytucsaudit_publisher').is(":checked");

		// Get actionables.
		var data = {
				'action':'analyticsaudit_actionable',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};
		var actionables = ['goals_set_up', 'demographic_data', 'events', 'tracking_enhanced_ecomerce', 'measuring_goal_values'];

		actionables.forEach( function (item) {
			jQuery( '#analytucsaudit_test_'+ item).hide();
		});

		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			if ( response.success ) {
				var result = JSON.parse(response.data);
				actionables.forEach( function (item) {
					if ( result[ item ] ) {
						jQuery( '#analytucsaudit_test_'+ item).addClass( 'passed' );
					} else {
						jQuery( '#analytucsaudit_test_'+ item).addClass( 'failed' );
					}

					// Make sure we display ecom and event only when user selects it.
					if  ( 'tracking_enhanced_ecomerce' == item ) {
						if ( ecom_on ) {
							jQuery( '#analytucsaudit_test_'+ item).show();
						}
					} else if ( 'events' == item ) {
						if ( event_on ) {
							jQuery( '#analytucsaudit_test_'+ item).show();
						}
					} else {
						jQuery( '#analytucsaudit_test_'+ item).show();
					}
				});
				reply_processed();
			} else {
				error_handler( response );
			}
		});

		var data = {
				'action':'analyticsaudit_accessable',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};
		var accessables = ['linked_search_console', 'customize_channel_group', 'content_groups'];
		accessables.forEach( function (item) {
			jQuery( '#analytucsaudit_test_'+ item).hide();
		});
		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			if ( response.success ) {
				var result = JSON.parse(response.data);
				accessables.forEach( function (item) {
					if ( result[ item ] ) {
						jQuery( '#analytucsaudit_test_'+ item).addClass('passed').show();
					} else {
						jQuery( '#analytucsaudit_test_'+ item).addClass('failed').show();
					}
				});
				reply_processed();
			} else {
				error_handler( response );
			}
		});

		var data = {
				'action':'analyticsaudit_accurate',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};

		var accurates = ['setup_correct', 'filltering_spam', 'raw_or_testing_view'];
		accurates.forEach( function (item) {
			jQuery( '#analytucsaudit_test_'+ item).hide();
		});

		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			if ( response.success ) {
				var result = JSON.parse(response.data);
				accurates.forEach( function (item) {
					if ( result[ item ] ) {
						jQuery( '#analytucsaudit_test_'+ item).addClass('passed').show();
					} else {
						jQuery( '#analytucsaudit_test_'+ item).addClass('failed').show();
					}
				});
				reply_processed();
			} else {
				error_handler( response );
			}
		});

		// Collect metrics.
		var data = {
				'action':'analyticsaudit_data_pull',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};

		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			if ( response.success ) {
				var data_point = ['total_sessions', 'bounce_rate', 'top_hostname'];
				var traffic_majority = ['channel', 'sessions', 'percentage'];
				var result = JSON.parse(response.data);
				data_point.forEach( function (item) {
					jQuery( '#analytucsaudit_datapoint_'+ item).text( result[ item ] ).show();
				});
				traffic_majority.forEach( function (item) {
					jQuery( '#analytucsaudit_datapoint_'+ item).text( result['traffic_majority'][ item ] ).show();
				});
				reply_processed();
			} else {
				error_handler( response );
			}
		});

	});

}

analyticsaudit();
