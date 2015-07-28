<h2>Auftragsübersicht</h2>

<table>
<thead>
<tr><th>ID</th><th>Anzahl an Kits</th></tr>
</thead>
<tbody>
<?php

$order_res = pg_query( "SELECT id,kit_count FROM orders ORDER BY id;" );

while( $order = pg_fetch_row( $order_res ) ) {

?>
<tr><td><a href="/?scenario=kitting&order_id=<?php echo $order[ 0 ]; ?>"><?php echo $order[ 0 ]; ?></a></td><td><?php echo $order[ 1 ];?></td></tr>
<?php

}

?>
</tbody>
</table>
