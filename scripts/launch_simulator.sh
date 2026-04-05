#!/usr/bin/env bash
set -euo pipefail

# Default simulator device name (can be overridden by exporting SIM_DEVICE_NAME)
DEVICE_NAME="${SIM_DEVICE_NAME:-iPhone 13}"

open -a Simulator
sleep 1
# Try to boot the device (ignore error if already booted)
xcrun simctl boot "$DEVICE_NAME" >/dev/null 2>&1 || true

# Find the built app in DerivedData
APP=$(ls -d ~/Library/Developer/Xcode/DerivedData/CardEvent-*/Build/Products/Debug-iphonesimulator/CardEvent.app 2>/dev/null | head -n1 || true)
if [ -z "$APP" ]; then
  echo "Built app not found. Build first with: xcodebuild -scheme CardEvent -workspace CardEvent.xcworkspace -configuration Debug -sdk iphonesimulator build"
  exit 1
fi

echo "Using simulator device: $DEVICE_NAME"
echo "Installing $APP into booted simulator..."
xcrun simctl install booted "$APP"

echo "Launching app..."
xcrun simctl launch booted com.franck.cardevent || echo "launch failed"

echo "Done."
