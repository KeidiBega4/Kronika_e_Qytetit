<?php
session_start();
require_once __DIR__ . '/../auth.php';

function require_admin(): void {
    require_role(['admin']);
}

function require_journalist(): void {
    require_role(['journalist', 'admin']);
}
