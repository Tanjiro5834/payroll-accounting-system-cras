-- Late rule: 10 minutes counts as late (was a 15-minute grace).
-- Only changes the value if it is still the old default, so a custom value is kept.
UPDATE system_settings SET setting_value = '10'
WHERE setting_key = 'late_threshold' AND setting_value = '15';

INSERT IGNORE INTO system_settings (setting_key, setting_value, description)
VALUES ('late_threshold', '10', 'Minutes after the scheduled start that count as late');
