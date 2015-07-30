<div class="pagetitle">
  <div class="container">
    <div class="title">
      <a href="/?scenario=kitting">Auftragsübersicht</a> &gt; Auftrag  <?php echo $current_order[ "id" ]; ?>
    </div>
    <?php

		if (isset($current_order["deadline"])) { ?>

			<div class="subtitle">
	      Auftragsfrist: <?php
				$date = date("d-m-Y", strtotime($current_order["deadline"]));
				echo $date; ?>
	    </div>

		<?php } ?>
  </div>
</div>

<div class="container">

  <div class="pagecontent">

    <div class="menucol">

      <?php

      $kits_res = pg_query( "SELECT kits.id id, kits.status status,users.username username FROM kits LEFT JOIN users ON kits.user_id=users.id WHERE kits.order_id={$current_order[ "id" ]} ORDER BY id;" );

      while( $kit = pg_fetch_assoc( $kits_res ) ) {

      ?>

      <div class="row">
        <div class="col left">
          <a href="/?scenario=kitting&order_id=<?php echo $current_order[ "id" ]; ?>&kit_id=<?php echo $kit[ "id" ]; ?>" class="btn<?php if ($kit[ "status" ]==2) echo " done"; ?>">Kit <?php echo ($kit[ "id" ]+1); ?></a>
        </div>
        <div class="col">
          <div class="data">
            <span class="trow"><b>Status:</b> <?php
              $stts;
              if ($kit[ "status" ]==1)
              $stts = "Vorkommissienieren";
              else if ($kit[ "status" ]==2)
              $stts = "Kitting";
              else
              $stts = "in Bearbeitung";
              echo $stts;?>
            </span>
            <?php if (isset($kit[ "username" ])):?><span class="trow"><b>Mitarbeiter:</b> <?php echo $kit[ "username" ];endif;?></span>
          </div>
        </div>
      </div>

      <?php

      }

      ?>

    </div>

  </div>
</div>
