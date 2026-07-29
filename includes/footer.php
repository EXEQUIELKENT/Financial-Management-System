    </div><!-- /.content -->
</div><!-- /.main-area -->
</div><!-- /.app-shell -->
<script src="<?= asset_url('assets/js/main.js') ?>"></script>
<script src="<?= asset_url('assets/js/page-guide.js') ?>"></script>
<?php if (!empty($extraScripts)) foreach ($extraScripts as $s):
    // Version our own local assets by file mtime; leave external CDN URLs untouched.
    $relative = str_starts_with($s, BASE_URL . '/assets/') ? substr($s, strlen(BASE_URL) + 1) : null;
    $src = $relative ? asset_url($relative) : $s;
?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
</body>
</html>
