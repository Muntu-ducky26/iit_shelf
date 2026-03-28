# 🚀 Quick Start Guide

## Backend is Ready!

### ✅ What's Installed:
- PHP 8.5.1 (with PDO MySQL support)
- MariaDB (MySQL compatible)
- Complete REST API

### 🎯 Server Status:
**PHP Server:** Running on http://localhost:8000  
**Database:** iit_shelf (MariaDB)

---

## 🔧 Quick Commands

### Start/Stop Server
```bash
# Start server
cd backend && ./start_server.sh

# Stop server
killall php

# View logs
tail -f /tmp/php_server.log
```

### Test API
```bash
cd backend && ./test_api.sh
```

---

## 📚 API Endpoints

### Base URL: `http://localhost:8000`

#### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register.php` | Register new user |
| POST | `/api/auth/login.php` | User login |

#### Books
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/books/get_books.php` | Get all books |
| POST | `/api/books/add_book.php` | Add new book |

#### Borrow/Return
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/borrow/borrow_book.php` | Borrow a book |
| POST | `/api/borrow/return_book.php` | Return a book |

---

## 🧪 Quick Test

```bash
# Test get books
curl http://localhost:8000/api/books/get_books.php

# Test login (default accounts)
curl -X POST http://localhost:8000/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"librarian@iit.edu","password":"password"}'
```

---

## 👥 Default Accounts

| Email | Password | Role |
|-------|----------|------|
| librarian@iit.edu | password | librarian |
| director@iit.edu | password | director |
| student@iit.edu | password123 | student |

---

## 🔗 Web Frontend Integration

### Example API call:
```javascript
async function login(email, password) {
  const response = await fetch('http://localhost:8000/api/auth/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) {
    throw new Error('Login failed');
  }

  return response.json();
}

async function getBooks() {
  const response = await fetch('http://localhost:8000/api/books/get_books.php');

  if (!response.ok) {
    throw new Error('Failed to fetch books');
  }

  return response.json();
}
```

---

## 🌐 Local Network Testing

If you need to access the backend from another device on your network, replace `localhost` with your computer's IP address:

```text
http://192.168.1.100:8000
```

---

## 🔍 Troubleshooting

### Server not responding?
```bash
# Check if running
ps aux | grep "php -S"

# Restart
cd backend && ./start_server.sh
```

### Database errors?
```bash
# Check MariaDB status
sudo systemctl status mariadb

# Restart MariaDB
sudo systemctl restart mariadb
```

### Can't connect from phone?
```bash
# Make sure phone is on same network
# Use your computer's IP instead of localhost
# Firewall: sudo ufw allow 8000
```

---

## 📁 Project Structure

```
backend/
├── api/
│   ├── auth/          # Authentication endpoints
│   ├── books/         # Book management
│   └── borrow/        # Borrow/return operations
├── config/
│   └── database.php   # Database configuration
├── database/
│   └── schema.sql     # Database schema
├── start_server.sh    # Start backend server
├── test_api.sh        # Test all endpoints
└── README.md          # Full documentation
```

---

## 🎉 Next Steps

1. ✅ Backend is running
2. 📱 Update Flutter app to use the API
3. 🧪 Test with the mobile app
4. 🚀 Deploy to production server (optional)

**Happy Coding! 🎉**
