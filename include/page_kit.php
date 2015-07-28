<?php
	if( $role_id!=LTLS_ADMIN )
		pg_query( "UPDATE kits SET user_id=".( (int)$role_id )." WHERE id={$current_kit[ "id" ]};" );

	chdir( LTLS_UTILDIR );

	exec( "./processmanager rfid ./rfidhandler 137.226.151.151 &>/dev/null </dev/null &" );
?>
<script type="text/javascript">
/* <![CDATA[ */
current_order_id = <?php echo $current_order[ "id" ]; ?>;
current_kit_id = <?php echo $current_kit[ "id" ]; ?>;
/* ]]> */
</script>
<script src="jquery.js" type="text/javascript"></script>
<script src="kitstatus.js" type="text/javascript"></script>
<h2><a href="/?scenario=kitting">Auftragsübersicht</a> &gt; <a href="/?scenario=kitting&order_id=<?php echo $current_order[ "id" ]; ?>">Auftrag  <?php echo $current_order[ "id" ]; ?></a> &gt; Kit <?php echo $current_kit[ "id" ]; ?></h2>
<table>
<thead>
<tr><th>Objekttyp</th><th>Inventar</th><th>Packzahl Soll</th><th>Packzahl Ist</th><th>RFID Status</th></tr>
</thead>
<tbody id="kittable">
</tbody>
</table>
