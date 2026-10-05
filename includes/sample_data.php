<?php
declare(strict_types=1);

// ─────────────────────────────────────────────────────────────────────────────
// TEMPORARY SAMPLE DATA for UI testing of modules that are not built yet.
// Nothing here is stored in the database, counted in Dashboard statistics, or able to sign in.
// To remove it: delete this file and the sample-data block in module.php.
// ─────────────────────────────────────────────────────────────────────────────

// Status labels map to the existing badge colours: active (green), pending (amber), inactive (grey), moved (blue), deceased (muted purple).
function sample_data_modules(): array
{
    return [
        'projects' => [
            'intro' => 'Barangay development projects and programs.',
            'columns' => ['Project', 'Category', 'Budget', 'Start', 'Target End', 'Status'],
            'tones' => ['Planned' => 'pending', 'Ongoing' => 'moved', 'Completed' => 'active', 'Suspended' => 'inactive'],
            'rows' => [
                ['Purok 3 drainage improvement', 'Infrastructure', '₱450,000', 'Aug 01, 2026', 'Nov 30, 2026', 'Ongoing'],
                ['Solar streetlights – main road', 'Infrastructure', '₱380,000', 'Oct 01, 2026', 'Dec 15, 2026', 'Planned'],
                ['Community garden program', 'Agriculture', '₱60,000', 'Jun 15, 2026', 'Sep 15, 2026', 'Completed'],
                ['Farm-to-market road concreting', 'Infrastructure', '₱1,200,000', 'Jul 01, 2026', 'Jan 31, 2027', 'Ongoing'],
                ['Senior citizens wellness program', 'Health', '₱75,000', 'Sep 01, 2026', 'Dec 31, 2026', 'Ongoing'],
                ['Barangay hall repainting', 'Maintenance', '₱90,000', 'May 01, 2026', 'Jun 30, 2026', 'Completed'],
                ['Youth basketball league', 'Sports', '₱40,000', 'Oct 10, 2026', 'Nov 20, 2026', 'Planned'],
                ['Water system rehabilitation', 'Utilities', '₱650,000', 'Apr 01, 2026', 'Oct 31, 2026', 'Suspended'],
                ['Livelihood training – dressmaking', 'Livelihood', '₱55,000', 'Aug 15, 2026', 'Sep 30, 2026', 'Ongoing'],
                ['Day care center renovation', 'Education', '₱280,000', 'Mar 01, 2026', 'Jun 30, 2026', 'Completed'],
                ['CCTV installation – key areas', 'Peace & Order', '₱210,000', 'Nov 01, 2026', 'Dec 31, 2026', 'Planned'],
                ['Tree planting activity', 'Environment', '₱15,000', 'Jul 20, 2026', 'Jul 20, 2026', 'Completed'],
            ],
        ],
        'registrations' => [
            'intro' => 'Online resident and personnel registration applications.',
            'columns' => ['Application No.', 'Applicant', 'Type', 'Purok', 'Submitted', 'Status'],
            'tones' => ['Submitted' => 'pending', 'Verified' => 'moved', 'Awaiting Final Approval' => 'moved', 'Approved' => 'active', 'Rejected' => 'deceased', 'Cancelled' => 'inactive'],
            'rows' => [
                ['REG-2026-0112', 'Jessa Morales', 'Resident', 'Purok 1', 'Sep 24, 2026', 'Submitted'],
                ['REG-2026-0111', 'Paolo Estrada', 'Resident', 'Purok 4', 'Sep 23, 2026', 'Submitted'],
                ['REG-2026-0110', 'Kristine Yap', 'Resident', 'Purok 2', 'Sep 22, 2026', 'Verified'],
                ['REG-2026-0109', 'Nathaniel Cruz', 'Personnel', '—', 'Sep 21, 2026', 'Awaiting Final Approval'],
                ['REG-2026-0108', 'Aileen Dominguez', 'Resident', 'Purok 3', 'Sep 20, 2026', 'Approved'],
                ['REG-2026-0107', 'Ruel Hernandez', 'Resident', 'Purok 5', 'Sep 19, 2026', 'Rejected'],
                ['REG-2026-0106', 'Michelle Abad', 'Resident', 'Purok 6', 'Sep 18, 2026', 'Approved'],
                ['REG-2026-0105', 'Joshua Santiago', 'Resident', 'Purok 2', 'Sep 17, 2026', 'Verified'],
                ['REG-2026-0104', 'Camille Rosales', 'Personnel', '—', 'Sep 16, 2026', 'Approved'],
                ['REG-2026-0103', 'Vincent Lacson', 'Resident', 'Purok 1', 'Sep 15, 2026', 'Cancelled'],
                ['REG-2026-0102', 'Hazel Fajardo', 'Resident', 'Purok 4', 'Sep 14, 2026', 'Approved'],
                ['REG-2026-0101', 'Oscar Bernardo', 'Resident', 'Purok 3', 'Sep 13, 2026', 'Approved'],
            ],
        ],
        'settings' => [
            'intro' => 'System configuration and preferences.',
            'columns' => ['Setting', 'Group', 'Value', 'Updated By', 'Updated', 'Status'],
            'tones' => ['Enabled' => 'active', 'Disabled' => 'inactive'],
            'rows' => [
                ['Barangay name', 'General', 'Barangay San Jose', 'Super Admin', 'Sep 01, 2026', 'Enabled'],
                ['Municipality / Province', 'General', 'Quirino, Isabela', 'Super Admin', 'Sep 01, 2026', 'Enabled'],
                ['Office hours', 'General', 'Mon–Fri, 8:00 AM – 5:00 PM', 'Super Admin', 'Sep 01, 2026', 'Enabled'],
                ['Time zone', 'General', 'Asia/Manila', 'Super Admin', 'Sep 01, 2026', 'Enabled'],
                ['Admin Access Gate', 'Security', '6-digit code, 5 attempts', 'Super Admin', 'Sep 24, 2026', 'Enabled'],
                ['Session timeout', 'Security', '30 minutes', 'Super Admin', 'Sep 10, 2026', 'Enabled'],
                ['Two-factor authentication', 'Security', 'Email code', 'Super Admin', 'Sep 10, 2026', 'Disabled'],
                ['Dashboard auto-refresh', 'Dashboard', 'Every 30 seconds', 'Super Admin', 'Sep 22, 2026', 'Enabled'],
                ['Announcement notifications', 'Notifications', 'In-app bell', 'Super Admin', 'Sep 22, 2026', 'Enabled'],
                ['Push notifications', 'Notifications', 'Firebase Cloud Messaging', 'Super Admin', 'Sep 22, 2026', 'Disabled'],
                ['Clearance fee', 'Fees', '₱50.00', 'Barangay Treasurer', 'Aug 15, 2026', 'Enabled'],
                ['Database backup', 'Maintenance', 'Daily at 11:00 PM', 'Super Admin', 'Aug 01, 2026', 'Disabled'],
            ],
        ],
    ];
}

function sample_data_module(string $key): ?array
{
    return sample_data_modules()[$key] ?? null;
}
