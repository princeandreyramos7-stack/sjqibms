document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('#sidebar');
    const mobileToggle = document.querySelector('[data-sidebar-toggle]');
    const closeButtons = document.querySelectorAll('[data-sidebar-close]');
    const backdrop = document.querySelector('.sidebar-backdrop');
    const collapseToggle = document.querySelector('[data-sidebar-collapse]');
    const notificationToggle = document.querySelector('[data-notification-toggle]');
    const notificationDropdown = document.querySelector('[data-notification-dropdown]');
    const notificationCount = document.querySelector('.notification-count');
    const dashboardCards = document.querySelectorAll('[data-dashboard-card]');
    const dashboardUpdated = document.querySelector('[data-dashboard-updated]');
    const dashboardWarning = document.querySelector('.dashboard-status.warning');
    const storageKey = 'sjqibms-navigation';
    const isMobile = () => window.matchMedia('(max-width: 760px)').matches;

    let preference = {};
    try {
        preference = JSON.parse(localStorage.getItem(storageKey) || '{}');
    } catch (error) {
        preference = {};
    }

    if (!isMobile() && preference.collapsed === true) {
        sidebar?.classList.add('is-collapsed');
    }

    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const groupKey = group.dataset.navGroup;
        if (preference.groups?.[groupKey] === false) {
            group.classList.add('is-collapsed');
            group.querySelector('[data-nav-group-toggle]')?.setAttribute('aria-expanded', 'false');
        }
        if (group.querySelector('.nav-link.active')) {
            group.classList.remove('is-collapsed');
            group.querySelector('[data-nav-group-toggle]')?.setAttribute('aria-expanded', 'true');
        }
    });

    const savePreference = () => {
        const groups = {};
        document.querySelectorAll('[data-nav-group]').forEach((group) => {
            groups[group.dataset.navGroup] = !group.classList.contains('is-collapsed');
        });
        localStorage.setItem(storageKey, JSON.stringify({
            collapsed: sidebar?.classList.contains('is-collapsed') === true,
            groups,
        }));
    };

    const closeMobileSidebar = () => {
        sidebar?.classList.remove('is-open');
        backdrop?.classList.remove('is-visible');
    };

    mobileToggle?.addEventListener('click', () => {
        sidebar?.classList.add('is-open');
        backdrop?.classList.add('is-visible');
    });
    closeButtons.forEach((button) => button.addEventListener('click', closeMobileSidebar));
    document.querySelectorAll('[data-nav-link]').forEach((link) => link.addEventListener('click', closeMobileSidebar));

    collapseToggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('is-collapsed');
        savePreference();
    });

    document.querySelectorAll('[data-nav-group-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const group = button.closest('[data-nav-group]');
            if (sidebar?.classList.contains('is-collapsed') && !isMobile()) {
                sidebar.classList.remove('is-collapsed');
            }
            if (sidebar?.classList.contains('is-collapsed')) {
                group?.classList.remove('is-collapsed');
            } else {
                group?.classList.toggle('is-collapsed');
            }
            button.setAttribute('aria-expanded', String(!group?.classList.contains('is-collapsed')));
            savePreference();
        });
    });

    notificationToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        const isOpen = notificationDropdown?.classList.toggle('is-open') === true;
        notificationToggle.setAttribute('aria-expanded', String(isOpen));
    });
    document.addEventListener('click', (event) => {
        if (notificationDropdown && !notificationDropdown.contains(event.target) && !notificationToggle?.contains(event.target)) {
            notificationDropdown.classList.remove('is-open');
            notificationToggle?.setAttribute('aria-expanded', 'false');
        }
    });

    const clock = document.querySelector('[data-local-clock]');
    const updateClock = () => {
        if (clock) {
            clock.textContent = new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date());
        }
    };
    updateClock();
    window.setInterval(updateClock, 1000);

    if (dashboardCards.length > 0) {
        let refreshInFlight = false;

        const formatCount = (value) => new Intl.NumberFormat().format(value);
        const announcementPanel = document.querySelector('[data-announcements-panel]');
        const announcementTabs = document.querySelectorAll('[data-announcement-tab]');
        let selectedAnnouncementTab = 'published';

        const activityPanel = document.querySelector('[data-activity-panel]');

        const activityIconPaths = {
            megaphone:  'm3 11 18-5v12L3 14v-3zM11.6 16.4 13 21H8l-1.7-5.1',
            users:      'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 8 0 4 4 0 0 0-8 0M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
            home:       'm3 10 9-7 9 7v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1zM9 21v-6h6v6',
            file:       'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h6',
            case:       'M21 16V8a2 2 0 0 0-2-2h-3V4a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H5a2 2 0 0 0-2 2v8M3 11h18v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 6h6M9 15h6',
            briefcase:  'M3 7h18v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v3h4v-3',
            'user-plus':'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 7a4 4 0 1 0 8 0 4 4 0 0 0-8 0M19 8v6M16 11h6',
            'user-cog': 'M9 7a4 4 0 1 0 8 0 4 4 0 0 0-8 0M2 21v-2a4 4 0 0 1 4-4h6M19.4 15a1.7 1.7 0 0 0 0-3.2l-.4-.7a1.7 1.7 0 0 0-2.9 0l-.4.7a1.7 1.7 0 0 0 0 3.2l.4.7a1.7 1.7 0 0 0 2.9 0zM18 11v-1M18 18v-1M14.5 14.5h-1M22.5 14.5h-1',
            history:    'M3 12a9 9 0 1 0 3-6.7M3 4v5h5M12 7v5l3 2',
        };

        const buildActivityIcon = (iconName) => {
            const paths = activityIconPaths[iconName] || activityIconPaths.history;
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'nav-svg');
            svg.setAttribute('viewBox', '0 0 24 24');
            svg.setAttribute('aria-hidden', 'true');
            svg.setAttribute('fill', 'none');
            svg.setAttribute('stroke', 'currentColor');
            svg.setAttribute('stroke-width', '1.8');
            svg.setAttribute('stroke-linecap', 'round');
            svg.setAttribute('stroke-linejoin', 'round');
            paths.split('M').filter(Boolean).forEach((segment) => {
                const p = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                p.setAttribute('d', 'M' + segment);
                svg.appendChild(p);
            });
            return svg;
        };

        const renderActivities = (activities) => {
            if (!activityPanel) {
                return;
            }
            const heading = activityPanel.querySelector('.panel-heading');
            activityPanel.innerHTML = '';
            if (heading) {
                activityPanel.appendChild(heading);
            }

            if (!Array.isArray(activities) || activities.length === 0) {
                const empty = document.createElement('div');
                empty.className = 'dashboard-empty-state';
                empty.textContent = 'No recent activities available.';
                activityPanel.appendChild(empty);
                return;
            }

            const ul = document.createElement('ul');
            ul.className = 'activity-card-list';
            ul.dataset.activityList = '';

            activities.forEach((activity) => {
                const li = document.createElement('li');

                const a = document.createElement('a');
                a.className = 'activity-card';
                a.href = `activity_view.php?id=${encodeURIComponent(activity.id)}`;

                const iconWrap = document.createElement('span');
                iconWrap.className = 'activity-card-icon';
                iconWrap.appendChild(buildActivityIcon(activity.icon || 'history'));

                const body = document.createElement('span');
                body.className = 'activity-card-body';

                const action = document.createElement('span');
                action.className = 'activity-card-action';
                const actionText = activity.action || '';
                action.textContent = actionText.charAt(0).toUpperCase() + actionText.slice(1);

                const meta = document.createElement('span');
                meta.className = 'activity-card-meta';
                meta.textContent = `${activity.actor || ''} · ${activity.module_label || ''}`;

                body.append(action, meta);

                const time = document.createElement('time');
                time.className = 'activity-card-time';
                time.setAttribute('datetime', activity.created_at || '');
                time.textContent = activity.date || '';

                a.append(iconWrap, body, time);
                li.appendChild(a);
                ul.appendChild(li);
            });

            activityPanel.appendChild(ul);
        };

        const renderAnnouncements = (announcements) => {
            if (!announcementPanel || !announcements) {
                return;
            }

            Object.entries(announcements).forEach(([tab, records]) => {
                const list = announcementPanel.querySelector(`[data-announcement-list="${tab}"]`);
                if (!list) {
                    return;
                }
                list.innerHTML = '';
                if (records.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'announcement-empty';
                    empty.textContent = tab === 'drafts' ? 'No drafts available.' : 'No announcements available.';
                    list.appendChild(empty);
                    return;
                }
                records.forEach((record) => {
                    const link = document.createElement('a');
                    link.className = 'announcement-item';
                    link.href = `announcement_view.php?id=${encodeURIComponent(record.id)}`;
                    const title = document.createElement('strong');
                    title.textContent = record.title || '';
                    const preview = document.createElement('span');
                    preview.textContent = record.preview || '';
                    const time = document.createElement('time');
                    time.textContent = record.date || '';
                    link.append(title, preview, time);
                    list.appendChild(link);
                });
            });
        };

        announcementTabs.forEach((tab) => tab.addEventListener('click', () => {
            selectedAnnouncementTab = tab.dataset.announcementTab;
            announcementTabs.forEach((item) => {
                const active = item === tab;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', String(active));
            });
            announcementPanel?.querySelectorAll('[data-announcement-list]').forEach((list) => {
                list.hidden = list.dataset.announcementList !== selectedAnnouncementTab;
            });
        }));

        const refreshDashboard = async () => {
            if (refreshInFlight) {
                return;
            }

            refreshInFlight = true;
            try {
                const response = await fetch('dashboard_stats.php', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                const result = await response.json();
                const errors = result.errors || {};
                let hasErrors = !response.ok;

                Object.entries(result.statistics || {}).forEach(([key, value]) => {
                    const valueElement = document.querySelector(`[data-stat-value="${key}"]`);
                    const errorElement = document.querySelector(`[data-stat-error="${key}"]`);
                    if (Number.isInteger(value)) {
                        if (valueElement) {
                            valueElement.textContent = formatCount(value);
                        }
                        if (errorElement) {
                            errorElement.textContent = 'Current records';
                        }
                    } else {
                        hasErrors = true;
                        if (errorElement) {
                            errorElement.textContent = errors[key] || 'This statistic is temporarily unavailable.';
                        }
                    }
                });

                const demographics = result.demographics;
                if (demographics) {
                    const demographicTotal = document.querySelector('[data-demographic-total]');
                    if (demographicTotal) {
                        demographicTotal.textContent = formatCount(demographics.total);
                    }
                    Object.entries(demographics).forEach(([dimension, values]) => {
                        if (dimension === 'total') {
                            return;
                        }
                        Object.entries(values).forEach(([label, value]) => {
                            const valueElement = document.querySelector(`[data-demographic-value="${dimension}-${label}"]`);
                            const chartLabel = document.querySelector(`[data-chart-label="${dimension}-${label}"]`);
                            if (valueElement) {
                                valueElement.textContent = formatCount(value);
                            }
                            if (chartLabel) {
                                chartLabel.textContent = formatCount(value);
                            }
                        });
                    });

                    const ageValues = Object.values(demographics.age);
                    const ageMax = Math.max(...ageValues, 1);
                    Object.entries(demographics.age).forEach(([label, value]) => {
                        const bar = document.querySelector(`[data-chart-bar="age-${label}"]`);
                        if (bar) {
                            bar.style.height = `${(value / ageMax) * 100}%`;
                        }
                    });
                    Object.entries(demographics.status).forEach(([label, value]) => {
                        const bar = document.querySelector(`[data-chart-bar="status-${label}"]`);
                        if (bar) {
                            bar.style.width = `${demographics.total ? (value / demographics.total) * 100 : 0}%`;
                        }
                    });
                    const donut = document.querySelector('[data-chart-donut="sex"]');
                    if (donut) {
                        const total = demographics.total || 0;
                        donut.style.setProperty('--male', `${total ? (demographics.sex.Male / total) * 100 : 0}%`);
                        donut.style.setProperty('--female', `${total ? (demographics.sex.Female / total) * 100 : 0}%`);
                        donut.style.setProperty('--other', `${total ? (demographics.sex.Other / total) * 100 : 0}%`);
                    }
                }
                if (result.announcements) {
                    renderAnnouncements(result.announcements);
                }
                if (result.activities !== undefined) {
                    renderActivities(result.activities);
                }
                if (result.notifications) {
                    if (notificationCount) {
                        notificationCount.textContent = result.notifications.count;
                        notificationCount.hidden = result.notifications.count < 1;
                    }
                    if (notificationDropdown && Array.isArray(result.notifications.items)) {
                        const heading = notificationDropdown.querySelector('.dropdown-heading');
                        notificationDropdown.innerHTML = '';
                        if (heading) notificationDropdown.appendChild(heading);
                        if (result.notifications.items.length === 0) {
                            const empty = document.createElement('p');
                            empty.className = 'notification-empty';
                            empty.textContent = 'You have no unread notifications.';
                            notificationDropdown.appendChild(empty);
                        } else {
                            result.notifications.items.forEach((notification) => {
                                const link = document.createElement('a');
                                link.className = `notification-item ${notification.is_read ? 'is-read' : 'is-unread'}`;
                                // Links are built by the server; only the two known relative targets are accepted.
                                const href = String(notification.href || '');
                                link.href = /^(announcement_view|notification_open)\.php\?[A-Za-z0-9_=&]+$/.test(href) ? href : 'notifications.php';
                                const title = document.createElement('strong');
                                title.textContent = notification.title || '';
                                const message = document.createElement('span');
                                message.textContent = notification.message || '';
                                link.append(title, message);
                                notificationDropdown.appendChild(link);
                            });
                        }
                    }
                }

                if (!hasErrors && dashboardUpdated) {
                    dashboardUpdated.textContent = new Intl.DateTimeFormat(undefined, {
                        dateStyle: 'medium',
                        timeStyle: 'short',
                    }).format(new Date());
                }
                if (dashboardWarning) {
                    dashboardWarning.hidden = !hasErrors;
                }
            } catch (error) {
                if (dashboardWarning) {
                    dashboardWarning.hidden = false;
                    dashboardWarning.textContent = 'Statistics could not be refreshed. Last successfully loaded values are still shown.';
                }
            } finally {
                refreshInFlight = false;
            }
        };

        window.setInterval(refreshDashboard, 30000);
    }

    // ── Kebab action menus (announcements page) ───────────────────────────────
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action-menu]');
        // Close all open menus first
        document.querySelectorAll('[data-action-menu]').forEach((b) => {
            if (b !== btn) {
                b.setAttribute('aria-expanded', 'false');
                b.nextElementSibling?.removeAttribute('hidden');
                b.nextElementSibling?.setAttribute('hidden', '');
            }
        });
        if (btn) {
            e.stopPropagation();
            const menu = btn.nextElementSibling;
            const isOpen = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', String(!isOpen));
            if (isOpen) { menu?.setAttribute('hidden', ''); }
            else if (menu) {
                // The menu opens leftward from the button; on narrow screens the button can sit at the left edge,
                // so open it rightward instead whenever it would run off the screen.
                menu.classList.remove('is-align-left');
                menu.removeAttribute('hidden');
                if (menu.getBoundingClientRect().left < 8) menu.classList.add('is-align-left');
            }
        }
    });

    // Announcement Archive / Unarchive / Delete: the row buttons only open this confirmation; the POST happens from its confirm button.
    const confirmDialog = document.querySelector('#announcement-confirm-dialog');
    if (confirmDialog && typeof confirmDialog.showModal === 'function') {
        const confirmCopy = {
            publish: { heading: 'Publish this draft?', detail: 'Are you sure you want to publish this draft announcement? It will become visible to its authorized audience.', submit: 'Publish Announcement', danger: false },
            archive: { heading: 'Are you sure you want to archive this announcement?', detail: 'This announcement will be moved to the Archived tab and will no longer appear in the Published announcements list.', submit: 'Archive Announcement', danger: false },
            unarchive: { heading: 'Are you sure you want to unarchive this announcement?', detail: 'This announcement will be restored to its previous status and removed from the Archived tab.', submit: 'Unarchive Announcement', danger: false },
            delete: { heading: 'Are you sure you want to permanently delete this announcement?', detail: 'This action cannot be undone. The announcement will be permanently removed.', submit: 'Delete Permanently', danger: true },
        };
        const confirmForm = confirmDialog.querySelector('form');
        const confirmId = confirmDialog.querySelector('[data-confirm-id-input]');
        const confirmAction = confirmDialog.querySelector('[data-confirm-action-input]');
        const confirmSubmit = confirmDialog.querySelector('[data-confirm-submit]');
        const confirmCancel = confirmDialog.querySelector('[data-confirm-cancel]');
        let lastTrigger = null;

        // Delegated so row buttons inside live-search results that replace the list keep working.
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-confirm-action]');
            if (!button) return;
            const copy = confirmCopy[button.dataset.confirmAction];
            if (!copy) return;
            lastTrigger = button;
            confirmId.value = button.dataset.confirmId;
            confirmAction.value = button.dataset.confirmAction;
            confirmDialog.querySelector('[data-confirm-heading]').textContent = copy.heading;
            confirmDialog.querySelector('[data-confirm-name]').textContent = button.dataset.confirmTitle;
            confirmDialog.querySelector('[data-confirm-detail]').textContent = copy.detail;
            confirmSubmit.textContent = copy.submit;
            confirmSubmit.classList.toggle('is-danger', copy.danger);
            confirmSubmit.disabled = false;
            confirmDialog.showModal();
            // Focus the safe choice so an Enter keypress carried over from the row button cannot confirm.
            confirmCancel.focus();
        });

        confirmCancel.addEventListener('click', () => confirmDialog.close());
        confirmForm.addEventListener('submit', (event) => {
            if (!confirmId.value || !confirmAction.value) { event.preventDefault(); return; }
            confirmSubmit.disabled = true;
        });
        confirmDialog.addEventListener('close', () => {
            confirmId.value = '';
            confirmAction.value = '';
            if (lastTrigger) lastTrigger.focus();
        });
    }

    // Announcement form: Cancel asks before leaving; every save/publish button asks before submitting with that same button.
    const formDialog = document.querySelector('#announcement-form-dialog, [data-form-dialog]');
    if (formDialog && typeof formDialog.showModal === 'function') {
        const formCopy = {
            discard: { heading: 'Discard changes?', message: 'Are you sure you want to cancel editing this announcement? Any unsaved changes will be lost.', confirm: 'Discard Changes', danger: true },
            save: { heading: 'Save announcement changes?', message: 'Are you sure you want to save the changes to this announcement? The updated information will be visible to its authorized audience.', confirm: 'Save Changes', danger: false },
            'draft-new': { heading: 'Save announcement as draft?', message: 'Are you sure you want to save this announcement as a draft? It will not be visible to residents until it is published.', confirm: 'Save as Draft', danger: false },
            'publish-new': { heading: 'Publish announcement?', message: 'Are you sure you want to publish this announcement? It will become visible to its authorized audience and notification records will be created for eligible recipients.', confirm: 'Publish Announcement', danger: false },
            'draft-edit': { heading: 'Save draft changes?', message: 'Are you sure you want to save your changes to this draft? The announcement will remain unpublished.', confirm: 'Save Draft Changes', danger: false },
            'publish-edit': { heading: 'Publish this draft?', message: 'Are you sure you want to publish this draft? The announcement will become visible to its authorized audience and eligible recipients will receive a notification.', confirm: 'Publish Announcement', danger: false },
        };
        const formDismiss = formDialog.querySelector('[data-form-dialog-dismiss]');
        const formConfirm = formDialog.querySelector('[data-form-dialog-confirm]');
        let formTrigger = null;
        let formMode = null;
        let submitting = false;

        // Triggers either name a predefined announcement mode or carry their own copy in data-dialog-* attributes (data-form-confirm="custom").
        const copyFor = (trigger) => {
            const mode = trigger.dataset.formConfirm;
            if (formCopy[mode]) return formCopy[mode];
            if (mode !== 'custom' || !trigger.dataset.dialogHeading) return null;
            const parse = (value) => { try { const list = JSON.parse(value || '[]'); return Array.isArray(list) ? list : []; } catch (error) { return []; } };
            // data-dialog-summary="form": review summary built from the form's labelled fields (text only, truncated).
            const summary = [];
            if (trigger.dataset.dialogSummary === 'form' && trigger.form) {
                trigger.form.querySelectorAll('[data-summary-label]').forEach((field) => {
                    if (field.disabled || field.closest('template')) return;
                    let value = field.tagName === 'SELECT' ? (field.selectedOptions[0] && field.value !== '' ? field.selectedOptions[0].textContent : '') : field.value;
                    value = String(value || '').replace(/\s+/g, ' ').trim();
                    if (field.type === 'datetime-local' && value) value = value.replace('T', ' ');
                    if (value) summary.push([field.dataset.summaryLabel, value.length > 80 ? `${value.slice(0, 77)}…` : value]);
                });
            }
            return {
                heading: trigger.dataset.dialogHeading,
                message: trigger.dataset.dialogMessage || '',
                confirm: trigger.dataset.dialogConfirm || 'Confirm',
                danger: trigger.dataset.dialogDanger === 'true',
                // Optional extras (Documents): record summary, required reason, requirement checklist, blocked notice.
                dismiss: trigger.dataset.dialogDismiss || null,
                details: parse(trigger.dataset.dialogDetails).concat(summary),
                checklist: parse(trigger.dataset.dialogChecklist),
                reason: trigger.dataset.dialogReason || null,
                blocked: trigger.dataset.dialogBlocked || null,
            };
        };
        const extras = {
            close: formDialog.querySelector('[data-form-dialog-close]'),
            details: formDialog.querySelector('[data-form-dialog-details]'),
            checklist: formDialog.querySelector('[data-form-dialog-checklist]'),
            reason: formDialog.querySelector('[data-form-dialog-reason]'),
            reasonLabel: formDialog.querySelector('[data-form-dialog-reason-label]'),
            reasonError: formDialog.querySelector('[data-form-dialog-reason-error]'),
            blocked: formDialog.querySelector('[data-form-dialog-blocked]'),
        };
        const reasonInput = extras.reason ? extras.reason.querySelector('textarea') : null;
        const dismissDefault = formDismiss.textContent;
        let formCopyActive = null;
        const fillExtras = (copy) => {
            const hasExtras = Boolean(copy.details && (copy.details.length || copy.checklist.length || copy.reason || copy.blocked));
            if (extras.close) extras.close.hidden = !hasExtras;
            if (extras.details) {
                extras.details.replaceChildren(...(copy.details || []).map(([label, value]) => {
                    const row = document.createElement('div');
                    const term = document.createElement('dt');
                    const data = document.createElement('dd');
                    term.textContent = label;
                    data.textContent = value;
                    row.append(term, data);
                    return row;
                }));
                extras.details.hidden = !(copy.details || []).length;
            }
            if (extras.checklist) {
                extras.checklist.replaceChildren(...(copy.checklist || []).map(([label, met, note]) => {
                    const item = document.createElement('li');
                    item.className = met ? 'is-met' : 'is-missing';
                    const text = document.createElement('span');
                    text.textContent = label;
                    item.append(text);
                    if (note) {
                        const small = document.createElement('small');
                        small.textContent = note;
                        item.append(small);
                    }
                    item.setAttribute('aria-label', `${label}: ${met ? 'complete' : 'missing'}${note ? ` (${note})` : ''}`);
                    return item;
                }));
                extras.checklist.hidden = !(copy.checklist || []).length;
            }
            if (extras.reason && reasonInput) {
                extras.reason.hidden = !copy.reason;
                extras.reasonLabel.textContent = copy.reason ? `${copy.reason} (required)` : '';
                reasonInput.value = '';
                reasonInput.classList.remove('is-invalid');
                extras.reasonError.hidden = true;
            }
            if (extras.blocked) {
                extras.blocked.textContent = copy.blocked || '';
                extras.blocked.hidden = !copy.blocked;
            }
            formDismiss.textContent = copy.dismiss || dismissDefault;
        };

        // Delegated so triggers inside live-search results (for example Add to Household) keep working after the results are replaced.
        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-form-confirm]');
            if (!trigger) return;
            const copy = copyFor(trigger);
            if (!copy) return;
            event.preventDefault();
            const isLink = trigger.tagName === 'A';
            // Run the browser's own required/maxlength checks first so invalid input is reported, not confirmed.
            if (!isLink && trigger.form && !trigger.form.reportValidity()) return;
            formTrigger = trigger;
            formCopyActive = copy;
            formMode = isLink ? 'discard' : 'submit';
            formDialog.querySelector('[data-form-dialog-heading]').textContent = copy.heading;
            formDialog.querySelector('[data-form-dialog-message]').textContent = copy.message;
            fillExtras(copy);
            formConfirm.textContent = copy.confirm;
            formConfirm.classList.toggle('is-danger', copy.danger);
            // A blocked action shows what is missing; its confirm button stays disabled and nothing can be submitted.
            formConfirm.disabled = Boolean(copy.blocked);
            formDialog.showModal();
            // Focus the reason field when one is required; otherwise the safe choice so a carried-over Enter cannot confirm.
            if (copy.reason && reasonInput) reasonInput.focus(); else formDismiss.focus();
        });

        formDismiss.addEventListener('click', () => formDialog.close());
        if (extras.close) extras.close.addEventListener('click', () => formDialog.close());
        formConfirm.addEventListener('click', () => {
            if (submitting || !formTrigger || (formCopyActive && formCopyActive.blocked)) return;
            if (formCopyActive && formCopyActive.reason && reasonInput) {
                const reason = reasonInput.value.replace(/\s+/g, ' ').trim();
                if (reason.length < 5 || reason.length > 500) {
                    extras.reasonError.textContent = 'Enter a reason of 5 to 500 characters.';
                    extras.reasonError.hidden = false;
                    reasonInput.classList.add('is-invalid');
                    reasonInput.focus();
                    return;
                }
                const field = formTrigger.form && formTrigger.form.querySelector('input[name="reason"]');
                if (!field) return;
                field.value = reason;
            }
            formConfirm.disabled = true;
            if (formMode === 'discard') {
                submitting = true;
                window.location.assign(formTrigger.href);
                return;
            }
            const form = formTrigger.form;
            submitting = true;
            if (typeof form.requestSubmit === 'function') {
                // Submitting with the original button keeps its save_action=update value in the request.
                form.requestSubmit(formTrigger);
            } else {
                if (formTrigger.name) {
                    const action = document.createElement('input');
                    action.type = 'hidden';
                    action.name = formTrigger.name;
                    action.value = formTrigger.value;
                    form.appendChild(action);
                }
                form.submit();
            }
        });
        formDialog.addEventListener('close', () => {
            if (!submitting && formTrigger) formTrigger.focus();
        });
    }

    // Resident forms: show only the panels for the selected option; hidden panels' fields are disabled so they are neither validated nor submitted.
    const togglePanels = (scope, attribute, selected) => {
        scope.querySelectorAll(`[${attribute}]`).forEach((panel) => {
            const visible = panel.getAttribute(attribute).split(' ').includes(selected);
            panel.hidden = !visible;
            panel.querySelectorAll('input, select, textarea').forEach((field) => { field.disabled = !visible; });
        });
    };

    document.querySelectorAll('[data-household-fields]').forEach((section) => {
        const radios = section.querySelectorAll('[data-household-mode]');
        const update = () => {
            const checked = section.querySelector('[data-household-mode]:checked');
            togglePanels(section, 'data-household-panel', checked ? checked.value : '');
        };
        radios.forEach((radio) => radio.addEventListener('change', update));
        update();
    });

    document.querySelectorAll('[data-household-filter]').forEach((input) => {
        const select = document.getElementById(input.dataset.householdFilter);
        if (!select) return;
        input.addEventListener('input', () => {
            const term = input.value.trim().toLowerCase();
            Array.from(select.options).forEach((option) => {
                option.hidden = option.value !== '' && term !== '' && !option.textContent.toLowerCase().includes(term);
            });
        });
    });

    // ── Live Search (standard for every SJQIBMS search bar; see README "Live Search standard") ──
    // A <form data-live-search data-live-target="#results"> re-queries its own page with the X-Live-Search header on typing
    // (debounced) and on filter changes, and swaps in the authorized results fragment the page returns.
    const initLiveSearch = (form) => {
        if (form.dataset.liveSearchReady === 'true') return;
        const target = document.querySelector(form.dataset.liveTarget || '');
        if (!target || typeof window.fetch !== 'function') return;
        form.dataset.liveSearchReady = 'true';

        const minLength = Number.parseInt(form.dataset.liveMin || '0', 10) || 0;
        const delay = Number.parseInt(form.dataset.liveDebounce || '300', 10) || 300;
        const minMessage = form.dataset.liveMinMessage || `Type at least ${minLength} characters to search.`;
        const errorMessage = form.dataset.liveError || 'Search is temporarily unavailable. Check your connection and try again.';
        const query = form.querySelector('[data-live-query]');
        const [rangeStart, rangeEnd] = (form.dataset.liveRange || '').split(',').map((selector) => (selector ? form.querySelector(selector.trim()) : null));
        let status = form.dataset.liveStatus ? document.querySelector(form.dataset.liveStatus) : null;
        if (!status) {
            status = document.createElement('p');
            status.className = 'live-search-status';
            status.setAttribute('aria-live', 'polite');
            form.insertAdjacentElement('afterend', status);
        }
        let timer = null;
        let controller = null;
        let sequence = 0;
        let lastUrl = null;

        // Automatic searching replaces the Search/Apply buttons; they stay in the markup as the no-JavaScript fallback.
        form.querySelectorAll('[data-live-submit]').forEach((button) => { button.hidden = true; });

        const setStatus = (message, retry = false) => {
            status.textContent = message;
            status.classList.toggle('is-error', retry);
            if (retry) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'live-search-retry';
                button.textContent = 'Retry';
                button.addEventListener('click', () => { if (lastUrl) load(lastUrl); });
                status.append(' ', button);
            }
        };

        const formUrl = () => {
            const url = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
            url.search = '';
            new FormData(form).forEach((value, key) => {
                const text = String(value).trim();
                if (text !== '') url.searchParams.set(key, text);
            });
            return url;
        };

        const load = async (url) => {
            if (controller) controller.abort();
            controller = new AbortController();
            const current = ++sequence;
            lastUrl = url;
            target.classList.add('is-loading');
            target.setAttribute('aria-busy', 'true');
            setStatus('Searching…');
            try {
                const response = await fetch(url, { headers: { 'X-Live-Search': '1' }, credentials: 'same-origin', signal: controller.signal });
                // Only fragments explicitly produced by live_search_respond() are accepted (a redirect to the login page is not).
                if (!response.ok || response.headers.get('X-Live-Search-Fragment') !== '1') throw new Error('unavailable');
                const html = await response.text();
                if (current !== sequence) return; // A newer search has started; never let an older response overwrite it.
                target.innerHTML = html;
                // Lets components inside the new fragment (e.g. the report A4 viewer) initialise themselves.
                target.dispatchEvent(new CustomEvent('live-search:updated', { bubbles: true }));
                window.history.replaceState(window.history.state, '', url.toString());
                setStatus('');
            } catch (error) {
                if (error.name === 'AbortError' || current !== sequence) return;
                // Keep the last valid results on screen; only report the failure.
                setStatus(errorMessage, true);
            } finally {
                if (current === sequence) {
                    target.classList.remove('is-loading');
                    target.removeAttribute('aria-busy');
                }
            }
        };

        const run = () => {
            window.clearTimeout(timer);
            if (rangeStart && rangeEnd) {
                if (rangeStart.validity.badInput || rangeEnd.validity.badInput) { setStatus('Enter complete, valid dates.'); return; }
                if (rangeStart.value && rangeEnd.value && rangeStart.value > rangeEnd.value) { setStatus('The start date must be on or before the end date.'); return; }
            }
            const url = formUrl();
            url.searchParams.delete('page');
            // Minimum length counts non-whitespace characters, matching the server-side rule.
            const term = query ? query.value.replace(/\s+/g, '') : '';
            if (minLength > 0 && term.length < minLength) {
                if (controller) controller.abort();
                sequence++;
                target.classList.remove('is-loading');
                target.innerHTML = '';
                window.history.replaceState(window.history.state, '', url.toString());
                setStatus(minMessage);
                return;
            }
            load(url);
        };

        form.addEventListener('input', (event) => {
            if (event.target.matches('[data-live-query], input[type="search"], input[type="text"]')) {
                window.clearTimeout(timer);
                timer = window.setTimeout(run, delay);
            }
        });
        form.addEventListener('change', (event) => {
            if (event.target.matches('select, input[type="date"], input[type="checkbox"], input[type="radio"]')) run();
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            run();
        });
        form.addEventListener('click', (event) => {
            const reset = event.target.closest('[data-live-reset]');
            if (!reset) return;
            event.preventDefault();
            form.querySelectorAll('input:not([type="hidden"]), select').forEach((field) => {
                if (field.tagName === 'SELECT') field.selectedIndex = 0;
                else if (field.type === 'checkbox' || field.type === 'radio') field.checked = false;
                else field.value = '';
            });
            run();
        });
        // Pagination links inside the results keep the current search and filters (they are built by the server) and load in place.
        target.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-live-page]');
            if (!link) return;
            event.preventDefault();
            load(new URL(link.href, window.location.href));
            target.scrollIntoView({ block: 'start', behavior: 'smooth' });
        });
    };
    document.querySelectorAll('form[data-live-search]').forEach(initLiveSearch);
    window.SJQIBMS = Object.assign(window.SJQIBMS || {}, { initLiveSearch });

    // Filter folders (Residents: Purok, Age Group): choosing a folder sets its filter select and lets Live Search reload the list
    // in place; choosing the active folder again clears that filter. Without Live Search the links simply load the filtered page.
    document.querySelectorAll('[data-filter-folders]').forEach((folders) => {
        const select = document.querySelector(folders.dataset.filterFolders);
        const form = select ? select.form : null;
        if (!form || form.dataset.liveSearchReady !== 'true') return;
        const sync = () => folders.querySelectorAll('[data-folder-value]').forEach((folder) => {
            const active = folder.dataset.folderValue === select.value;
            folder.classList.toggle('is-active', active);
            if (active) folder.setAttribute('aria-current', 'true'); else folder.removeAttribute('aria-current');
        });
        folders.addEventListener('click', (event) => {
            const folder = event.target.closest('[data-folder-value]');
            if (!folder) return;
            event.preventDefault();
            select.value = select.value === folder.dataset.folderValue ? '' : folder.dataset.folderValue;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
        });
        select.addEventListener('change', sync);
        // The Reset link clears the filter without a change event, so re-sync whenever new results arrive.
        document.addEventListener('live-search:updated', sync);
    });

    // Folder counts follow the active filters: each Live Search response carries them in [data-folder-counts]
    // ({"purok": {"1": n, ...}, "age": {"infant": n, ...}}), applied here to the folders of the matching group.
    document.addEventListener('live-search:updated', (event) => {
        const source = event.target.querySelector('[data-folder-counts]');
        if (!source) return;
        let counts;
        try { counts = JSON.parse(source.dataset.folderCounts); } catch (error) { return; }
        const groups = { purok: '#resident-purok', age: '#resident-age', sector: '#resident-sector' };
        Object.entries(groups).forEach(([group, selector]) => {
            const folders = document.querySelector(`[data-filter-folders="${selector}"]`);
            if (!folders || !counts[group]) return;
            folders.querySelectorAll('[data-folder-value]').forEach((folder) => {
                const count = counts[group][folder.dataset.folderValue];
                const label = folder.querySelector('small');
                if (label && typeof count === 'number') label.textContent = `${count} resident${count === 1 ? '' : 's'}`;
            });
        });
    });

    // VIEW switch (Residents: By Purok / By Age Group): shows one folder group without reloading. The choice is kept in the
    // form's hidden "view" field (so Live Search URLs carry it) and in the address bar. Without JavaScript the links reload the page.
    document.querySelectorAll('[data-folder-view-switch]').forEach((viewSwitch) => {
        const tabs = viewSwitch.querySelectorAll('[data-folder-view]');
        const input = document.querySelector('[data-folder-view-input]');
        const show = (view) => {
            tabs.forEach((tab) => {
                const selected = tab.dataset.folderView === view;
                tab.classList.toggle('is-active', selected);
                tab.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
            document.querySelectorAll('[data-folder-view-panel]').forEach((panel) => { panel.hidden = panel.dataset.folderViewPanel !== view; });
            if (input) input.value = view;
            const url = new URL(window.location.href);
            if (view === 'purok') url.searchParams.delete('view'); else url.searchParams.set('view', view);
            window.history.replaceState(window.history.state, '', url.toString());
        };
        viewSwitch.addEventListener('click', (event) => {
            const tab = event.target.closest('[data-folder-view]');
            if (!tab) return;
            event.preventDefault();
            show(tab.dataset.folderView);
        });
    });

    document.querySelectorAll('[data-status-form]').forEach((form) => {
        const select = form.querySelector('[data-status-select]');
        if (!select) return;
        const update = () => togglePanels(form, 'data-status-panel', select.value);
        select.addEventListener('change', update);
        update();
    });

    // ── A4 document viewer (Documents details page, Reports preview) ──
    // Scales the on-screen sheet with CSS zoom only; printed output keeps its real A4 size. Viewers that arrive in a
    // Live Search fragment are initialised when the fragment is swapped in ('live-search:updated').
    const initDocViewer = (viewer) => {
        if (viewer.dataset.viewerReady === 'true') return;
        const stage = viewer.querySelector('[data-doc-stage]');
        const sheet = viewer.querySelector('[data-doc-sheet]');
        const level = viewer.querySelector('[data-doc-zoom-level]');
        if (!stage || !sheet) return;
        viewer.dataset.viewerReady = 'true';
        const a4Width = ((Number.parseFloat(sheet.dataset.docWidthMm || '') || 210) / 25.4) * 96; // paper width in CSS pixels
        let scale = 1;
        let fitMode = true;
        const apply = (value) => {
            scale = Math.min(2, Math.max(0.3, Math.round(value * 100) / 100));
            sheet.style.zoom = String(scale);
            if (level) level.textContent = `${Math.round(scale * 100)}%`;
        };
        const fit = () => {
            const available = stage.clientWidth - 24;
            if (available > 0) apply(Math.min(1, available / a4Width));
        };
        viewer.addEventListener('click', (event) => {
            const button = event.target.closest('[data-doc-zoom]');
            if (!button) return;
            const mode = button.dataset.docZoom;
            fitMode = mode === 'fit';
            if (mode === 'fit') fit();
            else apply(scale + (mode === 'in' ? 0.1 : -0.1));
        });
        viewer.docViewerFit = () => { if (fitMode) fit(); };
        fit();
    };
    document.querySelectorAll('[data-doc-viewer]').forEach(initDocViewer);
    // One resize handler for whichever viewers are currently on the page.
    window.addEventListener('resize', () => document.querySelectorAll('[data-doc-viewer]').forEach((viewer) => { if (viewer.docViewerFit) viewer.docViewerFit(); }));
    document.addEventListener('live-search:updated', (event) => event.target.querySelectorAll('[data-doc-viewer]').forEach(initDocViewer));

    // ── Complaints & Blotter forms ──
    // Resident lookup (staff only; the server re-reads the resident by ID and ignores client-supplied names).
    document.addEventListener('input', (event) => {
        const query = event.target.closest('[data-lookup-query]');
        if (!query) return;
        const block = query.closest('[data-person-lookup]');
        const results = block && block.querySelector('[data-lookup-results]');
        if (!results || typeof window.fetch !== 'function') return;
        window.clearTimeout(query.lookupTimer);
        const term = query.value.trim();
        if (term.replace(/\s+/g, '').length < 2) { results.replaceChildren(); return; }
        query.lookupTimer = window.setTimeout(async () => {
            const sequence = (query.lookupSequence || 0) + 1;
            query.lookupSequence = sequence;
            try {
                const response = await fetch(`case_resident_lookup.php?q=${encodeURIComponent(term)}`, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const data = response.ok ? await response.json() : { results: [] };
                if (sequence !== query.lookupSequence) return;
                const items = Array.isArray(data.results) ? data.results : [];
                if (items.length === 0) {
                    const empty = document.createElement('p');
                    empty.className = 'case-lookup-empty';
                    empty.textContent = 'No matching residents. Enter the person\'s details manually below.';
                    results.replaceChildren(empty);
                    return;
                }
                results.replaceChildren(...items.map((item) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'case-lookup-option';
                    button.setAttribute('role', 'option');
                    const name = document.createElement('strong');
                    name.textContent = item.name;
                    const meta = document.createElement('span');
                    meta.textContent = item.meta;
                    button.append(name, meta);
                    button.addEventListener('click', () => {
                        block.querySelector('[data-lookup-id]').value = String(item.id);
                        const nameField = block.querySelector('[data-lookup-name]');
                        nameField.value = item.name;
                        nameField.readOnly = true;
                        const address = block.querySelector('[data-lookup-address]');
                        if (address && !address.value) address.value = item.address || '';
                        const contact = block.querySelector('[data-lookup-contact]');
                        if (contact && !contact.value) contact.value = item.contact || '';
                        const selected = block.querySelector('[data-lookup-selected]');
                        if (selected) { selected.hidden = false; selected.querySelector('[data-lookup-selected-id]').textContent = String(item.id); }
                        results.replaceChildren();
                        query.value = '';
                    });
                    return button;
                }));
            } catch (error) {
                results.replaceChildren();
            }
        }, 300);
    });
    document.addEventListener('click', (event) => {
        const clear = event.target.closest('[data-lookup-clear]');
        if (clear) {
            const block = clear.closest('[data-person-lookup]');
            block.querySelector('[data-lookup-id]').value = '';
            const nameField = block.querySelector('[data-lookup-name]');
            nameField.readOnly = false;
            nameField.focus();
            clear.closest('[data-lookup-selected]').hidden = true;
            return;
        }
        // Repeatable person rows (witnesses and other involved persons).
        const add = event.target.closest('[data-repeat-add]');
        if (add) {
            const list = add.parentElement.querySelector('[data-repeat-list]');
            const template = list && list.querySelector('template[data-repeat-template]');
            if (!template) return;
            const index = Number.parseInt(list.dataset.repeatNext || '0', 10);
            list.dataset.repeatNext = String(index + 1);
            const wrapper = document.createElement('div');
            wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
            const row = wrapper.firstElementChild;
            list.insertBefore(row, template);
            const first = row.querySelector('input:not([type="hidden"]), select');
            if (first) first.focus();
            return;
        }
        const remove = event.target.closest('[data-repeat-remove]');
        if (remove) remove.closest('[data-repeat-row]').remove();
        if (event.target.closest('[data-print-report]')) window.print();
    });
    document.querySelectorAll('select[data-auto-submit]').forEach((select) => {
        select.addEventListener('change', () => { if (select.value !== '' && select.form) select.form.submit(); });
    });
});
