CREATE TABLE `anytime`.`service_department` (
                                              `id` INT NOT NULL AUTO_INCREMENT,
                                              `title` VARCHAR(45) NULL,
                                              PRIMARY KEY (`id`));

ALTER TABLE `anytime`.`types`
  ADD COLUMN `department_id` INT NULL AFTER `is_internal`,
  ADD COLUMN `sla` VARCHAR(45) NULL AFTER `department_id`;
