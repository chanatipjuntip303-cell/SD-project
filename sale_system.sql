-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Mar 04, 2026 at 06:22 PM
-- Server version: 8.0.45
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sale_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `Customers`
--

CREATE TABLE `Customers` (
  `customer_id` int NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `address` text,
  `membership_level` enum('Standard','Premium') DEFAULT 'Standard',
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Customers`
--

INSERT INTO `Customers` (`customer_id`, `contact_name`, `address`, `membership_level`, `is_deleted`, `created_by`, `created_at`, `deleted_by`, `deleted_at`) VALUES
(1, 'General Customer (Walk-in)', 'Store Front', 'Standard', 0, 1, '2026-02-26 09:51:47', NULL, NULL),
(2, 'Charlie K.', 'LA', 'Standard', 1, 1, '2026-02-26 10:21:27', 1, '2026-02-26 12:31:07'),
(3, 'jame', 'alabama', 'Premium', 0, 1, '2026-02-26 11:18:05', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Employees`
--

CREATE TABLE `Employees` (
  `employee_id` int NOT NULL,
  `employee_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Manager','Sales','Inventory') NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Employees`
--

INSERT INTO `Employees` (`employee_id`, `employee_name`, `username`, `password`, `role`, `is_deleted`, `created_at`, `created_by`, `deleted_by`, `deleted_at`) VALUES
(1, 'Admin Manager', 'admin', '1234', 'Manager', 0, '2026-02-26 09:51:47', NULL, NULL, NULL),
(2, 'Sales Person', 'sale', '1234', 'Sales', 0, '2026-02-26 09:51:47', NULL, NULL, NULL),
(3, 'Stock Keeper', 'stock', '1234', 'Inventory', 0, '2026-02-26 09:51:47', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Invoices`
--

CREATE TABLE `Invoices` (
  `invoice_id` int NOT NULL,
  `order_id` int NOT NULL,
  `invoice_type` enum('Standard','Direct') DEFAULT 'Standard',
  `invoice_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `payment_status` enum('Pending','Paid','Cash') DEFAULT 'Pending',
  `issued_by` int DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'Cash',
  `payment_date` datetime DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount_received` decimal(10,2) DEFAULT NULL,
  `received_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Invoices`
--

INSERT INTO `Invoices` (`invoice_id`, `order_id`, `invoice_type`, `invoice_date`, `payment_status`, `issued_by`, `payment_method`, `payment_date`, `transaction_id`, `amount_received`, `received_by`) VALUES
(5, 9, 'Direct', '2026-03-04 09:41:55', 'Paid', 1, 'Cash', NULL, NULL, NULL, NULL),
(6, 10, 'Standard', '2026-03-04 09:43:24', 'Paid', 1, 'Cash', '2026-03-04 16:43:00', 'REC-1234', 9154.50, 1),
(7, 11, 'Standard', '2026-03-04 17:58:18', 'Paid', 1, 'Cash', '2026-03-05 00:58:00', 'REC-1235', 7624.50, 1);

-- --------------------------------------------------------

--
-- Table structure for table `Orders`
--

CREATE TABLE `Orders` (
  `order_id` int NOT NULL,
  `po_ref` varchar(50) DEFAULT NULL,
  `order_type` enum('Standard','Direct') DEFAULT 'Standard',
  `customer_id` int NOT NULL,
  `employee_id` int NOT NULL,
  `order_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `discount_amount` decimal(10,2) DEFAULT '0.00',
  `net_total` decimal(10,2) DEFAULT '0.00',
  `status` enum('Pending','Shipped','Cancelled') DEFAULT 'Pending',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `shipping_company` varchar(100) DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Orders`
--

INSERT INTO `Orders` (`order_id`, `po_ref`, `order_type`, `customer_id`, `employee_id`, `order_date`, `total_amount`, `discount_amount`, `net_total`, `status`, `updated_at`, `updated_by`, `is_deleted`, `deleted_by`, `deleted_at`, `shipping_company`, `tracking_number`) VALUES
(9, 'POS-20260304-0941', 'Direct', 1, 1, '2026-03-04 09:41:55', 399.00, 0.00, 399.00, 'Shipped', NULL, NULL, 0, NULL, NULL, NULL, NULL),
(10, 'PO-2026-001', 'Standard', 3, 1, '2026-03-04 09:43:15', 10770.00, 1615.50, 9154.50, 'Shipped', '2026-03-04 18:00:35', NULL, 0, NULL, NULL, 'Kerry Express', 'TH1233'),
(11, 'PO-2026-002', 'Standard', 3, 1, '2026-03-04 17:56:28', 8970.00, 1345.50, 7624.50, 'Shipped', '2026-03-04 17:58:18', NULL, 0, NULL, NULL, 'Kerry Express', 'TH1234');

-- --------------------------------------------------------

--
-- Table structure for table `Order_Details`
--

CREATE TABLE `Order_Details` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `qty` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Order_Details`
--

INSERT INTO `Order_Details` (`id`, `order_id`, `product_id`, `qty`, `unit_price`, `subtotal`) VALUES
(10, 9, 2, 1, 399.00, 399.00),
(11, 10, 4, 30, 359.00, 10770.00),
(12, 11, 5, 30, 299.00, 8970.00);

-- --------------------------------------------------------

--
-- Table structure for table `Products`
--

CREATE TABLE `Products` (
  `product_id` int NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `description` text,
  `cost` decimal(10,2) DEFAULT '0.00',
  `price` decimal(10,2) DEFAULT '0.00',
  `stock_qty` int DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Products`
--

INSERT INTO `Products` (`product_id`, `product_name`, `description`, `cost`, `price`, `stock_qty`, `is_deleted`, `updated_at`, `deleted_by`, `deleted_at`) VALUES
(1, 'Revitalizing Toner', 'ปลอบประโลมผืวหลังอาบน้ำ เติมความชุ่มชื้น ลดความแห้งกร้าน', 399.20, 499.00, 68, 0, '2026-03-04 09:42:32', NULL, NULL),
(2, 'Deep Moisturizing Cream', 'เติมความชุ่มชื้นเข้าสู่ชั้นใต้ผิว เหมาะสำหรับผู้ที่มีปัญหาผิวแห้งกร้านหรือขาดน้ำ', 319.20, 399.00, 56, 0, '2026-03-04 09:42:27', NULL, NULL),
(3, 'Intensive Serum', 'เซรั่มสูตรเข้มข้นพิเศษที่ออกแบบมาเพื่อการฟื้นบำรุงผิวอย่างล้ำลึกและเร่งด่วนในข้ามคืน ด้วยเทคโนโลยี Lanna-DeepClean เพื่อนำส่งสารสกัดจากพืชพรรณท้องถิ่นเข้าสู่ชั้นผิวได้อย่างมีประสิทธิภาพสูงสุด', 319.20, 399.00, 60, 0, '2026-03-04 09:42:21', NULL, NULL),
(4, 'Daily Protection Sunscreen', 'ปกป้องผิวจากแสงแดด พร้อมสัมผัสที่บางเบา ไม่เหนียวเหนอะหนะ และเป็นมิตรต่อสิ่งแวดล้อม', 287.20, 359.00, 40, 0, '2026-03-04 09:43:24', 1, '2026-02-26 10:32:21'),
(5, 'Gentle Face Scrub', 'ผลัดเซลล์ผิวอย่างอ่อนโยน เผยผิวกระจ่างใสด้วยวัตถุดิบทางธรรมชาติ', 239.20, 299.00, 70, 0, '2026-03-04 17:58:18', 1, '2026-03-03 18:31:27'),
(6, 'Facial Clean Clear', 'คืนความสดชื่นให้ผิวหน้าด้วยพลังพืชพรรณท้องถิ่น ช่วยทำความสะอาดผิวอย่างหมดจดโดยไม่ทำลายน้ำหล่อเลี้ยงผิวตามธรรมชาติ ให้ผิวหน้าเนียนนุ่ม ไม่แห้งตึง', 207.20, 259.00, 30, 0, '2026-03-04 09:29:58', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Stock_Logs`
--

CREATE TABLE `Stock_Logs` (
  `log_id` int NOT NULL,
  `product_id` int NOT NULL,
  `qty_change` int NOT NULL,
  `log_type` enum('Restock','Sale','Adjustment','Cancel_Restock') NOT NULL,
  `employee_id` int NOT NULL,
  `related_order_id` int DEFAULT NULL,
  `log_date` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Stock_Logs`
--

INSERT INTO `Stock_Logs` (`log_id`, `product_id`, `qty_change`, `log_type`, `employee_id`, `related_order_id`, `log_date`) VALUES
(9, 2, -1, 'Sale', 1, 9, '2026-03-04 09:41:55'),
(10, 4, 50, 'Restock', 1, NULL, '2026-03-04 09:42:14'),
(11, 3, 50, 'Restock', 1, NULL, '2026-03-04 09:42:21'),
(12, 2, 50, 'Restock', 1, NULL, '2026-03-04 09:42:27'),
(13, 1, 50, 'Restock', 1, NULL, '2026-03-04 09:42:32'),
(14, 4, -30, 'Sale', 1, 10, '2026-03-04 09:43:24'),
(15, 5, -30, 'Sale', 1, 11, '2026-03-04 17:58:18');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Customers`
--
ALTER TABLE `Customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `Employees`
--
ALTER TABLE `Employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `Invoices`
--
ALTER TABLE `Invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `issued_by` (`issued_by`);

--
-- Indexes for table `Orders`
--
ALTER TABLE `Orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `Order_Details`
--
ALTER TABLE `Order_Details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `Products`
--
ALTER TABLE `Products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Customers`
--
ALTER TABLE `Customers`
  MODIFY `customer_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `Employees`
--
ALTER TABLE `Employees`
  MODIFY `employee_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `Invoices`
--
ALTER TABLE `Invoices`
  MODIFY `invoice_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `Orders`
--
ALTER TABLE `Orders`
  MODIFY `order_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `Order_Details`
--
ALTER TABLE `Order_Details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `Products`
--
ALTER TABLE `Products`
  MODIFY `product_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Customers`
--
ALTER TABLE `Customers`
  ADD CONSTRAINT `Customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `Employees` (`employee_id`);

--
-- Constraints for table `Invoices`
--
ALTER TABLE `Invoices`
  ADD CONSTRAINT `Invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `Orders` (`order_id`),
  ADD CONSTRAINT `Invoices_ibfk_2` FOREIGN KEY (`issued_by`) REFERENCES `Employees` (`employee_id`);

--
-- Constraints for table `Orders`
--
ALTER TABLE `Orders`
  ADD CONSTRAINT `Orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `Customers` (`customer_id`),
  ADD CONSTRAINT `Orders_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `Employees` (`employee_id`);

--
-- Constraints for table `Order_Details`
--
ALTER TABLE `Order_Details`
  ADD CONSTRAINT `Order_Details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `Orders` (`order_id`),
  ADD CONSTRAINT `Order_Details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `Products` (`product_id`);

--
-- Constraints for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  ADD CONSTRAINT `Stock_Logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `Products` (`product_id`),
  ADD CONSTRAINT `Stock_Logs_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `Employees` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
