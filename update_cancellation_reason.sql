-- Add a dedicated cancellation reason field for admin-cancelled reservations.
ALTER TABLE reservations
ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL AFTER notes;

SELECT 'Cancellation reason column added successfully!' AS status;
