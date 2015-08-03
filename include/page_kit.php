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
<script src="static/js/jquery.js" type="text/javascript"></script>
<script src="static/js/bootstrap.min.js" type="text/javascript"></script>
<script src="static/js/kitstatus.js" type="text/javascript"></script>

<div class="pagetitle">
  <div class="container">
    <div class="title">
			<a href="/?scenario=kitting">Auftragsübersicht</a> &gt; <a href="/?scenario=kitting&order_id=<?php echo $current_order[ "id" ]; ?>">Auftrag  <?php echo $current_order[ "id" ]; ?></a> &gt; Kit <?php echo ($current_kit[ "id" ]+1); ?>
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

        <div class="pageprogress">
          <div class="breadcrumb">
            <ul>
              <li class="active"><span>Stückliste</span></li>
              <li<?php if($current_kit[ "status" ]>0) echo " class=\"active\""; ?>><span>Vorkommissienieren</span></li>
              <li<?php if($current_kit[ "status" ]>1) echo " class=\"active\""; ?>><span>Kitting</span></li>
            </ol>
          </div>
					<div class="next">
            <div class="btn-group" role="group" aria-label="Default button group">
              <button type="button" class="btn btn-default kitdocumentation" data-toggle="modal" data-target="#kitdocumentation"><span class="glyphicon glyphicon-camera" aria-hidden="true"></span> Dokumentation</button>
              <button type="button" class="btn btn-default kitseal"><span class="glyphicon glyphicon-print" aria-hidden="true"></span> Vollständigkeitssiegel</button>
            </div>
          </div>
        </div>

				<div class="message">

				</div>

				<div class="modal fade" id="kitdocumentation" tabindex="-1" role="dialog" aria-labelledby="kitdocumentationLabel">
				  <div class="modal-dialog" role="document">
				    <div class="modal-content">
				      <div class="modal-body">
								<img class="center" src="static/img/paket.jpg" alt="Vollständigkeitsdokumentation" class="img-thumbnail" style="width: 100%;">
				      </div>
				    </div>
				  </div>
				</div>

        <div class="pagecontent table-responsive">
          <table class="table table-striped table-hover table-condensed">
            <thead>
              <tr>
                <th>
                  Materialbezeichnung
                </th>
                <th>
                  P/N
                </th>
                <th>
                  Gewicht
                </th>
                <th>
                  Inventar
                </th>
                <th>
									Benötigte Menge
                </th>
                <th>
                  Entnommene Menge
                </th>
								<th>
									Lagerplatz
								</th>
                <th>
                  RFID Status
                </th>
								<th>
								</th>
              </tr>
            </thead>
						<tbody id="kittable">
						</tbody>
          </table>
        </div>
      </div>
