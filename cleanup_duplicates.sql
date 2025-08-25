-- ========================================
-- Cleanup Duplicate Subscriptions
-- ========================================

-- 1. Show duplicate subscription_ids
SELECT subscription_id, COUNT(*) as count
FROM user_push_subscriptions 
GROUP BY subscription_id 
HAVING count > 1;

-- 2. Show duplicate install_ids
SELECT install_id, COUNT(*) as count
FROM user_push_subscriptions 
GROUP BY install_id 
HAVING count > 1;

-- 3. Keep only one record per subscription_id (keep the latest one)
DELETE ups1 FROM user_push_subscriptions ups1
INNER JOIN user_push_subscriptions ups2 
WHERE ups1.id > ups2.id 
AND ups1.subscription_id = ups2.subscription_id;

-- 4. Alternative: Keep the record with user_id (if exists) or the latest one
-- This keeps user-specific settings when available
DELETE ups1 FROM user_push_subscriptions ups1
INNER JOIN user_push_subscriptions ups2 
WHERE ups1.subscription_id = ups2.subscription_id
AND (
  (ups1.user_id IS NULL AND ups2.user_id IS NOT NULL) OR
  (ups1.user_id IS NOT NULL AND ups2.user_id IS NULL AND ups1.id > ups2.id) OR
  (ups1.user_id IS NULL AND ups2.user_id IS NULL AND ups1.id > ups2.id)
);

-- 5. Verify cleanup
SELECT subscription_id, COUNT(*) as count
FROM user_push_subscriptions 
GROUP BY subscription_id 
HAVING count > 1;

-- 6. Show final result
SELECT subscription_id, user_id, install_id, created_at
FROM user_push_subscriptions 
ORDER BY subscription_id, created_at DESC;
