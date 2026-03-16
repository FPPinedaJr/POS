<?php
session_start();
require_once 'connect_db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$iduser = $_SESSION['user_id'];
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 24;

// FIXED: Check if the key exists AND isn't "all"
$iditem = (isset($_GET['iditem']) && $_GET['iditem'] !== 'all') ? (int) $_GET['iditem'] : null;

$query = "SELECT h.*, i.item_name, 
          (SELECT h2.item_count FROM item_history h2 
           WHERE h2.iditem = h.iditem AND h2.iditem_history < h.iditem_history 
           ORDER BY h2.iditem_history DESC LIMIT 1) as prev_count
          FROM item_history h
          JOIN item i ON h.iditem = i.iditem
          WHERE i.iduser = ? ";

$params = [$iduser];

if ($iditem) {
    $query .= " AND h.iditem = ? ";
    $params[] = $iditem;
}

$query .= " ORDER BY h.created_at DESC LIMIT $offset, $limit";
$stmt = $pdo->prepare($query);
$stmt->execute($params);


$logs = $stmt->fetchAll();

if (empty($logs))
    exit;

$grouped = [];
foreach ($logs as $log) {
    $date = date('F d, Y', strtotime($log['created_at']));
    $grouped[$date][] = $log;
}

foreach ($grouped as $date => $dayLogs):
    $count = count($dayLogs);
    $dayId = md5($date);
    ?>
    <div class="mb-6">
        <div class="flex items-center justify-between text-sm font-medium text-gray-600 mb-3 cursor-pointer toggle-day-details hover:text-gray-900 group"
            data-target="details-<?php echo $dayId; ?>">
            <div class="flex items-center gap-2">
                <i
                    class="fa-solid fa-chevron-right  text-[10px] text-gray-400 group-hover:text-gray-600 transition-transform duration-200"></i>
                <span><?php echo $date; ?></span>
            </div>
            <span class="text-xs font-normal text-gray-400"><?php echo $count; ?> item<?php echo $count > 1 ? 's' : ''; ?>
                modified</span>
        </div>

        <div id="details-<?php echo $dayId; ?>" class="pl-4 ml-1.5  hidden border-l-[1.5px] border-gray-200 space-y-5 py-1">
            <?php foreach ($dayLogs as $log):
                $prev = (int) $log['prev_count'];
                $new = (int) $log['item_count'];
                $diff = $new - $prev;
                $time = (new DateTime($log['created_at']))->modify('+8 hours')->format('h:i A');
                $isPositive = $diff >= 0;
                $dotColor = $isPositive ? 'bg-emerald-500' : 'bg-rose-500';
                $textColor = $isPositive ? 'text-emerald-600' : 'text-rose-600';
                $icon = $isPositive ? '▲' : '▼';
                ?>
                <div class="relative pl-5 group/item">
                    <div
                        class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full <?php echo $dotColor; ?> ring-4 ring-gray-50 group-hover/item:ring-white transition-all">
                    </div>

                    <p class="text-[13px] font-semibold text-gray-900 leading-tight">
                        <?php echo htmlspecialchars($log['item_name']); ?>
                    </p>
                    <div class="flex items-center gap-2 mt-1.5 text-[11px] text-gray-500">
                        <span><?php echo $time; ?></span>
                        <span>•</span>
                        <div class="flex items-center gap-1.5 bg-white px-2 py-0.5 rounded border border-gray-200 shadow-sm">
                            <span class="font-medium text-gray-500"><?php echo $prev; ?></span>
                            <i class="fa-solid fa-arrow-right text-gray-300"></i>
                            <span class="font-medium text-gray-900"><?php echo $new; ?></span>
                            <span
                                class="ml-1 font-bold <?php echo $textColor; ?>">(<?php echo $icon . ' ' . abs($diff); ?>)</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>