<?php
// File: pages/pricelist_categories.php

require_once '../auth.php';
requirePermission($pdo, 'pricelist_category.manage');

// Handle create/update/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Create new
  if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    if ($name !== '') {
      $ins = $pdo->prepare("INSERT INTO pricelist_categories (name, created_at) VALUES (:name, NOW())");
      $ins->execute(['name' => $name]);
      header('Location: pricelist_categories.php?success=added');
      exit();
    }
  }
  // Edit existing
  if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id   = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    if ($id > 0 && $name !== '') {
      $upd = $pdo->prepare("UPDATE pricelist_categories SET name = :name, updated_at = NOW() WHERE id = :id");
      $upd->execute(['name' => $name, 'id' => $id]);
      header('Location: pricelist_categories.php?success=updated');
      exit();
    }
  }
}
// Handle deletes
if (isset($_GET['action']) && $_GET['action'] === 'delete' && !empty($_GET['id'])) {
  $del = $pdo->prepare("DELETE FROM pricelist_categories WHERE id = ?");
  $del->execute([(int)$_GET['id']]);
  header('Location: pricelist_categories.php?success=deleted');
  exit();
}

// Fetch all categories
$stmt = $pdo->query("SELECT id, name, created_at, updated_at FROM pricelist_categories ORDER BY name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6">
          <h1>Pricelist Categories</h1>
        </div>
        <div class="col-sm-6 text-right">
          <button class="btn btn-primary" data-toggle="modal" data-target="#addCategoryModal">
            <i class="fas fa-plus"></i> Add Category
          </button>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="card">
      <div class="card-body">
        <table id="categoriesTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Created</th>
              <th>Updated</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $c): ?>
            <tr>
              <td><?= $c['id'] ?></td>
              <td><?= htmlspecialchars($c['name'], ENT_QUOTES) ?></td>
              <td><?= $c['created_at'] ?></td>
              <td><?= $c['updated_at'] ?></td>
              <td>
                <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editCategoryModal<?= $c['id'] ?>">
                  <i class="fas fa-edit"></i>
                </button>
                <a href="pricelist_categories.php?action=delete&id=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this category?');">
                  <i class="fas fa-trash"></i>
                </a>
              </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editCategoryModal<?= $c['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editCategoryLabel<?= $c['id'] ?>" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <form method="POST" action="pricelist_categories.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                    <div class="modal-header">
                      <h5 class="modal-title" id="editCategoryLabel<?= $c['id'] ?>">Edit Category</h5>
                      <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                      </button>
                    </div>
                    <div class="modal-body">
                      <div class="form-group">
                        <label for="categoryName<?= $c['id'] ?>">Name</label>
                        <input type="text" class="form-control" id="categoryName<?= $c['id'] ?>" name="name" value="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" required>
                      </div>
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                      <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</div>
<?php include '../includes/footer.php'; ?>

<!-- Add Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" role="dialog" aria-labelledby="addCategoryLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form method="POST" action="pricelist_categories.php">
        <input type="hidden" name="action" value="add">
        <div class="modal-header">
          <h5 class="modal-title" id="addCategoryLabel">Add Pricelist Category</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label for="newCategoryName">Name</label>
            <input type="text" class="form-control" id="newCategoryName" name="name" placeholder="Enter category name" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Category</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(function(){
  $('#categoriesTable').DataTable({
    lengthMenu: [ [25,50,100,200,-1], [25,50,100,200,'All'] ],
    searching: true,
    paging: true
  });
});
</script>
