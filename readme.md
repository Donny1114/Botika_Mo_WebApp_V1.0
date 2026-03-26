# Pharmacy POS System (Offline PHP Web App)

## Project Description

Pharmacy POS System developed by Donny  is an offline PHP-based web application designed for managing a pharmacy store.
The system allows the user to manage products, inventory, orders, sales, profit reports, Z-reading, invoices, and customer transactions.

This project is useful for small pharmacies that need a local/offline POS system without internet connection.

Features:

* Product management
* Inventory tracking
* Order / Sales system
* Profit reports
* Z-reading reports
* Invoice PDF generator
* CSV / PDF export
* Low stock alert
* Expiry date alert
* Offline local server support

The system runs using:

* PHP
* MySQL
* Composer packages
* TCPDF
* PHPMailer
* Dotenv
* Laragon local server



## Installation / Setup Guide

Follow the steps below to install all required software and PHP modules for the Pharmacy POS System.

This project runs offline using Laragon, MySQL, PHP, and Composer packages.

---

## 1. Install Laragon (Local Server)

Download Laragon:

https://laragon.org/download/

Install Laragon with default settings.

After installing:

Open Laragon
Click Start All

Make sure these are running:

* Apache
* MySQL

Project folder should be placed in:

C:\laragon\www\

Example:

C:\laragon\www\pharmacy_pos

---

## 2. Install MySQL Database

Open Laragon

Menu → MySQL → phpMyAdmin

Create database:

pharmacy_pos

Import database file:

database.sql

Make sure db.php is configured:

```
$host = "localhost";
$user = "root";
$pass = "";
$db   = "pharmacy_db_botika_mo";
```

---

## 3. Install Composer

Download Composer:

https://getcomposer.org/download/

Install Composer for Windows.

After install, check:

```
composer -v
```

If version shows → Composer installed correctly. In the project root folder delete the whats inside the vendor folder, then in cmdsame directory run composer install, thi will install dependencies(takingfrom composer.json and composer.lock)

---

## 4. Open Project in Terminal

Open Laragon

Right click → Terminal

Go to project folder:

```
cd C:\laragon\www\Botika_mo_V1.0
```

(or your project name)

---

## 5. Initialize Composer (if needed)

Run:

```
composer init
```

Press Enter for defaults.

This will create:

composer.json

---

## 6. Install Required PHP Modules

Run the following commands one by one.

### Install PHPMailer

```
composer require phpmailer/phpmailer
```

---

### Install TCPDF

```
composer require tecnickcom/tcpdf
```

---

### Install Dotenv

```
composer require vlucas/phpdotenv
```

---

### Install Graham Campbell Manager

```
composer require graham-campbell/manager
```

---

### Install phpoption

```
composer require phpoption/phpoption
```

---

### Install Symfony Components

```
composer require symfony/polyfill-mbstring
composer require symfony/polyfill-ctype
composer require symfony/deprecation-contracts
```

---

## 7. Install All Dependencies at Once (Alternative)

If composer.json exists, run:

```
composer install
```

This will install all modules automatically.

---

## 8. Check vendor folder

After install, you must have:

vendor/
vendor/autoload.php

Your PHP files must include:

```
require __DIR__ . '/vendor/autoload.php';
```

Example:

```
require_once __DIR__ . '/../vendor/autoload.php';
```

---

## 9. Setup .env file

Create file:

.env

Example:

```
MAIL_HOST=smtp.gmail.com
MAIL_USER=your@email.com
MAIL_PASS=yourpassword
MAIL_PORT=587
```

Load dotenv in PHP:

```
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

---

## 10. Start Application

Start Laragon

Open browser:

http://localhost/botika_mo_v1.0

Login to admin panel.

---

## 11. Generate PDF (TCPDF)

Make sure this exists:

```
require_once __DIR__ . '/../vendor/autoload.php';
```

Used in:

invoice.php
reports
exports

---

## 12. Send Email (PHPMailer)

Make sure PHPMailer installed.

Example:

```
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
```

---

## 13. Important Notes

* This system is OFFLINE
* Internet not required
* Runs on Laragon local server
* Works on Windows

Required software:

* Laragon
* PHP 8+
* MySQL
* Composer
* Apache

---

## 14. Troubleshooting

If error:

vendor/autoload.php not found

Run:

```
composer install
```

If MySQL error:

Check db.php

If TCPDF error:

Remove extra spaces before <?php

If PDF error:

Use ob_start();

---

## 15. Recommended Folder Structure

```
Botika_mo_V1.0/
│
├── admin/
├── assets/
├── vendor/
├── .env
├── composer.json
├── db.php
├── index.php
├── README.md
```

---


For phone or tablet acess

## ✅ 1. Start Laragon services

Open **Laragon**

Make sure running:

* Apache (or Nginx)
* MySQL

You should see:

```
Apache: Running
MySQL: Running
```

---

## ✅ 2. Find your PC IP address

Open CMD:

```
ipconfig
```

Find:

```
IPv4 Address
```

Example:

```
192.168.1.8
```

Your POS will be:

```
http://192.168.1.8/pharmacy
```

OR if using Laragon auto virtual host:

```
http://pharmacy.test
```

But phone cannot use `.test`, so use IP.

---

## ✅ 3. Allow external access in Laragon (IMPORTANT)

Laragon by default allows only localhost.

Open:

```
Laragon → Menu → Apache → httpd.conf
```

Find:

```
Require local
```

Change to:

```
Require all granted
```

OR find:

```
<Directory />
```

Make sure:

```
Require all granted
```

Save and restart Apache.

---

## ✅ 4. Allow Windows Firewall

Open:

```
Windows Defender Firewall
```

→ Advanced settings
→ Inbound rules
→ Allow Apache

Laragon Apache path usually:

```
C:\laragon\bin\apache\httpd-2.4...\bin\httpd.exe
```

Allow:

✔ Private
✔ Public

---

## ✅ 5. Make sure phone is on same WiFi

PC and phone must be on same router.

```
PC → WiFi router
Phone → same WiFi
```

Not mobile data.

---

## ✅ 6. Open from phone

If project folder:

```
C:\laragon\www\pharmacy
```

Open on phone:

```
http://192.168.1.8/pharmacy
```

If using Laragon auto host:

```
C:\laragon\www\pos
```

Still use IP:

```
http://192.168.1.8/pos
```

---

## ✅ 7. Best setup for Pharmacy POS

Recommended:

✔ Static IP
✔ Local network only
✔ No internet access
✔ Firewall enabled
✔ Daily backup (you already did 👍)

Static IP example:

```
192.168.1.10
```

Then always:

```
http://192.168.1.10/pharmacy
```

