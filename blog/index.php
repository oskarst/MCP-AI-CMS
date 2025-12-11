<!DOCTYPE html>
<html lang="en">
<head>
<?php /* CMS:BLOCK name=head role=meta start */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
    <meta name="description" content="Browse all Blog posts">
    <style>
        /* Simple pagination styles */
        .pagination { margin: 2rem 0; }
        .pagination-list { display: flex; list-style: none; gap: 0.5rem; padding: 0; flex-wrap: wrap; }
        .pagination-link, .pagination-previous, .pagination-next, .pagination-ellipsis {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination-link:hover, .pagination-previous:hover, .pagination-next:hover { background: #f5f5f5; }
        .pagination-link.active { background: #007bff; color: white; border-color: #007bff; }
        .pagination-previous.disabled, .pagination-next.disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination-ellipsis { border: none; }
    </style>
<?php /* CMS:BLOCK name=head end */ ?>
</head>
<body>
<?php /* CMS:BLOCK name=header role=navigation start */ ?>
    <header>
        <nav>
            <a href="/">Home</a>
            <a href="/blog/">Blog</a>
        </nav>
    </header>
<?php /* CMS:BLOCK name=header end */ ?>

<?php /* CMS:BLOCK name=content start */ ?>
    <main>
        <h1>Blog</h1>
        <div class="posts-list">
            <article class="post-item">
    <h2><a href="/blog/new-post/">New Post</a></h2>
    <p class="date">2025-11-26</p>
    <p class="excerpt">New Post</p>
    <a href="/blog/new-post/">Read more</a>
</article>
<article class="post-item">
    <h2><a href="/blog/ttt/">Ttt</a></h2>
    <p class="date">2025-11-26</p>
    <p class="excerpt">Ttt</p>
    <a href="/blog/ttt/">Read more</a>
</article>
<article class="post-item">
    <h2><a href="/blog/test/">Tests</a></h2>
    <p class="date">2025-11-24</p>
    <p class="excerpt">Test</p>
    <a href="/blog/test/">Read more</a>
</article>

        </div>
        
    </main>
<?php /* CMS:BLOCK name=content end */ ?>

<?php /* CMS:BLOCK name=footer start */ ?>
    <footer>
        <p>&copy; <?php echo date('Y'); ?> All rights reserved.</p>
    </footer>
<?php /* CMS:BLOCK name=footer end */ ?>
</body>
</html>