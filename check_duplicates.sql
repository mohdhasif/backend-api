-- Check for duplicate subscription IDs
SELECT subscription_id, COUNT(*) as count
FROM user_push_subscriptions 
GROUP BY subscription_id 
HAVING count > 1;

-- Check for duplicate install IDs
SELECT install_id, COUNT(*) as count
FROM user_push_subscriptions 
GROUP BY install_id 
HAVING count > 1;

-- Check for duplicate user IDs
SELECT user_id, COUNT(*) as count
FROM user_push_subscriptions 
WHERE user_id IS NOT NULL
GROUP BY user_id 
HAVING count > 1;

-- Show all subscriptions for a specific phone (replace with your subscription_id)
SELECT * FROM user_push_subscriptions 
WHERE subscription_id = 'YOUR_SUBSCRIPTION_ID_HERE';

-- Show prayer settings for a user
SELECT * FROM user_prayer_settings 
WHERE user_id = YOUR_USER_ID_HERE;
