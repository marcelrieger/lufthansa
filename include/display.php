<?xml version="1.1" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo LTLS_TITLE; ?></title>
<link href="static/css/bootstrap.min.css" rel="stylesheet">
<link href="static/css/main.css" rel="stylesheet">
<!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
<![endif]-->
</head>
<body>
<div class="header">
  <div class="container">
		<?php if( !is_null( $role_id ) ) { ?>
			<div class="userinfo">
	      <span class="trow">Nutzer: <?php echo $role_name; ?></span>
	      <span class="trow"><a href="/?logout="><span class="glyphicon glyphicon-off" aria-hidden="true"></span> Logout</a></span>
	    </div>
		<?php	} ?>

    <div class="logo">
      <a href="/">Lufthansa Technik Logistik Services</a>
    </div>
  </div>
</div>

		<div class="page">

      <?php if (count($messages)>0) { ?>
        <div class="pagecontent"><div class="container">
      <?php foreach( $messages as $message ) { ?>
        <div class="alert alert-<?php echo $message[ 0 ]; ?>" role="alert">
          <a href="#" class="alert-link"><?php echo $message[ 1 ]; ?></a>
        </div>
      <?php } ?>
    </div></div>
      <?php }
      $messages = array( ); ?>

	      <?php
				if( is_null( $role_id ) )
					require( "include/loginscreen.php" );
				else
					require( "include/pages.php" );
				?>

    </div>

    <div class="footer">
      <div class="container">
        <p class="center">
          2015 &copy; Lufthansa Technik Logistik Services
        </p>
      </div>
    </div>

  </body>
</html>
