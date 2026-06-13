<?php

return [

    'repos_path' => env('MAESTRO_REPOS_PATH', storage_path('repos')),

    'max_dev_attempts' => 3,

    'max_gate_regenerations' => 2,

    'model_prices' => [
        'claude-sonnet-4-6' => ['input' => 0.000003, 'output' => 0.000015, 'cache' => 0.0000003],
        'claude-haiku-4-5' => ['input' => 0.00000025, 'output' => 0.00000125, 'cache' => 0.000000025],
        'claude-opus-4-8' => ['input' => 0.000015, 'output' => 0.000075, 'cache' => 0.0000015],
    ],

    'avg_output_tokens' => [
        'pm' => 800,
        'ux' => 600,
        'tech_lead' => 1000,
        'security' => 300,
        'dev' => 3000,
        'qa' => 800,
        'pr_expert' => 500,
        'doc' => 300,
        'discovery' => 1200,
    ],

    'project_context_tokens' => 2000,

    'default_models' => [
        'pm' => 'claude-sonnet-4-6',
        'ux' => 'claude-sonnet-4-6',
        'tech_lead' => 'claude-sonnet-4-6',
        'security' => 'claude-haiku-4-5',
        'dev' => 'claude-sonnet-4-6',
        'qa' => 'claude-haiku-4-5',
        'pr_expert' => 'claude-haiku-4-5',
        'doc' => 'claude-haiku-4-5',
        'discovery' => 'claude-sonnet-4-6',
    ],

    'default_pipelines' => [
        'feature' => ['pm', 'ux', 'tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc'],
        'bug' => ['tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc'],
        'improvement' => ['pm', 'tech_lead', 'security', 'dev', 'qa', 'pr_expert', 'doc'],
        'chore' => ['tech_lead', 'dev', 'pr_expert'],
    ],

    'default_gate_config' => [
        'feature' => ['gate_specs' => true, 'gate_tech' => true, 'gate_merge' => true],
        'bug' => ['gate_specs' => false, 'gate_tech' => false, 'gate_merge' => true],
        'improvement' => ['gate_specs' => false, 'gate_tech' => false, 'gate_merge' => true],
        'chore' => ['gate_specs' => false, 'gate_tech' => false, 'gate_merge' => false],
    ],

    'default_modes' => [
        'feature' => 'manual',
        'bug' => 'semi_auto',
        'improvement' => 'semi_auto',
        'chore' => 'full_auto',
    ],

    'agent_labels' => [
        'pm' => ['emoji' => '🧠', 'name' => 'PM Agent'],
        'ux' => ['emoji' => '🎨', 'name' => 'UX Agent'],
        'tech_lead' => ['emoji' => '⚙️', 'name' => 'Tech Lead'],
        'security' => ['emoji' => '🔒', 'name' => 'Security'],
        'dev' => ['emoji' => '💻', 'name' => 'Dev Agent'],
        'qa' => ['emoji' => '🧪', 'name' => 'QA Agent'],
        'pr_expert' => ['emoji' => '📝', 'name' => 'PR Expert'],
        'doc' => ['emoji' => '📚', 'name' => 'Doc Agent'],
        'discovery' => ['emoji' => '🤖', 'name' => 'Discovery IA'],
    ],

    'builtin_agents' => [
        'pm' => ['runner' => 'api'],
        'ux' => ['runner' => 'api'],
        'tech_lead' => ['runner' => 'api'],
        'security' => ['runner' => 'api'],
        'dev' => ['runner' => 'dev', 'queue' => 'dev-agent'],
        'qa' => ['runner' => 'api'],
        'pr_expert' => ['runner' => 'api', 'post_action' => 'open_pr'],
        'doc' => ['runner' => 'api'],
        'discovery' => ['runner' => 'chat'],
    ],

    'discovery_sources' => [
        'https://news.ycombinator.com/rss',
        'https://www.producthunt.com/feed',
    ],

    'anthropic_timeout' => 60,

    'anthropic_discovery_timeout' => 180,

    /*
    | Connexion GitHub utilisateur :
    | - auto : token personnel en local, OAuth en production (si configuré)
    | - pat  : token personnel uniquement (idéal en dev sans callback localhost)
    | - oauth : redirection OAuth (nécessite GITHUB_CLIENT_ID/SECRET + callback URL)
    */
    'github_auth' => env('GITHUB_AUTH', 'auto'),

    'github_oauth_in_local' => env('GITHUB_OAUTH_IN_LOCAL', false),

];
