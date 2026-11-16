<?php

$appUrl = env('APP_URL', 'http://127.0.0.1:8000');
$appPort = parse_url($appUrl, PHP_URL_PORT) ?: (parse_url($appUrl, PHP_URL_SCHEME) === 'https' ? 443 : 80);

return [
    // Destino fijo del despliegue: el cliente no puede enviar hosts ni puertos.
    'scan_host' => env('INFRASTRUCTURE_SCAN_HOST', parse_url($appUrl, PHP_URL_HOST) ?: '127.0.0.1'),
    'scan_ports' => array_values(array_unique(array_map('intval', explode(',', env('INFRASTRUCTURE_SCAN_PORTS', $appPort.',22,443,3306'))))),
    'scan_timeout' => 1.0,
    'backup_directory' => env('PREVENTITI_BACKUP_DIRECTORY', storage_path('backups/preventiti')),
    'backup_files_directory' => storage_path('app'),
    'mariadb_dump_binary' => env('MARIADB_DUMP_BINARY'),
    'mariadb_backup_defaults' => env('MARIADB_BACKUP_DEFAULTS_FILE'),
];
