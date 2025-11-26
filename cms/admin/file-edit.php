<?php
/**
 * File Editor - Edit file contents
 */

require_once __DIR__ . '/includes/auth-guard.php';
require_once __DIR__ . '/../core/CSRF.php';

$pageTitle = 'Edit File';
$activePage = 'files';

// Forbidden directories
$forbiddenDirs = ['cms', '.git', 'node_modules', 'vendor'];

// Get root directory from config
$rootDir = rtrim($config['root_dir'], '/');

// Get file path from query parameter and sanitize it
$requestedFile = $_GET['file'] ?? '';
$requestedFile = str_replace(['..', '\\'], '', trim($requestedFile, '/'));

if (!$requestedFile) {
    header('Location: /cms/admin/files.php');
    exit;
}

// Check if first segment is forbidden
$pathSegments = array_filter(explode('/', $requestedFile));
if (!empty($pathSegments) && in_array($pathSegments[0], $forbiddenDirs)) {
    die('Access denied to this directory');
}

// Build absolute path
$filePath = $rootDir . '/' . $requestedFile;
$realFilePath = realpath($filePath);
$realRootPath = realpath($rootDir);

// Security checks
if (!$realFilePath || !is_file($realFilePath) || strpos($realFilePath, $realRootPath) !== 0) {
    die('Invalid file path');
}

$fileName = basename($requestedFile);
$fileExt = pathinfo($fileName, PATHINFO_EXTENSION);

// Handle file save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    CSRF::verifyOrDie();

    try {
        $content = $_POST['content'] ?? '';

        // Create backup before editing
        $backupDir = dirname($realFilePath) . '/.backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $backupFile = $backupDir . '/' . $fileName . '.' . date('YmdHis') . '.bak';
        @copy($realFilePath, $backupFile);

        if (file_put_contents($realFilePath, $content) === false) {
            throw new Exception('Failed to save file');
        }

        $successMessage = 'File saved successfully';
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Read file content
$fileContent = file_get_contents($realFilePath);
$fileSize = filesize($realFilePath);
$fileSizeFormatted = $fileSize < 1024 ? $fileSize . ' B' : ($fileSize < 1048576 ? round($fileSize / 1024, 1) . ' KB' : round($fileSize / 1048576, 1) . ' MB');

// Get parent directory for breadcrumb
$parentPath = dirname($requestedFile);
$parentPath = ($parentPath === '.' || $parentPath === '/') ? '' : $parentPath;

require __DIR__ . '/includes/header.php';
?>

<!-- CodeMirror CSS and JS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/markdown/markdown.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/python/python.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/sql/sql.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/clike/clike.min.js"></script>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-900 mb-2">Edit File</h1>
    <p class="text-gray-600">
        <a href="/cms/admin/files.php?path=<?php echo urlencode($parentPath); ?>" class="text-blue-600 hover:text-blue-800">&larr; Back to File Manager</a>
    </p>
</div>

<?php if (isset($successMessage)): ?>
    <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <p class="text-green-700"><?php echo htmlspecialchars($successMessage); ?></p>
    </div>
<?php endif; ?>

<?php if (isset($errorMessage)): ?>
    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-700"><?php echo htmlspecialchars($errorMessage); ?></p>
    </div>
<?php endif; ?>

<!-- File Info -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <div class="grid grid-cols-3 gap-4 text-sm">
        <div>
            <span class="text-gray-600">File:</span>
            <span class="font-medium text-gray-900 ml-2"><?php echo htmlspecialchars($fileName); ?></span>
        </div>
        <div>
            <span class="text-gray-600">Size:</span>
            <span class="font-medium text-gray-900 ml-2"><?php echo htmlspecialchars($fileSizeFormatted); ?></span>
        </div>
        <div>
            <span class="text-gray-600">Type:</span>
            <span class="font-medium text-gray-900 ml-2"><?php echo htmlspecialchars($fileExt ?: 'no extension'); ?></span>
        </div>
    </div>
</div>

<!-- File Editor -->
<form method="post">
    <?php echo CSRF::inputField(); ?>
    <input type="hidden" name="action" value="save">

    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">File Content:</label>
            <textarea
                name="content"
                rows="25"
                class="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                spellcheck="false"
            ><?php echo htmlspecialchars($fileContent); ?></textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                Save Changes
            </button>
            <a href="/cms/admin/files.php?path=<?php echo urlencode($parentPath); ?>" class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition inline-block">
                Cancel
            </a>
        </div>
    </div>
</form>

<div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
    <p class="text-yellow-700">
        <strong>Note:</strong> A backup is automatically created before saving. Backups are stored in <code class="bg-yellow-100 px-1 rounded">.backups</code> folder.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.querySelector('textarea[name="content"]');
    const form = textarea.closest('form');
    const fileExt = '<?php echo strtolower($fileExt); ?>';

    // Determine CodeMirror mode based on file extension
    let mode = 'text/plain';

    const modeMap = {
        'php': 'application/x-httpd-php',
        'html': 'htmlmixed',
        'htm': 'htmlmixed',
        'xml': 'xml',
        'js': 'javascript',
        'json': { name: 'javascript', json: true },
        'css': 'css',
        'scss': 'text/x-scss',
        'sass': 'text/x-sass',
        'less': 'text/x-less',
        'md': 'markdown',
        'markdown': 'markdown',
        'py': 'python',
        'sh': 'shell',
        'bash': 'shell',
        'sql': 'sql',
        'yml': 'yaml',
        'yaml': 'yaml',
        'java': 'text/x-java',
        'c': 'text/x-csrc',
        'cpp': 'text/x-c++src',
        'h': 'text/x-csrc',
        'go': 'text/x-go',
        'rb': 'text/x-ruby',
        'txt': 'text/plain'
    };

    if (modeMap[fileExt]) {
        mode = modeMap[fileExt];
    }

    // Initialize CodeMirror
    const editor = CodeMirror.fromTextArea(textarea, {
        mode: mode,
        theme: 'material-darker',
        lineNumbers: true,
        lineWrapping: true,
        indentUnit: 4,
        indentWithTabs: false,
        matchBrackets: true,
        autoCloseTags: true,
        viewportMargin: Infinity
    });

    // Save CodeMirror content to textarea before form submit
    form.addEventListener('submit', function() {
        editor.save();
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
