**RateMyFaculty project audit — 13 September 2026**

Reviewed all 35 original project files: 31 PHP, two SQL, one CSS, and one JavaScript. The review includes source inspection, PHP/JavaScript syntax checks, read-only inspection of the running database, local HTTP checks, and targeted PHP verification. There is no Git repository in this directory, so there was no branch to check out or history to inspect.

The application currently runs on the local XAMPP installation, but it cannot be reconstructed from the supplied database schema. The most consequential findings concern evaluation anonymity, historical result attribution, session revocation, and evaluation timing. This report records 22 findings and nine additional gaps or policy decisions. Some are reproduced defects; concurrency and privacy scenarios are identified from code and schema and are explicitly distinguished below. A complete file review does not prove that every possible runtime defect has been found.

Only this report was added. Application source and application database records were not modified. HTTP verification used separate demo-account sessions and logged those sessions out; normal web-server logging and session housekeeping may occur.

**How the project works**

This is a server-rendered PHP/PDO application with three roles. Administrators manage accounts, courses, semesters, offerings, and enrollments. Students with completed enrollments can submit five ratings and an optional comment during the evaluation window. Submission writes an identity-bearing receipt and a separate evaluation in one transaction. Faculty see their own offerings, and both administrators and faculty see result content only after the window closes and at least three responses exist. Faculty summaries average the individual evaluations across released offerings.

**Findings, in priority order**

**F01 — High: The supplied schema cannot install the application.**

Location: [database/schema.sql:4](C:/xampp/htdocs/ratemyfaculty/database/schema.sql:4).

The entire file contains `USE ratemyfaculty` and the definition of `audit_logs`, whose foreign key references `users`. It creates neither the database nor the nine other required tables: `users`, `students`, `faculty`, `courses`, `semesters`, `course_offerings`, `enrollments`, `submission_receipts`, and `evaluations`. A fresh database cannot be built from these files. The running database does contain all ten tables, so essential schema exists only outside the project. This also leaves important constraints undocumented in source.

Evidence: full SQL-file review and read-only live table/constraint inspection. No import was attempted. Fix: restore complete, dependency-ordered DDL or migrations, preserving the current data, and verify installation in an isolated empty database.

**F02 — High: Submission metadata weakens the promised anonymity.**

Locations: [student/submit_evaluation.php:111](C:/xampp/htdocs/ratemyfaculty/student/submit_evaluation.php:111), [faculty/offering_result.php:71](C:/xampp/htdocs/ratemyfaculty/faculty/offering_result.php:71), [admin/result_detail.php:64](C:/xampp/htdocs/ratemyfaculty/admin/result_detail.php:64), [admin/progress_detail.php:40](C:/xampp/htdocs/ratemyfaculty/admin/progress_detail.php:40).

The live `submission_receipts` and `evaluations` tables both have `submitted_at DATETIME DEFAULT current_timestamp()`. The handler inserts the two rows sequentially, often giving them the same timestamp. A database reader can correlate student identity with response content using offering plus timestamp. An aggregate-only query found one uniquely matching offering/timestamp pair among six receipts and six evaluations; the audit did not display the associated identity or content.

The result screens also sort comments by `evaluation_id`, preserving insertion order. An administrator who records changes on the named submission-progress screen can later associate that order with the comments when, for example, every respondent supplied a comment. This second attack requires observed submission order; it is not evidence that every response is already identifiable.

Fix: define the intended anonymity boundary, remove or reduce correlatable metadata, and publish comments in an order unrelated to submission order. Review identity-progress access and database/log retention. Update the “completely anonymous” promise in `student/evaluate.php:77` to match the actual guarantees. Shuffling comments alone does not address database timestamps.

**F03 — High: Reopening released evaluations can reveal an additional student's response.**

Locations: [admin/semesters.php:85](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:85), [includes/release_rule.php:13](C:/xampp/htdocs/ratemyfaculty/includes/release_rule.php:13), [faculty/summary.php:44](C:/xampp/htdocs/ratemyfaculty/faculty/summary.php:44).

