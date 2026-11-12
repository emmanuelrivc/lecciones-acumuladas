# Teacher Absence & Accumulated Lessons Management System

Web application built for **Escuela Los Ángeles** (San Rafael, Heredia, Costa Rica) as my university Community Service Project (TCU, 150 hours, September – November 2026). The system is deployed and in use by the school's administration.

## The problem

When a specialty teacher (music, English, physical education, etc.) is absent, homeroom teachers cover those lessons and accumulate lesson time they can later use. The school tracked this manually, which made balances hard to verify and reports slow to produce.

## What the system does

- **Staff management:** register teachers and their schedules.
- **Absences:** record a specialty teacher's absence and see the lessons affected that day.
- **Coverage assignment:** assign who covers each affected lesson.
- **Accumulated-lesson balances:** automatically track each teacher's balance and record when accumulated lessons are used.
- **PDF reports:** generate reports for the administration.
- **Secure authentication:** login and password reset for the administrator account.

## Tech stack

- **Backend:** PHP 8.4, Laravel
- **Database:** SQLite (designed to migrate to MySQL for hosting)
- **Frontend:** Blade templates, Tailwind CSS, Vite
- **Tooling:** Git, Laravel Herd, Composer, npm

## My role

I worked on the full project lifecycle:

1. Gathered requirements with the school principal and wrote a requirements specification she approved.
2. Designed the data model and business rules.
3. Developed all modules of the application.
4. Ran usability testing with the end user and fixed the defects found.
5. Deployed the system on the school's computer.
6. Wrote the user manual, technical documentation and usage guides.

## Screenshots

> _Screenshots coming soon (login, absence registration, balances, PDF report), using demo data only._

## Running it locally

```bash
git clone https://github.com/emmanuelrivc/lecciones-acumuladas.git
cd lecciones-acumuladas
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Then open http://localhost:8000.

## Note on data

This repository contains no real personal data from the school. Teacher names in the seeders are fictional.

## Author

**Emmanuel Rivera Cordero** · Computer Systems Engineering student, Universidad Fidélitas
[LinkedIn](https://www.linkedin.com/in/emmanuel-rivera-9a986a2ba/) · [GitHub](https://github.com/emmanuelrivc)
