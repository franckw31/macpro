#!/usr/bin/env bash
set -euo pipefail

# Usage: ./scripts/build_and_run.sh [SIM_DEVICE_NAME]
# Example: ./scripts/build_and_run.sh "iPhone 13"

DEVICE_NAME="${1:-iPhone 13}"


echo "Building CardEvent (Debug) for iOS Simulator..."

xcodebuild -scheme CardEvent -workspace CardEvent.xcworkspace -configuration Debug -sdk iphonesimulator build

export SIM_DEVICE_NAME="$DEVICE_NAME"

echo "Build finished. Launching simulator ($DEVICE_NAME)..."
./scripts/launch_simulator.sh

