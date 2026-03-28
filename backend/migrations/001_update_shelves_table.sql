-- Migration: Change Shelves table structure from total counts to individual entries
-- Date: 2026-01-10

ALTER TABLE Shelves 
  DROP COLUMN total_compartments,
  DROP COLUMN total_subcompartments,
  ADD COLUMN compartment INT NOT NULL AFTER shelf_id,
  ADD COLUMN subcompartment INT NOT NULL AFTER compartment;

-- Create unique constraint to prevent duplicate shelf-compartment-subcompartment entries
ALTER TABLE Shelves 
  ADD UNIQUE KEY unique_shelf_location (shelf_id, compartment, subcompartment);

-- Verify the new structure
-- DESCRIBE Shelves;
