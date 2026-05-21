-- Received NF-e manifestation history indexes.

ALTER TABLE llx_fiscal_nfe_received_manifestation ADD INDEX idx_fiscal_received_manifest_entity (entity);
ALTER TABLE llx_fiscal_nfe_received_manifestation ADD INDEX idx_fiscal_received_manifest_nfe (fk_nfe_received);
ALTER TABLE llx_fiscal_nfe_received_manifestation ADD INDEX idx_fiscal_received_manifest_chave (chave_nfe);
ALTER TABLE llx_fiscal_nfe_received_manifestation ADD INDEX idx_fiscal_received_manifest_tipo (tipo);
