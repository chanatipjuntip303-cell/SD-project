-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Feb 14, 2026 at 01:41 PM
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
  `contact_name` varchar(45) NOT NULL,
  `address` varchar(45) NOT NULL,
  `membership_level` enum('standard','premium') NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Customers`
--

INSERT INTO `Customers` (`customer_id`, `contact_name`, `address`, `membership_level`, `is_deleted`, `created_by`, `updated_by`) VALUES
(1, 'Global Fashion Co.', 'New York, USA', 'premium', 0, NULL, NULL),
(2, 'City Boutique', 'London, UK', 'standard', 0, NULL, NULL),
(3, 'Trendsetters Inc.', 'Tokyo, Japan', 'premium', 0, NULL, NULL),
(4, 'Urban Wear Ltd.', 'Sydney, Australia', 'standard', 0, NULL, NULL),
(5, 'Elite Styles', 'Paris, France', 'premium', 0, NULL, NULL),
(6, 'alamak', 'thailand', 'premium', 1, NULL, NULL),
(7, 'alamak', '3', 'standard', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `Employees`
--

CREATE TABLE `Employees` (
  `employee_ID` int NOT NULL,
  `employee_name` varchar(45) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `position` varchar(45) NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Employees`
--

INSERT INTO `Employees` (`employee_ID`, `employee_name`, `username`, `password`, `position`, `is_deleted`, `created_by`, `updated_by`) VALUES
(1, 'John Smith', 'admin', '1234', 'Manager', 0, NULL, NULL),
(2, 'Sarah Jones', 'sale', '1234', 'Sales', 0, NULL, NULL),
(3, 'Michael Brown', 'stock', '1234', 'Inventory Control', 0, NULL, NULL),
(4, 'Emily Davis', NULL, NULL, 'Senior Sales', 0, NULL, NULL),
(5, 'David Wilson', NULL, NULL, 'Junior Sales', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Invoices`
--

CREATE TABLE `Invoices` (
  `Invoice_id` int NOT NULL,
  `discount_member` decimal(10,2) NOT NULL,
  `discount_special` decimal(10,2) NOT NULL,
  `grand_total` decimal(20,2) NOT NULL,
  `payment_status` varchar(45) NOT NULL,
  `Orders_Order_ID` int NOT NULL,
  `billing_date` date NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Invoices`
--

INSERT INTO `Invoices` (`Invoice_id`, `discount_member`, `discount_special`, `grand_total`, `payment_status`, `Orders_Order_ID`, `billing_date`, `is_deleted`, `updated_by`, `updated_at`) VALUES
(201, 600.00, 1200.00, 10200.00, 'Cash', 101, '2026-03-01', 0, NULL, NULL),
(202, 0.00, 0.00, 4250.00, 'Cash', 102, '2026-03-02', 0, NULL, NULL),
(203, 250.00, 0.00, 4750.00, 'Cash', 103, '2026-03-03', 0, NULL, NULL),
(204, 0.00, 1375.00, 12375.00, 'Cash', 104, '2026-03-04', 0, NULL, NULL),
(205, 650.00, 1300.00, 11050.00, 'Cash', 105, '2026-03-05', 0, NULL, NULL),
(206, 0.00, 2500.00, 22500.00, 'Cash', 106, '2026-02-12', 0, NULL, NULL),
(207, 706750.00, 1413500.00, 12014750.00, 'Cash', 107, '2026-02-12', 0, NULL, NULL),
(208, 112500.00, 225000.00, 1912500.00, 'Cash', 108, '2026-02-13', 0, NULL, NULL),
(209, 1200.00, 2400.00, 20400.00, 'Cash', 109, '2026-02-13', 0, NULL, NULL),
(210, 1200.00, 2400.00, 20400.00, 'Pending', 110, '2026-02-13', 0, 2, '2026-02-14 13:39:37'),
(211, 60.00, 0.00, 1140.00, 'Paid', 111, '2026-02-14', 0, 1, '2026-02-14 13:36:31');

-- --------------------------------------------------------

--
-- Table structure for table `Orders`
--

CREATE TABLE `Orders` (
  `Order_ID` int NOT NULL,
  `PO_reference` varchar(45) NOT NULL,
  `order_date` date NOT NULL,
  `total_before_discount` decimal(10,2) NOT NULL,
  `Employees_employee_ID` int NOT NULL,
  `Customers_customer_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Orders`
--

INSERT INTO `Orders` (`Order_ID`, `PO_reference`, `order_date`, `total_before_discount`, `Employees_employee_ID`, `Customers_customer_id`) VALUES
(101, '1001', '2026-03-01', 12000.00, 2, 1),
(102, '1002', '2026-03-02', 4250.00, 3, 2),
(103, '1003', '2026-03-03', 5000.00, 4, 3),
(104, '1004', '2026-03-04', 13750.00, 5, 4),
(105, '1005', '2026-03-05', 13000.00, 1, 5),
(106, 'PO-001', '2026-02-12', 25000.00, 1, 1),
(107, 'PO-002', '2026-02-12', 14135000.00, 3, 1),
(108, 'PO-003', '2026-02-13', 2250000.00, 5, 5),
(109, 'PO-004', '2026-02-13', 24000.00, 1, 1),
(110, 'PO-005', '2026-02-13', 24000.00, 1, 3),
(111, 'PO-006', '2026-02-14', 1200.00, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `Order_details`
--

CREATE TABLE `Order_details` (
  `detail_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `Orders_Order_ID` int NOT NULL,
  `Products_product_ID` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Order_details`
--

INSERT INTO `Order_details` (`detail_id`, `quantity`, `unit_price`, `Orders_Order_ID`, `Products_product_ID`) VALUES
(1, 10, 1200.00, 101, 1),
(2, 5, 850.00, 102, 2),
(3, 2, 2500.00, 103, 4),
(4, 25, 550.00, 104, 5),
(5, 10, 850.00, 105, 2),
(6, 10, 2500.00, 106, 4),
(7, 11000, 1200.00, 107, 1),
(8, 1100, 850.00, 107, 2),
(9, 5000, 450.00, 108, 3),
(10, 20, 1200.00, 109, 1),
(11, 20, 1200.00, 110, 1),
(12, 1, 1200.00, 111, 1);

-- --------------------------------------------------------

--
-- Table structure for table `Products`
--

CREATE TABLE `Products` (
  `product_ID` int NOT NULL,
  `product_name` varchar(45) NOT NULL,
  `description` varchar(45) NOT NULL,
  `price_per_unit` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `amount` int NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Products`
--

INSERT INTO `Products` (`product_ID`, `product_name`, `description`, `price_per_unit`, `cost`, `amount`, `is_deleted`, `created_by`) VALUES
(1, 'Denim Jeans', 'Slim fit blue denim', 1200.00, 600.00, 41, 0, NULL),
(2, 'White Shirt', 'Cotton formal shirt', 850.00, 400.00, 100, 0, NULL),
(3, 'Leather Belt', 'Genuine brown leather', 450.00, 150.00, 30, 0, NULL),
(4, 'Sneakers', 'Casual sports shoes', 2500.00, 1100.00, 25, 0, NULL),
(5, 'Polo T-Shirt', 'Classic fit polo', 550.00, 200.00, 150, 0, NULL),
(7, 'alamak', 'asaaddad', 120.00, 100.00, 100, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `Stock_Logs`
--

CREATE TABLE `Stock_Logs` (
  `log_id` int NOT NULL,
  `qty_added` int NOT NULL,
  `log_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `Products_product_ID` int DEFAULT NULL,
  `Employees_employee_ID` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `Stock_Logs`
--

INSERT INTO `Stock_Logs` (`log_id`, `qty_added`, `log_date`, `Products_product_ID`, `Employees_employee_ID`) VALUES
(1, 20, '2026-02-13 15:04:18', 1, 3),
(2, 1, '2026-02-14 13:15:03', 1, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `Customers`
--
ALTER TABLE `Customers`
  ADD PRIMARY KEY (`customer_id`);

--
-- Indexes for table `Employees`
--
ALTER TABLE `Employees`
  ADD PRIMARY KEY (`employee_ID`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `Invoices`
--
ALTER TABLE `Invoices`
  ADD PRIMARY KEY (`Invoice_id`),
  ADD KEY `fk_Invoices_Orders1_idx` (`Orders_Order_ID`);

--
-- Indexes for table `Orders`
--
ALTER TABLE `Orders`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `fk_Orders_Employees_idx` (`Employees_employee_ID`),
  ADD KEY `fk_Orders_Customers1_idx` (`Customers_customer_id`);

--
-- Indexes for table `Order_details`
--
ALTER TABLE `Order_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_Order_details_Orders1_idx` (`Orders_Order_ID`),
  ADD KEY `fk_Order_details_Products1_idx` (`Products_product_ID`);

--
-- Indexes for table `Products`
--
ALTER TABLE `Products`
  ADD PRIMARY KEY (`product_ID`);

--
-- Indexes for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `Products_product_ID` (`Products_product_ID`),
  ADD KEY `Employees_employee_ID` (`Employees_employee_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `Customers`
--
ALTER TABLE `Customers`
  MODIFY `customer_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `Employees`
--
ALTER TABLE `Employees`
  MODIFY `employee_ID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `Invoices`
--
ALTER TABLE `Invoices`
  MODIFY `Invoice_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=212;

--
-- AUTO_INCREMENT for table `Orders`
--
ALTER TABLE `Orders`
  MODIFY `Order_ID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `Order_details`
--
ALTER TABLE `Order_details`
  MODIFY `detail_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `Products`
--
ALTER TABLE `Products`
  MODIFY `product_ID` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `Invoices`
--
ALTER TABLE `Invoices`
  ADD CONSTRAINT `fk_Invoices_Orders1` FOREIGN KEY (`Orders_Order_ID`) REFERENCES `Orders` (`Order_ID`);

--
-- Constraints for table `Orders`
--
ALTER TABLE `Orders`
  ADD CONSTRAINT `fk_Orders_Customers1` FOREIGN KEY (`Customers_customer_id`) REFERENCES `Customers` (`customer_id`),
  ADD CONSTRAINT `fk_Orders_Employees` FOREIGN KEY (`Employees_employee_ID`) REFERENCES `Employees` (`employee_ID`);

--
-- Constraints for table `Order_details`
--
ALTER TABLE `Order_details`
  ADD CONSTRAINT `fk_Order_details_Orders1` FOREIGN KEY (`Orders_Order_ID`) REFERENCES `Orders` (`Order_ID`),
  ADD CONSTRAINT `fk_Order_details_Products1` FOREIGN KEY (`Products_product_ID`) REFERENCES `Products` (`product_ID`);

--
-- Constraints for table `Stock_Logs`
--
ALTER TABLE `Stock_Logs`
  ADD CONSTRAINT `Stock_Logs_ibfk_1` FOREIGN KEY (`Products_product_ID`) REFERENCES `Products` (`product_ID`),
  ADD CONSTRAINT `Stock_Logs_ibfk_2` FOREIGN KEY (`Employees_employee_ID`) REFERENCES `Employees` (`employee_ID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
