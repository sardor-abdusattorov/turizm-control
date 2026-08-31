<?php

use App\Filament\Resources\ActivityResource;
use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\ContractTypes\ContractTypeResource;
use App\Filament\Resources\Orders\CommitteeOrderResource;
use App\Filament\Resources\Orders\PrCenterOrderResource;
use App\Filament\Resources\Projects\InternalProjectResource;
use App\Filament\Resources\Projects\InternationalProjectResource;
use App\Models\Approval;
use App\Models\Contact;
use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Order;
use App\Models\Position;
use App\Models\Project;
use App\Models\Requisition;
use App\Models\Sponsor;
use App\Models\User;
use MrAdder\FilamentLogger\Loggers\AccessLogger;
use MrAdder\FilamentLogger\Loggers\ModelLogger;
use MrAdder\FilamentLogger\Loggers\NotificationLogger;
use MrAdder\FilamentLogger\Loggers\ResourceLogger;
use MrAdder\FilamentLogger\Support\ActivityEvents;
use MrAdder\FilamentLogger\Support\ActivityReviewPlaybookManager;

return [
    'datetime_format' => 'd/m/Y H:i:s',
    'date_format' => 'd/m/Y',
    'redacted_placeholder' => '[REDACTED]',

    'authorization' => [
        'strict' => true,
        'sensitive_ability' => 'viewSensitiveData',
    ],

    'sensitive_keys' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'client_secret',
        'api_key',
        'private_key',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_codes',
    ],

    'diff' => [
        'collapse_after' => 120,
        'pretty_print_json' => true,
    ],

    'risk' => [
        'high' => [
            'events' => [
                'Deleted',
                ActivityEvents::FORCE_DELETED,
                ActivityEvents::FAILED_LOGIN,
                'Lockout',
            ],
            'change_keys' => [
                'role',
                'role_id',
                'roles',
                'permission',
                'permissions',
            ],
        ],
    ],

    'pruning' => [
        'days' => 365,
        'only' => [],
        'except' => [],
    ],

    'exports' => [
        'enabled' => true,
        'chunk_size' => 500,
        'columns' => [
            'id',
            'log_name',
            'event',
            'description',
            'subject_type',
            'subject_id',
            'causer_type',
            'causer_id',
            'causer_name',
            'risk',
            'tags',
            'properties',
            'created_at',
        ],
    ],

    'dashboard' => [
        'enabled' => true,
        'lookback_days' => 30,
        'top_limit' => 5,
    ],

    'activity_playbooks' => ActivityReviewPlaybookManager::DEFAULT_PLAYBOOKS,

    'activity_filters' => [
        'date_presets' => [
            'today' => 'Today',
            'last_24_hours' => 'Last 24 Hours',
            'last_7_days' => 'Last 7 Days',
            'last_30_days' => 'Last 30 Days',
            'this_month' => 'This Month',
        ],
        'saved' => [
            'all' => [
                'label' => 'All Activity',
                'icon' => 'heroicon-o-bars-3-bottom-left',
            ],
            'high_risk' => [
                'label' => 'High Risk',
                'icon' => 'heroicon-o-shield-exclamation',
                'risk' => ['high'],
            ],
            'destructive' => [
                'label' => 'Deletes',
                'icon' => 'heroicon-o-trash',
                'events' => ['Deleted', ActivityEvents::FORCE_DELETED],
            ],
            'auth_issues' => [
                'label' => 'Auth Issues',
                'icon' => 'heroicon-o-lock-closed',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN, 'Lockout'],
            ],
            'failed_logins' => [
                'label' => 'Failed Logins',
                'icon' => 'heroicon-o-exclamation-triangle',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN],
                'date_preset' => 'last_7_days',
            ],
            'destructive_recent' => [
                'label' => 'Recent Destructive',
                'icon' => 'heroicon-o-fire',
                'events' => ['Deleted', ActivityEvents::FORCE_DELETED],
                'date_preset' => 'last_7_days',
            ],
            'auth_anomalies' => [
                'label' => 'Auth Anomalies',
                'icon' => 'heroicon-o-finger-print',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN, 'Lockout', 'Two Factor Recovery'],
                'date_preset' => 'last_30_days',
            ],
        ],
    ],

    'alerts' => [
        'enabled' => false,
        'cache_store' => null,
        'default_channels' => ['mail'],
        'mail' => [
            'to' => [],
        ],
        'slack' => [
            'webhook_url' => null,
        ],
        'discord' => [
            'webhook_url' => null,
        ],
        'rules' => [
            'destructive_activity' => [
                'enabled' => true,
                'label' => 'Destructive activity detected',
                'channels' => ['mail', 'slack', 'discord'],
                'events' => ['Deleted', ActivityEvents::FORCE_DELETED],
            ],
            'role_changes' => [
                'enabled' => true,
                'label' => 'Role or permission change detected',
                'channels' => ['mail', 'slack', 'discord'],
                'risk_reasons' => ['role_change'],
            ],
            'failed_login_spike' => [
                'enabled' => true,
                'label' => 'Repeated failed login attempts detected',
                'channels' => ['mail', 'slack', 'discord'],
                'type' => 'threshold',
                'log_names' => ['Access'],
                'events' => [ActivityEvents::FAILED_LOGIN],
                'threshold' => 5,
                'window_minutes' => 10,
            ],
        ],
    ],

    'custom_events' => [
        'default_log_name' => 'Custom',
        'color' => 'primary',
    ],

    'activity_resource' => ActivityResource::class,
    'scoped_to_tenant' => false,
    'navigation_sort' => 999,

    'resources' => [
        'enabled' => true,
        'log_name' => 'Resource',
        'logger' => ResourceLogger::class,
        'color' => 'success',

        'exclude' => [
            // Contracts, Orders and Projects are already tracked
            // by the ModelLogger via Eloquent events; logging the Filament UI
            // actions on top of that produced duplicate Created / Updated
            // entries in the Execution history.
            ContractResource::class,
            ContractTypeResource::class,
            PrCenterOrderResource::class,
            CommitteeOrderResource::class,
            InternalProjectResource::class,
            InternationalProjectResource::class,
        ],
        'ignore' => [
            'updated_at',
            'remember_token',
        ],
        'ignore_for_models' => [
            // App\Models\User::class => ['last_seen_at', 'login_count'],
        ],
        'ignore_for_resources' => [
            // App\Filament\Resources\UserResource::class => ['last_seen_at', 'login_count'],
        ],
        'cluster' => null,
        'navigation_group' => 'Settings',
    ],

    'access' => [
        'enabled' => true,
        'logger' => AccessLogger::class,
        'color' => 'danger',
        'log_name' => 'Access',
        'guards' => null,
        'store_ip' => true,
        'anonymize_ip' => true,
        'redact_ip_for_unauthorized_viewers' => false,
        'store_user_agent' => true,
        'user_agent_max_length' => 255,
        'identifier_keys' => [
            'email',
            'username',
            'login',
        ],
        'events' => [
            'login' => true,
            'logout' => true,
            'failed' => true,
            'lockout' => true,
            'password_reset' => true,
            'two_factor_recovery' => true,
        ],
    ],

    'notifications' => [
        'enabled' => true,
        'logger' => NotificationLogger::class,
        'color' => null,
        'log_name' => 'Notification',
        'log_recipient' => false,
        'mask_recipient' => true,
    ],

    'models' => [
        'enabled' => true,
        'log_name' => 'Model',
        'color' => 'warning',
        'logger' => ModelLogger::class,
        'ignore' => [
            'updated_at',
            'remember_token',
        ],
        'ignore_for' => [
            User::class => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
            // Bookkeeping columns that change as a side effect of an action
            // that is already logged elsewhere — the document build after
            // create (document_file/key), the workflow transitions which log
            // their own "Submitted/Approved/…" entries (status, signed_at),
            // and PDF generation (pdf_file). Ignoring them stops a duplicate
            // "Contract Updated" landing next to the real entry.
            Contract::class => ['document_file', 'document_key', 'pdf_file', 'signed_at', 'status'],
            Requisition::class => ['submitted_at'],
        ],
        'register' => [
            Contract::class,
            ContractApprover::class,
            ContractType::class,
            User::class,
            Contact::class,
            Department::class,
            Position::class,
            Currency::class,
            Order::class,
            Project::class,
            Requisition::class,
            Approval::class,
            Sponsor::class,
        ],
    ],

    'custom' => [
        [
            'log_name' => 'Workflow',
            'color' => 'info',
        ],
        [
            'log_name' => 'Document',
            'color' => 'primary',
        ],
    ],
];
