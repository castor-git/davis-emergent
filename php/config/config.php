<?php
return [
    'app_name' => getenv('APP_NAME') ?: 'DAVISPORN',
    'tagline' => 'Premium HD adult tube — the finest network.',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: 'davisporn',
        'user' => getenv('DB_USER') ?: 'dav',
        'pass' => getenv('DB_PASS') ?: 'davpass',
    ],
    'admin' => [
        'user' => getenv('ADMIN_USER') ?: 'admin',
        'pass' => getenv('ADMIN_PASS') ?: 'admin123',
    ],
    'cache_ttl' => 900,
    'per_page' => 24,
    'sources' => [
        'demo' => [
            'enabled' => true,
            'label' => 'Davis Demo Network',
            'adapter' => 'DemoAdapter',
        ],
        'upornia_csv' => [
            'enabled' => false,
            'label' => 'Upornia XML Feed',
            'adapter' => 'UporniaCsvAdapter',
            'feed_url' => getenv('UPORNIA_CSV_URL') ?: '',
            'deleted_feed_url' => getenv('UPORNIA_DELETED_URL') ?: '',
            'import_limit' => 10000,
        ],
        'xvideos_csv' => [
            'enabled' => false,
            'label' => 'XVideos CSV Import',
            'adapter' => 'XVideosCsvAdapter',
            'feed_url' => getenv('XVIDEOS_CSV_URL') ?: 'https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-export-week.csv.gz',
            'deleted_feed_url' => getenv('XVIDEOS_DELETED_CSV_URL')
                ?: 'https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-deleted-week.csv.gz',
            'deleted_full_feed_url' => getenv('XVIDEOS_DELETED_FULL_CSV_URL')
                ?: 'https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-deleted-full.csv.gz',
            'import_limit' => 500,
        ],
        'xnxx_rapidapi' => [
            'enabled' => false,
            'label' => 'XNXX (RapidAPI)',
            'adapter' => 'XnxxRapidApiAdapter',
            'api_key' => getenv('RAPIDAPI_KEY') ?: '',
            'host' => getenv('RAPIDAPI_HOST') ?: 'porn-xnxx-api.p.rapidapi.com',
            'queries' => 'milf,teen,anal,amateur,lesbian,asian,latina,ebony,big tits,blowjob,hardcore,pov',
            'import_limit' => 400,
        ],
    ],
];
