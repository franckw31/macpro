#!/usr/bin/env bash
set -euo pipefail

API_URL="${API_URL:-https://viendez.com/api/player-action.php}"

usage() {
  echo "Usage: $0 <activity_id> <victim_participation_id> <eliminator_name> <eliminator_member_id>"
  exit 1
}

[ "$#" -ge 4 ] || usage

activity_id="$1"
victim_id="$2"
eliminator_name="$3"
eliminator_member_id="$4"

if command -v jq >/dev/null 2>&1; then
  payload=$(jq -n --arg a "$activity_id" --arg v "$victim_id" --arg en "$eliminator_name" --arg em "$eliminator_member_id" '{activity_id: ($a|tonumber), victim_participation_id: ($v|tonumber), eliminator_name: $en, eliminator_member_id: ($em|tonumber), is_definitive: 0, action: "recave_player"}')
else
  export activity_id victim_id eliminator_name eliminator_member_id
  payload=$(python3 - <<PY
import json, os
print(json.dumps({
  "activity_id": int(os.environ["activity_id"]),
  "victim_participation_id": int(os.environ["victim_id"]),
  "eliminator_name": os.environ["eliminator_name"],
  "eliminator_member_id": int(os.environ["eliminator_member_id"]),
  "is_definitive": 0,
  "action": "recave_player"
}))
PY
)
fi

echo "API URL: $API_URL"
echo "Payload:"
echo "$payload"

curl -s -X POST "$API_URL" -H "Content-Type: application/json" -d "$payload" -w "\nHTTP_CODE:%{http_code}\n"
