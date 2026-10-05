<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');
$me = currentUser();
$flash=''; $flashType='success';

if ($_SERVER['REQUEST_METHOD']==='POST' && !csrfValid()) {
    $flash=CSRF_ERROR; $flashType='danger';
} elseif ($_SERVER['REQUEST_METHOD']==='POST') {
    $action=$_POST['action']??'';
    if ($action==='add'||$action==='edit') {
        $fullName  =trim($_POST['full_name']??'');
        $email     =trim($_POST['email']??'');
        $role      =($_POST['role']??'user')==='admin'?'admin':'user';
        $department=trim($_POST['department']??'');
        $status    =($_POST['status']??'active')==='inactive'?'inactive':'active';
        $password  =$_POST['password']??'';
        if ($fullName===''||$email==='') { $flash='Full name and email are required.'; $flashType='danger'; }
        else {
            $excludeId=$action==='edit'?(int)($_POST['user_id']??0):0;
            $chk=$conn->prepare("SELECT id FROM users WHERE email=? AND id!=?"); $chk->bind_param("si",$email,$excludeId); $chk->execute();
            if ($chk->get_result()->num_rows>0) { $flash='That email is already in use.'; $flashType='danger'; }
            elseif ($action==='add') {
                if ($password==='') { $flash='Password is required for new accounts.'; $flashType='danger'; }
                elseif (($pwdError=passwordPolicyError($password))!=='') { $flash=$pwdError; $flashType='danger'; }
                else {
                    $hash=password_hash($password,PASSWORD_BCRYPT);
                    $s=$conn->prepare("INSERT INTO users (full_name,email,password,role,department,status) VALUES (?,?,?,?,?,?)");
                    $s->bind_param("ssssss",$fullName,$email,$hash,$role,$department,$status); $s->execute(); $s->close();
                    logActivity($conn,$me['id'],"Added user \"$fullName\" ($role)");
                    $flash="User \"$fullName\" created successfully.";
                }
            } else {
                $userId=(int)($_POST['user_id']??0);
                $pwdError=$password!==''?passwordPolicyError($password):'';
                if (isLastActiveAdmin($conn,$userId)&&($role!=='admin'||$status!=='active')) {
                    $flash='This is the last active administrator account — it must remain an active administrator.'; $flashType='danger';
                }
                elseif ($pwdError!=='') { $flash=$pwdError; $flashType='danger'; }
                else {
                    if ($password!=='') {
                        $hash=password_hash($password,PASSWORD_BCRYPT);
                        $s=$conn->prepare("UPDATE users SET full_name=?,email=?,role=?,department=?,status=?,password=? WHERE id=?"); $s->bind_param("ssssssi",$fullName,$email,$role,$department,$status,$hash,$userId);
                    } else {
                        $s=$conn->prepare("UPDATE users SET full_name=?,email=?,role=?,department=?,status=? WHERE id=?"); $s->bind_param("sssssi",$fullName,$email,$role,$department,$status,$userId);
                    }
                    $s->execute(); $s->close();
                    logActivity($conn,$me['id'],"Edited user \"$fullName\" (#$userId)"); $flash="User \"$fullName\" updated.";
                }
            }
            $chk->close();
        }
    } elseif ($action==='delete') {
        $userId=(int)($_POST['user_id']??0);
        if ($userId===(int)$me['id']) { $flash='You cannot delete your own account.'; $flashType='danger'; }
        elseif (isLastActiveAdmin($conn,$userId)) { $flash='This is the last active administrator account — it cannot be removed.'; $flashType='danger'; }
        else {
            $nr=$conn->prepare("SELECT full_name FROM users WHERE id=?"); $nr->bind_param("i",$userId); $nr->execute();
            $name=$nr->get_result()->fetch_assoc()['full_name']??'#'.$userId; $nr->close();
            $s=$conn->prepare("DELETE FROM users WHERE id=?"); $s->bind_param("i",$userId); $s->execute(); $s->close();
            logActivity($conn,$me['id'],"Deleted user \"$name\""); $flash="User \"$name\" removed.";
        }
    }
}

$pageTitle='User Management'; $activeNav='users';
require __DIR__ . '/../includes/admin_header.php';

$totalUsers  =(int)($conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()['c']);
$activeUsers =(int)($conn->query("SELECT COUNT(*) c FROM users WHERE status='active'")->fetch_assoc()['c']);
$adminUsers  =(int)($conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch_assoc()['c']);
$inactiveUsers=(int)($conn->query("SELECT COUNT(*) c FROM users WHERE status='inactive'")->fetch_assoc()['c']);

