Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'paynl',
    key: 'paynl',
    roles: {
        viewer: {
            privileges: [
                'paynl:read',
                'sales_channel:read',
                'system_config:read',
            ],
            dependencies: []
        },
        editor: {
            privileges: [
                'media:create',
                'media:update',
                'media:delete',
                'media:read',
                'media_folder:update',
                'media_folder:read',
                'media_default_folder:read',
                'media_default_folder:update',
                'media_folder_configuration:read',
                'payment_method:create',
                'payment_method:update',
                'paynl:update',
                'paynl:create',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
                'sales_channel:create',
                'sales_channel:update',
                'sales_channel_payment_method:create',
                'sales_channel_payment_method:delete',
                'sales_channel_payment_method:read',
                'sales_channel_payment_method:update',
            ],
            dependencies: [
                'paynl.viewer'
            ]
        }
    }
});
