Fastlane automation for CardEvent — App ID + provisioning

Prerequisites
- Install fastlane (recommended via Homebrew or Bundler):

```bash
brew install fastlane
# or inside project if you use Bundler:
# bundle init; echo "gem 'fastlane'" >> Gemfile; bundle install
```

- Create a private git repo to store certificates/profiles for `match` and set `MATCH_GIT_URL` to it.
- Ensure you have an Apple Developer account user; two-factor auth will require either a session or App Store Connect API key.

Environment variables (set before running):
- `FASTLANE_USER` — your Apple ID (email)
- `MATCH_GIT_URL` — git repo URL for `match`
- `FASTLANE_PASSWORD` or use an app-specific password / App Store Connect API key

Run (will create App ID and profiles):

```bash
cd CardEvent
fastlane create_app_and_profiles
# or with Bundler:
# bundle exec fastlane create_app_and_profiles
```

Notes
- `produce` creates the App ID on developer.apple.com.
- `match` will create and sync provisioning profiles and certificates into your `MATCH_GIT_URL` repo.
- If you prefer not to store certs in git, configure `match` to use a different storage backend.
- After running, open the Xcode project, select your device, and verify signing (Xcode may need to refresh profiles).

Manual alternative
- Visit https://developer.apple.com/account/resources/identifiers/add to create an App ID (bundle ID `com.franck.cardevent`), then create provisioning profiles linked to that App ID.
