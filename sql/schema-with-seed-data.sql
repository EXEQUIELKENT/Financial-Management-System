-- ==============================================================================
-- travelcore_fms : schema + seed sample + corrected user emails (ALL-IN-ONE)
-- ==============================================================================
-- This file combines:
--   1) sql/schema.sql ............ full table structure + roles/permissions/settings
--   2) sql/seed.php sample data .. demo users, COA, customers, vendors, AR/AP,
--                                 budgets, cash, tax, journals (snapshot of one seed run)
--   3) sql/update-user-emails.sql  real OTP addresses for admin/accountant/
--                                 approver/auditor (also appended at the end,
--                                 idempotent - safe to re-run)
--
-- Usage (fresh database):
--   mysql -u root < schema-with-seed-data.sql
--   -- creates/fills travelcore_fms in one step. No need to run schema.sql,
--   -- seed.php, or update-user-emails.sql separately.
--
-- Demo logins (password for all: Passw0rd!):
--   admin / accountant / approver / auditor
-- ==============================================================================

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: travelcore_fms
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `travelcore_fms`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `travelcore_fms` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `travelcore_fms`;

--
-- Table structure for table `ai_assistant_query_log`
--

DROP TABLE IF EXISTS `ai_assistant_query_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_assistant_query_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `question_text` varchar(500) NOT NULL,
  `matched_intent` varchar(50) DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `ai_assistant_query_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_assistant_query_log`
--

LOCK TABLES `ai_assistant_query_log` WRITE;
/*!40000 ALTER TABLE `ai_assistant_query_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_assistant_query_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_bill_lines`
--

DROP TABLE IF EXISTS `ap_bill_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ap_bill_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `account_id` int(11) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `account_id` (`account_id`),
  KEY `tax_type_id` (`tax_type_id`),
  CONSTRAINT `ap_bill_lines_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `ap_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ap_bill_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `coa_accounts` (`id`),
  CONSTRAINT `ap_bill_lines_ibfk_3` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_bill_lines`
--

LOCK TABLES `ap_bill_lines` WRITE;
/*!40000 ALTER TABLE `ap_bill_lines` DISABLE KEYS */;
INSERT INTO `ap_bill_lines` VALUES (1,1,'Tour guide and permit fees',21,1.00,27000.00,27000.00,NULL),(2,2,'Hotel booking settlement',22,1.00,29000.00,29000.00,NULL),(3,3,'Air ticketing services',22,1.00,15000.00,15000.00,1),(4,4,'Land transport arrangement',21,1.00,21000.00,21000.00,NULL),(5,5,'Land transport arrangement',21,1.00,15000.00,15000.00,1),(6,6,'Tour guide and permit fees',22,1.00,19000.00,19000.00,1),(7,7,'Hotel booking settlement',22,1.00,23000.00,23000.00,NULL),(8,8,'Air ticketing services',23,1.00,12000.00,12000.00,1),(9,9,'Air ticketing services',23,1.00,30000.00,30000.00,1),(10,10,'Air ticketing services',23,1.00,15000.00,15000.00,1);
/*!40000 ALTER TABLE `ap_bill_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_bills`
--

DROP TABLE IF EXISTS `ap_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ap_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_no` varchar(30) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `bill_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_no` (`bill_no`),
  KEY `vendor_id` (`vendor_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_bill_due` (`due_date`),
  KEY `idx_bill_status` (`status`),
  CONSTRAINT `ap_bills_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ap_vendors` (`id`),
  CONSTRAINT `ap_bills_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ap_bills_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `ap_bills_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_bills`
--

