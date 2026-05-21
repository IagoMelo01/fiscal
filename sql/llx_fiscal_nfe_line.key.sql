-- Fiscal issued NF-e item indexes.

ALTER TABLE llx_fiscal_nfe_line ADD INDEX idx_fiscal_nfe_line_fk_nfe (fk_nfe);
ALTER TABLE llx_fiscal_nfe_line ADD INDEX idx_fiscal_nfe_line_fk_product (fk_product);
ALTER TABLE llx_fiscal_nfe_line ADD UNIQUE INDEX uk_fiscal_nfe_line_item (fk_nfe, numero_item);
ALTER TABLE llx_fiscal_nfe_line ADD INDEX idx_fiscal_nfe_line_ncm (ncm);
ALTER TABLE llx_fiscal_nfe_line ADD INDEX idx_fiscal_nfe_line_cfop (cfop);
