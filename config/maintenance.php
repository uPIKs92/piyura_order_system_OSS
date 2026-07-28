<?php

return [
  'allowed_ips' => array_values(array_filter(array_map(
      'trim',
      explode(',', (string) env('MAINTENANCE_ALLOWED_IPS', ''))
  ))),
];
