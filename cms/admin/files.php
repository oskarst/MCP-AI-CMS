<?php
/**
 * File Manager - Browse, upload, edit, and manage files
 */

require_once __DIR__ . '/includes/auth-guard.php';

$pageTitle = 'File Manager';
$activePage = 'files';

// Current path (for now just showing root)
$currentPath = '/';
$breadcrumbs = [
    ['name' => 'Root', 'path' => '/']
];

// Mock file/folder data for UI demonstration
$items = [
    ['type' => 'folder', 'name' => 'assets', 'modified' => '2024-01-15 10:30', 'size' => '-'],
    ['type' => 'folder', 'name' => 'about', 'modified' => '2024-01-14 09:15', 'size' => '-'],
    ['type' => 'file', 'name' => 'index.php', 'modified' => '2024-01-20 14:22', 'size' => '125 KB', 'ext' => 'php'],
    ['type' => 'file', 'name' => 'robots.txt', 'modified' => '2024-01-10 11:45', 'size' => '245 B', 'ext' => 'txt'],
    ['type' => 'file', 'name' => '.htaccess', 'modified' => '2024-01-08 16:30', 'size' => '512 B', 'ext' => 'htaccess'],
];

require __DIR__ . '/includes/header.php';
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">File Manager</h1>
    <p class="text-gray-600">Browse and manage your website files</p>
</div>

<!-- Action Bar -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-3">
        <button class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition flex items-center gap-2 whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Upload Files
        </button>
        <button class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition flex items-center gap-2 whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            New File
        </button>
        <button class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition flex items-center gap-2 whitespace-nowrap">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            </svg>
            New Folder
        </button>
    </div>
</div>

<!-- Breadcrumb Navigation -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <nav class="flex" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                <li class="inline-flex items-center">
                    <?php if ($index > 0): ?>
                        <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                    <?php endif; ?>
                    <a href="#" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <?php if ($index === 0): ?>
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                            </svg>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($crumb['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
</div>

<!-- File Browser -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <!-- Table Header -->
    <div class="bg-gray-50 px-6 py-3 border-b border-gray-200">
        <div class="grid grid-cols-12 gap-4 text-xs font-medium text-gray-700 uppercase tracking-wider">
            <div class="col-span-6">Name</div>
            <div class="col-span-3">Modified</div>
            <div class="col-span-2">Size</div>
            <div class="col-span-1 text-right">Actions</div>
        </div>
    </div>

    <!-- File/Folder List -->
    <div class="divide-y divide-gray-200">
        <?php foreach ($items as $item): ?>
            <div class="px-6 py-4 hover:bg-gray-50 transition">
                <div class="grid grid-cols-12 gap-4 items-center">
                    <!-- Name with Icon -->
                    <div class="col-span-6 flex items-center gap-3">
                        <?php if ($item['type'] === 'folder'): ?>
                            <!-- Folder Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <a href="#" class="font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </a>
                        <?php else: ?>
                            <!-- File Icon -->
                            <?php
                            $iconColor = 'text-gray-500';
                            if ($item['ext'] === 'php') $iconColor = 'text-purple-500';
                            elseif ($item['ext'] === 'html') $iconColor = 'text-orange-500';
                            elseif ($item['ext'] === 'css') $iconColor = 'text-blue-500';
                            elseif ($item['ext'] === 'js') $iconColor = 'text-yellow-500';
                            elseif (in_array($item['ext'], ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'])) $iconColor = 'text-green-500';
                            ?>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 <?php echo $iconColor; ?> flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="font-medium text-gray-900">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Modified Date -->
                    <div class="col-span-3 text-sm text-gray-600">
                        <?php echo htmlspecialchars($item['modified']); ?>
                    </div>

                    <!-- Size -->
                    <div class="col-span-2 text-sm text-gray-600">
                        <?php echo htmlspecialchars($item['size']); ?>
                    </div>

                    <!-- Actions -->
                    <div class="col-span-1 flex justify-end gap-2">
                        <?php if ($item['type'] === 'file'): ?>
                            <button class="text-blue-600 hover:text-blue-800 p-1" title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button class="text-green-600 hover:text-green-800 p-1" title="Download">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                            </button>
                        <?php endif; ?>
                        <button class="text-red-600 hover:text-red-800 p-1" title="Delete">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Empty State (hidden for now, shown when no files) -->
<div class="hidden bg-white rounded-lg shadow-md p-12 text-center">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-gray-400 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
    </svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">This folder is empty</h3>
    <p class="text-gray-600 mb-6">Upload files or create a new folder to get started</p>
    <button class="btn bg-blue-600 text-white hover:bg-blue-700">Upload Files</button>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
