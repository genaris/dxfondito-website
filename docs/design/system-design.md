# System design

| Item    | Value      |
| ------- | ---------- |
| Status  | Draft 5    |
| Date    | 2026-10-01 |

## 1. Purpose

This document tells how the software does the functions of the [functional specification](../spec/functional-specification.md).
It gives the architecture, the data model, the interface between the browser and the server, and the work phases.

A tag such as `[DQ7]` shows that a design item is a proposal.
The related question in section 13 is open.

## 2. Architecture

The system has three parts.

| Part            | Technology                         | Function                                                        |
| --------------- | ---------------------------------- | --------------------------------------------------------------- |
| Browser program | TypeScript, Vite, React            | It shows all pages. It gets and sends data through the API.     |
| API             | PHP 8.5, no framework              | It applies the rules, reads and writes the database, makes the documents. |
| Database        | MySQL 5.7                          | It keeps all data but the uploaded files.                       |

- The developer compiles the browser program into static files. The host supplies these files without PHP.
- The API is one PHP program with one entry file. It sends and receives JSON.
- The API uses PDO for the database and GD for the images.
- The API uses Composer for its libraries. The developer uploads the `vendor` folder with the other files.
- The system uses no scheduled tasks and no background processes (constraint C-5).
- All SQL must operate on MySQL 5.7. Thus the queries use no window functions and no common table expressions.
- All tables use the InnoDB engine and the `utf8mb4` character set.
- The design does not depend on `.htaccess` files. A test on the host showed that the host possibly ignores them.

### 2.1 Libraries

| Library              | Use                                                   |
| -------------------- | ----------------------------------------------------- |
| React                | Page components in the browser program.               |
| FPDF                 | The API puts the certificate image into a PDF file.   |
| PHPUnit              | Tests of the API.                                     |
| Vitest               | Tests of the browser program.                         |
| pnpm                 | Package manager for the browser program.              |

The API has its own ADIF reader.
The ADIF text format is simple, and an own reader gives a clear cause for each record that is not valid.

## 3. Data model

All table names and column names are in English.
All dates and times are in UTC.

### 3.1 Tables

**`users`**: the accounts of operators and administrators.

| Column                 | Type          | Notes                                         |
| ---------------------- | ------------- | --------------------------------------------- |
| `id`                   | integer, key  |                                               |
| `call_sign`            | text, unique  | Uppercase letters.                            |
| `name`                 | text          |                                               |
| `email`                | text, null    |                                               |
| `role`                 | enumeration   | `operator` or `administrator`.                |
| `password_hash`        | text          | From `password_hash()`.                       |
| `must_change_password` | boolean       | True for an initial password.                 |
| `active`               | boolean       |                                               |
| `failed_attempts`      | integer       | For FR-AUT-5.                                 |
| `locked_until`         | datetime, null | For FR-AUT-5.                                |

**`series`**: DPS and EFE. A table lets a later version add series (C-10).

| Column | Type         | Notes          |
| ------ | ------------ | -------------- |
| `id`   | integer, key |                |
| `code` | text, unique | `DPS`, `EFE`.  |
| `name` | text         |                |

**`refs`**: the permanent references. The name is short because `REFERENCES` is a reserved word in MySQL.

| Column        | Type         | Notes                              |
| ------------- | ------------ | ---------------------------------- |
| `id`          | integer, key |                                    |
| `series_id`   | integer      | Link to `series`.                  |
| `number`      | integer      | Unique together with `series_id`.  |
| `name`        | text         |                                    |
| `description` | text, null   |                                    |

**`activities`**: one operation for one reference.

| Column         | Type           | Notes                                              |
| -------------- | -------------- | -------------------------------------------------- |
| `id`           | integer, key   |                                                    |
| `reference_id` | integer        | Link to `refs`.                                    |
| `season`       | integer        | The year of `start_date`. The API calculates it.   |
| `start_date`   | date           |                                                    |
| `end_date`     | date           |                                                    |
| `description`  | text, null     |                                                    |

`reference_id` and `start_date` are unique together.
A reference can have more than one activity in a season (R-SEA-7).

**`logs`**: one uploaded ADIF file.

