# DTLive

Complete DTLive application source, server code, Flutter app, documentation, and database dump.

## Project layout

- `DTLive v2/.../Mainfiles_DTLive/Appcode/yourappname` — Flutter application
- `DTLive v2/.../Mainfiles_DTLive/ServerCode` — Laravel server and admin panel
- `DTLive v2/.../Mainfiles_DTLive/Documentation` — offline documentation
- `DTLive v2/.../DTLive_db_23_12_2025` — database dump

## Local configuration

Runtime secrets and generated build output are intentionally not committed. Copy the included `.env.example` to `.env` in the Laravel admin panel and fill in values for your environment.

Flutter dependencies and build files can be regenerated with the normal Flutter and Composer workflows.
