<?php
// pages/sms_settings.php — Manage SMS Providers & Settings with Priority and Success Alerts

require_once __DIR__ . '/../auth.php';
requirePermission($pdo, 'sms.manage');

// Handle Provider Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add_provider','edit_provider'])) {
  $provId = (int)($_POST['prov_id'] ?? 0);
  $name   = trim($_POST['prov_name'] ?? '');
  $doc    = trim($_POST['prov_doc'] ?? '');
  if ($name !== '') {
    if ($_POST['action'] === 'edit_provider' && $provId > 0) {
      $stmt = $pdo->prepare("UPDATE sms_providers SET name = ?, doc_url = ? WHERE id = ?");
      $stmt->execute([$name, $doc, $provId]);
      logAction($pdo, $_SESSION['user_id'], "Updated SMS provider ID $provId");
    } else {
      $stmt = $pdo->prepare("INSERT INTO sms_providers (name, doc_url) VALUES (?, ?)");
      $stmt->execute([$name, $doc]);
      $newId = $pdo->lastInsertId();
      logAction($pdo, $_SESSION['user_id'], "Added SMS provider ID $newId");
    }
  }
  header('Location: sms_settings.php'); exit();
}

// Handle Provider Delete
if (isset($_GET['delete_provider_id'])) {
  $delId = (int)$_GET['delete_provider_id'];
  $stmt = $pdo->prepare("DELETE FROM sms_providers WHERE id = ?");
  $stmt->execute([$delId]);
  logAction($pdo, $_SESSION['user_id'], "Deleted SMS provider ID $delId");
  header('Location: sms_settings.php'); exit();
}

// Handle Priority Update AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_priority') {
  $order = $_POST['order'] ?? [];
  if (is_array($order)) {
    foreach ($order as $priority => $id) {
      $stmt = $pdo->prepare("UPDATE sms_providers SET priority = ? WHERE id = ?");
      $stmt->execute([$priority, (int)$id]);
    }
    echo 'ok';
  }
  exit();
}

// Handle SMS Settings Save
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['action'])) {
  $provider_id = (int)($_POST['provider_id'] ?? 0);
  $api_key     = trim($_POST['api_key'] ?? '');
  $api_secret  = trim($_POST['api_secret'] ?? '');
  $sender_id   = trim($_POST['sender_id'] ?? '');
  $enabled     = isset($_POST['is_enabled']) ? 1 : 0;
  if (!$provider_id || $api_key === '') {
    $error = 'Please select a provider and enter the API key.';
  } else {
    $count = (int)$pdo->query("SELECT COUNT(*) FROM sms_settings")->fetchColumn();
    if ($count > 0) {
      $upd = $pdo->prepare(
        "UPDATE sms_settings
           SET provider_id = ?, api_key = ?, api_secret = ?, sender_id = ?, is_enabled = ?, updated_at = NOW()
         WHERE id = 1"
      );
      $upd->execute([$provider_id,$api_key,$api_secret,$sender_id,$enabled]);
    } else {
      $ins = $pdo->prepare(
        "INSERT INTO sms_settings (provider_id,api_key,api_secret,sender_id,is_enabled)
         VALUES (?,?,?,?,?)"
      );
      $ins->execute([$provider_id,$api_key,$api_secret,$sender_id,$enabled]);
    }
    logAction($pdo, $_SESSION['user_id'], 'Saved SMS settings');
    $success = 'Settings saved.';
  }
}

