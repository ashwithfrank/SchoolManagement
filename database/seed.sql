-- Reference data required by every installation (no personal or demo data here).

INSERT INTO roles (code, name) VALUES ('admin','Admin'), ('office','Office Staff'), ('teaching','Teaching Staff');

INSERT INTO classes (name, sort_order) VALUES
 ('LKG',1),('UKG',2),('1',3),('2',4),('3',5),('4',6),('5',7),('6',8),('7',9),('8',10),('9',11),('10',12);

INSERT INTO sections (class_id, name, is_active) SELECT id, 'A', 1 FROM classes;

INSERT INTO counters (name, value) VALUES ('receipt', 0), ('student', 0), ('employee', 0);

INSERT INTO app_settings (setting_key, setting_value) VALUES
 ('receipt_prefix','RCT'),
 ('receipt_footer','This is a computer generated receipt.'),
 ('teachers_all_marks','0'),
 ('expiry_warning_days','30'),
 ('photo_max_mb','5');
