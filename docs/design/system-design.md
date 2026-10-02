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
| FPDF                 | The API puts the certificate image into a PDF file. The Composer package `setasign/fpdf`. |
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
| `start_time`   | time, null     | UTC, on `start_date` (FR-ACT-3b). Migration 0004.  |
| `end_date`     | date           |                                                    |
| `end_time`     | time, null     | UTC, on `end_date`. Null for an activity before migration 0004. |
| `description`  | text, null     |                                                    |

`reference_id` and `start_date` are unique together.
A reference can have more than one activity in a season (R-SEA-7).

- The number of a reference is 1 to 999.
- The proposed number for a new reference (FR-REF-3) is the highest number of the series and one.
  Thus the code of a deleted reference does not come back.
- The end date of an activity is on or after its start date.
- The descriptions have 2000 or fewer characters.

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
| `station_call_sign` | text, null | From `STATION_CALLSIGN`, such as LU2AOG/A (FR-LOG-7b). |
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

The actions of the QSL card templates are `qsl-template.save` and `qsl-template.delete`.
The actions of the certificate templates are `certificate-template.save` and `certificate-template.delete`. The detail has the season and the points of the level.
The actions of the accounts are `user.create`, `user.update`, `user.password.reset` (an administrator sets an initial password) and `user.password.change` (the user changes the own password).
The detail has the call sign of the account. For `user.update`, it has only the changed values, each with the old and the new value.
The record never has a password.
`GET /audit` gives 50 entries for each page, the newest first.

**`schema_migrations`**: the list of the database changes that the host has.

A migration is a `.sql` file, or a `.php` file that returns a function `function (PDO $pdo, string $storageDir): void`.
A PHP migration changes data that SQL cannot change, for example from the stored files.
Migration `0003_station_call_sign_backfill.php` reads the stored ADIF files and fills `station_call_sign` for the contacts of the logs before migration 0002.
It finds each contact by its call sign and its time. It changes only contacts without a station call sign. Thus it can run again.

The operator of a contact on the pages is `COALESCE(station_call_sign, users.call_sign)`: the station call sign of the log, or the call sign of the account.
The list of operators of an activity uses the same value. Thus an operator with two suffixes in one activity has two items.

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

For one season, the API reads one line for each participant and reference, not one line for each contact:

```sql
SELECT DISTINCT c.base_call_sign, a.reference_id, s.code, r.number
FROM activities a
JOIN contacts c ON c.activity_id = a.id
JOIN refs r ON r.id = a.reference_id
JOIN series s ON s.id = r.series_id
WHERE a.season = :season;
```

- The index (`activity_id`, `base_call_sign`, `qso_at`) of `contacts` gives the base call signs without a read of the contact lines.
- The points of a participant are the number of its lines. Thus a second activity of a reference gives no second point (R-PTS-2a).
- The same lines give the list of the references of each line of the ranking (FR-PUB-3, D-25). Thus the list needs no second query.
- The ranking needs no first contact and no operator. Thus the query has no join with `logs` and `users`.

The API gives the same position to participants with equal points.
The next position skips the shared positions: 1, 1, 3.

The participant page and the certificates need the first contacts (section 4.3).
For them, the API reads the contacts with their activity, reference and log operator,
and calculates the first contacts in PHP (`Ranking\Calculator`).

### 4.3 First contact

For one participant, one reference and one season, the first contact is the contact with the lowest `qso_at`.
The query includes all activities of the reference in that season.
If two contacts have the same `qso_at`, the API uses the contact with the lowest `id`.
This contact gives the point. Its activity gives the date of a certificate (R-OPR-4).
Each contact, the first one or not, gives a QSL card with the template of its own operator for its own activity (FR-QSL-9, D-27).
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
- A last record without `<EOR>` is also a record. Thus the summary shows it.
- `CALL` has 3 to 20 letters, digits or `/` characters. `MODE` has 1 to 20 letters, digits, `-` or `/` characters.
- `FREQ` is a positive number of MHz below 10000. The API keeps six decimals or fewer.
- `BAND` has the ADIF form, such as `40m` or `70cm`.
- The API cuts `NAME` to 100 characters and `RST_SENT`, `RST_RCVD` to 10 characters.
- The `STATION_CALLSIGN` warning compares base call signs. Thus `LU1ABC/P` gives no warning for the operator `LU1ABC`.