An administrator can unlock a closed semester, extend the closing time, accept another student's response, and close the window again. Results are recalculated over the changed group; there is no irreversible release event. If three responses average 4.00 and four responses later average 3.25, the added rating is `4 × 3.25 − 3 × 4.00 = 1`. Named progress can identify the additional respondent, and a newly appearing comment provides another signal. The three-response threshold does not prevent this comparison.

Evidence: reachable source paths and a concrete arithmetic example; no live semester was reopened. Fix: prevent new submissions to a released cohort, or implement a publication policy that prevents identifying changes between overlapping releases. An ordinary reversible semester lock is insufficient.

**F04 — High: Editing an offering can transfer historical evaluations to another faculty member.**

Locations: [admin/offerings.php:60](C:/xampp/htdocs/ratemyfaculty/admin/offerings.php:60), [faculty/offering_result.php:33](C:/xampp/htdocs/ratemyfaculty/faculty/offering_result.php:33).

Offering edits freely replace course, semester, faculty, and section after enrollments or evaluations exist. Evaluations retain only the offering reference, and result pages resolve the current offering attributes. Reassigning faculty therefore makes existing ratings appear to describe the replacement faculty and may grant that faculty access to the previous faculty's comments. Changing semester can also change the release status of existing results.

Evidence: update/read paths and live foreign-key structure; no historical records were altered. Fix: freeze identifying fields once dependent records exist, or create a new offering/version. Coordinate the check with submissions inside a transaction.

**F05 — High: Deactivating an account does not revoke an existing session.**

Locations: [includes/auth.php:99](C:/xampp/htdocs/ratemyfaculty/includes/auth.php:99), [includes/authorization.php:14](C:/xampp/htdocs/ratemyfaculty/includes/authorization.php:14), [admin/students.php:172](C:/xampp/htdocs/ratemyfaculty/admin/students.php:172), [admin/faculty.php:171](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:171).

`is_active` is checked only during login. Protected requests trust the cached user ID and role in the session. A student or faculty member who is already signed in can continue using that session after the administrator deactivates the account. A database role change similarly leaves the cached role intact.

Evidence: all authentication and guard paths reviewed; no real account was deactivated. Fix: revalidate account status and authorization on protected requests, or use a session version/revocation mechanism that administrative changes invalidate.

**F06 — High: PHP and MySQL disagree about the evaluation timezone.**

Locations: [config/app.php:4](C:/xampp/htdocs/ratemyfaculty/config/app.php:4), [includes/evaluation_window.php:20](C:/xampp/htdocs/ratemyfaculty/includes/evaluation_window.php:20).

PHP currently uses `Europe/Berlin`, while MySQL uses `Asia/Dhaka`. The application sets no timezone, and it interprets timezone-free stored dates using PHP's default. A synthetic window from MySQL `NOW() - 1 hour` to `NOW() + 1 hour` returned `UPCOMING` through the actual helper, although it should be `OPEN`. For Dhaka-local schedules, the current difference is four hours, increasing to five during Berlin winter. Evaluation access and result release are both affected.

Fix: choose one explicit application/database timezone and apply it consistently to input, storage, comparisons, and display. For existing campus-local DATETIME values, first establish their intended timezone before converting anything.

**F07 — Medium: Database source files and a diagnostic endpoint are publicly served.**

Locations: [database/seed.sql:1](C:/xampp/htdocs/ratemyfaculty/database/seed.sql:1), [database/test_connection.php:4](C:/xampp/htdocs/ratemyfaculty/database/test_connection.php:4).

Unauthenticated HTTP requests returned 200 for `/ratemyfaculty/database/`, `/database/schema.sql`, `/database/seed.sql`, and `/database/test_connection.php`. The directory lists its contents, the SQL files expose schema/demo-account information, and the diagnostic reveals the connected database name. This was confirmed locally; exposure to the wider network depends on Apache/network configuration.

Fix: put SQL/setup material outside the web-served tree or deny access through server configuration, disable directory listing, and remove or restrict the diagnostic endpoint. Public deployment must also remove or disable demo accounts.

**F08 — Medium: The documented seed password does not match the seed hash.**

Location: [database/seed.sql:4](C:/xampp/htdocs/ratemyfaculty/database/seed.sql:4), first hash at line 17.

