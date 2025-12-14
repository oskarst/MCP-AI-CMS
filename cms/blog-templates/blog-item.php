<article class="bg-white rounded-2xl shadow-xl overflow-hidden hover:shadow-2xl transition-shadow duration-300">
    <div class="grid md:grid-cols-2">
        <a href="/{COLLECTION_BASE_PATH}/{SLUG}/" class="block overflow-hidden">
            <img src="{FEATURED_IMAGE}" alt="{TITLE}" class="w-full h-full object-cover hover:scale-105 transition-transform duration-300" onerror="this.src='/assets/img/blog-placeholder.jpg'">
        </a>
        <div class="p-8 md:p-10 flex flex-col justify-center">
            <div class="flex items-center gap-3 mb-4">
                {FEATURED_BADGE}
                <span class="text-gray-500 text-sm">{DATE_FORMATTED}</span>
                <span class="text-gray-300">•</span>
                <span class="text-gray-500 text-sm">{READING_TIME} min read</span>
            </div>
            <h2 class="text-xl md:text-2xl font-heading font-bold mb-3 text-gray-900 hover:text-primary transition">
                <a href="/{COLLECTION_BASE_PATH}/{SLUG}/">{TITLE}</a>
            </h2>
            <p class="text-gray-600 mb-6 leading-relaxed line-clamp-3">{EXCERPT}</p>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-200 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-semibold text-gray-900 text-sm">{AUTHOR_NAME}</div>
                    </div>
                </div>
                <a href="/{COLLECTION_BASE_PATH}/{SLUG}/" class="inline-flex items-center gap-1.5 text-primary font-medium text-sm group/link">
                    <span>Read More</span>
                    <svg class="w-4 h-4 transition-transform group-hover/link:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</article>
