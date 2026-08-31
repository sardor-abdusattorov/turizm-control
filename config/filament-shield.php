<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Dashboard\DashboardHeaderWidget;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;

return [

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => true,
        ],
    ],

    'tenant_model' => null,

    'auth_provider_model' => 'App\\Models\\User',

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    'panel_user' => [
        'enabled' => false,
        'name' => 'panel_user',
    ],

    'permissions' => [
        'separator' => '.',
        'case' => 'snake',
        'generate' => true,
    ],

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,

        'generate' => false,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield',
    ],

    'resources' => [
        'subject' => 'model',
        'manage' => [
            RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
        ],
        'exclude' => [

        ],
    ],

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            Dashboard::class,
        ],
    ],

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            AccountWidget::class,
            FilamentInfoWidget::class,

            DashboardHeaderWidget::class,
        ],
    ],

    'custom_permissions' => [

        'view_all_contracts',

        'approve_contracts',

        'update_approved_contract',

        'export_contract',

        'export_contact',
        'export_payment',

        'export_project',
        'export_sponsor',

        'export_press_tour',

        'view_all_requisitions',
    ],

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    'register_role_policy' => true,

];