### 5.2 Upload in two steps

The API keeps no data between the summary and the save operation.

1. The browser sends the file with `mode=preview`. The API reads the file and sends the summary. It saves nothing.
2. The user accepts. The browser sends the same file again with `mode=save`.
3. The API reads the file again and saves the log and its contacts in one database transaction.

The API accepts ADIF files up to 5 MB.
The build puts a `.user.ini` file next to `api/index.php` with the upload limits (section 6.3).

- An operator uploads logs only for the same operator. An administrator selects the operator (FR-LOG-4).
- The API saves the original file with a random name in `storage/logs/`. If the database transaction fails, the API deletes the file.
- `GET /activities/{id}` also gives the call signs of the operators with a log (FR-ACT-2a).
- `GET /activities/{id}/logs` and `GET /logs/{id}` need a session. The download of the file needs the operator of the log or an administrator.

## 6. QSL cards and certificates

### 6.1 Template fields

The `fields` column holds one entry for each field.
For a QSL card, the fields are `call_sign`, `name`, `date`, `time`, `frequency`, `mode` and `rst`.
For a certificate, the fields are `call_sign` and `date`.

```json
{
  "call_sign": { "x": 420, "y": 250, "width": 360, "height": 60, "colour": "#1A1A1A", "align": "center", "font": "sans-bold" }
}
```

- Each field is a box: `x`, `y`, `width` and `height` are in pixels of the template image. The box must be in the image.
  `width` is 10 or more. `height` is 6 to 500.
- The text fills the height of the box: the font size is `height / 1.2` pixels.
  If the text is wider than the box, it becomes smaller, to 0.97 of the box width. Thus a long name stays in the box.
- On a QSL card, all fields share one size: the smallest size at which each text fits its box (`TextBox::sharedSizes`).
  A field that needs less than 0.75 of the median size, such as a long name, keeps its own smaller size. Thus a long name does not make the whole card small.
  The API calculates the shared size for each card with its own texts. The editor uses the same rule with the example data.
  A certificate keeps the size of each field: a large call sign and a smaller date.
- The base line puts the capital letters in the vertical centre of the box: `y + (height + 0.72 × size) / 2`.
- `align` puts the text at the left side, in the centre or at the right side of the box.
- GD uses points at 96 dots for each inch. Thus the API uses `size × 0.75` points.
  GD does not scale the width of small texts in proportion. Thus the API measures again after it makes a text smaller.
- `api/src/Templates/TextBox.php` and `web/src/qsl.ts` have the same rules and the same tests.
- `font` is `sans`, `sans-bold`, `serif`, `serif-bold` or `mono-bold`: the DejaVu fonts of `api/fonts/`, with a free licence.
  `GET /fonts/{name}` supplies them. Thus the editor of the browser draws the text with the same fonts as the API.
- The editor shows each field as a box with its name, on an SVG layer in the pixels of the image.
  The user moves a box with the mouse or a finger, and changes its size with its corners and sides.
- Before the save operation, the API makes a sample image with example data (FR-QSL-5).

### 6.2 Search of the field boxes

The editor tries to find the field boxes of a new template image (FR-QSL-12). The code is `web/src/detect.ts`.
The source of the method is `qsl_send/detect.py` of the qsl-send program. The two give the same boxes for the six templates of 2026.

- The search is in the browser, on the pixels of the image in a canvas. It needs no request to the API, and the image needs no save operation first.
  It takes about 25 ms for an image of 1600 × 1060 pixels.
