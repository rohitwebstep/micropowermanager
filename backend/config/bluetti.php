<?php

/*
    micropowermanager-main\backend\config\bluetti.php
*/

return [
    'app_key'    => env('BLUETTI_APP_KEY'),
    'app_secret' => env('BLUETTI_APP_SECRET'),
    'base_url'   => env('BLUETTI_BASE_URL', 'https://open.bluetti.com'),
];