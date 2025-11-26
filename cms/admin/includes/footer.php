            </div>
        </main>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-100 border-t border-gray-200 py-4 px-8 text-center text-sm text-gray-600">
        <?php
        require_once __DIR__ . '/../../core/Version.php';
        $version = new Version($config['root_dir']);
        $versionString = $version->getVersionString();
        ?>
        <p>MCP CMS &copy; <?php echo date('Y'); ?> | Version: <code class="bg-gray-200 px-2 py-1 rounded text-xs"><?php echo htmlspecialchars($versionString); ?></code></p>
    </footer>
</body>
</html>