- It uses only the colours of the image. It does not read the texts, and it uses no external service.
- The steps:
  1. The candidate colours are the 6 most common saturated colours in the part below 55 % of the height.
     A saturated colour has 25 or more of difference between its largest and its smallest channel. Thus the search skips white, black and grey.
     The search counts each second pixel of each second line, with the colours in steps of 8. Then it takes the exact most common colour of each step.
  2. For each candidate colour, the pixels with each channel at 26 or less from the colour make connected areas.
     An area is a box if it is 40 × 18 pixels or more, and the colour fills 75 % or more of its rectangle. Thus texts and drawings are not boxes.
     Areas that start above 55 % of the height are not boxes. Areas smaller than 15 % of the median box are not boxes.
  3. The candidate with the most boxes wins, not the candidate with the most pixels. A background of one colour has more pixels than seven boxes.
     This choice uses a copy of the image of 400 pixels of width. Then the search finds the boxes of the winner on the full image.
  4. The boxes go in rows from top to bottom, and from left to right in each row.
     A box starts a new row if its top is below the bottom of the row less a third of its height.
- If the search finds exactly 7 boxes, they go to the fields in the order `date`, `call_sign`, `name`, `frequency`, `time`, `mode`, `rst` (FR-QSL-12a).
  The text goes in the centre of each box. The fields keep their colour and font.
  The box keeps the limits of the API: for example, a height of 500 pixels at most.
- With a different number of boxes, a new template gets the first layout of `defaultFields`, and an existing template keeps its fields.
  The editor shows the number of boxes (FR-QSL-12d).
- The search runs by itself when the user chooses the image of a new template.
  For a new image of an existing template, the fields stay. The button "Detectar recuadros" starts the search.
- The button "Elegir el color" waits for a click on the image. The colour of that pixel is the only candidate (FR-QSL-12c).
- "Cambiar lugar con" exchanges the boxes of the selected field and a different field. The two fields keep their colour, alignment and font (FR-QSL-12b).
- If the browser cannot read the pixels of the image, the editor tells the user, and the user puts the fields in place with the mouse.

### 6.3 Image and PDF

- The API makes each document when a visitor downloads it. It keeps no copy.
- For a QSL card, the API opens the template with GD, writes the fields with `imagettftext()`, and sends a JPEG image.
- For a certificate, the API makes the image in the same way. Then FPDF puts the image on one PDF page.
  The page has the proportions of the image and no margin. Its long side is 297 mm, the long side of an A4 sheet.
  Thus an A4 template gives an A4 page. A landscape image gives a landscape page.
  FPDF reads the image from a file. Thus the API writes the JPEG image to a temporary file and deletes it after the PDF file is complete.
- The texts of the certificate: the base call sign (R-CALL-3), and the certificate date (R-CER-5) in Spanish words, such as `4 de octubre de 2026`.
- The name of a QSL card file is `QSL_{base call sign}_{reference}_{YYYYMMDD}_{HHMM}.jpg`, with the date and the time of the contact. Thus each contact has a different file name.
- The name of the PDF file is `Certificado_{call sign}_{season}_{points}.pdf`. The download link of the browser uses the level name, such as `Certificado_LU1ABC_2026_Bronce.pdf`.
- The sample image of a certificate uses `LU1ABC` and `4 de octubre de 2026`.
- The certificate templates use the same field editor as the QSL card templates, with the fields `call_sign` and `date`. The search of the field boxes (section 6.2) is only for QSL card templates.
  The first layout of a new certificate template puts a large call sign in the centre, and the date below it.
- The API accepts JPEG and PNG templates up to 10 MB and 4096 pixels on each side. Larger images need more memory than PHP has on a shared host.
- The build puts `upload_max_filesize = 11M` and `post_max_size = 12M` in the `.user.ini` file of `api/`.
- The API keeps the template images in `storage/templates/` with a random name. A new image deletes the old file.
- The texts of the QSL card: the date as `DD/MM/YYYY`, the time as `HH:MM` (UTC), the frequency as `7.130 MHz` (FR-LOG-7a), or the band if the log has no frequency.
  `api/src/Logs/Frequency.php` makes the text of the frequency for the ADIF reader, the log pages, the participant page and the QSL card.
