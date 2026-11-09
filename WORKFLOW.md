# Mac ও Windows PC-তে DTLive চালানোর workflow

AI coding agent-এর জন্য প্রথমে `AGENTS.md` এবং `AI_AGENT_SETUP/README.md` পড়ুন। Sync ও web build-এর repeatable scripts ওই folder-এ আছে।

## ১. একবারের setup

দুই কম্পিউটারেই Git ইনস্টল করুন। যে platform-এ app চালাবেন সেখানে Flutter SDK, Android Studio/SDK এবং প্রয়োজন হলে PHP ও Composer ইনস্টল করুন। iOS build শুধু macOS-এ করা যাবে।

প্রতিটি কম্পিউটারে একবার করে:

```bash
git clone https://github.com/sbayshop3171-ship-it/movielist.git
cd movielist
git config user.name "Your Name"
git config user.email "your-email@example.com"
```

`git clone`-এর পরে আর ZIP download করবেন না। Clone করা folder-এর ভিতরেই কাজ করবেন, কারণ সেখানে Git history এবং remote connection থাকে।

## ২. Flutter ও Laravel-এর local setup

Repository root থেকে Flutter folder-এ যান। Folder name-এ spaces আছে, তাই quotes ব্যবহার করুন। macOS/Linux:

```bash
cd "DTLive v2/codecanyon-44548358-dtlive-flutter-app-android-ios-website-androidtv-movies-tv-series-live-tv-channel-ott/Mainfiles_DTLive/Appcode/yourappname"
flutter pub get
```

Windows PowerShell-এ একই path backslash দিয়ে লিখুন:

```powershell
cd ".\DTLive v2\codecanyon-44548358-dtlive-flutter-app-android-ios-website-androidtv-movies-tv-series-live-tv-channel-ott\Mainfiles_DTLive\Appcode\yourappname"
flutter pub get
```

Laravel admin panel-এর জন্য `ServerCode/admin_panel` folder-এ গিয়ে:

```bash
composer install
```

তারপর `.env.example` কপি করে `.env` বানান এবং database/API values নিজের কম্পিউটার অনুযায়ী দিন। `.env` GitHub-এ রাখা হয় না, তাই দুই কম্পিউটারে আলাদা করে তৈরি করতে হবে।

## ৩. প্রতিবার কাজ শুরু করার আগে

যে কম্পিউটারে কাজ করবেন, প্রথমে repository root-এ গিয়ে চালান:

```bash
git status
git pull --ff-only origin main
```

`Already up to date` দেখালে local copy সর্বশেষ অবস্থায় আছে। অন্য কম্পিউটারে আগে push করা পরিবর্তন এখানেই চলে আসবে।

## ৪. কাজ শেষ করার পরে

```bash
git status
git add -A
git commit -m "Describe what changed"
git push origin main
```

`git push` সফল হলে পরিবর্তন GitHub-এ সংরক্ষিত হয়েছে। এরপর অন্য কম্পিউটারে কাজ শুরু করার আগে শুধু `git pull --ff-only origin main` চালালেই হবে।

## ৫. দুই কম্পিউটারে একই সময়ে কাজ করলে

সহজ নিয়ম: একই সময়ে শুধু একটি কম্পিউটারে `main` edit করুন। প্রথম কম্পিউটারে commit ও push শেষ না হওয়া পর্যন্ত দ্বিতীয় কম্পিউটারে কাজ শুরু করবেন না।

একসাথে কাজ করতেই হলে আলাদা branch ব্যবহার করুন:

```bash
git switch -c feature/short-description
git add -A
git commit -m "Describe the change"
git push -u origin feature/short-description
```

তারপর GitHub-এ Pull Request করে `main`-এ merge করুন এবং দুই কম্পিউটারেই আবার `git switch main && git pull --ff-only origin main` চালান।

## ৬. Pull করার সময় local changes থাকলে

প্রথমে নিজের কাজ সংরক্ষণ করুন:

```bash
git add -A
git commit -m "Save local progress"
git pull --rebase origin main
git push origin main
```

Conflict দেখালে Git যে file দেখাবে সেটি ঠিক করে `git add <file>` এবং `git rebase --continue` চালাতে হবে। অনিশ্চিত হলে কোনো file delete না করে কাজ থামিয়ে repository history দেখে নিন।

## মনে রাখবেন

- `build`, `.dart_tool`, cache এবং dependency output প্রতিটি কম্পিউটারে আবার তৈরি হবে; এগুলো sync করা হয় না।
- `.env` এবং credentials প্রতিটি কম্পিউটারে আলাদা থাকবে এবং public repository-তে push করা যাবে না।
- কাজ শেষ না হলেও backup দরকার হলে `git add -A && git commit -m "WIP: ..."` করে branch-এ push করুন।
- Mac থেকে Windows-এ গেলে path আলাদা হবে, কিন্তু Git workflow একই থাকবে।
