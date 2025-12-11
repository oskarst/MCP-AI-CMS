<?php
/**
 * MCP Tools Definition
 *
 * Defines all available MCP tools with their descriptions
 */

function getMCPTools() {
    return [
        'list_pages' => 'List all pages in the CMS',
        'create_page' => 'Create a new page from HTML content',
        'read_page' => 'Read the full content of a page',
        'delete_page' => 'Delete a page permanently',
        'duplicate_page' => 'Duplicate an existing page',
        'publish_page' => 'Publish a draft page to live',
        'discard_draft' => 'Discard a draft and revert to live version',
        'list_blocks' => 'List all blocks in a specific page',
        'read_block' => 'Read the content of a specific block',
        'update_block' => 'Update the content of a block',
        'insert_block' => 'Insert a new block into a page',
        'search_blocks' => 'Search for text across all blocks',
        'find_and_replace_block_content' => 'Find and replace text in block content',
        'search_in_page' => 'Search for text within a specific page',
        'get_page_region' => 'Get a region of page content between markers',
        'update_page_region' => 'Update a region of page content',
        'list_backups' => 'List all backups for a page',
        'restore_backup' => 'Restore a page from a backup',
        'list_posts' => 'List all blog posts in a collection',
        'create_post' => 'Create a new blog post',
        'read_post' => 'Read a blog post',
        'publish_post' => 'Publish a draft blog post',
        'unpublish_post' => 'Unpublish a blog post',
        'read_post_block' => 'Read a specific block from a blog post',
        'update_post_block' => 'Update a block in a blog post',
        'upload_file' => 'Upload a file to the server',
        'upload_image' => 'Upload and process an image',
        'get_usage_tips' => 'Get usage tips and best practices for the MCP API'
    ];
}

/**
 * Get MCP tools with full JSON Schema definitions for JSON-RPC 2.0 clients (Claude Code)
 */
