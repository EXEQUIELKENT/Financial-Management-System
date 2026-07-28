    </div><!-- /.content -->
</div><!-- /.main-area -->
</div><!-- /.app-shell -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraScripts)) foreach ($extraScripts as $s): ?>
<script src="<?= e($s) ?>"></script>
<?php endforeach; ?>
</body>
</html>
