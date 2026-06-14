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
        'dev' => 'claude-haiku-4-5',
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

    'agent_max_tokens' => [
        'pm'        => 8192,
        'ux'        => 8192,
        'tech_lead' => 8192,
        'security'  => 8192,
        'qa'        => 4096,
        'pr_expert' => 4096,
        'doc'       => 2048,
    ],

    'anthropic_timeout' => (int) env('ANTHROPIC_TIMEOUT', 180),

    'anthropic_discovery_timeout' => (int) env('ANTHROPIC_DISCOVERY_TIMEOUT', 180),

    /*
    | Troncature des outputs agents précédents dans les prompts API (~2000 tokens).
    | Réduit latence, timeouts et coût input — même logique que DevPromptBuilder.
    */
    'agent_output_max_chars' => (int) env('MAESTRO_AGENT_OUTPUT_MAX_CHARS', 8000),

    /*
    | Retry automatique sur erreurs transitoires API (timeout, surcharge).
    | 2 essais max = 1 retry gratuit en cas de pic réseau Anthropic.
    */
    'agent_job_tries' => (int) env('MAESTRO_AGENT_JOB_TRIES', 2),

    'agent_job_retry_delay' => (int) env('MAESTRO_AGENT_JOB_RETRY_DELAY', 15),

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

    'claude_code_path' => env('CLAUDE_CODE_PATH'),

    /*
    | Runner Dev Agent : cli (Claude Code local) ou api (Anthropic tool use, portable).
    */
    'dev_runner' => env('MAESTRO_DEV_RUNNER', 'cli'),

    'dev_claude_timeout' => (int) env('MAESTRO_DEV_CLAUDE_TIMEOUT', 900),

    'dev_api_timeout' => (int) env('MAESTRO_DEV_API_TIMEOUT', 120),

    'dev_api_max_iterations' => (int) env('MAESTRO_DEV_API_MAX_ITERATIONS', 40),

];
