-- Migration: add category (UGC/PGC) to college_streams for structured
-- degree classification used by the College Creation wizard.
-- Column is NULLable so legacy rows (and the simpler colleges.php flow)
-- remain valid; backfill matches canonical degree names.

ALTER TABLE college_streams
  ADD COLUMN category ENUM('UGC', 'PGC') NULL DEFAULT NULL AFTER stream_name;

-- Backfill existing rows that exactly match canonical degree names.
UPDATE college_streams SET category = 'UGC' WHERE stream_name IN (
  'Bachelor of Arts (BA)', 'Bachelor of Science (BSc)', 'Bachelor of Commerce (BCom)',
  'Bachelor of Technology (BTech)', 'Bachelor of Engineering (BE)',
  'Bachelor of Business Administration (BBA)', 'Bachelor of Computer Applications (BCA)',
  'Bachelor of Pharmacy (BPharm)', 'Bachelor of Medicine, Bachelor of Surgery (MBBS)',
  'Bachelor of Dental Surgery (BDS)', 'Bachelor of Laws (LLB)', 'Bachelor of Education (BEd)',
  'Bachelor of Architecture (BArch)', 'Bachelor of Design (BDes)', 'Bachelor of Fine Arts (BFA)',
  'Bachelor of Hotel Management (BHM)', 'Bachelor of Social Work (BSW)',
  'Bachelor of Physiotherapy (BPT)', 'Bachelor of Nursing (BSc Nursing)',
  'Bachelor of Ayurvedic Medicine and Surgery (BAMS)',
  'Bachelor of Homeopathic Medicine and Surgery (BHMS)',
  'Bachelor of Veterinary Science and Animal Husbandry (BVSc & AH)',
  'Bachelor of Journalism and Mass Communication (BJMC)',
  'Bachelor of Library and Information Science (BLIS)',
  'Bachelor of Agricultural Science (BSc Agriculture)'
);

UPDATE college_streams SET category = 'PGC' WHERE stream_name IN (
  'Master of Arts (MA)', 'Master of Science (MSc)', 'Master of Commerce (MCom)',
  'Master of Technology (MTech)', 'Master of Engineering (ME)',
  'Master of Business Administration (MBA)', 'Master of Computer Applications (MCA)',
  'Master of Pharmacy (MPharm)', 'Master of Laws (LLM)', 'Master of Education (MEd)',
  'Master of Architecture (MArch)', 'Master of Design (MDes)', 'Master of Fine Arts (MFA)',
  'Master of Social Work (MSW)', 'Master of Public Health (MPH)',
  'Master of Journalism and Mass Communication (MJMC)',
  'Master of Library and Information Science (MLIS)',
  'Master of Engineering Management (MEM)', 'Master of Public Administration (MPA)',
  'Master of Social Sciences (MSS)', 'Master of Philosophy (MPhil)',
  'Master of Veterinary Science (MVSc)', 'Master of Agricultural Science (MSc Agriculture)',
  'Master of Physiotherapy (MPT)'
);