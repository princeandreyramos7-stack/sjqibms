<?php
// Barangay header of the Financial Management printouts. Expects $settings from finance_report_settings()
// (edit templates/finance/report_settings.php to change the text or logo).
?>
<header class="fnp-header">
    <?php if ($settings['logo'] !== ''): ?><img class="fnp-logo" src="<?= e($settings['logo']) ?>" alt="Logo"><?php else: ?><span class="fnp-logo is-placeholder" aria-hidden="true">LOGO</span><?php endif; ?>
    <div class="fnp-header-text">
        <?php foreach ($settings['header_lines'] as $line): ?><p><?= e((string) $line) ?></p><?php endforeach; ?>
        <p class="fnp-barangay"><?= e($settings['barangay_name']) ?></p>
        <?php if ($settings['office'] !== ''): ?><p class="fnp-office"><?= e($settings['office']) ?></p><?php endif; ?>
        <?php if ($settings['address'] !== ''): ?><p><?= e($settings['address']) ?></p><?php endif; ?>
    </div>
    <span class="fnp-logo" aria-hidden="true"></span>
</header>
