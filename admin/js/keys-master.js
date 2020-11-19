jQuery(document).ready( function($) {
	$('.pokm-about-logo').css({opacity:1});

	$( "#pokm-chart-button-authentication" ).on(
		"click",
		function() {
			$( "#pokm-chart-authentication" ).addClass( "active" );
			$( "#pokm-chart-password" ).removeClass( "active" );
			$( "#pokm-chart-turnover" ).removeClass( "active" );
			$( "#pokm-chart-button-authentication" ).addClass( "active" );
			$( "#pokm-chart-button-password" ).removeClass( "active" );
			$( "#pokm-chart-button-turnover" ).removeClass( "active" );
		}
	);
	$( "#pokm-chart-button-password" ).on(
		"click",
		function() {
			$( "#pokm-chart-authentication" ).removeClass( "active" );
			$( "#pokm-chart-password" ).addClass( "active" );
			$( "#pokm-chart-turnover" ).removeClass( "active" );
			$( "#pokm-chart-button-authentication" ).removeClass( "active" );
			$( "#pokm-chart-button-password" ).addClass( "active" );
			$( "#pokm-chart-button-turnover" ).removeClass( "active" );
		}
	);
	$( "#pokm-chart-button-turnover" ).on(
		"click",
		function() {
			$( "#pokm-chart-authentication" ).removeClass( "active" );
			$( "#pokm-chart-password" ).removeClass( "active" );
			$( "#pokm-chart-turnover" ).addClass( "active" );
			$( "#pokm-chart-button-authentication" ).removeClass( "active" );
			$( "#pokm-chart-button-password" ).removeClass( "active" );
			$( "#pokm-chart-button-turnover" ).addClass( "active" );
		}
	);
} );
