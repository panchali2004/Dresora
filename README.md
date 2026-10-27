# 👗 Dresora – Online Dress Rental System

## 📌 Project Overview

**Dresora** is a web-based dress rental management system developed to make renting dresses easier and more convenient.

The system allows customers to browse dresses, view dress details, select rental dates, add dresses to a shopping cart, and place rental orders online.

It also provides an admin panel to manage dresses, customer orders, and rental activities.

This project was developed as part of the **ICT2142 – E-Business Systems** course at the **Faculty of Technology, University of Ruhuna**.

## 🎯 Objectives

- Provide an easy online platform for renting dresses.
- Allow customers to search and select dresses.
- Simplify the dress rental and ordering process.
- Help administrators manage dresses and customer orders.
- Store customer, dress, and order information in a database.

## ✨ Main Features

### 👤 Customer Features

- User registration and login
- Browse available dresses
- Search and filter dresses by category
- View dress details, sizes, and rental prices
- Select rental dates
- Add dresses to the shopping cart
- Place rental orders
- Choose a payment method
- View order history and order status
- Manage customer account details

### 🔐 Admin Features

- Admin login and dashboard
- Add new dresses
- Edit existing dress details
- Delete dresses
- Manage dress categories
- View customer orders
- Confirm or cancel orders
- Update order and payment status
- Manage rental activities

## 🛠️ Technologies Used

| Technology | Purpose |
|---|---|
| HTML | Web page structure |
| CSS | Website design and styling |
| JavaScript | Client-side interactions |
| PHP | Server-side development |
| MySQL | Database management |
| XAMPP | Local development server |
| phpMyAdmin | Database administration |

## 📂 Main Project Files

```text
Dresora/
│
├── index.php
├── products.php
├── product-details.php
├── about.php
├── contact.php
├── cart.php
├── checkout.php
├── account.php
│
├── customer/
│   └── orders.php
│
├── admin/
│   └── dashboard.php
│
└── README.md
```

*This structure shows the main files. Additional PHP files, images, stylesheets, and configuration files are included in the project.*

## 🗄️ Database

The system uses a MySQL database to store and manage information.

Main database tables include:

- `users` – Stores customer and admin details.
- `categories` – Stores dress categories.
- `dresses` – Stores dress information.
- `cart_items` – Stores shopping cart items.
- `orders` – Stores customer rental orders.
- `order_items` – Stores ordered dress details.
- `notifications` – Stores system notifications.

## ⚙️ Installation and Setup

### Step 1: Install XAMPP

Download and install XAMPP.

### Step 2: Start the Server

Open XAMPP Control Panel and start:

- Apache
- MySQL

### Step 3: Copy the Project

Copy the Dresora project folder into:

```text
C:\xampp\htdocs\
```

### Step 4: Set Up the Database

1. Open `http://localhost/phpmyadmin`
2. Create the project database.
3. Import the project's SQL database file.
4. Configure the PHP database connection with the correct database name, username, and password.

### Step 5: Run the Project

Open a web browser and visit:

```text
http://localhost/Dresora/
```

## 🛒 How the System Works

1. Customers register or log in to the website.
2. Customers browse dresses and view their details.
3. Customers select dresses, sizes, and rental dates.
4. Selected dresses are added to the shopping cart.
5. Customers proceed to checkout and place an order.
6. The order information is stored in the database.
7. The administrator reviews and manages orders.
8. Customers can view their order status.

## 💳 Payment Methods

The system provides the following payment options:

- Cash on Delivery
- Online Payment

## 📅 Rental Management

- Customers can select rental dates.
- The system supports a five-day rental period.
- Late returns may include an additional charge of **Rs. 500 per day**.
- Administrators can manage rental order statuses.

## 🚀 Future Improvements

- Integrate a secure online payment gateway.
- Add email and SMS notifications.
- Improve dress availability tracking.
- Add customer reviews and ratings.
- Develop a mobile application.

## 🎓 Academic Information

**Project Name:** Dresora – Online Dress Rental System

**Course:** ICT2142 – E-Business Systems

**Degree Programme:** Bachelor of Information and Communication Technology (BICT)

**Department:** Department of ICT

**Faculty:** Faculty of Technology

**University:** University of Ruhuna

## 📄 Conclusion

Dresora is an online dress rental system that helps customers rent dresses conveniently. It reduces the need to visit physical stores and makes the rental process easier.

The system also helps administrators manage dress information, customer orders, and rental activities efficiently.

---

**Developed for ICT2142 – E-Business Systems | University of Ruhuna**