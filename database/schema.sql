CREATE DATABASE IF NOT EXISTS programming_courses CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE programming_courses;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    role ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS instructors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    display_name VARCHAR(150) NOT NULL,
    primary_language VARCHAR(80) NOT NULL,
    title VARCHAR(160),
    bio TEXT,
    annual_income INT DEFAULT 0,
    photo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NULL,
    language VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT,
    level VARCHAR(50) DEFAULT 'Beginner',
    duration_weeks INT DEFAULT 6,
    price INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    summary TEXT,
    video_url VARCHAR(255) NOT NULL,
    video_file_path VARCHAR(255) NOT NULL,
    poster_url VARCHAR(255),
    duration_minutes INT DEFAULT 15,
    position INT DEFAULT 1,
    is_free TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    language VARCHAR(80) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) NOT NULL,
    file_size VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorite_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    book_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, book_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    reply_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS chat_message_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (message_id, user_id),
    FOREIGN KEY (message_id) REFERENCES chat_messages(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS instructor_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    instructor_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (instructor_id, user_id),
    FOREIGN KEY (instructor_id) REFERENCES instructors(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lesson_views (
    lesson_id INT NOT NULL,
    user_id INT NOT NULL,
    views INT DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (lesson_id, user_id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    role ENUM('student','instructor','admin') DEFAULT NULL,
    action VARCHAR(120) NOT NULL,
    context TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO users (name, email, password_hash, role) VALUES
('အက်ဒ်မင်', 'admin@codehub.local', '$2y$10$kB6ifbQyLjHm7o.fpX4ol.7d.DujVL.o6u9r1VekGJ.Lr0nTZrZ2a', 'admin'),
('ဆရာ မင်းထက်', 'mentor@codehub.local', '$2y$10$oStJyQobb7.uTiQODwUS6.CNOjQKAtH3HRWSu6N9GSX5JI/p3hqUK', 'instructor'),
('ကျောင်းသား ကိုကို', 'learner@codehub.local', '$2y$10$i2mhN3H0cqoMW4jK1BhhKuHL0s/T9KJ12rjTlZv/qzMVj7owA0ElW', 'student');

INSERT INTO instructors (user_id, display_name, primary_language, title, bio, annual_income, photo_url)
VALUES
(2, 'ဆရာ မင်းထက်', 'PHP', 'Lead Backend Instructor', '၁၀ နှစ်အတွေ့အကြုံရှိ PHP Developer ဖြစ်ပြီး Laravel နှင့် API Architecture အတွက် အထူးပြုပါသည်။', 12000000, 'assets/images/mentor-minhtet.jpg');

INSERT INTO courses (instructor_id, language, title, description, level, duration_weeks, price)
VALUES
(1, 'PHP', 'PHP Full Stack Bootcamp', 'Backend + Frontend ပေါင်းစပ်သင်ကြားမှုများ၊ REST API နှင့် Database Optimization ပေါင်းစပ်ထားသော Intensive သင်တန်း။', 'Intermediate', 8, 180000),
(1, 'Python', 'Python Data Automation', 'Automation Script များ၊ Chatbot နှင့် Data Pipeline များရေးသားခြင်းကို အခြေခံမှ အဆင့်မြင့်အထိ လေ့လာစေသည်။', 'Beginner', 6, 150000);

INSERT INTO lessons (course_id, title, summary, video_url, video_file_path, duration_minutes, position, is_free)
VALUES
(1, 'PHP ပထမINTRO', 'PHP ၏ Syntax နှင့် Local Dev Setup', 'storage/videos/php_intro.mp4', 'storage/videos/php_intro.mp4', 18, 1, 1),
(1, 'Laravel Routing', 'Route Model Binding နှင့် Controller Setup', 'storage/videos/php_intro.mp4', 'storage/videos/php_intro.mp4', 24, 2, 1),
(1, 'Database Layer', 'PDO နှင့် Query Builder အသုံးပြုပုံ', 'storage/videos/php_intro.mp4', 'storage/videos/php_intro.mp4', 26, 3, 0),
(1, 'Testing & Debugging', 'Unit Test နှင့် Xdebug အသုံးပြုပုံ', 'storage/videos/php_intro.mp4', 'storage/videos/php_intro.mp4', 22, 4, 0),
(1, 'Deployment Playbook', 'Env setup, cache warmup, zero-downtime deploy', 'storage/videos/php_intro.mp4', 'storage/videos/php_intro.mp4', 28, 5, 0),
(2, 'Python Setup', 'Virtualenv နှင့် Package Management', 'storage/videos/python_loops.mp4', 'storage/videos/python_loops.mp4', 20, 1, 1),
(2, 'Loop & Collection', 'Data Structure အခြေခံ', 'storage/videos/python_loops.mp4', 'storage/videos/python_loops.mp4', 25, 2, 1),
(2, 'Automation Project', 'Practical ETL Automation', 'storage/videos/python_loops.mp4', 'storage/videos/python_loops.mp4', 30, 3, 0),
(2, 'Async Workers', 'Celery/AsyncIO ဖြင့် worker pattern မိတ်ဆက်', 'storage/videos/python_loops.mp4', 'storage/videos/python_loops.mp4', 24, 4, 0),
(2, 'Observability Basics', 'Logging/Tracing ဖြင့် Monitoring ထည့်သွင်းခြင်း', 'storage/videos/python_loops.mp4', 'storage/videos/python_loops.mp4', 27, 5, 0);

INSERT INTO books (title, language, description, file_path, file_size)
VALUES
('PHP Foundation Guide', 'PHP', 'OOP Concept, Composer & Testing အယူအဆများပါဝင်သည်။', 'storage/books/php_foundation.pdf', '4.2'),
('Python Automation Handbook', 'Python', 'Automation Script, Requests, Async IO များကို ဖော်ပြထားသည်။', 'storage/books/python_mastery.pdf', '5.1');
