<?php
// About page for the MCP-enabled flat-file CMS prototype.

?><!doctype html>
<html lang="en">
<head>
<?php /* CMS:BLOCK name=head role=meta start */ ?>
    <meta charset="utf-8">
    <title>About – MCP CMS Demo</title>
    <meta name="description" content="About page for the flat-file MCP-enabled CMS.">
<?php /* CMS:BLOCK name=head role=meta end */ ?>
</head>
<body>
<?php /* CMS:BLOCK name=main_nav role=navigation start */ ?>
<nav>
    <ul>
        <li><a href="/">Home</a></li>
        <li><a href="/about/">About</a></li>
    </ul>
</nav>
<?php /* CMS:BLOCK name=main_nav role=navigation end */ ?>

<?php /* CMS:BLOCK name=header custom=1 start */ ?>
<header>
    <h1>About This CMS</h1>
</header>
<?php /* CMS:BLOCK name=header end */ ?>

<?php /* CMS:BLOCK name=content start */ ?>
<main>
    <h2>What is This?</h2>
    <p>This is a flat-file CMS powered by PHP, with no database required. Each page is a folder containing an <code>index.php</code> file.</p>

    <h2>Key Features</h2>
    <ul>
        <li>No database - just files and folders</li>
        <li>Block-based editing with simple PHP comment markers</li>
        <li>MCP integration for AI-powered content editing</li>
        <li>Built-in admin panel for human editors</li>
        <li>Automatic backups before each change</li>
    </ul>

    <h2>How It Works</h2>
    <p>Content is organized into blocks marked with special PHP comments. These blocks can be edited individually through the admin panel or via MCP tools used by AI assistants like ChatGPT and Claude.</p>
</main>
<?php /* CMS:BLOCK name=content end */ ?>

<?php /* CMS:BLOCK name=footer start */ ?>
<footer>
    <p>&copy; <?php echo date('Y'); ?> Demo Site</p>
</footer>
<?php /* CMS:BLOCK name=footer end */ ?>
</body>
</html>