The file promises `Password123`, but `password_verify()` rejects that password against the hash repeated for all eight accounts. A fresh import would therefore produce accounts that do not accept the documented password. The existing local database has different hashes, and its demo accounts successfully accepted `Password123` during this audit. This is a source/setup defect, not a claim that current local login is broken.

Fix: generate and verify the intended demo hash, update the seed, and check the documented credentials as part of isolated installation verification.

**F09 — Medium: Failed edits silently become creation forms.**

Locations: [admin/courses.php:83](C:/xampp/htdocs/ratemyfaculty/admin/courses.php:83), [admin/students.php:199](C:/xampp/htdocs/ratemyfaculty/admin/students.php:199), [admin/faculty.php:198](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:198), [admin/offerings.php:97](C:/xampp/htdocs/ratemyfaculty/admin/offerings.php:97), [admin/semesters.php:112](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:112).

The forms submit to URLs without `?edit=...`, but edit state is rebuilt solely from GET. After an invalid update, the page drops the target ID and renders `action=create`. A retry can create a new record instead of correcting the intended record, or request an initial password again. Offering/enrollment selections and semester date fields also lose submitted values on errors.

Evidence: each of the five real edit scripts was executed with deliberately invalid update data, an in-memory session, and a read-only database transaction. All five rendered create mode and omitted their target ID. No writes occurred. Fix: preserve the validated POST action/ID and submitted values after errors; use GET state for initial page loading only.

**F10 — Medium: Replaying a status-change request reverses its effect.**

Locations: [admin/courses.php:75](C:/xampp/htdocs/ratemyfaculty/admin/courses.php:75), [admin/students.php:172](C:/xampp/htdocs/ratemyfaculty/admin/students.php:172), [admin/faculty.php:171](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:171), [admin/offerings.php:89](C:/xampp/htdocs/ratemyfaculty/admin/offerings.php:89), [admin/semesters.php:104](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:104).

Handlers flip flags using `1 - is_active` or `1 - is_locked` and render directly after POST. A resubmitted refresh, duplicate click, or retry flips the flag again, potentially reactivating an account or unlocking a semester. Successful creation POSTs also remain replayable, although unique constraints usually turn repeats into errors.

Evidence: source control flow and SQL. Fix: submit an explicit desired state, redirect after successful POST, and account for stale concurrent changes where needed.

**F11 — Medium: Submission and enrollment deletion can race.**

Locations: [student/submit_evaluation.php:40](C:/xampp/htdocs/ratemyfaculty/student/submit_evaluation.php:40), [admin/enrollments.php:68](C:/xampp/htdocs/ratemyfaculty/admin/enrollments.php:68).

Submission checks eligibility before its transaction begins at line 105. Enrollment deletion separately checks receipt count before deleting at line 82. These operations can interleave so that the enrollment disappears while its receipt and evaluation are successfully stored. Live receipt foreign keys reference students and offerings separately, not the enrollment pair. Progress then omits a respondent whose evaluation still contributes to results. Concurrent changes to semester dates can also invalidate a pre-transaction eligibility check.

Evidence: source and live schema; destructive/concurrent requests were not run. Fix: validate and lock the relevant enrollment/offering/semester inside the submission transaction and use compatible locking for administrative writes. Consider a composite receipt-to-enrollment foreign key.

**F12 — Medium: Invalid calendar dates are accepted and silently changed.**

Location: [admin/semesters.php:24](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:24).

`DateTimeImmutable::createFromFormat()` can return an object with warnings after normalizing invalid dates. The parser checks only for `false`. The actual function converted `2026-02-31T09:00` into `2026-03-03 09:00:00` and `2026-13-01T09:00` into `2027-01-01 09:00:00`. This lets malformed/stale-client requests save unintended schedules.

Fix: reject parser warnings/errors and require an exact format round-trip. There was no evidence of unwanted current seconds being retained on this PHP version.

**F13 — Medium: Semester year limits are enforced only in HTML.**

Location: [admin/semesters.php:44](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:44), HTML limits at line 161.

The browser advertises 2000–2100, but the handler accepts any positive integer. The live column is `SMALLINT UNSIGNED`, and the installation lacks strict SQL mode; out-of-range storage values can be clipped rather than rejected. Values outside the advertised interval can also be saved without reaching the storage limit.

