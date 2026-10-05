-- ================================================================
-- PICS — Database Schema & Seed Data
-- Import this file in phpMyAdmin BEFORE running seed_users.php
-- ================================================================

CREATE DATABASE IF NOT EXISTS philtrack_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE philtrack_db;

-- Users
CREATE TABLE IF NOT EXISTS users (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  full_name   VARCHAR(150) NOT NULL,
  email       VARCHAR(150) NOT NULL UNIQUE,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('admin','user') NOT NULL DEFAULT 'user',
  department  VARCHAR(100) DEFAULT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Supply categories
CREATE TABLE IF NOT EXISTS categories (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(50)  DEFAULT 'fa-solid fa-box-open',
  description TEXT DEFAULT NULL
) ENGINE=InnoDB;

-- Inventory items
CREATE TABLE IF NOT EXISTS inventory_items (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  item_code        VARCHAR(50)  NOT NULL UNIQUE,
  item_name        VARCHAR(150) NOT NULL,
  category_id      INT          NOT NULL,
  description      TEXT,
  image            VARCHAR(255) DEFAULT NULL,
  quantity         INT          NOT NULL DEFAULT 0,
  unit             VARCHAR(20)  NOT NULL DEFAULT 'pcs',
  reorder_level    INT          NOT NULL DEFAULT 10,
  storage_location VARCHAR(150) DEFAULT NULL,
  date_added       DATE         DEFAULT NULL,
  date_updated     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Item requests (submitted by users)
CREATE TABLE IF NOT EXISTS item_requests (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  item_id            INT          NOT NULL,
  requester_id       INT          DEFAULT NULL,
  requester_name     VARCHAR(150) NOT NULL,
  department         VARCHAR(100) NOT NULL,
  quantity_requested INT          NOT NULL,
  purpose            TEXT,
  status             ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  date_requested     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  date_processed     TIMESTAMP    NULL DEFAULT NULL,
  processed_by       INT          DEFAULT NULL,
  remarks            VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (item_id)      REFERENCES inventory_items(id) ON DELETE CASCADE,
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Issuance records
CREATE TABLE IF NOT EXISTS issuance (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  request_id  INT          DEFAULT NULL,
  item_id     INT          NOT NULL,
  quantity    INT          NOT NULL,
  issued_to   VARCHAR(150) NOT NULL,
  department  VARCHAR(100) DEFAULT NULL,
  purpose     VARCHAR(255) DEFAULT NULL,
  issued_by   INT          DEFAULT NULL,
  date_issued TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (request_id) REFERENCES item_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (item_id)    REFERENCES inventory_items(id) ON DELETE CASCADE,
  FOREIGN KEY (issued_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Stock receiving log. Records each batch of stock received into inventory
-- (e.g. from the PhilHealth Regional Office). This is the source of truth
-- for the Monthly Report's "Received from PRO" column — recording a
-- receipt here is also what increases inventory_items.quantity
-- (see admin/inventory.php, "Receive Stock" action).
CREATE TABLE IF NOT EXISTS stock_receipts (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  item_id        INT          NOT NULL,
  quantity       INT          NOT NULL,
  source         VARCHAR(150) NOT NULL DEFAULT 'PRO',
  reference_no   VARCHAR(100) DEFAULT NULL,
  received_by    INT          DEFAULT NULL,
  date_received  DATE         NOT NULL,
  created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id)     REFERENCES inventory_items(id) ON DELETE CASCADE,
  FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- "Remember me" persistent login tokens. A selector identifies the row for
-- lookup; only a hash of the validator is stored, so a stolen DB row alone
-- can't be replayed as a cookie (see includes/auth.php).
CREATE TABLE IF NOT EXISTS remember_tokens (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT       NOT NULL,
  selector    CHAR(24)  NOT NULL UNIQUE,
  token_hash  CHAR(64)  NOT NULL,
  expires_at  TIMESTAMP NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Activity log (powers Reports > User Activity)
CREATE TABLE IF NOT EXISTS activity_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT          DEFAULT NULL,
  action     VARCHAR(255) NOT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Seed Data ────────────────────────────────────────────────

INSERT IGNORE INTO categories (name, icon, description) VALUES
  ('IT Supplies',
   'fa-solid fa-computer',
   'View and manage all IT equipment and supplies.'),

  ('Medical Supplies',
   'fa-solid fa-kit-medical',
   'View and manage all medical supplies and items.'),

  ('Office Supplies',
   'fa-solid fa-folder',
   'View and manage all office supplies and materials.'),

  ('Other Supplies',
   'fa-solid fa-box-open',
   'View and manage other supplies and miscellaneous items.');  

INSERT IGNORE INTO inventory_items
  (item_code, item_name, category_id, description, image, quantity, unit, reorder_level, storage_location, date_added)
VALUES
  ('OFF-001','Acrylic Insert Frame (A4 SIZE)',3,'A4-size acrylic insert frame for signage and document display.','Acrylic Insert Frame (A4 SIZE).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-002','Alcohol',3,'Disinfecting alcohol for general office sanitation.','Alcohol.jpg',0,'gal',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-003','Auto Supply Battery for Motor Vehichle',3,'Automotive battery for office service vehicle.','Auto Supply Battery for Motor Vehichle.jpg',0,'pcs',3,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-004','Ballpen (Black)',3,'Black ink ballpen for everyday office writing.','Ballpen (Black).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-005','Ballpen (Blue)',3,'Blue ink ballpen for everyday office writing.','Ballpen (Blue).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-006','Ballpen (with String)',3,'Ballpen with attached string for counter/reception use.','Ballpen (with string).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-007','Battery (1.5v)',3,'1.5V battery for office equipment and devices.','Battery (1.5v).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-008','Battery (2A)',3,'2A battery for office equipment and devices.','Battery (2A).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-009','Battery (3A)',3,'3A battery for office equipment and devices.','Battery (3A).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-010','Battery (3V)',3,'3V battery for office equipment and devices.','Battery (3v).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-011','Battery (9v)',3,'9V battery for office equipment and devices.','Battery (9v).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-012','Battery 2A Rechargeable',3,'Rechargeable 2A battery for reusable device power.','Battery 3A Rechargeable.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-013','Battery 3A Rechargeable',3,'Rechargeable 3A battery for reusable device power.','Battery 3A Rechargeable.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-014','Battery Charger',3,'Charger unit for rechargeable batteries.','Battery Charger.jpg',0,'pcs',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-015','Battery-UPS',3,'Backup battery for uninterruptible power supply units.','Battery-UPS.jpg',0,'box',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-016','Binder Clips (1")',3,'1-inch binder clips for document fastening.','Binder Clips 1.jpg',0,'box',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-017','Binder Clips (2")',3,'2-inch binder clips for document fastening.','Binder Clips 2.jpg',0,'box',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-018','Binder Clips -XS',3,'Extra-small binder clips for light document fastening.','Binder Clips -XS.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-019','Calculator',3,'Desktop calculator for office computations.','Calculator.jpg',0,'pcs',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-020','Carbon Paper (Short)',3,'Short-size carbon paper for duplicate document copies.','Carbon Paper (Short).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-021','Cash Box',3,'Lockable cash box for secure cash handling.','Cash Box.jpg',0,'pcs',3,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-022','Clear Book A4',3,'A4-size clear book for document organization and storage.','Clear Book A4.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-023','Clip Board',3,'Standard clipboard for holding papers during fieldwork.','Clip Board.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-024','Clip Board Long',3,'Long-size clipboard for holding legal-size documents.','Clip Board.jpg',0,'box',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-025','Clip Paper (Jumbo)',3,'Jumbo paper clips for bundling documents.','Clip Paper (Jumbo).jpg',0,'box',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-026','Clip Paper (Small)',3,'Small paper clips for bundling documents.','Clip Paper (Small).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-027','Compact Disc',3,'Blank compact disc for data storage and backup.','Compact Disc.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-028','Computer Gel Cleaner',3,'Gel cleaner for removing dust and debris from keyboards and equipment.','Computer Gel Cleaner.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-029','Copy Paper (A4)',3,'A4 bond paper for office printing and documentation.','Copy Paper (A4).jpg',0,'ream',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-030','Copy Paper (Executive)',3,'Executive-size bond paper for office printing and documentation.','Copy Paper (Executive).jpg',0,'ream',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-031','Copy Paper (Legal)',3,'Legal-size bond paper for office printing and documentation.','Copy Paper (Legal).jpg',0,'ream',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-032','Copy Paper (Short)',3,'Short-size bond paper for office printing and documentation.','Copy Paper (Short).webp',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-033','Cork Board (4x3)',3,'4x3 cork board for posting notices and announcements.','Cork Board (4x3).jpg',0,'pcs',3,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-034','Correction Tape',3,'Correction tape for editing printed or written documents.','Correction Tape.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-035','Corrugated Box',3,'Corrugated box for storage and packing of documents/items.','Corrugated Box.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-036','Data File Box',3,'File box for archiving and storing office documents.','Data File Box.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-037','Data File Folder',3,'File folder for organizing office documents.','Data Filer Folder.jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-038','Dater',3,'Adjustable date stamp for document processing.','Dater.webp',0,'pcs',3,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-039','Disinfectant Cleaner (Zonrox/Bleach)',3,'Disinfectant bleach cleaner for facility sanitation.','Disinfectant Cleaner (ZonroxBleach).png',0,'gal',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-040','Disinfectant Solution (Fogging) (1gal)',3,'Disinfectant solution used for fogging sanitation.','Disinfectant Solution (Fogging).webp',0,'box',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-041','Double Clips (Bulldog)',3,'Bulldog clips for holding large stacks of documents.','Double Clips (Bulldog).jpg',0,'pack',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-042','DTR',3,'Daily Time Record forms for employee attendance monitoring.','DTR.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-043','Envelope (Expanding long)',3,'Long expanding envelope for document storage.','Envelope (Expanding long).jpg',0,'bundle',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-044','Envelope (Expanding Short/A4)',3,'Short/A4 expanding envelope for document storage.','Envelope (Expanding ShortA4) - Copy.jpg',0,'bundle',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-045','Envelope Documentary (Long)',3,'Long documentary envelope for official correspondence.','Envelope Documentary (Long).jpg',0,'bundle',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-046','Envelope Documentary (Short)',3,'Short documentary envelope for official correspondence.','Envelope Documentary (Short).jpg',0,'box',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-047','Envelope Mailing (White#10)',3,'White #10 mailing envelope for correspondence.','Envelope Mailing (White #10).jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-048','Fastener',3,'Paper fastener for binding documents.','Fastener.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-049','Folder (A4)',3,'A4-size folder for document organization.','Folder (A4).webp',0,'bundle',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-050','Folder (Long)',3,'Long-size folder for document organization.','Folder (Long).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-051','Folder (Short)',3,'Short-size folder for document organization.','Folder (Short).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-052','Glue',3,'All-purpose glue for office use.','Glue.jpg',0,'gal',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-053','Glue (Jar)',3,'Jar of all-purpose glue for office use.','Glue (Jar).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-054','Gun Tucker',3,'Staple/tacker gun for fastening materials.','Gun Tucker.jpg',0,'pcs',3,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-055','Hand Sanitizer (1Gal)',3,'Hand sanitizer for personal and facility hygiene.','Hand Sanitizer (1Gal).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-056','ID Holder',3,'ID holder for employee identification cards.','ID Holder.jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-057','Index Card (3 x 5)',3,'3x5 index card for note-taking and records.','Index Card (3 x 5).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-058','Index Card (5 x 8)',3,'5x8 index card for note-taking and records.','Index Card (5 x 8).jpg',0,'pack',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-059','Laminating Film (A4)',3,'A4-size laminating film for document protection.','Laminating Film (A4).webp',0,'box',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-060','Laminating Film 250 Micron',3,'250-micron laminating film for document protection.','Laminating Film 250 Micron.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-061','Led Bulb (11watts)',3,'11-watt LED bulb for office lighting.','Led Bulb (11watts).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-062','Led Bulb (18watts)',3,'18-watt LED bulb for office lighting.','Led Bulb (18watts).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-063','Marker (Flourescent)',3,'Fluorescent highlighter marker for document annotation.','Marker (Flourescent).avif',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-064','Marker (Permanent Black)',3,'Permanent black marker for labeling and writing.','Marker (Permanent Black).webp',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-065','Marker (Permanent Blue)',3,'Permanent blue marker for labeling and writing.','Marker (Permanent Blue).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-066','Marker (Permanent Red)',3,'Permanent red marker for labeling and writing.','Marker (Permanent Red).png',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-067','Marker (Whiteboard Black)',3,'Black whiteboard marker for presentations and notes.','Marker (Whiteboard Black).webp',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-068','Marker (Whiteboard Red)',3,'Red whiteboard marker for presentations and notes.','Marker (Whiteboard Red).webp',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-069','Parchment Paper (A4)',3,'A4 parchment paper for certificates and formal documents.','Parchment Paper (A4).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-070','Pencil',3,'Standard pencil for office writing.','Pencil.jpg',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-071','Pencil Sharpener',3,'Pencil sharpener for office use.','Pencil Sharpener.avif',0,'pcs',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-072','Photopaper',3,'Photo paper for printing photographs and images.','Photopaper.jpg',0,'pack',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-073','Post it Small Flags (Sign Here)',3,'Small sign-here flags for marking signature points on documents.',NULL,0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-074','Puncher',3,'Paper puncher for hole-punching documents.','Puncher.jpg',0,'box',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-075','Push Pin',3,'Push pins for posting notices on boards.','Push Pin.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-076','RJ 45',3,'RJ45 network connector for cabling.','RJ 45.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-077','Rubber Band - Big',3,'Large rubber bands for bundling documents.','Rubber Band – Big.webp',0,'box',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-078','Rubber Band - Small',3,'Small rubber bands for bundling documents.','Rubber Band – Small.jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-079','Scissors',3,'Standard scissors for office cutting tasks.','Scissors.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-080','Sign Pen (Black)',3,'Black sign pen for signing documents.','Sign Pen (Black).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-081','Sign Pen (Blue)',3,'Blue sign pen for signing documents.','Sign Pen (Blue).jpg',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-082','Stamp (Self-inking)',3,'Self-inking stamp for document processing.',NULL,0,'pcs',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-083','Stamp Pad',3,'Stamp pad for use with rubber stamps.','Stamp Pad.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-084','Stamp Pad Ink (Black)',3,'Black ink refill for stamp pads.','Stamp Pad Ink (Black).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-085','Stamp Pad Ink (Blue)',3,'Blue ink refill for stamp pads.','Stamp Pad Ink (Blue).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-086','Stamp Pad Ink (Red)',3,'Red ink refill for stamp pads.','Stamp Pad Ink (Red).jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-087','Stamp Pad Ink (Violet)',3,'Violet ink refill for stamp pads.','Stamp Pad Ink (Violet).webp',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-088','Staple Wire',3,'Staple wire refill for staplers.','Staple Wire.jpg',0,'box',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-089','Staples',3,'Staples for document fastening.','Staples.webp',0,'pcs',20,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-090','Sticker Paper',3,'Sticker paper for printing labels and stickers.','Sticker Paper.jpg',0,'pcs',10,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-091','Stick-on-Pad 2"x2"',3,'2x2 inch adhesive notepad for reminders and notes.','Stick-on-Pad 2x2.webp',0,'pcs',15,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-092','Tape Dispenser',3,'Tape dispenser for office desk use.','Tape Dispenser.jpg',0,'pcs',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-093','Twine',3,'Twine for tying and bundling documents/materials.',NULL,0,'roll',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-094','WD 40',3,'WD-40 lubricant spray for maintenance and repairs.',NULL,0,'pcs',5,'Office Supplies Cabinet','2026-08-14'),
  ('OFF-095','Wiper Blade',3,'Vehicle wiper blade for maintenance of office service vehicles.','Wiper Blade.png',0,'pcs',3,'Office Supplies Cabinet','2026-08-14'),
  ('IT-001','Drum Ctrg, Fujifilm, Docucentre 3065',1,'Drum cartridge for Fujifilm Docucentre 3065 copier.','Drum Cartridge, Fujifilm DocuCentre 3065.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-002','Drum for Copier - Lexmark 77L0ZK0',1,'Drum unit for Lexmark 77L0ZK0 copier.','Drum for Copier - Lexmark 77L0ZK0.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-003','Drum Kit, Lexmark SX58D0Z00',1,'Drum kit for Lexmark SX58D0Z00 printer.','Drum Kit, Lexmark SX58D0Z00.jpeg',0,'pcs',3,'IT Cabinet','2026-08-14'),
  ('IT-004','Epson LX 310',1,'Epson LX 310 dot matrix printer/ribbon supply.','Epson LX 310.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-005','Fuji Xerox Toner - Docucentre',1,'Toner cartridge for Fuji Xerox Docucentre copier.','Fuji Xerox Toner.jpeg',0,'pcs',3,'IT Cabinet','2026-08-14'),
  ('IT-006','HDMI Cable 15m',1,'15-meter HDMI cable for video/audio connections.','HDMI Cable 15m.jpeg',0,'pcs',3,'IT Cabinet','2026-08-14'),
  ('IT-007','HDMI Cable 10m',1,'10-meter HDMI cable for video/audio connections.','HDMI Cable 10m.jpeg',3,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-008','HP 147A',1,'HP 147A toner cartridge for HP LaserJet printers.','HP 147A.jpeg',1,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-009','HP 37A',1,'HP 37A toner cartridge for HP LaserJet printers.','HP 37A.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-010','HP Ink Advantage 680 (Black)',1,'Black ink cartridge for HP Ink Advantage 680 printers.','HP Ink Advantage 680 (Black).jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-011','HP Ink Advantage 680 (Colored)',1,'Colored ink cartridge for HP Ink Advantage 680 printers.','HP Ink Advantage 680 (Colored).jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-012','HP Laserjet 202A (BLACK)',1,'Black toner cartridge for HP LaserJet 202A printers.','HP LaserJet 202A (Black).jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-013','HP Laserjet 202A (CYAN)',1,'Cyan toner cartridge for HP LaserJet 202A printers.','HP LaserJet 202A (Cyan).jpeg',3,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-014','HP Laserjet 202A (MAGENTA)',1,'Magenta toner cartridge for HP LaserJet 202A printers.','HP LaserJet 202A (Magenta).jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-015','HP Laserjet 202A (YELLOW)',1,'Yellow toner cartridge for HP LaserJet 202A printers.','HP LaserJet 202A (Yellow).jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-016','HP Laserjet 79A BLACK',1,'Black toner cartridge for HP LaserJet 79A printers.','HP LaserJet 79A Black.jpeg',2,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-017','HP Laserjet 83A',1,'Toner cartridge for HP LaserJet 83A printers.','HP LaserJet 83A.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-018','Imaging, Lexmark SX58D0Z00',1,'Imaging unit for Lexmark SX58D0Z00 printer.','Imaging, Lexmark SX58D0Z00.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-019','Lexmark Toner Cartridge 58D3H00',1,'Toner cartridge for Lexmark 58D3H00 printers.','Lexmark Toner Cartridge 58D3H00.png',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-020','Toner for Copier - Lexmark 77L0ZK1',1,'Toner cartridge for Lexmark 77L0ZK1 copier.','Toner for Copier - Lexmark 77L0ZK1.jpeg',0,'cartridge',3,'IT Cabinet','2026-08-14'),
  ('IT-021','USB Bluetooth Dongle',1,'USB Bluetooth dongle for wireless device connectivity.','USB Bluetooth Dongle.jpeg',0,'pcs',5,'IT Cabinet','2026-08-14'),
  ('MED-001','Adalat GITS 30- Nifedipine 30mg',2,'Nifedipine 30mg tablet for hypertension management.','IMG_3125.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-002','Adhesive Bandage',2,'Adhesive bandage for minor wound care.','IMG_3126.PNG',0,'pcs',20,'Medical Cabinet','2026-08-14'),
  ('MED-003','Alcohol 70%',2,'70% alcohol solution for disinfection.','IMG_3127.PNG',0,'gal',15,'Medical Cabinet','2026-08-14'),
  ('MED-004','Aluminum Hydroxide Magnesium Hydroxide-Calmsaph-200mg',2,'Antacid tablet for relief of hyperacidity.','IMG_3128.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-005','Amlodipine Desilate (10mg)',2,'Amlodipine 10mg tablet for hypertension management.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-006','Anti-anginal Isosorbide Dinitrate (10 mg)',2,'Isosorbide dinitrate 10mg tablet for angina management.','IMG_3140.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-007','Automatic Blood Pressure Monitor',2,'Digital automatic blood pressure monitoring device.',NULL,0,'pcs',3,'Medical Cabinet','2026-08-14'),
  ('MED-008','Batahistine Dihydrochloride (16 mg)',2,'Betahistine dihydrochloride 16mg tablet for vertigo relief.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-009','Betahistine Hydrochloride',2,'Betahistine hydrochloride tablet for vertigo relief.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-010','Captopril (25mg)',2,'Captopril 25mg tablet for hypertension management.','IMG_3129.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-011','Celecoxib (Pain reliever)',2,'Celecoxib tablet for pain and inflammation relief.','IMG_3130.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-012','Chlorphenamine Maleate (4 mg)',2,'Chlorphenamine maleate 4mg tablet for allergy relief.','IMG_3131.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-013','Cinnarizine (25mg)',2,'Cinnarizine 25mg tablet for vertigo and circulation support.','IMG_3132.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-014','Clonidine Hydrochloride-Ritemed 75mg',2,'Clonidine hydrochloride 75mg tablet for hypertension management.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-015','Clonidine Hydrocloride (75 mg)',2,'Clonidine hydrochloride 75mg tablet for hypertension management.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-016','Cotton Balls',2,'Cotton balls for wound cleaning and first aid.',NULL,0,'pack',15,'Medical Cabinet','2026-08-14'),
  ('MED-017','Cottton Buds',2,'Cotton buds for cleaning and first aid use.',NULL,0,'tube',15,'Medical Cabinet','2026-08-14'),
  ('MED-018','Dual head Stethoscope',2,'Dual head stethoscope for patient examination.',NULL,0,'pcs',3,'Medical Cabinet','2026-08-14'),
  ('MED-019','Elastic Bandage (4x5 yards)',2,'Elastic bandage for wound support and wrapping.','IMG_3133.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-020','Facemask',2,'Disposable facemask for protective and medical use.','IMG_3134.PNG',0,'box',15,'Medical Cabinet','2026-08-14'),
  ('MED-021','Finger Tip Pulse Oximeter',2,'Fingertip pulse oximeter for oxygen saturation monitoring.','IMG_3135.PNG',0,'pcs',3,'Medical Cabinet','2026-08-14'),
  ('MED-022','Gauge Sponge',2,'Gauze sponge for wound dressing.','IMG_3136.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-023','Gauze Pad',2,'Gauze pad for wound dressing.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-024','Gloves',2,'Disposable gloves for medical and protective use.',NULL,0,'pcs',20,'Medical Cabinet','2026-08-14'),
  ('MED-025','Hydrocortisone (10 mh/g cream)',2,'Hydrocortisone cream for skin inflammation relief.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-026','Hydrogen Peroxide ( 120 ml)',2,'Hydrogen peroxide solution for wound cleaning.',NULL,0,'bottle',15,'Medical Cabinet','2026-08-14'),
  ('MED-027','Hyoscine N-butylbromide (10mg)',2,'Hyoscine N-butylbromide 10mg tablet for stomach cramp relief.','IMG_3137.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-028','Hyoscine N-Butylbromide Zolnex-10mg',2,'Hyoscine N-butylbromide 10mg (Zolnex) tablet for stomach cramp relief.','IMG_3137.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-029','Ibuprofen 200mg',2,'Ibuprofen 200mg tablet for pain and fever relief.','IMG_3138.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-030','Icebag Rubberized 6''',2,'6-inch rubberized icebag for cold therapy.','IMG_3139.PNG',0,'pcs',5,'Medical Cabinet','2026-08-14'),
  ('MED-031','Isorbinate Dinitrate',2,'Isosorbide dinitrate tablet for angina management.','IMG_3140.PNG',0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-032','Kelly Torcep',2,'Kelly forceps for clinical/first aid procedures.',NULL,0,'pcs',3,'Medical Cabinet','2026-08-14'),
  ('MED-033','Kidney Basin',2,'Kidney basin for medical and clinical use.',NULL,0,'pcs',5,'Medical Cabinet','2026-08-14'),
  ('MED-034','Led Camping Lamp',2,'LED camping lamp for emergency lighting.',NULL,0,'pcs',3,'Medical Cabinet','2026-08-14'),
  ('MED-035','Loperamide HCl (2mg)',2,'Loperamide 2mg tablet for diarrhea relief.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('MED-036','Losartan',2,'Losartan tablet for hypertension management.',NULL,0,'pcs',15,'Medical Cabinet','2026-08-14'),
  ('OTH-001','Cash Book',4,'Cash book for recording financial transactions.','Cash Book.jpg',0,'pcs',5,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-002','Flag',4,'Philippine flag for office display.','Flag.jpg',0,'bundle',2,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-003','Flag Pole',4,'Pole for mounting and displaying flags.','Flag pole.jpg',0,'bundle',2,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-004','Record Book',4,'Record book for logging transactions and entries.','Record Book.jpg',0,'box',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-005','Duct Tape',4,'Duct tape for general repair and sealing.','Duct Tape.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-006','Electric Tape',4,'Electrical insulation tape for wiring and repairs.','Electrical Tape.webp',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-007','Tape (Double Adhesive With Foam 1")',4,'1-inch double-sided foam adhesive tape for mounting.','Tape (Double Adhesive With Foam) — 1 & 2.jpg',0,'pcs',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-008','Tape (Double Adhesive With Foam 2")',4,'2-inch double-sided foam adhesive tape for mounting.','Tape (Double Adhesive With Foam) — 1 & 2.jpg',0,'roll',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-009','Tape (Double Adhesive Without Foam 1")',4,'1-inch double-sided adhesive tape without foam.','Tape (Double Adhesive Without Foam) — 1 & 2.jpg',0,'roll',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-010','Tape (Double Adhesive without Foam 2")',4,'2-inch double-sided adhesive tape without foam.','Tape (Double Adhesive Without Foam) — 1 & 2.jpg',0,'roll',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-011','Tape (Masking 1")',4,'1-inch masking tape for general office use.','Tape (Masking) — 1 & 2.jpg',0,'roll',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-012','Tape (Masking 2")',4,'2-inch masking tape for general office use.','Tape (Masking) — 1 & 2.jpg',0,'roll',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-013','Tape (Packaging) (2 x 60m)',4,'2-inch by 60m packaging tape for sealing boxes.','Tape (Packaging) — 2 x 60m.jpg',0,'roll',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-014','Tape (Scotch) (2 x 60m)',4,'2-inch by 60m scotch tape for general use.','Scotch Sticky Tape 502 24mm x 66M Pack 6.jpg',0,'roll',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-015','Tape (Scotch)( 1 x 60 m)',4,'1-inch by 60m scotch tape for general use.','Tape (Scotch) 1 x 60m.jpg',0,'roll',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-016','Thermal Paper',4,'Thermal paper for receipt and label printers.','Thermal Paper.jpg',0,'pcs',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-017','Iwaksi flyers',4,'Printed promotional flyers for Iwaksi campaign distribution.','Iwaksi flyers.jpeg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-018','Konsulta - Package',4,'Printed marketing materials for Konsulta package promotion.','Konsulta - Package.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-019','Konsulta - Provider',4,'Printed marketing materials for Konsulta provider promotion.','Konsulta - Provider_.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-020','Miyembro Benepisyo',4,'Printed materials on member benefits for distribution.','Miyembro Benepisyo.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-021','Member Portal - Record',4,'Printed materials for member portal record services.','Member Portal - Record.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-022','Member Portal - Online Premium',4,'Printed materials for member portal online premium services.','Member Portal Online - Premium.png',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-023','Brochure (eGov)',4,'Printed brochures promoting eGov services.','Brochure (eGOV).jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-024','OFW',4,'Printed marketing materials for OFW membership program.','OFW.PNG',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-025','Employees w/ Formal Employment',4,'Printed marketing materials for employed member coverage program.','Employees w_ Formal Employment.jpg',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-026','CSM FORM (Survey Form)',4,'Customer satisfaction measurement survey forms.','CSM FORM (Survey Form).png',0,'bundle',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-027','UHC 4M',4,'Universal Health Care 4Ps/Marginalized program promotional material.','UHC 4M.png',0,'pcs',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-028','Mug new',4,'Promotional mugs for member and public giveaways.','Mug new.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-029','ID',4,'Blank ID forms/materials for member identification processing.','ID.jpg',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-030','PKRF',4,'PhilHealth Konsulta Registration Form for member enrollment.','PKRF.png',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-031','PMRF',4,'PhilHealth Member Registration Form for member enrollment.','PMRF.jpg',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-032','Request Form',4,'General request forms for member transactions.','Request Form.jpg',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-033','E-PAR',4,'Electronic Premium Adjustment Request forms.','E-PAR.jpg',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-034','FPE',4,'Field Personnel/Enrollment forms for member processing.','FPE.jpg',0,'ream',15,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-035','Payment Slip',4,'Payment slips for premium contribution transactions.','Payment Slip.jpg',0,'pcs',20,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-036','T-shirt',4,'Promotional t-shirts for member and public giveaways.','T-shirt.jpg',0,'pcs',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-037','Katsa Bag/Tote Bag',4,'Promotional katsa/tote bags for member and public giveaways.','Katsa Bag.jpg',0,'bundle',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-038','Hand Fan',4,'Promotional hand fans for member and public giveaways.','Hand Fan.jpg',0,'pcs',10,'Other Supplies Cabinet','2026-08-14'),
  ('OTH-039','Cap',4,'Promotional caps for member and public giveaways.','Cap.jpg',0,'pcs',10,'Other Supplies Cabinet','2026-08-14');
  


