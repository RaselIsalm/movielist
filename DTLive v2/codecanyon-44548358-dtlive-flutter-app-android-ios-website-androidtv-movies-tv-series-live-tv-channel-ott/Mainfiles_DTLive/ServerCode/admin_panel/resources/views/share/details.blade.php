<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} | Link Viral</title>
    <link rel="canonical" href="{{ $url }}">
    <meta name="robots" content="index,follow">

    <!-- Primary Meta Tags -->
    <meta name="title" content="{{ $title }}">
    <meta name="description" content="{{ $description }}">

    <!-- Open Graph / Facebook -->
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:type" content="video.other">
    <meta property="og:site_name" content="Link Viral">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }} | Link Viral">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => $content_type ?? 'VideoObject',
            'name' => $title,
            'description' => $description,
            'url' => $url,
            'image' => [$image],
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => 'Link Viral',
                'url' => rtrim(config('app.seo_site_url'), '/') . '/',
            ],
        ];
    @endphp
    <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
    <p><a href="{{ $redirect_url }}">Open this title on Link Viral</a></p>
</main>
</body>
</html>
