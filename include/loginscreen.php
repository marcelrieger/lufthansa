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
	<input type="text" name="username" value="" />
	<input type="password" name="password" value="" />
	<button type="submit"><img src="icons/unlock.png" alt="Entsperren" /></button>
</form>
