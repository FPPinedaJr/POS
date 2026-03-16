<?php
/**
 * Shared helper for dashboard pagination/filter/search.
 *
 * Returns paged items with latest counts and image paths.
 */
declare(strict_types=1);

function inv_normalize_public_path(?string $path): ?string
{
    if ($path === null)
        return null;
    $path = trim($path);
    if ($path === '')
        return null;

    // Support both stored variants:
    // - "storage/..." (preferred): relative to /public
    // - "/storage/..." (older): convert to "storage/..."
    // - "../storage/..." (legacy): convert to "storage/..."
    if (strncmp($path, '../storage/', 11) === 0) {
        return substr($path, 3);
    }
    if (strncmp($path, '/storage/', 9) === 0) {
        return substr($path, 1);
    }
    return $path;
}

function inv_parse_positive_int(?string $raw, int $default, ?int $min = null, ?int $max = null): int
{
    $n = (int) ($raw ?? '');
    if ($n <= 0)
        $n = $default;
    if ($min !== null)
        $n = max($min, $n);
    if ($max !== null)
        $n = min($max, $n);
    return $n;
}

function inv_build_query_string(array $params, array $keepKeys = []): string
{
    $out = [];
    foreach ($params as $k => $v) {
        if ($keepKeys && !in_array($k, $keepKeys, true))
            continue;
        if ($v === null)
            continue;
        if (is_string($v) && trim($v) === '')
            continue;
        $out[$k] = $v;
    }
    $qs = http_build_query($out);
    return $qs !== '' ? ('?' . $qs) : '';
}

/**
 * @return array{items: array<int, array<string,mixed>>, total_rows:int, total_pages:int, page:int, per_page:int, q:string, categories: array<int,int>, sort:string}
 */