$roleFilter=$_GET['role']??''; $statusFilter=$_GET['status']??'';
$where=[];$params=[];$types='';
if($roleFilter){$where[]='role=?';$params[]=$roleFilter;$types.='s';}
if($statusFilter){$where[]='status=?';$params[]=$statusFilter;$types.='s';}
$sql="SELECT id,full_name,email,role,department,status,created_at FROM users".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY created_at DESC";
$stmt=$conn->prepare($sql); if($types)$stmt->bind_param($types,...$params); $stmt->execute(); $users=$stmt->get_result();
?>

<div class="page-head"><div><h2>User Management</h2><div class="desc">Manage system users and permissions</div></div>
  <button class="btn btn-primary" onclick="openAddUserModal()">+ Add New User</button>
</div>
<?= $flash ? alertBox($flash, $flashType) : '' ?>

<div class="stat-grid">

    <div class="stat-card user-stat-card total-card">
        <div class="stat-content">
            <span class="stat-label">Total Users</span>
            <h2 class="stat-value"><?= number_format($totalUsers) ?></h2>
            <span class="stat-sub">Registered accounts</span>
        </div>

        <div class="stat-icon ic-blue">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>

    <div class="stat-card user-stat-card active-card">
        <div class="stat-content">
            <span class="stat-label">Active Users</span>
            <h2 class="stat-value"><?= number_format($activeUsers) ?></h2>
            <span class="stat-sub">Currently enabled</span>
        </div>

        <div class="stat-icon ic-green">
            <i class="fa-solid fa-user-check"></i>
        </div>
    </div>

    <div class="stat-card user-stat-card admin-card">
        <div class="stat-content">
            <span class="stat-label">Administrators</span>
            <h2 class="stat-value"><?= number_format($adminUsers) ?></h2>
            <span class="stat-sub">System managers</span>
        </div>

        <div class="stat-icon ic-amber">
            <i class="fa-solid fa-user-shield"></i>
        </div>
    </div>

    <div class="stat-card user-stat-card inactive-card">
        <div class="stat-content">
            <span class="stat-label">Inactive Users</span>
            <h2 class="stat-value"><?= number_format($inactiveUsers) ?></h2>
            <span class="stat-sub">Disabled accounts</span>
        </div>

        <div class="stat-icon ic-red">
            <i class="fa-solid fa-user-slash"></i>
        </div>
    </div>

</div>

<div class="panel">
  <div class="panel-head users-panel-head">

      <div>
          <h3>All Users</h3>
          <p class="panel-subtitle">
              <?= $totalUsers ?> registered account<?= $totalUsers == 1 ? '' : 's' ?>
          </p>
      </div>

      <form method="GET" class="users-filter">

          <select name="role" class="form-control" onchange="this.form.submit()">
              <option value="">All Roles</option>
              <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Administrator</option>
              <option value="user" <?= $roleFilter==='user'?'selected':'' ?>>User</option>
          </select>

          <select name="status" class="form-control" onchange="this.form.submit()">
              <option value="">All Status</option>
              <option value="active" <?= $statusFilter==='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>Inactive</option>
          </select>

      </form>

  </div>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>User</th><th>Email</th><th class="text-center">Role</th><th>Department</th><th class="text-center">Status</th><th class="text-center">Actions</th></tr></thead>
    <tbody>
    <?php $hasRows=false; while($row=$users->fetch_assoc()): $hasRows=true;
        $initials=strtoupper(substr($row['full_name'],0,1).substr(strrchr($row['full_name'],' ')?:'',1,1));
        $uj=h(json_encode(['id'=>$row['id'],'full_name'=>$row['full_name'],'email'=>$row['email'],'role'=>$row['role'],'department'=>$row['department'],'status'=>$row['status'],'created_at'=>$row['created_at']]));
    ?>
      <tr class="<?= (int)$row['id']===(int)$me['id']?'current-user-row':'' ?>">
        <td><div class="flex items-center gap-8"><div class="user-table-avatar"><?= h($initials?:'U') ?></div><span class="cell-strong"><?= h($row['full_name']) ?></span></div></td>
        <td class="cell-muted"><?= h($row['email']) ?></td>
        <td class="text-center"><?= badge(ucfirst($row['role']),$row['role']==='admin'?'badge-info':'badge-muted') ?></td>
        <td><?= h($row['department']?:'—') ?></td>
        <td class="text-center"><?= badge(ucfirst($row['status']),$row['status']==='active'?'badge-success':'badge-muted') ?></td>
        <td class="text-center"><div class="row-actions">
          <button class="icon-btn" title="View" onclick='openViewUser(<?= $uj ?>)'><i class="fa-solid fa-eye"></i></button>
          <button class="icon-btn" title="Edit" onclick='openEditUser(<?= $uj ?>)'><i class="fa-solid fa-pen"></i></button>
          <?php if((int)$row['id']!==(int)$me['id']): ?>
          <form method="POST" style="display:inline" onsubmit="return confirmDelete('Remove user &quot;<?= h(addslashes($row['full_name'])) ?>&quot;?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $row['id'] ?>">
            <button class="icon-btn danger" title="Delete" type="submit"><i class="fa-solid fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endwhile; if(!$hasRows): ?><?= emptyStateRow(6, 'fa-solid fa-user-slash', 'No users found.') ?><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-backdrop" id="userModal">
  <div class="modal"><div class="modal-head"><h3 id="userModalTitle">Add New User</h3><button class="modal-close" onclick="closeModal('userModal')" aria-label="Close">✕</button></div>
    <form method="POST"><div class="modal-body">
      <?= csrfField() ?>
      <input type="hidden" name="action" id="user_action" value="add"><input type="hidden" name="user_id" id="user_id">
      <div class="form-group"><label for="uf_name">Full Name</label><input type="text" name="full_name" id="uf_name" class="form-control" required></div>
      <div class="form-row">
        <div class="form-group"><label for="uf_email">Email Address</label><input type="email" name="email" id="uf_email" class="form-control" required></div>
        <div class="form-group"><label for="uf_dept">Department</label><input type="text" name="department" id="uf_dept" class="form-control"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label for="uf_role">Role</label><select name="role" id="uf_role" class="form-control"><option value="user">User (Employee/Requester)</option><option value="admin">Administrator</option></select></div>
        <div class="form-group"><label for="uf_status">Status</label><select name="status" id="uf_status" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
      </div>
      <div class="form-group"><label for="uf_pwd" id="uf_pwd_label">Password</label><input type="password" name="password" id="uf_pwd" class="form-control" placeholder="••••••••"><div class="password-callout" id="uf_pwd_hint"><?= h(PASSWORD_RULE_TEXT) ?></div></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Cancel</button><button type="submit" class="btn btn-primary">Save User</button></div>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="viewUserModal">
  <div class="modal"><div class="modal-head"><h3>User Details</h3><button class="modal-close" onclick="closeModal('viewUserModal')" aria-label="Close">✕</button></div>
    <div class="modal-body" id="viewUserBody"></div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" onclick="closeModal('viewUserModal')">Close</button></div>
  </div>
