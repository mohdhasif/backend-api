<?php
// Example of updating client with logo_url going to users.avatar_url

// First, update the clients table (without logo_url)
$stmt = $conn->prepare("UPDATE clients SET company_name=?, phone=?, status=?, client_type=? WHERE id=?");

// Then, update the users table with the logo_url as avatar_url
$stmt2 = $conn->prepare("UPDATE users SET avatar_url=? WHERE id=(SELECT user_id FROM clients WHERE id=?)");

// Or alternatively, you can do it in one query if you know the user_id:
// $stmt2 = $conn->prepare("UPDATE users SET avatar_url=? WHERE id=?");
