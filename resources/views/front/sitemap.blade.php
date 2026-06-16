<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($staticUrls as $url)
    <url>
        <loc>{{ url($url) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>{{ $url === '/' ? '1.0' : '0.8' }}</priority>
    </url>
    @endforeach
    @foreach($blogPosts as $post)
    <url>
        <loc>{{ url('/blog/' . $post->slug) }}</loc>
        <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach
    @foreach($kbArticles as $article)
    <url>
        <loc>{{ url('/znalostni-baze/' . $article->slug) }}</loc>
        <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach
</urlset>
