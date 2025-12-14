# AI Template Creation Guide

This document defines the rules for creating page templates in this CMS. All editable content must be wrapped in CMS blocks.

## Block Syntax

Use PHP comment style for blocks:

```php
<?php /* CMS:BLOCK name=blockname role=roletype start */ ?>
... editable content ...
<?php /* CMS:BLOCK name=blockname end */ ?>
```

### Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `name` | Yes | Unique identifier for the block |
| `role` | No | Block category: `meta`, `content`, `navigation` |
| `custom` | No | Set `custom=1` for page-specific blocks |
| `system` | No | Set `system=1` for system-managed blocks |

## Block Types

### Global Blocks (Shared Across Pages)

These blocks contain content that is typically the same across all pages. They should NOT have `custom=1`.

| Block Name | Purpose |
|------------|---------|
| `header` | Site header, logo, main navigation |
| `footer` | Site footer, copyright, footer links |
| `navigation` | Main menu structure |
| `scripts` | Global JavaScript includes |
| `styles` | Global CSS includes |

### Custom Blocks (Page-Specific)

These blocks contain content unique to each page. They MUST have `custom=1`.

| Block Name | Purpose |
|------------|---------|
| `content` | Main page content area |
| `hero` | Page-specific hero section |
| `sidebar` | Page-specific sidebar content |
| `cta` | Page-specific call-to-action |

### Meta Blocks (Page-Specific SEO)

Meta blocks should have `role=meta` and `custom=1` for per-page customization.

| Block Name | Purpose |
|------------|---------|
| `meta_title` | `<title>` tag content |
| `meta_description` | Meta description tag |
| `meta_keywords` | Meta keywords tag |
| `meta_og` | Open Graph tags (og:title, og:description, og:image) |
| `meta_canonical` | Canonical URL |
| `meta_robots` | Robots directives |

## Template Structure Example

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* CMS:BLOCK name=meta_title role=meta custom=1 start */ ?>
    <title>Page Title</title>
    <?php /* CMS:BLOCK name=meta_title end */ ?>

    <?php /* CMS:BLOCK name=meta_description role=meta custom=1 start */ ?>
    <meta name="description" content="Page description">
    <?php /* CMS:BLOCK name=meta_description end */ ?>

    <?php /* CMS:BLOCK name=meta_keywords role=meta custom=1 start */ ?>
    <meta name="keywords" content="keyword1, keyword2">
    <?php /* CMS:BLOCK name=meta_keywords end */ ?>

    <?php /* CMS:BLOCK name=meta_og role=meta custom=1 start */ ?>
    <meta property="og:title" content="Page Title">
    <meta property="og:description" content="Page description">
    <meta property="og:image" content="/images/og-image.jpg">
    <meta property="og:type" content="website">
    <?php /* CMS:BLOCK name=meta_og end */ ?>

    <?php /* CMS:BLOCK name=meta_canonical role=meta custom=1 start */ ?>
    <link rel="canonical" href="https://example.com/page">
    <?php /* CMS:BLOCK name=meta_canonical end */ ?>

    <?php /* CMS:BLOCK name=styles start */ ?>
    <link rel="stylesheet" href="/css/main.css">
    <?php /* CMS:BLOCK name=styles end */ ?>
</head>
<body>
    <?php /* CMS:BLOCK name=header start */ ?>
    <header>
        <nav>
            <!-- Global navigation -->
        </nav>
    </header>
    <?php /* CMS:BLOCK name=header end */ ?>

    <?php /* CMS:BLOCK name=content role=content custom=1 start */ ?>
    <main>
        <!-- Page-specific content goes here -->
    </main>
    <?php /* CMS:BLOCK name=content end */ ?>

    <?php /* CMS:BLOCK name=footer start */ ?>
    <footer>
        <!-- Global footer -->
    </footer>
    <?php /* CMS:BLOCK name=footer end */ ?>

    <?php /* CMS:BLOCK name=scripts start */ ?>
    <script src="/js/main.js"></script>
    <?php /* CMS:BLOCK name=scripts end */ ?>
</body>
</html>
```

## Rules

1. **Wrap ALL editable content** - Any content the user might want to edit must be inside a block
2. **Use descriptive names** - Block names should clearly indicate their purpose
3. **Mark custom blocks** - Page-specific blocks must have `custom=1`
4. **Keep global blocks simple** - Header/footer blocks should not have `custom=1`
5. **Separate meta tags** - Each meta tag type should be in its own block for granular control
6. **Matching tags** - Start and end tags must have the same `name` value
7. **No nested blocks** - Blocks cannot be nested inside other blocks
8. **Unique names** - Each block name must be unique within a template

## Common Meta Tags to Include

For SEO-optimized templates, include blocks for:

- Title tag
- Meta description
- Meta keywords (optional, low SEO value)
- Open Graph tags (og:title, og:description, og:image, og:url, og:type)
- Twitter Card tags (twitter:card, twitter:title, twitter:description, twitter:image)
- Canonical URL
- Robots meta (index/noindex, follow/nofollow)
- Structured data (JSON-LD schema)