- The sample image (FR-QSL-5) uses example data: `LU1ABC/P`, `Juana Pérez`, `04/10/2026`, `14:30`, `7.130 MHz`, `SSB`, `59`.
- An activity with QSL card templates cannot be deleted, as an activity with logs (FR-ACT-8).
- The API opens each uploaded template with GD before it saves the file. It refuses a file that GD cannot read.

### 6.4 Lists of licensees

The name on a QSL card comes from the official lists of licensees (FR-QSL-3a). The code is in `api/src/Registry/`.

| Country   | Source | Page | Format |
| --------- | ------ | ---- | ------ |
| Argentina | ENACOM | `https://hertz.enacom.gob.ar/se/portal/arg/publico/ListadoRadioaficionado.php` | An HTML table. The page shows all licensees after a POST request with its CSRF token and `mostrarTodos=1`. About 16 000 lines, 7 MB. |
| Uruguay   | URSEC  | `https://www.gub.uy/unidad-reguladora-servicios-comunicaciones/tematica/radioaficionados` | The page links an ODS file each month, such as `files/2026-07/Nomina CX Vigentes Julio 2026.ods`. About 1 300 lines. |

- The button "Actualizar" of `/admin/licencias` sends `POST /registries/{ar|uy}`. The API downloads the list with curl and replaces the licensees of that country in one transaction.
- ENACOM: the columns "Radioaficionado" (the name) and "Señal Distintiva". URSEC: "Distintivo de Llamada", "Nombres" and "Apellidos/Razón Social". The name is the given names and then the surnames.
- The system keeps only the call sign and the name. The lists have other data, such as the city and the validity of a certificate of criminal records. The system does not keep these.
- A list with fewer than 5 000 (Argentina) or 300 (Uruguay) licensees means a change of the source page. The API refuses it and keeps the old list.
- The ODS file is a ZIP archive. The shared host can lack the zip extension of PHP. Thus `ZipReader` reads `content.xml` with the zlib extension.
- The server of ENACOM does not send its intermediate certificate. Browsers find it, but curl does not. Thus `api/certs/sectigo-r36-chain.pem` has the intermediate certificate and the root of Sectigo, and the API verifies the server with this file. The intermediate certificate is valid until 2036.
- The QSL card shows the name with a capital letter at the start of each word (`LicenseeName`). Particles such as "de" and "del" stay in lowercase letters. The lists of Argentina have no accents.
- The table `licensees` has `call_sign` (key), `country` and `name`. The table `licensee_updates` has the date and the number of licensees of the last update of each country. Migration 0005.
- The action of the record is `registry.update`, with the country and the number of licensees.

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
| `GET /seasons/{season}/ranking`                        | The ranking of the season, and the levels with a certificate template (`certificateLevels`). |
| `GET /seasons/{season}/activities`                     | All activities of the season, each with `contactCount`. The public list shows those with contacts (FR-PUB-13b). |
| `GET /activities/{id}`                                 | The activity, its operators, `participantCount`, and all its `contacts` in the order of time, each with `qsl`. |
| `GET /participants/{call}`                             | The seasons, points, activities, certificates. Each certificate has `available`: true if its level has a template for the season. Each season has all `contacts`, each with `point` (true for the first contact with the reference) and `qsl`. |
| `GET /participants/{call}/qsl/{contactId}`             | The QSL card of one contact, as a JPEG image (D-27). The contact must be of that participant. |
| `GET /fonts/{name}`                                    | A TrueType font, for the field editor.         |
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
| `GET /activities/{id}/qsl-templates/{operatorId}/image` | The image of the template.                  |
| `POST /activities/{id}/qsl-templates/{operatorId}` | A new or changed template (multipart: `fields`, and `file` for a new image). |
| `DELETE /activities/{id}/qsl-templates/{operatorId}` | The deletion of the template.                  |
| `POST /template-preview`                          | A sample image for a template and its fields (`file`, or `activityId` and `operatorId`). |

