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


pg_query( "UPDATE groups SET archived='f';" );
pg_query( "UPDATE groups SET tag_id='';" );
pg_query( "UPDATE groups SET count=0;" );
pg_query( "UPDATE classes SET inventory=2500;" );
echo "Reset complete.";
