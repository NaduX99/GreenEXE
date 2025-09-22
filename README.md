<h1>📚 Learning Management System</h1>
A modern LMS built with PHP and MySQL featuring admin panel and student dashboard with dark theme UI.

✨ Features
Admin Panel: Dashboard, User Management, Quiz Creation, Assignment System, Reports & Analytics, Leaderboard

Student Portal: Take Quizzes, Submit Assignments, Track Progress, View Rankings

🛠️ Tech Stack
Backend: PHP 8.0+, MySQL 8.0+

Frontend: Bootstrap 5, Font Awesome, Chart.js

Server: Apache/XAMPP

🚀 Quick Setup
Clone repository

bash
git clone https://github.com/yourusername/learning-management-system.git
Database setup

Create MySQL database learning_platform

Tables auto-create on first run

Configuration

php
$host = 'localhost';
$username = 'root'; 
$password = '';
$database = 'learning_platform';
Access

Admin: http://localhost/lms/admin/

Student: http://localhost/student/

📂 Structure

learnarena/
├── admin/          # Admin panel (dashboard, users, quizzes, reports)
├── student/        # Student portal (quizzes, assignments, profile)  
├── uploads/        # File uploads
└── assets/         # CSS, JS, images
🎯 Key Features
Auto-Schema Repair - Fixes database issues automatically

Modern Dark UI - Professional navy blue theme with glassmorphism

MCQ Quiz System - Timer, auto-scoring, analytics

File Uploads - Assignment submission support

Role-Based Access - Admin/Student separation

Responsive Design - Works on all devices

🔧 Sample Accounts
Admin: admin@example.com / admin123
Student: student@example.com / student123

📊 Database Tables
users, subjects, lessons, quizzes, quiz_questions, quiz_attempts, assignments, assignment_submissions, settings

🤝 Contributing
Fork → Feature branch → Commit → Push → Pull Request

⭐ Star this repo if you find it helpful!

Built with PHP, MySQL & modern web technologies
