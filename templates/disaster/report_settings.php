<?php
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
// Disaster Management printouts — EDITABLE TEMPLATE SETTINGS
// Change the text below to adjust the printed Disaster Management lists and reports. Keep the quotes and commas.
//   header_lines    : lines printed above the barangay name (centered).
//   barangay_name   : shown in bold under the header lines.
//   office          : office line under the barangay name.
//   address         : shown under the office line.
//   logo            : image file shown at the top left (relative to the SJQIBMS folder); set to '' for no logo.
//   records_title   : title of the printable DRR Records list (Export PDF).
//   incident_title  : title of the printable Incident Report.
//   incident_intro  : optional sentence printed under the Incident Report title; set to '' for none.
//   signatories     : signature blocks of the Incident Report. Leave 'name' as '' to print a blank line that is signed
//                     and filled in by hand; type a name to print it under the line. 'position' is printed below.
// ─────────────────────────────────────────────────────────────────────────────────────────────────────────────
return [
    'header_lines' => ['Republic of the Philippines', 'Province of Isabela', 'Municipality of Quirino'],
    'barangay_name' => 'BARANGAY SAN JOSE',
    'office' => 'Barangay Disaster Risk Reduction and Management Committee',
    'address' => 'San Jose, Quirino, Isabela',
    'logo' => 'assets/img/barangay-san-jose-logo.jpg',
    'records_title' => 'DISASTER RISK REDUCTION RECORDS',
    'incident_title' => 'INCIDENT REPORT',
    'incident_intro' => '',
    'signatories' => [
        ['label' => 'Prepared by:', 'name' => '', 'position' => 'BDRRMC'],
        ['label' => 'Noted by:', 'name' => '', 'position' => 'Punong Barangay'],
    ],
];
