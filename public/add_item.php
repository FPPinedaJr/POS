<?php
session_start();
require_once __DIR__ . '/includes/connect_db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Load categories for the select dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT idcategory, category_name FROM category ORDER BY category_name ASC");
    $categories = $stmt->fetchAll();
} catch (Throwable $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Item</title>
    <link rel="stylesheet" href="assets/css/output.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>

<body class="min-h-screen bg-gray-50 flex flex-col">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-3xl mx-auto px-6 py-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button type="button" onclick="window.history.back()"
                    class="h-9 w-9 inline-flex items-center justify-center rounded-full border border-gray-200 text-gray-600 hover:bg-gray-50">
                    <i class="fa-solid fa-chevron-left text-sm"></i>
                </button>
                <div>
                    <h1 class="text-lg font-semibold text-gray-900">Add new item</h1>
                    <p class="text-sm text-gray-500">Upload an image and set basic details.</p>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="max-w-3xl mx-auto px-6 py-8">
            <form action="includes/save_item.php" method="POST" enctype="multipart/form-data"
                class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">

                <div class="space-y-1">
                    <label for="item_name" class="block text-sm font-medium text-gray-700">Item name</label>
                    <input type="text" name="item_name" id="item_name" required
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                        placeholder="e.g. Wireless Mouse">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label for="idcategory" class="block text-sm font-medium text-gray-700">Category</label>
                        <select name="idcategory" id="idcategory" required
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm bg-white">
                            <option value="" disabled selected>Select category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int) $cat['idcategory']; ?>">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label for="value" class="block text-sm font-medium text-gray-700">Value</label>
                        <div class="relative mt-1 rounded-xl shadow-sm">
                            <span
                                class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 text-sm select-none">₱</span>
                            <input type="number" step="0.01" min="0" name="value" id="value"
                                class="block w-full rounded-xl border-gray-300 pl-7 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                placeholder="0.00">
                        </div>
                    </div>
                </div>

                <div class="space-y-2">
                    <label for="item_image" class="block text-sm font-medium text-gray-700">Item image</label>
                    <p class="text-xs text-gray-500">Square-ish images work best. Max 5MB. JPEG, PNG, GIF or WebP.</p>
                    <input type="file" name="item_image" id="item_image" accept="image/*"
                        class="mt-1 block w-full text-sm text-gray-900 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" />
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                    <a href="dashboard.php"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-xl border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-indigo-600 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fa-solid fa-check mr-2"></i>
                        Save item
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>

</html>