function inv_fetch_dashboard_items(PDO $pdo, int $userId, array $input, int $perPage = 24): array
{
    $q = isset($input['q']) ? trim((string) $input['q']) : '';
    $qLike = $q !== '' ? ('%' . $q . '%') : null;

    $parseIntList = function ($raw): array {
        if ($raw === null)
            return [];
        if (is_string($raw))
            $raw = array_map('trim', explode(',', $raw));
        $out = [];
        foreach ((array) $raw as $x) {
            $n = (int) $x;
            if ($n > 0)
                $out[] = $n;
        }
        $out = array_values(array_unique($out));
        return $out;
    };
    $rawCategories = $input['category'] ?? $input['category[]'] ?? null;
    $categoryIds = $parseIntList($rawCategories);

    $page = inv_parse_positive_int($input['page'] ?? null, 1, 1, 10_000);
    $offset = ($page - 1) * $perPage;

    $sort = (string) ($input['sort'] ?? 'name_asc');
    $orderBy = match ($sort) {
        'name_desc' => 'i.item_name DESC, i.iditem DESC',
        'count_desc' => 'item_count DESC, i.item_name ASC',
        'count_asc' => 'item_count ASC, i.item_name ASC',
        default => 'i.item_name ASC, i.iditem ASC',
    };

    $baseWhere = ['i.iduser = :iduser'];
    if ($qLike !== null) {
        $baseWhere[] = '(i.item_name LIKE :q_item OR c.category_name LIKE :q_cat)';
    }
    if (!empty($categoryIds)) {
        $placeholders = [];
        foreach ($categoryIds as $idx => $_) {
            $placeholders[] = ':cat_' . $idx;
        }
        $baseWhere[] = 'i.idcategory IN (' . implode(',', $placeholders) . ')';
    }

    $buildWhereSql = function (bool $useHistory) use ($baseWhere): string {
        $where = $baseWhere;
        return implode(' AND ', $where);
    };

    $fromSqlWithHistory = "
        FROM item i
        LEFT JOIN category c
            ON c.idcategory = i.idcategory
           AND (c.iduser = :cat_user OR c.iduser IS NULL OR c.iduser = 0)
           AND (c.is_deleted = 0 OR c.is_deleted IS NULL)
        LEFT JOIN (
            SELECT ih.iditem, ih.item_count
            FROM item_history ih
            INNER JOIN (
                SELECT iditem, MAX(iditem_history) AS max_iditem_history
                FROM item_history
                GROUP BY iditem
            ) latest
                ON latest.iditem = ih.iditem
               AND latest.max_iditem_history = ih.iditem_history
        ) h
            ON h.iditem = i.iditem
    ";

    $fromSqlNoHistory = "
        FROM item i
        LEFT JOIN category c
            ON c.idcategory = i.idcategory
           AND (c.iduser = :cat_user OR c.iduser IS NULL OR c.iduser = 0)
           AND (c.is_deleted = 0 OR c.is_deleted IS NULL)
    ";

    $useHistory = true;
    try {
        $whereSql = $buildWhereSql(true);
        $sqlCount = "SELECT COUNT(*) " . $fromSqlWithHistory . " WHERE " . $whereSql;
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->bindValue(':iduser', $userId, PDO::PARAM_INT);
        $stmtCount->bindValue(':cat_user', $userId, PDO::PARAM_INT);
        if ($qLike !== null) {
            $stmtCount->bindValue(':q_item', $qLike, PDO::PARAM_STR);
            $stmtCount->bindValue(':q_cat', $qLike, PDO::PARAM_STR);
        }
        foreach ($categoryIds as $idx => $cid) {
            $stmtCount->bindValue(':cat_' . $idx, $cid, PDO::PARAM_INT);
        }
        $stmtCount->execute();
        $totalRows = (int) $stmtCount->fetchColumn();
    } catch (PDOException $e) {
        $useHistory = false;
        $whereSql = $buildWhereSql(false);
        $sqlCount = "SELECT COUNT(*) " . $fromSqlNoHistory . " WHERE " . $whereSql;
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->bindValue(':iduser', $userId, PDO::PARAM_INT);
        $stmtCount->bindValue(':cat_user', $userId, PDO::PARAM_INT);
        if ($qLike !== null) {
            $stmtCount->bindValue(':q_item', $qLike, PDO::PARAM_STR);
            $stmtCount->bindValue(':q_cat', $qLike, PDO::PARAM_STR);
        }
        foreach ($categoryIds as $idx => $cid) {
            $stmtCount->bindValue(':cat_' . $idx, $cid, PDO::PARAM_INT);
        }
        $stmtCount->execute();
        $totalRows = (int) $stmtCount->fetchColumn();
    }

    $totalPages = (int) max(1, (int) ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }

    $runSelect = function (bool $withHistory) use ($pdo, $fromSqlWithHistory, $fromSqlNoHistory, $buildWhereSql, $orderBy, $userId, $qLike, $categoryIds, $perPage, $offset): array {
        $whereSql = $buildWhereSql($withHistory);
        $limitInt = (int) $perPage;
        $offsetInt = (int) $offset;

        if ($withHistory) {
            // FIX: Added i.idcategory and i.value
            $sql = "
                SELECT
                    i.iditem,
                    i.idcategory,
                    i.value,
                    i.item_name,
                    i.image_thumb_path,
                    i.image_preview_path,
                    c.category_name,
                    COALESCE(h.item_count, 0) AS item_count
                " . $fromSqlWithHistory . "
                WHERE " . $whereSql . "
                ORDER BY " . $orderBy . "
                LIMIT " . $limitInt . " OFFSET " . $offsetInt . "
            ";
        } else {
            // FIX: Added i.idcategory and i.value
            $sql = "
                SELECT
                    i.iditem,
                    i.idcategory,
                    i.value,
                    i.item_name,
                    i.image_thumb_path,
                    i.image_preview_path,
                    c.category_name,
                    0 AS item_count
                " . $fromSqlNoHistory . "
                WHERE " . $whereSql . "
                ORDER BY " . $orderBy . "
                LIMIT " . $limitInt . " OFFSET " . $offsetInt . "
            ";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':iduser', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':cat_user', $userId, PDO::PARAM_INT);
        if ($qLike !== null) {
            $stmt->bindValue(':q_item', $qLike, PDO::PARAM_STR);
            $stmt->bindValue(':q_cat', $qLike, PDO::PARAM_STR);
        }
        foreach ($categoryIds as $idx => $cid) {
            $stmt->bindValue(':cat_' . $idx, $cid, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    };

    try {
        $items = $runSelect($useHistory);
    } catch (PDOException $e) {
        if ($useHistory) {
            $useHistory = false;
            $items = $runSelect(false);
        } else {
            throw $e;
        }
    }

    foreach ($items as &$it) {
        $it['image_thumb_path'] = inv_normalize_public_path($it['image_thumb_path'] ?? null);
        $it['image_preview_path'] = inv_normalize_public_path($it['image_preview_path'] ?? null);
    }
    unset($it);

    return [
        'items' => $items,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
        'page' => $page,
        'per_page' => $perPage,
        'q' => $q,
        'categories' => $categoryIds,
        'sort' => $sort,
    ];
}