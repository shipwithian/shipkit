<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Where an AI client that registers itself may send the user after they approve it. Anything
    | else is refused at registration, so a site cannot register under a trusted name such as
    | "Claude" to collect consent. Localhost (any port) is allowed for Claude Code and other local
    | clients. Add more, comma-separated with their scheme, in MCP_REDIRECT_DOMAINS, for example
    | "https://app.example.com,https://other.example.com".
    |
    */

    'redirect_domains' => array_values(array_filter([
        'https://claude.ai',
        'https://chatgpt.com',
        'http://localhost',
        ...array_map(trim(...), explode(',', (string) env('MCP_REDIRECT_DOMAINS', ''))),
    ], fn (string $domain): bool => $domain !== '' && $domain !== '*')),

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Desktop clients such as Cursor and VS Code redirect to a private scheme (RFC 8252). Add
    | 'cursor' or 'vscode' here to allow them.
    |
    */

    'custom_schemes' => [],

    /*
    |--------------------------------------------------------------------------
    | Confirmation Lock Wait
    |--------------------------------------------------------------------------
    |
    | How many seconds a repeated confirmed tool call waits for the first one to finish before it
    | answers "still being processed".
    |
    */

    'confirmation_lock_wait_seconds' => 5,

];
