<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\SeederGuardServiceProvider;

return [
    AppServiceProvider::class,
    SeederGuardServiceProvider::class,
    AdminPanelProvider::class,
];
