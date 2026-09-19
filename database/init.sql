CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NULL,
    name VARCHAR(255) NOT NULL,
    manufacturer VARCHAR(255) NULL,
    equipment_type VARCHAR(255) NULL,
    size VARCHAR(255) NULL,
    serial_number VARCHAR(255) NULL,
    purchase_date DATE NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    inspection_interval_days INT NULL,
    inspection_start_date DATE NULL,
    last_inspection_date DATE NULL,
    next_inspection_date DATE NULL,
    manufacturer_check_date DATE NULL,
    manufacturer_validity_days INT NULL,
    max_operating_days INT NULL,
    retired_at DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

CREATE TABLE IF NOT EXISTS document_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS equipment_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    category_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(255) NULL,
    file_size INT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES document_categories(id)
);

CREATE TABLE IF NOT EXISTS inspection_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspection_type VARCHAR(255) NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notification_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    reminder_30_days BOOLEAN DEFAULT FALSE,
    reminder_14_days BOOLEAN DEFAULT FALSE,
    reminder_7_days BOOLEAN DEFAULT FALSE,
    reminder_due BOOLEAN DEFAULT FALSE,
    reminder_retired BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO categories (name, slug, description) VALUES
('Gleitschirm', 'gleitschirm', 'Gleitschirme und zugehörige Ausrüstung'),
('Rettungsgerät', 'rettungsgeraet', 'Notfall- und Rettungsgeräte'),
('Gurtzeug', 'gurtzeug', 'Gurtzeuge und Tragesysteme'),
('Helm', 'helm', 'Helme und Kopfschutz'),
('Sonstiges', 'sonstiges', 'Weitere Ausrüstungsgegenstände');

INSERT INTO document_categories (name, slug, description) VALUES
('Kaufbeleg', 'kaufbeleg', 'Kauf- und Lieferdokumente'),
('Prüfprotokoll', 'pruefprotokoll', 'Prüfungs- und Wartungsnachweise'),
('Herstellerinfo', 'herstellerinfo', 'Herstellerunterlagen und Datenblätter'),
('Nachprüfung', 'nachpruefung', 'Hersteller- und Nachprüfungsdokumente'),
('Sonstiges', 'sonstiges', 'Weitere Unterlagen');