The save operation of a template uses `POST`, not `PUT`, because PHP reads the files of a multipart body only for `POST`.

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
| `GET /certificate-templates`                           | The certificate levels and the templates of all seasons. |
| `GET /certificate-templates/{season}/{points}/image`   | The image of the template.          |
| `POST`, `DELETE /certificate-templates/{season}/{points}` | A new or changed template (multipart: `fields`, and `file` for a new image), a deletion. |
| `POST /certificate-preview`                            | A sample image for a certificate template and its fields (`file`, or `season` and `points`). |
| `GET /registries`                                      | The last update of each list of licensees. |
| `POST /registries/{country}`                           | Downloads the list of `ar` or `uy` (section 6.4). |
| `GET /audit`                                           | The record of actions.              |

`GET /seasons` gives the seasons with activities and always the current season, the newest first.
`GET /references` also gives the series, each with its proposed number for a new reference.

There is no request to delete an account. An administrator deactivates it (FR-USR-5, FR-USR-7).
A new initial password also opens a locked account.

## 9. Pages of the browser program

| Path                         | Page                                             | User          |
| ---------------------------- | ------------------------------------------------ | ------------- |
| `/`                          | Ranking of the current season                    | Visitor       |
| `/temporada/{season}`        | Ranking of a different season                    | Visitor       |
| `/actividades`               | Activity list of the current season              | Visitor       |
| `/actividades/{season}`      | Activity list of a different season              | Visitor       |
| `/actividad/{id}`            | Activity, logs, QSL card templates, contacts     | Visitor, operator |
| `/participante/{call}`       | Participant, QSL cards, certificates             | Visitor       |
| `/log/{id}`                  | Contacts of a log                                | Operator, administrator |
| `/estado`                    | Status of the API and the database               | Visitor       |
| `/programa`                  | Rules of the program                             | Visitor       |
| `/ingresar`                  | Sign-in                                          | Visitor       |
| `/contrasena`                | Password change                                  | Operator, administrator |
| `/admin/referencias`         | References                                       | Administrator |
| `/admin/actividades`         | Activities                                       | Administrator |
| `/admin/usuarios`            | Accounts                                         | Administrator |
| `/admin/certificados`        | Certificate templates                            | Administrator |
| `/admin/licencias`           | Lists of licensees of Argentina and Uruguay      | Administrator |
| `/admin/registro`            | Record of actions                                | Administrator |

The paths of the pages are in Spanish, as the user interface (C-6). Users see them and share them.
The paths of the API (section 8), the source code and the documents are in English.

The browser program uses hash paths, such as `/#/participante/LU1ABC`.
The table shows the part of the path after the `#` character.
Thus the web server needs no rewrite rules, and each page has an address that a user can share.

A user with an initial password sees only the password change page until the change (FR-AUT-3).

The texts of the program (purpose, bands, modes, hours, certificates) are in `web/src/program.ts`. Their source is the page of LU2AOZ on QRZ.com.
The footer and the program page link to the Facebook group of the group: https://www.facebook.com/groups/1097332786007967.
The program page also shows:
- the logos of the activators, from the page of LU2AOZ on QRZ.com, in `web/src/assets/activators/`: WebP images with a transparent background, 320 pixels at most. LU2AOZ comes first, with the logo of the group;
- the planned calendar of the season (`CALENDAR` in `web/src/program.ts`). The past events are pale and the next event has a mark.
  An event links to the activity of the API with the same date and series, if the group already loaded it.
  The calendar is a plan: the confirmed dates are the activities.
The names of the certificate levels are Bronce (5), Plata (10) and Oro (15).
The home page shows the activity in progress or the next one: the earliest activity that has not ended. It comes from the activities of the API, with its description.
The end of an activity is its end date and end time. An activity without hours ends at the end of its last day.
The card says "¡En el aire!" between the start and the end, "Hoy" before the start on the start date, and "Próxima actividad" for a later day.
It shows the date, the hours in UTC and the hours of Argentina (UTC−3, all year).
The API requires the hours for each new or changed activity. The warning of a log uses only the dates (FR-LOG-12).

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
  fonts/                 TrueType fonts for the QSL cards and the certificates
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
