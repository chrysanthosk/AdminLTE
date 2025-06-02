<?php
// edit_user.php - edit existing user
require_once '../auth.php';
requireAdmin();

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}
$user_id = $_GET['id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data) {
    header('Location: users.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $theme = $_POST['theme'];

    if (!empty($_POST['password'])) {
        $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, role = ?, first_name = ?, last_name = ?, theme = ?, password_hash = ? WHERE id = ?');
        $stmt->execute([$username, $email, $role, $first_name, $last_name, $theme, $password_hash, $user_id]);
    } else {
        $stmt = $pdo->prepare('UPDATE users SET username = ?, email = ?, role = ?, first_name = ?, last_name = ?, theme = ? WHERE id = ?');
        $stmt->execute([$username, $email, $role, $first_name, $last_name, $theme, $user_id]);
    }

    logAction($pdo, $_SESSION['user_id'], 'Edited user ID: ' . $user_id);
    header('Location: users.php');
    exit();
}
$page_title = 'Edit User';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Edit User</h1>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Password (leave blank to keep current)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" class="form-control">
                        <option value="user" <?php echo ($user_data['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo ($user_data['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user_data['first_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user_data['last_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Theme</label>
                    <select name="theme" class="form-control">
                        <option value="light" <?php echo ($user_data['theme'] === 'light') ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo ($user_data['theme'] === 'dark') ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">Update User</button>
            </form>
        </div>
    </section>
</div>

<?php include '../includes/footer.php'; ?>