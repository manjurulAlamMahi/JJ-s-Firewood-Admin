<?php

/*
|--------------------------------------------------------------------------
| Admin Navigation
|--------------------------------------------------------------------------
|
| Single source of truth for the admin shell navigation. It drives the
| desktop sidebar, the mobile drawer, the mobile bottom tab bar and the
| nav search box, so a link only ever has to be declared once.
|
| Item keys:
|   label     Display text.
|   icon      Name understood by <x-admin-icon />.
|   route     Route name. The item is hidden when the route does not exist,
|             which keeps the menu safe across partially-migrated branches.
|   url       Raw URL. Use instead of `route` for non-named endpoints.
|   params    Query string appended to the resolved route.
|   match     Route-name prefixes that mark this item as active. Defaults to
|             the item's own route name.
|   badge     Key resolved at render time (see partial/nav-badges.blade.php).
|   requires  Table name that must exist for the item to show.
|   children  Sub-items, same shape (one level deep only).
|
*/

return [

    /*
     | Mobile bottom tab bar. Four destinations plus an auto-appended "More"
     | button that opens the drawer. Keep this list at four — five tabs plus
     | More does not fit a 360px viewport at a 44px tap target.
     */
    'primary' => [
        ['label' => 'Home', 'icon' => 'home', 'route' => 'dashboard'],
        ['label' => 'Orders', 'icon' => 'receipt', 'route' => 'order-centre.index', 'match' => ['order-centre.', 'order.', 'orders.']],
        ['label' => 'Products', 'icon' => 'box', 'route' => 'product-centre.index', 'match' => ['product-centre.', 'products.', 'category.', 'subcategory.']],
        ['label' => 'Customers', 'icon' => 'users', 'route' => 'customer-management.index', 'match' => ['customer-management.', 'customers.']],
    ],

    'groups' => [

        [
            'label' => 'Operations',
            'items' => [
                [
                    'label' => 'Dashboard',
                    'icon' => 'home',
                    'route' => 'dashboard',
                ],
                [
                    'label' => 'Order Centre',
                    'icon' => 'receipt',
                    'route' => 'order-centre.index',
                    'match' => ['order-centre.', 'orders.'],
                ],
                [
                    'label' => 'Setmore Matching',
                    'icon' => 'calendar-check',
                    'route' => 'setmore-reviews.index',
                    'match' => ['setmore-reviews.'],
                    'requires' => 'setmore_webhook_events',
                    'badge' => 'setmore_pending',
                ],
            ],
        ],

        [
            'label' => 'Catalogue',
            'items' => [
                /*
                 | Product Centre is the front door for products, categories and
                 | subcategories — it holds all three as tabs. The individual
                 | CRUD screens stay reachable for adding new records, but the
                 | duplicated "Manage" links were removed: two routes to the
                 | same list is the sort of thing that makes an admin panel feel
                 | bigger than it is.
                 */
                [
                    'label' => 'Product Centre',
                    'icon' => 'grid',
                    'route' => 'product-centre.index',
                    'match' => [
                        'product-centre.', 'products.', 'category.', 'subcategory.',
                        'bundles.', 'coupon.',
                    ],
                ],
                /*
                 | Bundles and Coupons live as tabs inside Product Centre, so
                 | they are deliberately absent here — the routes still exist
                 | and the create screens are linked from those tabs.
                 */
                [
                    'label' => 'Reviews',
                    'icon' => 'star',
                    'route' => 'review.index',
                    'match' => ['review.'],
                ],
            ],
        ],

        [
            'label' => 'Customers',
            'items' => [
                [
                    'label' => 'Customer Centre',
                    'icon' => 'users',
                    'match' => ['customer-management.', 'customers.'],
                    'children' => [
                        ['label' => 'All Customers', 'route' => 'customer-management.index'],
                        ['label' => 'Add Customer', 'route' => 'customer-management.create'],
                        ['label' => 'Export Customers', 'route' => 'customer-management.export'],
                    ],
                ],
                [
                    'label' => 'Notification Centre',
                    'icon' => 'bell',
                    'url' => '/backend/notification-centre',
                    'match' => ['backend.notification-centre.', 'notifications.'],
                ],
                [
                    'label' => 'Weather Campaigns',
                    'icon' => 'cloud',
                    'route' => 'weather-campaigns.index',
                    'match' => ['weather-campaigns.'],
                ],
                [
                    'label' => 'Contact Requests',
                    'icon' => 'mail',
                    'route' => 'contact-us.index',
                    'match' => ['contact-us.'],
                ],
                [
                    'label' => 'Reports',
                    'icon' => 'flag',
                    'route' => 'report.index',
                    'match' => ['report.'],
                ],
            ],
        ],

        [
            'label' => 'Giveaways',
            'items' => [
                /*
                 | Giveaway Centre already carries Giveaways, Live Draws, Entry
                 | Packages and Permanent Entries as tabs. Listing them again
                 | here just made the menu longer than the feature.
                 */
                [
                    'label' => 'Giveaway Centre',
                    'icon' => 'gift',
                    'route' => 'giveaway-centre.index',
                    'match' => ['giveaway-centre.', 'giveaways.', 'entry-package.', 'manage-entries.'],
                ],
            ],
        ],

        [
            'label' => 'Delivery',
            'items' => [
                [
                    'label' => 'Delivery Calculator',
                    'icon' => 'calculator',
                    'route' => 'delivery-calculator.index',
                    'match' => ['delivery-calculator.'],
                ],
                [
                    'label' => 'Delivery Types',
                    'icon' => 'truck',
                    'match' => ['delivery-type.'],
                    'children' => [
                        ['label' => 'Manage Types', 'route' => 'delivery-type.index'],
                        ['label' => 'Add Type', 'route' => 'delivery-type.create'],
                    ],
                ],
                [
                    'label' => 'Postcode Zones',
                    'icon' => 'map-pin',
                    'match' => ['zip-code.'],
                    'children' => [
                        ['label' => 'Manage Zones', 'route' => 'zip-code.index'],
                        ['label' => 'Create Zone', 'route' => 'zip-code.create-group'],
                    ],
                ],
            ],
        ],

        [
            'label' => 'Content',
            'items' => [
                [
                    'label' => 'Banners',
                    'icon' => 'image',
                    'match' => ['banner.'],
                    'children' => [
                        ['label' => 'Manage Banners', 'route' => 'banner.index'],
                        ['label' => 'Add Banner', 'route' => 'banner.create'],
                    ],
                ],
                [
                    'label' => 'Blogs',
                    'icon' => 'pen',
                    'route' => 'blog.index',
                    'match' => ['blog.'],
                ],
                [
                    'label' => 'Tips & Guides',
                    'icon' => 'book',
                    'route' => 'tips-and-guides.index',
                    'match' => ['tips-and-guides.'],
                ],
                [
                    'label' => 'FAQs',
                    'icon' => 'help',
                    'route' => 'faqs.index',
                    'match' => ['faqs.'],
                ],
                [
                    'label' => 'Wood Education',
                    'icon' => 'flame',
                    'route' => 'wood-education.index',
                    'match' => ['wood-education.'],
                ],
                [
                    'label' => 'Dynamic Pages',
                    'icon' => 'file',
                    'match' => ['dynamicpage.'],
                    'children' => [
                        ['label' => 'Manage Pages', 'route' => 'dynamicpage.index'],
                        ['label' => 'Add Page', 'route' => 'dynamicpage.create'],
                    ],
                ],
                [
                    'label' => 'Service Categories',
                    'icon' => 'wrench',
                    'match' => ['services.category.'],
                    'children' => [
                        ['label' => 'Manage Categories', 'route' => 'services.category.index'],
                        ['label' => 'Add Category', 'route' => 'services.category.create'],
                    ],
                ],
                [
                    'label' => 'App Info',
                    'icon' => 'info',
                    'route' => 'info.index',
                    'match' => ['info.'],
                ],
            ],
        ],

        [
            'label' => 'Payments',
            'items' => [
                [
                    'label' => 'Payment Methods',
                    'icon' => 'credit-card',
                    'route' => 'payment-methods.index',
                    'match' => ['payment-methods.'],
                ],
                [
                    'label' => 'Bank Transfers',
                    'icon' => 'bank',
                    'route' => 'bank_transfer.index',
                    'match' => ['bank_transfer.'],
                ],
                [
                    'label' => 'Payment Settings',
                    'icon' => 'sliders',
                    'route' => 'payment-settings.edit',
                    'match' => ['payment-settings.'],
                ],
            ],
        ],

        [
            'label' => 'System',
            'items' => [
                [
                    'label' => 'My Profile',
                    'icon' => 'user',
                    'route' => 'profile.index',
                    'match' => ['profile.'],
                ],
                [
                    'label' => 'System Settings',
                    'icon' => 'settings',
                    'route' => 'system-settings.edit',
                    'match' => ['system-settings.'],
                ],
                [
                    'label' => 'Mail Settings',
                    'icon' => 'mail',
                    'route' => 'settings.mail',
                    'match' => ['settings.mail'],
                ],
                [
                    'label' => 'Admin Users',
                    'icon' => 'shield',
                    'match' => ['users.', 'role.'],
                    'children' => [
                        ['label' => 'Manage Admins', 'route' => 'users.index'],
                        ['label' => 'Add Admin', 'route' => 'users.create'],
                        ['label' => 'Roles & Permissions', 'route' => 'role.index'],
                    ],
                ],
            ],
        ],

    ],
];
