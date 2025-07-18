<?php return [
  'internal_traffic_statistics' => [
    'label' => 'Internal Traffic Statistics',
    'permission_description' => 'Purge the Internal Traffic Statistics data',
    'permission_label' => 'Manage Internal Traffic Statistics settings',
    'hint' => 'The Internal Traffic Statistics feature logs pageviews, IP addresses, and other basic anonymous user data into the database.',
    'disabled' => 'Internal Traffic Statistics is disabled. To enable it, edit configuration in config/cms.php.',
    'enabled' => 'Internal Traffic Statistics is currently enabled. You can adjust the retention period and time zone, or disable this feature, by editing the settings in config/cms.php.',
    'purging' => 'Purging the data...',
    'purge_button' => 'Purge Data',
    'purge_data_confirm' => 'Do you really want to purge the Internal Traffic Statistics data?',
    'retention' => 'Data retention period',
    'timezone' => 'Time zone',
    'retention_mon' => ':retention month(s)',
    'retention_indefinite' => 'Indefinite',
    'purge_success' => 'Internal Traffic Statistics data purged',
  ],
];
