ALTER TABLE rolls
  ADD COLUMN current_work_order_id BIGINT UNSIGNED NULL AFTER supplier_id,
  ADD KEY idx_rolls_current_wo_id (current_work_order_id),
  ADD CONSTRAINT fk_rolls_current_wo_id FOREIGN KEY (current_work_order_id) REFERENCES work_orders(id);