Evidence: validator, form, live DDL, and SQL-mode inspection; no invalid year was inserted. Fix: enforce the intended interval in the handler and adopt appropriate strict database validation.

**F14 — Medium: Accepted credit hours can become a different value in storage.**

Location: [admin/courses.php:26](C:/xampp/htdocs/ratemyfaculty/admin/courses.php:26).

The handler accepts any positive numeric value up to 9.9, but the live column is `DECIMAL(3,1)`. Read-only SQL conversions showed `0.01` becoming `0.0` and `3.75` becoming `3.8`. Thus even the positive-value rule can be defeated through rounding.

Fix: enforce supported decimal precision/increments or deliberately widen the storage precision to match the course-credit model. Validate the stored representation, not only the incoming float.

**F15 — Medium: A concurrent edit can bypass a newly applied semester lock.**

Location: [admin/semesters.php:50](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:50), update at line 85.

The handler reads the lock flag and later updates the semester without including `is_locked = 0` in the update condition or holding a lock. Another administrator can lock the semester between these operations, but the first edit still succeeds.

Evidence: source-level race analysis; no live concurrent mutation. Fix: enforce the lock condition atomically in the update or lock/recheck the row transactionally, and report a conflict when the state changed.

**F16 — Medium: Malformed form fields crash handlers and expose diagnostics.**

Locations: [includes/csrf.php:26](C:/xampp/htdocs/ratemyfaculty/includes/csrf.php:26), [includes/validation.php:27](C:/xampp/htdocs/ratemyfaculty/includes/validation.php:27), [includes/validation.php:41](C:/xampp/htdocs/ratemyfaculty/includes/validation.php:41), [config/app.php:5](C:/xampp/htdocs/ratemyfaculty/config/app.php:5).

PHP allows fields such as `csrf_token[]=x` and `comment[]=x` to arrive as arrays. `hash_equals()` receives the array directly, and typed text validators receive arrays directly, causing uncaught `TypeError` exceptions. Casting other arrays to strings can also produce warnings and unintended input values. Development mode is hard-coded and displays errors.

Evidence: direct helper calls reproduced the type errors. An anonymous HTTP POST containing an array CSRF field returned a fatal-error page with a local filesystem path and stack details; on this installation it even carried HTTP 200. An invalid scalar token correctly returned 403.

Fix: validate input shape before use; reject nonscalar fields with a controlled 400/403 response. Add consistent error handling and environment-based error-display settings. Hiding diagnostics alone does not fix input handling.

**F17 — Medium: Login has no attempt throttling.**

Locations: [login.php:24](C:/xampp/htdocs/ratemyfaculty/login.php:24), [includes/auth.php:34](C:/xampp/htdocs/ratemyfaculty/includes/auth.php:34).

Each submitted login is immediately checked, with no application attempt limit, backoff, or other abuse control. Public CSRF tokens are obtainable from the login form and do not limit password guessing. Unknown/inactive accounts also exit before password verification, creating a potential timing distinction despite the identical displayed error.

Evidence: source review; no brute-force or load test was run. External infrastructure could provide controls that are not represented in this project. Fix: implement bounded, account/network-aware throttling without permanent attacker-triggered lockouts; consider a comparable password-verification path for unknown users.

**F18 — Medium: Long passwords are silently truncated by bcrypt.**

Locations: [admin/students.php:41](C:/xampp/htdocs/ratemyfaculty/admin/students.php:41), [admin/faculty.php:40](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:40).

Account creation checks only a minimum length before using `PASSWORD_BCRYPT`. A local verification showed that two passwords sharing the first 72 bytes but having different suffixes both verify against the same hash. The interface does not tell the user that the suffix is ignored.

Fix: enforce bcrypt's byte limit consistently at creation/login with clear validation, or adopt a supported password algorithm and migration strategy that meets the desired password policy. Account for multibyte input.

**F19 — Medium: Some transaction error handlers can fail while handling the original error.**

