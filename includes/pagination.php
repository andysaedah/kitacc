<?php
/**
 * KiTAcc - Pagination Helper
 * Reusable server-side pagination with UI rendering
 */

/**
 * Calculate pagination metadata
 * 
 * @param int $totalRecords Total number of records
 * @param int $currentPage Current page number (1-indexed)
 * @param int $perPage Records per page
 * @return array Pagination metadata
 */
function paginate(int $totalRecords, int $currentPage = 1, int $perPage = 25): array
{
    $totalPages = max(1, (int) ceil($totalRecords / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    $from = $totalRecords > 0 ? $offset + 1 : 0;
    $to = min($offset + $perPage, $totalRecords);

    return [
        'current_page' => $currentPage,
        'per_page' => $perPage,
        'total' => $totalRecords,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'from' => $from,
        'to' => $to,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
    ];
}

/**
 * Render pagination UI
 * 
 * @param array $pager Output from paginate()
 * @param string $baseUrl Base URL for page links
 * @param array $queryParams Existing query params to preserve
 */
function renderPagination(array $pager, string $baseUrl = '', array $queryParams = []): void
{
    if ($pager['total_pages'] <= 1 && $pager['total'] <= $pager['per_page'])
        return;

    // Remove 'page' from existing params
    unset($queryParams['page']);

    $buildUrl = function (int $page) use ($baseUrl, $queryParams): string {
        $queryParams['page'] = $page;
        $qs = http_build_query($queryParams);
        return $baseUrl . ($qs ? '?' . $qs : '');
    };

    // Calculate visible page range (max 7 buttons)
    $maxVisible = 7;
    $startPage = max(1, $pager['current_page'] - (int) floor($maxVisible / 2));
    $endPage = min($pager['total_pages'], $startPage + $maxVisible - 1);
    if ($endPage - $startPage + 1 < $maxVisible) {
        $startPage = max(1, $endPage - $maxVisible + 1);
    }
    ?>

    <div class="d-flex justify-between align-center flex-wrap gap-4" style="margin-top: 1rem; padding: 0.75rem 0;">
        <div class="pagination-info">
            Showing <strong>
                <?php echo $pager['from']; ?>–
                <?php echo $pager['to']; ?>
            </strong>
            of <strong>
                <?php echo number_format($pager['total']); ?>
            </strong> records
        </div>

        <?php if ($pager['total_pages'] > 1): ?>
            <div class="pagination">
                <!-- First -->
                <a href="<?php echo $buildUrl(1); ?>"
                    class="pagination-btn <?php echo !$pager['has_prev'] ? 'disabled' : ''; ?>" <?php echo !$pager['has_prev'] ? 'tabindex="-1" aria-disabled="true"' : ''; ?>
                    title="First">
                    <i class="fas fa-angle-double-left"></i>
                </a>

                <!-- Previous -->
                <a href="<?php echo $buildUrl($pager['current_page'] - 1); ?>"
                    class="pagination-btn <?php echo !$pager['has_prev'] ? 'disabled' : ''; ?>" <?php echo !$pager['has_prev'] ? 'tabindex="-1" aria-disabled="true"' : ''; ?>
                    title="Previous">
                    <i class="fas fa-angle-left"></i>
                </a>

                <!-- Page Numbers -->
                <?php if ($startPage > 1): ?>
                    <a href="<?php echo $buildUrl(1); ?>" class="pagination-btn">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="pagination-btn" style="border: none; cursor: default;">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="<?php echo $buildUrl($i); ?>"
                        class="pagination-btn <?php echo $i === $pager['current_page'] ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($endPage < $pager['total_pages']): ?>
                    <?php if ($endPage < $pager['total_pages'] - 1): ?>
                        <span class="pagination-btn" style="border: none; cursor: default;">…</span>
                    <?php endif; ?>
                    <a href="<?php echo $buildUrl($pager['total_pages']); ?>" class="pagination-btn">
                        <?php echo $pager['total_pages']; ?>
                    </a>
                <?php endif; ?>

                <!-- Next -->
                <a href="<?php echo $buildUrl($pager['current_page'] + 1); ?>"
                    class="pagination-btn <?php echo !$pager['has_next'] ? 'disabled' : ''; ?>" <?php echo !$pager['has_next'] ? 'tabindex="-1" aria-disabled="true"' : ''; ?>
                    title="Next">
                    <i class="fas fa-angle-right"></i>
                </a>

                <!-- Last -->
                <a href="<?php echo $buildUrl($pager['total_pages']); ?>"
                    class="pagination-btn <?php echo !$pager['has_next'] ? 'disabled' : ''; ?>" <?php echo !$pager['has_next'] ? 'tabindex="-1" aria-disabled="true"' : ''; ?>
                    title="Last">
                    <i class="fas fa-angle-double-right"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
