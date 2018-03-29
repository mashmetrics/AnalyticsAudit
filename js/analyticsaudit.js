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

	jQuery(document).ready( function () {
		refresh_selection();
	});

	jQuery('.analytucsaudit_profile select').on( 'change', function () {
		refresh_selection();
	});

}

analyticsaudit();
