-- Received NF-e indexes.

ALTER TABLE llx_fiscal_nfe_received ADD INDEX idx_fiscal_nfe_received_entity (entity);
ALTER TABLE llx_fiscal_nfe_received ADD INDEX idx_fiscal_nfe_received_company (fk_focus_company);
ALTER TABLE llx_fiscal_nfe_received ADD UNIQUE INDEX uk_fiscal_nfe_received_chave (entity, cnpj_destinatario, chave_nfe);
ALTER TABLE llx_fiscal_nfe_received ADD INDEX idx_fiscal_nfe_received_cnpj (cnpj_destinatario);
ALTER TABLE llx_fiscal_nfe_received ADD INDEX idx_fiscal_nfe_received_emitente (documento_emitente);
ALTER TABLE llx_fiscal_nfe_received ADD INDEX idx_fiscal_nfe_received_versao (entity, cnpj_destinatario, versao);
ALTER TABLE llx_fiscal_nfe_received ADD INDEX idx_fiscal_nfe_received_manifestacao (manifestacao_destinatario);
