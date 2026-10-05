<?php
// Barangay header of the Disaster Management printouts. Expects $settings from disaster_report_settings()
// (edit templates/disaster/report_settings.php to change the text or logo).
?>
<header class="drp-header">
    <?php if ($settings['logo'] !== ''): ?><img class="drp-logo" src="<?= e($settings['logo']) ?>" alt="Logo"><?php else: ?><span class="drp-logo is-placeholder" aria-hidden="true">LOGO</span><?php endif; ?>
    <div class="drp-header-text">
        <?php foreach ($settings['header_lines'] as $line): ?><p><?= e((string) $line) ?></p><?php endforeach; ?>
        <p class="drp-barangay"><?= e($settings['barangay_name']) ?></p>
        <?php if ($settings['office'] !== ''): ?><p class="drp-office"><?= e($settings['office']) ?></p><?php endif; ?>
        <?php if ($settings['address'] !== ''): ?><p><?= e($settings['address']) ?></p><?php endif; ?>
    </div>
    <span class="drp-logo" aria-hidden="true"></span>
</header>
