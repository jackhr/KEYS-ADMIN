<?php

include_once 'includes/admin-auth.php';

admin_logout();

header('Location: /login.php');
exit;
