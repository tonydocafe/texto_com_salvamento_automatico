# 📝 Automatically Save Text with XAMPP

This project allows you to automatically save text entered in an input field to a **MySQL** database using **PHP** and **JavaScript**. Saving occurs every **2 seconds after the last entry**.

The system uses **XAMPP** to provide a local environment with **Apache, PHP and MySQL**.

## 📌 Requirements

- **XAMPP** installed
- **Apache and MySQL enabled in XAMPP**
- **MySQL database configured**

## 🛠️Configuration

### 1️⃣ Start XAMPP
1. Open the **XAMPP Control Panel**
2. Start **Apache and MySQL**

### 2️⃣ Create the Database
1. Access **phpMyAdmin** at:
http://localhost/phpmyadmin/

PHP
----------------------------------------------------------------------------
The PHP script receives a POST request, checks if there is a field called text in the request and tries to store this content in a MySQL database, ensuring that there are no duplicates.

.JS
------------------------------------------------------------------------------
JavaScript implements a system for automatic and manual saving of text entered by the user, sending it to a server via fetch(). It avoids repeated submissions and provides visual feedback to the user.
