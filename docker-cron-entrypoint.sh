#!/bin/bash
set -e

# Setup cron job
echo "* * * * * cd /var/www/html && /usr/local/bin/php prayer_cron.php >> /var/www/html/prayer_cron.log 2>&1" | crontab -

# Start cron daemon
service cron start

# Keep container running
tail -f /dev/null
