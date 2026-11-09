# DTLive

Complete DTLive application source, server code, Flutter app, documentation, and database dump.

## AI agent setup

Any coding agent should start with [AI_AGENT_SETUP/README.md](AI_AGENT_SETUP/README.md). It defines the cross-platform sync, local build, secret handling, commit, push, and automatic deployment process. The root [AGENTS.md](AGENTS.md) points agents to this entrypoint.

## দুই কম্পিউটারে কাজ করার নিয়ম

এই repository-টাই project-এর shared master copy। Mac বা Windows PC-তে ZIP বারবার download করবেন না। প্রতিটি কম্পিউটারে একবার `git clone` করুন, এরপর কাজ শুরু করার আগে `git pull` এবং কাজ শেষ হলে `git push` করুন। বিস্তারিত কমান্ড [WORKFLOW.md](WORKFLOW.md)-তে আছে।

সংক্ষিপ্ত নিয়ম:

```bash
git pull --ff-only origin main
# কাজ করুন
git add -A
git commit -m "Describe the change"
git push origin main
```

একই সময়ে দুই কম্পিউটারে `main`-এ কাজ না করে আগে এক কম্পিউটারের পরিবর্তন push করে অন্য কম্পিউটারে pull করুন।

## Project layout

- `DTLive v2/.../Mainfiles_DTLive/Appcode/yourappname` — Flutter application
- `DTLive v2/.../Mainfiles_DTLive/ServerCode` — Laravel server and admin panel
- `DTLive v2/.../Mainfiles_DTLive/Documentation` — offline documentation
- `DTLive v2/.../DTLive_db_23_12_2025` — database dump

## Local configuration

Runtime secrets and generated build output are intentionally not committed. Copy the included `.env.example` to `.env` in the Laravel admin panel and fill in values for your environment.

Flutter dependencies and build files can be regenerated with the normal Flutter and Composer workflows.

## নতুন কম্পিউটারে দ্রুত শুরু

```bash
git clone https://github.com/sbayshop3171-ship-it/movielist.git
cd movielist
```

তারপর Flutter project-এ `flutter pub get` এবং Laravel admin panel-এ `composer install` চালান। প্রতিটি কম্পিউটারের `.env` আলাদা থাকবে; `.env.example` কপি করে নিজের local values বসান।
