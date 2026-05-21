-- Fiscal issued NF-e indexes.

ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_entity (entity);
ALTER TABLE llx_fiscal_nfe ADD UNIQUE INDEX uk_fiscal_nfe_ref (entity, ref);
ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_fk_soc (fk_soc);
ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_fk_project (fk_project);
ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_fk_focus_company (fk_focus_company);
ALTER TABLE llx_fiscal_nfe ADD UNIQUE INDEX uk_fiscal_nfe_focus_ref (entity, focus_environment, focus_ref);
ALTER TABLE llx_fiscal_nfe ADD UNIQUE INDEX uk_fiscal_nfe_chave (entity, chave_nfe);
ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_status (status);
ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_status_focus (status_focus);
ALTER TABLE llx_fiscal_nfe ADD INDEX idx_fiscal_nfe_status_sefaz (status_sefaz);
