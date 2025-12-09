# Online Exam Application (QCM and Open Questions)

## 📖 About The Project

This project focuses on developing a secure web application for conducting online exams. It allows teachers to create and manage exams while students can take them in a user-friendly and secure environment. The application supports multiple-choice questions (QCM) and open-ended questions.

### 🎯 Key Features

- **User Management**: Role-based access for administrators, teachers, and students.
- **Exam Creation**: Teachers can create exams with customizable parameters (e.g., duration, retries).
- **Student Interaction**: User-friendly interface with real-time timers.
- **Anti-Cheating Mechanisms**: Tab-switch detection, automatic answer saving, and optional webcam monitoring.
- **AI-Powered Grading**: Automatic grading for QCM and space for teachers to manually grade open-ended questions.
- **Reporting and Analytics**: Individual and aggregated performance reports.

---

## 📋 Functional Requirements

### User Management

- **Roles**: Admin, Teacher, Student.
- **Features**: Registration, authentication, password reset.

### Exam Management

- **Teachers**: Create exams with QCM, short answers, and open-ended questions.
- **Exam Settings**: Define start/end time, duration, and allowed attempts.

### Anti-Cheating

- Tab/browser monitoring.
- Auto-save answers periodically.
- Optional webcam surveillance.

### Grading and Analytics

- Automatic QCM grading.
- Manual grading for open-ended questions.
- Performance reports for students and questions.

---

## 🛠️ Technologies Used

### Frontend

- React.js, Vue.js, or Angular (responsive design).

### Backend

- PHP (Laravel), Python (Django/Flask), or Node.js.
- RESTful API communication.

### Database

- MySQL or PostgreSQL.

### Other Tools

- Agile project management.
- Notification system (email/SMS).

---

## 📂 Folder Structure

```plaintext
|-- /frontend       # React/Vue/Angular code
|-- /backend        # PHP/Laravel or Django/Flask code
|-- /ai             # Python-based AI grading scripts
|-- /database       # SQL scripts for setup
|-- README.md       # Project documentation

```

---

## 🚀 Getting Started

### Prerequisites

- Node.js and npm.
- PHP/Composer or Python (with virtualenv).
- MySQL/PostgreSQL database.

### Installation

#### Clone the Repository

```bash
git clone https://github.com/BOmarB/OnlineExamApplication.git
cd OnlineExamApplication
```

#### Frontend Setup

```bash
cd frontend
npm install
npm start
```

#### Backend Setup

- Install dependencies:

```bash
Copy code
composer install
```

- Configure database in .env.

#### AI Component

```bash
cd ai
python -m venv venv
source venv/bin/activate  # Linux/Mac
venv\Scripts\activate     # Windows
pip install -r requirements.txt
```

#### Database Setup Import the database.sql file into your MySQL/PostgreSQL database.

---

## 🖥️ Usage

#### Start the backend server:

```bash
php -S localhost:8000
```

#### Start the frontend server:

```bash
npm start
```

#### Run AI grading scripts:

```bash
python ai_corrector.py
```

---

## 📊 Future Enhancements

- Integration of live proctoring with AI.
- Advanced analytics for performance insights.
- Real-time collaboration features for teachers.

---

## 🤝 Contributing

#### Fork the repository.

#### Create your feature branch:

```bash
git checkout -b feature/AmazingFeature
```

#### Commit your changes:

```bash
git commit -m 'Add some AmazingFeature'
```

#### Push to the branch:

```bash
git push origin feature/AmazingFeature
```

#### Open a pull request.

---

## 🛡️ Security Features

- Password hashing with bcrypt.
- JWT-based session management.
- Protection against CSRF, XSS, and SQL injection attacks.

---

## 👨‍💻 Author

- | Omar Bouazzaoui
- | Taouil Aboubakr

---
