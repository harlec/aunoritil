<?php
require_once __DIR__ . '/../../config.php';
Auth::requireLogin();
redirect(BASE_URL . '/modules/proveedores/proveedores.php');
