<?php

return [

    'max_gate_regenerations' => 2,

    /*
    | Pipeline IA interne Maestro (PM, UX, QA… via Horizon).
    | Désactivé par défaut : Hermes gère l'exécution via MCP.
    | Activer avec MAESTRO_INTERNAL_PIPELINE=true pour réactiver les workers.
    */
    'internal_pipeline_enabled' => filter_var(env('MAESTRO_INTERNAL_PIPELINE', false), FILTER_VALIDATE_BOOL),

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
        'dev' => 'claude-haiku-4-5',
        'qa' => 'claude-haiku-4-5',
        'pr_expert' => 'claude-haiku-4-5',
        'doc' => 'claude-haiku-4-5',
        'discovery' => 'claude-sonnet-4-6',
    ],

    'default_pipelines' => [
        'feature' => ['pm', 'ux', 'tech_lead', 'security', 'qa', 'pr_expert', 'doc'],
        'bug' => ['tech_lead', 'security', 'qa', 'pr_expert', 'doc'],
        'improvement' => ['pm', 'tech_lead', 'security', 'qa', 'pr_expert', 'doc'],
        'chore' => ['tech_lead', 'pr_expert'],
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

    'role_labels' => [
        'pm' => ['emoji' => '🧠', 'name' => 'Product Manager'],
        'ux' => ['emoji' => '🎨', 'name' => 'UX Designer'],
        'tech_lead' => ['emoji' => '⚙️', 'name' => 'Tech Lead'],
        'security' => ['emoji' => '🔒', 'name' => 'Security'],
        'dev' => ['emoji' => '💻', 'name' => 'Hermes'],
        'qa' => ['emoji' => '🧪', 'name' => 'QA'],
        'pr_expert' => ['emoji' => '📝', 'name' => 'PR Expert'],
        'doc' => ['emoji' => '📚', 'name' => 'Documentation'],
        'discovery' => ['emoji' => '🤖', 'name' => 'Discovery IA'],
    ],

    'builtin_roles' => [
        'pm' => ['runner' => 'api'],
        'ux' => ['runner' => 'api'],
        'tech_lead' => ['runner' => 'api'],
        'security' => ['runner' => 'api'],
        'qa' => ['runner' => 'api'],
        'pr_expert' => ['runner' => 'api', 'post_action' => 'open_pr'],
        'doc' => ['runner' => 'api'],
        'discovery' => ['runner' => 'chat'],
    ],

    'discovery_sources' => [
        'https://news.ycombinator.com/rss',
        'https://www.producthunt.com/feed',
    ],

    'role_max_tokens' => [
        'pm' => 8192,
        'ux' => 8192,
        'tech_lead' => 8192,
        'security' => 8192,
        'qa' => 4096,
        'pr_expert' => 4096,
        'doc' => 2048,
    ],

    'anthropic_timeout' => (int) env('ANTHROPIC_TIMEOUT', 180),

    'anthropic_discovery_timeout' => (int) env('ANTHROPIC_DISCOVERY_TIMEOUT', 180),

    'discovery_max_history' => (int) env('MAESTRO_DISCOVERY_MAX_HISTORY', 10),

    /*
    | Troncature des outputs agents précédents dans les prompts API (~2000 tokens).
    | Réduit latence, timeouts et coût input — même logique que DevPromptBuilder.
    */
    'pipeline_output_max_chars' => (int) env('MAESTRO_PIPELINE_OUTPUT_MAX_CHARS', 8000),

    /*
    | Retry automatique sur erreurs transitoires API (timeout, surcharge).
    | 2 essais max = 1 retry gratuit en cas de pic réseau Anthropic.
    */
    'pipeline_step_job_tries' => (int) env('MAESTRO_PIPELINE_STEP_JOB_TRIES', 2),

    'pipeline_step_job_retry_delay' => (int) env('MAESTRO_PIPELINE_STEP_JOB_RETRY_DELAY', 15),

    'queue_worker_timeout' => (int) env('MAESTRO_QUEUE_WORKER_TIMEOUT', 960),

    'queue_retry_after' => (int) env('MAESTRO_QUEUE_RETRY_AFTER', 960),

    /*
    | Connexion GitHub utilisateur :
    | - auto : token personnel en local, OAuth en production (si configuré)
    | - pat  : token personnel uniquement (idéal en dev sans callback localhost)
    | - oauth : redirection OAuth (nécessite GITHUB_CLIENT_ID/SECRET + callback URL)
    */
    'github_auth' => env('GITHUB_AUTH', 'auto'),

    'github_oauth_in_local' => env('GITHUB_OAUTH_IN_LOCAL', false),

    /*
    | Dépôt template GitHub pour créer de nouveaux projets (format owner/repo).
    | Ex. Thomaspruv/maestro-template — laisser vide pour désactiver l'option wizard.
    */
    'github_template_repo' => env('MAESTRO_GITHUB_TEMPLATE_REPO', ''),

    'mcp' => [
        'resource_url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/mcp',
        'oauth' => [
            'issuer' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
            'access_token_ttl' => (int) env('MAESTRO_MCP_ACCESS_TOKEN_TTL', 3600),
            'refresh_token_ttl' => (int) env('MAESTRO_MCP_REFRESH_TOKEN_TTL', 2592000),
            'scopes' => ['mcp:read', 'mcp:write'],
        ],
    ],

];
