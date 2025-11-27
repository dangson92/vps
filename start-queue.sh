#!/bin/bash
# Start queue worker script

cd /opt/vps-manager

# Check if queue worker is already running
if pgrep -f "queue:work" > /dev/null; then
    echo "Queue worker is already running"
    pgrep -f "queue:work"
else
    echo "Starting queue worker..."
    nohup php artisan queue:work --sleep=3 --tries=3 --timeout=300 > storage/logs/queue-worker.log 2>&1 &
    echo "Queue worker started with PID: $!"
    echo "Logs: tail -f storage/logs/queue-worker.log"
fi
