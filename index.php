<?php

require( "include/config.php" );

session_start( );

/* Connection setup */

$dbhost = "localhost";
$dbuser = "ltls";
$dbdb = "ltls";
$dbpass = "ltlspass";
$currentprofile = 0;

$conn = pg_connect( "host=$dbhost dbname=$dbdb user=$dbuser password=$dbpass" );

function pgvalue( $value ) {
	if( is_null( $value ) )
		return "NULL";
	else
		return "'".pg_escape_string( $value )."'";
}

$role_id = NULL;
$role_name = NULL;
$login_name = NULL;

if( isset( $_SESSION[ "messages" ] ) )
	$messages = $_SESSION[ "messages" ];
else
	$messages = array( );

/* Authentication */

if( !isset( $_REQUEST[ "logout" ] ) ) {
/* Verify existing */
	if( isset( $_SESSION[ "role_id" ] ) ) {
		if( $_SESSION[ "role_id" ]==LTLS_ADMIN ) {
				$role_id = LTLS_ADMIN;
				$role_name = LTLS_ADMINALIAS;
		} else {
			$roles_res = pg_query( "SELECT id,username FROM users WHERE upper(username)=upper(".pgvalue( $_SESSION[ "role_name" ] ).");" );
			if( !is_null( $roles_res )&&( $role_row = pg_fetch_row( $roles_res ) ) ) {
				$role_id = (int)( $role_row[ 0 ] );
				$role_name = $role_row[ 1 ];
			}
		}
	}

	if( isset( $_POST[ "username" ] )&& isset( $_POST[ "password" ] ) ) {
		if( $_POST[ "username" ]==LTLS_ADMINALIAS && $_POST[ "password" ]==LTLS_ADMINPASSWD ) {
			$role_id = LTLS_ADMIN;
			$role_name = LTLS_ADMINALIAS;
		} else {
			$login_name = $_POST[ "username" ];
			$roles_res = pg_query( "SELECT id,username FROM users WHERE password=decode(md5(".pgvalue( $_POST[ "password" ] )."),'hex');" );

			if( !is_null( $roles_res )&&( $role_row = pg_fetch_row( $roles_res ) ) ) {
				$role_id = (int)( $role_row[ 0 ] );
				$role_name = $role_row[ 1 ];
			}
		}
	}
}

/** [ 0 ]: Background-Color, [ 1 ]: Text- and Border-Color, [ 2 ]: Message */

if( !is_null( $role_id ) ) {
	// Logged in
	$_SESSION[ "role_id" ]= $role_id;
	$_SESSION[ "role_name" ]= $role_name;
	require( "include/operations.php" );
} elseif( isset( $_SESSION[ "role_id" ] ) ) {
	// Logout
	unset( $_SESSION[ "role_id" ] );
} elseif( isset( $login_name ) ) {
	// Failed login
	$messages[ ]= array( "silver","red","Authentifikation gescheitert! Versuch wurde protokolliert." );
}

if( !isset( $_GET[ "ajax" ] )&& count( $_POST )>0 && !is_null( $role_id ) ) {
	$get_array = array( );
	foreach( $_GET as $name=>$value )
		$get_array[ ]= $name."=".urlencode( $value );

	$_SESSION[ "messages" ]= $messages;

	if( count( $get_array )>0 ) {
		header( "Location: http://{$_SERVER[ "SERVER_NAME" ]}?".implode( "&",$get_array ) );
	} else {
		header( "Location: http://{$_SERVER[ "SERVER_NAME" ]}" );
	}
} else {
	if( isset( $_GET[ "ajax" ] ) )
		require( "include/ajax.php" );
	else
		require( "include/display.php" );
}

$_SESSION[ "messages" ]= $messages;

?>
