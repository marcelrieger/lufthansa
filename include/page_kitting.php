<?php exec("python scripts/led.py 1"); ?>
<div class="pagetitle">
  <div class="container">
    <div class="title">
      Auftragsübersicht
    </div>
  </div>
</div>

<div class="container">

  <div class="pagecontent">

    <div class="menucol">

      <?php

      $order_res = pg_query( "SELECT id,kit_count,deadline FROM orders ORDER BY id;" );

      while( $order = pg_fetch_row( $order_res ) ) {

      ?>

      <div class="row">
        <div class="col left">
          <a href="/?scenario=kitting&order_id=<?php echo $order[ 0 ]; ?>" class="btn">Auftrag <?php echo $order[ 0 ]; ?></a>
        </div>
        <div class="col">
          <div class="data">
            <span class="trow"><b>Anzahl an Kits:</b> <?php echo $order[ 1 ];?></span>
            <?php

        		if (isset($order[2])) { ?>

        			<span class="trow"><b>Auftragsfrist:</b>
        	      <?php
        				$date = date("d-m-Y", strtotime($order[2]));
        				echo $date; ?>
              </span>

        		<?php } ?>
          </div>
        </div>
      </div>
      <?php

      }

      ?>

    </div>

  </div>
</div>
