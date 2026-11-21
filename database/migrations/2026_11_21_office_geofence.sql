-- Office geofence: punches are only accepted within office_radius_m of the office.
-- Stays OFF until office_lat and office_lng are filled in (empty = no check).
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
  ('office_lat',         '',    'Office latitude for punch geofence (empty = geofence off)'),
  ('office_lng',         '',    'Office longitude for punch geofence (empty = geofence off)'),
  ('office_radius_m',    '100', 'Max distance in meters from the office to allow a punch'),
  ('gps_max_accuracy_m', '100', 'Reject punches whose GPS accuracy is worse than this (meters)');

-- Turn it on per environment (coordinates are not kept in the repo):
-- UPDATE system_settings SET setting_value = '<lat>' WHERE setting_key = 'office_lat';
-- UPDATE system_settings SET setting_value = '<lng>' WHERE setting_key = 'office_lng';