LOCK TABLES `ap_bills` WRITE;
/*!40000 ALTER TABLE `ap_bills` DISABLE KEYS */;
INSERT INTO `ap_bills` VALUES (1,'BILL-2026-00001',3,'2026-03-05','2026-04-04',27000.00,0.00,27000.00,27000.00,'Paid',7,2,3,'2026-09-22 23:27:40'),(2,'BILL-2026-00002',3,'2026-03-04','2026-04-03',29000.00,0.00,29000.00,0.00,'Open',8,2,3,'2026-09-22 23:27:40'),(3,'BILL-2026-00003',3,'2026-04-18','2026-05-18',15000.00,1800.00,16800.00,16800.00,'Paid',24,2,3,'2026-09-22 23:27:40'),(4,'BILL-2026-00004',4,'2026-05-16','2026-06-15',21000.00,0.00,21000.00,21000.00,'Paid',39,2,3,'2026-09-22 23:27:41'),(5,'BILL-2026-00005',4,'2026-06-04','2026-07-04',15000.00,1800.00,16800.00,16800.00,'Paid',54,2,3,'2026-09-22 23:27:41'),(6,'BILL-2026-00006',4,'2026-07-21','2026-08-20',19000.00,2280.00,21280.00,21280.00,'Paid',71,2,3,'2026-09-22 23:27:41'),(7,'BILL-2026-00007',2,'2026-08-22','2026-09-21',23000.00,0.00,23000.00,23000.00,'Paid',90,2,3,'2026-09-22 23:27:42'),(8,'BILL-2026-00008',3,'2026-08-02','2026-09-01',12000.00,1440.00,13440.00,13440.00,'Paid',91,2,3,'2026-09-22 23:27:42'),(9,'BILL-2026-00009',2,'2026-09-13','2026-10-13',30000.00,3600.00,33600.00,0.00,'Open',111,2,3,'2026-09-22 23:27:42'),(10,'BILL-2026-00010',4,'2026-09-11','2026-10-11',15000.00,1800.00,16800.00,0.00,'Open',112,2,3,'2026-09-22 23:27:42');
/*!40000 ALTER TABLE `ap_bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_payment_applications`
--

DROP TABLE IF EXISTS `ap_payment_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ap_payment_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `bill_id` int(11) NOT NULL,
  `amount_applied` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `bill_id` (`bill_id`),
  CONSTRAINT `ap_payment_applications_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `ap_payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ap_payment_applications_ibfk_2` FOREIGN KEY (`bill_id`) REFERENCES `ap_bills` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_payment_applications`
--

LOCK TABLES `ap_payment_applications` WRITE;
/*!40000 ALTER TABLE `ap_payment_applications` DISABLE KEYS */;
INSERT INTO `ap_payment_applications` VALUES (1,1,1,27000.00),(2,2,3,16800.00),(3,3,4,21000.00),(4,4,5,16800.00),(5,5,6,21280.00),(6,6,7,23000.00),(7,7,8,13440.00);
/*!40000 ALTER TABLE `ap_payment_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_payments`
--

DROP TABLE IF EXISTS `ap_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ap_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_no` varchar(30) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'Bank Transfer',
  `reference_no` varchar(100) DEFAULT NULL,
  `cash_account_id` int(11) NOT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_no` (`payment_no`),
  KEY `vendor_id` (`vendor_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `ap_payments_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ap_vendors` (`id`),
  CONSTRAINT `ap_payments_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `ap_payments_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ap_payments_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_payments`
--

LOCK TABLES `ap_payments` WRITE;
/*!40000 ALTER TABLE `ap_payments` DISABLE KEYS */;
INSERT INTO `ap_payments` VALUES (1,'APPMT-2026-00001',3,'2026-04-01',27000.00,'Bank Transfer',NULL,1,18,2,'2026-09-22 23:27:40'),(2,'APPMT-2026-00002',3,'2026-05-12',16800.00,'Bank Transfer',NULL,1,34,2,'2026-09-22 23:27:41'),(3,'APPMT-2026-00003',4,'2026-06-08',21000.00,'Bank Transfer',NULL,3,49,2,'2026-09-22 23:27:41'),(4,'APPMT-2026-00004',4,'2026-06-27',16800.00,'Bank Transfer',NULL,1,65,2,'2026-09-22 23:27:41'),(5,'APPMT-2026-00005',4,'2026-08-19',21280.00,'Bank Transfer',NULL,3,83,2,'2026-09-22 23:27:42'),(6,'APPMT-2026-00006',2,'2026-09-13',23000.00,'Bank Transfer',NULL,2,104,2,'2026-09-22 23:27:42'),(7,'APPMT-2026-00007',3,'2026-08-29',13440.00,'Bank Transfer',NULL,2,105,2,'2026-09-22 23:27:42');
/*!40000 ALTER TABLE `ap_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ap_vendors`
--

DROP TABLE IF EXISTS `ap_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ap_vendors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vendor_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tax_id` varchar(30) DEFAULT NULL,
  `payment_terms_days` int(11) NOT NULL DEFAULT 30,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_code` (`vendor_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ap_vendors`
--

LOCK TABLES `ap_vendors` WRITE;
/*!40000 ALTER TABLE `ap_vendors` DISABLE KEYS */;
INSERT INTO `ap_vendors` VALUES (1,'V0001','Boracay Island Tours Supplier','Contact 1','boracayis@supplier.test',NULL,NULL,NULL,30,'Active','2026-09-22 23:27:39'),(2,'V0002','Cebu Pacific Air','Contact 2','cebupacif@supplier.test',NULL,NULL,NULL,30,'Active','2026-09-22 23:27:39'),(3,'V0003','Local Bus Transport Co.','Contact 3','localbus@supplier.test',NULL,NULL,NULL,30,'Active','2026-09-22 23:27:39'),(4,'V0004','Palawan Resort Partners','Contact 4','palawanre@supplier.test',NULL,NULL,NULL,30,'Active','2026-09-22 23:27:39');
/*!40000 ALTER TABLE `ap_vendors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_customers`
--

DROP TABLE IF EXISTS `ar_customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ar_customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tax_id` varchar(30) DEFAULT NULL,
  `credit_terms_days` int(11) NOT NULL DEFAULT 30,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_customers`
--

LOCK TABLES `ar_customers` WRITE;
/*!40000 ALTER TABLE `ar_customers` DISABLE KEYS */;
INSERT INTO `ar_customers` VALUES (1,'C0001','Juan Dela Cruz','Juan Dela Cruz','juandela@customer.test',NULL,NULL,NULL,15,'Active','2026-09-22 23:27:39'),(2,'C0002','Maria Santos Family','Maria Santos Family','mariasant@customer.test',NULL,NULL,NULL,15,'Active','2026-09-22 23:27:40'),(3,'C0003','ABC Corporate Events','ABC Corporate Events','abccorpor@customer.test',NULL,NULL,NULL,15,'Active','2026-09-22 23:27:40'),(4,'C0004','Manila Travel Club','Manila Travel Club','manilatra@customer.test',NULL,NULL,NULL,15,'Active','2026-09-22 23:27:40');
/*!40000 ALTER TABLE `ar_customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_invoice_lines`
--

DROP TABLE IF EXISTS `ar_invoice_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ar_invoice_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `account_id` int(11) NOT NULL,
  `qty` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `account_id` (`account_id`),
  KEY `tax_type_id` (`tax_type_id`),
  CONSTRAINT `ar_invoice_lines_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `ar_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ar_invoice_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `coa_accounts` (`id`),
  CONSTRAINT `ar_invoice_lines_ibfk_3` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_invoice_lines`
--

LOCK TABLES `ar_invoice_lines` WRITE;
/*!40000 ALTER TABLE `ar_invoice_lines` DISABLE KEYS */;
INSERT INTO `ar_invoice_lines` VALUES (1,1,'Baguio Weekend Getaway',18,1.00,21000.00,21000.00,NULL),(2,2,'Bohol Chocolate Hills Tour',16,1.00,44000.00,44000.00,NULL),(3,3,'Boracay 3D2N Package',18,1.00,58000.00,58000.00,1),(4,4,'Cebu City + Beach Package',17,1.00,31000.00,31000.00,1),(5,5,'Cebu City + Beach Package',18,1.00,52000.00,52000.00,1),(6,6,'Baguio Weekend Getaway',18,1.00,22000.00,22000.00,1),(7,7,'Cebu City + Beach Package',18,1.00,33000.00,33000.00,1),(8,8,'Baguio Weekend Getaway',18,1.00,57000.00,57000.00,1),(9,9,'Boracay 3D2N Package',18,1.00,21000.00,21000.00,1),(10,10,'Boracay 3D2N Package',17,1.00,37000.00,37000.00,NULL),(11,11,'Cebu City + Beach Package',18,1.00,45000.00,45000.00,1),(12,12,'Cebu City + Beach Package',16,1.00,50000.00,50000.00,NULL),(13,13,'Palawan Island Hopping Tour',18,1.00,21000.00,21000.00,1),(14,14,'Cebu City + Beach Package',18,1.00,45000.00,45000.00,1),(15,15,'Bohol Chocolate Hills Tour',16,1.00,28000.00,28000.00,NULL),(16,16,'Cebu City + Beach Package',16,1.00,54000.00,54000.00,1),(17,17,'Bohol Chocolate Hills Tour',16,1.00,48000.00,48000.00,NULL),(18,18,'Cebu City + Beach Package',18,1.00,41000.00,41000.00,1),(19,19,'Boracay 3D2N Package',17,1.00,49000.00,49000.00,NULL),(20,20,'Palawan Island Hopping Tour',17,1.00,59000.00,59000.00,1),(21,21,'Cebu City + Beach Package',16,1.00,43000.00,43000.00,1),(22,22,'Palawan Island Hopping Tour',16,1.00,42000.00,42000.00,NULL),(23,23,'Bohol Chocolate Hills Tour',17,1.00,25000.00,25000.00,1),(24,24,'Cebu City + Beach Package',16,1.00,68000.00,68000.00,NULL),(25,25,'Palawan Island Hopping Tour',16,1.00,37000.00,37000.00,1),(26,26,'Bohol Chocolate Hills Tour',16,1.00,29000.00,29000.00,1),(27,27,'Cebu City + Beach Package',17,1.00,46000.00,46000.00,NULL),(28,28,'Cebu City + Beach Package',16,1.00,55000.00,55000.00,1),(29,29,'Baguio Weekend Getaway',18,1.00,62000.00,62000.00,1),(30,30,'Boracay 3D2N Package',16,1.00,32000.00,32000.00,NULL),(31,31,'Palawan Island Hopping Tour',17,1.00,26000.00,26000.00,NULL),(32,32,'Bohol Chocolate Hills Tour',16,1.00,69000.00,69000.00,1),(33,33,'Cebu City + Beach Package',17,1.00,54000.00,54000.00,NULL),(34,34,'Boracay 3D2N Package',18,1.00,26000.00,26000.00,1);
/*!40000 ALTER TABLE `ar_invoice_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_invoices`
--

DROP TABLE IF EXISTS `ar_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ar_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `amount_received` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `customer_id` (`customer_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_inv_due` (`due_date`),
  KEY `idx_inv_status` (`status`),
  CONSTRAINT `ar_invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `ar_customers` (`id`),
  CONSTRAINT `ar_invoices_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ar_invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `ar_invoices_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_invoices`
--

LOCK TABLES `ar_invoices` WRITE;
/*!40000 ALTER TABLE `ar_invoices` DISABLE KEYS */;
INSERT INTO `ar_invoices` VALUES (1,'INV-2026-00001',1,'2026-03-13','2026-03-28',21000.00,0.00,21000.00,0.00,'Open',2,2,3,'2026-09-22 23:27:40'),(2,'INV-2026-00002',2,'2026-03-18','2026-04-02',44000.00,0.00,44000.00,44000.00,'Paid',3,2,3,'2026-09-22 23:27:40'),(3,'INV-2026-00003',3,'2026-03-04','2026-03-19',58000.00,6960.00,64960.00,64960.00,'Paid',4,2,3,'2026-09-22 23:27:40'),(4,'INV-2026-00004',4,'2026-03-14','2026-03-29',31000.00,3720.00,34720.00,34720.00,'Paid',5,2,3,'2026-09-22 23:27:40'),(5,'INV-2026-00005',3,'2026-03-09','2026-03-24',52000.00,6240.00,58240.00,58240.00,'Paid',6,2,3,'2026-09-22 23:27:40'),(6,'INV-2026-00006',2,'2026-04-18','2026-05-03',22000.00,2640.00,24640.00,12320.00,'PartiallyPaid',19,2,3,'2026-09-22 23:27:40'),(7,'INV-2026-00007',4,'2026-04-23','2026-05-08',33000.00,3960.00,36960.00,36960.00,'Paid',20,2,3,'2026-09-22 23:27:40'),(8,'INV-2026-00008',4,'2026-04-11','2026-04-26',57000.00,6840.00,63840.00,63840.00,'Paid',21,2,3,'2026-09-22 23:27:40'),(9,'INV-2026-00009',2,'2026-04-07','2026-04-22',21000.00,2520.00,23520.00,0.00,'Open',22,2,3,'2026-09-22 23:27:40'),(10,'INV-2026-00010',4,'2026-04-19','2026-05-04',37000.00,0.00,37000.00,0.00,'Open',23,2,3,'2026-09-22 23:27:40'),(11,'INV-2026-00011',2,'2026-05-09','2026-05-24',45000.00,5400.00,50400.00,50400.00,'Paid',35,2,3,'2026-09-22 23:27:41'),(12,'INV-2026-00012',2,'2026-05-08','2026-05-23',50000.00,0.00,50000.00,0.00,'Open',36,2,3,'2026-09-22 23:27:41'),(13,'INV-2026-00013',2,'2026-05-17','2026-06-01',21000.00,2520.00,23520.00,23520.00,'Paid',37,2,3,'2026-09-22 23:27:41'),(14,'INV-2026-00014',4,'2026-05-23','2026-06-07',45000.00,5400.00,50400.00,50400.00,'Paid',38,2,3,'2026-09-22 23:27:41'),(15,'INV-2026-00015',1,'2026-06-21','2026-07-06',28000.00,0.00,28000.00,28000.00,'Paid',50,2,3,'2026-09-22 23:27:41'),(16,'INV-2026-00016',4,'2026-06-10','2026-06-25',54000.00,6480.00,60480.00,60480.00,'Paid',51,2,3,'2026-09-22 23:27:41'),(17,'INV-2026-00017',3,'2026-06-18','2026-07-03',48000.00,0.00,48000.00,48000.00,'Paid',52,2,3,'2026-09-22 23:27:41'),(18,'INV-2026-00018',3,'2026-06-21','2026-07-06',41000.00,4920.00,45920.00,45920.00,'Paid',53,2,3,'2026-09-22 23:27:41'),(19,'INV-2026-00019',2,'2026-07-14','2026-07-29',49000.00,0.00,49000.00,49000.00,'Paid',66,2,3,'2026-09-22 23:27:41'),(20,'INV-2026-00020',1,'2026-07-09','2026-07-24',59000.00,7080.00,66080.00,33040.00,'PartiallyPaid',67,2,3,'2026-09-22 23:27:41'),(21,'INV-2026-00021',4,'2026-07-13','2026-07-28',43000.00,5160.00,48160.00,48160.00,'Paid',68,2,3,'2026-09-22 23:27:41'),(22,'INV-2026-00022',1,'2026-07-12','2026-07-27',42000.00,0.00,42000.00,42000.00,'Paid',69,2,3,'2026-09-22 23:27:41'),(23,'INV-2026-00023',3,'2026-07-07','2026-07-22',25000.00,3000.00,28000.00,28000.00,'Paid',70,2,3,'2026-09-22 23:27:41'),(24,'INV-2026-00024',1,'2026-08-05','2026-08-20',68000.00,0.00,68000.00,68000.00,'Paid',84,2,3,'2026-09-22 23:27:42'),(25,'INV-2026-00025',3,'2026-08-09','2026-08-24',37000.00,4440.00,41440.00,41440.00,'Paid',85,2,3,'2026-09-22 23:27:42'),(26,'INV-2026-00026',2,'2026-08-05','2026-08-20',29000.00,3480.00,32480.00,16240.00,'PartiallyPaid',86,2,3,'2026-09-22 23:27:42'),(27,'INV-2026-00027',3,'2026-08-08','2026-08-23',46000.00,0.00,46000.00,46000.00,'Paid',87,2,3,'2026-09-22 23:27:42'),(28,'INV-2026-00028',3,'2026-08-03','2026-08-18',55000.00,6600.00,61600.00,30800.00,'PartiallyPaid',88,2,3,'2026-09-22 23:27:42'),(29,'INV-2026-00029',1,'2026-08-04','2026-08-19',62000.00,7440.00,69440.00,69440.00,'Paid',89,2,3,'2026-09-22 23:27:42'),(30,'INV-2026-00030',4,'2026-09-03','2026-09-18',32000.00,0.00,32000.00,0.00,'Open',106,2,3,'2026-09-22 23:27:42'),(31,'INV-2026-00031',4,'2026-09-22','2026-10-07',26000.00,0.00,26000.00,0.00,'Open',107,2,3,'2026-09-22 23:27:42'),(32,'INV-2026-00032',3,'2026-09-09','2026-09-24',69000.00,8280.00,77280.00,0.00,'Open',108,2,3,'2026-09-22 23:27:42'),(33,'INV-2026-00033',1,'2026-09-02','2026-09-17',54000.00,0.00,54000.00,0.00,'Open',109,2,3,'2026-09-22 23:27:42'),(34,'INV-2026-00034',3,'2026-09-06','2026-09-21',26000.00,3120.00,29120.00,0.00,'Open',110,2,3,'2026-09-22 23:27:42');
/*!40000 ALTER TABLE `ar_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_receipt_applications`
--

DROP TABLE IF EXISTS `ar_receipt_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ar_receipt_applications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount_applied` decimal(14,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `receipt_id` (`receipt_id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `ar_receipt_applications_ibfk_1` FOREIGN KEY (`receipt_id`) REFERENCES `ar_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ar_receipt_applications_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `ar_invoices` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_receipt_applications`
--

LOCK TABLES `ar_receipt_applications` WRITE;
/*!40000 ALTER TABLE `ar_receipt_applications` DISABLE KEYS */;
INSERT INTO `ar_receipt_applications` VALUES (1,1,2,44000.00),(2,2,3,64960.00),(3,3,4,34720.00),(4,4,5,58240.00),(5,5,6,12320.00),(6,6,7,36960.00),(7,7,8,63840.00),(8,8,11,50400.00),(9,9,13,23520.00),(10,10,14,50400.00),(11,11,15,28000.00),(12,12,16,60480.00),(13,13,17,48000.00),(14,14,18,45920.00),(15,15,19,49000.00),(16,16,20,33040.00),(17,17,21,48160.00),(18,18,22,42000.00),(19,19,23,28000.00),(20,20,24,68000.00),(21,21,25,41440.00),(22,22,26,16240.00),(23,23,27,46000.00),(24,24,28,30800.00),(25,25,29,69440.00);
/*!40000 ALTER TABLE `ar_receipt_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ar_receipts`
--

DROP TABLE IF EXISTS `ar_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ar_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `receipt_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL DEFAULT 'Bank Transfer',
  `reference_no` varchar(100) DEFAULT NULL,
  `cash_account_id` int(11) NOT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `customer_id` (`customer_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `ar_receipts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `ar_customers` (`id`),
  CONSTRAINT `ar_receipts_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `ar_receipts_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ar_receipts_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ar_receipts`
--

LOCK TABLES `ar_receipts` WRITE;
/*!40000 ALTER TABLE `ar_receipts` DISABLE KEYS */;
INSERT INTO `ar_receipts` VALUES (1,'ARCPT-2026-00001',2,'2026-03-29',44000.00,'Bank Transfer',NULL,2,14,2,'2026-09-22 23:27:40'),(2,'ARCPT-2026-00002',3,'2026-03-19',64960.00,'Bank Transfer',NULL,1,15,2,'2026-09-22 23:27:40'),(3,'ARCPT-2026-00003',4,'2026-03-29',34720.00,'Bank Transfer',NULL,1,16,2,'2026-09-22 23:27:40'),(4,'ARCPT-2026-00004',3,'2026-03-22',58240.00,'Bank Transfer',NULL,2,17,2,'2026-09-22 23:27:40'),(5,'ARCPT-2026-00005',2,'2026-04-23',12320.00,'Bank Transfer',NULL,3,31,2,'2026-09-22 23:27:41'),(6,'ARCPT-2026-00006',4,'2026-05-07',36960.00,'Bank Transfer',NULL,3,32,2,'2026-09-22 23:27:41'),(7,'ARCPT-2026-00007',4,'2026-04-19',63840.00,'Bank Transfer',NULL,3,33,2,'2026-09-22 23:27:41'),(8,'ARCPT-2026-00008',2,'2026-05-21',50400.00,'Bank Transfer',NULL,3,46,2,'2026-09-22 23:27:41'),(9,'ARCPT-2026-00009',2,'2026-05-23',23520.00,'Bank Transfer',NULL,3,47,2,'2026-09-22 23:27:41'),(10,'ARCPT-2026-00010',4,'2026-06-07',50400.00,'Bank Transfer',NULL,1,48,2,'2026-09-22 23:27:41'),(11,'ARCPT-2026-00011',1,'2026-06-30',28000.00,'Bank Transfer',NULL,2,61,2,'2026-09-22 23:27:41'),(12,'ARCPT-2026-00012',4,'2026-06-17',60480.00,'Bank Transfer',NULL,1,62,2,'2026-09-22 23:27:41'),(13,'ARCPT-2026-00013',3,'2026-06-27',48000.00,'Bank Transfer',NULL,1,63,2,'2026-09-22 23:27:41'),(14,'ARCPT-2026-00014',3,'2026-07-02',45920.00,'Bank Transfer',NULL,1,64,2,'2026-09-22 23:27:41'),(15,'ARCPT-2026-00015',2,'2026-07-25',49000.00,'Bank Transfer',NULL,3,78,2,'2026-09-22 23:27:42'),(16,'ARCPT-2026-00016',1,'2026-07-19',33040.00,'Bank Transfer',NULL,1,79,2,'2026-09-22 23:27:42'),(17,'ARCPT-2026-00017',4,'2026-07-24',48160.00,'Bank Transfer',NULL,1,80,2,'2026-09-22 23:27:42'),(18,'ARCPT-2026-00018',1,'2026-07-27',42000.00,'Bank Transfer',NULL,2,81,2,'2026-09-22 23:27:42'),(19,'ARCPT-2026-00019',3,'2026-07-18',28000.00,'Bank Transfer',NULL,2,82,2,'2026-09-22 23:27:42'),(20,'ARCPT-2026-00020',1,'2026-08-15',68000.00,'Bank Transfer',NULL,3,98,2,'2026-09-22 23:27:42'),(21,'ARCPT-2026-00021',3,'2026-08-15',41440.00,'Bank Transfer',NULL,1,99,2,'2026-09-22 23:27:42'),(22,'ARCPT-2026-00022',2,'2026-08-15',16240.00,'Bank Transfer',NULL,3,100,2,'2026-09-22 23:27:42'),(23,'ARCPT-2026-00023',3,'2026-08-19',46000.00,'Bank Transfer',NULL,1,101,2,'2026-09-22 23:27:42'),(24,'ARCPT-2026-00024',3,'2026-08-10',30800.00,'Bank Transfer',NULL,3,102,2,'2026-09-22 23:27:42'),(25,'ARCPT-2026-00025',1,'2026-08-13',69440.00,'Bank Transfer',NULL,1,103,2,'2026-09-22 23:27:42');
/*!40000 ALTER TABLE `ar_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_audit_module` (`module`,`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_reconciliation_items`
--

DROP TABLE IF EXISTS `bank_reconciliation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_reconciliation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reconciliation_id` int(11) NOT NULL,
  `cash_transaction_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `item_type` varchar(30) NOT NULL,
  `cleared` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `reconciliation_id` (`reconciliation_id`),
  KEY `cash_transaction_id` (`cash_transaction_id`),
  CONSTRAINT `bank_reconciliation_items_ibfk_1` FOREIGN KEY (`reconciliation_id`) REFERENCES `bank_reconciliations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_reconciliation_items_ibfk_2` FOREIGN KEY (`cash_transaction_id`) REFERENCES `cash_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_reconciliation_items`
--

LOCK TABLES `bank_reconciliation_items` WRITE;
/*!40000 ALTER TABLE `bank_reconciliation_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_reconciliation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_reconciliations`
--

DROP TABLE IF EXISTS `bank_reconciliations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_reconciliations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cash_account_id` int(11) NOT NULL,
  `statement_date` date NOT NULL,
  `statement_balance` decimal(14,2) NOT NULL,
  `book_balance` decimal(14,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'InProgress',
  `reconciled_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `reconciled_by` (`reconciled_by`),
  CONSTRAINT `bank_reconciliations_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `bank_reconciliations_ibfk_2` FOREIGN KEY (`reconciled_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_reconciliations`
--

LOCK TABLES `bank_reconciliations` WRITE;
/*!40000 ALTER TABLE `bank_reconciliations` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_reconciliations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budget_line_monthly`
--

DROP TABLE IF EXISTS `budget_line_monthly`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_line_monthly` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_line_id` int(11) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `budgeted_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_line_month` (`budget_line_id`,`month`),
  CONSTRAINT `budget_line_monthly_ibfk_1` FOREIGN KEY (`budget_line_id`) REFERENCES `budget_lines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_line_monthly`
--

LOCK TABLES `budget_line_monthly` WRITE;
/*!40000 ALTER TABLE `budget_line_monthly` DISABLE KEYS */;
INSERT INTO `budget_line_monthly` VALUES (1,1,1,90000.00),(2,1,2,90000.00),(3,1,3,90000.00),(4,1,4,90000.00),(5,1,5,90000.00),(6,1,6,90000.00),(7,1,7,90000.00),(8,1,8,90000.00),(9,1,9,90000.00),(10,1,10,90000.00),(11,1,11,90000.00),(12,1,12,90000.00),(13,2,1,20000.00),(14,2,2,20000.00),(15,2,3,20000.00),(16,2,4,20000.00),(17,2,5,20000.00),(18,2,6,20000.00),(19,2,7,20000.00),(20,2,8,20000.00),(21,2,9,20000.00),(22,2,10,20000.00),(23,2,11,20000.00),(24,2,12,20000.00),(25,3,1,8000.00),(26,3,2,8000.00),(27,3,3,8000.00),(28,3,4,8000.00),(29,3,5,8000.00),(30,3,6,8000.00),(31,3,7,8000.00),(32,3,8,8000.00),(33,3,9,8000.00),(34,3,10,8000.00),(35,3,11,8000.00),(36,3,12,8000.00),(37,4,1,15000.00),(38,4,2,15000.00),(39,4,3,15000.00),(40,4,4,15000.00),(41,4,5,15000.00),(42,4,6,15000.00),(43,4,7,15000.00),(44,4,8,15000.00),(45,4,9,15000.00),(46,4,10,15000.00),(47,4,11,15000.00),(48,4,12,15000.00),(49,5,1,5000.00),(50,5,2,5000.00),(51,5,3,5000.00),(52,5,4,5000.00),(53,5,5,5000.00),(54,5,6,5000.00),(55,5,7,5000.00),(56,5,8,5000.00),(57,5,9,5000.00),(58,5,10,5000.00),(59,5,11,5000.00),(60,5,12,5000.00);
/*!40000 ALTER TABLE `budget_line_monthly` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budget_lines`
--

DROP TABLE IF EXISTS `budget_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `budget_id` (`budget_id`),
  KEY `account_id` (`account_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `budget_lines_ibfk_1` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `budget_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `coa_accounts` (`id`),
  CONSTRAINT `budget_lines_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_lines`
--

LOCK TABLES `budget_lines` WRITE;
/*!40000 ALTER TABLE `budget_lines` DISABLE KEYS */;
INSERT INTO `budget_lines` VALUES (1,1,24,NULL,NULL),(2,1,26,NULL,NULL),(3,1,27,NULL,NULL),(4,1,29,NULL,NULL),(5,1,28,NULL,NULL);
/*!40000 ALTER TABLE `budget_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budget_periods`
--

DROP TABLE IF EXISTS `budget_periods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budget_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budget_periods`
--

LOCK TABLES `budget_periods` WRITE;
/*!40000 ALTER TABLE `budget_periods` DISABLE KEYS */;
INSERT INTO `budget_periods` VALUES (1,'FY2026','2026-01-01','2026-12-31','Open','2026-09-22 23:27:40');
/*!40000 ALTER TABLE `budget_periods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `budgets`
--

DROP TABLE IF EXISTS `budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `budgets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `budget_period_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `budget_period_id` (`budget_period_id`),
  KEY `department_id` (`department_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`budget_period_id`) REFERENCES `budget_periods` (`id`),
  CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `budgets_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `budgets_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `budgets`
--

LOCK TABLES `budgets` WRITE;
/*!40000 ALTER TABLE `budgets` DISABLE KEYS */;
INSERT INTO `budgets` VALUES (1,1,NULL,'Operating Budget 2026','Approved',2,3,'2026-09-22 23:27:40');
/*!40000 ALTER TABLE `budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_accounts`
--

DROP TABLE IF EXISTS `cash_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_name` varchar(150) NOT NULL,
  `account_type` varchar(30) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_no` varchar(50) DEFAULT NULL,
  `gl_account_id` int(11) NOT NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `current_balance` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `gl_account_id` (`gl_account_id`),
  CONSTRAINT `cash_accounts_ibfk_1` FOREIGN KEY (`gl_account_id`) REFERENCES `coa_accounts` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_accounts`
--

LOCK TABLES `cash_accounts` WRITE;
/*!40000 ALTER TABLE `cash_accounts` DISABLE KEYS */;
INSERT INTO `cash_accounts` VALUES (1,'Cash on Hand','Cash on Hand',NULL,NULL,1,50000.00,490800.00,'Active','2026-09-22 23:27:39'),(2,'Bank - BDO','Bank','BDO','001-234-5678',2,300000.00,47399.80,'Active','2026-09-22 23:27:39'),(3,'Bank - BPI','Bank','BPI','002-345-6789',3,150000.00,2381.00,'Active','2026-09-22 23:27:39');
/*!40000 ALTER TABLE `cash_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_transactions`
--

DROP TABLE IF EXISTS `cash_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cash_account_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `type` varchar(20) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `source_module` varchar(30) DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `cash_flow_category` varchar(20) NOT NULL DEFAULT 'Operating',
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_ct_date` (`transaction_date`),
  CONSTRAINT `cash_transactions_ibfk_1` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `cash_transactions_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_transactions_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_transactions`
--

LOCK TABLES `cash_transactions` WRITE;
/*!40000 ALTER TABLE `cash_transactions` DISABLE KEYS */;
INSERT INTO `cash_transactions` VALUES (1,3,'2026-03-26','Withdrawal',88520.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',9,2,'2026-09-22 23:27:40'),(2,2,'2026-03-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',10,2,'2026-09-22 23:27:40'),(3,2,'2026-03-11','Withdrawal',8306.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',11,2,'2026-09-22 23:27:40'),(4,1,'2026-03-19','Withdrawal',1611.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',12,2,'2026-09-22 23:27:40'),(5,1,'2026-03-13','Withdrawal',3824.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',13,2,'2026-09-22 23:27:40'),(6,2,'2026-03-29','Deposit',44000.00,'ARCPT-2026-00001','AR receipt','ar',1,'Operating',14,2,'2026-09-22 23:27:40'),(7,1,'2026-03-19','Deposit',64960.00,'ARCPT-2026-00002','AR receipt','ar',2,'Operating',15,2,'2026-09-22 23:27:40'),(8,1,'2026-03-29','Deposit',34720.00,'ARCPT-2026-00003','AR receipt','ar',3,'Operating',16,2,'2026-09-22 23:27:40'),(9,2,'2026-03-22','Deposit',58240.00,'ARCPT-2026-00004','AR receipt','ar',4,'Operating',17,2,'2026-09-22 23:27:40'),(10,1,'2026-04-01','Withdrawal',27000.00,'APPMT-2026-00001','AP payment','ap',1,'Operating',18,2,'2026-09-22 23:27:40'),(11,2,'2026-04-26','Withdrawal',89847.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',25,2,'2026-09-22 23:27:40'),(12,3,'2026-04-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',26,2,'2026-09-22 23:27:40'),(13,3,'2026-04-11','Withdrawal',6841.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',27,2,'2026-09-22 23:27:40'),(14,2,'2026-04-16','Withdrawal',15661.00,NULL,'Marketing and social media ads',NULL,NULL,'Operating',28,2,'2026-09-22 23:27:41'),(15,1,'2026-04-19','Withdrawal',1287.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',29,2,'2026-09-22 23:27:41'),(16,1,'2026-04-13','Withdrawal',5587.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',30,2,'2026-09-22 23:27:41'),(17,3,'2026-04-23','Deposit',12320.00,'ARCPT-2026-00005','AR receipt','ar',5,'Operating',31,2,'2026-09-22 23:27:41'),(18,3,'2026-05-07','Deposit',36960.00,'ARCPT-2026-00006','AR receipt','ar',6,'Operating',32,2,'2026-09-22 23:27:41'),(19,3,'2026-04-19','Deposit',63840.00,'ARCPT-2026-00007','AR receipt','ar',7,'Operating',33,2,'2026-09-22 23:27:41'),(20,1,'2026-05-12','Withdrawal',16800.00,'APPMT-2026-00002','AP payment','ap',2,'Operating',34,2,'2026-09-22 23:27:41'),(21,3,'2026-05-26','Withdrawal',89071.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',40,2,'2026-09-22 23:27:41'),(22,2,'2026-05-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',41,2,'2026-09-22 23:27:41'),(23,2,'2026-05-11','Withdrawal',8572.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',42,2,'2026-09-22 23:27:41'),(24,3,'2026-05-16','Withdrawal',10646.00,NULL,'Marketing and social media ads',NULL,NULL,'Operating',43,2,'2026-09-22 23:27:41'),(25,1,'2026-05-19','Withdrawal',977.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',44,2,'2026-09-22 23:27:41'),(26,1,'2026-05-13','Withdrawal',4768.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',45,2,'2026-09-22 23:27:41'),(27,3,'2026-05-21','Deposit',50400.00,'ARCPT-2026-00008','AR receipt','ar',8,'Operating',46,2,'2026-09-22 23:27:41'),(28,3,'2026-05-23','Deposit',23520.00,'ARCPT-2026-00009','AR receipt','ar',9,'Operating',47,2,'2026-09-22 23:27:41'),(29,1,'2026-06-07','Deposit',50400.00,'ARCPT-2026-00010','AR receipt','ar',10,'Operating',48,2,'2026-09-22 23:27:41'),(30,3,'2026-06-08','Withdrawal',21000.00,'APPMT-2026-00003','AP payment','ap',3,'Operating',49,2,'2026-09-22 23:27:41'),(31,2,'2026-06-26','Withdrawal',86893.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',55,2,'2026-09-22 23:27:41'),(32,3,'2026-06-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',56,2,'2026-09-22 23:27:41'),(33,3,'2026-06-11','Withdrawal',7542.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',57,2,'2026-09-22 23:27:41'),(34,2,'2026-06-16','Withdrawal',13774.00,NULL,'Marketing and social media ads',NULL,NULL,'Operating',58,2,'2026-09-22 23:27:41'),(35,1,'2026-06-19','Withdrawal',1076.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',59,2,'2026-09-22 23:27:41'),(36,1,'2026-06-13','Withdrawal',5692.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',60,2,'2026-09-22 23:27:41'),(37,2,'2026-06-30','Deposit',28000.00,'ARCPT-2026-00011','AR receipt','ar',11,'Operating',61,2,'2026-09-22 23:27:41'),(38,1,'2026-06-17','Deposit',60480.00,'ARCPT-2026-00012','AR receipt','ar',12,'Operating',62,2,'2026-09-22 23:27:41'),(39,1,'2026-06-27','Deposit',48000.00,'ARCPT-2026-00013','AR receipt','ar',13,'Operating',63,2,'2026-09-22 23:27:41'),(40,1,'2026-07-02','Deposit',45920.00,'ARCPT-2026-00014','AR receipt','ar',14,'Operating',64,2,'2026-09-22 23:27:41'),(41,1,'2026-06-27','Withdrawal',16800.00,'APPMT-2026-00004','AP payment','ap',4,'Operating',65,2,'2026-09-22 23:27:41'),(42,3,'2026-07-26','Withdrawal',89652.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',72,2,'2026-09-22 23:27:42'),(43,2,'2026-07-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',73,2,'2026-09-22 23:27:42'),(44,2,'2026-07-11','Withdrawal',7388.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',74,2,'2026-09-22 23:27:42'),(45,3,'2026-07-16','Withdrawal',10340.00,NULL,'Marketing and social media ads',NULL,NULL,'Operating',75,2,'2026-09-22 23:27:42'),(46,1,'2026-07-19','Withdrawal',636.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',76,2,'2026-09-22 23:27:42'),(47,1,'2026-07-13','Withdrawal',4000.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',77,2,'2026-09-22 23:27:42'),(48,3,'2026-07-25','Deposit',49000.00,'ARCPT-2026-00015','AR receipt','ar',15,'Operating',78,2,'2026-09-22 23:27:42'),(49,1,'2026-07-19','Deposit',33040.00,'ARCPT-2026-00016','AR receipt','ar',16,'Operating',79,2,'2026-09-22 23:27:42'),(50,1,'2026-07-24','Deposit',48160.00,'ARCPT-2026-00017','AR receipt','ar',17,'Operating',80,2,'2026-09-22 23:27:42'),(51,2,'2026-07-27','Deposit',42000.00,'ARCPT-2026-00018','AR receipt','ar',18,'Operating',81,2,'2026-09-22 23:27:42'),(52,2,'2026-07-18','Deposit',28000.00,'ARCPT-2026-00019','AR receipt','ar',19,'Operating',82,2,'2026-09-22 23:27:42'),(53,3,'2026-08-19','Withdrawal',21280.00,'APPMT-2026-00005','AP payment','ap',5,'Operating',83,2,'2026-09-22 23:27:42'),(54,2,'2026-08-26','Withdrawal',87679.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',92,2,'2026-09-22 23:27:42'),(55,3,'2026-08-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',93,2,'2026-09-22 23:27:42'),(56,3,'2026-08-11','Withdrawal',7066.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',94,2,'2026-09-22 23:27:42'),(57,2,'2026-08-16','Withdrawal',10745.00,NULL,'Marketing and social media ads',NULL,NULL,'Operating',95,2,'2026-09-22 23:27:42'),(58,1,'2026-08-19','Withdrawal',709.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',96,2,'2026-09-22 23:27:42'),(59,1,'2026-08-13','Withdrawal',3851.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',97,2,'2026-09-22 23:27:42'),(60,3,'2026-08-15','Deposit',68000.00,'ARCPT-2026-00020','AR receipt','ar',20,'Operating',98,2,'2026-09-22 23:27:42'),(61,1,'2026-08-15','Deposit',41440.00,'ARCPT-2026-00021','AR receipt','ar',21,'Operating',99,2,'2026-09-22 23:27:42'),(62,3,'2026-08-15','Deposit',16240.00,'ARCPT-2026-00022','AR receipt','ar',22,'Operating',100,2,'2026-09-22 23:27:42'),(63,1,'2026-08-19','Deposit',46000.00,'ARCPT-2026-00023','AR receipt','ar',23,'Operating',101,2,'2026-09-22 23:27:42'),(64,3,'2026-08-10','Deposit',30800.00,'ARCPT-2026-00024','AR receipt','ar',24,'Operating',102,2,'2026-09-22 23:27:42'),(65,1,'2026-08-13','Deposit',69440.00,'ARCPT-2026-00025','AR receipt','ar',25,'Operating',103,2,'2026-09-22 23:27:42'),(66,2,'2026-09-13','Withdrawal',23000.00,'APPMT-2026-00006','AP payment','ap',6,'Operating',104,2,'2026-09-22 23:27:42'),(67,2,'2026-08-29','Withdrawal',13440.00,'APPMT-2026-00007','AP payment','ap',7,'Operating',105,2,'2026-09-22 23:27:42'),(68,3,'2026-09-26','Withdrawal',86741.00,NULL,'Monthly salaries and wages',NULL,NULL,'Operating',113,2,'2026-09-22 23:27:42'),(69,2,'2026-09-06','Withdrawal',20000.00,NULL,'Office rent',NULL,NULL,'Operating',114,2,'2026-09-22 23:27:42'),(70,2,'2026-09-11','Withdrawal',7740.00,NULL,'Utilities (power/water/internet)',NULL,NULL,'Operating',115,2,'2026-09-22 23:27:42'),(71,1,'2026-09-19','Withdrawal',1155.00,NULL,'Miscellaneous office expense',NULL,NULL,'Operating',116,2,'2026-09-22 23:27:42'),(72,1,'2026-09-13','Withdrawal',5987.00,NULL,'Office supplies purchase',NULL,NULL,'Operating',117,2,'2026-09-22 23:27:43'),(73,2,'2026-10-05','Deposit',123.45,NULL,'Deposit - Bank - BDO',NULL,NULL,'Operating',118,1,'2026-10-05 17:52:44'),(74,2,'2026-10-05','Deposit',123.45,NULL,'Deposit - Bank - BDO',NULL,NULL,'Operating',119,1,'2026-10-05 17:53:09'),(75,2,'2026-10-05','Withdrawal',42.10,NULL,'E2E typed description test',NULL,NULL,'Operating',120,1,'2026-10-05 17:53:10');
/*!40000 ALTER TABLE `cash_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cash_transfers`
--

DROP TABLE IF EXISTS `cash_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cash_transfers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transfer_no` varchar(30) NOT NULL,
  `from_cash_account_id` int(11) NOT NULL,
  `to_cash_account_id` int(11) NOT NULL,
  `transfer_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `transfer_no` (`transfer_no`),
  KEY `from_cash_account_id` (`from_cash_account_id`),
  KEY `to_cash_account_id` (`to_cash_account_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `cash_transfers_ibfk_1` FOREIGN KEY (`from_cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `cash_transfers_ibfk_2` FOREIGN KEY (`to_cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `cash_transfers_ibfk_3` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cash_transfers_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cash_transfers`
--

LOCK TABLES `cash_transfers` WRITE;
/*!40000 ALTER TABLE `cash_transfers` DISABLE KEYS */;
/*!40000 ALTER TABLE `cash_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `coa_accounts`
--

DROP TABLE IF EXISTS `coa_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `coa_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `account_type` varchar(20) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `normal_balance` varchar(10) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code` (`account_code`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `coa_accounts_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `coa_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `coa_accounts`
--

LOCK TABLES `coa_accounts` WRITE;
/*!40000 ALTER TABLE `coa_accounts` DISABLE KEYS */;
INSERT INTO `coa_accounts` VALUES (1,'1000','Cash on Hand','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(2,'1010','Cash in Bank - BDO','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(3,'1020','Cash in Bank - BPI','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(4,'1100','Accounts Receivable','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(5,'1150','Input Tax (VAT)','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(6,'1200','Withholding Tax Receivable','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(7,'1300','Prepaid Expenses','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(8,'1400','Office Equipment','Asset',NULL,'Debit',1,'2026-09-22 23:27:39'),(9,'2000','Accounts Payable','Liability',NULL,'Credit',1,'2026-09-22 23:27:39'),(10,'2010','Output Tax (VAT) Payable','Liability',NULL,'Credit',1,'2026-09-22 23:27:39'),(11,'2020','Withholding Tax Payable','Liability',NULL,'Credit',1,'2026-09-22 23:27:39'),(12,'2100','Accrued Expenses','Liability',NULL,'Credit',1,'2026-09-22 23:27:39'),(13,'2200','Loans Payable','Liability',NULL,'Credit',1,'2026-09-22 23:27:39'),(14,'3000','Owner\'s Capital','Equity',NULL,'Credit',1,'2026-09-22 23:27:39'),(15,'3100','Retained Earnings','Equity',NULL,'Credit',1,'2026-09-22 23:27:39'),(16,'4000','Tour Package Revenue','Revenue',NULL,'Credit',1,'2026-09-22 23:27:39'),(17,'4010','Hotel Booking Revenue','Revenue',NULL,'Credit',1,'2026-09-22 23:27:39'),(18,'4020','Transport Booking Revenue','Revenue',NULL,'Credit',1,'2026-09-22 23:27:39'),(19,'4030','Visa & Documentation Fee Revenue','Revenue',NULL,'Credit',1,'2026-09-22 23:27:39'),(20,'4900','Other Income','Revenue',NULL,'Credit',1,'2026-09-22 23:27:39'),(21,'5000','Cost of Tours (Guides & Permits)','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(22,'5010','Cost of Hotel Bookings','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(23,'5020','Cost of Transport Bookings','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(24,'5100','Salaries and Wages','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(25,'5110','Employee Benefits','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(26,'5200','Rent Expense','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(27,'5210','Utilities Expense','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(28,'5220','Office Supplies Expense','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(29,'5300','Marketing and Advertising Expense','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(30,'5400','Bank Charges','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(31,'5600','Professional Fees','Expense',NULL,'Debit',1,'2026-09-22 23:27:39'),(32,'5800','Miscellaneous Expense','Expense',NULL,'Debit',1,'2026-09-22 23:27:39');
/*!40000 ALTER TABLE `coa_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `collection_receipt_lines`
--

DROP TABLE IF EXISTS `collection_receipt_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collection_receipt_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cr_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `ar_invoice_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cr_id` (`cr_id`),
  KEY `account_id` (`account_id`),
  KEY `ar_invoice_id` (`ar_invoice_id`),
  CONSTRAINT `collection_receipt_lines_ibfk_1` FOREIGN KEY (`cr_id`) REFERENCES `collection_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `collection_receipt_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `coa_accounts` (`id`),
  CONSTRAINT `collection_receipt_lines_ibfk_3` FOREIGN KEY (`ar_invoice_id`) REFERENCES `ar_invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `collection_receipt_lines`
--

LOCK TABLES `collection_receipt_lines` WRITE;
/*!40000 ALTER TABLE `collection_receipt_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `collection_receipt_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `collection_receipts`
--

DROP TABLE IF EXISTS `collection_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `collection_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cr_no` varchar(30) NOT NULL,
  `cr_date` date NOT NULL,
  `payer_type` varchar(20) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `payer_name` varchar(150) NOT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `cash_account_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `received_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `ar_receipt_id` int(11) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cr_no` (`cr_no`),
  KEY `customer_id` (`customer_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `received_by` (`received_by`),
  KEY `approved_by` (`approved_by`),
  KEY `ar_receipt_id` (`ar_receipt_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `collection_receipts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `ar_customers` (`id`),
  CONSTRAINT `collection_receipts_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `collection_receipts_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  CONSTRAINT `collection_receipts_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `collection_receipts_ibfk_5` FOREIGN KEY (`ar_receipt_id`) REFERENCES `ar_receipts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `collection_receipts_ibfk_6` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `collection_receipts`
--

LOCK TABLES `collection_receipts` WRITE;
/*!40000 ALTER TABLE `collection_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `collection_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cr_approval_history`
--

DROP TABLE IF EXISTS `cr_approval_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cr_approval_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cr_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cr_id` (`cr_id`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `cr_approval_history_ibfk_1` FOREIGN KEY (`cr_id`) REFERENCES `collection_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cr_approval_history_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cr_approval_history`
--

LOCK TABLES `cr_approval_history` WRITE;
/*!40000 ALTER TABLE `cr_approval_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `cr_approval_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disbursement_voucher_lines`
--

DROP TABLE IF EXISTS `disbursement_voucher_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disbursement_voucher_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dv_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `ap_bill_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dv_id` (`dv_id`),
  KEY `account_id` (`account_id`),
  KEY `ap_bill_id` (`ap_bill_id`),
  CONSTRAINT `disbursement_voucher_lines_ibfk_1` FOREIGN KEY (`dv_id`) REFERENCES `disbursement_vouchers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `disbursement_voucher_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `coa_accounts` (`id`),
  CONSTRAINT `disbursement_voucher_lines_ibfk_3` FOREIGN KEY (`ap_bill_id`) REFERENCES `ap_bills` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disbursement_voucher_lines`
--

LOCK TABLES `disbursement_voucher_lines` WRITE;
/*!40000 ALTER TABLE `disbursement_voucher_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `disbursement_voucher_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `disbursement_vouchers`
--

DROP TABLE IF EXISTS `disbursement_vouchers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disbursement_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dv_no` varchar(30) NOT NULL,
  `dv_date` date NOT NULL,
  `payee_type` varchar(20) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `payee_name` varchar(150) NOT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `cash_account_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `ap_payment_id` int(11) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dv_no` (`dv_no`),
  KEY `vendor_id` (`vendor_id`),
  KEY `cash_account_id` (`cash_account_id`),
  KEY `requested_by` (`requested_by`),
  KEY `approved_by` (`approved_by`),
  KEY `ap_payment_id` (`ap_payment_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  CONSTRAINT `disbursement_vouchers_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ap_vendors` (`id`),
  CONSTRAINT `disbursement_vouchers_ibfk_2` FOREIGN KEY (`cash_account_id`) REFERENCES `cash_accounts` (`id`),
  CONSTRAINT `disbursement_vouchers_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `disbursement_vouchers_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `disbursement_vouchers_ibfk_5` FOREIGN KEY (`ap_payment_id`) REFERENCES `ap_payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `disbursement_vouchers_ibfk_6` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `disbursement_vouchers`
--

LOCK TABLES `disbursement_vouchers` WRITE;
/*!40000 ALTER TABLE `disbursement_vouchers` DISABLE KEYS */;
/*!40000 ALTER TABLE `disbursement_vouchers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dv_approval_history`
--

DROP TABLE IF EXISTS `dv_approval_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dv_approval_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dv_id` int(11) NOT NULL,
  `action` varchar(30) NOT NULL,
  `actor_id` int(11) NOT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `dv_id` (`dv_id`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `dv_approval_history_ibfk_1` FOREIGN KEY (`dv_id`) REFERENCES `disbursement_vouchers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dv_approval_history_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dv_approval_history`
--

LOCK TABLES `dv_approval_history` WRITE;
/*!40000 ALTER TABLE `dv_approval_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `dv_approval_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entry_no` varchar(30) NOT NULL,
  `entry_date` date NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `source_module` varchar(30) NOT NULL DEFAULT 'manual',
  `source_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `created_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_no` (`entry_no`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  KEY `idx_je_date` (`entry_date`),
  KEY `idx_je_source` (`source_module`,`source_id`),
  CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `journal_entries_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
INSERT INTO `journal_entries` VALUES (1,'JE-2026-00001','2026-03-01','OPENING','manual',NULL,'Opening balances','Posted',1,1,'2026-09-22 23:27:39','2026-09-22 23:27:39'),(2,'JE-2026-00002','2026-03-13','INV-2026-00001','ar',1,'AR Invoice INV-2026-00001','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(3,'JE-2026-00003','2026-03-18','INV-2026-00002','ar',2,'AR Invoice INV-2026-00002','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(4,'JE-2026-00004','2026-03-04','INV-2026-00003','ar',3,'AR Invoice INV-2026-00003','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(5,'JE-2026-00005','2026-03-14','INV-2026-00004','ar',4,'AR Invoice INV-2026-00004','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(6,'JE-2026-00006','2026-03-09','INV-2026-00005','ar',5,'AR Invoice INV-2026-00005','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(7,'JE-2026-00007','2026-03-05','BILL-2026-00001','ap',1,'AP Bill BILL-2026-00001','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(8,'JE-2026-00008','2026-03-04','BILL-2026-00002','ap',2,'AP Bill BILL-2026-00002','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(9,'JE-2026-00009','2026-03-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(10,'JE-2026-00010','2026-03-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(11,'JE-2026-00011','2026-03-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(12,'JE-2026-00012','2026-03-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(13,'JE-2026-00013','2026-03-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(14,'JE-2026-00014','2026-03-29','ARCPT-2026-00001','ar',1,'AR Receipt ARCPT-2026-00001','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(15,'JE-2026-00015','2026-03-19','ARCPT-2026-00002','ar',2,'AR Receipt ARCPT-2026-00002','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(16,'JE-2026-00016','2026-03-29','ARCPT-2026-00003','ar',3,'AR Receipt ARCPT-2026-00003','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(17,'JE-2026-00017','2026-03-22','ARCPT-2026-00004','ar',4,'AR Receipt ARCPT-2026-00004','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(18,'JE-2026-00018','2026-04-01','APPMT-2026-00001','ap',1,'AP Payment APPMT-2026-00001','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(19,'JE-2026-00019','2026-04-18','INV-2026-00006','ar',6,'AR Invoice INV-2026-00006','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(20,'JE-2026-00020','2026-04-23','INV-2026-00007','ar',7,'AR Invoice INV-2026-00007','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(21,'JE-2026-00021','2026-04-11','INV-2026-00008','ar',8,'AR Invoice INV-2026-00008','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(22,'JE-2026-00022','2026-04-07','INV-2026-00009','ar',9,'AR Invoice INV-2026-00009','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(23,'JE-2026-00023','2026-04-19','INV-2026-00010','ar',10,'AR Invoice INV-2026-00010','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(24,'JE-2026-00024','2026-04-18','BILL-2026-00003','ap',3,'AP Bill BILL-2026-00003','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(25,'JE-2026-00025','2026-04-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(26,'JE-2026-00026','2026-04-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(27,'JE-2026-00027','2026-04-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(28,'JE-2026-00028','2026-04-16','','cash',NULL,'Marketing and social media ads','Posted',2,2,'2026-09-22 23:27:40','2026-09-22 23:27:40'),(29,'JE-2026-00029','2026-04-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(30,'JE-2026-00030','2026-04-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(31,'JE-2026-00031','2026-04-23','ARCPT-2026-00005','ar',5,'AR Receipt ARCPT-2026-00005','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(32,'JE-2026-00032','2026-05-07','ARCPT-2026-00006','ar',6,'AR Receipt ARCPT-2026-00006','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(33,'JE-2026-00033','2026-04-19','ARCPT-2026-00007','ar',7,'AR Receipt ARCPT-2026-00007','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(34,'JE-2026-00034','2026-05-12','APPMT-2026-00002','ap',2,'AP Payment APPMT-2026-00002','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(35,'JE-2026-00035','2026-05-09','INV-2026-00011','ar',11,'AR Invoice INV-2026-00011','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(36,'JE-2026-00036','2026-05-08','INV-2026-00012','ar',12,'AR Invoice INV-2026-00012','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(37,'JE-2026-00037','2026-05-17','INV-2026-00013','ar',13,'AR Invoice INV-2026-00013','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(38,'JE-2026-00038','2026-05-23','INV-2026-00014','ar',14,'AR Invoice INV-2026-00014','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(39,'JE-2026-00039','2026-05-16','BILL-2026-00004','ap',4,'AP Bill BILL-2026-00004','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(40,'JE-2026-00040','2026-05-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(41,'JE-2026-00041','2026-05-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(42,'JE-2026-00042','2026-05-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(43,'JE-2026-00043','2026-05-16','','cash',NULL,'Marketing and social media ads','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(44,'JE-2026-00044','2026-05-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(45,'JE-2026-00045','2026-05-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(46,'JE-2026-00046','2026-05-21','ARCPT-2026-00008','ar',8,'AR Receipt ARCPT-2026-00008','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(47,'JE-2026-00047','2026-05-23','ARCPT-2026-00009','ar',9,'AR Receipt ARCPT-2026-00009','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(48,'JE-2026-00048','2026-06-07','ARCPT-2026-00010','ar',10,'AR Receipt ARCPT-2026-00010','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(49,'JE-2026-00049','2026-06-08','APPMT-2026-00003','ap',3,'AP Payment APPMT-2026-00003','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(50,'JE-2026-00050','2026-06-21','INV-2026-00015','ar',15,'AR Invoice INV-2026-00015','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(51,'JE-2026-00051','2026-06-10','INV-2026-00016','ar',16,'AR Invoice INV-2026-00016','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(52,'JE-2026-00052','2026-06-18','INV-2026-00017','ar',17,'AR Invoice INV-2026-00017','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(53,'JE-2026-00053','2026-06-21','INV-2026-00018','ar',18,'AR Invoice INV-2026-00018','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(54,'JE-2026-00054','2026-06-04','BILL-2026-00005','ap',5,'AP Bill BILL-2026-00005','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(55,'JE-2026-00055','2026-06-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(56,'JE-2026-00056','2026-06-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(57,'JE-2026-00057','2026-06-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(58,'JE-2026-00058','2026-06-16','','cash',NULL,'Marketing and social media ads','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(59,'JE-2026-00059','2026-06-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(60,'JE-2026-00060','2026-06-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(61,'JE-2026-00061','2026-06-30','ARCPT-2026-00011','ar',11,'AR Receipt ARCPT-2026-00011','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(62,'JE-2026-00062','2026-06-17','ARCPT-2026-00012','ar',12,'AR Receipt ARCPT-2026-00012','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(63,'JE-2026-00063','2026-06-27','ARCPT-2026-00013','ar',13,'AR Receipt ARCPT-2026-00013','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(64,'JE-2026-00064','2026-07-02','ARCPT-2026-00014','ar',14,'AR Receipt ARCPT-2026-00014','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(65,'JE-2026-00065','2026-06-27','APPMT-2026-00004','ap',4,'AP Payment APPMT-2026-00004','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(66,'JE-2026-00066','2026-07-14','INV-2026-00019','ar',19,'AR Invoice INV-2026-00019','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(67,'JE-2026-00067','2026-07-09','INV-2026-00020','ar',20,'AR Invoice INV-2026-00020','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(68,'JE-2026-00068','2026-07-13','INV-2026-00021','ar',21,'AR Invoice INV-2026-00021','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(69,'JE-2026-00069','2026-07-12','INV-2026-00022','ar',22,'AR Invoice INV-2026-00022','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(70,'JE-2026-00070','2026-07-07','INV-2026-00023','ar',23,'AR Invoice INV-2026-00023','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(71,'JE-2026-00071','2026-07-21','BILL-2026-00006','ap',6,'AP Bill BILL-2026-00006','Posted',2,2,'2026-09-22 23:27:41','2026-09-22 23:27:41'),(72,'JE-2026-00072','2026-07-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(73,'JE-2026-00073','2026-07-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(74,'JE-2026-00074','2026-07-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(75,'JE-2026-00075','2026-07-16','','cash',NULL,'Marketing and social media ads','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(76,'JE-2026-00076','2026-07-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(77,'JE-2026-00077','2026-07-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(78,'JE-2026-00078','2026-07-25','ARCPT-2026-00015','ar',15,'AR Receipt ARCPT-2026-00015','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(79,'JE-2026-00079','2026-07-19','ARCPT-2026-00016','ar',16,'AR Receipt ARCPT-2026-00016','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(80,'JE-2026-00080','2026-07-24','ARCPT-2026-00017','ar',17,'AR Receipt ARCPT-2026-00017','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(81,'JE-2026-00081','2026-07-27','ARCPT-2026-00018','ar',18,'AR Receipt ARCPT-2026-00018','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(82,'JE-2026-00082','2026-07-18','ARCPT-2026-00019','ar',19,'AR Receipt ARCPT-2026-00019','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(83,'JE-2026-00083','2026-08-19','APPMT-2026-00005','ap',5,'AP Payment APPMT-2026-00005','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(84,'JE-2026-00084','2026-08-05','INV-2026-00024','ar',24,'AR Invoice INV-2026-00024','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(85,'JE-2026-00085','2026-08-09','INV-2026-00025','ar',25,'AR Invoice INV-2026-00025','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(86,'JE-2026-00086','2026-08-05','INV-2026-00026','ar',26,'AR Invoice INV-2026-00026','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(87,'JE-2026-00087','2026-08-08','INV-2026-00027','ar',27,'AR Invoice INV-2026-00027','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(88,'JE-2026-00088','2026-08-03','INV-2026-00028','ar',28,'AR Invoice INV-2026-00028','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(89,'JE-2026-00089','2026-08-04','INV-2026-00029','ar',29,'AR Invoice INV-2026-00029','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(90,'JE-2026-00090','2026-08-22','BILL-2026-00007','ap',7,'AP Bill BILL-2026-00007','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(91,'JE-2026-00091','2026-08-02','BILL-2026-00008','ap',8,'AP Bill BILL-2026-00008','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(92,'JE-2026-00092','2026-08-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(93,'JE-2026-00093','2026-08-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(94,'JE-2026-00094','2026-08-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(95,'JE-2026-00095','2026-08-16','','cash',NULL,'Marketing and social media ads','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(96,'JE-2026-00096','2026-08-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(97,'JE-2026-00097','2026-08-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(98,'JE-2026-00098','2026-08-15','ARCPT-2026-00020','ar',20,'AR Receipt ARCPT-2026-00020','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(99,'JE-2026-00099','2026-08-15','ARCPT-2026-00021','ar',21,'AR Receipt ARCPT-2026-00021','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(100,'JE-2026-00100','2026-08-15','ARCPT-2026-00022','ar',22,'AR Receipt ARCPT-2026-00022','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(101,'JE-2026-00101','2026-08-19','ARCPT-2026-00023','ar',23,'AR Receipt ARCPT-2026-00023','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(102,'JE-2026-00102','2026-08-10','ARCPT-2026-00024','ar',24,'AR Receipt ARCPT-2026-00024','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(103,'JE-2026-00103','2026-08-13','ARCPT-2026-00025','ar',25,'AR Receipt ARCPT-2026-00025','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(104,'JE-2026-00104','2026-09-13','APPMT-2026-00006','ap',6,'AP Payment APPMT-2026-00006','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(105,'JE-2026-00105','2026-08-29','APPMT-2026-00007','ap',7,'AP Payment APPMT-2026-00007','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(106,'JE-2026-00106','2026-09-03','INV-2026-00030','ar',30,'AR Invoice INV-2026-00030','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(107,'JE-2026-00107','2026-09-22','INV-2026-00031','ar',31,'AR Invoice INV-2026-00031','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(108,'JE-2026-00108','2026-09-09','INV-2026-00032','ar',32,'AR Invoice INV-2026-00032','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(109,'JE-2026-00109','2026-09-02','INV-2026-00033','ar',33,'AR Invoice INV-2026-00033','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(110,'JE-2026-00110','2026-09-06','INV-2026-00034','ar',34,'AR Invoice INV-2026-00034','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(111,'JE-2026-00111','2026-09-13','BILL-2026-00009','ap',9,'AP Bill BILL-2026-00009','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(112,'JE-2026-00112','2026-09-11','BILL-2026-00010','ap',10,'AP Bill BILL-2026-00010','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(113,'JE-2026-00113','2026-09-26','','cash',NULL,'Monthly salaries and wages','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(114,'JE-2026-00114','2026-09-06','','cash',NULL,'Office rent','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(115,'JE-2026-00115','2026-09-11','','cash',NULL,'Utilities (power/water/internet)','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(116,'JE-2026-00116','2026-09-19','','cash',NULL,'Miscellaneous office expense','Posted',2,2,'2026-09-22 23:27:42','2026-09-22 23:27:42'),(117,'JE-2026-00117','2026-09-13','','cash',NULL,'Office supplies purchase','Posted',2,2,'2026-09-22 23:27:43','2026-09-22 23:27:43'),(118,'JE-2026-00118','2026-10-05','','cash',NULL,'Deposit - Bank - BDO','Posted',1,1,'2026-10-05 17:52:44','2026-10-05 17:52:44'),(119,'JE-2026-00119','2026-10-05','','cash',NULL,'Deposit - Bank - BDO','Posted',1,1,'2026-10-05 17:53:09','2026-10-05 17:53:09'),(120,'JE-2026-00120','2026-10-05','','cash',NULL,'E2E typed description test','Posted',1,1,'2026-10-05 17:53:10','2026-10-05 17:53:10');
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_lines`
--

DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_lines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(14,2) NOT NULL DEFAULT 0.00,
  `department_id` int(11) DEFAULT NULL,
  `memo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `department_id` (`department_id`),
  KEY `idx_jl_account` (`account_id`),
  CONSTRAINT `journal_lines_ibfk_1` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_lines_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `coa_accounts` (`id`),
  CONSTRAINT `journal_lines_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=270 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_lines`
--

LOCK TABLES `journal_lines` WRITE;
/*!40000 ALTER TABLE `journal_lines` DISABLE KEYS */;
INSERT INTO `journal_lines` VALUES (1,1,1,50000.00,0.00,NULL,'Opening cash on hand'),(2,1,2,300000.00,0.00,NULL,'Opening bank - BDO'),(3,1,3,150000.00,0.00,NULL,'Opening bank - BPI'),(4,1,14,0.00,500000.00,NULL,'Owner\'s capital - opening'),(5,2,4,21000.00,0.00,NULL,'AR - invoice INV-2026-00001'),(6,2,18,0.00,21000.00,NULL,'Baguio Weekend Getaway'),(7,3,4,44000.00,0.00,NULL,'AR - invoice INV-2026-00002'),(8,3,16,0.00,44000.00,NULL,'Bohol Chocolate Hills Tour'),(9,4,4,64960.00,0.00,NULL,'AR - invoice INV-2026-00003'),(10,4,18,0.00,58000.00,NULL,'Boracay 3D2N Package'),(11,4,10,0.00,6960.00,NULL,'Output tax'),(12,5,4,34720.00,0.00,NULL,'AR - invoice INV-2026-00004'),(13,5,17,0.00,31000.00,NULL,'Cebu City + Beach Package'),(14,5,10,0.00,3720.00,NULL,'Output tax'),(15,6,4,58240.00,0.00,NULL,'AR - invoice INV-2026-00005'),(16,6,18,0.00,52000.00,NULL,'Cebu City + Beach Package'),(17,6,10,0.00,6240.00,NULL,'Output tax'),(18,7,21,27000.00,0.00,NULL,'Tour guide and permit fees'),(19,7,9,0.00,27000.00,NULL,'AP - bill BILL-2026-00001'),(20,8,22,29000.00,0.00,NULL,'Hotel booking settlement'),(21,8,9,0.00,29000.00,NULL,'AP - bill BILL-2026-00002'),(22,9,24,88520.00,0.00,NULL,'Monthly salaries and wages'),(23,9,3,0.00,88520.00,NULL,'Monthly salaries and wages'),(24,10,26,20000.00,0.00,NULL,'Office rent'),(25,10,2,0.00,20000.00,NULL,'Office rent'),(26,11,27,8306.00,0.00,NULL,'Utilities (power/water/internet)'),(27,11,2,0.00,8306.00,NULL,'Utilities (power/water/internet)'),(28,12,32,1611.00,0.00,NULL,'Miscellaneous office expense'),(29,12,1,0.00,1611.00,NULL,'Miscellaneous office expense'),(30,13,28,3824.00,0.00,NULL,'Office supplies purchase'),(31,13,1,0.00,3824.00,NULL,'Office supplies purchase'),(32,14,2,44000.00,0.00,NULL,'Cash received'),(33,14,4,0.00,44000.00,NULL,'AR receipt'),(34,15,1,64960.00,0.00,NULL,'Cash received'),(35,15,4,0.00,64960.00,NULL,'AR receipt'),(36,16,1,34720.00,0.00,NULL,'Cash received'),(37,16,4,0.00,34720.00,NULL,'AR receipt'),(38,17,2,58240.00,0.00,NULL,'Cash received'),(39,17,4,0.00,58240.00,NULL,'AR receipt'),(40,18,9,27000.00,0.00,NULL,'AP payment'),(41,18,1,0.00,27000.00,NULL,'Cash paid'),(42,19,4,24640.00,0.00,NULL,'AR - invoice INV-2026-00006'),(43,19,18,0.00,22000.00,NULL,'Baguio Weekend Getaway'),(44,19,10,0.00,2640.00,NULL,'Output tax'),(45,20,4,36960.00,0.00,NULL,'AR - invoice INV-2026-00007'),(46,20,18,0.00,33000.00,NULL,'Cebu City + Beach Package'),(47,20,10,0.00,3960.00,NULL,'Output tax'),(48,21,4,63840.00,0.00,NULL,'AR - invoice INV-2026-00008'),(49,21,18,0.00,57000.00,NULL,'Baguio Weekend Getaway'),(50,21,10,0.00,6840.00,NULL,'Output tax'),(51,22,4,23520.00,0.00,NULL,'AR - invoice INV-2026-00009'),(52,22,18,0.00,21000.00,NULL,'Boracay 3D2N Package'),(53,22,10,0.00,2520.00,NULL,'Output tax'),(54,23,4,37000.00,0.00,NULL,'AR - invoice INV-2026-00010'),(55,23,17,0.00,37000.00,NULL,'Boracay 3D2N Package'),(56,24,22,15000.00,0.00,NULL,'Air ticketing services'),(57,24,5,1800.00,0.00,NULL,'Input tax'),(58,24,9,0.00,16800.00,NULL,'AP - bill BILL-2026-00003'),(59,25,24,89847.00,0.00,NULL,'Monthly salaries and wages'),(60,25,2,0.00,89847.00,NULL,'Monthly salaries and wages'),(61,26,26,20000.00,0.00,NULL,'Office rent'),(62,26,3,0.00,20000.00,NULL,'Office rent'),(63,27,27,6841.00,0.00,NULL,'Utilities (power/water/internet)'),(64,27,3,0.00,6841.00,NULL,'Utilities (power/water/internet)'),(65,28,29,15661.00,0.00,NULL,'Marketing and social media ads'),(66,28,2,0.00,15661.00,NULL,'Marketing and social media ads'),(67,29,32,1287.00,0.00,NULL,'Miscellaneous office expense'),(68,29,1,0.00,1287.00,NULL,'Miscellaneous office expense'),(69,30,28,5587.00,0.00,NULL,'Office supplies purchase'),(70,30,1,0.00,5587.00,NULL,'Office supplies purchase'),(71,31,3,12320.00,0.00,NULL,'Cash received'),(72,31,4,0.00,12320.00,NULL,'AR receipt'),(73,32,3,36960.00,0.00,NULL,'Cash received'),(74,32,4,0.00,36960.00,NULL,'AR receipt'),(75,33,3,63840.00,0.00,NULL,'Cash received'),(76,33,4,0.00,63840.00,NULL,'AR receipt'),(77,34,9,16800.00,0.00,NULL,'AP payment'),(78,34,1,0.00,16800.00,NULL,'Cash paid'),(79,35,4,50400.00,0.00,NULL,'AR - invoice INV-2026-00011'),(80,35,18,0.00,45000.00,NULL,'Cebu City + Beach Package'),(81,35,10,0.00,5400.00,NULL,'Output tax'),(82,36,4,50000.00,0.00,NULL,'AR - invoice INV-2026-00012'),(83,36,16,0.00,50000.00,NULL,'Cebu City + Beach Package'),(84,37,4,23520.00,0.00,NULL,'AR - invoice INV-2026-00013'),(85,37,18,0.00,21000.00,NULL,'Palawan Island Hopping Tour'),(86,37,10,0.00,2520.00,NULL,'Output tax'),(87,38,4,50400.00,0.00,NULL,'AR - invoice INV-2026-00014'),(88,38,18,0.00,45000.00,NULL,'Cebu City + Beach Package'),(89,38,10,0.00,5400.00,NULL,'Output tax'),(90,39,21,21000.00,0.00,NULL,'Land transport arrangement'),(91,39,9,0.00,21000.00,NULL,'AP - bill BILL-2026-00004'),(92,40,24,89071.00,0.00,NULL,'Monthly salaries and wages'),(93,40,3,0.00,89071.00,NULL,'Monthly salaries and wages'),(94,41,26,20000.00,0.00,NULL,'Office rent'),(95,41,2,0.00,20000.00,NULL,'Office rent'),(96,42,27,8572.00,0.00,NULL,'Utilities (power/water/internet)'),(97,42,2,0.00,8572.00,NULL,'Utilities (power/water/internet)'),(98,43,29,10646.00,0.00,NULL,'Marketing and social media ads'),(99,43,3,0.00,10646.00,NULL,'Marketing and social media ads'),(100,44,32,977.00,0.00,NULL,'Miscellaneous office expense'),(101,44,1,0.00,977.00,NULL,'Miscellaneous office expense'),(102,45,28,4768.00,0.00,NULL,'Office supplies purchase'),(103,45,1,0.00,4768.00,NULL,'Office supplies purchase'),(104,46,3,50400.00,0.00,NULL,'Cash received'),(105,46,4,0.00,50400.00,NULL,'AR receipt'),(106,47,3,23520.00,0.00,NULL,'Cash received'),(107,47,4,0.00,23520.00,NULL,'AR receipt'),(108,48,1,50400.00,0.00,NULL,'Cash received'),(109,48,4,0.00,50400.00,NULL,'AR receipt'),(110,49,9,21000.00,0.00,NULL,'AP payment'),(111,49,3,0.00,21000.00,NULL,'Cash paid'),(112,50,4,28000.00,0.00,NULL,'AR - invoice INV-2026-00015'),(113,50,16,0.00,28000.00,NULL,'Bohol Chocolate Hills Tour'),(114,51,4,60480.00,0.00,NULL,'AR - invoice INV-2026-00016'),(115,51,16,0.00,54000.00,NULL,'Cebu City + Beach Package'),(116,51,10,0.00,6480.00,NULL,'Output tax'),(117,52,4,48000.00,0.00,NULL,'AR - invoice INV-2026-00017'),(118,52,16,0.00,48000.00,NULL,'Bohol Chocolate Hills Tour'),(119,53,4,45920.00,0.00,NULL,'AR - invoice INV-2026-00018'),(120,53,18,0.00,41000.00,NULL,'Cebu City + Beach Package'),(121,53,10,0.00,4920.00,NULL,'Output tax'),(122,54,21,15000.00,0.00,NULL,'Land transport arrangement'),(123,54,5,1800.00,0.00,NULL,'Input tax'),(124,54,9,0.00,16800.00,NULL,'AP - bill BILL-2026-00005'),(125,55,24,86893.00,0.00,NULL,'Monthly salaries and wages'),(126,55,2,0.00,86893.00,NULL,'Monthly salaries and wages'),(127,56,26,20000.00,0.00,NULL,'Office rent'),(128,56,3,0.00,20000.00,NULL,'Office rent'),(129,57,27,7542.00,0.00,NULL,'Utilities (power/water/internet)'),(130,57,3,0.00,7542.00,NULL,'Utilities (power/water/internet)'),(131,58,29,13774.00,0.00,NULL,'Marketing and social media ads'),(132,58,2,0.00,13774.00,NULL,'Marketing and social media ads'),(133,59,32,1076.00,0.00,NULL,'Miscellaneous office expense'),(134,59,1,0.00,1076.00,NULL,'Miscellaneous office expense'),(135,60,28,5692.00,0.00,NULL,'Office supplies purchase'),(136,60,1,0.00,5692.00,NULL,'Office supplies purchase'),(137,61,2,28000.00,0.00,NULL,'Cash received'),(138,61,4,0.00,28000.00,NULL,'AR receipt'),(139,62,1,60480.00,0.00,NULL,'Cash received'),(140,62,4,0.00,60480.00,NULL,'AR receipt'),(141,63,1,48000.00,0.00,NULL,'Cash received'),(142,63,4,0.00,48000.00,NULL,'AR receipt'),(143,64,1,45920.00,0.00,NULL,'Cash received'),(144,64,4,0.00,45920.00,NULL,'AR receipt'),(145,65,9,16800.00,0.00,NULL,'AP payment'),(146,65,1,0.00,16800.00,NULL,'Cash paid'),(147,66,4,49000.00,0.00,NULL,'AR - invoice INV-2026-00019'),(148,66,17,0.00,49000.00,NULL,'Boracay 3D2N Package'),(149,67,4,66080.00,0.00,NULL,'AR - invoice INV-2026-00020'),(150,67,17,0.00,59000.00,NULL,'Palawan Island Hopping Tour'),(151,67,10,0.00,7080.00,NULL,'Output tax'),(152,68,4,48160.00,0.00,NULL,'AR - invoice INV-2026-00021'),(153,68,16,0.00,43000.00,NULL,'Cebu City + Beach Package'),(154,68,10,0.00,5160.00,NULL,'Output tax'),(155,69,4,42000.00,0.00,NULL,'AR - invoice INV-2026-00022'),(156,69,16,0.00,42000.00,NULL,'Palawan Island Hopping Tour'),(157,70,4,28000.00,0.00,NULL,'AR - invoice INV-2026-00023'),(158,70,17,0.00,25000.00,NULL,'Bohol Chocolate Hills Tour'),(159,70,10,0.00,3000.00,NULL,'Output tax'),(160,71,22,19000.00,0.00,NULL,'Tour guide and permit fees'),(161,71,5,2280.00,0.00,NULL,'Input tax'),(162,71,9,0.00,21280.00,NULL,'AP - bill BILL-2026-00006'),(163,72,24,89652.00,0.00,NULL,'Monthly salaries and wages'),(164,72,3,0.00,89652.00,NULL,'Monthly salaries and wages'),(165,73,26,20000.00,0.00,NULL,'Office rent'),(166,73,2,0.00,20000.00,NULL,'Office rent'),(167,74,27,7388.00,0.00,NULL,'Utilities (power/water/internet)'),(168,74,2,0.00,7388.00,NULL,'Utilities (power/water/internet)'),(169,75,29,10340.00,0.00,NULL,'Marketing and social media ads'),(170,75,3,0.00,10340.00,NULL,'Marketing and social media ads'),(171,76,32,636.00,0.00,NULL,'Miscellaneous office expense'),(172,76,1,0.00,636.00,NULL,'Miscellaneous office expense'),(173,77,28,4000.00,0.00,NULL,'Office supplies purchase'),(174,77,1,0.00,4000.00,NULL,'Office supplies purchase'),(175,78,3,49000.00,0.00,NULL,'Cash received'),(176,78,4,0.00,49000.00,NULL,'AR receipt'),(177,79,1,33040.00,0.00,NULL,'Cash received'),(178,79,4,0.00,33040.00,NULL,'AR receipt'),(179,80,1,48160.00,0.00,NULL,'Cash received'),(180,80,4,0.00,48160.00,NULL,'AR receipt'),(181,81,2,42000.00,0.00,NULL,'Cash received'),(182,81,4,0.00,42000.00,NULL,'AR receipt'),(183,82,2,28000.00,0.00,NULL,'Cash received'),(184,82,4,0.00,28000.00,NULL,'AR receipt'),(185,83,9,21280.00,0.00,NULL,'AP payment'),(186,83,3,0.00,21280.00,NULL,'Cash paid'),(187,84,4,68000.00,0.00,NULL,'AR - invoice INV-2026-00024'),(188,84,16,0.00,68000.00,NULL,'Cebu City + Beach Package'),(189,85,4,41440.00,0.00,NULL,'AR - invoice INV-2026-00025'),(190,85,16,0.00,37000.00,NULL,'Palawan Island Hopping Tour'),(191,85,10,0.00,4440.00,NULL,'Output tax'),(192,86,4,32480.00,0.00,NULL,'AR - invoice INV-2026-00026'),(193,86,16,0.00,29000.00,NULL,'Bohol Chocolate Hills Tour'),(194,86,10,0.00,3480.00,NULL,'Output tax'),(195,87,4,46000.00,0.00,NULL,'AR - invoice INV-2026-00027'),(196,87,17,0.00,46000.00,NULL,'Cebu City + Beach Package'),(197,88,4,61600.00,0.00,NULL,'AR - invoice INV-2026-00028'),(198,88,16,0.00,55000.00,NULL,'Cebu City + Beach Package'),(199,88,10,0.00,6600.00,NULL,'Output tax'),(200,89,4,69440.00,0.00,NULL,'AR - invoice INV-2026-00029'),(201,89,18,0.00,62000.00,NULL,'Baguio Weekend Getaway'),(202,89,10,0.00,7440.00,NULL,'Output tax'),(203,90,22,23000.00,0.00,NULL,'Hotel booking settlement'),(204,90,9,0.00,23000.00,NULL,'AP - bill BILL-2026-00007'),(205,91,23,12000.00,0.00,NULL,'Air ticketing services'),(206,91,5,1440.00,0.00,NULL,'Input tax'),(207,91,9,0.00,13440.00,NULL,'AP - bill BILL-2026-00008'),(208,92,24,87679.00,0.00,NULL,'Monthly salaries and wages'),(209,92,2,0.00,87679.00,NULL,'Monthly salaries and wages'),(210,93,26,20000.00,0.00,NULL,'Office rent'),(211,93,3,0.00,20000.00,NULL,'Office rent'),(212,94,27,7066.00,0.00,NULL,'Utilities (power/water/internet)'),(213,94,3,0.00,7066.00,NULL,'Utilities (power/water/internet)'),(214,95,29,10745.00,0.00,NULL,'Marketing and social media ads'),(215,95,2,0.00,10745.00,NULL,'Marketing and social media ads'),(216,96,32,709.00,0.00,NULL,'Miscellaneous office expense'),(217,96,1,0.00,709.00,NULL,'Miscellaneous office expense'),(218,97,28,3851.00,0.00,NULL,'Office supplies purchase'),(219,97,1,0.00,3851.00,NULL,'Office supplies purchase'),(220,98,3,68000.00,0.00,NULL,'Cash received'),(221,98,4,0.00,68000.00,NULL,'AR receipt'),(222,99,1,41440.00,0.00,NULL,'Cash received'),(223,99,4,0.00,41440.00,NULL,'AR receipt'),(224,100,3,16240.00,0.00,NULL,'Cash received'),(225,100,4,0.00,16240.00,NULL,'AR receipt'),(226,101,1,46000.00,0.00,NULL,'Cash received'),(227,101,4,0.00,46000.00,NULL,'AR receipt'),(228,102,3,30800.00,0.00,NULL,'Cash received'),(229,102,4,0.00,30800.00,NULL,'AR receipt'),(230,103,1,69440.00,0.00,NULL,'Cash received'),(231,103,4,0.00,69440.00,NULL,'AR receipt'),(232,104,9,23000.00,0.00,NULL,'AP payment'),(233,104,2,0.00,23000.00,NULL,'Cash paid'),(234,105,9,13440.00,0.00,NULL,'AP payment'),(235,105,2,0.00,13440.00,NULL,'Cash paid'),(236,106,4,32000.00,0.00,NULL,'AR - invoice INV-2026-00030'),(237,106,16,0.00,32000.00,NULL,'Boracay 3D2N Package'),(238,107,4,26000.00,0.00,NULL,'AR - invoice INV-2026-00031'),(239,107,17,0.00,26000.00,NULL,'Palawan Island Hopping Tour'),(240,108,4,77280.00,0.00,NULL,'AR - invoice INV-2026-00032'),(241,108,16,0.00,69000.00,NULL,'Bohol Chocolate Hills Tour'),(242,108,10,0.00,8280.00,NULL,'Output tax'),(243,109,4,54000.00,0.00,NULL,'AR - invoice INV-2026-00033'),(244,109,17,0.00,54000.00,NULL,'Cebu City + Beach Package'),(245,110,4,29120.00,0.00,NULL,'AR - invoice INV-2026-00034'),(246,110,18,0.00,26000.00,NULL,'Boracay 3D2N Package'),(247,110,10,0.00,3120.00,NULL,'Output tax'),(248,111,23,30000.00,0.00,NULL,'Air ticketing services'),(249,111,5,3600.00,0.00,NULL,'Input tax'),(250,111,9,0.00,33600.00,NULL,'AP - bill BILL-2026-00009'),(251,112,23,15000.00,0.00,NULL,'Air ticketing services'),(252,112,5,1800.00,0.00,NULL,'Input tax'),(253,112,9,0.00,16800.00,NULL,'AP - bill BILL-2026-00010'),(254,113,24,86741.00,0.00,NULL,'Monthly salaries and wages'),(255,113,3,0.00,86741.00,NULL,'Monthly salaries and wages'),(256,114,26,20000.00,0.00,NULL,'Office rent'),(257,114,2,0.00,20000.00,NULL,'Office rent'),(258,115,27,7740.00,0.00,NULL,'Utilities (power/water/internet)'),(259,115,2,0.00,7740.00,NULL,'Utilities (power/water/internet)'),(260,116,32,1155.00,0.00,NULL,'Miscellaneous office expense'),(261,116,1,0.00,1155.00,NULL,'Miscellaneous office expense'),(262,117,28,5987.00,0.00,NULL,'Office supplies purchase'),(263,117,1,0.00,5987.00,NULL,'Office supplies purchase'),(264,118,2,123.45,0.00,NULL,'Deposit - Bank - BDO'),(265,118,1,0.00,123.45,NULL,'Deposit - Bank - BDO'),(266,119,2,123.45,0.00,NULL,'Deposit - Bank - BDO'),(267,119,1,0.00,123.45,NULL,'Deposit - Bank - BDO'),(268,120,1,42.10,0.00,NULL,'E2E typed description test'),(269,120,2,0.00,42.10,NULL,'E2E typed description test');
/*!40000 ALTER TABLE `journal_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_otps`
--

DROP TABLE IF EXISTS `password_reset_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `purpose` varchar(30) NOT NULL DEFAULT 'password_reset',
  `code_hash` varchar(255) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `max_attempts` int(10) unsigned NOT NULL DEFAULT 5,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pr_user` (`user_id`),
  KEY `idx_pr_expires` (`expires_at`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_otps`
--

LOCK TABLES `password_reset_otps` WRITE;
/*!40000 ALTER TABLE `password_reset_otps` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_otps` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module_key` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_module_action` (`module_key`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard','view','View dashboard'),(2,'gl','view','View general ledger'),(3,'gl','create','Create GL records'),(4,'gl','post','Approve/post journal entries'),(5,'ap','view','View accounts payable'),(6,'ap','create','Create AP records'),(7,'ap','approve','Approve AP bills/payments'),(8,'ar','view','View accounts receivable'),(9,'ar','create','Create AR records'),(10,'ar','approve','Approve AR invoices/receipts'),(11,'disbursement','view','View disbursement vouchers'),(12,'disbursement','create','Create disbursement vouchers'),(13,'disbursement','approve','Approve/pay disbursement vouchers'),(14,'collection','view','View collection receipts'),(15,'collection','create','Create collection receipts'),(16,'collection','approve','Approve/deposit collection receipts'),(17,'budget','view','View budgets'),(18,'budget','create','Create budgets'),(19,'budget','approve','Approve budgets'),(20,'cash','view','View cash management'),(21,'cash','create','Create cash transactions/transfers'),(22,'cash','approve','Approve reconciliations'),(23,'tax','view','View tax management'),(24,'tax','create','Create tax records'),(25,'tax','approve','Approve tax remittances'),(26,'reports','view','View financial reports'),(27,'users','view','View users'),(28,'users','create','Create/edit users'),(29,'settings','view','View system settings'),(30,'settings','create','Edit system settings'),(31,'audit','view','View audit log'),(32,'assistant','view','Use AI financial assistant');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (2,1),(2,2),(2,3),(2,5),(2,6),(2,8),(2,9),(2,11),(2,12),(2,14),(2,15),(2,17),(2,18),(2,20),(2,21),(2,23),(2,24),(2,26),(2,32),(3,1),(3,2),(3,4),(3,5),(3,7),(3,8),(3,10),(3,11),(3,13),(3,14),(3,16),(3,17),(3,19),(3,20),(3,22),(3,23),(3,25),(3,26),(3,32),(4,1),(4,2),(4,5),(4,8),(4,11),(4,14),(4,17),(4,20),(4,23),(4,26),(4,27),(4,29),(4,31),(4,32);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','Full system access including user management and settings'),(2,'Accountant','Creates and edits transactional records; cannot approve'),(3,'Approver','Approves/rejects transactions and views reports'),(4,'Auditor','Read-only access to all modules and the audit log');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
INSERT INTO `system_settings` VALUES ('ap_control_account_id','9',NULL),('ap_overdue_pct_threshold','30','AP 60+/90+ bucket as % of total AP that triggers a Warning'),('ar_aging_pct_threshold','20','AR 90+ bucket as % of total AR that triggers a Warning'),('ar_control_account_id','4',NULL),('budget_critical_pct','100','Budget utilization % that triggers a Critical alert'),('budget_warning_pct','90','Budget utilization % that triggers a Warning'),('cash_runway_days_threshold','60','Minimum healthy cash runway in days before a Critical alert fires'),('input_tax_account_id','5',NULL),('output_tax_account_id','10',NULL),('upcoming_payable_days','30','Look-ahead window (days) for the insufficient-cash-for-payables rule'),('withholding_tax_payable_account_id','11',NULL);
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_remittances`
--

DROP TABLE IF EXISTS `tax_remittances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_remittances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remittance_no` varchar(30) NOT NULL,
  `tax_type_id` int(11) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_amount` decimal(14,2) NOT NULL,
  `remittance_date` date DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `journal_entry_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `remittance_no` (`remittance_no`),
  KEY `tax_type_id` (`tax_type_id`),
  KEY `journal_entry_id` (`journal_entry_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `tax_remittances_ibfk_1` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`),
  CONSTRAINT `tax_remittances_ibfk_2` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tax_remittances_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_remittances`
--

LOCK TABLES `tax_remittances` WRITE;
/*!40000 ALTER TABLE `tax_remittances` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_remittances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_transactions`
--

DROP TABLE IF EXISTS `tax_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tax_type_id` int(11) NOT NULL,
  `source_module` varchar(10) NOT NULL,
  `source_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `taxable_amount` decimal(14,2) NOT NULL,
  `tax_amount` decimal(14,2) NOT NULL,
  `direction` varchar(15) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `remittance_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tax_type_id` (`tax_type_id`),
  KEY `remittance_id` (`remittance_id`),
  CONSTRAINT `tax_transactions_ibfk_1` FOREIGN KEY (`tax_type_id`) REFERENCES `tax_types` (`id`),
  CONSTRAINT `tax_transactions_ibfk_2` FOREIGN KEY (`remittance_id`) REFERENCES `tax_remittances` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_transactions`
--

LOCK TABLES `tax_transactions` WRITE;
/*!40000 ALTER TABLE `tax_transactions` DISABLE KEYS */;
INSERT INTO `tax_transactions` VALUES (1,1,'AR',3,'2026-03-04',58000.00,6960.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(2,1,'AR',4,'2026-03-14',31000.00,3720.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(3,1,'AR',5,'2026-03-09',52000.00,6240.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(4,1,'AR',6,'2026-04-18',22000.00,2640.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(5,1,'AR',7,'2026-04-23',33000.00,3960.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(6,1,'AR',8,'2026-04-11',57000.00,6840.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(7,1,'AR',9,'2026-04-07',21000.00,2520.00,'Output','Pending',NULL,'2026-09-22 23:27:40'),(8,1,'AP',3,'2026-04-18',15000.00,1800.00,'Input','Pending',NULL,'2026-09-22 23:27:40'),(9,1,'AR',11,'2026-05-09',45000.00,5400.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(10,1,'AR',13,'2026-05-17',21000.00,2520.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(11,1,'AR',14,'2026-05-23',45000.00,5400.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(12,1,'AR',16,'2026-06-10',54000.00,6480.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(13,1,'AR',18,'2026-06-21',41000.00,4920.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(14,1,'AP',5,'2026-06-04',15000.00,1800.00,'Input','Pending',NULL,'2026-09-22 23:27:41'),(15,1,'AR',20,'2026-07-09',59000.00,7080.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(16,1,'AR',21,'2026-07-13',43000.00,5160.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(17,1,'AR',23,'2026-07-07',25000.00,3000.00,'Output','Pending',NULL,'2026-09-22 23:27:41'),(18,1,'AP',6,'2026-07-21',19000.00,2280.00,'Input','Pending',NULL,'2026-09-22 23:27:41'),(19,1,'AR',25,'2026-08-09',37000.00,4440.00,'Output','Pending',NULL,'2026-09-22 23:27:42'),(20,1,'AR',26,'2026-08-05',29000.00,3480.00,'Output','Pending',NULL,'2026-09-22 23:27:42'),(21,1,'AR',28,'2026-08-03',55000.00,6600.00,'Output','Pending',NULL,'2026-09-22 23:27:42'),(22,1,'AR',29,'2026-08-04',62000.00,7440.00,'Output','Pending',NULL,'2026-09-22 23:27:42'),(23,1,'AP',8,'2026-08-02',12000.00,1440.00,'Input','Pending',NULL,'2026-09-22 23:27:42'),(24,1,'AR',32,'2026-09-09',69000.00,8280.00,'Output','Pending',NULL,'2026-09-22 23:27:42'),(25,1,'AR',34,'2026-09-06',26000.00,3120.00,'Output','Pending',NULL,'2026-09-22 23:27:42'),(26,1,'AP',9,'2026-09-13',30000.00,3600.00,'Input','Pending',NULL,'2026-09-22 23:27:42'),(27,1,'AP',10,'2026-09-11',15000.00,1800.00,'Input','Pending',NULL,'2026-09-22 23:27:42');
/*!40000 ALTER TABLE `tax_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_types`
--

DROP TABLE IF EXISTS `tax_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `rate_percent` decimal(6,3) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_types`
--

LOCK TABLES `tax_types` WRITE;
/*!40000 ALTER TABLE `tax_types` DISABLE KEYS */;
INSERT INTO `tax_types` VALUES (1,'VAT','Value Added Tax',12.000,1),(2,'WHT','Withholding Tax',2.000,1);
/*!40000 ALTER TABLE `tax_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','rvincetimothy@gmail.com','$2y$10$b2U6gXykdMnf9C73V9hgmORg8YqZu0DqhsrlFRcozugrGovdokmGe','System Administrators',1,'Active',NULL,'2026-09-22 23:27:39'),(2,'accountant','maddyperez22111@gmail.com','$2y$10$b2U6gXykdMnf9C73V9hgmORg8YqZu0DqhsrlFRcozugrGovdokmGe','Ana Contadora',2,'Active',NULL,'2026-09-22 23:27:39'),(3,'approver','vlmnzn.fnc@gmail.com','$2y$10$b2U6gXykdMnf9C73V9hgmORg8YqZu0DqhsrlFRcozugrGovdokmGe','Marco Aprobado',3,'Active',NULL,'2026-09-22 23:27:39'),(4,'auditor','romerojanvincetimothy@gmail.com','$2y$10$b2U6gXykdMnf9C73V9hgmORg8YqZu0DqhsrlFRcozugrGovdokmGe','Ivy Auditor',4,'Active',NULL,'2026-09-22 23:27:39');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'travelcore_fms'
--

--
-- Dumping routines for database 'travelcore_fms'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-06 19:55:59


-- ==============================================================================
-- PART 3 : update-user-emails.sql (idempotent - re-applied on every import)
-- ==============================================================================
-- The dumped users already carry the corrected addresses below, but these
-- statements are kept so this file is a true union of schema.sql + seed.php
-- data + update-user-emails.sql, and so re-importing self-heals the OTP emails.

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'rvincetimothy@gmail.com'
WHERE r.name = 'Admin' AND u.username = 'admin';

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'vlmnzn.fnc@gmail.com'
WHERE r.name = 'Approver' AND u.username = 'approver';

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'maddyperez22111@gmail.com'
WHERE r.name = 'Accountant' AND u.username = 'accountant';

UPDATE users u JOIN roles r ON r.id = u.role_id
SET u.email = 'romerojanvincetimothy@gmail.com'
WHERE r.name = 'Auditor' AND u.username = 'auditor';
