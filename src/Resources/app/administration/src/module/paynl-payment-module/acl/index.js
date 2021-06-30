Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'paynl',
    key: 'paynl_transactions',
    roles: {
        viewer: {
            privileges: [
                'order:read',
                'customer:read',
                'state_machine_state:read',
                'paynl_transactions:read',
            ],
            dependencies: []
        },
    }
});

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'orders',
    key: 'order',
    roles: {
        viewer: {
            privileges: [
                'paynl_transactions:read'
            ],
            dependencies: []
        },
    }
});
