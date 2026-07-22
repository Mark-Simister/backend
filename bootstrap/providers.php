<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\ProxyTrustServiceProvider::class,   // R1: reverse-proxy trust, fail closed
];
