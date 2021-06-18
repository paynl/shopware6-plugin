Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'paynl',
    key: 'paynl_transactions',
    roles: {
        viewer: {
            privileges: [
                'paynl_transactions:read',
            ],
            dependencies: []
        },
    }
});
