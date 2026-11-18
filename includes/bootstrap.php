<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/helpers.php';

if (!is_dir(STORAGE_PATH)) mkdir(STORAGE_PATH, 0700, true);
if (!is_dir(KEY_PATH)) mkdir(KEY_PATH, 0700, true);
