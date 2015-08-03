<?php
$printer = "\\\\Pserver.php.net\\printername");
if($ph = printer_open($printer))
{
   // Get file contents
   $fh = fopen("filename.ext", "rb");
   $content = fread($fh, filesize("filename.ext"));
   fclose($fh);

   // Set print mode to RAW and send PDF to printer
   printer_set_option($ph, PRINTER_MODE, "RAW");
   printer_write($ph, $content);
   printer_close($ph);
}
else "Couldn't connect...";
?>
