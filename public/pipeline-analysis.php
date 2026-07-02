<?php

require_once __DIR__ . '/../app/includes/auth.php';

require_auth();
redirect('/');
