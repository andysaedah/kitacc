<!-- Page Footer -->
<footer class="page-footer">
    <p>&copy;
        <?php echo date('Y'); ?>
        <?php echo htmlspecialchars(getChurchName()); ?>. All rights reserved.
    </p>
    <p><?php echo htmlspecialchars(getSetting('app_name', 'KiTAcc')); ?> - Built with <span
            style="color:#e74c3c;">&hearts;</span> by
        <a href="https://www.acreativemagic.com/" target="_blank" rel="noopener"
            style="color: var(--primary); font-weight: 600; text-decoration: none;">CreativeMagic</a>.
    </p>
</footer>
</main>
</div>
</div>

<!-- Bottom Navigation (Mobile) -->
<?php include __DIR__ . '/bottom_nav.php'; ?>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<!-- Core JavaScript -->
<?php
$isProduction = (getenv('APP_ENV') === 'production');
$jsFile = $isProduction && file_exists(__DIR__ . '/../js/app.min.js') ? 'js/app.min.js' : 'js/app.js';
$jsPath = __DIR__ . '/../' . $jsFile;
?>
<script src="<?php echo $jsFile; ?>?v=<?php echo filemtime($jsPath); ?>"></script>

<?php if (isset($page_scripts)): ?>
    <?php echo $page_scripts; ?>
<?php endif; ?>
</body>

</html>