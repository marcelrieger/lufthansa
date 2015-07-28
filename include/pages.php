<?php

if( isset( $_GET[ "scenario" ] )&& in_array( $_GET[ "scenario" ],array( "kitting","init" ) ) ) {
	if( $_GET[ "scenario" ]=="kitting" ) {
		if( !is_null( $current_order ) ) {
			if( !is_null( $current_kit ) )
				require( "include/page_kit.php" );
			else
				require( "include/page_order.php" );
		}	else
			require( "include/page_kitting.php" );
	} elseif( $_GET[ "scenario" ]=="init" ) {
		if( $role_id==LTLS_ADMIN ) {
			require( "include/page_initialize.php" );
		}	}
} else
	require( "include/page_overview.php" );

?>
