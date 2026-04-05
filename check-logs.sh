#!/bin/bash
echo "=== Logs actuels dans la base ==="
curl -s https://viendez.com/api/debug-logs.php | grep -A 200 "Total rows:"
