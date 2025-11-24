<?php
// Simple front page example for the MCP-enabled flat-file CMS prototype.

?><!doctype html>
<html lang="en">
<head>
<?php /* CMS:BLOCK name=head role=meta start */ ?>
    <meta charset="utf-8">
    <title>Home – MCP CMS Demo</title>
    <meta name="description" content="Demo home page for the flat-file MCP-enabled CMS.">
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

<?php /* CMS:BLOCK name=header start */ ?>
<header>
    <h1>WELCOME TO HOMEpage</h1>
</header>
<?php /* CMS:BLOCK name=header end */ ?>

<?php /* CMS:BLOCK name=content start */ ?>
<main>
    <p>This is the home page. Use MCP or the future admin UI to edit these blocks.</p>
</main>
<?php /* CMS:BLOCK name=content end */ ?>

<?php /* CMS:BLOCK name=footer start */ ?>
<footer>
    <p>&copy; <?php echo date('Y'); ?> Demo Site</p>
</footer>
<?php /* CMS:BLOCK name=footer end */ ?>
</body>
</html>
