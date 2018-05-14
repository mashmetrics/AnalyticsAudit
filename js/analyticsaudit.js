function analyticsaudit() {

	function refresh_selection() {
		var account = jQuery( '#analyticsaudit_account' ).val();
		var property = jQuery( '[data-account=' + account + '] select' ).val();
		var profile = jQuery( '[data-property=' + account + '-' + property + '] select' ).val();

		jQuery( '.analytucsaudit_properties' ).hide();
		jQuery( '.analytucsaudit_profiles p' ).hide();

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
		var domain = jQuery( '[data-account=' + account + '] select option:selected' ).data('url');
		var profile = jQuery( '[data-property=' + account + '-' + property + '] select' ).val();

		var replies = 0;
		var results = {};  // hold all the results of the tests


		/**
		 *  Called from th reply handlers to indicate complition of ajax processing
		 *  once all replies are in expose the test results, and the results are sent to the server
		 *  for storage.
		 */
		function reply_processed( result ) {

			var storename;
			var name_map = {
				'goals_set_up' : 'test_goals',
				'goal_value' : 'test_goal_value',
				'demographic_data' : 'test_demographic',
				'events' : 'test_events',
				'enhanced_ecommerce' : 'test_ecom',
				'adwords_linked' : 'test_adwords',
				'channel_groups' : 'test_channelgroups',
				'content_groups' : 'test_contentgroups',
				'setup_correct' : 'test_setup',
				'filltering_spam' : 'test_spam',
				'raw_or_testing_view' : 'test_raw',
				'total_sessions' : 'test_sessions',
				'bounce_rate' : 'test_bouncerate',
				'top_hostname' : 'test_tophost',
				'channel' : 'test_majority_channel',
				'sessions' : 'test_majority_session',
				'percentage' : 'test_majority_percentage',
			}
			for (var attrname in result) {
				console.log(attrname);
				// map results to storage format
				storename = name_map[attrname];
				results[storename] = result[attrname];
			}

			replies++;
			if ( 4 === replies ) {
				jQuery( '#analytucsaudit_message').hide();
				jQuery( '#analytucsaudit_results').show();
				equal_tests_height();

				var data = {
						'action' : 'analyticsaudit_save',
						'website' : domain,
						'email' : jQuery('#analytucsaudit_email').attr('data-email'),
						'gtm' : jQuery('#analytucsaudit_gtm').is(":checked"),
						'tableau' : jQuery('#analytucsaudit_tableau').is(":checked"),
						'bigquery' : jQuery('#analytucsaudit_bigquery').is(":checked"),
						'datastudio' : jQuery('#analytucsaudit_datastudio').is(":checked"),
						'unsure' : jQuery('#analytucsaudit_unsure').is(":checked"),
						'ecom' : jQuery('#analytucsaudit_ecom').is(":checked"),
						'lead' : jQuery('#analytucsaudit_lead').is(":checked"),
						'publisher' : jQuery('#analytucsaudit_publisher').is(":checked"),
				};
				for (var attrname in results) {
					data[attrname] = results[attrname];
				}

				jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
				});

			}
		}

		function error_handler(response) {
			jQuery( '#analytucsaudit_message').addClass( 'error' );
			jQuery( '#analytucsaudit_message').html( 'Something went wrong :( please retry the whole process' );
		}

		jQuery( '#analytucsaudit_message').removeClass( 'error' );
		//jQuery( '#analytucsaudit_message').text('Running the tests...');
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
		var actionables = ['goals_set_up', 'demographic_data', 'events', 'enhanced_ecommerce', 'goal_value'];

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
					if  ( 'enhanced_ecommerce' == item ) {
						if ( ecom_on ) {
							jQuery( '#analytucsaudit_test_'+ item).show();
						}
					} else {
						jQuery( '#analytucsaudit_test_'+ item).show();
					}
				});
				reply_processed( result );
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
		var accessables = ['adwords_linked', 'channel_groups', 'content_groups'];
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
				reply_processed( result );
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
				reply_processed( result );
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
					// for storing nicely in results
					result[ item ] = result['traffic_majority'][ item ];
				});
				reply_processed( result );
			} else {
				error_handler( response );
			}
		});

	});

}

analyticsaudit();
