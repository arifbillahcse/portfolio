<?php

return [
    'token_sha256' => env('BLOG_MCP_TOKEN_SHA256', ''),
    'image_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('BLOG_MCP_IMAGE_HOSTS', ''))))),
    'max_image_bytes' => 4 * 1024 * 1024,
    'max_request_bytes' => 6 * 1024 * 1024,
];
