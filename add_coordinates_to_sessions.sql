-- Add latitude and longitude columns to sessions table for precise location mapping
ALTER TABLE sessions 
ADD COLUMN geo_lat DECIMAL(10, 8) DEFAULT NULL,
ADD COLUMN geo_lon DECIMAL(11, 8) DEFAULT NULL;

-- Add index for coordinate-based queries
CREATE INDEX idx_geo_coords ON sessions (geo_lat, geo_lon);
