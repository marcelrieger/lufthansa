<?php

function create_tag( ) {
	/* Picks first tag from database which is not associated to group */
	$free = pg_fetch_row( pg_query( "SELECT id FROM tags WHERE NOT EXISTS( SELECT * FROM groups WHERE tags.id=groups.tag_id ) LIMIT 1;" ) );
	return $free[ 0 ];
}

if ($_GET["print"]="1" && isset($_GET["material"])) {

	if ($_GET["material"]=="0")
		$cert = "cert/"."seal.pdf";
	else
		$cert = "cert/"."cert".".txt";

	echo json_encode( exec("python scripts/print.py /var/www/ltls/localhost/".$cert) );

} else if ($_GET["cabinet"]="1") {

	$location = (isset($_GET["location"])) ? $_GET["location"] : ""

	$location = explode("-", $location);

	$location = str_replace("-", " ", $location);

	exec("python scripts/led.py "+$location)

	echo json_encode( "LED UPDATED." );

} else if( is_null( $current_order ) || is_null( $current_kit ) ) {

?>
{ "err":"Keine gültiges Kit gewählt" }
<?php

} else {
	if( isset( $_POST[ "requests" ] )&& is_array( $requests = $_POST[ "requests" ] ) ) {
		foreach( $requests as $request ) {
			if( !is_null( $json = json_decode( $request ) )&& is_object( $json )&& isset( $json->id )&& isset( $json->value )&& isset( $json->tag ) ) {

				if ( (int)( $json->status ) < 0 ) {
					$value = (int)( $json->value );
					$id = (int)( $json->id );
					$tag = $json->tag==true;

					// TODO: Make atomic/lock
					$take_res = pg_query( "UPDATE classes SET inventory=inventory-($value-groups.count) FROM groups WHERE classes.id=groups.class_id AND groups.order_id={$current_order[ "id" ]} AND groups.kit_id={$current_kit[ "id" ]} AND groups.id=$id AND classes.inventory>=($value-groups.count);" );

					if( pg_affected_rows( $take_res )>0 )
						pg_query( "UPDATE groups SET count=$value WHERE order_id={$current_order[ "id" ]} AND kit_id={$current_kit[ "id" ]} AND id=$id;" );

					if( $tag )
						pg_query( "UPDATE groups SET tag_id=".pgvalue( create_tag( ) )." WHERE order_id={$current_order[ "id" ]} AND kit_id={$current_kit[ "id" ]} AND id=$id AND count=target;" );
				} else {
					pg_query( "UPDATE kits SET status=".((int)( $json->status ))." WHERE order_id={$current_order[ "id" ]} AND id={$current_kit[ "id" ]};" );
				}

			}
		}
	}

	$stat_res = pg_query( "SELECT groups.id,classes.description,classes.pn,classes.weight,classes.location,groups.count,groups.target,classes.inventory,groups.tag_id,groups.archived FROM groups LEFT JOIN classes ON groups.class_id=classes.id WHERE order_id={$current_order[ "id" ]} AND kit_id={$current_kit[ "id" ]} ORDER BY groups.id;" );

	$result = array( );

	while( $stat = pg_fetch_object( $stat_res ) )
		$result[ ]= $stat;

	echo json_encode( $result );
}
