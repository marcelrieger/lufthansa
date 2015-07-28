<div class="container">
  <div class="pagecontent">

    <form action="." method="post">
			<?php

				if( !isset( $_REQUEST[ "logout" ] ) ) {
					foreach( $_REQUEST as $var=>$val ) {

			?>
				<input type="hidden" name="<?php echo htmlentities( $var ); ?>" value="<?php echo htmlentities( $val ); ?>" />
			<?php

					}
				}

			?>
      <div class="form-group">
        <label for="exampleInputEmail1">Benutzername</label>
        <input type="text" class="form-control" name="username" placeholder="Benutzername">
      </div>
      <div class="form-group">
        <label for="exampleInputPassword1">Passwort</label>
        <input type="password" class="form-control" name="password" placeholder="Passwort">
      </div>
      <button type="submit" class="btn btn-default">Entsperren</button>
    </form>

  </div>
</div>
