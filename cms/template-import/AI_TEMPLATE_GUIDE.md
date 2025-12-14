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
| `system` | No | Set `system=1` for non-visual blocks (hides preview toggle) |

## Block Types

### System Blocks (No Visual Preview)

System blocks contain non-visual content like meta tags, scripts, and structured data. They should have `system=1` to hide the preview toggle in the editor since there's nothing visual to preview.

| Block Name | Attributes | Purpose |
|------------|------------|---------|
| `meta_title` | `role=meta custom=1 system=1` | `<title>` tag content |
| `meta_description` | `role=meta custom=1 system=1` | Meta description tag |
| `meta_keywords` | `role=meta custom=1 system=1` | Meta keywords tag |
| `meta_og` | `role=meta custom=1 system=1` | Open Graph tags |
| `meta_twitter` | `role=meta custom=1 system=1` | Twitter Card tags |
| `meta_canonical` | `role=meta custom=1 system=1` | Canonical URL |
| `meta_robots` | `role=meta custom=1 system=1` | Robots directives |
| `structured_data` | `role=meta custom=1 system=1` | JSON-LD schema markup |
| `scripts` | `system=1` | Global JavaScript includes |
| `head` | `role=meta system=1` | Combined head meta tags |

### Global Blocks (Shared Across Pages)

These blocks contain visible content that is typically the same across all pages. They should NOT have `custom=1`.

| Block Name | Attributes | Purpose |
|------------|------------|---------|
| `header` | (none) | Site header, logo, main navigation |
| `footer` | (none) | Site footer, copyright, footer links |
| `navigation` | (none) | Main menu structure |
| `styles` | (none) | Global CSS includes |

### Custom Blocks (Page-Specific Visible Content)

These blocks contain visible content unique to each page. They MUST have `custom=1`.

| Block Name | Attributes | Purpose |
|------------|------------|---------|
| `content` | `role=content custom=1` | Main page content area |
| `hero` | `custom=1` | Page-specific hero section |
| `sidebar` | `custom=1` | Page-specific sidebar content |
| `cta` | `custom=1` | Page-specific call-to-action |

## Template Structure Example

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <?php /* CMS:BLOCK name=meta_title role=meta custom=1 system=1 start */ ?>
    <title>Page Title</title>
    <?php /* CMS:BLOCK name=meta_title end */ ?>

    <?php /* CMS:BLOCK name=meta_description role=meta custom=1 system=1 start */ ?>
    <meta name="description" content="Page description">
    <?php /* CMS:BLOCK name=meta_description end */ ?>

    <?php /* CMS:BLOCK name=meta_keywords role=meta custom=1 system=1 start */ ?>
    <meta name="keywords" content="keyword1, keyword2">
    <?php /* CMS:BLOCK name=meta_keywords end */ ?>

    <?php /* CMS:BLOCK name=meta_og role=meta custom=1 system=1 start */ ?>
    <meta property="og:title" content="Page Title">
    <meta property="og:description" content="Page description">
    <meta property="og:image" content="/images/og-image.jpg">
    <meta property="og:type" content="website">
    <?php /* CMS:BLOCK name=meta_og end */ ?>

    <?php /* CMS:BLOCK name=meta_canonical role=meta custom=1 system=1 start */ ?>
    <link rel="canonical" href="https://example.com/page">
    <?php /* CMS:BLOCK name=meta_canonical end */ ?>

    <?php /* CMS:BLOCK name=structured_data role=meta custom=1 system=1 start */ ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Page Title"
    }
    </script>
    <?php /* CMS:BLOCK name=structured_data end */ ?>

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

    <?php /* CMS:BLOCK name=scripts system=1 start */ ?>
    <script src="/js/main.js"></script>
    <?php /* CMS:BLOCK name=scripts end */ ?>
</body>
</html>
```

## Global vs Custom Blocks Behavior

### Global Blocks (Automatic Sync)

Blocks **WITHOUT** `custom=1` are **global blocks**. When you edit a global block:

1. The change is saved as a draft on the source page
2. **All other pages** with the same block (that don't have `custom=1`) are **automatically updated**
3. A **global backup** is created containing snapshots of all affected pages
4. Pages where the block is marked as `custom=1` are **skipped**

**Example:** Editing the `header` block on the homepage will automatically update the header on all other pages.

### Custom Blocks (Page-Specific)

Blocks **WITH** `custom=1` are **page-specific**. When you edit a custom block:

1. Only that specific page is affected
2. A page-specific backup is created
3. Other pages are never touched

**Example:** Each page has its own `content` block with `custom=1`, so editing content on one page doesn't affect others.

### Two Types of Backups

| Backup Type | Created When | Restores |
|-------------|--------------|----------|
| **Page Backup** | Editing custom blocks | Single page only |
| **Global Backup** | Editing global blocks | All affected pages at once |

### When to Use `custom=1`

| Content Type | Use `custom=1`? | Reason |
|--------------|----------------|--------|
| Header/Footer | No | Same on all pages |
| Main Navigation | No | Same on all pages |
| Page Content | Yes | Unique per page |
| Hero Sections | Yes | Different per page |
| Meta Tags | Yes | Different per page |
| Page-specific CTAs | Yes | Different per page |

## Rules

1. **Wrap ALL editable content** - Any content the user might want to edit must be inside a block
2. **Use descriptive names** - Block names should clearly indicate their purpose
3. **Mark custom blocks** - Page-specific blocks must have `custom=1`
4. **Keep global blocks simple** - Header/footer blocks should not have `custom=1`
5. **Separate meta tags** - Each meta tag type should be in its own block for granular control
6. **Matching tags** - Start and end tags must have the same `name` value
7. **No nested blocks** - Blocks cannot be nested inside other blocks
8. **Unique names** - Each block name must be unique within a template
9. **Think about sync** - If content should be the same everywhere, don't use `custom=1`. If it should be unique per page, use `custom=1`

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