Locations: [student/submit_evaluation.php:136](C:/xampp/htdocs/ratemyfaculty/student/submit_evaluation.php:136), [admin/students.php:80](C:/xampp/htdocs/ratemyfaculty/admin/students.php:80), [admin/faculty.php:79](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:79), and their update catches.

The catches call `rollBack()` unconditionally. If starting the transaction failed, or it is no longer active, rollback can throw another exception and skip the intended error logging/message. A memory-only PDO check reproduced `There is no active transaction`; no database failure was induced.

Fix: check `inTransaction()` and preserve the original exception if cleanup fails. Apply consistent handling to toggle/mark-completed handlers, whose database exceptions currently have no local catch.

**F20 — Low: Nonexistent update targets can report success.**

Locations: [admin/courses.php:57](C:/xampp/htdocs/ratemyfaculty/admin/courses.php:57), [admin/offerings.php:60](C:/xampp/htdocs/ratemyfaculty/admin/offerings.php:60), [admin/semesters.php:85](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php:85), [admin/enrollments.php:55](C:/xampp/htdocs/ratemyfaculty/admin/enrollments.php:55), [admin/students.php:181](C:/xampp/htdocs/ratemyfaculty/admin/students.php:181), [admin/faculty.php:180](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:180).

Several updates/toggles accept a positive ID and display success even when no record matches. Student/faculty toggles additionally interpret a missing follow-up row as `DEACTIVATE` and can log an operation on a nonexistent profile. This is reachable through stale or crafted administrator requests.

Fix: distinguish a missing record from an unchanged value, and log only a confirmed operation. Do not treat every zero affected-row count as “not found,” because unchanged updates can also produce zero.

**F21 — Medium: Account status changes and their audit logs are not atomic.**

Locations: [admin/students.php:170](C:/xampp/htdocs/ratemyfaculty/admin/students.php:170), [admin/faculty.php:169](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php:169), [includes/audit.php:17](C:/xampp/htdocs/ratemyfaculty/includes/audit.php:17).

The status update commits independently before a follow-up SELECT and audit INSERT. If logging fails, the status has changed without a log and the page errors. If another toggle runs between update and SELECT, the logged action can describe the other request's resulting state.

Evidence: source transaction boundaries; failure was not injected into the running database. Fix: perform the confirmed state change and audit write in the same transaction, recording the intended/previous/new state consistently.

**F22 — Low: Upcoming windows are described as already open.**

Locations: [admin/results.php:55](C:/xampp/htdocs/ratemyfaculty/admin/results.php:55), [admin/result_detail.php:87](C:/xampp/htdocs/ratemyfaculty/admin/result_detail.php:87), [faculty/dashboard.php:71](C:/xampp/htdocs/ratemyfaculty/faculty/dashboard.php:71), [faculty/offering_result.php:93](C:/xampp/htdocs/ratemyfaculty/faculty/offering_result.php:93).

The message branch checks only whether the window is not closed, conflating `UPCOMING` with `OPEN`. A future semester can therefore show “Upcoming” alongside “Window still open.” The content gate itself remains closed.

Fix: distinguish the two states or use a message that results become available after the evaluation window closes.

**Additional gaps and policy decisions**

These are not all independent runtime defects. They should be resolved according to intended deployment and product behavior.

