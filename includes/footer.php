<?php $is_full = $full_page ?? false; ?>
<?php if (!$is_full): ?>
    </div><!-- /.page-body -->
    </div><!-- /.main-content -->
<?php else: ?>
    </div><!-- /fullpage wrapper -->
<?php endif; ?>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!empty($extra_js)) echo $extra_js; ?>
</body>
</html>
