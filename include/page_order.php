<h2><a href="/?scenario=kitting">Auftragsübersicht</a> &gt; Auftrag  <?php echo $current_order[ "id" ]; ?></h2>

<table>
<thead>
<tr><th>ID</th><th>Bearbeitung</tr>
</thead>
<tbody>
<?php

$kits_res = pg_query( "SELECT kits.id id,users.username username FROM kits LEFT JOIN users ON kits.user_id=users.id WHERE kits.order_id={$current_order[ "id" ]} ORDER BY id;" );

while( $kit = pg_fetch_assoc( $kits_res ) ) {

?>
<tr><td><a href="/?scenario=kitting&order_id=<?php echo $current_order[ "id" ]; ?>&kit_id=<?php echo $kit[ "id" ]; ?>"><?php echo $kit[ "id" ]; ?></a></td><td><?php echo $kit[ "username" ];?></td></tr>
<?php

}

?>
</tbody>
</table>
