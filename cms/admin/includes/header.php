<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?> - MCP CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-50" x-data="{ contentOpen: <?php echo in_array($activePage ?? '', ['pages', 'create', 'sync']) ? 'true' : 'false'; ?>, settingsOpen: <?php echo ($activePage ?? '') === 'settings' || in_array($_SERVER['PHP_SELF'] ?? '', ['/cms/admin/settings.php', '/cms/admin/blog-templates.php', '/cms/admin/mcp-config.php']) ? 'true' : 'false'; ?> }">
    <!-- Header -->
    <header class="bg-gray-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold">MCP CMS Admin</h1>
            <?php if (isset($currentUser)): ?>
                <div class="flex items-center gap-4">
                    <span class="text-gray-200"><?php echo htmlspecialchars($currentUser['username']); ?></span>
                    <a href="/cms/admin/logout.php" class="text-blue-300 hover:text-blue-200 transition">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <nav class="w-64 bg-gray-600 text-white shadow-xl">
            <div class="py-6">
                <!-- Dashboard -->
                <a href="/cms/admin/" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'dashboard' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">Dashboard</span>
                </a>

                <!-- Content Menu (Collapsible) -->
                <div class="relative">
                    <button @click="contentOpen = !contentOpen" class="w-full text-left px-6 py-3 hover:bg-gray-500 transition flex items-center justify-between <?php echo in_array($activePage ?? '', ['pages', 'create', 'sync']) ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                        <span class="font-medium">Content</span>
                        <svg class="w-4 h-4 transition-transform" :class="contentOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="contentOpen" x-cloak x-collapse class="bg-gray-700">
                        <a href="/cms/admin/pages.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'pages' ? 'bg-gray-500' : ''; ?>">
                            Pages
                        </a>
                        <a href="/cms/admin/create.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'create' ? 'bg-gray-500' : ''; ?>">
                            Create Page
                        </a>
                        <a href="/cms/admin/sync-blocks.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'sync' ? 'bg-gray-500' : ''; ?>">
                            Sync Page Blocks
                        </a>
                        <a href="/cms/admin/blog.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'blog' ? 'bg-gray-500' : ''; ?>">
                            Blog Posts
                        </a>
                        <a href="/cms/admin/blog-sync-blocks.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'blog-sync' ? 'bg-gray-500' : ''; ?>">
                            Sync Post Blocks
                        </a>
                    </div>
                </div>

                <!-- File Manager -->
                <a href="/cms/admin/files.php" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'files' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">File Manager</span>
                </a>

                <!-- Settings Menu (Collapsible) -->
                <div class="relative">
                    <button @click="settingsOpen = !settingsOpen" class="w-full text-left px-6 py-3 hover:bg-gray-500 transition flex items-center justify-between <?php echo ($activePage ?? '') === 'settings' || in_array($_SERVER['PHP_SELF'] ?? '', ['/cms/admin/settings.php', '/cms/admin/blog-templates.php', '/cms/admin/mcp-config.php']) ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                        <span class="font-medium">Settings</span>
                        <svg class="w-4 h-4 transition-transform" :class="settingsOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="settingsOpen" x-cloak x-collapse class="bg-gray-700">
                        <a href="/cms/admin/settings.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($_SERVER['PHP_SELF'] ?? '') === '/cms/admin/settings.php' ? 'bg-gray-500' : ''; ?>">
                            General
                        </a>
                        <a href="/cms/admin/blog-templates.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($_SERVER['PHP_SELF'] ?? '') === '/cms/admin/blog-templates.php' ? 'bg-gray-500' : ''; ?>">
                            Blog Templates
                        </a>
                        <a href="/cms/admin/mcp-config.php" class="block px-6 py-2 pl-12 text-sm hover:bg-gray-500 transition <?php echo ($_SERVER['PHP_SELF'] ?? '') === '/cms/admin/mcp-config.php' ? 'bg-gray-500' : ''; ?>">
                            MCP Config
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="max-w-6xl mx-auto">