function getMCPToolsWithSchema() {
    return [
        'list_pages' => [
            'description' => 'List all available page_ids in the CMS. PRIMARY DISCOVERY TOOL: Use this FIRST to identify the correct page_id when the user references a page in natural language. TIP: If user wants to edit specific text, skip this tool and go directly to search_blocks.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'required' => []
            ]
        ],
        'list_blocks' => [
            'description' => 'List all CMS blocks on a page (returns metadata only: name, role, custom). Use this BEFORE editing to understand page structure.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID (e.g., "about", "about/team"). For homepage use: "" or "/"']
                ],
                'required' => ['page_id']
            ]
        ],
        'search_blocks' => [
            'description' => 'PRIMARY SEARCH TOOL - Search for text inside CMS blocks across all pages. Use this FIRST when looking for any user-specified text. Returns block_name, page_id, and content preview.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'search_text' => ['type' => 'string', 'description' => 'Text to search for in block content'],
                    'search_mode' => ['type' => 'string', 'enum' => ['case_insensitive', 'case_sensitive', 'html_insensitive'], 'description' => 'Search mode (default: case_insensitive)']
                ],
                'required' => ['search_text']
            ]
        ],
        'read_block' => [
            'description' => 'Read a specific CMS block\'s content from a page. Use after identifying the block via search_blocks or list_blocks.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID. For homepage use: "" or "/"'],
                    'name' => ['type' => 'string', 'description' => 'Block name']
                ],
                'required' => ['page_id', 'name']
            ]
        ],
        'update_block' => [
            'description' => 'Update a single CMS block\'s content. Creates a DRAFT. After editing, provide a CLICKABLE markdown link for preview: [Preview Draft](http://localhost:2222/cms/admin/preview.php?page_id={page_id}&draft=1) and ask user to publish using publish_page tool.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID. For homepage use: "" or "/"'],
                    'name' => ['type' => 'string', 'description' => 'Block name'],
                    'content' => ['type' => 'string', 'description' => 'New block content (HTML)'],
                    'custom' => ['type' => 'boolean', 'description' => 'Whether this block is a custom per-page override']
                ],
                'required' => ['page_id', 'name', 'content']
            ]
        ],
        'find_and_replace_block_content' => [
            'description' => 'Find and replace text inside a CMS block. PREFERRED for small edits. Creates a DRAFT. After editing, provide a CLICKABLE markdown link for preview: [Preview Draft](http://localhost:2222/cms/admin/preview.php?page_id={page_id}&draft=1) and ask user to publish using publish_page tool.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID. For homepage use: "" or "/"'],
                    'name' => ['type' => 'string', 'description' => 'Block name'],
                    'search' => ['type' => 'string', 'description' => 'Exact text to search for'],
                    'replace' => ['type' => 'string', 'description' => 'Replacement text'],
                    'mode' => ['type' => 'string', 'enum' => ['first', 'all'], 'description' => 'Replace mode (default: first)'],
                    'case_sensitive' => ['type' => 'boolean', 'description' => 'Case sensitive search (default: true)']
                ],
                'required' => ['page_id', 'name', 'search', 'replace']
            ]
        ],
        'publish_page' => [
            'description' => 'Publish a page draft to make it live. NOTE: tool name is "publish_page", NOT "publish_draft". Use after editing to make changes live.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID. For homepage use: "" or "/"']
                ],
                'required' => ['page_id']
            ]
        ],
        'discard_draft' => [
            'description' => 'Discard a page draft without publishing. Keeps the live page unchanged.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID. For homepage use: "" or "/"']
                ],
                'required' => ['page_id']
            ]
        ],
        'create_page' => [
            'description' => 'Create a new page with optional HTML content.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'New page ID (e.g., "about", "services/web")'],
                    'content' => ['type' => 'string', 'description' => 'Optional HTML content for the page']
                ],
                'required' => ['page_id']
            ]
        ],
        'read_page' => [
            'description' => 'Read the full HTML content of a page file. Use sparingly - prefer list_blocks + read_block.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID. For homepage use: "" or "/"']
                ],
                'required' => ['page_id']
            ]
        ],
        'delete_page' => [
            'description' => 'Delete a page permanently. Creates backup before deletion.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID to delete']
                ],
                'required' => ['page_id']
            ]
        ],
        'duplicate_page' => [
            'description' => 'Duplicate an existing page to create a new one.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'source_page_id' => ['type' => 'string', 'description' => 'Source page ID to duplicate from'],
                    'new_page_id' => ['type' => 'string', 'description' => 'New page ID']
                ],
                'required' => ['source_page_id', 'new_page_id']
            ]
        ],
        'insert_block' => [
            'description' => 'Insert a new CMS block into a page at a specific position. Creates a DRAFT. After inserting, provide a CLICKABLE markdown link for preview: [Preview Draft](http://localhost:2222/cms/admin/preview.php?page_id={page_id}&draft=1) and ask user to publish using publish_page tool.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID'],
                    'position' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => ['before_block', 'after_block', 'at_end']],
                            'block_name' => ['type' => 'string', 'description' => 'Reference block name']
                        ],
                        'required' => ['type']
                    ],
                    'name' => ['type' => 'string', 'description' => 'New block name (must be unique)'],
                    'role' => ['type' => 'string', 'description' => 'Optional block role'],
                    'custom' => ['type' => 'boolean', 'description' => 'Whether this is a custom block'],
                    'content' => ['type' => 'string', 'description' => 'HTML content for the new block']
                ],
                'required' => ['page_id', 'position', 'name', 'content']
            ]
        ],
        'search_in_page' => [
            'description' => 'RAW FILE SEARCH - FALLBACK ONLY. Use only if search_blocks finds nothing.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID'],
                    'search' => ['type' => 'string', 'description' => 'Text to search for'],
                    'limit' => ['type' => 'integer', 'description' => 'Max matches to return (default: 20)'],
                    'case_sensitive' => ['type' => 'boolean', 'description' => 'Case sensitive search (default: false)']
                ],
                'required' => ['page_id', 'search']
            ]
        ],
        'get_page_region' => [
            'description' => 'Retrieve a region of a page by line range. Use after search_in_page.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID'],
                    'start_line' => ['type' => 'integer', 'description' => '1-based line number (inclusive)'],
                    'end_line' => ['type' => 'integer', 'description' => '1-based line number (inclusive)'],
                    'max_chars' => ['type' => 'integer', 'description' => 'Soft cap on region length (default: 4000)']
                ],
                'required' => ['page_id', 'start_line', 'end_line']
            ]
        ],
        'update_page_region' => [
            'description' => 'Apply a patch to a page region using optimistic locking. Creates a DRAFT. After updating, provide a CLICKABLE markdown link for preview: [Preview Draft](http://localhost:2222/cms/admin/preview.php?page_id={page_id}&draft=1) and ask user to publish using publish_page tool.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID'],
                    'start_line' => ['type' => 'integer', 'description' => 'Start line number'],
                    'end_line' => ['type' => 'integer', 'description' => 'End line number'],
                    'old_region' => ['type' => 'string', 'description' => 'Exact content from get_page_region'],
                    'new_region' => ['type' => 'string', 'description' => 'New content to replace with']
                ],
                'required' => ['page_id', 'start_line', 'end_line', 'old_region', 'new_region']
            ]
        ],
        'list_backups' => [
            'description' => 'List all available backups for a page with timestamps.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID']
                ],
                'required' => ['page_id']
            ]
        ],
        'restore_backup' => [
            'description' => 'Restore a page from a previous backup.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'page_id' => ['type' => 'string', 'description' => 'Page ID'],
                    'timestamp' => ['type' => 'string', 'description' => 'Backup timestamp (YmdHis format)']
                ],
                'required' => ['page_id', 'timestamp']
            ]
        ],
        'list_posts' => [
            'description' => 'List all blog posts in a collection (blog, news, etc.).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")']
                ],
                'required' => []
            ]
        ],
        'create_post' => [
            'description' => 'Create a new blog post as a draft.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")'],
                    'slug' => ['type' => 'string', 'description' => 'Post slug (e.g., "my-first-post")'],
                    'content' => ['type' => 'string', 'description' => 'Optional HTML content']
                ],
                'required' => ['slug']
            ]
        ],
        'read_post' => [
            'description' => 'Read the full content of a blog post.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")'],
                    'slug' => ['type' => 'string', 'description' => 'Post slug']
                ],
                'required' => ['slug']
            ]
        ],
        'publish_post' => [
            'description' => 'Publish a draft blog post.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")'],
                    'slug' => ['type' => 'string', 'description' => 'Post slug']
                ],
                'required' => ['slug']
            ]
        ],
        'unpublish_post' => [
            'description' => 'Unpublish a blog post (move back to drafts).',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")'],
                    'slug' => ['type' => 'string', 'description' => 'Post slug']
                ],
                'required' => ['slug']
            ]
        ],
        'read_post_block' => [
            'description' => 'Read a specific block from a blog post.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")'],
                    'slug' => ['type' => 'string', 'description' => 'Post slug'],
                    'block_name' => ['type' => 'string', 'description' => 'Name of the block to read']
                ],
                'required' => ['slug', 'block_name']
            ]
        ],
        'update_post_block' => [
            'description' => 'Update a specific block in a blog post. Always saves as draft first.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'collection_id' => ['type' => 'string', 'description' => 'Collection ID (default: "blog")'],
                    'slug' => ['type' => 'string', 'description' => 'Post slug'],
                    'block_name' => ['type' => 'string', 'description' => 'Name of the block to update'],
                    'new_content' => ['type' => 'string', 'description' => 'New content for the block (HTML)'],
                    'custom' => ['type' => 'boolean', 'description' => 'Mark block as custom (per-post)']
                ],
                'required' => ['slug', 'block_name', 'new_content']
            ]
        ],
        'upload_file' => [
            'description' => 'Upload a file to the uploads directory. Returns the URL.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'string', 'description' => 'Base64-encoded file data'],
                    'filename' => ['type' => 'string', 'description' => 'Original filename with extension'],
                    'subdir' => ['type' => 'string', 'description' => 'Optional subdirectory']
                ],
                'required' => ['data', 'filename']
            ]
        ],
        'upload_image' => [
            'description' => 'Upload and automatically optimize an image. Generates WebP and PNG, full-size and thumbnails.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'string', 'description' => 'Base64-encoded image data'],
                    'filename' => ['type' => 'string', 'description' => 'Original filename'],
                    'subdir' => ['type' => 'string', 'description' => 'Optional subdirectory']
                ],
                'required' => ['data', 'filename']
            ]
        ],
        'get_usage_tips' => [
            'description' => 'Get usage tips and best practices. QUICK START: 1) search_blocks to find text, 2) find_and_replace_block_content or update_block, 3) show draft preview link and ask to publish_page. NEVER guess tool names.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => new stdClass(),
                'required' => []
            ]
        ]
    ];
}
