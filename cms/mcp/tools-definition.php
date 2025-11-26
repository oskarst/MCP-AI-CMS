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
