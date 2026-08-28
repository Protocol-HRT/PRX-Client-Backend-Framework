<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\IntegrationServiceProvider;
use App\Providers\TelehealthServiceProvider;
use App\Providers\WorkflowServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    IntegrationServiceProvider::class,
    TelehealthServiceProvider::class,
    WorkflowServiceProvider::class,
];
