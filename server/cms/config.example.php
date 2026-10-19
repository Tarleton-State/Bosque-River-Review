<?php
declare(strict_types=1);
// Put the completed file OUTSIDE the document root. No secrets belong in Git.
return [
    'acl_file' => '/absolute/path/to/applications/auth-config.php',
    'auth_helper_file' => '/absolute/path/to/applications/auth-helper.php',
    'database' => '/absolute/private/path/cms-sessions.sqlite',
    'sites' => [
        'bosque-river-review' => [
            'origin' => 'https://tarleton-state.github.io',
            'repo' => 'Tarleton-State/Bosque-River-Review',
            'branch' => 'main',
            'acl' => 'cms_bosque_river_review',
            'schema_file' => '/absolute/private/path/bosque-cms-schema.json',
            'github_token_env' => 'CMS_BOSQUE_GITHUB_TOKEN',
        ],
    ],
];
