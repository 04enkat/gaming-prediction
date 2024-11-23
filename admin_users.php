<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Set pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_param = '%' . $search . '%';

// Query to fetch user statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM users WHERE account_status = 'active') AS active_users,
        (SELECT COUNT(*) FROM users WHERE account_status = 'banned') AS banned_users,
        (SELECT COUNT(*) FROM users WHERE last_activity > DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS online_users
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Fetch user list based on search criteria
$query = "
    SELECT 
        u.id, u.username, u.email, u.wallet_balance, u.wallet_withdrawable, u.vip_level, 
        u.vip_expiry, u.created_at, u.last_login, u.account_status, u.profile_image,
        (SELECT COUNT(*) FROM users WHERE invited_by = u.id) AS referral_count
    FROM users u
    WHERE u.username LIKE ? OR u.id LIKE ?
    ORDER BY u.username ASC
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('ssii', $search_param, $search_param, $limit, $offset);
$stmt->execute();
$stmt->bind_result(
    $user_id, $username, $email, $wallet_balance, $wallet_withdrawable, $vip_level, 
    $vip_expiry, $created_at, $last_login, $account_status, $profile_image, $referral_count
);

$users = [];
while ($stmt->fetch()) {
    $users[] = [
        'user_id' => $user_id,
        'username' => $username,
        'email' => $email,
        'wallet_balance' => $wallet_balance,
        'wallet_withdrawable' => $wallet_withdrawable,
        'vip_level' => $vip_level,
        'vip_expiry' => $vip_expiry,
        'created_at' => $created_at,
        'last_login' => $last_login,
        'account_status' => $account_status,
        'profile_image' => $profile_image ?: 'uploads/default.png',
        'referral_count' => $referral_count
    ];
}
$stmt->close();

// Count total users for pagination
$count_query = "SELECT COUNT(*) FROM users WHERE username LIKE ? OR id LIKE ?";
$stmt = $conn->prepare($count_query);
$stmt->bind_param('ss', $search_param, $search_param);
$stmt->execute();
$stmt->bind_result($total_records);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_records / $limit);

// Handle Ban/Unban and VIP Update actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $action = $_POST['action'];

    if ($action === 'toggle_ban') {
        $new_status = ($_POST['current_status'] === 'active') ? 'banned' : 'active';
        $stmt = $conn->prepare("UPDATE users SET account_status = ? WHERE id = ?");
        $stmt->bind_param('si', $new_status, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    header('Location: admin_users.php');
    exit();
}

// Fetch referred users if requested
$referred_users = [];
if (isset($_GET['view_referrals'])) {
    $selected_user_id = $_GET['view_referrals'];

    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE invited_by = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $selected_user_id);
    $stmt->execute();
    $stmt->bind_result($referred_id, $referred_username, $referred_email);

    while ($stmt->fetch()) {
        $referred_users[] = [
            'referred_id' => $referred_id,
            'username' => $referred_username,
            'email' => $referred_email
        ];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Manage Users</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>User Management</h1>

    <!-- Statistics Section -->
    <div class="stats">
        <p>Total Users: <?php echo $stats['total_users']; ?></p>
        <p>Active Accounts: <?php echo $stats['active_users']; ?></p>
        <p>Banned Accounts: <?php echo $stats['banned_users']; ?></p>
        <p>Online Users (Last 15 mins): <?php echo $stats['online_users']; ?></p>
    </div>

    <!-- Search Form -->
    <form method="get" action="admin_users.php">
        <input type="text" name="search" placeholder="Search by Username or ID" value="<?php echo htmlspecialchars($search); ?>" required>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($users)): ?>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Profile Image</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Wallet Balance (₹)</th>
                    <th>Withdrawable Balance (₹)</th>
                    <th>VIP Level</th>
                    <th>VIP Expiry</th>
                    <th>Referral Count</th>
                    <th>Created At</th>
                    <th>Account Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile Image" width="50" height="50" style="border-radius: 50%;"></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>₹<?php echo number_format($user['wallet_balance'], 2); ?></td>
                        <td>₹<?php echo number_format($user['wallet_withdrawable'], 2); ?></td>
                        <td>VIP <?php echo $user['vip_level']; ?></td>
                        <td><?php echo $user['vip_expiry']; ?></td>
                        <td>
                            <?php echo $user['referral_count']; ?>
                            <?php if ($user['referral_count'] > 0): ?>
                                <a href="admin_users.php?view_referrals=<?php echo $user['user_id']; ?>">View Referrals</a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user['created_at']; ?></td>
                        <td><?php echo ucfirst($user['account_status']); ?></td>
                        <td>
                            <form method="post" action="admin_users.php">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="hidden" name="current_status" value="<?php echo $user['account_status']; ?>">
                                <button type="submit" name="action" value="toggle_ban">
                                    <?php echo $user['account_status'] === 'active' ? 'Ban User' : 'Unban User'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination Links -->
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p>No users found.</p>
    <?php endif; ?>

    <!-- Referred Users List -->
    <?php if (!empty($referred_users)): ?>
        <h2>Referred Users</h2>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($referred_users as $ref_user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($ref_user['username']); ?></td>
                        <td><?php echo htmlspecialchars($ref_user['email']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>
