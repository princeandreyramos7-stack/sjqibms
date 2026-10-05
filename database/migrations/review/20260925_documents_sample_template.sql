-- ============================================================================================
-- SJQIBMS — DEVELOPMENT-ONLY sample Barangay Clearance template
-- STATUS: REVIEW ONLY. NOT IMPORTED. Import only after 20260925_documents_management.sql and explicit approval.
-- The sample is stored as a DRAFT with is_development_sample = 1. Application code must never approve, feature,
-- or issue development samples. The same content is shipped as templates/documents/sample_barangay_clearance.html.
-- ============================================================================================

INSERT INTO document_types (name, name_normalized, description, is_active)
SELECT 'Barangay Clearance', 'barangay clearance', NULL, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM document_types WHERE name_normalized = 'barangay clearance');

INSERT INTO document_templates (document_type_id, version_no, title, body, page_size, margin_mm, status, is_development_sample)
SELECT t.id,
       COALESCE((SELECT MAX(version_no) FROM document_templates WHERE document_type_id = t.id), 0) + 1,
       'Barangay Clearance — Development Sample',
       '<header class="doc-header"><p>Republic of the Philippines</p><p>Province of Isabela</p><p>Municipality of Quirino</p><p class="doc-barangay">BARANGAY SAN JOSE</p></header><h1 class="doc-title">BARANGAY CLEARANCE</h1><section class="doc-body"><p class="doc-salutation">TO WHOM IT MAY CONCERN:</p><p>This sample document is prepared for <strong>{{resident_full_name}}</strong>, whose recorded address is {{resident_address}}.</p><p>The stated purpose of this request is <strong>{{document_purpose}}</strong>.</p><p>This document is a development sample only and does not constitute an officially issued barangay clearance.</p></section><table class="doc-meta"><tr><td>Date:</td><td>{{document_date}}</td></tr><tr><td>Reference Number:</td><td>{{request_reference}}</td></tr></table><section class="doc-signature"><div class="doc-signature-line"></div><p class="doc-signatory-name">{{authorized_signatory_name}}</p><p class="doc-signatory-position">{{authorized_signatory_position}}</p><p class="doc-signature-note">Physical signature required.</p></section><footer class="doc-footer">SAMPLE DOCUMENT — NOT VALID FOR OFFICIAL ISSUANCE</footer>',
       'A4', 20.0, 'draft', 1
FROM document_types t WHERE t.name_normalized = 'barangay clearance';
