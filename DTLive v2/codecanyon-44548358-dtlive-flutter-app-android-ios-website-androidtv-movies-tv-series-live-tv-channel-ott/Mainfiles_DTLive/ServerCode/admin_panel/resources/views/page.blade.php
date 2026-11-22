@php
    $pageTitle = (string) ($result['title'] ?? 'Link Viral');
    $pageDescription = trim(strip_tags((string) ($result['description'] ?? '')));
    $pageDescription = \Illuminate\Support\Str::limit($pageDescription, 160, '');
    $seoSiteUrl = rtrim(config('app.seo_site_url'), '/');
    $pageUrl = $seoSiteUrl . '/page/' . rawurlencode($pageTitle);
    $pageImage = $seoSiteUrl . '/assets/imgs/logo.png';
    $pageStructuredData = [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $pageTitle,
        'description' => $pageDescription,
        'url' => $pageUrl,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'Link Viral',
            'url' => $seoSiteUrl . '/',
        ],
    ];
@endphp
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle }} | Link Viral</title>
        <meta name="description" content="{{ $pageDescription }}">
        <meta name="robots" content="index,follow">
        <link rel="canonical" href="{{ $pageUrl }}">
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="Link Viral">
        <meta property="og:title" content="{{ $pageTitle }} | Link Viral">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ $pageUrl }}">
        <meta property="og:image" content="{{ $pageImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }} | Link Viral">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ $pageImage }}">
        <script type="application/ld+json">{!! json_encode($pageStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

        <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">

        <style type="text/css">
            :root {
                --title-color: {{ $settings['page_title_color'] }};
                --background-color: {{ $settings['page_background_color'] }};
            }
            body {
                font-family: 'Rubik', sans-serif;
                background-color: var(--background-color);
                margin: 0px;
            }
            .page-title{
                text-align: center;
                margin: 15px;
                font-size: 36px;
                color: var(--title-color);
                border-bottom: 2px solid var(--title-color);
            }
            .description{
                margin: 15px;
            }
        </style>
    </head>

    <body>
        <h1 class="page-title">{{ $pageTitle }}</h1>
        <div class="description">
            <?php echo htmlspecialchars_decode($result['description']); ?>
        </div>
    </body>
</html>