| Column          | Type         | Notes                                           |
| --------------- | ------------ | ----------------------------------------------- |
| `id`            | integer, key |                                                 |
| `activity_id`   | integer      | Link to `activities`.                           |
| `operator_id`   | integer      | Link to `users`. The operator of the log.       |
| `uploaded_by`   | integer      | Link to `users`. The user who did the upload.   |
| `file_name`     | text         | The original name.                              |
| `stored_name`   | text         | The name in the file store. The API makes it.   |
| `contact_count` | integer      |                                                 |
| `uploaded_at`   | datetime     |                                                 |

**`contacts`**: one valid record of a log.

| Column           | Type         | Notes                                                  |
| ---------------- | ------------ | ------------------------------------------------------ |
| `id`             | integer, key |                                                        |
| `log_id`         | integer      | Link to `logs`. The database deletes the contacts with the log. |
| `activity_id`    | integer      | A copy from the log, for fast queries.                 |
| `call_sign`      | text         | As the log gives it, in uppercase letters.             |
| `base_call_sign` | text         | See section 4.1.                                       |
| `name`           | text, null   | From `NAME`.                                           |
| `qso_at`         | datetime     | From `QSO_DATE` and `TIME_ON`.                         |
| `frequency`      | decimal, null | From `FREQ`, in MHz.                                  |
| `band`           | text, null   | From `BAND`.                                           |
| `mode`           | text         | From `MODE`.                                           |
| `rst_sent`       | text, null   |                                                        |
| `rst_rcvd`       | text, null   |                                                        |

Indexes: (`activity_id`, `base_call_sign`, `qso_at`) and (`base_call_sign`).

**`certificate_levels`**: 5, 10 and 15. A table lets a later version add levels (C-10).

| Column   | Type            |
| -------- | --------------- |
| `id`     | integer, key    |
| `points` | integer, unique |

**`qsl_templates`**: one template for one operator in one activity.

| Column        | Type         | Notes                                      |
| ------------- | ------------ | ------------------------------------------ |
| `id`          | integer, key |                                            |
| `activity_id` | integer      | Unique together with `operator_id`.        |
| `operator_id` | integer      | Link to `users`.                           |
| `stored_name` | text         | The image in the file store.               |
| `fields`      | JSON         | See section 6.1.                           |

**`certificate_templates`**: one template for one level in one season.

| Column        | Type         | Notes                                 |
| ------------- | ------------ | ------------------------------------- |
| `id`          | integer, key |                                       |
| `season`      | integer      | Unique together with `level_id`.      |
| `level_id`    | integer      | Link to `certificate_levels`.         |
| `stored_name` | text         | The image in the file store.          |
| `fields`      | JSON         | See section 6.1.                      |

**`audit_entries`**: the record of actions (FR-AUD).

| Column       | Type         | Notes                                        |
| ------------ | ------------ | -------------------------------------------- |
| `id`         | integer, key |                                              |
| `user_id`    | integer      | Link to `users`.                             |
| `action`     | text         | For example `log.upload`, `log.delete`.      |
| `entity_id`  | integer, null |                                             |
| `detail`     | JSON, null   | For example the file name of a deleted log.  |
| `created_at` | datetime     |                                              |

**`schema_migrations`**: the list of the database changes that the host has.

### 3.2 No stored points

The database has no table for points, positions or certificates.
The API calculates them from `contacts` for each request (R-PTS-4, R-OPR-6).
Thus the deletion of a log needs no second step, and the data cannot disagree.

The group has a small quantity of data: some thousands of contacts for each season.
With the indexes of section 3.1, these queries are fast.

## 4. Calculations

### 4.1 Base call sign

1. Change the call sign to uppercase letters and remove the spaces.
2. Divide the call sign at each `/` character.
3. Use the longest part. If two parts have the same length, use the first part.

| Call sign    | Base call sign |
| ------------ | -------------- |
| `LU1ABC`     | `LU1ABC`       |
| `LU1ABC/P`   | `LU1ABC`       |
| `LU1ABC/QRP` | `LU1ABC`       |
| `CX/LU1ABC`  | `LU1ABC`       |

### 4.2 Points and ranking

For one season, the API counts the different references for each base call sign.
Thus a second activity of a reference gives no second point (R-PTS-2a).

