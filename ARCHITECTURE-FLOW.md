# QR Attendance System - Architecture Flow

## System Overview
The QR Attendance System is a web-based application for managing event attendance using QR codes.

## Architecture Layers

### 1. **Presentation Layer (Frontend)**
- User Interface built with HTML, CSS, JavaScript
- Responsive design for desktop and mobile
- Real-time QR code scanning interface

### 2. **Business Logic Layer**
- Event management (create, update, delete)
- User authentication and authorization
- Attendance tracking and reporting
- QR code generation and validation

### 3. **Data Layer**
- MySQL database
- User management
- Event data storage
- Attendance records
- Device information logging

## Core Flow

### User Authentication Flow
```
Login Page → Database Verification → Session Creation → Dashboard
     ↓
   Invalid Credentials → Error Message
```

### Event Management Flow
```
Create Event → Database Storage → Generate QR Code → Event Dashboard
     ↓
Browse Events → Join Event → Receive Event Code
```

### Attendance Tracking Flow
```
User Opens Scan Interface → Camera/QR Reading → Validate Code → Record Attendance
     ↓
Save to Database → Update Statistics
```

### Reporting Flow
```
Event Creator → Reports Page → Display Attendance Statistics → Export/View Data
```

## Key Components

### Files & Functions

| File | Purpose |
|------|---------|
| `login.php` | User authentication |
| `dashboard.php` | Main user dashboard |
| `events.php` | Browse available events |
| `create-event.php` | Event creation interface |
| `event.php` | Event details page |
| `scan.php` | QR code scanning interface |
| `attendance.php` | View attendance records |
| `report.php` | Generate reports |
| `generate-qr.php` | QR code generation API |
| `save-attendance.php` | Attendance recording API |

### Includes

| File | Purpose |
|------|---------|
| `includes/auth.php` | Authentication middleware |
| `includes/db.php` | Database connection |
| `includes/app.php` | Application shell & utilities |
| `includes/style.css` | Global styling |

## Database Schema

### Users Table
- id, name, email, phone, password, created_at

### Events Table
- id, name, date, time, end_time, type, image
- venue_name, venue_location, target_audience
- registration_mode, description
- attendance_start, attendance_end
- access_code, created_by

### Participants Table
- id, user_id, event_id, joined_at

### Attendance Table
- id, event_id, user_id, time, device_info

## User Roles

1. **Event Creator/Organizer**
   - Create and manage events
   - View attendance reports
   - Generate event codes

2. **Regular User/Attendee**
   - Browse events
   - Join events
   - Scan QR codes
   - View attendance history

## Navigation Structure

### Sidebar Menu (Desktop)
- Dashboard
- Events
- Create Event
- Attendance
- Live QR
- Reports

### Mobile Navigation
- Dashboard
- Events
- Scan
- Reports
- Profile

## Security Features
- Password hashing (PASSWORD_DEFAULT)
- Session-based authentication
- SQL preparation (parameterized queries)
- User authorization checks
- Device information tracking

## Event Lifecycle States
- Upcoming: Event hasn't started
- Ongoing: Event is happening now
- Ended: Event has finished

## Technology Stack
- **Backend**: PHP 7.x+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache/XAMPP
