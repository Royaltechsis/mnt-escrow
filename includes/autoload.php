<?php
spl_autoload_register(function($class){
    $prefix = 'MNT\\';
    $base = __DIR__ . '/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;

    $relative = substr($class, strlen($prefix));
    $file = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) require $file;
});
