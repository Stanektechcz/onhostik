<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Support\PublicSeo;
use Illuminate\Http\Response;

/** N15: /sitemap.xml with the public, indexable URLs only (read-only, no data of any customer). */
final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        return response(PublicSeo::sitemap(), 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
