-- Sample test data for unit tests

-- Insert sample sensor data
INSERT INTO data (dev_id, dev_time, tempUL, tempDOM, pressure, inserted) VALUES
(1, '14:30:45', 22.5, 21.0, 1013.25, NOW()),
(1, '14:40:45', 22.3, 20.8, 1013.10, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(1, '14:50:45', 22.1, 20.6, 1012.95, DATE_SUB(NOW(), INTERVAL 10 MINUTE)),
(2, '14:35:22', 18.9, 19.5, 1012.80, NOW()),
(2, '14:45:22', 19.1, 19.3, 1012.70, DATE_SUB(NOW(), INTERVAL 5 MINUTE)),
(3, '14:32:10', 25.3, 24.5, 1011.50, NOW()),
(3, '14:42:10', 25.5, 24.7, 1011.40, DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- Verify config table has corrections
INSERT INTO config (param_name, param_data, comment) VALUES
('ULcorr', '0.5', 'Outside temperature correction'),
('DOMcorr', '-0.2', 'Inside temperature correction'),
('PRESScorr', '0.1', 'Pressure correction')
ON DUPLICATE KEY UPDATE param_data = VALUES(param_data);
