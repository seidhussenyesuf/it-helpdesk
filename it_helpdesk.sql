-- Create database
CREATE DATABASE IF NOT EXISTS it_helpdesk;
USE it_helpdesk;
-- Create teams table first
CREATE TABLE teams (
    team_id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL
);
-- Create users table after teams
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('submitter', 'senior_officer') DEFAULT 'submitter',
    team_id INT,
    FOREIGN KEY (team_id) REFERENCES teams(team_id)
);
-- Create issue_team_mapping table
CREATE TABLE issue_team_mapping (
    issue_type VARCHAR(50) PRIMARY KEY,
    team_id INT NOT NULL,
    FOREIGN KEY (team_id) REFERENCES teams(team_id)
);
-- Create tickets table
CREATE TABLE tickets (
    ticket_id INT AUTO_INCREMENT PRIMARY KEY,
    issue_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('Low', 'Medium', 'High') DEFAULT 'Low',
    submitter_id INT NOT NULL,
    team_id INT NOT NULL,
    status ENUM('Open', 'In Progress', 'Closed') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (submitter_id) REFERENCES users(user_id),
    FOREIGN KEY (team_id) REFERENCES teams(team_id),
    FOREIGN KEY (issue_type) REFERENCES issue_team_mapping(issue_type)
);
-- Create comments table
CREATE TABLE comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    author_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id),
    FOREIGN KEY (author_id) REFERENCES users(user_id)
);
-- Insert sample data
INSERT INTO teams (team_name)
VALUES ('Hardware Support'),
    ('Software Support'),
    ('Network Operations'),
    ('Security Team');
INSERT INTO issue_team_mapping (issue_type, team_id)
VALUES ('Hardware', 1),
    ('Software', 2),
    ('Network', 3),
    ('Account Access', 4);
CREATE TABLE ticket_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    changed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(ticket_id),
    FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE = InnoDB;
INSERT INTO users (name, email, password, role, team_id)
VALUES (
        'Test Officer',
        'officer@example.com',
        '$2y$10$4Bh2b8ziFQ.POL8iXg.7A.buqSck/1eBR1ou1aTP1rIR558heZY4C',
        'senior_officer',
        1
    );