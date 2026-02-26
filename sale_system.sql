-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Feb 26, 2026 at 12:41 PM
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
(3, 'Stock Keeper', 'stock', '1234', 'Inventory', 0, '2026-02-26 09:51:47', NULL, NULL, NULL),
(4, 'Charlie K.', 'sales', '1234', 'Sales', 1, '2026-02-26 10:40:08', 1, 1, '2026-02-26 10:40:13');

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
  `issued_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Invoices`
--

INSERT INTO `Invoices` (`invoice_id`, `order_id`, `invoice_type`, `invoice_date`, `payment_status`, `issued_by`) VALUES
(1, 1, 'Direct', '2026-02-26 10:59:08', 'Paid', 1),
(2, 2, 'Standard', '2026-02-26 12:19:54', 'Paid', 1);

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
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Orders`
--

INSERT INTO `Orders` (`order_id`, `po_ref`, `order_type`, `customer_id`, `employee_id`, `order_date`, `total_amount`, `discount_amount`, `net_total`, `status`, `updated_at`) VALUES
(1, 'POS-20260226-1059', 'Direct', 1, 1, '2026-02-26 10:59:08', 1200.00, 0.00, 1200.00, 'Shipped', NULL),
(2, 'PO-2026-001', 'Standard', 2, 1, '2026-02-26 11:19:51', 36000.00, 3600.00, 32400.00, 'Shipped', '2026-02-26 12:19:54'),
(3, 'PO-2026-002', 'Standard', 2, 1, '2026-02-26 12:22:05', 49500.00, 0.00, 49500.00, 'Cancelled', '2026-02-26 12:22:52');

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
(1, 1, 2, 1, 1200.00, 1200.00),
(2, 2, 2, 30, 1200.00, 36000.00),
(3, 3, 3, 11, 4500.00, 49500.00);

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
(1, 'Mechanical Keyboard', NULL, 1500.00, 2500.00, 31, 0, '2026-02-26 12:29:24', NULL, NULL),
(2, 'Gaming Mouse', NULL, 800.00, 1200.00, 19, 0, '2026-02-26 12:19:54', NULL, NULL),
(3, 'Monitor 24inch', NULL, 3000.00, 4500.00, 10, 0, NULL, NULL, NULL),
(4, 'Charlie K.', NULL, 100.00, 120.00, 20, 1, '2026-02-26 10:32:21', 1, '2026-02-26 10:32:21');

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
(1, 4, 20, 'Restock', 1, NULL, '2026-02-26 10:16:28'),
(2, 2, -1, 'Sale', 1, 1, '2026-02-26 10:59:08'),
(3, 2, -30, 'Sale', 1, 2, '2026-02-26 12:19:54'),
(4, 1, 11, 'Restock', 1, NULL, '2026-02-26 12:29:24');

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
  MODIFY `invoice_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `Orders`
--
ALTER TABLE `Orders`
  MODIFY `order_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `Order_Details`
--
ALTER TABLE `Order_Details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `Products`
--
ALTER TABLE `Products`
  MODIFY `product_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
