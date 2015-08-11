<?php
pg_query( "UPDATE groups SET archived='f';" );
pg_query( "UPDATE groups SET tag_id='';" );
echo "Reset complete.";
