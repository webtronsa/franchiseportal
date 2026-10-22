-- My Franchise details, one row per franchise (keyed by inastix company_uuid).
CREATE TABLE IF NOT EXISTS franchise_details (
  company_uuid VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(255) NULL,
  contact_name VARCHAR(255) NULL,
  phone VARCHAR(64) NULL,
  email VARCHAR(255) NULL,
  address TEXT NULL,
  town VARCHAR(255) NULL,
  facebook_url VARCHAR(500) NULL,
  updated_by VARCHAR(255) NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) DEFAULT CHARSET = utf8mb4;