```sql
SELECT c.base_call_sign,
       COUNT(DISTINCT a.reference_id) AS points
FROM contacts c
JOIN activities a ON a.id = c.activity_id
WHERE a.season = :season
GROUP BY c.base_call_sign
ORDER BY points DESC, c.base_call_sign;
```

The same query counts the references of each series for FR-PUB-3.
The API gives the same position to participants with equal points.

### 4.3 First contact

For one participant, one reference and one season, the first contact is the contact with the lowest `qso_at`.
The query includes all activities of the reference in that season.
If two contacts have the same `qso_at`, the API uses the contact with the lowest `id`.
The log of this contact gives the operator (R-OPR-4) and the QSL card template (FR-QSL-9).
The query uses a `MIN(qso_at)` subquery, because MySQL 5.7 has no window functions.

### 4.4 Certificates

1. Get the activities of the season where the participant has a contact.
2. For each reference, keep only the activity of the first contact. This activity gave the point.
3. Put these activities in the order of `start_date`.
4. For a level of N points, the participant has the certificate if the list has N or more activities.
5. The certificate date is the `start_date` of activity number N in the list (R-CER-5).

## 5. Logs

### 5.1 ADIF reader

- The reader ignores all text before `<EOH>`, if the file has a header.
- The reader reads fields with the form `<NAME:length>data`. The field names are not case-sensitive.
- `<EOR>` ends each record.
- The length is a number of bytes.
- If the data is not valid UTF-8, the reader changes it from ISO-8859-1 to UTF-8. Thus names with accents stay correct.
- `QSO_DATE` must have the form `YYYYMMDD`. `TIME_ON` must have the form `HHMM` or `HHMMSS`.
- The reader gives the record number and the cause for each record that is not valid.

### 5.2 Upload in two steps

The API keeps no data between the summary and the save operation.

1. The browser sends the file with `mode=preview`. The API reads the file and sends the summary. It saves nothing.
2. The user accepts. The browser sends the same file again with `mode=save`.
3. The API reads the file again and saves the log and its contacts in one database transaction.

The API accepts ADIF files up to 5 MB.

## 6. QSL cards and certificates

### 6.1 Template fields

The `fields` column holds one entry for each field.
For a QSL card, the fields are `call_sign`, `name`, `date`, `time`, `frequency`, `mode` and `rst`.
For a certificate, the fields are `call_sign` and `date`.

```json
{
  "call_sign": { "x": 420, "y": 310, "size": 48, "colour": "#1A1A1A", "align": "center", "font": "sans-bold" }
}
```

- `x` and `y` are in pixels of the template image.
- `font` is the name of one of the TrueType fonts that the system includes. The fonts have an open licence.
- In the browser, the user moves each field on the template image and sees the result immediately.
- Before the save operation, the API makes a sample image with example data (FR-QSL-5).

### 6.2 Image and PDF

- The API makes each document when a visitor downloads it. It keeps no copy.
- For a QSL card, the API opens the template with GD, writes the fields with `imagettftext()`, and sends a JPEG image.
- For a certificate, the API makes the image in the same way. Then FPDF puts the image on one PDF page.
- The API accepts JPEG and PNG templates up to 10 MB.
- The API opens each uploaded template with GD before it saves the file. It refuses a file that GD cannot read.

## 7. Security

- All database queries use prepared statements.
- The API uses PHP sessions. The session cookie has the `HttpOnly`, `Secure` and `SameSite=Lax` attributes.
- Each request that changes data must have a token header (`X-CSRF-Token`). The API compares it with the token of the session.
- The API makes a new session identifier at each sign-in.
- The API keeps the session files in `storage/sessions/`. Thus the cleanup of the shared PHP folder of the host does not end a session early.
- A password has 8 or more characters and 72 or fewer bytes, because `password_hash()` uses only the first 72 bytes.
- The API checks the role and the owner for each protected request. The browser program only hides the buttons.
- The API makes the names of stored files. It does not use a name that a user supplies.
- The web server does not supply the file store, the PHP source files or the configuration file.
- The configuration file holds the database password. It is not in the Git repository.

## 8. API

The API has one entry file, `api/index.php`.
The browser sends the path of the request in the `r` parameter, such as `api/index.php?r=/seasons/2026/ranking`.
Thus the API needs no rewrite rules on the web server.
The tables below show only the path.
A visitor can use the public requests.

