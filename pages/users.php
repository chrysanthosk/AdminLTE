<?php
// users.php - list and manage users
require_once '../auth.php';
requirePermission($pdo, 'user.manage');

if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$_GET['delete_id']]);
    logAction($pdo, $_SESSION['user_id'], 'Deleted user ID: ' . $_GET['delete_id']);
    header('Location: users.php');
    exit();
}

$stmt = $pdo->query('SELECT * FROM users');
$users = $stmt->fetchAll();
$page_title = 'User Administration';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>User Administration</h1>
                </div>
                <div class="col-sm-6">
                    <a href="add_user.php" class="btn btn-primary float-right">Add User</a>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td>
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">Edit</a>
                            <a href="users.php?delete_id=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure?');" class="btn btn-sm btn-danger">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>