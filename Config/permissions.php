<?php

return [
    'name' => 'warmup',
    'categories' => [
        'plugin:warmup' => [
            'domains' => [
                'view' => 'plugin:warmup:domains:view',
                'create' => 'plugin:warmup:domains:create',
                'edit' => 'plugin:warmup:domains:edit',
                'delete' => 'plugin:warmup:domains:delete'
            ],
            'campaigns' => [
                'view' => 'plugin:warmup:campaigns:view',
                'create' => 'plugin:warmup:campaigns:create',
                'edit' => 'plugin:warmup:campaigns:edit',
                'delete' => 'plugin:warmup:campaigns:delete'
            ],
            'templates' => [
                'view' => 'plugin:warmup:templates:view',
                'create' => 'plugin:warmup:templates:create',
                'edit' => 'plugin:warmup:templates:edit',
                'delete' => 'plugin:warmup:templates:delete'
            ],
            'contacts' => [
                'view' => 'plugin:warmup:contacts:view'
            ],
            'reports' => [
                'view' => 'plugin:warmup:reports:view'
            ]
        ]
    ]
];
