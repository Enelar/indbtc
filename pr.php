<?php
phpinfo();
exit();
system("tail -n 1000 /var/lib/bitcoin/debug.log | grep progress | tail -n 1 | sed 's/^.* //'");

