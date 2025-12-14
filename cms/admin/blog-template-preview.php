<?php
/**
 * Blog Template Preview
 * Renders template with demo data for preview purposes
 */

require_once __DIR__ . '/includes/auth-guard.php';

$templateType = $_GET['type'] ?? 'post';

// Handle POST: store content in session and redirect to GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['template_content'])) {
    $_SESSION['template_preview_' . $templateType] = $_POST['template_content'];
    header('Location: /cms/admin/blog-template-preview.php?type=' . urlencode($templateType) . '&from=editor');
    exit;
}

// Demo data for placeholders
$demoData = [
    '{{POST_TITLE}}' => 'How to Optimize Your E-commerce Store for Maximum Performance',
    '{{POST_SLUG}}' => 'optimize-ecommerce-store-performance',
    '{{POST_DATE}}' => date('Y-m-d'),
    '{{POST_DATE_FORMATTED}}' => date('F j, Y'),
    '{{POST_AUTHOR}}' => 'John Smith',
    '{{POST_EXCERPT}}' => 'Learn the essential techniques and best practices to boost your online store\'s speed, improve user experience, and increase conversion rates.',
    '{{COLLECTION_LABEL}}' => 'Blog',
    '{{POST_CATEGORY}}' => 'E-commerce',
    '{{POST_READING_TIME}}' => '8',
    '{{POST_TAGS}}' => '<a href="#" style="display: inline-block; padding: 0.25rem 0.75rem; font-size: 0.875rem; background: #f3f4f6; color: #374151; border-radius: 9999px; text-decoration: none; margin-right: 0.5rem;">Magento</a><a href="#" style="display: inline-block; padding: 0.25rem 0.75rem; font-size: 0.875rem; background: #f3f4f6; color: #374151; border-radius: 9999px; text-decoration: none; margin-right: 0.5rem;">Performance</a><a href="#" style="display: inline-block; padding: 0.25rem 0.75rem; font-size: 0.875rem; background: #f3f4f6; color: #374151; border-radius: 9999px; text-decoration: none;">E-commerce</a>',
];

// Demo posts for list template
$demoPosts = [
    [
        'title' => 'How to Optimize Your E-commerce Store',
        'slug' => 'optimize-ecommerce-store',
        'excerpt' => 'Learn essential techniques to boost your online store\'s speed and conversion rates.',
        'date' => date('F j, Y'),
        'author' => 'John Smith',
        'category' => 'E-commerce',
        'reading_time' => '8',
    ],
    [
        'title' => 'Magento 2 Performance Best Practices',
        'slug' => 'magento-2-performance',
        'excerpt' => 'Discover the top strategies for maximizing your Magento 2 store performance.',
        'date' => date('F j, Y', strtotime('-3 days')),
        'author' => 'Jane Doe',
        'category' => 'Magento',
        'reading_time' => '12',
    ],
    [
        'title' => 'Building Custom Themes with Hyva',
        'slug' => 'custom-hyva-themes',
        'excerpt' => 'A comprehensive guide to creating blazing-fast themes using the Hyva framework.',
        'date' => date('F j, Y', strtotime('-7 days')),
        'author' => 'Mike Johnson',
        'category' => 'Development',
        'reading_time' => '10',
    ],
];

// Get template content
$templatesDir = __DIR__ . '/../blog-templates';
$templateFile = match($templateType) {
    'post' => $templatesDir . '/blog-post.php',
    'list' => $templatesDir . '/blog-list.php',
    'item' => $templatesDir . '/blog-item.php',
    default => null
};

if (!$templateFile || !file_exists($templateFile)) {
    die('Template file not found');
}

// Check if we have session content from editor (only use once, then clear)
$sessionKey = 'template_preview_' . $templateType;
if (isset($_GET['from']) && $_GET['from'] === 'editor' && !empty($_SESSION[$sessionKey])) {
    // Use editor content from session (unsaved preview)
    $templateContent = $_SESSION[$sessionKey];
    // Clear after use - next refresh will load from file
    unset($_SESSION[$sessionKey]);
} else {
    // Load fresh from file (after save, or direct access, or refresh)
    $templateContent = file_get_contents($templateFile);
}

// Replace placeholders with demo data
$output = str_replace(array_keys($demoData), array_values($demoData), $templateContent);

// Handle list template's {POSTS_LIST} placeholder
if ($templateType === 'list' && strpos($output, '{POSTS_LIST}') !== false) {
    // Load the item template
    $itemTemplateFile = __DIR__ . '/../blog-templates/blog-item.php';
    $itemTemplate = file_exists($itemTemplateFile) ? file_get_contents($itemTemplateFile) : '<article>{{POST_TITLE}}</article>';

    // Generate demo post items
    $postsHtml = '';
    foreach ($demoPosts as $post) {
        $itemHtml = $itemTemplate;
        $itemHtml = str_replace('{{POST_TITLE}}', $post['title'], $itemHtml);
        $itemHtml = str_replace('{{POST_SLUG}}', $post['slug'], $itemHtml);
        $itemHtml = str_replace('{{POST_EXCERPT}}', $post['excerpt'], $itemHtml);
        $itemHtml = str_replace('{{POST_DATE_FORMATTED}}', $post['date'], $itemHtml);
        $itemHtml = str_replace('{{POST_DATE}}', date('Y-m-d'), $itemHtml);
        $itemHtml = str_replace('{{POST_AUTHOR}}', $post['author'], $itemHtml);
        $itemHtml = str_replace('{{POST_CATEGORY}}', $post['category'], $itemHtml);
        $itemHtml = str_replace('{{POST_READING_TIME}}', $post['reading_time'], $itemHtml);
        $itemHtml = str_replace('{{COLLECTION_LABEL}}', 'Blog', $itemHtml);
        // Remove CMS markers from item
        $itemHtml = preg_replace('/\s*<\?php\s*\/\*\s*CMS:BLOCK[^*]*\*\/\s*\?>\s*/s', '', $itemHtml);
        $postsHtml .= $itemHtml;
    }

    $output = str_replace('{POSTS_LIST}', $postsHtml, $output);
}

// Remove CMS block markers for clean preview
$output = preg_replace('/\s*<\?php\s*\/\*\s*CMS:BLOCK[^*]*\*\/\s*\?>\s*/s', '', $output);

// Output the rendered template
echo $output;