// Fetch Data
$providers = $pdo->query(
  "SELECT id, name, doc_url FROM sms_providers WHERE is_active = 1 ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

// Sorted by priority
$providersPriority = $pdo->query(
  "SELECT id, name FROM sms_providers WHERE is_active = 1 ORDER BY priority ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$sms = $pdo->query(
  "SELECT s.*, p.name AS provider_name
     FROM sms_settings s
     JOIN sms_providers p ON s.provider_id = p.id
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'SMS Settings';
?>
<?php include '../includes/header.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="content-wrapper">
  <section class="content-header">
    <div class="container-fluid">
      <div class="row mb-2">
        <div class="col-sm-6"><h1>SMS Settings</h1></div>
        <div class="col-sm-6 text-right">
          <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#providerModal" onclick="openCreateProviderModal()">
            <i class="fas fa-plus"></i> Add Provider
          </button>
        </div>
      </div>
    </div>
  </section>

  <section class="content">
    <div class="container-fluid">
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

      <!-- Providers Table -->
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">SMS Providers</h3></div>
        <div class="card-body">
          <table class="table table-bordered table-striped">
            <thead><tr><th>ID</th><th>Name</th><th>Docs URL</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach($providers as $p): ?>
              <tr>
                <td><?= $p['id'] ?></td>
                <td><?= htmlspecialchars($p['name'],ENT_QUOTES) ?></td>
                <td><a href="<?= htmlspecialchars($p['doc_url'],ENT_QUOTES) ?>" target="_blank">Docs</a></td>
                <td>
                  <button class="btn btn-sm btn-info" onclick="openEditProviderModal(<?= $p['id'] ?>,'<?= addslashes($p['name']) ?>','<?= addslashes($p['doc_url']) ?>')">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <a href="sms_settings.php?delete_provider_id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete provider?');">
                    <i class="fas fa-trash"></i>
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Draggable Priority List -->
      <div class="card mb-4">
        <div class="card-header"><h3 class="card-title">Provider Priority</h3></div>
        <div class="card-body">
          <div id="priorityAlert" class="alert alert-success" style="display:none;">
            Settings updated successfully.
          </div>
          <ul id="providerPriorityList" class="list-group">
            <?php foreach ($providersPriority as $pp): ?>
              <li class="list-group-item draggable-provider" data-id="<?= $pp['id'] ?>">
                <i class="fas fa-arrows-alt-v mr-2"></i> <?= htmlspecialchars($pp['name'],ENT_QUOTES) ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <button id="savePriority" class="btn btn-primary mt-2">
            <i class="fas fa-save"></i> Save Priority
          </button>
        </div>
      </div>

      <!-- SMS Settings Form -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Configure SMS API</h3></div>
        <div class="card-body">
          <form method="post">
            <div class="form-group">
              <label for="provider_id">SMS Provider<span class="text-danger">*</span></label>
              <select id="provider_id" name="provider_id" class="form-control" required>
                <option value="">— Select provider —</option>
                <?php foreach($providers as $p): ?>
                <option value="<?= $p['id'] ?>" <?= (isset($sms['provider_id']) && $sms['provider_id']==$p['id'])?'selected':'' ?>>
                  <?= htmlspecialchars($p['name'],ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="api_key">API Key<span class="text-danger">*</span></label>
              <input type="text" id="api_key" name="api_key" class="form-control" required value="<?= htmlspecialchars($sms['api_key'] ?? '',ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
              <label for="api_secret">API Secret</label>
              <input type="text" id="api_secret" name="api_secret" class="form-control" value="<?= htmlspecialchars($sms['api_secret'] ?? '',ENT_QUOTES) ?>">
            </div>
            <div class="form-group">
              <label for="sender_id">Sender ID</label>
              <input type="text" id="sender_id" name="sender_id" class="form-control" placeholder="E.g. MyClinic or +35795551234" value="<?= htmlspecialchars($sms['sender_id'] ?? '',ENT_QUOTES) ?>">
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" id="is_enabled" name="is_enabled" class="form-check-input" <?= !empty($sms['is_enabled'])?'checked':'' ?>>
              <label for="is_enabled" class="form-check-label">Enable SMS sending</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<?php include '../includes/footer.php'; ?>

<!-- Add/Edit Provider Modal -->
<div class="modal fade" id="providerModal" tabindex="-1" aria-labelledby="providerModalLabel" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <form method="post" id="providerModalForm">
      <input type="hidden" name="action" id="prov_action" value="">
      <input type="hidden" name="prov_id" id="prov_id" value="">
      <div class="modal-header">
        <h5 class="modal-title" id="providerModalLabel">Add SMS Provider</n        </h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label for="prov_name">Provider Name</label>
          <input type="text" id="prov_name" name="provider_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label for="prov_doc">Documentation URL</label>
          <input type="url" id="prov_doc" name="provider_doc" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Provider</button>
      </div>
    </form>
  </div></div>
</div>

<script>
$(function(){
  // Make providers list sortable
  $('#providerPriorityList').sortable({ handle: '.fa-arrows-alt-v' });

  // Save priority order, show success message
  $('#savePriority').click(function(){
    var order = $('#providerPriorityList .draggable-provider')
      .map((i,el)=>$(el).data('id')).get();
    $.post('sms_settings.php', { action:'update_priority', order:order})
      .done(function(){
        $('#priorityAlert').fadeIn().delay(2000).fadeOut();
      })
      .fail(function(){ alert('Error saving priority'); });
  });
});

function openCreateProviderModal() {
  $('#providerModalLabel').text('Add SMS Provider');
  $('#prov_action').val('add_provider');
  $('#prov_id, #prov_name, #prov_doc').val('');
  $('#providerModal').modal('show');
}

function openEditProviderModal(id,name,doc) {
  $('#providerModalLabel').text('Edit SMS Provider');
  $('#prov_action').val('edit_provider');
  $('#prov_id').val(id);
  $('#prov_name').val(name);
  $('#prov_doc').val(doc);
  $('#providerModal').modal('show');
}
</script>