<?php
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
// Relief & Assistance — Acknowledgment printout — EDITABLE TEMPLATE SETTINGS
// Change the text below to adjust the printed Acknowledgment Receipt. Keep the quotes and commas.
//   header_lines   : lines printed above the barangay name (centered).
//   barangay_name  : shown in bold under the header lines.
//   office         : office line under the barangay name ('' for none).
//   address        : shown under the office line.
//   logo           : image file shown at the top left (relative to the SJQIBMS folder); set to '' for no logo.
//   title          : title of the printout.
//   statement      : sentence printed above the signatures ('' for none).
//   noted_by       : the "Noted by" signature block. Leave 'name' as '' to print a blank line that is signed and
//                    filled in by hand; type a name to print it under the line.
// "Received by" prints the name of the person who received the assistance; "Prepared by" prints the name of the user
// who recorded it. Signatures are always signed by hand (no digital signature).
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────
return [
    'header_lines' => ['Republic of the Philippines', 'Province of Isabela', 'Municipality of Quirino'],
    'barangay_name' => 'BARANGAY SAN JOSE',
    'office' => 'Office of the Punong Barangay',
    'address' => 'San Jose, Quirino, Isabela',
    'logo' => 'assets/img/barangay-san-jose-logo.jpg',
    'title' => 'ACKNOWLEDGMENT RECEIPT',
    'statement' => 'I acknowledge that I received the assistance described above from Barangay San Jose.',
    'noted_by' => ['name' => '', 'position' => 'Punong Barangay'],
];
