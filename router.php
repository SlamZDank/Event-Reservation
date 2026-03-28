<?php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = __DIR__ . '/public';
$file = $public . $uri;
$mimes = ['css'=>'text/css','js'=>'application/javascript','png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml','woff2'=>'font/woff2'];

if (file_exists($file) && !is_dir($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
        readfile($file);
        exit;
    }
}

chdir($public);
$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $public;
include $public . '/index.php';
