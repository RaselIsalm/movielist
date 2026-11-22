<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Shorts;
use App\Models\TVShow;
use App\Models\Video;
use App\Models\Common;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SeoController extends Controller
{
    private string $contentFolder = 'content';

    public function __construct(private readonly Common $common)
    {
    }

    /**
     * Return public page metadata for the Flutter shell to apply to the
     * document before/while the route is rendered. No user or purchase data
     * is exposed by this endpoint.
     */
    public function metadata(Request $request): JsonResponse
    {
        $path = '/' . ltrim((string) $request->query('path', '/'), '/');
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        if ($segments === []) {
            return response()->json($this->siteMetadata());
        }

        $metadata = match ($segments[0]) {
            'details' => $this->detailsMetadata($segments),
            'shorts' => $this->shortsMetadata($segments),
            'page' => $this->pageMetadata($segments),
            default => null,
        };

        if ($metadata === null) {
            return response()->json(['message' => 'SEO metadata not found'], 404);
        }

        return response()->json($metadata);
    }

    /**
     * Build a sitemap from active public content. The Flutter web build hosts
     * a sitemap index which points here, so new catalog records are included
     * without committing generated database output to Git.
     */
    public function sitemap(): Response
    {
        $urls = [
            $this->siteUrl('/'),
            $this->siteUrl('/search'),
        ];

        Page::query()
            ->where('status', 1)
            ->orderBy('id')
            ->get(['title', 'updated_at'])
            ->each(function (Page $page) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->siteUrl('/page/' . rawurlencode($page->title)),
                    'lastmod' => $this->lastModified($page->updated_at),
                ];
            });

        Video::query()
            ->where('status', 1)
            ->whereIn('video_type', [1, 5, 6, 7])
            ->orderBy('id')
            ->get(['id', 'type_id', 'video_type', 'updated_at'])
            ->each(function (Video $video) use (&$urls): void {
                $subVideoType = in_array((int) $video->video_type, [5, 6, 7], true) ? 1 : null;
                $path = $this->contentPath(
                    'details',
                    (int) $video->video_type,
                    (int) $video->type_id,
                    (int) $video->id,
                    $subVideoType,
                );
                $urls[] = [
                    'loc' => $this->siteUrl($path),
                    'lastmod' => $this->lastModified($video->updated_at),
                ];
            });

        TVShow::query()
            ->where('status', 1)
            ->whereIn('video_type', [2, 6, 7])
            ->orderBy('id')
            ->get(['id', 'type_id', 'video_type', 'updated_at'])
            ->each(function (TVShow $show) use (&$urls): void {
                $subVideoType = in_array((int) $show->video_type, [6, 7], true) ? 2 : null;
                $path = $this->contentPath(
                    'details',
                    (int) $show->video_type,
                    (int) $show->type_id,
                    (int) $show->id,
                    $subVideoType,
                );
                $urls[] = [
                    'loc' => $this->siteUrl($path),
                    'lastmod' => $this->lastModified($show->updated_at),
                ];
            });

        Shorts::query()
            ->where('status', 1)
            ->where('video_type', 8)
            ->orderBy('id')
            ->get(['id', 'type_id', 'video_type', 'updated_at'])
            ->each(function (Shorts $short) use (&$urls): void {
                $urls[] = [
                    'loc' => $this->siteUrl($this->contentPath(
                        'shorts',
                        (int) $short->video_type,
                        (int) $short->type_id,
                        (int) $short->id,
                    )),
                    'lastmod' => $this->lastModified($short->updated_at),
                ];
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $entry) {
            $entry = is_string($entry) ? ['loc' => $entry] : $entry;
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . e($entry['lastmod']) . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= "</urlset>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=900',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function detailsMetadata(array $segments): ?array
    {
        if (count($segments) < 4 || count($segments) > 5) {
            return null;
        }

        [$type, $typeId, $id] = array_map('intval', array_slice($segments, 1, 3));
        $subVideoType = isset($segments[4]) ? (int) $segments[4] : 0;

        $data = null;
        if ($type === 1) {
            $data = Video::where('id', $id)->where('video_type', $type)
                ->where('type_id', $typeId)->where('status', 1)->first();
        } elseif ($type === 2) {
            $data = TVShow::where('id', $id)->where('video_type', $type)
                ->where('type_id', $typeId)->where('status', 1)->first();
        } elseif (in_array($type, [5, 6, 7], true) && $subVideoType === 1) {
            $data = Video::where('id', $id)->where('video_type', $type)
                ->where('type_id', $typeId)->where('status', 1)->first();
        } elseif (in_array($type, [5, 6, 7], true) && $subVideoType === 2) {
            $data = TVShow::where('id', $id)->where('video_type', $type)
                ->where('type_id', $typeId)->where('status', 1)->first();
        }

        if ($data === null) {
            return null;
        }

        $path = $this->contentPath('details', $type, $typeId, $id, $subVideoType ?: null);
        $title = trim((string) ($data->name ?? '')) ?: 'Link Viral';
        $description = $this->description($data->description ?? null);
        $image = $data->thumbnail
            ? $this->common->getImage($this->contentFolder, $data->thumbnail, 'normal', $data->storage_type)
            : asset('assets/imgs/logo.png');

        return $this->contentMetadata(
            $title,
            $description,
            $this->siteUrl($path),
            $image,
            $type === 2 || $subVideoType === 2 ? 'TVSeries' : 'Movie',
        );
    }

    private function shortsMetadata(array $segments): ?array
    {
        if (count($segments) !== 4) {
            return null;
        }

        [$type, $typeId, $id] = array_map('intval', array_slice($segments, 1, 3));
        $data = Shorts::where('id', $id)->where('video_type', $type)
            ->where('type_id', $typeId)->where('status', 1)->first();
        if ($data === null) {
            return null;
        }

        $title = trim((string) ($data->name ?? '')) ?: 'Link Viral Shorts';
        $image = $data->thumbnail
            ? $this->common->getImage($this->contentFolder, $data->thumbnail, 'normal', $data->storage_type)
            : asset('assets/imgs/logo.png');

        return $this->contentMetadata(
            $title,
            $this->description($data->description ?? null),
            $this->siteUrl($this->contentPath('shorts', $type, $typeId, $id)),
            $image,
            'VideoObject',
        );
    }

    private function pageMetadata(array $segments): ?array
    {
        if (count($segments) !== 2) {
            return null;
        }

        $page = Page::where('title', rawurldecode($segments[1]))
            ->where('status', 1)->first();
        if ($page === null) {
            return null;
        }

        $canonical = $this->siteUrl('/page/' . rawurlencode($page->title));
        return [
            'title' => trim((string) $page->title) . ' | Link Viral',
            'description' => $this->description($page->description),
            'content' => (string) ($page->description ?? ''),
            'subtitle' => (string) ($page->page_subtitle ?? ''),
            'canonical' => $canonical,
            'image' => asset('assets/imgs/logo.png'),
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => (string) $page->title,
                'description' => $this->description($page->description),
                'url' => $canonical,
            ],
        ];
    }

    private function siteMetadata(): array
    {
        $canonical = $this->siteUrl('/');
        return [
            'title' => 'Link Viral | Movies, TV Series and Live Entertainment',
            'description' => 'Watch movies, TV series, live TV and short videos on Link Viral.',
            'canonical' => $canonical,
            'image' => $this->siteUrl('/icons/Icon-192.png'),
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => 'Link Viral',
                'alternateName' => ['LinkViral', 'linkviral.seedintrade.com'],
                'url' => $canonical,
            ],
        ];
    }

    private function contentMetadata(
        string $title,
        string $description,
        string $canonical,
        string $image,
        string $contentType,
    ): array {
        return [
            'title' => $title . ' | Link Viral',
            'description' => $description,
            'canonical' => $canonical,
            'image' => $image,
            'structured_data' => [
                '@context' => 'https://schema.org',
                '@type' => $contentType,
                'name' => $title,
                'description' => $description,
                'url' => $canonical,
                'image' => [$image],
                'isPartOf' => [
                    '@type' => 'WebSite',
                    'name' => 'Link Viral',
                    'url' => $this->siteUrl('/'),
                ],
            ],
        ];
    }

    private function description(?string $description): string
    {
        $description = trim(strip_tags((string) $description));
        return Str::limit($description ?: 'Watch this title on Link Viral.', 155, '');
    }

    private function contentPath(string $kind, int $type, int $typeId, int $id, ?int $subType = null): string
    {
        $path = "/{$kind}/{$type}/{$typeId}/{$id}";
        return $subType === null ? $path : $path . "/{$subType}";
    }

    private function siteUrl(string $path): string
    {
        $base = rtrim((string) config(
            'app.seo_site_url',
            'https://linkviral.seedintrade.com',
        ), '/');
        return $base . '/' . ltrim($path, '/');
    }

    private function lastModified($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return method_exists($value, 'toAtomString')
            ? $value->toAtomString()
            : date(DATE_ATOM, strtotime((string) $value));
    }
}
