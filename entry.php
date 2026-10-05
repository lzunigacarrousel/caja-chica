<?php
session_start();
$_SESSION['portal_ok'] = 1;
header("Location: /CajaChica/index.php?from_portal=1");
exit;
