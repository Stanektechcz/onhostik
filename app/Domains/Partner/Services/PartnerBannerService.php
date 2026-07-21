<?php

declare(strict_types=1);

namespace App\Domains\Partner\Services;

/**
 * Generates ready-to-embed referral banners for partners (audit 112).
 *
 * The "Propagační materiály" card was a "coming soon" placeholder. This turns
 * it into something a partner can actually use: brand banners in the standard
 * IAB ad sizes, each already wrapping the partner's own referral link, with a
 * copy-paste embed snippet and a download.
 *
 * Banners are inline SVG — text, not binary — so there is no image library,
 * no asset pipeline, and nothing to host. The only dynamic value is the
 * referral URL, which appears solely inside an anchor's href and is escaped for
 * that attribute; the SVG artwork itself carries no caller-supplied data, so
 * there is nothing to inject through it.
 */
final class PartnerBannerService
{
    /**
     * Standard IAB sizes worth shipping: label, width, height, headline scale.
     *
     * @var list<array{label: string, width: int, height: int}>
     */
    private const SIZES = [
        ['label' => 'Leaderboard',        'width' => 728, 'height' => 90],
        ['label' => 'Obdélník',           'width' => 300, 'height' => 250],
        ['label' => 'Skyscraper',         'width' => 160, 'height' => 600],
        ['label' => 'Full banner',        'width' => 468, 'height' => 60],
        ['label' => 'Mobilní banner',     'width' => 320, 'height' => 100],
    ];

    /**
     * @return list<array{label: string, size: string, width: int, height: int, svg: string, embed: string, download: string}>
     */
    public function generate(string $referralUrl): array
    {
        $banners = [];

        foreach (self::SIZES as $spec) {
            $svg = $this->renderSvg($spec['width'], $spec['height']);

            $banners[] = [
                'label'    => $spec['label'],
                'size'     => $spec['width'] . '×' . $spec['height'],
                'width'    => $spec['width'],
                'height'   => $spec['height'],
                'svg'      => $svg,
                'embed'    => $this->embedSnippet($referralUrl, $svg),
                'download' => 'data:image/svg+xml;base64,' . base64_encode($svg),
            ];
        }

        return $banners;
    }

    /** The copy-paste block: an anchor to the referral URL wrapping the SVG. */
    private function embedSnippet(string $referralUrl, string $svg): string
    {
        // href goes into an HTML attribute — escape it there. rel="sponsored"
        // is the correct disclosure for a paid referral link.
        $href = htmlspecialchars($referralUrl, ENT_QUOTES, 'UTF-8');

        return '<a href="' . $href . '" target="_blank" rel="noopener sponsored">' . $svg . '</a>';
    }

    private function renderSvg(int $w, int $h): string
    {
        // Brand: Cuba theme-default #7366FF → a slightly darker stop for depth.
        $isTall  = $h > $w;               // skyscraper — stack vertically
        $isSmall = $h <= 100;             // thin banners — single line, small CTA

        $titleSize = $isSmall ? 18 : ($isTall ? 22 : 30);
        $subSize   = $isSmall ? 10 : 13;
        $pad       = $isSmall ? 12 : 20;

        // Layout coordinates differ for the tall skyscraper vs wide/square.
        if ($isTall) {
            $titleY = 90;
            $subY   = 120;
            $ctaY   = $h - 70;
            $textAnchor = 'middle';
            $textX = $w / 2;
        } else {
            $titleY = $isSmall ? $h / 2 : $h / 2 - 6;
            $subY   = $isSmall ? $h - 14 : $h / 2 + 16;
            $ctaY   = $h / 2 - 16;
            $textAnchor = 'start';
            $textX = $pad;
        }

        $ctaW = $isSmall ? 96 : 150;
        $ctaH = 34;
        $ctaX = $isTall ? ($w - $ctaW) / 2 : ($w - $ctaW - $pad);
        $ctaTextX = $ctaX + $ctaW / 2;
        $ctaTextY = $ctaY + $ctaH / 2 + 5;

        $sub = $isSmall ? '' : '<text x="' . $textX . '" y="' . $subY . '" fill="#E7E3FF" '
            . 'font-family="Segoe UI, Arial, sans-serif" font-size="' . $subSize . '" text-anchor="' . $textAnchor . '">'
            . 'Rychlý a spolehlivý hosting</text>';

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}" role="img" aria-label="OnHost.cz">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#7366FF"/>
      <stop offset="1" stop-color="#5A4FD6"/>
    </linearGradient>
  </defs>
  <rect width="{$w}" height="{$h}" rx="8" fill="url(#g)"/>
  <text x="{$textX}" y="{$titleY}" fill="#FFFFFF" font-family="Segoe UI, Arial, sans-serif" font-size="{$titleSize}" font-weight="700" text-anchor="{$textAnchor}">OnHost.cz</text>
  {$sub}
  <rect x="{$ctaX}" y="{$ctaY}" width="{$ctaW}" height="{$ctaH}" rx="17" fill="#FFFFFF"/>
  <text x="{$ctaTextX}" y="{$ctaTextY}" fill="#5A4FD6" font-family="Segoe UI, Arial, sans-serif" font-size="13" font-weight="600" text-anchor="middle">Vyzkoušet</text>
</svg>
SVG;
    }
}
