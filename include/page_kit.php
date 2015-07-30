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

<div class="pagetitle">
  <div class="container">
    <div class="title">
			<?php echo $current_order; ?>;
			<a href="/?scenario=kitting">Auftragsübersicht</a> &gt; <a href="/?scenario=kitting&order_id=<?php echo $current_order[ "id" ]; ?>">Auftrag  <?php echo $current_order[ "id" ]; ?></a> &gt; Kit <?php echo ($current_kit[ "id" ]+1); ?>
    </div>
    <div class="subtitle">
      Auftragsfrist: xx.xx.xxxx
    </div>
  </div>
</div>

<div class="container">

        <div class="pageprogress">
          <div class="breadcrumb">
            <ul>
              <li class="active"><span>Stückliste</span></li>
              <li><span>Vorkommisienieren</span></li>
              <li><span>Kitting</span></li>
            </ol>
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
              </tr>
            </thead>
						<tbody id="kittable">
						</tbody>
          </table>
        </div>
      </div>
