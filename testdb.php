<?php
declare(strict_types=1);

use Platformsh\ConfigReader\Config;

require __DIR__ . '/vendor/autoload.php';

$config = new Config();
if (!$config->isValidPlatform()) {
    die("Not in a Platform.sh/Upsun Environment.");
}

$credentials = $config->credentials('database');
$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $credentials['host'],
    $credentials['port'],
    $credentials['path']
);

try {
    $conn = new \PDO($dsn, $credentials['username'], $credentials['password'], [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        \PDO::MYSQL_ATTR_FOUND_ROWS    => true,
        \PDO::ATTR_DEFAULT_FETCH_MODE  => \PDO::FETCH_ASSOC,
    ]);

    // Tạo bảng People nếu chưa có
    $conn->exec("CREATE TABLE IF NOT EXISTS People (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mssv VARCHAR(20) NOT NULL,
        name VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL
    )");

} catch (\Exception $e) {
    die("Lỗi kết nối CSDL: " . $e->getMessage());
}

// ---------- Routing đơn giản qua query string ?action= ----------
$action = $_GET['action'] ?? 'list';
$errors = [];
$msg = $_GET['msg'] ?? '';

// ---------- Xử lý ADD ----------
if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mssv  = trim($_POST['mssv'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($mssv === '')  $errors[] = "MSSV không được để trống.";
    if ($name === '')  $errors[] = "Họ tên không được để trống.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO People (mssv, name, email) VALUES (:mssv, :name, :email)"
        );
        $stmt->execute([':mssv' => $mssv, ':name' => $name, ':email' => $email]);

        header("Location: ?action=list&msg=" . urlencode("Thêm dữ liệu thành công!"));
        exit;
    }
}

// ---------- Xử lý UPDATE ----------
if ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)($_POST['id'] ?? 0);
    $mssv  = trim($_POST['mssv'] ?? '');
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($mssv === '')  $errors[] = "MSSV không được để trống.";
    if ($name === '')  $errors[] = "Họ tên không được để trống.";
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email không hợp lệ.";
    }

    if (empty($errors) && $id > 0) {
        $stmt = $conn->prepare(
            "UPDATE People SET mssv = :mssv, name = :name, email = :email WHERE id = :id"
        );
        $stmt->execute([':mssv' => $mssv, ':name' => $name, ':email' => $email, ':id' => $id]);

        header("Location: ?action=list&msg=" . urlencode("Cập nhật thành công!"));
        exit;
    }
}

// ---------- Xử lý DELETE ----------
if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM People WHERE id = :id");
        $stmt->execute([':id' => $id]);
        header("Location: ?action=list&msg=" . urlencode("Xóa thành công!"));
        exit;
    }
}

// ---------- Lấy dữ liệu cho form EDIT ----------
$editPerson = null;
if ($action === 'edit') {
    $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
    $stmt = $conn->prepare("SELECT * FROM People WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $editPerson = $stmt->fetch();

    if (!$editPerson) {
        header("Location: ?action=list&msg=" . urlencode("Không tìm thấy bản ghi."));
        exit;
    }

    // Nếu vừa submit lỗi, giữ lại dữ liệu vừa nhập
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
        $editPerson = [
            'id'    => $id,
            'mssv'  => $_POST['mssv'] ?? '',
            'name'  => $_POST['name'] ?? '',
            'email' => $_POST['email'] ?? '',
        ];
    }
}

// ---------- Lấy danh sách cho trang LIST ----------
$people = [];
if ($action === 'list') {
    $stmt = $conn->query("SELECT * FROM People ORDER BY id ASC");
    $people = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý sinh viên (People)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        h1 { color: #333; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        th { background: #4a4a4a; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        a.btn, button.btn {
            display: inline-block; padding: 5px 10px; margin: 0 2px;
            border-radius: 4px; text-decoration: none; color: #fff; font-size: 14px;
            border: none; cursor: pointer;
        }
        .btn-add    { background: #28a745; }
        .btn-edit   { background: #007bff; }
        .btn-delete { background: #dc3545; }
        .top-bar { margin-bottom: 15px; }
        .msg   { padding: 10px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 15px; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .form-box { background: #fff; padding: 20px; border-radius: 6px; max-width: 450px; }
        label { display: block; margin-top: 10px; font-weight: bold; }
        input { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
        .submit-btn { margin-top: 15px; padding: 8px 16px; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .submit-add  { background: #28a745; }
        .submit-edit { background: #007bff; }
        a.back { display: inline-block; margin-bottom: 15px; }
    </style>
</head>
<body>

<?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="error">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

    <h1>Danh sách sinh viên (People)</h1>

    <div class="top-bar">
        <a class="btn btn-add" href="?action=add">+ Thêm mới</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>MSSV</th>
                <th>Họ tên</th>
                <th>Email</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($people) === 0): ?>
                <tr><td colspan="6" style="text-align:center;">Chưa có dữ liệu</td></tr>
            <?php else: ?>
                <?php foreach ($people as $p): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$p['id']) ?></td>
                    <td><?= htmlspecialchars($p['mssv']) ?></td>
                    <td><?= htmlspecialchars($p['name']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td>
                        <a class="btn btn-edit" href="?action=edit&id=<?= (int)$p['id'] ?>">Sửa</a>
                        <a class="btn btn-delete" href="?action=delete&id=<?= (int)$p['id'] ?>"
                           onclick="return confirm('Bạn có chắc muốn xóa bản ghi này?');">Xóa</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($action === 'add'): ?>

    <a class="back" href="?action=list">&larr; Quay lại danh sách</a>
    <h1>Thêm mới</h1>

    <div class="form-box">
        <form method="post" action="?action=add">
            <label>MSSV</label>
            <input type="text" name="mssv" value="<?= htmlspecialchars($_POST['mssv'] ?? '') ?>" required>

            <label>Họ tên</label>
            <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>

            <button type="submit" class="submit-btn submit-add">Lưu</button>
        </form>
    </div>

<?php elseif ($action === 'edit' && $editPerson): ?>

    <a class="back" href="?action=list">&larr; Quay lại danh sách</a>
    <h1>Sửa thông tin (ID: <?= (int)$editPerson['id'] ?>)</h1>

    <div class="form-box">
        <form method="post" action="?action=edit">
            <input type="hidden" name="id" value="<?= (int)$editPerson['id'] ?>">

            <label>MSSV</label>
            <input type="text" name="mssv" value="<?= htmlspecialchars($editPerson['mssv']) ?>" required>

            <label>Họ tên</label>
            <input type="text" name="name" value="<?= htmlspecialchars($editPerson['name']) ?>" required>

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($editPerson['email']) ?>" required>

            <button type="submit" class="submit-btn submit-edit">Cập nhật</button>
        </form>
    </div>

<?php else: ?>
    <p>Hành động không hợp lệ. <a href="?action=list">Quay lại danh sách</a></p>
<?php endif; ?>

</body>
</html>