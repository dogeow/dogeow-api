<?php

use App\Providers\AppServiceProvider;
use App\Providers\BroadcastServiceProvider;
use App\Providers\PolicyServiceProvider;

return [
    AppServiceProvider::class,
    PolicyServiceProvider::class,
    BroadcastServiceProvider::class,
];
