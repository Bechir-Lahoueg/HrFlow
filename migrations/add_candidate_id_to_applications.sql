-- Migration: Add candidate_id column to applications table
-- This allows linking applications to the candidates table

ALTER TABLE `applications`
    ADD COLUMN `candidate_id` INT NULL AFTER `id`,
    ADD INDEX `idx_applications_candidate` (`candidate_id`),
    ADD CONSTRAINT `fk_applications_candidate`
        FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
