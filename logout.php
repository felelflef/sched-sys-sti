<?php
session_start();
session_unset();
session_destroy();
header('Location: /sti_scheduling_dashboard.html');
exit;
