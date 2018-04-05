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
		var account = jQuery( '#analyticsaudit_account' ).val();
		var property = jQuery( '[data-account=' + account + '] select' ).val();
		var domain = jQuery( '[data-account=' + account + '] select option:selected' ).text();
		var profile = jQuery( '[data-property=' + account + '-' + property + '] select' ).val();

		jQuery( '#analytucsaudit_message').text( 'Fetching. actionables..' );
		jQuery( '#analytucsaudit_results').show();
		jQuery( '#analytucsaudit_message').show();
		jQuery( '.analytucsaudit_test' ).removeClass( 'passed' ).removeClass( 'failed' );
		equal_tests_height();

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

		// Get actinables.
		var data = {
				'action':'analyticsaudit_actionable',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};
		var actinables = ['goals_set_up', 'demographic_data', 'events', 'tracking_enhanced_ecomerce', 'measuring_goal_values'];
		actinables.forEach( function (item) {
			jQuery( '#'+ item).hide();
		});

		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			var result = JSON.parse(response.data);
			jQuery( '#analytucsaudit_message').hide();
			actinables.forEach( function (item) {
				if ( result[ item ] ) {
					jQuery( '#analytucsaudit_test_'+ item).addClass( 'passed' ).show();
				} else {
					jQuery( '#analytucsaudit_test_'+ item).addClass( 'failed' ).show();
				}
			});
		});

		var data = {
				'action':'analyticsaudit_accessable',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};
		var accessables = ['linked_search_console', 'customize_channel_group', 'content_groups'];
		actinables.forEach( function (item) {
			jQuery( '#'+ item).hide();
		});
		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			var result = JSON.parse(response.data);
			jQuery( '#analytucsaudit_message').hide();
			accessables.forEach( function (item) {
				if ( result[ item ] ) {
					jQuery( '#analytucsaudit_test_'+ item).css('background-color','green').show();
				} else {
					jQuery( '#analytucsaudit_test_'+ item).css('background-color','red').show();
				}
			});
		});

		var data = {
				'action':'analyticsaudit_accurate',
				'profile' : profile,
				'property' : property,
				'domain' : domain,
		};

		var accurates = ['setup_correct', 'filltering_spam', 'raw_or_testing_view'];
		actinables.forEach( function (item) {
			jQuery( '#'+ item).hide();
		});

		jQuery.post(analyticsaudit_vars.ajax_url, data, function(response) {
			var result = JSON.parse(response.data);
			jQuery( '#analytucsaudit_message').hide();
			accurates.forEach( function (item) {
				if ( result[ item ] ) {
					jQuery( '#analytucsaudit_test_'+ item).css('background-color','green').show();
				} else {
					jQuery( '#analytucsaudit_test_'+ item).css('background-color','red').show();
				}
			});
		});
	});

}

analyticsaudit();
