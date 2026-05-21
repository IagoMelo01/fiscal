-- Focus NFe company indexes.

ALTER TABLE llx_fiscal_focus_company ADD INDEX idx_fiscal_focus_company_entity (entity);
ALTER TABLE llx_fiscal_focus_company ADD UNIQUE INDEX uk_fiscal_focus_company_cnpj (entity, environment, cnpj);
ALTER TABLE llx_fiscal_focus_company ADD UNIQUE INDEX uk_fiscal_focus_company_cpf (entity, environment, cpf);
ALTER TABLE llx_fiscal_focus_company ADD INDEX idx_fiscal_focus_company_focus_id (environment, focus_id);
ALTER TABLE llx_fiscal_focus_company ADD INDEX idx_fiscal_focus_company_status (status);
ALTER TABLE llx_fiscal_focus_company ADD INDEX idx_fiscal_focus_company_active (entity, environment, active);
