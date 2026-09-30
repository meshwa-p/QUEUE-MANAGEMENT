# Sprint 1 – Viva Explanation
## Login (Student ID + Parent's Mobile) & Basic User Management

### 1. What this sprint delivers
- `register.php` – a student registers using Full Name, Student ID, and
  Parent's Mobile Number.
- `login.php` – a student logs in using ONLY Student ID + Parent's Mobile
  Number (no separate password).
- `logout.php` – ends the session.
- `dashboard.php` – landing page for a logged-in student/staff user.
- `admin_users.php` – admin-only screen to view all users, change roles
  (student/staff/admin), and enable/disable accounts.
- `config.php` – shared MySQL connection file.
- `database/queue_system.sql` – the `users` table, seeded with a default
  admin (Student ID: `ADMIN001`, Parent Mobile: `9999999999`).

### 2. Why Student ID + Parent Mobile instead of email/password?
This system is meant for students, many of whom don't have or don't
regularly use email. The school already assigns every student a unique
Student ID, and the parent's mobile number is already on file (it's also
needed later for the SMS queue-alert feature). Using these two together as
login credentials means:
- No new password to remember or forget.
- The parent's mobile doubles as the contact number for the SMS alert
  feature planned in later sprints — one field, two purposes.
- Both values together act like a username + PIN: Student ID identifies
  *who*, Parent Mobile confirms it's *really them* (only someone with access
  to both pieces of info can log in).

### 3. Database design decisions
- **`student_id`** — `VARCHAR`, marked `UNIQUE`, so no two students can
  register with the same ID.
- **`parent_mobile`** — stored as a 10-digit string; validated with a
  regex on the registration form (`^[0-9]{10}$`) so junk data can't be saved.
- **`role` ENUM** (`student`, `staff`, `admin`) in the same table — simplest
  design for this project's scope; keeps one source of truth for "who can do
  what."
- **`status`** (`active`/`disabled`) — lets admin block a login without
  deleting the student's record.

### 4. Security note (be upfront about this if asked)
Because there's no traditional secret password, security here relies on the
Student ID + Parent Mobile combination not being easily guessable together,
and the school controlling who receives their assigned Student ID. This is a
deliberate, practical trade-off for a low-friction school queue system —
not a banking-grade login. If asked "is this fully secure?", the honest
answer is: *it's convenience-first, appropriate for this use case, and could
be hardened later with an OTP sent to the parent's mobile if needed.*

### 5. How login works (be ready to trace this live)
1. Student submits Student ID + Parent Mobile (POST) → `login.php`.
2. One SQL query checks: does a row exist where **both** values match
   **at the same time**?
3. If yes and `status = active` → `$_SESSION` is set with `user_id`,
   `full_name`, `role` → redirected to dashboard (or admin panel if admin).
4. If no match, or account is disabled → error message shown, no session
   created.
5. Every protected page (`dashboard.php`, `admin_users.php`) checks
   `$_SESSION["user_id"]` before showing anything.

### 6. Likely viva questions and short answers
- **Q: Why not use email and a real password?**
  A: Students don't reliably use email; Student ID + Parent Mobile is data
  the school already has, and it doubles as the contact number for SMS
  alerts in later sprints.
- **Q: What if someone else knows a student's ID?**
  A: They'd also need the exact parent mobile number on file — combining
  two pieces of specific information is the safeguard here.
- **Q: How would you make this more secure later?**
  A: Add an OTP step — after Student ID + Parent Mobile match, send a
  one-time code to that mobile number and require it before login completes.
- **Q: Why is parent mobile validated with a regex?**
  A: To stop obviously invalid data (letters, wrong length) from being saved,
  since this field is later reused for SMS notifications.
- **Q: Why one `users` table for student/staff/admin?**
  A: All three share the same basic structure; the `role` column is enough
  to separate their permissions at this project's scale.

### 7. How to run it (for the demo)
1. Import `database/queue_system.sql` into MySQL (via phpMyAdmin or CLI).
2. Start Apache + MySQL (e.g. via XAMPP).
3. Visit `login.php`:
   - Admin: Student ID `ADMIN001`, Parent Mobile `9999999999`
   - Or register a new student via `register.php` and log in with those
     details.
