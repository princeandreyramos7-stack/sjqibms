<?php
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
// Inventory Report — EDITABLE TEMPLATE SETTINGS
// Change the text below to adjust the printed Inventory Report and the item label sheet. Keep the quotes and commas.
//   header_lines   : lines printed above the report title (centered).
//   barangay_name  : shown in bold under the header lines and on each item label.
//   address        : shown under the barangay name.
//   logo           : image file shown at the top left (relative to the SJQIBMS folder); set to '' for no logo.
//   title          : report title.
//   signatories    : one block per signature line. Leave 'name' and 'position' as '' to print a blank line that is
//                    filled in by hand; type a name and position to print them under the line.
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
return [
    'header_lines' => ['Republic of the Philippines', 'Province of Isabela', 'Municipality of Quirino'],
    'barangay_name' => 'BARANGAY SAN JOSE',
    'address' => 'San Jose, Quirino, Isabela',
    'logo' => 'assets/img/barangay-san-jose-logo.jpg',
    'title' => 'INVENTORY REPORT',
    'signatories' => [
        ['label' => 'Prepared by:', 'name' => '', 'position' => ''],
        ['label' => 'Checked by:', 'name' => '', 'position' => ''],
        ['label' => 'Approved by:', 'name' => '', 'position' => ''],
    ],
];
