<?php
require_once __DIR__ . '/../includes/navigation.php';
$navigation_groups = accessible_navigation_groups();
?>
<aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <div class="brand-mark">
        <img class="brand-logo" src="assets/img/barangay-san-jose-logo.jpg" alt="Barangay San Jose logo">
        <div class="brand-copy"><strong>SJQIBMS</strong><span>Barangay San Jose</span></div>
        <button class="sidebar-collapse" type="button" aria-label="Collapse sidebar" data-sidebar-collapse><?= icon_svg('chevron') ?></button>
    </div>
    <button class="sidebar-close" type="button" aria-label="Close navigation" data-sidebar-close>Close</button>
    <nav class="sidebar-nav">
        <?php foreach ($navigation_groups as $group): ?>
            <?php $group_id = 'nav-group-' . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $group['label'])); ?>
            <section class="nav-group" data-nav-group="<?= e($group_id) ?>">
                <button class="nav-group-toggle" type="button" aria-expanded="true" aria-controls="<?= e($group_id) ?>-items" data-nav-group-toggle>
                    <span class="nav-group-icon"><?= icon_svg($group['items'][0]['icon']) ?></span><span class="nav-label"><?= e($group['label']) ?></span><?= icon_svg('chevron') ?>
                </button>
                <div class="nav-group-items" id="<?= e($group_id) ?>-items">
                    <?php foreach ($group['items'] as $item): ?>
                        <a class="nav-link <?= ($active_page ?? '') === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>" data-nav-link>
                            <?= icon_svg($item['icon']) ?><span class="nav-link-label"><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer"><?= e(role_title()) ?> Portal<span class="sidebar-role">SJQIBMS</span></div>
</aside>
<div class="sidebar-backdrop" data-sidebar-close></div>
