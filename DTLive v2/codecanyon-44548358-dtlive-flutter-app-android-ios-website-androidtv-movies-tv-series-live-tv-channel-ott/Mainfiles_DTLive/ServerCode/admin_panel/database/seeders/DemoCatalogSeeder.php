<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds a complete, safe-to-reseed local catalog for design and integration
 * testing. The video URLs are public sample streams; poster art is generated
 * locally as PNG files so the demo does not depend on a third-party image CDN.
 */
class DemoCatalogSeeder extends Seeder
{
    private const TYPE_ID = 1;

    /** @var array<int, int> */
    private array $categoryIds = [];

    /** @var array<int, int> */
    private array $languageIds = [];

    /** @var array<int, int> */
    private array $castIds = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->prepareFolders();
            $this->seedBranding();
            $this->seedTaxonomy();
            $movieIds = $this->seedMovies();
            $this->seedBanners($movieIds);
            $this->seedHomeSections($movieIds);
            $this->seedShorts();
            $this->seedFooterLinks();
            $this->seedPages();
        });

        $this->command?->info('DTLive demo catalog ready: 20 movies, 6 clips, taxonomy, banners and home sections.');
    }

    private function prepareFolders(): void
    {
        foreach (['app', 'content', 'category', 'language', 'cast'] as $folder) {
            Storage::disk('public')->makeDirectory($folder);
        }
    }

    private function seedBranding(): void
    {
        $this->writePoster('app/demo-logo.png', 'DTLIVE', 'STUDIO', ['#5c6cff', '#13d6b1'], 'wide');

        $this->upsert('tbl_general_setting', ['key' => 'app_name'], [
            'value' => 'DTLive Studio',
        ]);
        $this->upsert('tbl_general_setting', ['key' => 'app_logo_storage_type'], [
            'value' => '1',
        ]);
        $this->upsert('tbl_general_setting', ['key' => 'app_logo'], [
            'value' => 'demo-logo.png',
        ]);
        $this->upsert('tbl_general_setting', ['key' => 'app_desripation'], [
            'value' => 'A polished local OTT demo catalog for testing discovery, playback, watchlists, reviews and responsive layouts.',
        ]);
        $this->upsert('tbl_general_setting', ['key' => 'author'], ['value' => 'DTLive Studio']);
        $this->upsert('tbl_general_setting', ['key' => 'email'], ['value' => 'hello@dtlive.local']);
        $this->upsert('tbl_general_setting', ['key' => 'website'], ['value' => 'http://localhost:8080']);
        $this->upsert('tbl_general_setting', ['key' => 'currency'], ['value' => 'USD']);
        $this->upsert('tbl_general_setting', ['key' => 'currency_code'], ['value' => '$']);
        $this->upsert('tbl_general_setting', ['key' => 'tmdb_status'], ['value' => '0']);
        $this->upsert('tbl_general_setting', ['key' => 'auto_play_trailer'], ['value' => '1']);
        $this->upsert('tbl_general_setting', ['key' => 'watchlist_status'], ['value' => '1']);
        $this->upsert('tbl_general_setting', ['key' => 'continue_watching_status'], ['value' => '1']);
        $this->upsert('tbl_general_setting', ['key' => 'coupon_status'], ['value' => '1']);
        $this->upsert('tbl_general_setting', ['key' => 'rent_status'], ['value' => '1']);
        $this->upsert('tbl_general_setting', ['key' => 'parent_control_status'], ['value' => '1']);
        $this->upsert('tbl_general_setting', ['key' => 'page_background_color'], ['value' => '#070910']);
        $this->upsert('tbl_general_setting', ['key' => 'page_title_color'], ['value' => '#f8fafc']);
        $this->upsert('tbl_general_setting', ['key' => 'video_player_ima_ads_status'], ['value' => '0']);
    }

    private function seedTaxonomy(): void
    {
        DB::table('tbl_type')->updateOrInsert(
            ['id' => self::TYPE_ID],
            $this->timestamps([
                'name' => 'Movies',
                'type' => 1,
                'storage_type' => 1,
                'icon' => '',
                'sort_order' => 1,
                'status' => 1,
            ])
        );

        $categories = [
            ['name' => 'Action', 'colors' => ['#ff5f6d', '#ffc371']],
            ['name' => 'Drama', 'colors' => ['#8e2de2', '#4a00e0']],
            ['name' => 'Comedy', 'colors' => ['#00c6ff', '#0072ff']],
            ['name' => 'Sci-Fi', 'colors' => ['#11998e', '#38ef7d']],
            ['name' => 'Documentary', 'colors' => ['#f7971e', '#ffd200']],
            ['name' => 'Family', 'colors' => ['#ee0979', '#ff6a00']],
        ];
        foreach ($categories as $index => $category) {
            $image = 'demo-category-' . ($index + 1) . '.png';
            $this->writePoster('category/' . $image, $category['name'], 'COLLECTION', $category['colors'], 'square');
            $this->categoryIds[$index + 1] = $this->upsert('tbl_category', ['name' => $category['name']], [
                'storage_type' => 1,
                'image' => $image,
                'sort_order' => $index + 1,
                'status' => 1,
            ]);
        }

        $languages = [
            ['name' => 'English', 'short' => 'EN', 'colors' => ['#1e3c72', '#2a5298']],
            ['name' => 'বাংলা', 'short' => 'BN', 'colors' => ['#134e5e', '#71b280']],
            ['name' => 'Español', 'short' => 'ES', 'colors' => ['#c31432', '#240b36']],
            ['name' => 'Français', 'short' => 'FR', 'colors' => ['#283c86', '#45a247']],
        ];
        foreach ($languages as $index => $language) {
            $image = 'demo-language-' . ($index + 1) . '.png';
            $this->writePoster('language/' . $image, $language['short'], 'LANGUAGE', $language['colors'], 'square');
            $this->languageIds[$index + 1] = $this->upsert('tbl_language', ['name' => $language['name']], [
                'storage_type' => 1,
                'image' => $image,
                'sort_order' => $index + 1,
                'status' => 1,
            ]);
        }

        $cast = [
            ['name' => 'Maya Chen', 'type' => 'Director'],
            ['name' => 'Jon Bell', 'type' => 'Actor'],
            ['name' => 'Ari Santos', 'type' => 'Actor'],
            ['name' => 'Nadia Rahman', 'type' => 'Writer'],
            ['name' => 'Leo Martin', 'type' => 'Actor'],
        ];
        foreach ($cast as $index => $person) {
            $image = 'demo-cast-' . ($index + 1) . '.png';
            $this->writePoster('cast/' . $image, $person['name'], $person['type'], ['#111827', '#374151'], 'portrait');
            $this->castIds[$index + 1] = $this->upsert('tbl_cast', ['name' => $person['name']], [
                'storage_type' => 1,
                'image' => $image,
                'type' => $person['type'],
                'personal_info' => 'Demo cast profile for local UI and detail-page testing.',
                'status' => 1,
            ]);
        }
    }

    /** @return array<int, int> */
    private function seedMovies(): array
    {
        $movies = [
            ['Aurora Run', 'Action', 'A courier races across a neon city to deliver one last message before dawn.', 1, 1],
            ['Paper Moons', 'Drama', 'Two strangers rebuild a forgotten cinema and discover a shared past.', 2, 2],
            ['Laugh Track', 'Comedy', 'A small-town radio host turns every awkward mistake into a midnight hit.', 3, 1],
            ['Signal Zero', 'Sci-Fi', 'An orbital engineer receives a transmission from a future that should not exist.', 4, 1],
            ['The Last Glacier', 'Documentary', 'A team of climbers records a changing ice world in one unforgettable season.', 5, 1],
            ['Pocket Planet', 'Family', 'A curious sister and her brother build a tiny universe in their attic.', 6, 2],
            ['Midnight Circuit', 'Action', 'A street mechanic enters an underground race to save the family garage.', 1, 1],
            ['The Blue Hour', 'Drama', 'A photographer follows a single blue hour through three cities and three lives.', 2, 3],
            ['Odd Socks', 'Comedy', 'Four roommates solve a laundry mystery that gets stranger every day.', 3, 1],
            ['Europa Signal', 'Sci-Fi', 'A quiet signal from Europa forces a crew to choose between truth and safety.', 4, 4],
            ['Wild Neighbours', 'Documentary', 'A year of hidden wildlife unfolds in the gardens behind a busy street.', 5, 1],
            ['Cloud House', 'Family', 'A young inventor turns a weather balloon into the ultimate treehouse.', 6, 2],
            ['Redline Harbor', 'Action', 'A salvage diver has one night to stop a heist beneath the harbor.', 1, 1],
            ['Small Victories', 'Drama', 'A community kitchen finds new hope in the recipes of its neighbors.', 2, 2],
            ['Good Neighbors', 'Comedy', 'A new apartment building competes in the friendliest talent show ever.', 3, 1],
            ['Afterlight', 'Sci-Fi', 'A city powered by memories begins to forget its own history.', 4, 3],
            ['Inside the Rain', 'Documentary', 'Rain researchers follow storms across the planet to understand our water future.', 5, 4],
            ['The Little Comet', 'Family', 'A bedtime story becomes real when a tiny comet lands in a backyard.', 6, 2],
            ['Harbor Lights', 'Action', 'A rescue pilot navigates a blackout to bring a missing crew home.', 1, 1],
            ['Letters to June', 'Drama', 'A box of letters reconnects a daughter with the artist her mother once was.', 2, 3],
        ];
        $streams = [
            'https://media.w3.org/2010/05/sintel/trailer.mp4',
            'https://media.w3.org/2010/05/bunny/trailer.mp4',
            'https://media.w3.org/2010/05/video/movie_300.mp4',
            'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
            'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_1MB.mp4',
        ];
        $streamDurations = [52, 33, 10, 5, 10];

        $ids = [];
        foreach ($movies as $index => $movie) {
            [$name, $category, $description, $categoryIndex, $languageIndex] = $movie;
            $posterBase = 'demo-movie-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $poster = $posterBase . '.png';
            $landscape = $posterBase . '-wide.png';
            $this->writePoster('content/' . $poster, $name, strtoupper($category), $this->movieColors($index), 'portrait');
            $this->writePoster('content/' . $landscape, $name, strtoupper($category), $this->movieColors($index), 'wide');
            $stream = $streams[$index % count($streams)];
            $data = [
                'type_id' => self::TYPE_ID,
                'video_type' => 1,
                'channel_id' => 0,
                'producer_id' => 0,
                'category_id' => (string) $this->categoryIds[$categoryIndex],
                'language_id' => (string) $this->languageIds[$languageIndex],
                'cast_id' => (string) $this->castIds[($index % count($this->castIds)) + 1],
                'name' => $name,
                'storage_type' => 1,
                'thumbnail' => $poster,
                'landscape' => $landscape,
                'description' => $description,
                'video_storage_type' => 1,
                'video_upload_type' => 'external',
                'video_320' => $stream,
                'video_480' => $stream,
                'video_720' => $stream,
                'video_1080' => $stream,
                'video_extension' => 'mp4',
                'video_duration' => $streamDurations[$index % count($streamDurations)],
                'trailer_storage_type' => 1,
                'trailer_type' => 'external',
                'trailer_url' => $stream,
                'subtitle_storage_type' => 1,
                'subtitle_type' => 'external',
                'subtitle_lang_1' => '',
                'subtitle_1' => '',
                'subtitle_lang_2' => '',
                'subtitle_2' => '',
                'subtitle_lang_3' => '',
                'subtitle_3' => '',
                'release_date' => sprintf('2025-%02d-%02d', (($index % 12) + 1), (($index % 25) + 1)),
                'is_premium' => in_array($index, [3, 8, 14, 18], true) ? 1 : 0,
                'is_title' => 1,
                'is_download' => 1,
                'is_comment' => 1,
                'is_like' => 1,
                'is_rent' => 0,
                'price' => 0,
                'rent_day' => 0,
                'avg_rating' => number_format(3.9 + (($index % 10) / 10), 1, '.', ''),
                'total_review' => 12 + ($index * 3),
                'total_view' => 1000 + (($index * 733) % 9000),
                'total_like' => 120 + ($index * 11),
                'status' => 1,
            ];
            $ids[] = $this->upsert('tbl_video', ['name' => $name], $data);
        }

        return $ids;
    }

    /** @param array<int, int> $movieIds */
    private function seedBanners(array $movieIds): void
    {
        DB::table('tbl_banner')
            ->where('type_id', self::TYPE_ID)
            ->whereIn('is_home_screen', [1, 2])
            ->delete();

        foreach ([1, 2] as $isHomeScreen) {
            foreach (array_slice($movieIds, 0, 5) as $index => $movieId) {
                DB::table('tbl_banner')->insert($this->timestamps([
                    'is_home_screen' => $isHomeScreen,
                    'type_id' => self::TYPE_ID,
                    'video_type' => 1,
                    'subvideo_type' => 0,
                    'video_id' => $movieId,
                    'sort_order' => $index + 1,
                    'status' => 1,
                ]));
            }
        }
    }

    /** @param array<int, int> $movieIds */
    private function seedHomeSections(array $movieIds): void
    {
        DB::table('tbl_home_section')
            ->where('type_id', self::TYPE_ID)
            ->whereIn('is_home_screen', [1, 2])
            ->delete();
        $sections = [
            [1, 1, 'Featured releases', 'Featured', 'landscape', implode(',', array_slice($movieIds, 0, 8)), 0, 0, 2, 8],
            [0, 1, 'Trending this week', 'Trending', 'portrait', '', 0, 0, 2, 10],
            [2, 1, 'Made for your mood', 'For you', 'big_landscape', '', 0, 0, 2, 6],
            [0, 3, 'Browse by genre', 'Genres', 'category', '', 0, 0, 0, 6],
            [0, 4, 'Pick a language', 'Languages', 'language', '', 0, 0, 0, 4],
            [0, 8, 'Quick clips', 'Clips', 'shorts', '', 0, 0, 0, 6],
        ];
        foreach ([1, 2] as $isHomeScreen) {
            foreach ($sections as $index => $section) {
                [$sectionType, $videoType, $title, $shortTitle, $layout, $contentIds, $categoryId, $languageId, $orderByView, $limit] = $section;
                DB::table('tbl_home_section')->insert($this->timestamps([
                    'section_type' => $sectionType,
                    'is_home_screen' => $isHomeScreen,
                    'video_type' => $videoType,
                    'sub_video_type' => 0,
                    'type_id' => self::TYPE_ID,
                    'title' => $title,
                    'short_title' => $shortTitle,
                    'screen_layout' => $layout,
                    'content_ids' => $contentIds,
                    'category_id' => $categoryId,
                    'language_id' => $languageId,
                    'channel_id' => 0,
                    'order_by_upload' => $sectionType === 1 ? 0 : 2,
                    'order_by_view' => $orderByView,
                    'premium_video' => 2,
                    'no_of_content' => $limit,
                    'view_all' => 1,
                    'is_title' => 1,
                    'sort_order' => $index + 1,
                    'status' => 1,
                ]));
            }
        }
    }

    private function seedShorts(): void
    {
        $showId = $this->upsert('tbl_shorts', ['name' => 'DTLive Quick Stories'], [
            'type_id' => self::TYPE_ID,
            'video_type' => 8,
            'producer_id' => 0,
            'category_id' => (string) $this->categoryIds[6],
            'language_id' => (string) $this->languageIds[1],
            'cast_id' => (string) $this->castIds[2],
            'storage_type' => 1,
            'thumbnail' => 'demo-movie-06.png',
            'trailer_storage_type' => 1,
            'trailer_type' => 'external',
            'trailer_url' => 'https://media.w3.org/2010/05/bunny/trailer.mp4',
            'description' => 'Six quick vertical stories curated for testing the clips experience.',
            'is_title' => 1,
            'is_comment' => 1,
            'is_like' => 1,
            'total_view' => 8200,
            'total_like' => 920,
            'avg_rating' => 4.6,
            'total_review' => 18,
            'status' => 1,
        ]);
        $seasonId = $this->upsert('tbl_season', ['name' => 'Quick Stories · Season 1'], [
            'sort_order' => 1,
            'status' => 1,
        ]);
        $streams = [
            'https://media.w3.org/2010/05/bunny/trailer.mp4',
            'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
            'https://media.w3.org/2010/05/sintel/trailer.mp4',
            'https://media.w3.org/2010/05/video/movie_300.mp4',
            'https://test-videos.co.uk/vids/bigbuckbunny/mp4/h264/720/Big_Buck_Bunny_720_10s_1MB.mp4',
            'https://media.w3.org/2010/05/sintel/trailer.mp4',
        ];
        $clipDurations = [33, 5, 52, 10, 10, 52];
        for ($index = 0; $index < 6; $index++) {
            $poster = 'demo-clip-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '.png';
            $this->writePoster('content/' . $poster, 'CLIP ' . ($index + 1), 'QUICK STORY', $this->movieColors($index + 8), 'portrait');
            $this->upsert('tbl_shorts_episode', ['show_id' => $showId, 'name' => 'Quick Story ' . ($index + 1)], [
                'season_id' => $seasonId,
                'storage_type' => 1,
                'thumbnail' => $poster,
                'description' => 'A short demo episode for testing the vertical player and next clip navigation.',
                'video_storage_type' => 1,
                'video_upload_type' => 'external',
                'video_320' => $streams[$index],
                'video_duration' => $clipDurations[$index],
                'is_premium' => 0,
                'is_title' => 1,
                'total_view' => 500 + ($index * 140),
                'sort_order' => $index + 1,
                'status' => 1,
            ]);
        }
    }

    private function seedFooterLinks(): void
    {
        foreach ([
            ['Facebook', 'https://facebook.com', 'demo-social-facebook.png'],
            ['Instagram', 'https://instagram.com', 'demo-social-instagram.png'],
            ['YouTube', 'https://youtube.com', 'demo-social-youtube.png'],
        ] as $index => [$name, $url, $image]) {
            $this->writePoster('app/' . $image, strtoupper(substr($name, 0, 1)), 'SOCIAL', ['#111827', '#475569'], 'square');
            $this->upsert('tbl_social_link', ['name' => $name], [
                'storage_type' => 1,
                'image' => $image,
                'url' => $url,
                'status' => 1,
            ]);
        }
    }

    private function seedPages(): void
    {
        // Remove the temporary labels used by the first local demo seed pass.
        DB::table('tbl_page')->whereIn('title', ['About DTLive', 'Terms of use'])->delete();
        $pages = [
            ['About Us', 'A local demo space showing how a complete OTT catalog is organized.', 'Our studio'],
            ['Help Center', 'Find simple answers about profiles, watchlists, playback and subscriptions.', 'Need a hand?'],
            ['Terms and Conditions', 'This local catalog uses public sample streams for development and QA only.', 'Please read'],
            ['Privacy Policy', 'No real payment or personal data is required for this local demo.', 'Your privacy'],
            ['Refund Policy', 'This local demo does not process charges. Configure a payment provider before accepting purchases.', 'Payment information'],
        ];
        foreach ($pages as [$title, $description, $subtitle]) {
            DB::table('tbl_page')->updateOrInsert(
                ['title' => $title],
                $this->timestamps([
                    'description' => $description,
                    'page_subtitle' => $subtitle,
                    'storage_type' => 1,
                    'icon' => '',
                    'status' => 1,
                ])
            );
        }
    }

    /** @param array<string, mixed> $values */
    private function upsert(string $table, array $identity, array $values): int
    {
        $existing = DB::table($table)->where($identity)->first();
        $payload = $this->timestamps($values);
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);
            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId(array_merge($identity, $payload));
    }

    /** @param array<string, mixed> $values */
    private function timestamps(array $values): array
    {
        $now = now();
        return array_merge($values, ['created_at' => $now, 'updated_at' => $now]);
    }

    /** @return array{0:string,1:string} */
    private function movieColors(int $index): array
    {
        $palette = [
            ['#ff512f', '#dd2476'], ['#4568dc', '#b06ab3'], ['#11998e', '#38ef7d'],
            ['#834d9b', '#d04ed6'], ['#f7971e', '#ffd200'], ['#0f2027', '#2c5364'],
            ['#c33764', '#1d2671'], ['#00b4db', '#0083b0'], ['#e65c00', '#f9d423'],
            ['#396afc', '#2948ff'],
        ];
        return $palette[$index % count($palette)];
    }

    /** @param array{0:string,1:string} $colors */
    private function writePoster(string $path, string $title, string $subtitle, array $colors, string $shape): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            throw new \RuntimeException('The PHP GD extension is required to create demo poster images.');
        }
        [$start, $end] = $colors;
        $width = $shape === 'wide' ? 1200 : 720;
        $height = $shape === 'square' ? 720 : ($shape === 'wide' ? 360 : 1080);
        [$r1, $g1, $b1] = $this->rgb($start);
        [$r2, $g2, $b2] = $this->rgb($end);
        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);
        for ($y = 0; $y < $height; $y++) {
            $progress = $height > 1 ? $y / ($height - 1) : 0;
            $color = imagecolorallocate(
                $image,
                (int) ($r1 + (($r2 - $r1) * $progress)),
                (int) ($g1 + (($g2 - $g1) * $progress)),
                (int) ($b1 + (($b2 - $b1) * $progress))
            );
            imageline($image, 0, $y, $width, $y, $color);
        }
        $glow = imagecolorallocatealpha($image, 255, 255, 255, 110);
        imagefilledellipse($image, (int) ($width * 0.82), (int) ($height * 0.18), (int) ($width * 0.8), (int) ($height * 0.58), $glow);
        $ring = imagecolorallocatealpha($image, 255, 255, 255, 105);
        imageellipse($image, (int) ($width * 0.76), (int) ($height * 0.39), (int) ($width * 0.74), (int) ($height * 0.4), $ring);
        imageellipse($image, (int) ($width * 0.76), (int) ($height * 0.39), (int) ($width * 0.56), (int) ($height * 0.31), $ring);
        $shade = imagecolorallocatealpha($image, 0, 0, 0, 62);
        imagefilledrectangle($image, 0, (int) ($height * 0.68), $width, $height, $shade);
        $white = imagecolorallocate($image, 255, 255, 255);
        $muted = imagecolorallocate($image, 232, 237, 245);
        imagestring($image, 5, 38, 34, 'DTLIVE STUDIO', $muted);
        $title = strtoupper($title);
        $titleY = (int) ($height * ($shape === 'wide' ? 0.66 : 0.72));
        $fontPath = $this->fontPath();
        if ($fontPath !== null && function_exists('imagettftext')) {
            $fontSize = $shape === 'wide' ? 48 : 56;
            $maxTextWidth = $width - 80;
            $lines = $this->wrapTitle($title, $fontPath, $fontSize, $maxTextWidth);
            foreach ($lines as $lineIndex => $line) {
                imagettftext($image, $fontSize, 0, 38, $titleY + ($lineIndex * (int) ($fontSize * 1.12)), $white, $fontPath, $line);
            }
            $subtitleY = $titleY + (count($lines) * (int) ($fontSize * 1.12)) + 20;
            imagettftext($image, $shape === 'wide' ? 22 : 24, 0, 40, $subtitleY, $muted, $fontPath, strtoupper($subtitle));
        } else {
            imagestring($image, 5, 38, $titleY, substr($title, 0, 34), $white);
            imagestring($image, 4, 40, $titleY + 34, strtoupper($subtitle), $muted);
        }
        ob_start();
        imagepng($image, null, 8);
        $png = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put($path, $png);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function fontPath(): ?string
    {
        foreach ([
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arialbd.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return null;
    }

    /** @return array<int, string> */
    private function wrapTitle(string $text, string $fontPath, int $fontSize, int $maxWidth): array
    {
        $lines = [];
        $line = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = trim($line . ' ' . $word);
            $box = imagettfbbox($fontSize, 0, $fontPath, $candidate);
            $candidateWidth = abs($box[2] - $box[0]);
            if ($line !== '' && $candidateWidth > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        return $lines;
    }
}
