-- Focus certificate submission indexes.

ALTER TABLE llx_fiscal_focus_certificate_submission ADD INDEX idx_fiscal_focus_cert_entity (entity);
ALTER TABLE llx_fiscal_focus_certificate_submission ADD INDEX idx_fiscal_focus_cert_company (fk_focus_company);
ALTER TABLE llx_fiscal_focus_certificate_submission ADD INDEX idx_fiscal_focus_cert_hash (certificate_hash);
ALTER TABLE llx_fiscal_focus_certificate_submission ADD INDEX idx_fiscal_focus_cert_cnpj (cnpj_certificado);
ALTER TABLE llx_fiscal_focus_certificate_submission ADD INDEX idx_fiscal_focus_cert_status (status);
