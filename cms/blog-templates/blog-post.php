<?php /* POST_STATUS: draft */ ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php /* CMS:BLOCK name=meta_title role=meta custom=1 system=1 start */ ?>
    <title>{{POST_TITLE}} - Developers Alliance Blog</title>
    <?php /* CMS:BLOCK name=meta_title end */ ?>
    <?php /* CMS:BLOCK name=meta_description role=meta custom=1 system=1 start */ ?>
    <meta name="description" content="{{POST_EXCERPT}}">
    <?php /* CMS:BLOCK name=meta_description end */ ?>
    <?php /* CMS:BLOCK name=meta_author role=meta custom=1 system=1 start */ ?>
    <meta name="author" content="{{POST_AUTHOR}}">
    <meta name="date" content="{{POST_DATE}}">
    <?php /* CMS:BLOCK name=meta_author end */ ?>
    <?php /* CMS:BLOCK name=meta_canonical role=meta custom=1 system=1 start */ ?>
    <link rel="canonical" href="https://developers-alliance.com/blog/{{POST_SLUG}}/">
    <?php /* CMS:BLOCK name=meta_canonical end */ ?>
    <link rel="icon" type="image/png" href="/assets/img/favicon.png">

    <?php /* CMS:BLOCK name=meta_og role=meta custom=1 system=1 start */ ?>
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://developers-alliance.com/blog/{{POST_SLUG}}/">
    <meta property="og:title" content="{{POST_TITLE}} - Developers Alliance Blog">
    <meta property="og:description" content="{{POST_EXCERPT}}">
    <meta property="og:image" content="https://developers-alliance.com/img/devall-raft-sc.jpg">
    <meta property="article:published_time" content="{{POST_DATE}}">
    <meta property="article:author" content="{{POST_AUTHOR}}">
    <?php /* CMS:BLOCK name=meta_og end */ ?>

    <?php /* CMS:BLOCK name=meta_twitter role=meta custom=1 system=1 start */ ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{POST_TITLE}}">
    <meta name="twitter:description" content="{{POST_EXCERPT}}">
    <meta name="twitter:image" content="https://developers-alliance.com/img/devall-raft-sc.jpg">
    <?php /* CMS:BLOCK name=meta_twitter end */ ?>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700&family=Raleway:wght@300;400;500;600;700&family=Open+Sans:wght@400;500;600&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & DaisyUI -->
    <link rel="stylesheet" href="/assets/css/styles.css">

    <?php /* CMS:BLOCK name=structured_data role=meta custom=1 system=1 start */ ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BlogPosting",
        "headline": "{{POST_TITLE}}",
        "description": "{{POST_EXCERPT}}",
        "datePublished": "{{POST_DATE}}",
        "dateModified": "{{POST_DATE}}",
        "author": {
            "@type": "Person",
            "name": "{{POST_AUTHOR}}"
        },
        "publisher": {
            "@type": "Organization",
            "name": "Developers Alliance",
            "logo": {
                "@type": "ImageObject",
                "url": "https://developers-alliance.com/img/logo-dark.png"
            }
        },
        "mainEntityOfPage": {
            "@type": "WebPage",
            "@id": "https://developers-alliance.com/blog/{{POST_SLUG}}/"
        }
    }
    </script>
    <?php /* CMS:BLOCK name=structured_data end */ ?>

    <style>
        /* Editorial Typography System */
        .article-content {
            font-family: 'Open Sans', system-ui, sans-serif;
            font-size: 1.125rem;
            line-height: 1.9;
            color: #1f2937;
            letter-spacing: -0.01em;
        }

        .article-content > *:first-child { margin-top: 0; }

        .article-content h2 {
            font-family: 'Titillium Web', sans-serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 3rem;
            margin-bottom: 1.25rem;
            letter-spacing: -0.02em;
            position: relative;
            padding-bottom: 0.75rem;
        }

        .article-content h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #EE1C25, #f87171);
            border-radius: 2px;
        }

        .article-content h3 {
            font-family: 'Titillium Web', sans-serif;
            font-size: 1.375rem;
            font-weight: 600;
            color: #1e293b;
            margin-top: 2.25rem;
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        .article-content h4 {
            font-family: 'Titillium Web', sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: #334155;
            margin-top: 1.75rem;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .article-content p {
            margin-bottom: 1.75rem;
        }

        .article-content .lead-paragraph {
            font-size: 1.375rem;
            line-height: 1.7;
            color: #475569;
            font-weight: 400;
            margin-bottom: 2.5rem;
            border-left: 4px solid #e2e8f0;
            padding-left: 1.5rem;
        }

        .article-content ul, .article-content ol {
            margin-bottom: 1.75rem;
            padding-left: 1.75rem;
        }

        .article-content li {
            margin-bottom: 0.625rem;
            position: relative;
        }

        .article-content ul li::marker {
            color: #EE1C25;
        }

        .article-content ol li::marker {
            color: #EE1C25;
            font-weight: 600;
        }

        .article-content blockquote {
            position: relative;
            margin: 2.5rem 0;
            padding: 1.75rem 2rem 1.75rem 2.5rem;
            background: linear-gradient(135deg, #fef2f2 0%, #fff7ed 100%);
            border-radius: 0 1rem 1rem 0;
            border-left: 4px solid #EE1C25;
            font-style: italic;
            color: #374151;
        }

        .article-content blockquote::before {
            content: '"';
            position: absolute;
            top: -0.25rem;
            left: 0.75rem;
            font-family: 'Playfair Display', serif;
            font-size: 4rem;
            color: #EE1C25;
            opacity: 0.2;
            line-height: 1;
        }

        .article-content blockquote p:last-child { margin-bottom: 0; }

        .article-content code {
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            background: #f1f5f9;
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875em;
            color: #be123c;
            border: 1px solid #e2e8f0;
        }

        .article-content pre {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            color: #e2e8f0;
            padding: 1.75rem;
            border-radius: 1rem;
            overflow-x: auto;
            margin: 2rem 0;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.05);
            position: relative;
        }

        .article-content pre::before {
            content: '';
            position: absolute;
            top: 1rem;
            left: 1.25rem;
            width: 12px;
            height: 12px;
            background: #ef4444;
            border-radius: 50%;
            box-shadow: 20px 0 0 #fbbf24, 40px 0 0 #22c55e;
        }

        .article-content pre code {
            background: transparent;
            padding: 0;
            color: inherit;
            border: none;
            font-size: 0.875rem;
            display: block;
            margin-top: 1rem;
        }

        .article-content a:not(.btn) {
            color: #EE1C25;
            text-decoration: none;
            border-bottom: 1px solid rgba(238, 28, 37, 0.3);
            transition: border-color 0.2s ease;
        }

        .article-content a:not(.btn):hover {
            border-bottom-color: #EE1C25;
        }

        .article-content img {
            border-radius: 1rem;
            margin: 2.5rem 0;
            box-shadow: 0 20px 60px -15px rgba(0,0,0,0.15);
        }

        .article-content figure {
            margin: 2.5rem 0;
        }

        .article-content figcaption {
            text-align: center;
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.75rem;
            font-style: italic;
        }

        .article-content hr {
            border: none;
            height: 1px;
            background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
            margin: 3rem 0;
        }

        .article-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            font-size: 0.9375rem;
        }

        .article-content th {
            background: #f8fafc;
            padding: 0.875rem 1rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .article-content td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .article-content tr:hover td {
            background: #fafafa;
        }

        /* Reading Progress Indicator */
        .reading-progress {
            position: fixed;
            top: 70px;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, #EE1C25, #f97316);
            z-index: 100;
            transition: width 0.1s ease-out;
        }
    </style>
</head>
<body class="font-body">
    <!-- Reading Progress Bar -->
    <div class="reading-progress" id="readingProgress"></div>

    <?php /* CMS:BLOCK name=header start */ ?>
    <!-- Navigation -->
    <nav class="navbar bg-white shadow-md fixed top-0 z-50" role="navigation" aria-label="Main navigation">
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
                        <li>
                            <details>
                                <summary>Services</summary>
                                <ul>
                                    <li><a href="/services/">All Services</a></li>
                                    <li><a href="/services/magento-development/">Magento Development</a></li>
                                    <li><a href="/services/seo/">SEO Services</a></li>
                                    <li><a href="/services/hyva-theme-development/">Hyva Theme</a></li>
                                    <li><a href="/services/magento-migration/">Magento Migration</a></li>
                                </ul>
                            </details>
                        </li>
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

    <main style="padding-top: 70px;">
        <?php /* CMS:BLOCK name=article_header custom=1 start */ ?>
        <!-- Article Header -->
        <section class="bg-gradient-to-b from-slate-50 to-white">
            <div class="container mx-auto px-4">
                <!-- Breadcrumb -->
                <nav class="pt-6 pb-6" aria-label="Breadcrumb">
                    <ol class="flex items-center gap-2 text-sm">
                        <li><a href="/" class="text-gray-600 hover:text-[#EE1C25] transition-colors">Home</a></li>
                        <li><span class="text-gray-400">/</span></li>
                        <li><a href="/blog/" class="text-gray-600 hover:text-[#EE1C25] transition-colors">Blog</a></li>
                        <li><span class="text-gray-400">/</span></li>
                        <li class="text-gray-900 font-medium truncate max-w-xs">{{POST_TITLE}}</li>
                    </ol>
                </nav>

                <div class="max-w-4xl pb-12 md:pb-16">
                    <!-- Category Badge -->
                    <div class="mb-6">
                        <span class="inline-block px-3 py-1 text-xs font-bold uppercase tracking-wider rounded-full" style="background: rgba(238, 28, 37, 0.1); color: #EE1C25;">
                            {{POST_CATEGORY}}
                        </span>
                    </div>

                    <!-- Title -->
                    <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-6 leading-tight text-gray-900" style="font-family: 'Titillium Web', sans-serif;">
                        {{POST_TITLE}}
                    </h1>

                    <!-- Excerpt -->
                    <p class="text-lg md:text-xl text-gray-600 mb-8 leading-relaxed">
                        {{POST_EXCERPT}}
                    </p>

                    <!-- Meta Bar -->
                    <div class="flex flex-wrap items-center gap-6 py-6 border-t border-b border-gray-200">
                        <!-- Author -->
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900 text-sm">{{POST_AUTHOR}}</div>
                                <div class="text-xs text-gray-500">Author</div>
                            </div>
                        </div>

                        <span class="hidden sm:block text-gray-300">|</span>

                        <!-- Date -->
                        <div class="flex items-center gap-2 text-gray-600 text-sm">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            <span>{{POST_DATE_FORMATTED}}</span>
                        </div>

                        <span class="hidden sm:block text-gray-300">|</span>

                        <!-- Read Time -->
                        <div class="flex items-center gap-2 text-gray-600 text-sm">
                            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span>{{POST_READING_TIME}} min read</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php /* CMS:BLOCK name=article_header end */ ?>

        <!-- Article Content -->
        <article class="py-12 md:py-20 bg-white">
            <div class="container mx-auto px-4">
                <div class="grid lg:grid-cols-12 gap-12">
                    <!-- Main Content -->
                    <div class="lg:col-span-8">
                        <?php /* CMS:BLOCK name=content custom=1 start */ ?>
                        <div class="article-content">
                            <h2>Introduction</h2>
                            <p>
                                Write your content here. This template supports full HTML formatting including headings, lists, code blocks, images, and more. Start with a compelling introduction that hooks your reader.
                            </p>

                            <h2>Main Topic</h2>
                            <p>
                                Develop your main argument or explanation here. Use clear, concise language and break complex topics into digestible sections.
                            </p>

                            <h3>Key Points</h3>
                            <p>
                                Support your main topic with detailed explanations:
                            </p>
                            <ul>
                                <li><strong>First point</strong> - Explain the concept clearly</li>
                                <li><strong>Second point</strong> - Provide examples or evidence</li>
                                <li><strong>Third point</strong> - Connect to practical applications</li>
                                <li><strong>Fourth point</strong> - Address common questions</li>
                            </ul>

                            <blockquote>
                                <p>Pro tip: Keep your paragraphs concise and focused. Break up long sections with subheadings to improve readability and help readers find information quickly.</p>
                            </blockquote>

                            <h3>Code Example</h3>
                            <p>
                                When including code examples, use proper formatting:
                            </p>
                            <pre><code>// Example Magento 2 code
public function execute()
{
    $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
    return $result->setData(['success' => true]);
}</code></pre>

                            <h2>Best Practices</h2>
                            <p>
                                Share actionable recommendations that readers can implement immediately. Use numbered lists for step-by-step processes:
                            </p>
                            <ol>
                                <li>Start with a clear goal in mind</li>
                                <li>Research existing solutions and patterns</li>
                                <li>Plan your implementation approach</li>
                                <li>Test thoroughly before deployment</li>
                                <li>Monitor and iterate based on results</li>
                            </ol>

                            <h2>Conclusion</h2>
                            <p>
                                Wrap up your article with a summary of key takeaways and a clear call to action. Encourage readers to engage, share, or contact you for more information about implementing these strategies in their projects.
                            </p>
                        </div>
                        <?php /* CMS:BLOCK name=content end */ ?>

                        <!-- Article Footer -->
                        <div class="mt-12 pt-8 border-t border-gray-200">
                            <!-- Tags -->
                            <div class="flex flex-wrap items-center gap-3 mb-6">
                                <span class="text-sm font-semibold text-gray-700">Tags:</span>
                                {{POST_TAGS}}
                            </div>

                            <!-- Back Link -->
                            <a href="/blog/" class="inline-flex items-center gap-2 text-gray-600 hover:text-[#EE1C25] font-medium text-sm transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                </svg>
                                Back to Articles
                            </a>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <aside class="lg:col-span-4">
                        <div class="sticky space-y-6" style="top: 90px;">
                            <!-- Author Box -->
                            <div class="bg-gray-50 rounded-xl p-5 border border-gray-100">
                                <h3 class="font-bold text-base mb-4 text-gray-900" style="font-family: 'Titillium Web', sans-serif;">About the Author</h3>
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900 text-sm">{{POST_AUTHOR}}</div>
                                        <div class="text-xs text-gray-500">Developer</div>
                                    </div>
                                </div>
                                <p class="text-gray-600 text-sm leading-relaxed">
                                    Part of the Developers Alliance team, specializing in e-commerce development.
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </article>

    </main>

    <?php /* CMS:BLOCK name=footer start */ ?>
    <!-- Footer -->
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
                    <a href="https://twitter.com/developersall" class="hover:text-primary transition" aria-label="Twitter">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"></path></svg>
                    </a>
                    <a href="https://www.facebook.com/devalliance" class="hover:text-primary transition" aria-label="Facebook">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"></path></svg>
                    </a>
                    <a href="https://www.instagram.com/developersall/" class="hover:text-primary transition" aria-label="Instagram">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zm1.5-4.87h.01M6.5 3h11A3.5 3.5 0 0121 6.5v11a3.5 3.5 0 01-3.5 3.5h-11A3.5 3.5 0 013 17.5v-11A3.5 3.5 0 016.5 3z" stroke="currentColor" stroke-width="2" fill="none"></path></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>
    <?php /* CMS:BLOCK name=footer end */ ?>

    <!-- Reading Progress Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.getElementById('readingProgress');
            const article = document.querySelector('article');

            if (progressBar && article) {
                window.addEventListener('scroll', function() {
                    const articleRect = article.getBoundingClientRect();
                    const articleTop = window.scrollY + articleRect.top;
                    const articleHeight = articleRect.height;
                    const windowHeight = window.innerHeight;
                    const scrolled = window.scrollY - articleTop + windowHeight;
                    const progress = Math.min(Math.max(scrolled / articleHeight * 100, 0), 100);
                    progressBar.style.width = progress + '%';
                });
            }
        });
    </script>
</body>
</html>
