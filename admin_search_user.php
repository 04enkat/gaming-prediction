<?php
session_start();
include 'db.php';

// Ensure the admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

// Search user by username
$search_query = $_GET['search'] ?? '';
$users = [];

if ($search_query) {
    $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE username LIKE ?");
    $search_query = '%' . $search_query . '%';
    $stmt->bind_param('s', $search_query);
    $stmt->execute();
    $stmt->bind_result($user_id, $username, $email);

    while ($stmt->fetch()) {
        $users[] = ['user_id' => $user_id, 'username' => $username, 'email' => $email];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search User</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <h1>Search User</h1>

    <!-- Search Form -->
    <form method="get" action="admin_search_user.php">
        <label for="search">Username:</label>
        <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" required>
        <button type="submit">Search</button>
    </form>

    <?php if (!empty($users)): ?>
        <h2>Search Results</h2>
        <table border="1" cellpadding="10">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><a href="admin_user_details.php?user_id=<?php echo $user['user_id']; ?>">View Details</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No users found.</p>
    <?php endif; ?>

    <p><a href="admin_panel.php">Back to Admin Panel</a></p>
</body>
</html>
