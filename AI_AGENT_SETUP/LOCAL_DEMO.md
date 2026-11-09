# Local OTT demo catalog

The Laravel backend includes an idempotent seeder for a populated local web demo. It adds 20 movie records, 6 short-video episodes, 6 categories, 4 languages, cast profiles, 5 home banners, 5 Movies-tab banners, six sections on both Home and Movies pages, footer social links and page copy. The poster, category, cast and logo artwork is generated as local PNG files in `storage/app/public`.

The movie titles and metadata are demo content. Playback uses public sample MP4 streams, reused across catalog entries; these are short samples, not full-length movies. Internet access is needed to play them. Do not use the demo stream URLs for a production catalog. The web application supports the included browsing, search, detail, category, clip and player flows. Payment providers remain disabled until valid provider credentials are configured. Users can register locally to exercise account-based watchlist and profile features.

## Seed or refresh the demo data

From the Laravel app directory:

```bash
php artisan db:seed --class="Database\\Seeders\\DemoCatalogSeeder" --force
php artisan storage:link
```

The seeder can be run again safely. It updates demo rows by stable names and IDs. It replaces Home and Movies-tab sections and banners for the Movies type, so run it only against the local development database.

## Run the web app

Start Laravel from `DTLive v2/.../Mainfiles_DTLive/ServerCode/admin_panel`:

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Start Flutter from `DTLive v2/.../Mainfiles_DTLive/Appcode/yourappname`:

```bash
flutter run -d web-server --web-port 8080 --dart-define-from-file=../../../../../AI_AGENT_SETUP/config/local.json
```

Then open `http://localhost:8080`. `AI_AGENT_SETUP/config/local.json` is local-only and must contain the API token matching the Laravel `.env` value. Keep both servers running while using the demo.
