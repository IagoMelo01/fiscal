-- Focus NFe request log indexes.

ALTER TABLE llx_fiscal_focus_request_log ADD INDEX idx_fiscal_focus_log_entity (entity);
ALTER TABLE llx_fiscal_focus_request_log ADD INDEX idx_fiscal_focus_log_object (object_type, object_id);
ALTER TABLE llx_fiscal_focus_request_log ADD INDEX idx_fiscal_focus_log_endpoint (method, endpoint);
ALTER TABLE llx_fiscal_focus_request_log ADD INDEX idx_fiscal_focus_log_http_code (http_code);
ALTER TABLE llx_fiscal_focus_request_log ADD INDEX idx_fiscal_focus_log_date (date_request);
