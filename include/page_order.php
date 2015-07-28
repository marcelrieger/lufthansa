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

***********************************

<div class="pagetitle">
  <div class="container">
    <div class="title">
      <a href="/?scenario=kitting">Auftragsübersicht</a> &gt; Auftrag  <?php echo $current_order[ "id" ]; ?>
    </div>
    <div class="subtitle">
      Auftragsfrist: xx.xx.xxxx
    </div>
  </div>
</div>

<div class="container">

  <div class="pagecontent">

    <div class="menucol">

      <?php

      $kits_res = pg_query( "SELECT kits.id id,users.username username FROM kits LEFT JOIN users ON kits.user_id=users.id WHERE kits.order_id={$current_order[ "id" ]} ORDER BY id;" );

      while( $kit = pg_fetch_assoc( $kits_res ) ) {

      ?>

      <div class="row">
        <div class="col left">
          <a href="/?scenario=kitting&order_id=<?php echo $current_order[ "id" ]; ?>&kit_id=<?php echo $kit[ "id" ]; ?>" class="btn">Kit <?php echo $kit[ "id" ]; ?></a>
        </div>
        <div class="col">
          <div class="data">
            <span class="trow"><b>Status:</b> in Bearbeitung</span>
            <span class="trow"><b>Mitarbeiter:</b> <?php echo $kit[ "username" ];?></span>
          </div>
        </div>
      </div>

      <?php

      }

      ?>

    </div>

  </div>
</div>
