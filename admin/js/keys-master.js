jQuery(document).ready( function($) {
	$('.pokm-about-logo').css({opacity:1});

	$( "#pokm-chart-button-user" ).on(
		"click",
		function() {
			$( "#pokm-chart-user" ).addClass( "active" );
			$( "#pokm-chart-session" ).removeClass( "active" );
			$( "#pokm-chart-turnover" ).removeClass( "active" );
			$( "#pokm-chart-log" ).removeClass( "active" );
			$( "#pokm-chart-password" ).removeClass( "active" );
			$( "#pokm-chart-button-user" ).addClass( "active" );
			$( "#pokm-chart-button-session" ).removeClass( "active" );
			$( "#pokm-chart-button-turnover" ).removeClass( "active" );
			$( "#pokm-chart-button-log" ).removeClass( "active" );
			$( "#pokm-chart-button-password" ).removeClass( "active" );
		}
	);
	$( "#pokm-chart-button-session" ).on(
		"click",
		function() {
			$( "#pokm-chart-user" ).removeClass( "active" );
			$( "#pokm-chart-session" ).addClass( "active" );
			$( "#pokm-chart-turnover" ).removeClass( "active" );
			$( "#pokm-chart-log" ).removeClass( "active" );
			$( "#pokm-chart-password" ).removeClass( "active" );
			$( "#pokm-chart-button-user" ).removeClass( "active" );
			$( "#pokm-chart-button-session" ).addClass( "active" );
			$( "#pokm-chart-button-turnover" ).removeClass( "active" );
			$( "#pokm-chart-button-log" ).removeClass( "active" );
			$( "#pokm-chart-button-password" ).removeClass( "active" );
		}
	);
	$( "#pokm-chart-button-turnover" ).on(
		"click",
		function() {
			$( "#pokm-chart-user" ).removeClass( "active" );
			$( "#pokm-chart-session" ).removeClass( "active" );
			$( "#pokm-chart-turnover" ).addClass( "active" );
			$( "#pokm-chart-log" ).removeClass( "active" );
			$( "#pokm-chart-password" ).removeClass( "active" );
			$( "#pokm-chart-button-user" ).removeClass( "active" );
			$( "#pokm-chart-button-session" ).removeClass( "active" );
			$( "#pokm-chart-button-turnover" ).addClass( "active" );
			$( "#pokm-chart-button-log" ).removeClass( "active" );
			$( "#pokm-chart-button-password" ).removeClass( "active" );
		}
	);
	$( "#pokm-chart-button-log" ).on(
		"click",
		function() {
			$( "#pokm-chart-user" ).removeClass( "active" );
			$( "#pokm-chart-session" ).removeClass( "active" );
			$( "#pokm-chart-turnover" ).removeClass( "active" );
			$( "#pokm-chart-log" ).addClass( "active" );
			$( "#pokm-chart-password" ).removeClass( "active" );
			$( "#pokm-chart-button-user" ).removeClass( "active" );
			$( "#pokm-chart-button-session" ).removeClass( "active" );
			$( "#pokm-chart-button-turnover" ).removeClass( "active" );
			$( "#pokm-chart-button-log" ).addClass( "active" );
			$( "#pokm-chart-button-password" ).removeClass( "active" );
		}
	);
	$( "#pokm-chart-button-password" ).on(
		"click",
		function() {
			$( "#pokm-chart-user" ).removeClass( "active" );
			$( "#pokm-chart-session" ).removeClass( "active" );
			$( "#pokm-chart-turnover" ).removeClass( "active" );
			$( "#pokm-chart-log" ).removeClass( "active" );
			$( "#pokm-chart-password" ).addClass( "active" );
			$( "#pokm-chart-button-user" ).removeClass( "active" );
			$( "#pokm-chart-button-session" ).removeClass( "active" );
			$( "#pokm-chart-button-turnover" ).removeClass( "active" );
			$( "#pokm-chart-button-log" ).removeClass( "active" );
			$( "#pokm-chart-button-password" ).addClass( "active" );
		}
	);




} );