| ID | Gap and location | Action |
| --- | --- | --- |
| G01 | Inactive offerings remain evaluable: `includes/eligibility.php:12` and `student/dashboard.php:27` ignore `co.is_active`. A completed/open fixture still returned eligible for an inactive offering. | Decide whether deactivation stops feedback or only prevents new enrollments. If it stops feedback, enforce it in both the handler's eligibility rule and dashboard. The current UI does not explain the narrower interpretation. |
| G02 | Active-only dropdown restrictions are not enforced by writes: `admin/offerings.php:23`, `admin/enrollments.php:22`. Stale/crafted requests can create assignments involving inactive entities. | If active-only creation is intended, validate related status server-side while deliberately allowing existing inactive assignments to remain unchanged during edits. |
| G03 | `config/database.php:15` uses passwordless root, and `config/app.php:5` hard-codes development mode. These are explicitly XAMPP development defaults. | Before deployment, use external environment configuration and a dedicated, limited database account; disable displayed errors and demo accounts. Do not change the local database account blindly. |
| G04 | `includes/auth.php:20` sets HttpOnly/SameSite but does not explicitly enable Secure under HTTPS, strict session mode, or enforce application idle/absolute expiry. Local PHP has secure-cookie and strict-session settings disabled. | Configure HTTPS-aware Secure cookies and deliberate expiry/revocation behavior. Session garbage collection is not a deterministic access-expiration policy. HTTP deployment context determines the immediate risk. |
| G05 | Course, semester, offering, and enrollment changes never call `log_admin_action()`; only student/faculty management does. | Define required audit scope and record relevant changes transactionally without passwords or evaluation content. This is separate from the broken atomicity of the logs that already exist (F21). |
| G06 | Password recovery is unfinished. `admin/students.php:263` and `admin/faculty.php:262` refer to a reset feature “coming in RMF-032”; no reset/change route exists. | Implement a controlled reset/change flow. Currently a forgotten password requires intervention outside the application. This is a missing feature, not a failing implemented endpoint. |
| G07 | Presentation/accessibility gaps: no viewport metadata; wide unwrapped tables; `<form>` nested inside `<p>` in `student/dashboard.php:57` and `faculty/dashboard.php:49`; rating criteria lack fieldset/legend grouping; `admin/dashboard.php:17` omits the stylesheet and line 25 contains stale future-feature text. | Add responsive metadata/layout, valid dashboard markup, accessible rating groups, shared styling, and current copy. The CSS is syntactically valid; no visual browser/device sweep was performed. |
| G08 | Error recovery discards drafts: `student/submit_evaluation.php:81` links back to a blank form, and `student/evaluate.php:102`/`:110` repopulate nothing. Admin selects/date fields also lose values (F09). | Re-render errors with submitted values in the same request or another deliberately temporary mechanism; avoid permanently storing identifiable evaluation drafts. |
| G09 | Reproducibility and scale gaps: no README/setup procedure, version requirements, migration history, automated test suite, or pagination. The seed is not transactional/repeatable and many seed queries match only course code, so it must not be assumed safe for a populated database. `assets/js/app.js` is an unused intentional placeholder. | Document a verified empty-database setup and required PHP extensions/version, add targeted regression checks, make seed assumptions explicit, and add pagination when actual data volume warrants it. These omissions do not prove a current performance failure; the empty JavaScript file is not itself a bug. |

**Verification results and limits**

| Check | Result |
| --- | --- |
| Full file inventory and review | All 35 original files reviewed; no dependency/vendor/generated directories were present. |
| PHP syntax | All 31 PHP files passed `C:\xampp\php\php.exe -l` using PHP 8.0.30. |
| JavaScript syntax | `node --check assets/js/app.js` passed. |
| Database inspection | Read-only examination of all ten live tables and their constraints; MariaDB 10.4.32, InnoDB. |
| Required extensions | PDO MySQL and mbstring are present locally. |
| Normal HTTP paths | Demo logins for admin, student, and both faculty accounts succeeded. Admin management/list pages, student dashboard, faculty dashboards/summary, and detail pages for valid offerings rendered without detected PHP warning/fatal output. |
| Role authorization | Student and faculty requests to the admin dashboard returned 403. |
| Faculty ownership | Faculty could view their own offerings; tested other-faculty offerings returned 404. |
| Result suppression | Tested released results rendered aggregate content; below-threshold/unreleased offerings did not render ratings. |
| Logout/CSRF | Logout GET returned 405; invalid scalar CSRF returned 403; normal POST logout succeeded. Array CSRF reproduced F16. |
| Window/release helper boundaries | Four window-boundary cases and 12 status/count combinations passed when supplied a consistent timezone. The separate actual-timezone test reproduced F06. |
| Admin invalid updates | All five edit flows reproduced F09 in read-only execution. |
| Input/storage checks | Invalid calendar normalization, malformed input errors, bcrypt suffix truncation, and decimal rounding were reproduced without changing live records. |
| Seed credentials | Source hash verification failed; current local demo credentials worked. |
| Anonymity metadata | Aggregate-only timestamp-correlation check confirmed a unique matching pair without displaying student identity or feedback. |

