<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?> - MCP CMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none; }
    </style>
</head>
<body class="bg-gray-50">
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
                <a href="/cms/admin/" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'dashboard' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">Dashboard</span>
                </a>
                <a href="/cms/admin/pages.php" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'pages' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">Pages</span>
                </a>
                <a href="/cms/admin/create.php" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'create' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">Create Page</span>
                </a>
                <a href="/cms/admin/sync-blocks.php" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'sync' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">Sync Blocks</span>
                </a>
                <a href="/cms/admin/mcp-config.php" class="block px-6 py-3 hover:bg-gray-500 transition <?php echo ($activePage ?? '') === 'mcp' ? 'bg-gray-500 border-l-4 border-blue-400' : ''; ?>">
                    <span class="font-medium">MCP Config</span>
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="max-w-6xl mx-auto">
