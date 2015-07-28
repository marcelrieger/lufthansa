<?xml version="1.1" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo LTLS_TITLE; ?></title>
</head>
<body>
<?php

if( !is_null( $role_id ) ) {

?>
	<div id="logoutform">
		<form method="get" action="/">
			<button type="submit" name="logout" style="vertical-align:middle;">
				<?php echo $role_name; ?>
				<img style="vertical-align:bottom" src="icons/<?php echo $role_id==LTLS_ADMIN?"admin.png":"lock.png"; ?>" alt="Sperren" />
			</button>
		</form>
	</div>
<?php

}

foreach( $messages as $message ) {

?>
	<p class="message" style="border: 2px solid <?php echo $message[ 1 ]; ?>; background-color: <?php echo $message[ 0 ]; ?>; color: <?php echo $message[ 1 ]; ?>">
		<b><?php echo $message[ 2 ]; ?></b>
	</p>
<?php

}

$messages = array( );
	
?>
<?php

if( is_null( $role_id ) )
	require( "include/loginscreen.php" );
else
	require( "include/pages.php" );

?>
</body>
</html>
