<?php
session_start();
require_once './includes/connect_db.php';
require_once './includes/DashboardItemsQuery.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$items = [];
$categories = [];
$dbError = null;
$pageData = null;

$itemsPerPage = 20;

try {
    // Load categories for the add-item form.
    $catStmt = $pdo->prepare(
        "SELECT idcategory, category_name
         FROM category
         WHERE COALESCE(is_deleted, 0) = 0
           AND (iduser = :iduser OR iduser IS NULL OR iduser = 0)
         ORDER BY category_name ASC"
    );
    $catStmt->execute(['iduser' => (int) $_SESSION['user_id']]);
    $categories = $catStmt->fetchAll();

    $pageData = inv_fetch_dashboard_items($pdo, (int) $_SESSION['user_id'], $_GET, $itemsPerPage);
    $items = $pageData['items'];
} catch (Throwable $e) {
    $dbError = 'Unable to load inventory items right now.';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $isLocalhost = ($host === 'localhost' || strncmp($host, 'localhost:', 10) === 0 || $host === '127.0.0.1' || strncmp($host, '127.0.0.1:', 10) === 0);
    if ($isLocalhost) {
        $dbError .= ' Debug: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="referrer" content="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/css/output.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
</head>

<body class="min-h-screen flex flex-col relative font-sans text-slate-800 bg-slate-50">

    <div
        class="fixed inset-0 z-[-2] bg-gradient-to-br from-indigo-500/5 via-fuchsia-500/5 to-teal-500/5 pointer-events-none">
    </div>

    <div
        class="fixed inset-0 z-[-1] bg-[linear-gradient(to_right,#cbd5e1_1px,transparent_1px),linear-gradient(to_bottom,#cbd5e1_1px,transparent_1px)] bg-[size:32px_32px] opacity-40 pointer-events-none [mask-image:linear-gradient(to_bottom,black_40%,transparent_100%)]">
    </div>

    <div class="fixed top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-fuchsia-500 to-teal-400 z-[100]">
    </div> 
    
    <!-- Loader -->
    <div id="loader-container"
        class="fixed inset-0 z-50 hidden flex flex-col items-center justify-center bg-slate-900/40 backdrop-blur-sm">
        <?php include "./includes/partial/loader.php" ?>
    </div>

    <header class="bg-white/70 backdrop-blur-xl border-b border-white sticky top-0 z-40 shadow-sm shadow-slate-200/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="h-10 w-10 rounded-xl bg-indigo-600/90 backdrop-blur-sm flex items-center justify-center text-white shadow-md shadow-indigo-200 transition-transform hover:scale-105">
                    <i class="fa-solid fa-boxes-stacked"></i>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-1">
                    <h1 class="text-xl font-black text-indigo-700 tracking-tight drop-shadow-sm">Venda</h1>
                    <span class="text-lg font-medium text-slate-600 tracking-wide">Track</span>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-4">
                <a href="includes/export_pdf.php" target="_blank"
                    class="flex items-center gap-2 bg-white/80 backdrop-blur-md border border-white hover:bg-white hover:shadow-md text-slate-700 px-4 py-2 rounded-xl text-sm font-semibold transition-all shadow-sm">
                    <i class="fa-solid fa-download text-indigo-500"></i>
                    <span class="hidden sm:inline">Export</span>
                </a>

                <div class="relative ml-2">
                    <button id="profileTrigger"
                        class="flex items-center justify-center p-1 rounded-full hover:cursor-pointer hover:ring-4 hover:ring-indigo-500/10 transition-all focus:outline-none shadow-sm bg-white/50">
                        <img class="h-9 w-9 rounded-full object-cover border-2 border-white"
                            src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" alt="User">
                    </button>

                    <div id="googleMenu"
                        class="hidden absolute right-0 mt-3 w-80 bg-white/95 backdrop-blur-2xl rounded-3xl shadow-2xl border border-white z-50 overflow-hidden">
                        <div class="p-6 flex flex-col items-center text-center relative">
                            <div class="absolute inset-0 linear-gradient-to-br from-indigo-500/5 to-purple-500/5"></div>

                            <div class="relative mb-3 z-10">
                                <img class="h-24 w-24 rounded-full object-cover border-4 border-white shadow-lg"
                                    src="<?php echo htmlspecialchars($_SESSION['user_picture']); ?>" alt="User">
                            </div>

                            <h2 class="text-xl text-slate-900 font-bold z-10">Hi,
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>!
                            </h2>
                            <p class="text-sm font-medium text-slate-500 mb-4 z-10">
                                <?php echo htmlspecialchars($_SESSION['user_email']); ?>
                            </p>

                            <?php if (empty($_SESSION['google_id'])): ?>
                                <button id="openPassModal"
                                    class="mt-4 px-6 py-2 border hover:cursor-pointer border-slate-200 bg-white/80 rounded-full text-sm font-semibold text-slate-700 hover:text-indigo-600 hover:bg-white hover:border-indigo-200 hover:shadow-md transition-all z-10">
                                    Change password
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="bg-slate-50/80 p-2 border-t border-slate-100">
                            <button id="openHistorySidebar"
                                class="w-full hover:cursor-pointer flex items-center gap-3 px-4 py-3 text-sm font-bold text-slate-700 hover:bg-white hover:text-indigo-600 hover:shadow-sm rounded-2xl transition-all">
                                <i class="fa-solid fa-clock-rotate-left text-indigo-400"></i>
                                <span>Inventory History</span>
                            </button>

                            <a href="includes/logout.php"
                                class="flex items-center gap-3 px-4 py-3 text-sm font-bold text-red-600 hover:bg-red-50 hover:shadow-sm rounded-2xl transition-all mt-1">
                                <i class="fa-solid fa-right-from-bracket text-red-400"></i>
                                <span>Sign out</span>
                            </a>
                        </div>

                        <div class="p-3 text-center border-t border-slate-100 bg-white">
                            <button type="button" id="open-legal-modal"
                                class="text-[10px] font-bold text-slate-400 uppercase tracking-widest hover:text-indigo-600 transition-colors cursor-pointer">
                                About Us
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1 relative z-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <h2 class="text-3xl font-black text-slate-800 tracking-tight drop-shadow-sm">Your items</h2>
                    <p class="text-sm text-slate-500 font-medium mt-1">Counts reflect the latest entry in item history.
                    </p>
                </div>
                <div
                    class="text-sm font-bold text-slate-600 bg-white/80 backdrop-blur-md px-4 py-2 rounded-2xl border border-white shadow-sm inline-flex items-center gap-2">
                    <?php $totalRows = $pageData['total_rows'] ?? count($items); ?>
                    <span
                        class="h-6 w-6 rounded-md bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs">
                        <?php echo (int) $totalRows; ?>
                    </span>
                    Total Items
                </div>
            </div>

            <?php
            $q = $pageData['q'] ?? '';
            $selectedCategories = $pageData['categories'] ?? [];
            $selectedSort = $pageData['sort'] ?? 'name_asc';
            $perPage = $pageData['per_page'] ?? $itemsPerPage;
            ?>

            <form id="dashboard-filter-form" method="get" class="mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

                    <aside class="lg:col-span-4 xl:col-span-3">
                        <div
                            class="bg-white/70 backdrop-blur-xl border border-white rounded-xl p-5 shadow-xl shadow-slate-200/40">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-xs font-black text-slate-500 uppercase tracking-widest">Filters</h3>
                            </div>

                            <div class="mt-4">
                                <button id="category-filter-toggle" type="button"
                                    class="w-full flex items-center justify-between text-sm font-bold text-slate-700 hover:text-indigo-600 transition-colors cursor-pointer"
                                    aria-expanded="true" aria-controls="category-filter-panel">
                                    <span>Categories</span>
                                    <div id="category-filter-chevron"
                                        class="w-6 h-6 rounded-full flex items-center justify-center transition-transform duration-300">
                                        <i class="fa-solid fa-chevron-up text-xs text-slate-400"></i>
                                    </div>
                                </button>

                                <div id="category-filter-panel" class="mt-4 space-y-3">
                                    <div class="border-b border-slate-200/70 pb-3 mb-3">
                                        <button type="button" id="filter-category-add-toggle"
                                            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-white border border-slate-200 px-3 py-2 text-xs font-bold text-indigo-600 shadow-sm hover:shadow hover:-translate-y-0.5 transition-all">
                                            <i class="fa-solid fa-plus text-[10px]"></i> Add new category
                                        </button>

                                        <div id="filter-category-add-row" class="hidden mt-3 flex gap-2">
                                            <input type="text" id="filter-category-add-input" placeholder="Name..."
                                                class="flex-1 rounded-lg border-white bg-white shadow-inner px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 font-medium" />
                                            <button type="button" id="filter-category-add-btn"
                                                class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 shadow-md">
                                                Add
                                            </button>
                                        </div>
                                    </div>

                                    <?php foreach ($categories as $cat): ?>
                                        <?php
                                        $cid = (int) ($cat['idcategory'] ?? 0);
                                        $cname = (string) ($cat['category_name'] ?? '');
                                        $checked = in_array($cid, $selectedCategories, true);
                                        ?>
                                        <div class="filter-category-row flex items-center gap-2 group"
                                            data-id="<?php echo $cid; ?>"
                                            data-name="<?php echo htmlspecialchars($cname, ENT_QUOTES); ?>"
                                            data-confirming="0">
                                            <label
                                                class="flex items-center gap-3 text-sm font-medium text-slate-600 select-none cursor-pointer group-hover:text-slate-900 transition-colors flex-1">
                                                <div class="relative flex items-center justify-center">
                                                    <input type="checkbox" name="category[]" value="<?php echo $cid; ?>"
                                                        class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border-2 border-slate-300 bg-white/50 checked:border-indigo-600 checked:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 transition-all"
                                                        <?php echo $checked ? 'checked' : ''; ?>>
                                                    <i
                                                        class="fa-solid fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                                                </div>
                                                <span
                                                    class="wrap-text pt-0.5"><?php echo htmlspecialchars($cname); ?></span>
                                            </label>

                                            <div
                                                class="filter-category-actions flex items-center gap-1 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity">
                                                <button type="button"
                                                    class="filter-category-rename h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors"
                                                    title="Rename">
                                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                                </button>
                                                <button type="button"
                                                    class="filter-category-delete h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors"
                                                    title="Delete">
                                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <section class="lg:col-span-8 xl:col-span-9">
                        <div
                            class="bg-white/70 backdrop-blur-xl border border-white rounded-xl p-5 lg:p-6 shadow-xl shadow-slate-200/40 mb-6">
                            <div class="flex flex-col sm:flex-row items-center gap-4">
                                <div class="flex-1 relative w-full">
                                    <span
                                        class="absolute inset-y-0 left-0 pl-4 flex items-center text-slate-400 z-10 pointer-events-none">
                                        <i class="fa-solid fa-magnifying-glass"></i>
                                    </span>
                                    <input type="search" name="q" id="q" value="<?php echo htmlspecialchars($q); ?>"
                                        class="block w-full h-12 rounded-2xl border-white bg-white/60 backdrop-blur-md pl-12 pr-4 shadow-inner focus:outline-none focus:ring-0 focus:border-white focus:bg-white text-sm transition-all font-medium text-slate-800 placeholder-slate-400"
                                        placeholder="Search Inventory Items...">
                                </div>
                                <button type="submit"
                                    class="w-full sm:w-auto cursor-pointer inline-flex items-center justify-center h-12 px-8 rounded-2xl bg-indigo-600 text-sm font-bold cursor-ponter text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 hover:-translate-y-0.5 focus:outline-none transition-all">
                                    Search
                                </button>
                            </div>

                            <div
                                class="mt-4 pt-4 border-t border-slate-200/50 flex flex-wrap items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="text-[10px] uppercase tracking-widest font-black text-slate-500">
                                        Sort By
                                    </div>
                                    <div class="relative group">
                                        <select name="sort" id="sort"
                                            class="appearance-none h-10 rounded-xl border-white bg-white/60 backdrop-blur-sm text-sm font-bold text-slate-700 pl-4 pr-10 shadow-inner cursor-pointer transition-all hover:bg-white/90 hover:border-indigo-100 focus:outline-none focus:ring-0 focus:border-indigo-200 focus:bg-white">
                                            <option value="name_asc" <?php echo ($selectedSort === 'name_asc') ? ' selected' : ''; ?>>Name (A–Z)</option>
                                            <option value="name_desc" <?php echo ($selectedSort === 'name_desc') ? ' selected' : ''; ?>>Name (Z–A)</option>
                                            <option value="count_asc" <?php echo ($selectedSort === 'count_asc') ? ' selected' : ''; ?>>Stocks (Low–High)</option>
                                            <option value="count_desc" <?php echo ($selectedSort === 'count_desc') ? ' selected' : ''; ?>>Stocks (High–Low)</option>
                                        </select>
                                        <div
                                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400 group-hover:text-indigo-600 transition-colors">
                                            <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                        </div>
                                    </div>
                                </div>

                                <?php
                                $total = (int) ($pageData['total_rows'] ?? 0);
                                $page = (int) ($pageData['page'] ?? 1);
                                $pp = (int) ($pageData['per_page'] ?? $itemsPerPage);
                                $from = $total > 0 ? (($page - 1) * $pp + 1) : 0;
                                $to = min($total, ($page - 1) * $pp + count($items));
                                $hasFilters = ($q !== '' || !empty($selectedCategories) || $selectedSort !== 'name_asc');
                                ?>
                                <?php if ($hasFilters): ?>
                                    <div class="flex items-center gap-3 text-sm font-medium text-slate-500">
                                        <div class="hidden sm:block">
                                            Showing <span
                                                class="text-slate-800 font-bold"><?php echo (int) $from; ?>–<?php echo (int) $to; ?></span>
                                            of <span class="text-slate-800 font-bold"><?php echo (int) $total; ?></span>
                                        </div>
                                        <a href="dashboard.php"
                                            class="text-xs font-bold text-red-500 hover:text-red-700 bg-white/80 px-3 py-1.5 rounded-lg shadow-sm hover:shadow hover:bg-white transition-all border border-red-100 ml-2">
                                            Clear filters
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div>
                            <?php if ($dbError): ?>
                                <div
                                    class="bg-red-50/90 backdrop-blur-xl border border-red-200 text-red-700 rounded-[2rem] p-6 font-medium shadow-lg shadow-red-100/50">
                                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                    <?php echo htmlspecialchars($dbError); ?>
                                </div>
                            <?php elseif (empty($items)): ?>
                                <div
                                    class="bg-white/70 backdrop-blur-xl border border-white rounded-[2rem] p-12 text-center shadow-xl shadow-slate-200/40">
                                    <div
                                        class="mx-auto h-20 w-20 rounded-[1.5rem] bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-300 shadow-inner">
                                        <i class="fa-solid fa-box-open text-3xl"></i>
                                    </div>
                                    <h3 class="mt-6 text-xl font-black text-slate-800 tracking-tight">No items found</h3>
                                    <p class="mt-2 text-sm font-medium text-slate-500">Adjust your filters or add a new item
                                        to get started.</p>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-6">
                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $name = (string) ($item['item_name'] ?? '');
                                        $categoryName = (string) ($item['category_name'] ?? '');
                                        $count = (int) ($item['item_count'] ?? 0);
                                        $value = (float) ($item['item_value'] ?? 0.0);

                                        $thumbUrl = $item['image_thumb_path'] ?? null;
                                        $previewUrl = $item['image_preview_path'] ?? null;

                                        $imgSrc = $thumbUrl ?: null;
                                        $srcset = ($thumbUrl && $previewUrl) ? $thumbUrl . ' 300w, ' . $previewUrl . ' 800w' : null;

                                        $initial = mb_strtoupper(mb_substr(trim($name) !== '' ? trim($name) : 'I', 0, 1));
                                        ?>
                                        <div onclick="openEditModal({
                                                    id: '<?php echo $item['iditem']; ?>',
                                                    name: '<?php echo addslashes($name); ?>',
                                                    value: '<?php echo $item['value']; ?>',
                                                    count: '<?php echo $count; ?>',
                                                    categoryId: '<?php echo $item['idcategory'] ?? ''; ?>',
                                                    categoryName: '<?php echo addslashes($categoryName); ?>',
                                                    imageSrc: '<?php echo $imgSrc ? addslashes($imgSrc) : ''; ?>'
                                                })" role="button" tabindex="0"
                                            onkeydown="if(event.key === 'Enter') this.click();"
                                            class="bg-white/80 backdrop-blur-xl rounded-xl shadow-lg shadow-slate-200/30 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden border border-white group relative flex flex-col cursor-pointer focus:outline-none focus:ring-4 focus:ring-indigo-500/30 text-left">

                                            <div
                                                class="relative aspect-square w-full bg-slate-100/50 overflow-hidden flex items-center justify-center">
                                                <?php if ($imgSrc): ?>
                                                    <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                                        alt="<?php echo htmlspecialchars($name); ?>"
                                                        class="w-full h-full object-cover object-center transform group-hover:scale-105 transition-transform duration-700 ease-out"
                                                        loading="lazy" <?php if ($srcset): ?> srcset="
                                        <?php echo htmlspecialchars($srcset); ?>"
                                                            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 300px" <?php endif; ?> />
                                                <?php else: ?>
                                                    <div
                                                        class="w-full h-full flex items-center justify-center linear-gradient-to-br from-slate-100 to-slate-200/50 group-hover:scale-105 transition-transform duration-700 ease-out">
                                                        <div
                                                            class="h-20 w-20 rounded-[1.5rem] bg-white border border-slate-100 flex items-center justify-center text-slate-300 font-black text-4xl shadow-sm">
                                                            <?php echo htmlspecialchars($initial); ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <div
                                                    class="absolute inset-0 bg-indigo-900/0 group-hover:bg-indigo-900/5 transition-colors duration-300 z-10 pointer-events-none">
                                                </div>

                                                <div class="absolute top-4 right-4 z-20">
                                                    <div
                                                        class="inline-flex items-center gap-2 bg-white/95 backdrop-blur-md px-3.5 py-1.5 rounded-xl border border-slate-100 shadow-md">
                                                        <?php if ($count === 0): ?>
                                                            <i class="fa-solid fa-circle-xmark text-red-500 text-[10px]"></i>
                                                            <span
                                                                class="text-[10px] font-black text-red-600 uppercase tracking-widest">Out
                                                                of Stock</span>
                                                        <?php else: ?>
                                                            <span class="text-sm font-black text-slate-800">
                                                                <?php echo $count; ?>
                                                            </span>
                                                            <span
                                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Qty</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="p-6 relative flex-1 flex flex-col">
                                                <h3
                                                    class="text-lg font-bold text-slate-800 leading-tight line-clamp-2 group-hover:text-indigo-600 transition-colors">
                                                    <?php echo htmlspecialchars($name); ?>
                                                </h3>
                                                <div class="mt-auto pt-4">
                                                    <?php if ($categoryName !== ''): ?>
                                                        <p
                                                            class="text-[10px] font-bold tracking-widest uppercase text-indigo-500 truncate inline-flex items-center gap-1.5 bg-indigo-50/80 px-2.5 py-1.5 rounded-lg border border-indigo-100/50">
                                                            <i class="fa-solid fa-tag"></i>
                                                            <?php echo htmlspecialchars($categoryName); ?>
                                                        </p>
                                                    <?php else: ?>
                                                        <p
                                                            class="text-[10px] font-bold tracking-widest uppercase text-slate-400 inline-flex items-center gap-1.5 bg-slate-50 px-2.5 py-1.5 rounded-lg border border-slate-100">
                                                            <i class="fa-solid fa-tag"></i> Uncategorized
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($pageData && (int) ($pageData['total_pages'] ?? 1) > 1): ?>
                                    <?php
                                    $cur = (int) $pageData['page'];
                                    $totalPages = (int) $pageData['total_pages'];

                                    $baseParams = [
                                        'q' => $pageData['q'],
                                        'category' => !empty($pageData['categories']) ? implode(',', (array) $pageData['categories']) : null,
                                        'sort' => $pageData['sort'],
                                    ];

                                    $prev = $cur > 1 ? $cur - 1 : null;
                                    $next = $cur < $totalPages ? $cur + 1 : null;
                                    $window = 2;
                                    $start = max(1, $cur - $window);
                                    $end = min($totalPages, $cur + $window);
                                    ?>
                                    <div class="mt-12 flex flex-col items-center gap-4">
                                        <nav class="inline-flex flex-wrap items-center justify-center gap-2 rounded-[2rem] bg-white/70 backdrop-blur-xl border border-white px-3 py-3 shadow-xl shadow-slate-200/40"
                                            aria-label="Pagination">
                                            <a class="px-4 py-2 rounded-xl text-sm font-bold border transition-all <?php echo $prev ? 'bg-white text-slate-700 border-white shadow-sm hover:shadow-md hover:-translate-y-0.5' : 'bg-slate-100/50 text-slate-400 border-transparent pointer-events-none'; ?>"
                                                href="<?php echo $prev ? ('dashboard.php' . inv_build_query_string($baseParams + ['page' => $prev])) : '#'; ?>">
                                                <i class="fa-solid fa-chevron-left mr-2 text-xs"></i> Prev
                                            </a>

                                            <?php if ($start > 1): ?>
                                                <a class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold border bg-white text-slate-700 border-white shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5"
                                                    href="<?php echo 'dashboard.php' . inv_build_query_string($baseParams + ['page' => 1]); ?>">1</a>
                                                <?php if ($start > 2): ?>
                                                    <span class="w-8 text-center text-slate-400 font-bold">…</span>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php for ($p = $start; $p <= $end; $p++): ?>
                                                <a class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold border transition-all hover:-translate-y-0.5 <?php echo $p === $cur ? 'bg-indigo-600 text-white border-indigo-600 shadow-md shadow-indigo-200' : 'bg-white text-slate-700 border-white shadow-sm hover:shadow-md hover:text-indigo-600'; ?>"
                                                    href="<?php echo 'dashboard.php' . inv_build_query_string($baseParams + ['page' => $p]); ?>">
                                                    <?php echo (int) $p; ?>
                                                </a>
                                            <?php endfor; ?>

                                            <?php if ($end < $totalPages): ?>
                                                <?php if ($end < $totalPages - 1): ?>
                                                    <span class="w-8 text-center text-slate-400 font-bold">…</span>
                                                <?php endif; ?>
                                                <a class="w-10 h-10 flex items-center justify-center rounded-xl text-sm font-bold border bg-white text-slate-700 border-white shadow-sm hover:shadow-md transition-all hover:-translate-y-0.5 hover:text-indigo-600"
                                                    href="<?php echo 'dashboard.php' . inv_build_query_string($baseParams + ['page' => $totalPages]); ?>">
                                                    <?php echo $totalPages; ?>
                                                </a>
                                            <?php endif; ?>

                                            <a class="px-4 py-2 rounded-xl text-sm font-bold border transition-all <?php echo $next ? 'bg-white text-slate-700 border-white shadow-sm hover:shadow-md hover:-translate-y-0.5' : 'bg-slate-100/50 text-slate-400 border-transparent pointer-events-none'; ?>"
                                                href="<?php echo $next ? ('dashboard.php' . inv_build_query_string($baseParams + ['page' => $next])) : '#'; ?>">
                                                Next <i class="fa-solid fa-chevron-right ml-2 text-xs"></i>
                                            </a>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                    </section>
                </div>
            </form>
        </div>
    </main>

    <div id="passModal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div
            class="bg-white/95 backdrop-blur-xl w-full max-w-md rounded-[2.5rem] shadow-2xl overflow-hidden border border-white animate-in fade-in zoom-in duration-300">
            <div class="p-8 pb-4 flex justify-between items-start relative">
                <div
                    class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/5 rounded-full blur-2xl -translate-y-1/2 translate-x-1/2 pointer-events-none">
                </div>

                <div class="relative z-10">
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Security Update</h2>
                    <p class="text-sm font-medium text-slate-500 mt-2">Please enter your new credentials below.</p>
                </div>
                <button
                    class="closeModal relative z-10 h-10 w-10 flex items-center justify-center rounded-full bg-white shadow-sm border border-slate-100 hover:bg-slate-50 transition-all text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="changePassForm" class="p-8 pt-0 space-y-5 relative z-10">
                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Current
                        Password</label>
                    <div class="relative">
                        <input type="password" name="current_password" required
                            class="w-full px-5 py-3.5 rounded-2xl border-white bg-slate-50/80 shadow-inner focus:outline-none focus:ring-4 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all">
                        <button type="button"
                            class="toggle-pass absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-white shadow-sm z-10">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">New
                        Password</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password" required
                            class="w-full px-5 py-3.5 rounded-2xl border-white bg-slate-50/80 shadow-inner focus:outline-none focus:ring-4 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all">
                        <button type="button"
                            class="toggle-pass absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-white shadow-sm z-10">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Confirm New
                        Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" required
                            class="w-full px-5 py-3.5 rounded-2xl border-white bg-slate-50/80 shadow-inner focus:outline-none focus:ring-4 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all">
                        <button type="button"
                            class="toggle-pass absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-indigo-600 transition-colors w-8 h-8 flex items-center justify-center rounded-full hover:bg-white shadow-sm z-10">
                            <i class="fa-solid fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50/80 rounded-[1.5rem] p-5 grid grid-cols-2 gap-y-3 border border-white shadow-sm">
                    <div id="check-len"
                        class="flex items-center gap-2 text-[10px] text-slate-400 font-black tracking-widest transition-colors">
                        <i class="fa-solid fa-circle-check"></i> 8+ CHARS
                    </div>
                    <div id="check-upper"
                        class="flex items-center gap-2 text-[10px] text-slate-400 font-black tracking-widest transition-colors">
                        <i class="fa-solid fa-circle-check"></i> UPPERCASE
                    </div>
                    <div id="check-num"
                        class="flex items-center gap-2 text-[10px] text-slate-400 font-black tracking-widest transition-colors">
                        <i class="fa-solid fa-circle-check"></i> NUMBER
                    </div>
                    <div id="check-match"
                        class="flex items-center gap-2 text-[10px] text-slate-400 font-black tracking-widest transition-colors">
                        <i class="fa-solid fa-circle-check"></i> MATCHING
                    </div>
                </div>

                <div id="passMessage" class="hidden text-xs p-4 rounded-2xl font-bold text-center border"></div>

                <button type="submit" id="submitPass" disabled
                    class="w-full bg-indigo-600 hover:cursor-pointer disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none text-white py-4 rounded-2xl font-black tracking-wider shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all hover:-translate-y-0.5 active:scale-95 mt-4">
                    Update Password
                </button>
            </form>
        </div>
    </div>

    <div id="historySidebar"
        class="fixed inset-y-0 right-0 w-full sm:w-96 bg-gray-50 z-[70] shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out border-l border-gray-200 flex flex-col">
        <div class="p-4 pt-6 px-6 flex justify-between items-center">
            <h2 class="text-xl font-medium text-gray-800 tracking-tight">Item History</h2>
            <button id="closeSidebar"
                class="h-8 w-8 flex items-center justify-center rounded-full hover:bg-gray-200 transition text-gray-500">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="px-6 pb-4">
            <div class="relative group">
                <select id="historyItemFilter"
                    class="w-full pl-3 pr-8 py-2 rounded-md border border-gray-300 bg-white hover:bg-gray-50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 text-sm font-medium transition-all appearance-none cursor-pointer">
                    <option value="all">All items</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo (int) $item['iditem']; ?>">
                            <?php echo htmlspecialchars($item['item_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-600">
                    <i class="fa-solid fa-caret-down text-xs"></i>
                </div>
            </div>
        </div>

        <div id="historyContent" class="flex-1 overflow-y-auto px-6 relative">
            <div id="historySkeleton" class="space-y-6 hidden mt-2">
                <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="animate-pulse">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="h-3 w-3 bg-gray-200 rounded-full"></div>
                            <div class="h-4 w-24 bg-gray-200 rounded"></div>
                        </div>
                        <div class="pl-4 ml-1.5 border-l-2 border-gray-200 space-y-4">
                            <div class="pl-6">
                                <div class="h-4 w-32 bg-gray-200 rounded mb-2"></div>
                                <div class="h-3 w-48 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <div id="historyLogs" class="pb-6 hidden"></div>
        </div>

        <div id="historyFooter" class="p-4 border-t border-gray-200 hidden bg-white">
            <button id="loadMoreHistory"
                class="w-full py-2 text-sm font-medium text-gray-600 border border-gray-300 hover:bg-gray-50 rounded-md transition">
                Load More
            </button>
        </div>
    </div>

    <button type="button" id="open-add-item-modal"
        class="fixed bottom-8 right-8 inline-flex items-center justify-center h-16 w-16 rounded-[1.5rem] bg-indigo-600 text-white shadow-xl shadow-indigo-200 hover:bg-indigo-700 hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-indigo-500/30 transition-all z-40 group cursor-pointer"
        aria-label="Add item">
        <i class="fa-solid fa-plus text-2xl group-hover:rotate-90 transition-transform duration-300"></i>
    </button>

    <div id="add-item-modal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="relative w-full max-w-lg mx-auto">
            <div
                class="bg-white/95 backdrop-blur-xl rounded-[2rem] shadow-2xl border border-white overflow-visible animate-in fade-in zoom-in duration-200">

                <style>
                    /* Custom style to completely hide the browser's up/down arrows on the number input */
                    #item_count::-webkit-outer-spin-button,
                    #item_count::-webkit-inner-spin-button {
                        -webkit-appearance: none;
                        margin: 0;
                    }
                </style>

                <div
                    class="px-6 py-4 border-b border-slate-100 flex items-center justify-between relative overflow-hidden rounded-t-[2rem]">
                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-indigo-600/5 rounded-full blur-2xl -translate-y-1/2 translate-x-1/2 pointer-events-none">
                    </div>

                    <div class="relative z-10">
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">Add new item</h2>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">Upload an image and set basic details.</p>
                    </div>
                </div>

                <form id="add-item-form" action="includes/save_item.php" method="POST" enctype="multipart/form-data"
                    class="px-6 py-5 space-y-6">

                    <div class="flex items-start gap-5">
                        <div class="shrink-0 flex flex-col items-center">
                            <label for="item_image" class="cursor-pointer group relative block">
                                <div id="image-preview-container"
                                    class="w-40 h-40 rounded-[1.5rem] border-2 border-dashed border-slate-300 bg-slate-50/80 flex items-center justify-center overflow-hidden transition-all group-hover:bg-slate-100 group-hover:border-indigo-300 shadow-inner relative">
                                    <i id="image-preview-icon"
                                        class="fa-solid fa-camera text-slate-300 text-4xl group-hover:text-indigo-400 transition-colors"></i>
                                    <img id="image-preview"
                                        class="absolute inset-0 w-full h-full object-cover hidden z-10" alt="Preview">
                                    <div
                                        class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                                        <i class="fa-solid fa-pen text-white text-2xl"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="file" name="item_image" id="item_image"
                                accept="image/jpeg, image/png, image/webp" class="hidden" />
                            <p class="text-[10px] font-bold text-slate-400 mt-2">Max 5MB (JPEG/PNG)</p>
                        </div>

                        <div class="flex-1 flex flex-col space-y-3.5">
                            <div class="space-y-1">
                                <label for="item_name"
                                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Item
                                    name</label>
                                <input type="text" name="item_name" id="item_name" required
                                    class="block w-full rounded-xl border-white bg-slate-50/80 shadow-inner px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all"
                                    placeholder="e.g. Wireless Mouse">
                            </div>

                            <div class="space-y-1">
                                <label for="idcategory"
                                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Category</label>
                                <div id="category-dd" class="relative">
                                    <input type="hidden" name="idcategory" id="idcategory" value="">

                                    <button type="button" id="category-dd-trigger"
                                        class="w-full inline-flex items-center justify-between rounded-xl border border-white bg-slate-50/80 shadow-inner px-4 py-2.5 text-sm font-medium hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
                                        <span id="category-dd-label" class="truncate text-slate-500">Select</span>
                                        <i
                                            class="fa-solid fa-chevron-down text-[10px] text-slate-400 bg-transparent"></i>
                                    </button>

                                    <div id="category-dd-menu"
                                        class="hidden absolute left-0 mt-2 min-w-full sm:min-w-[16rem] max-w-[calc(100vw-4rem)] rounded-2xl border border-white bg-white/95 backdrop-blur-xl shadow-2xl z-[80] overflow-hidden">
                                        <ul id="category-dd-options" class="max-h-48 overflow-y-auto p-2 space-y-1">
                                            <?php foreach ($categories as $cat): ?>
                                                <li class="category-opt group flex items-center justify-between gap-2 rounded-lg px-3 py-2 hover:bg-slate-50 cursor-pointer transition-colors"
                                                    data-id="<?php echo (int) $cat['idcategory']; ?>"
                                                    data-name="<?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES); ?>">
                                                    <button type="button"
                                                        class="category-select flex-1 text-left text-xs font-bold text-slate-700 whitespace-normal break-words leading-snug">
                                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label for="value"
                                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Value</label>
                                <div class="relative rounded-xl shadow-inner bg-slate-50/80 border border-white">
                                    <span
                                        class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm font-bold select-none z-10 pointer-events-none">₱</span>
                                    <input type="number" step="0.01" min="0" name="value" id="value"
                                        class="block w-full rounded-xl border-transparent bg-transparent pl-7 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all"
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col items-center justify-center bg-slate-50/80 py-4 rounded-2xl border border-white shadow-inner">
                        <label
                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2.5">Quantity</label>
                        <div class="flex items-center justify-center gap-6">
                            <button type="button" id="qty-btn-minus"
                                class="text-indigo-400 hover:text-indigo-600 hover:bg-white w-10 h-10 rounded-full flex items-center justify-center transition-all outline-none shadow-sm border border-slate-100 bg-slate-50">
                                <i class="fa-solid fa-minus"></i>
                            </button>

                            <div
                                class="min-w-[4.5rem] min-h-[4.5rem] px-2 bg-indigo-500 rounded-[1.25rem] flex items-center justify-center shadow-lg shadow-indigo-200 transition-all duration-200 ease-out">
                                <input type="number" min="0" name="item_count" id="item_count" value="0"
                                    class="bg-transparent border-none text-center text-4xl font-black text-white focus:outline-none focus:ring-0 p-0 m-0 transition-all duration-200"
                                    style="-moz-appearance: textfield; appearance: none; width: 1ch;">
                            </div>

                            <button type="button" id="qty-btn-plus"
                                class="text-indigo-400 hover:text-indigo-600 hover:bg-white w-10 h-10 rounded-full flex items-center justify-center transition-all outline-none shadow-sm border border-slate-100 bg-slate-50">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" id="cancel-add-item-modal"
                            class="px-5 py-2.5 rounded-xl bg-white border border-slate-200 shadow-sm text-sm font-bold cursor-pointer text-slate-600 hover:bg-slate-50 transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-indigo-600 text-sm font-black tracking-wide text-white shadow-md shadow-indigo-200 hover:bg-indigo-700 focus:outline-none cursor-pointer focus:ring-2 focus:ring-indigo-500/30 transition-all hover:-translate-y-0.5">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->

    <div id="edit-item-modal"
        class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="relative w-full max-w-lg mx-auto">
            <div
                class="bg-white/95 backdrop-blur-xl rounded-[2rem] shadow-2xl border border-white overflow-visible animate-in fade-in zoom-in duration-200">

                <style>
                    #edit_item_count::-webkit-outer-spin-button,
                    #edit_item_count::-webkit-inner-spin-button {
                        -webkit-appearance: none;
                        margin: 0;
                    }
                </style>

                <div
                    class="px-6 py-4 border-b border-slate-100 flex items-center justify-between relative overflow-hidden rounded-t-[2rem]">
                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl -translate-y-1/2 translate-x-1/2 pointer-events-none">
                    </div>

                    <div class="relative z-10">
                        <h2 class="text-xl font-black text-slate-900 tracking-tight">Edit item</h2>
                        <p class="text-xs font-medium text-slate-500 mt-0.5">Update item details and stock.</p>
                    </div>

                    <button type="button" id="delete-item-btn"
                        class="relative z-10 h-8 w-8 inline-flex items-center justify-center rounded-full cursor-pointer bg-white shadow-sm border border-red-100 hover:bg-red-50 transition-all text-red-400 hover:text-red-600"
                        title="Delete Item">
                        <i class="fa-solid fa-trash-can text-sm"></i>
                    </button>
                </div>

                <form id="edit-item-form" action="includes/update_item.php" method="POST" enctype="multipart/form-data"
                    class="px-6 py-5 space-y-6">

                    <input type="hidden" name="iditem" id="edit_iditem" value="">

                    <div class="flex items-start gap-5">
                        <div class="shrink-0 flex flex-col items-center">
                            <label for="edit_item_image" class="cursor-pointer group relative block">
                                <div id="edit-image-preview-container"
                                    class="w-40 h-40 rounded-[1.5rem] border-2 border-solid border-indigo-200 bg-slate-50/80 flex items-center justify-center overflow-hidden transition-all group-hover:bg-slate-100 group-hover:border-indigo-300 shadow-inner relative">
                                    <i id="edit-image-preview-icon"
                                        class="fa-solid fa-camera text-slate-300 text-4xl hidden group-hover:text-indigo-400 transition-colors"></i>
                                    <img id="edit-image-preview"
                                        class="absolute inset-0 w-full h-full object-cover z-10" alt="Preview">
                                    <div
                                        class="absolute inset-0 bg-slate-900/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-20">
                                        <i class="fa-solid fa-pen text-white text-2xl"></i>
                                    </div>
                                </div>
                            </label>
                            <input type="file" name="item_image" id="edit_item_image"
                                accept="image/jpeg, image/png, image/webp" class="hidden" />
                            <p class="text-[10px] font-bold text-slate-400 mt-2">Max 5MB (JPEG/PNG)</p>
                        </div>

                        <div class="flex-1 flex flex-col space-y-3.5">
                            <div class="space-y-1">
                                <label for="edit_item_name"
                                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Item
                                    name</label>
                                <input type="text" name="item_name" id="edit_item_name" required
                                    class="block w-full rounded-xl border-white bg-slate-50/80 shadow-inner px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all"
                                    placeholder="e.g. Wireless Mouse">
                            </div>

                            <div class="space-y-1">
                                <label for="edit_idcategory"
                                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Category</label>
                                <div id="edit-category-dd" class="relative">
                                    <input type="hidden" name="idcategory" id="edit_idcategory" value="">

                                    <button type="button" id="edit-category-dd-trigger"
                                        class="w-full inline-flex items-center justify-between rounded-xl border border-white bg-slate-50/80 shadow-inner px-4 py-2.5 text-sm font-medium hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all cursor-pointer">
                                        <span id="edit-category-dd-label"
                                            class="truncate text-slate-900 font-bold">Select</span>
                                        <i
                                            class="fa-solid fa-chevron-down text-[10px] text-slate-400 bg-transparent"></i>
                                    </button>

                                    <div id="edit-category-dd-menu"
                                        class="hidden absolute left-0 mt-2 min-w-full sm:min-w-[16rem] max-w-[calc(100vw-4rem)] rounded-2xl border border-white bg-white/95 backdrop-blur-xl shadow-2xl z-[80] overflow-hidden">
                                        <div class="p-3 border-b border-slate-100 bg-slate-50/80">
                                            <button type="button" id="edit-category-dd-add-toggle"
                                                class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-white border border-slate-200 px-3 py-2 text-xs font-bold text-indigo-600 shadow-sm hover:shadow hover:-translate-y-0.5 transition-all">
                                                <i class="fa-solid fa-plus text-[10px]"></i> Add new category
                                            </button>

                                            <div id="edit-category-dd-add-row" class="hidden mt-3 flex gap-2">
                                                <input type="text" id="edit-category-dd-add-input" placeholder="Name..."
                                                    class="flex-1 rounded-lg border-white bg-white shadow-inner px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500/20 font-medium" />
                                                <button type="button" id="edit-category-dd-add-btn"
                                                    class="inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700 shadow-md">
                                                    Add
                                                </button>
                                            </div>
                                        </div>

                                        <ul id="edit-category-dd-options"
                                            class="max-h-48 overflow-y-auto p-2 space-y-1">
                                            <?php foreach ($categories as $cat): ?>
                                                <li class="edit-category-opt group flex items-center justify-between gap-2 rounded-lg px-3 py-2 hover:bg-slate-50 cursor-pointer transition-colors"
                                                    data-id="<?php echo (int) $cat['idcategory']; ?>"
                                                    data-name="<?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES); ?>">
                                                    <button type="button"
                                                        class="edit-category-select flex-1 text-left text-xs font-bold text-slate-700 whitespace-normal break-words leading-snug cursor-pointer">
                                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                                    </button>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label for="edit_value"
                                    class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Value</label>
                                <div class="relative rounded-xl shadow-inner bg-slate-50/80 border border-white">
                                    <span
                                        class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 text-sm font-bold select-none z-10 pointer-events-none">₱</span>
                                    <input type="number" step="0.01" min="0" name="value" id="edit_value"
                                        class="block w-full rounded-xl border-transparent bg-transparent pl-7 pr-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:bg-white text-sm font-medium transition-all"
                                        placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="relative flex flex-col items-center justify-center bg-slate-50/80 pt-10 pb-5 rounded-2xl border border-white shadow-inner">

                        <div class="absolute top-3 left-3 inline-flex items-center gap-1.5 bg-teal-50 px-2 py-1 rounded-lg shadow-sm border border-teal-100/50"
                            title="Original Stock">
                            <i class="fa-solid fa-cubes text-teal-500 text-[11px]"></i>
                            <span class="text-[9px] font-black uppercase tracking-widest text-teal-700">
                                Current Stock: <span id="edit_original_count" class="text-teal-900 text-[11px]">0</span>
                            </span>
                        </div>

                        <div id="edit_qty_delta_badge"
                            class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-1 rounded-lg shadow-sm opacity-0 transition-all duration-300 border border-transparent">
                            <i id="edit_qty_delta_icon" class="fa-solid text-[10px]"></i>
                            <span id="edit_qty_delta_text" class="text-[10px] font-black tracking-widest">0</span>
                        </div>

                        <label
                            class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Quantity</label>
                        <div class="flex items-center justify-center gap-6">
                            <button type="button" id="edit-qty-btn-minus"
                                class="text-indigo-400 hover:text-indigo-600 hover:bg-white w-10 h-10 rounded-full flex items-center justify-center transition-all outline-none shadow-sm border border-slate-100 bg-slate-50 cursor-pointer">
                                <i class="fa-solid fa-minus"></i>
                            </button>

                            <div
                                class="min-w-[4.5rem] min-h-[4.5rem] px-2 bg-indigo-500 rounded-[1.25rem] flex items-center justify-center shadow-lg shadow-indigo-200 transition-all duration-200 ease-out">
                                <input type="number" min="0" name="item_count" id="edit_item_count" value="0"
                                    class="bg-transparent border-none text-center text-4xl font-black text-white focus:outline-none focus:ring-0 p-0 m-0 transition-all duration-200"
                                    style="-moz-appearance: textfield; appearance: none; width: 1ch;">
                            </div>

                            <button type="button" id="edit-qty-btn-plus"
                                class="text-indigo-400 hover:text-indigo-600 hover:bg-white w-10 h-10 rounded-full flex items-center justify-center transition-all outline-none shadow-sm border border-slate-100 bg-slate-50 cursor-pointer">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">

                        <button type="button" id="cancel-edit-item-modal"
                            class="text-center font-bold tracking-wide w-24 py-2.5 rounded-xl text-sm text-slate-500 bg-white border border-slate-200 shadow-sm hover:bg-slate-50 transition-all focus:outline-none cursor-pointer">
                            Cancel
                        </button>

                        <button type="submit"
                            class="text-center w-28 py-2.5 rounded-xl bg-indigo-600 text-sm font-black tracking-wide text-white shadow-md shadow-indigo-200 hover:bg-indigo-700 focus:outline-none cursor-pointer focus:ring-2 focus:ring-indigo-500/30 transition-all hover:-translate-y-0.5">
                            Save
                        </button>

                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- DELETE MODAL -->
    <div id="delete-confirm-modal"
        class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="relative w-full max-w-sm mx-auto">
            <div
                class="bg-white/95 backdrop-blur-xl rounded-[2rem] shadow-2xl border border-white overflow-hidden animate-in fade-in zoom-in duration-200">
                <div class="p-6 text-center sm:p-8">

                    <div
                        class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-50 border border-red-100 mb-6 shadow-inner">
                        <i class="fa-solid fa-triangle-exclamation text-2xl text-red-500"></i>
                    </div>

                    <h2 class="text-xl font-black text-slate-900 tracking-tight mb-2">Delete Item?</h2>
                    <p class="text-sm font-medium text-slate-500 mb-8 leading-relaxed">
                        Are you sure you want to permanently delete this item? This action cannot be undone.
                    </p>

                    <div class="flex items-center justify-center gap-3 w-full">
                        <button type="button" id="cancel-delete-btn"
                            class="flex-1 px-4 py-3 rounded-xl bg-white border border-slate-200 shadow-sm text-sm font-bold cursor-pointer text-slate-600 hover:bg-slate-50 transition-all">
                            Cancel
                        </button>
                        <button type="button" id="confirm-delete-btn"
                            class="flex-1 px-4 py-3 rounded-xl bg-red-600 text-sm font-black tracking-wide text-white shadow-md shadow-red-200 hover:bg-red-700 focus:outline-none cursor-pointer focus:ring-2 focus:ring-red-500/30 transition-all hover:-translate-y-0.5">
                            Confirm
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed top-6 right-6 z-80 space-y-3 pointer-events-none"></div>

    <div id="legal-modal"
        class="fixed inset-0 z-[75] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 transition-all">
        <div class="relative w-full max-w-lg mx-auto">
            <div
                class="bg-white/95 backdrop-blur-xl rounded-[2rem] shadow-2xl border border-white overflow-hidden animate-in fade-in zoom-in duration-200">

                <div
                    class="px-6 pt-6 pb-3 border-b border-slate-100 flex items-center justify-between relative overflow-hidden rounded-t-[2rem]">

                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl -translate-y-1/2 translate-x-1/2 pointer-events-none">
                    </div>

                    <div class="relative z-10 flex items-center gap-3.5">
                        <div
                            class="h-10 w-10 rounded-full bg-indigo-50 border border-indigo-100 flex items-center justify-center shadow-inner">
                            <i class="fa-solid fa-circle-info text-indigo-500 text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-slate-900 tracking-tight">About Us</h3>
                            <p class="text-xs font-medium text-slate-500 mt-0.5">VendaTrack</p>
                        </div>
                    </div>

                    <button type="button" id="close-legal-modal"
                        class="relative z-10 h-8 w-8 inline-flex items-center justify-center rounded-full cursor-pointer bg-white shadow-sm border border-slate-100 hover:bg-slate-50 transition-all text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <div class="px-6 py-3 space-y-2 max-h-[70vh] overflow-y-auto">

                    <div class="space-y-2 p-4 rounded-2xl bg-slate-50/80 border border-white shadow-inner">
                        <h4
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                            <i class="fa-solid fa-layer-group text-indigo-400"></i> Overview
                        </h4>
                        <p class="text-sm font-medium text-slate-700 leading-relaxed">
                            This inventory system helps you organize items, categories, and stock history in one place
                            with a seamless, modern interface.
                        </p>
                    </div>

                    <div class="space-y-2 p-4 rounded-2xl bg-slate-50/80 border border-white shadow-inner">
                        <h4
                            class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                            <i class="fa-solid fa-envelope text-teal-400"></i> Contact
                        </h4>
                        <p class="text-sm font-medium text-slate-700 leading-relaxed">
                            For questions, bug reports, or support, please contact your system administrator.
                        </p>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <footer class="border-t border-slate-200/60 bg-white/40 backdrop-blur-md relative z-10 mt-auto">
        <div
            class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between text-xs font-bold uppercase tracking-widest text-slate-400">
            <div>&copy; <?php echo date('Y'); ?> Inventory</div>
            <div class="hidden sm:block">Signed in as <span
                    class="text-indigo-500"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span></div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function () {

            // --- GLOBAL VARIABLES & TOAST ---
            const categoryCrudUrl = 'includes/category_crud.php';
            const $toastContainer = $('#toast-container');
            const $loaderContainer = $('#loader-container');
            const $loaderText = $('#loader-description');
            const $profileBtn = $('#profileTrigger');
            const $profileMenu = $('#googleMenu');
            const $addItemModal = $('#add-item-modal');
            const $filterForm = $('#dashboard-filter-form');
            const $catToggle = $('#category-filter-toggle');
            const $catPanel = $('#category-filter-panel');
            const $catChevron = $('#category-filter-chevron');
            const $addQtyInput = $('#item_count');
            const $passModal = $('#passModal');
            const $sidebar = $('#historySidebar');
            const $legalModal = $('#legal-modal');

            let hOffset = 0;
            const hLimit = 24;

            // --- Legal modal ---
            function openLegalModal() {
                if (!$legalModal.length) return;
                $legalModal.removeClass('hidden').addClass('flex');
            }
            function closeLegalModal() {
                if (!$legalModal.length) return;
                $legalModal.addClass('hidden').removeClass('flex');
            }
            $('#open-legal-modal').on('click', openLegalModal);
            $('#close-legal-modal, #legal-modal-ok').on('click', closeLegalModal);
            $(document).on('click', function (e) {
                if ($legalModal.length && $(e.target).is($legalModal)) closeLegalModal();
            });

            // Replace your existing .toggle-day-details listener with this:
            $(document).on('click', '.toggle-day-details', function () {
                const targetId = $(this).data('target');
                const $details = $('#' + targetId);
                const $chevron = $(this).find('.fa-chevron-right');

                // Toggle the visibility of the logs
                $details.toggleClass('hidden');

                // Check state and rotate: Right when closed, Down when open
                if ($details.hasClass('hidden')) {
                    $chevron.removeClass('rotate-90'); // Points Right
                } else {
                    $chevron.addClass('rotate-90');    // Points Down
                }
            });

            function fetchHistory(append = false) {
                const itemId = $('#historyItemFilter').val(); // Get current selected item ID
                const $logs = $('#historyLogs');
                const $skeleton = $('#historySkeleton');
                const $footer = $('#historyFooter');

                // If it's a new filter selection, reset the UI
                if (!append) {
                    hOffset = 0;
                    $logs.addClass('hidden').empty();
                    $skeleton.removeClass('hidden');
                    $footer.addClass('hidden');
                }

                $.get('includes/fetch_history.php', {
                    offset: hOffset,
                    limit: hLimit,
                    iditem: itemId // This ensures only items of that name are fetched
                }, function (data) {
                    // Subtle delay for the skeleton pulse effect
                    setTimeout(() => {
                        $skeleton.addClass('hidden');
                        $logs.removeClass('hidden');

                        const trimmedData = data.trim();

                        if (trimmedData === '') {
                            if (!append) {
                                $logs.html('<div class="text-center py-10 text-gray-400 text-xs">No activity for this item.</div>');
                            }
                            $footer.addClass('hidden');
                        } else {
                            $logs.append(trimmedData);

                            // Logic to show/hide Load More based on returned items
                            // We count the number of new entries appended
                            const newItemsCount = $(trimmedData).filter('.history-entry').length;
                            if (newItemsCount >= hLimit) {
                                $footer.removeClass('hidden');
                            } else {
                                $footer.addClass('hidden');
                            }
                        }
                    }, 150);
                });
            }

            // 1. Re-query on Dropdown Change (Resets everything)
            $('#historyItemFilter').on('change', function () {
                fetchHistory(false);
            });

            // 2. Load More (Append only)
            $('#loadMoreHistory').on('click', function () {
                hOffset += hLimit;
                fetchHistory(true);
            });

            // --- Profile Dropdown Logic ---
            $profileBtn.on('click', function (e) {
                e.stopPropagation();
                $profileMenu.toggleClass('hidden');
            });

            function showToast(type, message) {
                const isError = type === 'error';
                const base = 'pointer-events-auto w-80 max-w-[90vw] rounded-2xl border shadow-xl px-5 py-4 backdrop-blur-md';
                const colors = isError ? 'bg-red-50/90 border-red-200 text-red-800' : 'bg-emerald-50/90 border-emerald-200 text-emerald-800';
                const icon = isError ? 'fa-circle-exclamation' : 'fa-circle-check';

                const $toast = $('<div></div>').addClass(`${base} ${colors}`).hide();
                const $wrap = $('<div class="flex items-center"><div class="flex items-start gap-3"><i class="fa-solid ' + icon + ' mt-0.5 text-lg"></i><div class="text-sm font-bold leading-snug">' + message + '</div></div></div>');
                const $close = $('<button type="button" class="ml-auto text-inherit/50 hover:text-inherit hover:bg-black/5 p-1 rounded-md transition-all"><i class="fa-solid fa-xmark"></i></button>');

                $close.on('click', () => $toast.stop(true, true).fadeOut(150, function () { $(this).remove(); }));
                $toast.append($wrap.append($close));
                $toastContainer.append($toast);
                $toast.fadeIn(150);

                setTimeout(() => $toast.fadeOut(250, function () { $(this).remove(); }), 3000);
            }


            // --- PASSWORD UPDATE ---
            $(document).on('click', '.toggle-pass', function () {
                const $input = $(this).siblings('input');
                const isPass = $input.attr('type') === 'password';
                $input.attr('type', isPass ? 'text' : 'password');
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            $('#new_password, #confirm_password').on('input', function () {
                const pass = $('#new_password').val(), conf = $('#confirm_password').val();
                const r = { len: pass.length >= 8, up: /[A-Z]/.test(pass), num: /[0-9]/.test(pass), match: (pass === conf && pass.length > 0) };
                const update = (sel, ok) => $(sel).toggleClass('text-emerald-500', ok).toggleClass('text-slate-400', !ok);

                update('#check-len', r.len); update('#check-upper', r.up); update('#check-num', r.num); update('#check-match', r.match);
                $('#submitPass').prop('disabled', !(r.len && r.up && r.num && r.match));
            });

            $('#changePassForm').on('submit', function (e) {
                e.preventDefault();
                const $msg = $('#passMessage');
                $.post('includes/update_password.php', $(this).serialize(), function (res) {
                    $msg.removeClass('hidden bg-red-50 text-red-600 bg-emerald-50 text-emerald-600 border-red-200 border-emerald-200');
                    if (res.status === 'success') {
                        $msg.addClass('bg-emerald-50 text-emerald-600 border-emerald-200').text(res.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        $msg.addClass('bg-red-50 text-red-600 border-red-200').text(res.message);
                    }
                }, 'json');
            });

            // --- SHARED MODAL HELPERS ---
            function handleImagePreview(file, $img, $icon, $container) {
                if (file) {
                    // A file was selected, check if it's an image
                    if (file.type.match('image.*')) {
                        const reader = new FileReader();
                        reader.onload = e => {
                            $img.attr('src', e.target.result).removeClass('hidden');
                            $icon.addClass('hidden');
                            $container.removeClass('border-dashed border-slate-300').addClass('border-solid border-indigo-200 shadow-sm');
                        }
                        reader.readAsDataURL(file);
                    } else {
                        // It's a file, but NOT an image
                        showToast('error', 'Please select a valid image file.');
                        $img.addClass('hidden').attr('src', '');
                        $icon.removeClass('hidden');
                        $container.addClass('border-dashed border-slate-300').removeClass('border-solid border-indigo-200 shadow-sm');
                    }
                } else {
                    // No file (null). Just quietly reset the UI back to default without an error.
                    $img.addClass('hidden').attr('src', '');
                    $icon.removeClass('hidden');
                    $container.addClass('border-dashed border-slate-300').removeClass('border-solid border-indigo-200 shadow-sm');
                }
            }

            function handleQtyCounter($input, action) {
                let val = parseInt($input.val()) || 0;
                if (action === 'plus') $input.val(val + 1);
                if (action === 'minus' && val > 0) $input.val(val - 1);
                if ($input.val() < 0) $input.val(0);
                $input.css('width', Math.max(1, $input.val().length) + 'ch');
            }

            const MAX_IMAGE_BYTES = 5_000_000;

            function validateImageFileOrReset(file, $fileInput, $img, $icon, $container) {
                if (!file) {
                    handleImagePreview(null, $img, $icon, $container);
                    return false;
                }
                if (file.size > MAX_IMAGE_BYTES) {
                    showToast('error', 'Image too large. Max 5MB.');
                    $fileInput.val('');
                    handleImagePreview(null, $img, $icon, $container);
                    return false;
                }
                if (!file.type || !file.type.match('image.*')) {
                    showToast('error', 'Please select a valid image file.');
                    $fileInput.val('');
                    handleImagePreview(null, $img, $icon, $container);
                    return false;
                }
                return true;
            }

            // --- ADD ITEM MODAL ---


            function hideAddModal() {
                $addItemModal.addClass('hidden').removeClass('flex');
                $('#add-item-form')[0].reset();
                handleImagePreview(null, $('#image-preview'), $('#image-preview-icon'), $('#image-preview-container'));
                setSelectedCategory('', '');
                $addQtyInput.val(0).css('width', '1ch');
            }

            $('#open-add-item-modal').on('click', () => $addItemModal.removeClass('hidden').addClass('flex'));
            $('#close-add-item-modal, #cancel-add-item-modal').on('click', hideAddModal);
            $('#qty-btn-plus').on('click', () => handleQtyCounter($addQtyInput, 'plus'));
            $('#qty-btn-minus').on('click', () => handleQtyCounter($addQtyInput, 'minus'));
            $addQtyInput.on('input', () => handleQtyCounter($addQtyInput, 'input'));
            $('#item_image').on('change', function () {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const ok = validateImageFileOrReset(
                    file,
                    $(this),
                    $('#image-preview'),
                    $('#image-preview-icon'),
                    $('#image-preview-container')
                );
                if (ok) {
                    handleImagePreview(file, $('#image-preview'), $('#image-preview-icon'), $('#image-preview-container'));
                }
            });

            $('#add-item-form').on('submit', function (e) {
                e.preventDefault();
                if (!String($('#idcategory').val() || '').trim()) {
                    showToast('error', 'Please select a category.');
                    $('#category-dd-menu').removeClass('hidden');
                    return;
                }
                // Capture the form payload BEFORE resetting the form.
                const formData = new FormData(this);
                // Close + reset immediately (even before request finishes)
                hideAddModal();
                if ($loaderText.length) $loaderText.text('SAVING...');
                $loaderContainer.removeClass('hidden').addClass('flex');

                $.ajax({
                    url: $(this).attr('action'), type: 'POST', data: formData, contentType: false, processData: false, dataType: 'json',
                    success: (res) => {
                        if (res && res.success) {
                            window.location.reload();
                            return;
                        }
                        $loaderContainer.removeClass('flex').addClass('hidden');
                        showToast('error', (res && res.message) ? res.message : 'Failed to save item.');
                    },
                    error: () => {
                        $loaderContainer.removeClass('flex').addClass('hidden');
                        showToast('error', 'A server error occurred while saving.');
                    }
                });
            });

            // --- EDIT ITEM MODAL ---
            const $editItemModal = $('#edit-item-modal');
            const $editQtyInput = $('#edit_item_count');

            function resetEditDeltaBadge() {
                const $badge = $('#edit_qty_delta_badge');
                const $icon = $('#edit_qty_delta_icon');
                const $text = $('#edit_qty_delta_text');
                $badge.removeClass('bg-emerald-50 text-emerald-600 border-emerald-200 bg-rose-50 text-rose-600 border-rose-200').addClass('opacity-0');
                $icon.removeClass('fa-arrow-up fa-arrow-down');
                $text.text('0');
            }

            function hideEditModal() {
                $editItemModal.addClass('hidden').removeClass('flex');
                $('#edit-item-form')[0].reset();
                handleImagePreview(null, $('#edit-image-preview'), $('#edit-image-preview-icon'), $('#edit-image-preview-container'));
                setEditSelectedCategory('', 'Select');
                resetEditDeltaBadge();
            }

            $('#close-edit-item-modal, #cancel-edit-item-modal').on('click', hideEditModal);

            // Delta Calculator Function
            function calculateDelta() {
                const original = parseInt($('#edit_original_count').text()) || 0;
                const current = parseInt($editQtyInput.val()) || 0;
                const diff = current - original;

                const $badge = $('#edit_qty_delta_badge');
                const $icon = $('#edit_qty_delta_icon');
                const $text = $('#edit_qty_delta_text');

                // Reset base classes
                $badge.removeClass('bg-emerald-50 text-emerald-600 border-emerald-200 bg-rose-50 text-rose-600 border-rose-200');
                $icon.removeClass('fa-arrow-up fa-arrow-down');

                if (diff === 0) {
                    // No change, hide the badge smoothly
                    $badge.addClass('opacity-0');
                } else if (diff > 0) {
                    // Increased Stock (Green)
                    $badge.removeClass('opacity-0').addClass('bg-emerald-50 text-emerald-600 border-emerald-200');
                    $icon.addClass('fa-arrow-up');
                    $text.text('+' + diff);
                } else {
                    // Decreased Stock (Red)
                    $badge.removeClass('opacity-0').addClass('bg-rose-50 text-rose-600 border-rose-200');
                    $icon.addClass('fa-arrow-down');
                    $text.text(diff); // diff already contains the negative sign (e.g., "-5")
                }
            }

            // Attach delta calculator to the buttons
            $('#edit-qty-btn-plus').on('click', () => { handleQtyCounter($editQtyInput, 'plus'); calculateDelta(); });
            $('#edit-qty-btn-minus').on('click', () => { handleQtyCounter($editQtyInput, 'minus'); calculateDelta(); });
            $editQtyInput.on('input', () => { handleQtyCounter($editQtyInput, 'input'); calculateDelta(); });

            $('#edit_item_image').on('change', function () {
                const file = this.files && this.files[0] ? this.files[0] : null;
                const ok = validateImageFileOrReset(
                    file,
                    $(this),
                    $('#edit-image-preview'),
                    $('#edit-image-preview-icon'),
                    $('#edit-image-preview-container')
                );
                if (ok) {
                    handleImagePreview(file, $('#edit-image-preview'), $('#edit-image-preview-icon'), $('#edit-image-preview-container'));
                }
            });

            window.openEditModal = function (itemData) {
                $('#edit_original_count').text(itemData.count);
                $('#edit_iditem').val(itemData.id);
                $('#edit_item_name').val(itemData.name);
                $('#edit_value').val(itemData.value);
                $editQtyInput.val(itemData.count).css('width', Math.max(1, String(itemData.count).length) + 'ch');
                setEditSelectedCategory(itemData.categoryId, itemData.categoryName);
                resetEditDeltaBadge();

                if (itemData.imageSrc) {
                    $('#edit-image-preview').attr('src', itemData.imageSrc).removeClass('hidden');
                    $('#edit-image-preview-icon').addClass('hidden');
                    $('#edit-image-preview-container').removeClass('border-dashed border-slate-300').addClass('border-solid border-indigo-200 shadow-sm');
                } else {
                    handleImagePreview(null, $('#edit-image-preview'), $('#edit-image-preview-icon'), $('#edit-image-preview-container'));
                }
                $editItemModal.removeClass('hidden').addClass('flex');
            };

            $('#edit-item-form').on('submit', function (e) {
                e.preventDefault();
                if (!String($('#edit_idcategory').val() || '').trim()) {
                    showToast('error', 'Please select a category.');
                    $('#edit-category-dd-menu').removeClass('hidden');
                    return;
                }
                $editItemModal.addClass('hidden').removeClass('flex');
                if ($loaderText.length) $loaderText.text('UPDATING...');
                $loaderContainer.removeClass('hidden').addClass('flex');

                $.ajax({
                    url: $(this).attr('action'), type: 'POST', data: new FormData(this), contentType: false, processData: false, dataType: 'json',
                    success: res => res.success ? window.location.reload() : ($loaderContainer.removeClass('flex').addClass('hidden'), showToast('error', res.message || 'Failed to update item.')),
                    error: () => { $loaderContainer.removeClass('flex').addClass('hidden'); showToast('error', 'A server error occurred while updating.'); }
                });
            });

            // --- DELETE ITEM MODAL ---
            const $deleteConfirmModal = $('#delete-confirm-modal');
            $('#delete-item-btn').on('click', () => { if ($('#edit_iditem').val()) $deleteConfirmModal.removeClass('hidden').addClass('flex'); });
            $('#cancel-delete-btn').on('click', () => $deleteConfirmModal.removeClass('flex').addClass('hidden'));

            $('#confirm-delete-btn').on('click', function () {
                const iditem = $('#edit_iditem').val();
                $deleteConfirmModal.removeClass('flex').addClass('hidden');
                hideEditModal();

                if ($loaderText.length) $loaderText.text('DELETING...');
                $loaderContainer.removeClass('hidden').addClass('flex');

                $.post('includes/delete_item.php', { iditem: iditem }, res => {
                    if (res.success) window.location.reload();
                    else {
                        $loaderContainer.removeClass('flex').addClass('hidden');
                        showToast('error', res.message || 'Failed to delete item.');
                        $editItemModal.removeClass('hidden').addClass('flex');
                    }
                }, 'json').fail(() => {
                    $loaderContainer.removeClass('flex').addClass('hidden');
                    showToast('error', 'A server error occurred while deleting.');
                });
            });

            // --- CATEGORY SELECT (ADD & EDIT ITEM MODALS) ---
            const $idCategory = $('#idcategory'),
                $ddMenu = $('#category-dd-menu'),
                $ddLabel = $('#category-dd-label'),
                $ddOptions = $('#category-dd-options');

            const $editIdCategory = $('#edit_idcategory'),
                $editDdMenu = $('#edit-category-dd-menu'),
                $editDdLabel = $('#edit-category-dd-label'),
                $editDdOptions = $('#edit-category-dd-options');

            window.setSelectedCategory = function (id, name) {
                $idCategory.val(String(id || ''));
                $ddLabel.text(name || 'Select category')
                    .toggleClass('text-slate-500 font-medium', !id)
                    .toggleClass('text-slate-900 font-bold', !!id);
            };

            window.setEditSelectedCategory = function (id, name) {
                $editIdCategory.val(String(id || ''));
                $editDdLabel.text(name || 'Select')
                    .toggleClass('text-slate-500 font-medium', !id)
                    .toggleClass('text-slate-900 font-bold', !!id);
            };

            function closeDropdown() {
                $ddMenu.addClass('hidden');
            }

            function closeEditDropdown() {
                $editDdMenu.addClass('hidden');
            }

            $('#category-dd-trigger').on('click', () => {
                $ddMenu.toggleClass('hidden');
            });

            $('#edit-category-dd-trigger').on('click', () => {
                $editDdMenu.toggleClass('hidden');
            });

            $ddOptions.on('click', '.category-select', function (e) {
                e.preventDefault();
                const $li = $(this).closest('li');
                setSelectedCategory($li.attr('data-id'), $li.attr('data-name'));
                closeDropdown();
            });

            $editDdOptions.on('click', '.edit-category-select', function (e) {
                e.preventDefault();
                const $li = $(this).closest('li');
                setEditSelectedCategory($li.attr('data-id'), $li.attr('data-name'));
                closeEditDropdown();
            });

            // Helper to append options to the dropdown lists (used by filter CRUD)
            function addCategoryOption(id, name, $targetList, prefix) {
                const escaped = $('<div/>').text(name).html();
                const isEdit = prefix === 'edit-category';
                const liClass = isEdit
                    ? 'edit-category-opt group flex items-center justify-between gap-2 rounded-lg px-3 py-2 hover:bg-slate-50 cursor-pointer transition-colors'
                    : 'category-opt group flex items-center justify-between gap-2 rounded-lg px-3 py-2 hover:bg-slate-50 cursor-pointer transition-colors';
                const btnClass = isEdit
                    ? 'edit-category-select flex-1 text-left text-xs font-bold text-slate-700 whitespace-normal break-words leading-snug cursor-pointer'
                    : 'category-select flex-1 text-left text-xs font-bold text-slate-700 whitespace-normal break-words leading-snug';

                $targetList.append(
                    `<li class="${liClass}" data-id="${id}" data-name="${name}"><button type="button" class="${btnClass}">${escaped}</button></li>`
                );
            }

            // --- CATEGORY CRUD (FILTER SIDEBAR) ---
            const $filterCatPanel = $('#category-filter-panel');
            const $filterAddToggle = $('#filter-category-add-toggle');
            const $filterAddRow = $('#filter-category-add-row');
            const $filterAddInput = $('#filter-category-add-input');
            const $filterAddBtn = $('#filter-category-add-btn');
            let filterEditingId = null;

            function resetFilterCategoryRow() {
                filterEditingId = null;
                $filterAddInput.val('');
                $filterAddRow.addClass('hidden');
                $filterAddBtn
                    .text('Add')
                    .removeClass('bg-amber-600 hover:bg-amber-700')
                    .addClass('bg-indigo-600 hover:bg-indigo-700');
            }

            function getFilterCategoryNames() {
                return $filterCatPanel.find('.filter-category-row[data-name]').map(function () {
                    return String($(this).attr('data-name') || '').trim().toLowerCase();
                }).get();
            }

            function isDuplicateFilterCategoryName(name) {
                const n = String(name || '').trim().toLowerCase();
                if (!n) return false;
                return getFilterCategoryNames().indexOf(n) !== -1;
            }

            if ($filterCatPanel.length) {
                $filterAddToggle.on('click', function () {
                    $filterAddRow.toggleClass('hidden');
                    if (!$filterAddRow.hasClass('hidden')) setTimeout(() => $filterAddInput.trigger('focus'), 0);
                });

                $filterAddInput.on('keydown', e => {
                    if (e.key === 'Enter') { e.preventDefault(); $filterAddBtn.click(); }
                });

                $filterAddBtn.on('click', function () {
                    const name = String($filterAddInput.val() || '').trim();
                    if (!name) return showToast('error', 'Please enter a category name.');

                    const isEditing = !!filterEditingId;
                    if (!isEditing && isDuplicateFilterCategoryName(name)) {
                        return showToast('error', 'A category with this name already exists.');
                    }

                    if (isEditing) {
                        const id = String(filterEditingId);
                        $.post(categoryCrudUrl, { action: 'update', idcategory: id, category_name: name }).done(res => {
                            if (!res.ok) return showToast('error', res.message || 'Could not rename category.');

                            const updated = String(res.category_name || name).trim();
                            const idStr = String(id);

                            const $row = $filterCatPanel.find(`.filter-category-row[data-id="${idStr}"]`);
                            $row.attr('data-name', updated);
                            $row.find('.wrap-text').text(updated);

                            const $addLi = $ddOptions.find(`li[data-id="${idStr}"]`);
                            $addLi.attr('data-name', updated);
                            $addLi.find('.category-select').text(updated);

                            const $editLi = $editDdOptions.find(`li[data-id="${idStr}"]`);
                            $editLi.attr('data-name', updated);
                            $editLi.find('.edit-category-select').text(updated);

                            if ($idCategory.val() === idStr) setSelectedCategory(idStr, updated);
                            if ($editIdCategory.val() === idStr) setEditSelectedCategory(idStr, updated);

                            showToast('success', 'Category renamed.');
                            resetFilterCategoryRow();
                        }).fail(() => showToast('error', 'Could not rename category.'));
                        return;
                    }

                    $.post(categoryCrudUrl, { action: 'add', category_name: name }).done(res => {
                        if (!res.ok) return showToast('error', res.message || 'Could not add category.');

                        // Add to sidebar (before the checkboxes are auto-submitted)
                        const escaped = $('<div/>').text(res.category_name).html();
                        const rowHtml = `
                            <div class="filter-category-row flex items-center gap-2 group" data-id="${res.idcategory}" data-name="${escaped}" data-confirming="0">
                                <label class="flex items-center gap-3 text-sm font-medium text-slate-600 select-none cursor-pointer group-hover:text-slate-900 transition-colors flex-1">
                                    <div class="relative flex items-center justify-center">
                                        <input type="checkbox" name="category[]" value="${res.idcategory}"
                                            class="peer h-5 w-5 cursor-pointer appearance-none rounded-md border-2 border-slate-300 bg-white/50 checked:border-indigo-600 checked:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 transition-all">
                                        <i class="fa-solid fa-check absolute text-white text-[10px] opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                                    </div>
                                    <span class="wrap-text pt-0.5">${escaped}</span>
                                </label>
                                <div class="filter-category-actions flex items-center gap-1 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity">
                                    <button type="button" class="filter-category-rename h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors" title="Rename">
                                        <i class="fa-solid fa-pen text-[10px]"></i>
                                    </button>
                                    <button type="button" class="filter-category-delete h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors" title="Delete">
                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                    </button>
                                </div>
                            </div>`;
                        $filterCatPanel.append(rowHtml);

                        // Add to dropdowns
                        addCategoryOption(res.idcategory, res.category_name, $ddOptions, 'category');
                        addCategoryOption(res.idcategory, res.category_name, $editDdOptions, 'edit-category');

                        resetFilterCategoryRow();
                        showToast('success', 'Category added.');
                    }).fail(() => showToast('error', 'Could not add category.'));
                });

                function exitInlineCategoryEdit($row, restoreText = true) {
                    if (!$row || !$row.length) return;
                    const original = String($row.attr('data-name') || '').trim();
                    const $input = $row.find('input.filter-category-inline-input');
                    if ($input.length) $input.remove();
                    if (restoreText) {
                        const $span = $row.find('span.wrap-text');
                        if ($span.length) $span.text(original);
                    }
                    $row.attr('data-editing', '0');
                    $row.find('.filter-category-actions').html(
                        '<button type="button" class="filter-category-rename h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors" title="Rename"><i class="fa-solid fa-pen text-[10px]"></i></button>' +
                        '<button type="button" class="filter-category-delete h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors" title="Delete"><i class="fa-solid fa-trash-can text-[10px]"></i></button>'
                    );
                }

                function applyCategoryRenameEverywhere(idStr, updated) {
                    const $row = $filterCatPanel.find(`.filter-category-row[data-id="${idStr}"]`);
                    $row.attr('data-name', updated);
                    $row.find('span.wrap-text').text(updated);

                    const $addLi = $ddOptions.find(`li[data-id="${idStr}"]`);
                    $addLi.attr('data-name', updated);
                    $addLi.find('.category-select').text(updated);

                    const $editLi = $editDdOptions.find(`li[data-id="${idStr}"]`);
                    $editLi.attr('data-name', updated);
                    $editLi.find('.edit-category-select').text(updated);

                    if ($idCategory.val() === idStr) setSelectedCategory(idStr, updated);
                    if ($editIdCategory.val() === idStr) setEditSelectedCategory(idStr, updated);
                }

                $filterCatPanel.on('click', '.filter-category-rename', function (e) {
                    e.stopPropagation();
                    const $row = $(this).closest('.filter-category-row');

                    // Only allow one inline edit at a time
                    const $editing = $filterCatPanel.find('.filter-category-row[data-editing="1"]').first();
                    if ($editing.length && !$editing.is($row)) exitInlineCategoryEdit($editing, true);

                    if (String($row.attr('data-confirming') || '0') === '1') return;

                    $row.attr('data-editing', '1');
                    const currentName = String($row.attr('data-name') || '').trim();

                    // Swap the visible text for an inline input
                    const $span = $row.find('span.wrap-text').first();
                    if (!$span.length) return;

                    $span.text('');
                    const $input = $(`<input type="text" class="filter-category-inline-input w-full rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-bold text-slate-700 shadow-inner focus:outline-none focus:ring-2 focus:ring-indigo-500/20" />`);
                    $input.val(currentName);
                    $span.append($input);

                    // Replace actions with save/cancel
                    $row.find('.filter-category-actions').html(
                        '<button type="button" class="filter-category-inline-cancel h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-slate-500 hover:bg-slate-50 transition-colors" title="Cancel"><i class="fa-solid fa-xmark text-[10px]"></i></button>' +
                        '<button type="button" class="filter-category-inline-save h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-emerald-600 hover:bg-emerald-50 transition-colors" title="Save"><i class="fa-solid fa-check text-[10px]"></i></button>'
                    );

                    setTimeout(() => {
                        $input.trigger('focus');
                        const el = $input.get(0);
                        if (el && el.setSelectionRange) el.setSelectionRange(currentName.length, currentName.length);
                    }, 0);
                });

                $filterCatPanel.on('click', '.filter-category-inline-cancel', function (e) {
                    e.stopPropagation();
                    const $row = $(this).closest('.filter-category-row');
                    exitInlineCategoryEdit($row, true);
                });

                function saveInlineCategoryEdit($row) {
                    const idStr = String($row.attr('data-id') || '').trim();
                    const currentName = String($row.attr('data-name') || '').trim();
                    const nextName = String($row.find('input.filter-category-inline-input').val() || '').trim();

                    if (!nextName) return showToast('error', 'Please enter a category name.');
                    if (nextName.toLowerCase() === currentName.toLowerCase()) return exitInlineCategoryEdit($row, true);

                    // Duplicate check (excluding itself)
                    const names = getFilterCategoryNames().filter(n => n !== currentName.toLowerCase());
                    if (names.indexOf(nextName.toLowerCase()) !== -1) {
                        return showToast('error', 'A category with this name already exists.');
                    }

                    $.post(categoryCrudUrl, { action: 'update', idcategory: idStr, category_name: nextName }).done(res => {
                        if (!res.ok) return showToast('error', res.message || 'Could not rename category.');
                        const updated = String(res.category_name || nextName).trim();
                        applyCategoryRenameEverywhere(idStr, updated);
                        showToast('success', 'Category renamed.');
                        exitInlineCategoryEdit($row, false);
                    }).fail(() => showToast('error', 'Could not rename category.'));
                }

                $filterCatPanel.on('click', '.filter-category-inline-save', function (e) {
                    e.stopPropagation();
                    const $row = $(this).closest('.filter-category-row');
                    saveInlineCategoryEdit($row);
                });

                $filterCatPanel.on('keydown', 'input.filter-category-inline-input', function (e) {
                    const $row = $(this).closest('.filter-category-row');
                    if (e.key === 'Enter') { e.preventDefault(); saveInlineCategoryEdit($row); }
                    if (e.key === 'Escape') { e.preventDefault(); exitInlineCategoryEdit($row, true); }
                });

                $filterCatPanel.on('click', '.filter-category-delete', function (e) {
                    e.stopPropagation();
                    const $row = $(this).closest('.filter-category-row');
                    $row.attr('data-confirming', '1');
                    $row.find('.filter-category-actions').html(
                        '<button type="button" class="filter-category-cancel h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-slate-500 hover:bg-slate-50 transition-colors" title="Cancel"><i class="fa-solid fa-xmark text-[10px]"></i></button>' +
                        '<button type="button" class="filter-category-confirm h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-red-600 hover:bg-red-50 transition-colors" title="Confirm delete"><i class="fa-solid fa-check text-[10px]"></i></button>'
                    );
                });

                $filterCatPanel.on('click', '.filter-category-cancel', function (e) {
                    e.stopPropagation();
                    const $row = $(this).closest('.filter-category-row');
                    $row.attr('data-confirming', '0');
                    $row.find('.filter-category-actions').html(
                        '<button type="button" class="filter-category-rename h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-colors" title="Rename"><i class="fa-solid fa-pen text-[10px]"></i></button>' +
                        '<button type="button" class="filter-category-delete h-7 w-7 inline-flex items-center justify-center rounded-md bg-white border border-slate-100 shadow-sm text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors" title="Delete"><i class="fa-solid fa-trash-can text-[10px]"></i></button>'
                    );
                });

                $filterCatPanel.on('click', '.filter-category-confirm', function (e) {
                    e.stopPropagation();
                    const $row = $(this).closest('.filter-category-row');
                    const id = String($row.attr('data-id'));
                    $.post(categoryCrudUrl, { action: 'delete', idcategory: id }).done(res => {
                        if (!res.ok) {
                            showToast('error', res.message || 'Could not remove category.');
                            $row.find('.filter-category-cancel').click();
                            return;
                        }

                        $row.remove();
                        $ddOptions.find(`li[data-id="${id}"]`).remove();
                        $editDdOptions.find(`li[data-id="${id}"]`).remove();

                        if ($idCategory.val() === id) setSelectedCategory('', '');
                        if ($editIdCategory.val() === id) setEditSelectedCategory('', '');

                        // Uncheck and refresh results
                        if ($filterForm.length) {
                            $filterForm.find(`input[name="category[]"][value="${id}"]`).prop('checked', false);
                            $filterForm.trigger('submit');
                        }

                        showToast('success', 'Category removed.');
                    }).fail(() => {
                        showToast('error', 'Could not remove category.');
                        $row.find('.filter-category-cancel').click();
                    });
                });
            }

            $(document).on('click', function (e) {
                if (!$profileMenu.is(e.target) && $profileMenu.has(e.target).length === 0 && !$profileBtn.is(e.target)) $profileMenu.addClass('hidden');
                if ($(e.target).is($addItemModal)) hideAddModal();
                if ($(e.target).is($editItemModal)) hideEditModal();
                if ($(e.target).is($deleteConfirmModal)) $deleteConfirmModal.removeClass('flex').addClass('hidden');
                if ($('#category-dd').length && !$('#category-dd').is(e.target) && $('#category-dd').has(e.target).length === 0) closeDropdown();
                if ($('#edit-category-dd').length && !$('#edit-category-dd').is(e.target) && $('#edit-category-dd').has(e.target).length === 0) closeEditDropdown();
            });

            $(document).on('keydown', function (e) {
                if (e.key === "Escape") {
                    $profileMenu.addClass('hidden');
                    hideAddModal();
                    hideEditModal();
                    $deleteConfirmModal.removeClass('flex').addClass('hidden');
                    closeLegalModal();
                }
            });

            if ($filterForm.length) $filterForm.on('change', 'input[name="category[]"], #sort', () => $filterForm.trigger('submit'));
            if ($catToggle.length && $catPanel.length) {
                $catToggle.on('click', function () {
                    const isOpen = !$catPanel.hasClass('hidden');
                    $catPanel.toggleClass('hidden', isOpen);
                    $catToggle.attr('aria-expanded', isOpen ? 'false' : 'true');
                    if ($catChevron.length) $catChevron.toggleClass('rotate-180', !isOpen);
                });
            }



            $('#openPassModal').on('click', function () {
                $profileMenu.addClass('hidden');
                $passModal.removeClass('hidden').addClass('flex');
            });

            $('#openHistorySidebar').on('click', function () {
                $profileMenu.addClass('hidden');
                $('#historySidebar').removeClass('translate-x-full');
                fetchHistory(false);
            });

            $('#closeSidebar').on('click', function () {
                $('#historySidebar').addClass('translate-x-full');
            });
        });
    </script>
</body>

</html>