CardEvent — quick start

This folder contains an adapted copy of the Swift sources from the original `Timer` app.

Open & first-run

1. Open the workspace: `CardEvent.xcworkspace` (or open `Timer.xcworkspace`, it now loads CardEvent).
2. Select the `CardEvent` scheme and target, then open the project editor.
3. In the target's **Signing & Capabilities** set your `Team` and provisioning, and choose a final **Bundle Identifier** (example: `com.yourteam.cardevent`).

Assets

- Copy `cardevent/Timer/Assets.xcassets` into `CardEvent/CardEvent/Assets.xcassets` if you need the original images and colors.

What was renamed

- UserDefaults keys: `cardevent.*` → `cardevent.*` in the CardEvent sources.
- Keychain service: `com.cardevent.auth` → `com.cardevent.auth`.
- Project and scheme files were updated to reference `CardEvent` and `com.franck.cardevent` (placeholder bundle id).

Build & run from command line

Run these commands from the repository root to build for simulator and launch it:

```bash
# build
xcodebuild -scheme CardEvent -workspace CardEvent.xcworkspace -configuration Debug -sdk iphonesimulator build

# launch helper (installs and launches the app on the booted simulator)
./scripts/launch_simulator.sh
```

Manual steps you must do in Xcode

- Open `CardEvent.xcworkspace` and verify the `CardEvent` target settings.
- In **Signing & Capabilities**: set your `Team`, ensure the correct bundle id, enable Push Notifications if you use APNs, and select appropriate capabilities.
- If you changed the bundle id, update `api/apns_config.php` → `APNS_BUNDLE_ID` and any server-side config that validates bundle ids.

Notes & cautions

- Many other files in this repository contain the word "Timer" (web assets, vendor libs). I only updated project/scheme/config files relevant to the iOS project. If you want a global rename, tell me and I'll perform a conservative replacement excluding vendor files.
- Final code signing and App Store/TestFlight distribution must be performed from Xcode with your developer account.

If you'd like, I can now copy the original `Assets.xcassets` into the CardEvent project and finish a conservative rename of remaining project-related files.