</div>

<script>
function openAddUserModal(){
  document.getElementById('userModalTitle').textContent='Add New User';
  document.getElementById('user_action').value='add'; document.getElementById('user_id').value='';
  ['uf_name','uf_email','uf_dept','uf_pwd'].forEach(id=>document.getElementById(id).value='');
  document.getElementById('uf_role').value='user'; document.getElementById('uf_status').value='active';
  document.getElementById('uf_pwd_label').textContent='Password'; document.getElementById('uf_pwd').required=true;
  document.getElementById('uf_pwd_hint').textContent=<?= json_encode(PASSWORD_RULE_TEXT) ?>;
  openModal('userModal');
}
function openEditUser(u){
  document.getElementById('userModalTitle').textContent='Edit User — '+u.full_name;
  document.getElementById('user_action').value='edit'; document.getElementById('user_id').value=u.id;
  document.getElementById('uf_name').value=u.full_name; document.getElementById('uf_email').value=u.email;
  document.getElementById('uf_dept').value=u.department||''; document.getElementById('uf_role').value=u.role;
  document.getElementById('uf_status').value=u.status; document.getElementById('uf_pwd').value='';
  document.getElementById('uf_pwd').required=false; document.getElementById('uf_pwd_label').textContent='New Password';
  document.getElementById('uf_pwd_hint').textContent='Leave blank to keep current password. '+<?= json_encode(PASSWORD_RULE_TEXT) ?>;
  openModal('userModal');
}
function openViewUser(u){
  const body=document.getElementById('viewUserBody');
  // Static markup only — user-supplied values are filled in below as text.
  body.innerHTML=`
    <div class="form-group"><label>Full Name</label><div class="cell-strong" data-field="full_name"></div></div>
    <div class="form-group"><label>Email</label><div data-field="email"></div></div>
    <div class="form-row">
      <div class="form-group"><label>Role</label><div data-field="role"></div></div>
      <div class="form-group"><label>Status</label><div data-field="status"></div></div>
    </div>
    <div class="form-group"><label>Department</label><div data-field="department"></div></div>
    <div class="form-group"><label>Account Created</label><div data-field="created_at"></div></div>`;
  fillFields(body,{
    full_name:  u.full_name,
    email:      u.email,
    role:       u.role==='admin'?'Administrator':'User (Employee/Requester)',
    status:     u.status ? u.status.charAt(0).toUpperCase()+u.status.slice(1) : '',
    department: u.department||'—',
    created_at: u.created_at
  });
  openModal('viewUserModal');
}
</script>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