### 8.1 Public requests

| Method and path                                        | Result                                         |
| ------------------------------------------------------ | ---------------------------------------------- |
| `GET /seasons`                                         | The list of seasons and the current season.    |
| `GET /seasons/{season}/ranking`                        | The ranking of the season.                     |
| `GET /seasons/{season}/activities`                     | The activities of the season.                  |
| `GET /activities/{id}`                                 | The activity, its operators, its participants. |
| `GET /participants/{call}`                             | The seasons, points, activities, certificates. |
| `GET /participants/{call}/qsl/{season}/{referenceId}`  | The QSL card as a JPEG image.                  |
| `GET /participants/{call}/certificates/{season}/{points}` | The certificate as a PDF file.              |

### 8.2 Session requests

| Method and path          | Result                              |
| ------------------------ | ----------------------------------- |
| `GET /session`           | The signed-in user and the token.   |
| `POST /session`          | Sign-in.                            |
| `DELETE /session`        | Sign-out.                           |
| `PUT /session/password`  | A new password for the user.        |

### 8.3 Operator requests

| Method and path                                   | Result                                            |
| ------------------------------------------------- | ------------------------------------------------- |
| `POST /activities/{id}/logs`                      | The summary (`mode=preview`) or a new log (`mode=save`). |
| `GET /activities/{id}/logs`                       | The logs of the activity.                         |
| `GET /logs/{id}`                                  | The log and its contacts.                         |
| `GET /logs/{id}/file`                             | The original file.                                |
| `DELETE /logs/{id}`                               | The deletion of the log.                          |
| `GET /activities/{id}/qsl-templates`              | The templates of the activity.                    |
| `PUT /activities/{id}/qsl-templates/{operatorId}` | A new or changed template.                        |
| `DELETE /activities/{id}/qsl-templates/{operatorId}` | The deletion of the template.                  |
| `POST /template-preview`                          | A sample image for a template and its fields.     |

### 8.4 Administrator requests

| Method and path                                        | Result                              |
| ------------------------------------------------------ | ----------------------------------- |
| `GET`, `POST /references`                              | The list, a new reference.          |
| `PUT`, `DELETE /references/{id}`                       | A change, a deletion.               |
| `POST /activities`                                     | A new activity.                     |
| `PUT`, `DELETE /activities/{id}`                       | A change, a deletion.               |
| `GET`, `POST /users`                                   | The list, a new account.            |
| `PUT /users/{id}`                                      | A change, which includes `active`.  |
| `PUT /users/{id}/password`                             | A new initial password.             |
| `GET /certificate-templates`                           | The templates of all seasons.       |
| `PUT`, `DELETE /certificate-templates/{season}/{points}` | A new or changed template, a deletion. |
| `GET /audit`                                           | The record of actions.              |

There is no request to delete an account. An administrator deactivates it (FR-USR-5, FR-USR-7).
A new initial password also opens a locked account.

## 9. Pages of the browser program

| Path                         | Page                                             | User          |
| ---------------------------- | ------------------------------------------------ | ------------- |
| `/`                          | Ranking of the current season                    | Visitor       |
| `/temporada/{season}`        | Ranking of a different season                    | Visitor       |
| `/actividades`               | Activity list                                    | Visitor       |
| `/actividad/{id}`            | Activity, participants, logs, QSL card templates | Visitor, operator |
| `/participante/{call}`       | Participant, QSL cards, certificates             | Visitor       |
| `/ingresar`                  | Sign-in                                          | Visitor       |
| `/contrasena`                | Password change                                  | Operator, administrator |
| `/admin/referencias`         | References                                       | Administrator |
| `/admin/actividades`         | Activities                                       | Administrator |
| `/admin/usuarios`            | Accounts                                         | Administrator |
| `/admin/certificados`        | Certificate templates                            | Administrator |
| `/admin/registro`            | Record of actions                                | Administrator |

The browser program uses hash paths, such as `/#/participante/LU1ABC`.
The table shows the part of the path after the `#` character.
Thus the web server needs no rewrite rules, and each page has an address that a user can share.

A user with an initial password sees only the password change page until the change (FR-AUT-3).

## 10. Repository and installation

