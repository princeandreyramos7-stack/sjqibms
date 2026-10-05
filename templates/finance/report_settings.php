<?php
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
// Financial Management printouts — EDITABLE TEMPLATE SETTINGS
// Change the text below to adjust the Monthly Financial Report and the printed transaction list. Keep the quotes and
// commas.
//   header_lines   : lines printed above the barangay name (centered).
//   barangay_name  : shown in bold under the header lines.
//   office         : office line under the barangay name ('' for none).
//   address        : shown under the office line.
//   logo           : image file shown at the top left (relative to the SJQIBMS folder); set to '' for no logo.
//   monthly_title  : title of the Monthly Financial Report.
//   list_title     : title of the printed (PDF) transaction list.
//   show_details   : true lists every collection and disbursement under the category totals; false prints totals only.
//   signatories    : signature blocks. Leave 'name' as '' to print a blank line that is signed and filled in by hand;
//                    type a name to print it under the line. 'position' is printed below the name.
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
return [
    'header_lines' => ['Republic of the Philippines', 'Province of Isabela', 'Municipality of Quirino'],
    'barangay_name' => 'BARANGAY SAN JOSE',
    'office' => 'Office of the Barangay Treasurer',
    'address' => 'San Jose, Quirino, Isabela',
    'logo' => 'assets/img/barangay-san-jose-logo.jpg',
    'monthly_title' => 'MONTHLY FINANCIAL REPORT',
    'list_title' => 'FINANCIAL TRANSACTIONS',
    'show_details' => true,
    'signatories' => [
        ['label' => 'Prepared by:', 'name' => '', 'position' => 'Barangay Treasurer'],
        ['label' => 'Noted by:', 'name' => '', 'position' => 'Punong Barangay'],
    ],
];
