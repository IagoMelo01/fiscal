-- Focus synchronization cursor indexes.

ALTER TABLE llx_fiscal_sync_cursor ADD INDEX idx_fiscal_sync_cursor_entity (entity);
ALTER TABLE llx_fiscal_sync_cursor ADD INDEX idx_fiscal_sync_cursor_company (fk_focus_company);
ALTER TABLE llx_fiscal_sync_cursor ADD UNIQUE INDEX uk_fiscal_sync_cursor (entity, environment, cnpj, service);
ALTER TABLE llx_fiscal_sync_cursor ADD INDEX idx_fiscal_sync_cursor_last_sync (last_sync);
