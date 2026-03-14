-- Supabase / PostgreSQL Schema for VidhyaSetu

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    uid VARCHAR(50) UNIQUE NOT NULL,
    internal_uid VARCHAR(100) UNIQUE,
    role VARCHAR(20) NOT NULL CHECK (role IN ('student', 'tutor')),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    mobile VARCHAR(20),
    gender VARCHAR(20),
    dob DATE,
    age INTEGER DEFAULT 0,
    college VARCHAR(255),
    location VARCHAR(255),
    subjects JSONB DEFAULT '[]',
    grade VARCHAR(50),
    occupation VARCHAR(255),
    experience VARCHAR(255),
    availability VARCHAR(50) DEFAULT 'Online',
    fee_per_hour DECIMAL(10, 2) DEFAULT 0,
    bio TEXT,
    profile_photo TEXT,
    rating DECIMAL(3, 2) DEFAULT 0,
    total_sessions INTEGER DEFAULT 0,
    skill_level VARCHAR(50) DEFAULT 'Beginner',
    skill_score INTEGER DEFAULT 0,
    skill_test_passed BOOLEAN DEFAULT FALSE,
    primary_subject VARCHAR(100),
    status VARCHAR(20) DEFAULT 'active',
    availability_slots JSONB DEFAULT '[]',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Unique index for email + role (allows same email for different roles)
CREATE UNIQUE INDEX IF NOT EXISTS unique_email_role ON users (email, role);

-- 2. User Sessions Table
CREATE TABLE IF NOT EXISTS user_sessions (
    id SERIAL PRIMARY KEY,
    uid VARCHAR(50) NOT NULL REFERENCES users(uid) ON DELETE CASCADE,
    token VARCHAR(255) UNIQUE NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bookings Table
CREATE TABLE IF NOT EXISTS bookings (
    id SERIAL PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL REFERENCES users(uid),
    tutor_id VARCHAR(50) NOT NULL REFERENCES users(uid),
    student_name VARCHAR(255),
    tutor_name VARCHAR(255),
    subject VARCHAR(100),
    date DATE,
    time VARCHAR(50),
    mode VARCHAR(50),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed')),
    rating INTEGER DEFAULT 0,
    review TEXT,
    amount DECIMAL(10, 2) DEFAULT 0,
    fee DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 4. Messages Table
CREATE TABLE IF NOT EXISTS messages (
    id SERIAL PRIMARY KEY,
    booking_id INTEGER REFERENCES bookings(id),
    sender_id VARCHAR(50) NOT NULL REFERENCES users(uid),
    receiver_id VARCHAR(50) NOT NULL REFERENCES users(uid),
    text TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL REFERENCES users(uid) ON DELETE CASCADE,
    icon VARCHAR(10),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
