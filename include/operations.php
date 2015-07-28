<?php

$current_order = NULL;
$current_kit = NULL;

function create_class( $classname ) {
	preg_match( "/([[:alnum:][:space:]]*)/",$classname,$matches );
	$classname = $matches[ 1 ];

	$res = pg_query( "SELECT id FROM classes WHERE description=".pgvalue( $classname ).";" );

	if( $row = pg_fetch_row( $res ) )
		return $row[ 0 ];
	$res = pg_query( "INSERT INTO classes(description) VALUES(".pgvalue( $classname ).") RETURNING id;" );
	return (int)( pg_fetch_row( $res )[ 0 ] );
}

function create_rfid( $tagname ) {
	@pg_query( "INSERT INTO tags(id) VALUES(".pgvalue( $tagname ).");" );
}

if( isset( $_GET[ "order_id" ] )&& $row = pg_fetch_assoc( pg_query( "SELECT * FROM orders WHERE id=".( (int)( $_GET[ "order_id" ] ) ).";" ) ) ) {
	$current_order = $row;
	if( isset( $_GET[ "kit_id" ] )&& $row = pg_fetch_assoc( pg_query( "SELECT * FROM kits WHERE order_id={$current_order[ "id" ]} AND id=".( (int)( $_GET[ "kit_id" ] ) ).";" ) ) )
		$current_kit = $row;
}

if( isset( $_GET[ "scenario" ] )&& $_GET[ "scenario" ]=="init" && $role_id!=LTLS_ADMIN )
	$messages[ ]= array( "yellow","red","Nur Administrator kann die Datenbank zurücksetzen!" );

if( isset( $_POST[ "code" ] ) ) {
	pg_query( "TRUNCATE classes,groups,kits,orders,tags RESTART IDENTITY CASCADE;" );

	$lines = explode( "\n",$_POST[ "code" ] );
	$mode = 0;

	$order_id = NULL;
	$kit_id = NULL;
	foreach( $lines as $line ) {
		if( $line=='' || $line[ 0 ]=="#" ) {
			$messages[ ]= array( "silver","black","Read comment: $line" );
		} elseif( $line[ 0 ]=="[" ) {
			if( preg_match( "/^\\[([[:alnum:]]*)\\]/",$line,$matches ) ) {

				$mode = array_search( $matches[ 1 ],array( "INVENTORY","TAGS","ORDER","KIT","ITEMS","USERS" ) );

				if( $mode==2 ) {
					$res = pg_query( "INSERT INTO orders DEFAULT VALUES RETURNING id;" );
					$order_id = (int)( pg_fetch_row( $res )[ 0 ] );
					$kit_id = NULL;

					$messages[ ]= array( "lime","black","Created new order with ID $order_id" );
				} elseif( $mode==3 ) {
					if( !is_null( $order_id ) ) {
						$res = pg_query( "WITH t AS( UPDATE orders SET kit_count=kit_count+1 WHERE id=$order_id RETURNING kit_count-1 kitno )INSERT INTO kits(id,order_id) SELECT t.kitno,$order_id FROM t RETURNING id;" );
						$kit_id = (int)( pg_fetch_row( $res )[ 0 ] );
					}
				}
			}
		} elseif( $mode==0 ) {
			$fields = explode( ",",$line );
			if( count( $fields )==2 )
				pg_query( "UPDATE classes SET inventory=".( (int)( $fields[ 1 ] ) )." WHERE id=".create_class( $fields[ 0 ] ).";" );
		} elseif( $mode==1 ) {
			create_rfid( substr( $line,0,-1 ) );
		} elseif( $mode==2 ) {
			// Order parameters
		} elseif( $mode==3 ) {
			// Kit parameters
		} elseif( $mode==4 ) {
			// Create item
			$fields = explode( ",",$line );
			if( count( $fields )>=5 ) {
				$target = (int)( $fields[ 0 ] );
				$count = (int)( $fields[ 1 ] );
				$tag = $fields[ 2 ];
				$archived = !( $fields[ 3 ]=='f' || $fields[ 3 ]=='' );
				$class_name = implode( ",",array_slice( $fields,4 ) );
				$class_name = substr( $class_name,0,-1 );

				$class_id = create_class( $class_name );
				if( $tag!="" ) {
					create_rfid( $tag );
					$tagstr = pgvalue( $tag );
				} else
					$tagstr = "NULL";

				pg_query( "WITH t AS(UPDATE kits SET group_count=group_count+1 WHERE id=$kit_id AND order_id=$order_id RETURNING group_count-1 groupno) INSERT INTO groups(order_id,kit_id,count,target,class_id,id,tag_id,archived) SELECT $order_id,$kit_id,$count,$target,$class_id,t.groupno,$tagstr,".( $archived?"'t'":"'f'" )." FROM t;" );
			}
		} elseif( $mode==5 ) {
			$fields = explode( ",",$line );
			if( count( $fields==2 ) ) {
				pg_query( "INSERT INTO users(username,password) VALUES(".pgvalue( $fields[ 0 ] ).",decode(md5(".pgvalue( $fields[ 1 ] )."),'hex'));" );
			}
		}
	}
}


?>
