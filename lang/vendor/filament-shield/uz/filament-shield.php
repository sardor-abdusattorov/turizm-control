<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Nomi',
    'column.guard_name' => 'Guard nomi',
    'column.team' => 'Jamoa',
    'column.roles' => 'Rollar',
    'column.permissions' => 'Ruxsatlar',
    'column.updated_at' => 'Yangilangan',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Nomi',
    'field.guard_name' => 'Guard nomi',
    'field.permissions' => 'Ruxsatlar',
    'field.team' => 'Jamoa',
    'field.team.placeholder' => 'Jamoani tanlang ...',
    'field.select_all.name' => 'Hammasini tanlash',
    'field.select_all.message' => 'Ushbu rol uchun barcha ruxsatlarni yoqadi/o‘chiradi',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Filament Shield',
    'nav.role.label' => 'Rollar',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Rol',
    'resource.label.roles' => 'Rollar',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Mavjudliklar',
    'resources' => 'Resurslar',
    'widgets' => 'Vidjetlar',
    'pages' => 'Sahifalar',
    'custom' => 'Maxsus ruxsatlar',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'Sizda kirish uchun ruxsat yo‘q',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Ko‘rish',
        'view_any' => 'Har qanday ko‘rish',
        'create' => 'Yaratish',
        'update' => 'Yangilash',
        'delete' => 'O‘chirish',
        'delete_any' => 'Har qanday o‘chirish',
        'force_delete' => 'Majburiy o‘chirish',
        'force_delete_any' => 'Har qanday majburiy o‘chirish',
        'restore' => 'Tiklash',
        'reorder' => 'Tartibni o‘zgartirish',
        'restore_any' => 'Har qanday tiklash',
        'replicate' => 'Nusxalash',
    ],
];
