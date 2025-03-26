DROP DATABASE IF EXISTS club_management;
CREATE DATABASE club_management;

USE club_management;

CREATE TABLE users(
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'club_manager', 'student', 'advisor') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    contact VARCHAR(255),
    type ENUM('academic', 'sports', 'social', 'environmental', 'business') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE club_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    club_id INT NOT NULL,
    position VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    UNIQUE (student_id, club_id)
);

CREATE TABLE club_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    activity_date DATE NOT NULL,
    activity_time TIME NOT NULL,
    activity_location VARCHAR(255) NOT NULL,
    activity_description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);

CREATE TABLE activity_participants (
    activity_id INT NOT NULL,
    student_id INT NOT NULL,
    PRIMARY KEY (activity_id, student_id),
    FOREIGN KEY (activity_id) REFERENCES club_activity(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    department VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE club_advisors (
    club_id INT NOT NULL,
    advisor_id INT NOT NULL,
    PRIMARY KEY (club_id, advisor_id),
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE club_advisor_communication (
    club_id INT NOT NULL,
    advisor_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES teachers(id) ON DELETE CASCADE
);

CREATE TABLE club_advisor_meeting (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    advisor_id INT NOT NULL,
    date DATE NOT NULL,
    time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (advisor_id) REFERENCES teachers(id) ON DELETE CASCADE
);


CREATE TABLE dues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('paid', 'unpaid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES club_members(id) ON DELETE CASCADE
);

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    date DATE NOT NULL,
    time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    club_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);

CREATE TABLE event_attendees (
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    PRIMARY KEY (event_id, member_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES club_members(id) ON DELETE CASCADE
);

CREATE TABLE event_budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    item VARCHAR(255) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

CREATE TABLE club_forum (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);

CREATE TABLE club_announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE
);

CREATE TABLE club_announcement_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    announcement_id INT NOT NULL,
    member_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (announcement_id) REFERENCES club_announcements(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES club_members(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    sender_id INT NOT NULL,
    recipient_id INT,
    subject VARCHAR(255),
    content TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE forum_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE forum_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    receipt_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert Users (Admins, Students, Teachers)
INSERT INTO users (name, email, password, role) VALUES
('Admin User', 'admin@example.com', 'adminpass', 'admin'),
('John Doe', 'john@example.com', 'password123', 'student'),
('Alice Smith', 'alice@example.com', 'password123', 'student'),
('Bob Johnson', 'bob@example.com', 'password123', 'student'),
('Emma Brown', 'emma@example.com', 'password123', 'student'),
('Liam Wilson', 'liam@example.com', 'password123', 'student'),
('Sophia Martinez', 'sophia@example.com', 'password123', 'student'),
('James Anderson', 'james@example.com', 'password123', 'advisor'),
('Olivia Taylor', 'olivia@example.com', 'password123', 'advisor'),
('William Thomas', 'william@example.com', 'password123', 'advisor'),
('Isabella White', 'isabella@example.com', 'password123', 'advisor');

-- Insert Admins
INSERT INTO admins (user_id) VALUES (1);

-- Insert Students
INSERT INTO students (user_id, phone) VALUES
(2, '123-456-7890'),
(3, '234-567-8901'),
(4, '345-678-9012'),
(5, '456-789-0123'),
(6, '567-890-1234'),
(7, '678-901-2345');

-- Insert Teachers
INSERT INTO teachers (user_id, department) VALUES
(8, 'Computer Science'),
(9, 'Mathematics'),
(10, 'Physics'),
(11, 'Engineering');

-- Insert Clubs
INSERT INTO clubs (name, description, contact, type) VALUES
('AI Club', 'A club for AI enthusiasts', 'contact@aiclub.com', 'academic'),
('Sports Club', 'Engaging in various sports activities', 'contact@sportsclub.com', 'sports'),
('Music Club', 'For students who love music', 'contact@musicclub.com', 'social'),
('Environmental Club', 'Focused on environmental sustainability', 'contact@envclub.com', 'environmental');

-- Insert Club Advisors
INSERT INTO club_advisors (club_id, advisor_id) VALUES
(1, 1),
(1, 2),
(2, 2),
(2, 3),
(3, 3),
(3, 4),
(4, 4),
(4, 1);

-- Insert Club Members
INSERT INTO club_members (student_id, club_id, position) VALUES
(1, 1, 'President'),
(2, 1, 'Member'),
(3, 1, 'Member'),
(4, 2, 'President'),
(5, 2, 'Member'),
(6, 2, 'Member'),
(2, 3, 'Member'),
(3, 3, 'Member'),
(4, 4, 'President'),
(5, 4, 'Member');

-- Insert Events
INSERT INTO events (name, description, date, time, location, club_id) VALUES
('AI Hackathon', 'A competitive AI event', '2025-04-15', '10:00:00', 'Room 101', 1),
('Football Tournament', 'Annual sports event', '2025-05-20', '14:00:00', 'Main Field', 2),
('Music Fest', 'A festival of music performances', '2025-06-10', '18:00:00', 'Auditorium', 3),
('Eco Awareness Drive', 'A drive for sustainability', '2025-07-05', '09:00:00', 'Campus Park', 4);

-- Insert Event Attendees
INSERT INTO event_attendees (event_id, member_id) VALUES
(1, 1), (1, 2), (1, 3),
(2, 4), (2, 5), (2, 6),
(3, 7), (3, 2), (3, 3),
(4, 4), (4, 5);

-- Insert Club Announcements
INSERT INTO club_announcements (club_id, title, description) VALUES
(1, 'AI Club Meeting', 'Next AI club meeting scheduled for Friday'),
(2, 'Sports Club Tryouts', 'Tryouts for the football team on Saturday'),
(3, 'Music Club Performance', 'Live performance this weekend'),
(4, 'Green Campus Initiative', 'Join us for the tree-planting drive');

-- Insert Dues
INSERT INTO dues (member_id, amount, due_date, status) VALUES
(1, 50.00, '2025-04-01', 'unpaid'),
(2, 50.00, '2025-04-01', 'paid'),
(3, 50.00, '2025-04-01', 'unpaid'),
(4, 75.00, '2025-04-01', 'paid'),
(5, 75.00, '2025-04-01', 'unpaid');