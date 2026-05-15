<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Lib\Database;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

session_start();

$pdo = Database::pdo();
$productRepo = new ProductRepository($pdo);
$categoryRepo = new CategoryRepository($pdo);

// ----- Constants -----
const UPLOAD_DIR      = __DIR__ . '/../uploads/products/';
const UPLOAD_MAX_SIZE = 2 * 1024 * 1024; // 2 MB
const ALLOWED_MIMES   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];

// ----- Flash message -----
$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// ----- State -----
$formErrors  = [];
$formData    = [];
$modalOpen   = false;
$isEditing   = false;
$editProduct = null;

// =========================================================
// POST handler
// =========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- Soft delete ----
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $productRepo->softDelete($id);
            $_SESSION['flash'] = ['type' => 'success', 'text' => '商品を無効化しました。'];
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ---- Create / Update ----
    if (in_array($action, ['create', 'update'], true)) {

        // Collect form values
        $formData = [
            'id'                 => (int)($_POST['id'] ?? 0),
            'name'               => trim($_POST['name'] ?? ''),
            'category_id'        => (($_POST['category_id'] ?? '') !== '') ? (int)$_POST['category_id'] : null,
            'price'              => $_POST['price'] ?? '',
            'tax_rate'           => $_POST['tax_rate'] ?? '10',
            'tax_type'           => $_POST['tax_type'] ?? 'standard',
            'icon'               => trim($_POST['icon'] ?? ''),
            'stock_quantity'     => (($_POST['stock_quantity'] ?? '') !== '') ? (int)$_POST['stock_quantity'] : null,
            'is_active'          => isset($_POST['is_active']) ? 1 : 0,
            'display_order'      => (int)($_POST['display_order'] ?? 0),
            'image_path'         => $_POST['current_image_path'] ?? null, // preserve existing
        ];

        // ---- Validate text fields ----
        if ($formData['name'] === '') {
            $formErrors['name'] = '商品名は必須です。';
        }
        if (!is_numeric($formData['price']) || (int)$formData['price'] < 0) {
            $formErrors['price'] = '価格は 0 以上の整数で入力してください。';
        }

        // ---- Validate image ----
        $fileUploaded = isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK;
        $fileMime     = null;

        if ($fileUploaded) {
            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $fileMime = finfo_file($finfo, $_FILES['image']['tmp_name']);
            finfo_close($finfo);

            if (!array_key_exists($fileMime, ALLOWED_MIMES)) {
                $formErrors['image'] = '画像ファイル（JPEG, PNG, GIF, WebP）のみアップロード可能です。';
                $fileUploaded        = false;
            } elseif ($_FILES['image']['size'] > UPLOAD_MAX_SIZE) {
                $formErrors['image'] = 'ファイルサイズは 2MB 以内にしてください。';
                $fileUploaded        = false;
            }
        } elseif (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $formErrors['image'] = 'ファイルのアップロードに失敗しました（エラーコード: ' . $_FILES['image']['error'] . '）。';
        }

        // ---- Save if no errors ----
        if ($formErrors === []) {
            $currentImagePath = $formData['image_path'];
            $imagePath        = $currentImagePath; // default: keep

            if ($fileUploaded) {
                // Move uploaded file
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }
                $ext      = ALLOWED_MIMES[$fileMime];
                $filename = 'prod_' . bin2hex(random_bytes(8)) . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename);

                // Delete old file
                if ($currentImagePath !== null) {
                    $old = dirname(__DIR__) . '/' . $currentImagePath;
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                $imagePath = 'uploads/products/' . $filename;

            } elseif (isset($_POST['remove_image'])) {
                if ($currentImagePath !== null) {
                    $old = dirname(__DIR__) . '/' . $currentImagePath;
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                $imagePath = null;
            }

            $params = [
                ':category_id'    => $formData['category_id'],
                ':name'           => $formData['name'],
                ':price'          => (int)$formData['price'],
                ':tax_rate'       => (float)$formData['tax_rate'],
                ':tax_type'       => in_array($formData['tax_type'], ['standard', 'reduced'], true)
                                        ? $formData['tax_type'] : 'standard',
                ':icon'           => $formData['icon'] !== '' ? $formData['icon'] : null,
                ':image_path'     => $imagePath,
                ':stock_quantity' => $formData['stock_quantity'],
                ':is_active'      => $formData['is_active'],
                ':display_order'  => $formData['display_order'],
            ];

            if ($action === 'create') {
                $productRepo->create($params);
                $_SESSION['flash'] = ['type' => 'success', 'text' => '商品を追加しました。'];
            } else {
                $productRepo->update($formData['id'], $params);
                $_SESSION['flash'] = ['type' => 'success', 'text' => '商品を更新しました。'];
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Reopen modal with errors
        $modalOpen = true;
        $isEditing = ($action === 'update');
    }
}

// =========================================================
// GET handlers
// =========================================================
if (!$modalOpen && isset($_GET['edit'])) {
    $editProduct = $productRepo->find((int)$_GET['edit']);
    if ($editProduct !== null) {
        $modalOpen = true;
        $isEditing = true;
    }
}

if (!$modalOpen && isset($_GET['new'])) {
    $modalOpen = true;
    $isEditing = false;
}

// =========================================================
// Data
// =========================================================
$products   = $productRepo->findAll(['include_inactive' => true]);
$categories = $categoryRepo->findAll(includeInactive: true);

// =========================================================
// Template helpers
// =========================================================

/** Current field value for the modal */
function fv(string $key, string|int|null $default = ''): string
{
    global $formData, $editProduct;
    if ($formData !== []) {
        return htmlspecialchars((string)($formData[$key] ?? $default), ENT_QUOTES);
    }
    if ($editProduct !== null) {
        return htmlspecialchars((string)($editProduct[$key] ?? $default), ENT_QUOTES);
    }
    return htmlspecialchars((string)$default, ENT_QUOTES);
}

/** Is select option selected? */
function fSelected(string $key, string|int $option, string|int|null $default = null): string
{
    global $formData, $editProduct;
    $cur = $default;
    if ($formData !== []) {
        $cur = $formData[$key] ?? $default;
    } elseif ($editProduct !== null) {
        $cur = $editProduct[$key] ?? $default;
    }
    return (string)$cur === (string)$option ? 'selected' : '';
}

/** Is checkbox checked? */
function fChecked(string $key, bool $default = true): string
{
    global $formData, $editProduct;
    if ($formData !== []) {
        return (bool)($formData[$key] ?? $default) ? 'checked' : '';
    }
    if ($editProduct !== null) {
        return (bool)($editProduct[$key] ?? $default) ? 'checked' : '';
    }
    return $default ? 'checked' : '';
}

/** URL for a stored image path (relative from /admin/) */
function imgUrl(string|null $path): string|null
{
    if ($path === null || $path === '') {
        return null;
    }
    return '../' . htmlspecialchars($path, ENT_QUOTES);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品管理 | POS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<div class="flex h-screen overflow-hidden">

    <!-- ===== Sidebar ===== -->
    <aside class="w-56 bg-gray-900 text-white flex-shrink-0 flex flex-col">
        <div class="px-5 py-5 border-b border-gray-700">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">POS 2026</p>
            <p class="text-lg font-bold mt-0.5">管理画面</p>
        </div>
        <nav class="flex-1 py-4">
            <a href="products.php"
               class="flex items-center gap-3 px-5 py-3 bg-gray-700 text-white font-medium text-sm">
                <span>📦</span>商品管理
            </a>
        </nav>
    </aside>

    <!-- ===== Main ===== -->
    <div class="flex-1 flex flex-col overflow-hidden">

        <!-- Top bar -->
        <header class="bg-white border-b px-6 py-4 flex items-center justify-between flex-shrink-0">
            <div>
                <p class="text-xs text-gray-400">管理画面</p>
                <h1 class="text-xl font-bold text-gray-800">商品管理</h1>
            </div>
            <a href="?new=1"
               class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <span class="text-base leading-none">＋</span>新規追加
            </a>
        </header>

        <main class="flex-1 overflow-auto p-6">

            <!-- Flash -->
            <?php if ($flash !== null): ?>
            <div class="mb-4 flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium
                <?= $flash['type'] === 'success'
                    ? 'bg-green-50 text-green-700 border border-green-200'
                    : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <span><?= $flash['type'] === 'success' ? '✓' : '✕' ?></span>
                <?= htmlspecialchars($flash['text'], ENT_QUOTES) ?>
            </div>
            <?php endif; ?>

            <!-- Filter bar -->
            <div class="bg-white rounded-xl shadow-sm border px-5 py-4 mb-5 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-48">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">🔍</span>
                    <input type="text" id="searchInput" placeholder="商品名で検索..."
                           class="w-full pl-9 pr-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           oninput="applyFilter()">
                </div>
                <select id="categoryFilter" onchange="applyFilter()"
                        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">全カテゴリ</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer select-none">
                    <input type="checkbox" id="showInactive" onchange="applyFilter()" class="w-4 h-4 rounded accent-blue-600">
                    無効も表示
                </label>
                <span class="text-xs text-gray-400 ml-auto" id="countLabel"></span>
            </div>

            <!-- Product table -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="text-left px-4 py-3 w-12">ID</th>
                            <th class="text-left px-4 py-3">商品</th>
                            <th class="text-left px-4 py-3">カテゴリ</th>
                            <th class="text-right px-4 py-3">価格</th>
                            <th class="text-center px-4 py-3">税率</th>
                            <th class="text-center px-4 py-3">在庫</th>
                            <th class="text-center px-4 py-3 w-16">順</th>
                            <th class="text-center px-4 py-3">状態</th>
                            <th class="text-center px-4 py-3 w-28">操作</th>
                        </tr>
                    </thead>
                    <tbody id="productTable">
                    <?php foreach ($products as $p): ?>
                    <?php $thumb = imgUrl($p['image_path'] ?? null); ?>
                    <tr class="product-row border-b last:border-0 hover:bg-gray-50 transition-colors"
                        data-name="<?= htmlspecialchars(mb_strtolower($p['name']), ENT_QUOTES) ?>"
                        data-category="<?= $p['category_id'] ?? '' ?>"
                        data-active="<?= $p['is_active'] ?>">
                        <td class="px-4 py-3 text-gray-400 font-mono text-xs"><?= $p['id'] ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <!-- Thumbnail or emoji -->
                                <?php if ($thumb !== null): ?>
                                <img src="<?= $thumb ?>" alt=""
                                     class="w-10 h-10 rounded-lg object-cover border border-gray-100 flex-shrink-0">
                                <?php else: ?>
                                <span class="w-10 h-10 flex items-center justify-center text-2xl leading-none flex-shrink-0 bg-gray-50 rounded-lg border border-gray-100">
                                    <?= htmlspecialchars($p['icon'] ?? '', ENT_QUOTES) ?>
                                </span>
                                <?php endif; ?>
                                <div>
                                    <p class="font-medium <?= $p['is_active'] ? 'text-gray-800' : 'text-gray-400 line-through' ?>">
                                        <?= htmlspecialchars($p['name'], ENT_QUOTES) ?>
                                    </p>
                                    <?php if (!empty($p['icon']) && $thumb !== null): ?>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($p['icon'], ENT_QUOTES) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            <?= htmlspecialchars($p['category_name'] ?? '－', ENT_QUOTES) ?>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                            ¥<?= number_format($p['price']) ?>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            <?= number_format((float)$p['tax_rate'], 0) ?>%
                            <?php if ($p['tax_type'] === 'reduced'): ?>
                            <span class="ml-1 text-xs bg-amber-100 text-amber-700 px-1 rounded">軽減</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-600">
                            <?= $p['stock_quantity'] !== null
                                ? number_format((int)$p['stock_quantity'])
                                : '<span class="text-gray-300">∞</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500"><?= $p['display_order'] ?></td>
                        <td class="px-4 py-3 text-center">
                            <?php if ($p['is_active']): ?>
                            <span class="inline-flex items-center gap-1 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs px-2 py-0.5 rounded-full font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>有効
                            </span>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-500 border border-gray-200 text-xs px-2 py-0.5 rounded-full font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>無効
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <a href="?edit=<?= $p['id'] ?>"
                               class="inline-block text-blue-600 hover:text-blue-800 font-medium px-2 py-1 rounded hover:bg-blue-50 transition-colors">
                               編集
                            </a>
                            <button onclick="confirmDelete(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')"
                                    class="text-red-500 hover:text-red-700 font-medium px-2 py-1 rounded hover:bg-red-50 transition-colors">
                                削除
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="emptyState" class="hidden py-16 text-center text-gray-400">
                    <p class="text-4xl mb-3">📦</p>
                    <p class="text-sm">該当する商品がありません</p>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- =========================================================
     Create / Edit Modal
     ========================================================= -->
<div id="modal"
     class="<?= $modalOpen ? '' : 'hidden' ?> fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[92vh] overflow-y-auto">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b sticky top-0 bg-white rounded-t-2xl z-10">
            <h2 class="text-base font-bold text-gray-800">
                <?= $isEditing ? '商品を編集' : '商品を追加' ?>
            </h2>
            <button onclick="closeModal()"
                    class="w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                ✕
            </button>
        </div>

        <!-- Form (multipart for file upload) -->
        <form method="POST"
              action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>"
              enctype="multipart/form-data"
              class="px-6 py-5 space-y-4">

            <input type="hidden" name="action" value="<?= $isEditing ? 'update' : 'create' ?>">
            <?php if ($isEditing): ?>
            <input type="hidden" name="id" value="<?= fv('id') ?>">
            <?php endif; ?>
            <!-- Preserve existing image path across failed POSTs -->
            <input type="hidden" name="current_image_path" value="<?= fv('image_path') ?>">

            <!-- Name -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                    商品名 <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="<?= fv('name') ?>" required autofocus
                       class="w-full border <?= isset($formErrors['name']) ? 'border-red-400 bg-red-50' : 'border-gray-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php if (isset($formErrors['name'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($formErrors['name'], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>

            <!-- Category + Icon -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">カテゴリ</label>
                    <select name="category_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">なし</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= fSelected('category_id', $cat['id']) ?>>
                            <?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">アイコン (絵文字)</label>
                    <input type="text" name="icon" value="<?= fv('icon') ?>" maxlength="20" placeholder="🍌"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 text-xl">
                </div>
            </div>

            <!-- Image upload -->
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">商品画像</label>

                <?php $currentImgUrl = imgUrl(fv('image_path', null) ?: null); ?>

                <!-- Current image (edit mode or failed POST with existing image) -->
                <div id="currentImgWrap" class="<?= $currentImgUrl ? '' : 'hidden' ?> mb-3 flex items-start gap-3">
                    <img id="currentImg"
                         src="<?= $currentImgUrl ?? '' ?>"
                         alt="現在の画像"
                         class="w-20 h-20 object-cover rounded-lg border border-gray-200 flex-shrink-0">
                    <div>
                        <p class="text-xs text-gray-500 mb-2">現在の画像</p>
                        <label class="flex items-center gap-2 text-xs text-red-600 cursor-pointer select-none">
                            <input type="checkbox" name="remove_image" id="removeImage"
                                   class="w-3.5 h-3.5 accent-red-600"
                                   onchange="toggleRemoveImage(this)">
                            この画像を削除する
                        </label>
                    </div>
                </div>

                <!-- Drop zone / file input -->
                <div id="dropZone"
                     class="border-2 border-dashed rounded-xl p-5 text-center transition-colors
                            <?= isset($formErrors['image']) ? 'border-red-400 bg-red-50' : 'border-gray-200 hover:border-blue-400 bg-gray-50 hover:bg-blue-50' ?>"
                     ondragover="event.preventDefault(); this.classList.add('border-blue-500','bg-blue-50')"
                     ondragleave="this.classList.remove('border-blue-500','bg-blue-50')"
                     ondrop="handleDrop(event)">
                    <input type="file" name="image" id="imageInput"
                           accept="image/jpeg,image/png,image/gif,image/webp"
                           class="hidden" onchange="handleFileSelect(this.files[0])">

                    <!-- Default label -->
                    <div id="dropLabel">
                        <p class="text-2xl mb-1">🖼️</p>
                        <p class="text-sm text-gray-600 font-medium">
                            <label for="imageInput" class="text-blue-600 cursor-pointer hover:underline">クリックして選択</label>
                            またはドラッグ&ドロップ
                        </p>
                        <p class="text-xs text-gray-400 mt-1">JPEG, PNG, GIF, WebP / 最大 2MB</p>
                    </div>

                    <!-- Preview after selection -->
                    <div id="previewWrap" class="hidden">
                        <img id="previewImg" src="" alt="プレビュー"
                             class="w-24 h-24 object-cover rounded-lg mx-auto border border-gray-200">
                        <p class="text-xs text-gray-500 mt-2" id="previewName"></p>
                        <button type="button" onclick="clearFileInput()"
                                class="mt-2 text-xs text-red-500 hover:underline">選択を解除</button>
                    </div>
                </div>

                <?php if (isset($formErrors['image'])): ?>
                <p class="mt-1.5 text-xs text-red-600"><?= htmlspecialchars($formErrors['image'], ENT_QUOTES) ?></p>
                <?php endif; ?>
            </div>

            <!-- Price + Tax rate -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">
                        価格 (円) <span class="text-red-500">*</span>
                    </label>
                    <input type="number" name="price" value="<?= fv('price', '0') ?>" min="0" required
                           class="w-full border <?= isset($formErrors['price']) ? 'border-red-400 bg-red-50' : 'border-gray-300' ?> rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php if (isset($formErrors['price'])): ?>
                    <p class="mt-1 text-xs text-red-600"><?= htmlspecialchars($formErrors['price'], ENT_QUOTES) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">税率 (%)</label>
                    <input type="number" name="tax_rate" value="<?= fv('tax_rate', '10') ?>" min="0" max="100" step="0.01"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Tax type + Stock -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">税種別</label>
                    <select name="tax_type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="standard" <?= fSelected('tax_type', 'standard', 'standard') ?>>標準税率</option>
                        <option value="reduced"  <?= fSelected('tax_type', 'reduced') ?>>軽減税率</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">在庫数</label>
                    <input type="number" name="stock_quantity" value="<?= fv('stock_quantity') ?>" min="0"
                           placeholder="空白 = 無制限"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <!-- Display order + Is active -->
            <div class="grid grid-cols-2 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5 uppercase tracking-wide">表示順</label>
                    <input type="number" name="display_order" value="<?= fv('display_order', '0') ?>"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="pb-2">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="is_active" <?= fChecked('is_active') ?>
                               class="w-4 h-4 rounded accent-blue-600">
                        <span class="text-sm font-medium text-gray-700">有効にする</span>
                    </label>
                </div>
            </div>

            <!-- Footer buttons -->
            <div class="flex justify-end gap-3 pt-3 border-t">
                <button type="button" onclick="closeModal()"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    キャンセル
                </button>
                <button type="submit"
                        class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                    <?= $isEditing ? '更新する' : '追加する' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete confirmation modal -->
<div id="deleteModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/50" onclick="closeDeleteModal()"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6">
        <div class="text-center">
            <div class="text-4xl mb-3">⚠️</div>
            <h3 class="text-base font-bold text-gray-800 mb-1">商品を無効化しますか？</h3>
            <p class="text-sm text-gray-500 mb-5">
                「<span id="deleteProductName" class="font-semibold text-gray-700"></span>」を無効化します。<br>
                後から編集画面で有効に戻せます。
            </p>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteProductId">
                <div class="flex gap-3 justify-center">
                    <button type="button" onclick="closeDeleteModal()"
                            class="flex-1 px-4 py-2 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        キャンセル
                    </button>
                    <button type="submit"
                            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors">
                        無効化する
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// ----- Modal -----
function closeModal() {
    window.location.href = '<?= htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES) ?>';
}

// ----- Delete modal -----
function confirmDelete(id, name) {
    document.getElementById('deleteProductId').value = id;
    document.getElementById('deleteProductName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// ----- Image upload -----
function handleFileSelect(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
        document.getElementById('previewImg').src = e.target.result;
        document.getElementById('previewName').textContent = file.name + ' (' + formatBytes(file.size) + ')';
        document.getElementById('dropLabel').classList.add('hidden');
        document.getElementById('previewWrap').classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('border-blue-500', 'bg-blue-50');
    const file = event.dataTransfer.files[0];
    if (!file) return;
    // Assign to the file input so it gets submitted with the form
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('imageInput').files = dt.files;
    handleFileSelect(file);
}

function clearFileInput() {
    document.getElementById('imageInput').value = '';
    document.getElementById('previewWrap').classList.add('hidden');
    document.getElementById('dropLabel').classList.remove('hidden');
}

function toggleRemoveImage(checkbox) {
    const img = document.getElementById('currentImg');
    img.style.opacity = checkbox.checked ? '0.25' : '1';
    img.style.filter  = checkbox.checked ? 'grayscale(1)' : '';
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// ----- Client-side filter -----
function applyFilter() {
    const q    = document.getElementById('searchInput').value.toLowerCase();
    const cat  = document.getElementById('categoryFilter').value;
    const show = document.getElementById('showInactive').checked;

    let count = 0;
    document.querySelectorAll('.product-row').forEach(row => {
        const ok = row.dataset.name.includes(q)
                && (cat === '' || row.dataset.category === cat)
                && (show || row.dataset.active === '1');
        row.style.display = ok ? '' : 'none';
        if (ok) count++;
    });

    document.getElementById('countLabel').textContent = count + ' 件';
    document.getElementById('emptyState').classList.toggle('hidden', count > 0);
}

applyFilter();
</script>

</body>
</html>