The live database already has a composite primary key on `submission_receipts(student_id, offering_id)`, foreign keys, rating checks for 1–5, and a semester open-before-close check. These protections must be retained when restoring the schema. The receipt-first transaction is a sound basis for preventing duplicate evaluations. There was no evidence that duplicate protection is absent from the current live database.

Prepared statements and HTML escaping are consistently used in the reviewed main flows. The faculty summary correctly averages individual responses rather than averaging offering averages. No direct cross-faculty access or straightforward SQL-injection/unescaped-comment path was found during this review. These observations are not a penetration-test guarantee.

The audit did not create accounts, change courses/enrollments/semesters, submit evaluations, import SQL, trigger database outages, or execute concurrent destructive scenarios. Successful write flows, concurrency fixes, and clean installation should be tested against an isolated database when fixes are implemented. No screenshots, mobile-device rendering, or load tests were performed.

**Coverage of every original file**

Shared findings apply across files; a row saying no independent defect does not exclude the shared configuration/session issues.

| File | Review coverage / associated findings |
| --- | --- |
| [index.php](C:/xampp/htdocs/ratemyfaculty/index.php) | Session and role redirects reviewed; shared F05/G04, no independent redirect defect found. |
| [login.php](C:/xampp/htdocs/ratemyfaculty/login.php) | Login, CSRF, validation, redirect, escaping; F16–F17. Duplicate `require_once auth.php` is harmless cleanup. |
| [logout.php](C:/xampp/htdocs/ratemyfaculty/logout.php) | POST requirement, CSRF, session destruction, redirect checked; shared F16, otherwise tested behavior passed. |
| [config/app.php](C:/xampp/htdocs/ratemyfaculty/config/app.php) | Base URL, environment, error settings; F06, F16, G03. Hard-coded base URL is documented and correct for this installation. |
| [config/database.php](C:/xampp/htdocs/ratemyfaculty/config/database.php) | PDO setup, credentials, connection failure handling; G03. Connection failure message avoids exposing the raw PDO error. |
| [database/schema.sql](C:/xampp/htdocs/ratemyfaculty/database/schema.sql) | Entire DDL and comparison with live structure; F01, F02, F07. |
| [database/seed.sql](C:/xampp/htdocs/ratemyfaculty/database/seed.sql) | All inserts/joins, credentials, dates, demo cohorts; F07–F08, G09. |
| [database/test_connection.php](C:/xampp/htdocs/ratemyfaculty/database/test_connection.php) | Connection query/output and anonymous HTTP access; F07. |
| [includes/auth.php](C:/xampp/htdocs/ratemyfaculty/includes/auth.php) | Every session/login/logout/current-user/dashboard helper; F05, F17, G04. |
| [includes/authorization.php](C:/xampp/htdocs/ratemyfaculty/includes/authorization.php) | Login/role guards and 403 output; F05; tested role denial works. |
| [includes/csrf.php](C:/xampp/htdocs/ratemyfaculty/includes/csrf.php) | Token generation/form output/verification; F16; ordinary invalid-token denial works. |
| [includes/validation.php](C:/xampp/htdocs/ratemyfaculty/includes/validation.php) | Escaping, required/optional text, positive integers, 1–5 ratings; F16. |
| [includes/audit.php](C:/xampp/htdocs/ratemyfaculty/includes/audit.php) | Parameterized audit insertion and all callers; F21, G05. |
| [includes/eligibility.php](C:/xampp/htdocs/ratemyfaculty/includes/eligibility.php) | Offering/enrollment/receipt query and every eligibility branch; F11, G01. |
| [includes/evaluation_window.php](C:/xampp/htdocs/ratemyfaculty/includes/evaluation_window.php) | Date comparisons and status labels; F06; boundary tests passed. |
| [includes/release_rule.php](C:/xampp/htdocs/ratemyfaculty/includes/release_rule.php) | Threshold and closed-window rule; predicate correct, surrounding lifecycle F03. |
| [admin/dashboard.php](C:/xampp/htdocs/ratemyfaculty/admin/dashboard.php) | Guard, links, logout, markup and text; G07. |
| [admin/students.php](C:/xampp/htdocs/ratemyfaculty/admin/students.php) | Create/update/toggle, transactions, logs, forms/list; F05, F09–F10, F16, F18–F21, G06. |
| [admin/faculty.php](C:/xampp/htdocs/ratemyfaculty/admin/faculty.php) | Create/update/toggle, transactions, logs, forms/list; F05, F09–F10, F16, F18–F21, G06. |
| [admin/courses.php](C:/xampp/htdocs/ratemyfaculty/admin/courses.php) | Create/update/toggle, credit validation, forms/list; F09–F10, F14, F16, F20, G05. |
| [admin/semesters.php](C:/xampp/htdocs/ratemyfaculty/admin/semesters.php) | Dates, create/update/lock, forms/list; F03, F06, F09–F10, F12–F13, F15–F16, F20, G05. |
| [admin/offerings.php](C:/xampp/htdocs/ratemyfaculty/admin/offerings.php) | Assignment create/update/toggle, options/forms/list; F04, F09–F10, F16, F20, G01–G02, G05. |
| [admin/enrollments.php](C:/xampp/htdocs/ratemyfaculty/admin/enrollments.php) | Enroll/complete/delete, receipt protection, options/list; F11, F20, G02, G05, G08. |
| [admin/progress.php](C:/xampp/htdocs/ratemyfaculty/admin/progress.php) | Offering/count queries, links, output; F11's downstream consistency impact; no independent query defect found. |
| [admin/progress_detail.php](C:/xampp/htdocs/ratemyfaculty/admin/progress_detail.php) | Offering lookup, named receipt status, escaping; F02–F03, F11. |
| [admin/results.php](C:/xampp/htdocs/ratemyfaculty/admin/results.php) | Metadata/count queries, release status and links; F22, shared F03/F06. |
| [admin/result_detail.php](C:/xampp/htdocs/ratemyfaculty/admin/result_detail.php) | Offering lookup, release gate, aggregates, comments; F02–F04, F22. |
| [student/dashboard.php](C:/xampp/htdocs/ratemyfaculty/student/dashboard.php) | Own enrollments/receipts, eligibility display, notices, escaping; F05/F06, G01/G07. |
| [student/evaluate.php](C:/xampp/htdocs/ratemyfaculty/student/evaluate.php) | Guard, own profile, offering validation, eligibility, form/privacy copy; F02–F03, G07–G08. |
| [student/submit_evaluation.php](C:/xampp/htdocs/ratemyfaculty/student/submit_evaluation.php) | POST/CSRF, eligibility, ratings/comment, transaction/duplicate handling, redirects; F02, F11, F16, F19, G08. |
| [faculty/dashboard.php](C:/xampp/htdocs/ratemyfaculty/faculty/dashboard.php) | Own offerings/counts, release links, logout/markup; F22, G07. |
| [faculty/offering_result.php](C:/xampp/htdocs/ratemyfaculty/faculty/offering_result.php) | Ownership 404, release gate, aggregates/comments; F02–F04, F22. |
| [faculty/summary.php](C:/xampp/htdocs/ratemyfaculty/faculty/summary.php) | Own released offerings, pooled average, empty state; arithmetic correct, shared F03/F06. |
| [assets/css/style.css](C:/xampp/htdocs/ratemyfaculty/assets/css/style.css) | All eight lines reviewed; valid basic styling, responsive/accessibility work in G07. |
| [assets/js/app.js](C:/xampp/htdocs/ratemyfaculty/assets/js/app.js) | Both placeholder comment lines reviewed; syntax check passed, no standalone defect. |

**Suggested implementation order**

1. Restore a reproducible database schema and verified seed in an isolated test database; preserve live constraints and data.
2. Address anonymity and historical integrity together: correlatable metadata/comment order, released-cohort lifecycle, and offering reassignment.
3. Correct timezone handling, revoke inactive sessions, restrict public setup material, and handle malformed input safely.
4. Repair edit recovery, use explicit state changes with redirects, and enforce transactional enrollment/lock/audit rules.
5. Align date/year/credit/password validation with storage; implement required recovery, audit, and deployment behavior.
6. Add regression coverage for these specific failures, then improve the interface and test it at realistic data volumes.