### 10.1 Repository

```text
docs/               Specification and design documents
web/                Browser program (TypeScript, Vite, pnpm)
api/
  public/           Entry file of the API
  src/              PHP source files
  migrations/       Numbered SQL files
  tests/            PHPUnit tests
  bin/              Command line scripts
docker/             Image of the PHP container
compose.yaml        Local environment
tools/              Scripts for the host check, the build and the installation
.github/workflows/  Tests and installation
```

### 10.2 Host

```text
public_html/
  index.html, assets/    Compiled browser program
  api/index.php          Entry file of the API
  api/private-path.php   Location of the private folder. The build makes this file.
dxfondito-app/           Private folder, adjacent to public_html, not available from the web
  src/, vendor/          PHP source files and libraries
  migrations/
  storage/logs/          Original ADIF files
  storage/templates/     Template images
  config.php             Database data and secrets. The developer makes this file on the host.
```

The installation does not change `config.php` or the `storage/` folder.

### 10.3 Installation

The files for the host are not the source files of the repository.
They are the compiled browser program, the PHP source files, the PHP libraries and the migrations.
The host has no tool to compile the browser program.
Thus the Git function of the host is not sufficient, and the design does not use it.

GitHub Actions connects the installation to Git:

1. The developer pushes a commit to the `main` branch on GitHub.
2. A workflow runs the tests.
3. The workflow compiles the browser program and installs the PHP libraries without the test tools.
4. The workflow puts the result in one folder with the structure of section 10.2.
5. The workflow uploads the changed files to the host through FTP.
6. The developer opens the migration page. The page applies the new files of `migrations/`.

- The FTP user name and the FTP password are secrets of the GitHub repository. They are not in the source files.
- The workflow uses FTPS if the host has it.
- The migration page needs a secret from `config.php`.
- While there are no accounts, the migration page also makes the first administrator (`POST /migrate/administrator`).
  The host has no command line. Thus this is the only way to make the first account.
- `tools/build.sh` does steps 3 and 4. The workflow and the developer use the same script.
- `tools/deploy-ftp.sh` does step 5 with `lftp`. The workflow and the developer use the same script.
- The workflow uploads files only when the `DEPLOY_ENABLED` variable of the repository is `true`.

### 10.4 Local environment

The developer uses Docker Compose with PHP 8.5 and MySQL 5.7, the same versions as the host.
Vite supplies the browser program and sends the `/api` requests to the PHP container.

### 10.5 Site address

The site uses the main domain at first.
The group will possibly move the site to a subdomain.
This move must need no software change.

- The browser program uses relative paths for its files and for the API.
- No source file contains the domain name.
- Thus the site operates in the root folder or in a subfolder without a change.
- The configuration file gives the location of the private folder.

## 11. Work phases

Each phase ends with a version that operates on the host.

| Phase | Content                                                                                   |
| ----- | ----------------------------------------------------------------------------------------- |
| 1     | Repository structure, local environment, migrations, installation workflow, empty page on the host. |
| 2     | Accounts, sign-in, sessions, account administration, record of actions.                   |
| 3     | References and activities, with their public list.                                        |
| 4     | ADIF reader, upload in two steps, log list, log deletion.                                 |
| 5     | Ranking, seasons, participant page, activity page.                                        |
| 6     | QSL card templates, field editor, QSL card download.                                      |
| 7     | Certificate templates, certificate download.                                              |
| 8     | Tests with real logs of the group, corrections, release.                                  |

The ADIF reader, the base call sign and the calculations of section 4 have automatic tests from the start.

## 12. Design decisions

| Id  | Decision                                                                                          |
| --- | ------------------------------------------------------------------------------------------------- |
| DQ1 | The browser program uses React. The developer knows it well, and the larger size has no important effect here. |
| DQ2 | The design does not depend on `.htaccess` files. It uses hash paths and one API entry file. |
| DQ3 | The private files go in a folder above `public_html`.                                             |
| DQ4 | A GitHub Actions workflow installs the files through FTP after each push to `main`. A local script is the alternative. |
| DQ5 | The host has MySQL 5.7.                                                                           |
| DQ6 | The site uses the main domain at first. A later move to a subdomain needs no software change. |

## 13. Open design questions

There are no open design questions at this time.
