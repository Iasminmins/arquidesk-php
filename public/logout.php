<?php

require_once __DIR__ . '/../app/includes/auth.php';

logout_user();
redirect('/login.php');
