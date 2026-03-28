# Event Reservation System

A modern, full-stack event reservation web application built with **Symfony 7**, featuring passwordless authentication via **WebAuthn Passkeys**, automated confirmation emails via **Resend**, and a sleek expressive dark-mode / light-mode UI.

---

## ✨ Features

- **Event Management**: Create, edit, and delete events with multi-image upload support and drag-and-drop.
- **Seat Reservations**: Users can reserve seats with real-time availability checks and capacity enforcement.
- **JWT Authentication**: Stateless token-based authentication using `lexik/jwt-authentication-bundle`.
- **Passkey / WebAuthn**: Passwordless login and device registration using biometrics or hardware security keys (`web-auth/webauthn-symfony-bundle`).
- **Resend Email Integration**: Automated, beautifully styled reservation confirmation emails sent via the [Resend](https://resend.com) API. The API key is securely stored in the database and configurable from the Admin Settings panel - no hardcoded secrets.
- **Admin Dashboard**: Full admin panel with event CRUD, reservation statistics (total/upcoming/ongoing/passed), and a Settings section for mail configuration.
- **Dark/Light Theme**: System-wide theme toggle accessible from the navbar, persisted in `localStorage`.
- **Smooth Animations**: Hardware-accelerated modal transitions, crossfading login tabs, and fade-up effects.

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.4 / Symfony 7.4 |
| Database | PostgreSQL 16 |
| Auth | JWT (`lexik/jwt-authentication-bundle`) + WebAuthn Passkeys |
| Email | [Resend](https://resend.com) (`resend/resend-php`) |
| Frontend | Vanilla JS, Twig, CSS |
| Containerization | Docker / Podman with Nginx reverse proxy |

---

## 🚀 Getting Started

### Prerequisites
- Docker or Podman + podman-compose
- (Optional) PHP 8.2+ and Composer for local development

### Quick Start (Docker)
```bash
git clone https://github.com/SlamZDank/Event-Reservation.git
cd Event-Reservation

# Copy and configure environment
cp .env .env.local
# Edit .env.local with your database credentials and domain

# Build and start all services
podman compose up -d --build
# or: docker compose up -d --build
```

The `docker-init.sh` entrypoint will automatically:
1. Generate JWT signing keys if they don't exist
2. Create upload directories
3. Warm the Symfony cache
4. Run database migrations

Access the app at **https://localhost:8443**.

### Local Development (without Docker)
```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # seed sample data
php -S 0.0.0.0:8000 router.php
```

---

## 📧 Resend Email Integration

Reservation confirmation emails are sent automatically when a user books a seat. The integration uses the [Resend](https://resend.com) transactional email API.

**How it works:**
1. Navigate to **Admin → Settings** in the dashboard.
2. Enter your Resend API key and click **Save Configuration**.
3. The key is securely stored in the database (never hardcoded).
4. When a reservation is created, a styled HTML email is rendered from `templates/email/reservation_confirmation.html.twig` and dispatched to the user.
5. If the email fails to send, the reservation is **not** saved and the user is prompted to contact an administrator.

---

## 📁 Project Structure

```
src/
├── Controller/
│   ├── AuthController.php          
│   ├── PasskeyController.php       
│   ├── EventController.php        
│   ├── ReservationController.php   
│   ├── AdminSettingsController.php 
│   └── PageController.php         
├── Entity/
│   ├─ User.php 
│   ├─ Event.php 
│   ├─ Reservation.php
│   ├─ Setting.php                
│   └── WebauthnCredential.php     
└── Repository/
templates/
├── page/          
├── email/         
└── _nav.html.twig 
```

---

**Med Amine Slama | ING-A2-04 | ISSAT Sousse**
