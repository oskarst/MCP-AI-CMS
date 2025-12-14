<?php /* POST_STATUS: published */ ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php /* CMS:BLOCK name=meta_title role=meta custom=1 system=1 start */ ?>
    <title>Tests - Developers Alliance Blog</title>
    <?php /* CMS:BLOCK name=meta_title end */ ?>
    <?php /* CMS:BLOCK name=meta_description role=meta custom=1 system=1 start */ ?>
    <meta name="description" content="Test post exploring best practices for Magento development and testing methodologies.">
    <?php /* CMS:BLOCK name=meta_description end */ ?>
    <?php /* CMS:BLOCK name=meta_author role=meta custom=1 system=1 start */ ?>
    <meta name="author" content="Dev Team">
    <meta name="date" content="2025-11-24">
    <?php /* CMS:BLOCK name=meta_author end */ ?>
    <?php /* CMS:BLOCK name=meta_canonical role=meta custom=1 system=1 start */ ?>
    <link rel="canonical" href="https://developers-alliance.com/blog/test/">
    <?php /* CMS:BLOCK name=meta_canonical end */ ?>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

    <?php /* CMS:BLOCK name=meta_og role=meta custom=1 system=1 start */ ?>
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://developers-alliance.com/blog/test/">
    <meta property="og:title" content="Tests - Developers Alliance Blog">
    <meta property="og:description" content="Test post exploring best practices for Magento development and testing methodologies.">
    <meta property="og:image" content="https://developers-alliance.com/img/devall-raft-sc.jpg">
    <meta property="article:published_time" content="2025-11-24">
    <?php /* CMS:BLOCK name=meta_og end */ ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & DaisyUI -->
    <link rel="stylesheet" href="/assets/css/styles.css">

    <?php /* CMS:BLOCK name=structured_data role=meta custom=1 system=1 start */ ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": "Tests",
        "description": "Test post exploring best practices for Magento development.",
        "datePublished": "2025-11-24",
        "author": {
            "@type": "Person",
            "name": "Dev Team"
        },
        "publisher": {
            "@type": "Organization",
            "name": "Developers Alliance",
            "logo": {
                "@type": "ImageObject",
                "url": "https://developers-alliance.com/img/logo-dark.png"
            }
        }
    }
    </script>
    <?php /* CMS:BLOCK name=structured_data end */ ?>

    <style>
        .article-content { font-size: 1.125rem; line-height: 1.8; color: #374151; }
        .article-content h2 { font-family: 'Titillium Web', sans-serif; font-size: 1.875rem; font-weight: 700; color: #111827; margin-top: 2.5rem; margin-bottom: 1rem; }
        .article-content h3 { font-family: 'Titillium Web', sans-serif; font-size: 1.5rem; font-weight: 600; color: #1f2937; margin-top: 2rem; margin-bottom: 0.75rem; }
        .article-content p { margin-bottom: 1.5rem; }
        .article-content ul, .article-content ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
        .article-content li { margin-bottom: 0.5rem; }
        .article-content blockquote { border-left: 4px solid #EE1C25; padding-left: 1.5rem; margin: 2rem 0; font-style: italic; color: #4b5563; }
        .article-content code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875em; }
        .article-content pre { background: #1f2937; color: #f9fafb; padding: 1.5rem; border-radius: 0.75rem; overflow-x: auto; margin: 1.5rem 0; }
        .article-content pre code { background: transparent; padding: 0; color: inherit; }
        .article-content a { color: #EE1C25; text-decoration: underline; }
        .article-content img { border-radius: 0.75rem; margin: 2rem 0; }
    </style>
</head>
<body class="font-body">
    <?php /* CMS:BLOCK name=header start */ ?>
    <!-- Navigation -->
    <nav class="navbar bg-white shadow-md fixed top-0 z-50" role="navigation">
        <div class="container mx-auto">
            <div class="navbar-start">
                <div class="dropdown">
                    <button tabindex="0" class="btn btn-ghost lg:hidden" aria-label="Open mobile menu">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16" />
                        </svg>
                    </button>
                    <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[1] p-2 shadow bg-base-100 rounded-box w-52">
                        <li><a href="/">Home</a></li>
                        <li><details><summary>Services</summary><ul>
                            <li><a href="/services/">All Services</a></li>
                            <li><a href="/services/magento-development/">Magento Development</a></li>
                            <li><a href="/services/seo/">SEO Services</a></li>
                            <li><a href="/services/hyva-theme-development/">Hyva Theme</a></li>
                            <li><a href="/services/magento-migration/">Magento Migration</a></li>
                        </ul></details></li>
                        <li><a href="/portfolio/">Portfolio</a></li>
                        <li><a href="/team/">Team</a></li>
                        <li><a href="/blog/">Blog</a></li>
                        <li><a href="/#contacts">Contact Us</a></li>
                    </ul>
                </div>
                <a href="/" class="btn btn-ghost normal-case text-xl">
                    <img src="/assets/img/logo-dark.png" alt="Developers Alliance" class="h-8 w-auto object-contain">
                </a>
            </div>
            <div class="navbar-end hidden lg:flex">
                <ul class="menu menu-horizontal px-1 font-medium">
                    <li><a href="/" class="hover:text-primary">Home</a></li>
                    <li class="dropdown dropdown-hover">
                        <label tabindex="0" class="hover:text-primary cursor-pointer">Services</label>
                        <ul tabindex="0" class="dropdown-content menu p-2 shadow bg-base-100 rounded-box w-56 z-50">
                            <li><a href="/services/">All Services</a></li>
                            <li><a href="/services/magento-development/">Magento Development</a></li>
                            <li><a href="/services/seo/">SEO Services</a></li>
                            <li><a href="/services/hyva-theme-development/">Hyva Theme Development</a></li>
                            <li><a href="/services/magento-migration/">Magento Migration</a></li>
                        </ul>
                    </li>
                    <li><a href="/portfolio/" class="hover:text-primary">Portfolio</a></li>
                    <li><a href="/team/" class="hover:text-primary">Team</a></li>
                    <li><a href="/blog/" class="hover:text-primary">Blog</a></li>
                    <li><a href="/#contacts" class="hover:text-primary">Contact Us</a></li>
                </ul>
            </div>
        </div>
    </nav>
    <?php /* CMS:BLOCK name=header end */ ?>

    <main class="pt-20">
        <?php /* CMS:BLOCK name=article_header custom=1 start */ ?>
        <!-- Article Header -->
        <section class="py-16 md:py-24 relative overflow-hidden" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);">
            <div class="absolute inset-0">
                <div class="absolute top-0 left-1/4 w-[500px] h-[500px] rounded-full" style="background: #93c5fd; filter: blur(120px); opacity: 0.4;"></div>
                <div class="absolute bottom-0 right-1/4 w-[400px] h-[400px] rounded-full" style="background: #cbd5e1; filter: blur(100px); opacity: 0.5;"></div>
                <div class="absolute top-1/2 right-1/3 w-[300px] h-[300px] rounded-full" style="background: #a5b4fc; filter: blur(90px); opacity: 0.3;"></div>
            </div>

            <div class="container mx-auto px-4 relative z-10">
                <nav class="mb-8" aria-label="Breadcrumb">
                    <ol class="flex items-center gap-2 text-sm text-gray-500">
                        <li><a href="/" class="hover:text-primary transition">Home</a></li>
                        <li><span class="mx-2">/</span></li>
                        <li><a href="/blog/" class="hover:text-primary transition">Blog</a></li>
                        <li><span class="mx-2">/</span></li>
                        <li class="text-gray-700">Tests</li>
                    </ol>
                </nav>

                <div class="max-w-4xl">
                    <div class="mb-6">
                        <span class="px-4 py-1.5 bg-primary text-white text-sm font-semibold rounded-full">Testing</span>
                    </div>

                    <h1 class="text-3xl md:text-5xl lg:text-6xl font-heading font-bold mb-6 leading-tight text-gray-900">
                        Tests
                    </h1>

                    <div class="flex flex-wrap items-center gap-6 text-gray-600">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-white/80 rounded-full flex items-center justify-center shadow-sm">
                                <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900">Dev Team</div>
                                <div class="text-sm text-gray-500">Developer Team</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span>Nov 24, 2025</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>3 min read</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php /* CMS:BLOCK name=article_header end */ ?>

        <article class="py-12 md:py-20">
            <div class="container mx-auto px-4">
                <div class="grid lg:grid-cols-12 gap-12">
                    <div class="lg:col-span-8">
                        <?php /* CMS:BLOCK name=content custom=1 start */ ?>
                        <div class="article-content">
                            <p class="text-xl text-gray-600 mb-8 leading-relaxed">
                                This is a test post demonstrating the new blog post template design that matches the Developers Alliance website.
                            </p>

                            <h2>Introduction</h2>
                            <p>
                                Write your content here. This template supports full HTML formatting including headings, lists, code blocks, images, and more.
                            </p>

                            <h2>Main Section</h2>
                            <p>
                                Continue writing your article content. Use proper heading hierarchy (h2, h3) to structure your content for better readability and SEO.
                            </p>

                            <h3>Subsection</h3>
                            <p>
                                Add more detailed information in subsections. You can include:
                            </p>
                            <ul>
                                <li>Bullet points for lists</li>
                                <li>Code examples</li>
                                <li>Images and diagrams</li>
                                <li>Blockquotes for important callouts</li>
                            </ul>

                            <blockquote>
                                Pro tip: Keep your paragraphs concise and focused. Break up long sections with subheadings to improve readability.
                            </blockquote>

                            <h2>Conclusion</h2>
                            <p>
                                Wrap up your article with a summary and call to action. Encourage readers to engage, share, or contact you for more information.
                            </p>
                        </div>
                        <?php /* CMS:BLOCK name=content end */ ?>

                        <div class="mt-12 pt-8 border-t border-gray-200">
                            <div class="flex items-center gap-4">
                                <span class="font-semibold text-gray-700">Share this article:</span>
                                <div class="flex gap-3">
                                    <a href="#" class="w-10 h-10 bg-gray-100 hover:bg-primary hover:text-white rounded-full flex items-center justify-center transition">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path></svg>
                                    </a>
                                    <a href="#" class="w-10 h-10 bg-gray-100 hover:bg-primary hover:text-white rounded-full flex items-center justify-center transition">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-2-2 2 2 0 00-2 2v7h-4v-7a6 6 0 016-6zM2 9h4v12H2z"></path><circle cx="4" cy="4" r="2"></circle></svg>
                                    </a>
                                    <a href="#" class="w-10 h-10 bg-gray-100 hover:bg-primary hover:text-white rounded-full flex items-center justify-center transition">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <aside class="lg:col-span-4">
                        <div class="sticky top-24 space-y-8">
                            <div class="bg-gray-50 rounded-2xl p-6">
                                <h3 class="font-heading font-bold text-lg mb-4">About the Author</h3>
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="w-16 h-16 bg-gray-200 rounded-full"></div>
                                    <div>
                                        <div class="font-semibold">Dev Team</div>
                                        <div class="text-sm text-gray-500">Magento Developer</div>
                                    </div>
                                </div>
                                <p class="text-gray-600 text-sm">
                                    Part of the Developers Alliance team, specializing in Magento and Adobe Commerce development.
                                </p>
                            </div>

                            <div class="bg-gradient-to-br from-primary to-red-600 rounded-2xl p-6 text-white">
                                <h3 class="font-heading font-bold text-xl mb-3">Need Help with Magento?</h3>
                                <p class="text-white/90 mb-4 text-sm">
                                    Our certified developers are ready to help with your e-commerce project.
                                </p>
                                <a href="/#contacts" class="btn btn-sm bg-white text-primary hover:bg-gray-100 border-0 w-full">
                                    Get a Free Quote
                                </a>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </article>
    </main>

    <?php /* CMS:BLOCK name=footer start */ ?>
    <footer class="bg-black text-white py-12">
        <div class="container mx-auto px-4">
            <nav class="hidden md:flex justify-center mb-8">
                <ul class="menu menu-horizontal">
                    <li><a href="/privacy" class="hover:text-primary">Privacy Policy</a></li>
                    <li><a href="/terms" class="hover:text-primary">Terms & Conditions</a></li>
                    <li><a href="/#contacts" class="hover:text-primary">Contact Us</a></li>
                </ul>
            </nav>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-center">
                <div class="text-center md:text-left">
                    <img src="/assets/img/logo-light.png" alt="Developers Alliance" class="h-8 w-auto object-contain opacity-50 mx-auto md:mx-0">
                </div>
                <div class="text-center">
                    <p class="opacity-75">© Copyright <?php echo date('Y'); ?> Developers Alliance LLC. All Rights Reserved.</p>
                </div>
                <div class="flex justify-center md:justify-end gap-4">
                    <a href="https://twitter.com/developersall" class="hover:text-primary"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path></svg></a>
                    <a href="https://www.facebook.com/devalliance" class="hover:text-primary"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path></svg></a>
                    <a href="https://www.instagram.com/developersall/" class="hover:text-primary"><svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01M6.5 3h11A3.5 3.5 0 0121 6.5v11a3.5 3.5 0 01-3.5 3.5h-11A3.5 3.5 0 013 17.5v-11A3.5 3.5 0 016.5 3z" stroke="currentColor" stroke-width="2" fill="none"></path></svg></a>
                </div>
            </div>
        </div>
    </footer>
    <?php /* CMS:BLOCK name=footer end */ ?>
</body>
</html>
