CREATE DATABASE IF NOT EXISTS asterisk;
CREATE DATABASE IF NOT EXISTS asteriskcdrdb;
GRANT ALL PRIVILEGES ON `asterisk`.*      TO 'freepbxuser'@'%';
GRANT ALL PRIVILEGES ON `asteriskcdrdb`.* TO 'freepbxuser'@'%';
FLUSH PRIVILEGES;
