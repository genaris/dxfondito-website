# Functional specification

| Item    | Value      |
| ------- | ---------- |
| Status  | Draft 8    |
| Date    | 2026-10-01 |

## 1. Purpose

This document tells what the system does for its users.
It does not tell how the software does it.
A separate design document will give the architecture and the data model.

The system is a website for the DX Fondito group of radio amateurs.
It is for the "Diploma Puestos de Salud" programme of the group.
The group operates stations from parks and other places.
Other radio amateurs make contacts with these stations.
The system counts these contacts, shows a ranking, and supplies QSL cards and certificates.

A tag such as `[Q19]` shows that a rule is a default.
The related question in section 9 is open until the group gives an answer.

## 2. Scope

### 2.1 In scope for version 1

- A public ranking of participants for each season.
- A public page for each participant, with QSL cards and certificates.
- A public list of the activities of each season.
- Accounts for operators and administrators.
- The upload and the deletion of ADIF logs.
- The administration of references, activities, accounts and templates.

### 2.2 Out of scope for version 1

- Accounts for participants.
- Email messages from the system.
- The ADX file format (ADIF in XML).
- Connections to external services, such as LoTW, eQSL or QRZ.
- A user interface in a language other than Spanish.
- A function to add the name of a participant manually or from an external system. A later version can add it.
- A ranking that includes all seasons. A later version can add it.
- A ranking of the contacts of a participant with the same reference. A later version can add it.
- A function to add series or certificate levels. A later version can add it.

## 3. Terms

"Ranking" is a technical name in this document.

| Term             | Definition                                                                                     |
| ---------------- | ---------------------------------------------------------------------------------------------- |
| Reference        | A place or a commemorative date that the group puts on the air. It stays the same in all seasons. |
| Series           | A group of references with the same prefix. The two series are DPS and EFE.                    |
| Reference code   | The series, a hyphen, and a number that starts at 01. Examples: DPS-01, EFE-12.                |
| Activity         | One operation of the group on the air for one reference, with a start date and an end date.    |
| Season           | The period in which the system counts points. The name of a season is its year, such as 2026.  |
| Call sign        | The official identification of an amateur radio station.                                       |
| Base call sign   | A call sign without the prefix or the suffix for portable operation. LU1ABC/P gives LU1ABC.    |
| Contact          | One radio contact (QSO) between an operator in an activity and a different station.            |
| First contact    | The earliest contact of a participant with a reference in a season.                            |
| Participant      | A station that has one or more contacts with an activity. A participant has no account.        |
| Log              | One ADIF file that an operator uploads for one activity.                                       |
| Point            | The unit that the system counts for each participant in each season.                           |
| Certificate      | A document that a participant gets at 5, 10 and 15 points in one season.                       |
| QSL card         | A document that confirms one contact of a participant with an operator.                       |
| Ranking          | The list of the participants of one season in the order of their points.                       |
| Visitor          | A person who uses the public pages. A visitor does not sign in.                                |
| Operator         | A user with an account who operates a station in activities and uploads logs.                  |
| Administrator    | A user with an account who controls references, activities, accounts and templates.            |

## 4. Users and permissions

An account has one of two roles: operator or administrator.
The call sign of the user identifies the account.

| Action                                   | Visitor | Operator      | Administrator |
| ---------------------------------------- | ------- | ------------- | ------------- |
| See the ranking and the activities       | Yes     | Yes           | Yes           |
| See the page of a participant            | Yes     | Yes           | Yes           |
| Download a QSL card or a certificate     | Yes     | Yes           | Yes           |
| Upload a log                             | No      | Yes           | Yes           |
| Delete a log                             | No      | Own logs only | All logs      |
| Control the references and activities    | No      | No            | Yes           |
| Control the accounts                     | No      | No            | Yes           |
| Control the QSL card templates           | No      | Own templates | Yes           |
| Control the certificate templates        | No      | No            | Yes           |
| See the record of actions                | No      | No            | Yes           |

## 5. Rules

### 5.1 Call signs

- **R-CALL-1** The system stores each call sign in uppercase letters.
- **R-CALL-2** The system calculates the base call sign of each contact.
- **R-CALL-3** The system uses the base call sign for the points, the certificates and the ranking.
- **R-CALL-4** The system keeps the call sign as the log gives it, for display on the QSL card.
- **R-CALL-5** LU1ABC, LU1ABC/P and LU1ABC/M are the same participant.

### 5.2 Seasons

- **R-SEA-1** Each activity belongs to one season and to one reference.
- **R-SEA-2** A season is a calendar year, from 1 January to 31 December.
- **R-SEA-2a** The season of an activity is the year of its start date.
- **R-SEA-3** The current season is the season of the current year.
- **R-SEA-4** The system counts the points of each season separately.
- **R-SEA-5** The system keeps the data of all seasons.
- **R-SEA-6** A reference can have an activity in each season. DPS-01 of 2026 and DPS-01 of 2027 are different activities.
- **R-SEA-7** A reference can have more than one activity in the same season.

### 5.3 Points

- **R-PTS-1** In each season, a participant gets one point for each reference where the participant has one or more contacts.
- **R-PTS-2** More contacts with the same reference in the same season do not give more points.
- **R-PTS-2a** Thus a second activity of a reference in the same season gives no second point.
- **R-PTS-3** DPS references and EFE references add to the same total.
- **R-PTS-4** The system calculates the points from the contacts that are in the database at that moment.
- **R-PTS-5** An operator gets points with the same rules as all other participants.
- **R-PTS-6** Each participant starts each season with zero points.

### 5.4 Operator of a contact

- **R-OPR-1** Each log belongs to one operator.
- **R-OPR-2** The operator of a contact is the operator of its log.
- **R-OPR-3** For each participant and each reference in a season, the system finds the first contact. This contact gives the point.
- **R-OPR-4** The activity of the first contact gives the date of a certificate (R-CER-5).
- **R-OPR-5** Later contacts with the same reference in the same season give no point. Each of them still gives a QSL card (FR-QSL-7).
- **R-OPR-5a** This includes contacts with the same operator, contacts with other operators and contacts in a second activity of the reference.
  For example, a participant that contacts LU2AOG and LU3EBQ in the same activity gets two QSL cards and one point.
- **R-OPR-6** The system calculates the first contact from the contacts that are in the database at that moment.

### 5.5 Certificates

- **R-CER-1** There are three certificate levels: 5 points, 10 points and 15 points.
- **R-CER-2** A participant has a certificate of a season when the points in that season are equal to or more than the level.
- **R-CER-3** A participant with 10 points has the certificates for 5 points and for 10 points.
- **R-CER-4** If the deletion of a log decreases the points below a level, that certificate is not available.
- **R-CER-5** The date of a certificate is the date of the activity that gave the last necessary point.
- **R-CER-6** A certificate of a season stays available after the end of that season.

## 6. Functions

### 6.1 Public pages

- **FR-PUB-1** The home page shows the ranking of the current season.
- **FR-PUB-1a** A visitor can select a different season to see its ranking.
- **FR-PUB-2** Each line of the ranking shows the position, the call sign, the points and the certificate levels.
- **FR-PUB-2a** Each reached level in the ranking is a link to download that certificate, if the level has a template for the season (FR-CER-7).
- **FR-PUB-3** Each line also shows the codes of the references where the participant has a contact, such as DPS-01 and EFE-03 (D-25).
- **FR-PUB-4** The ranking shows the participants in the order of their points, from high to low.
- **FR-PUB-4a** The ranking shows 25, 50 or 100 lines on each page. A visitor selects the number. The positions are those of the full ranking.
- **FR-PUB-5** Participants with equal points have the same position. The system shows them in alphabetical order.
- **FR-PUB-6** A visitor can search the ranking for a call sign.
  The search applies to the full ranking, not only to the page. The result starts on its first page.
- **FR-PUB-7** Each call sign in the ranking opens the page of that participant.
- **FR-PUB-8** The page of a participant shows each season separately, with the current season first.
- **FR-PUB-8a** For each season, the page shows the points of the participant.
- **FR-PUB-8b** For the current season, the page also shows the number of points necessary for the next level.
- **FR-PUB-8c** For each season, the page also shows the number of contacts (QSOs).
- **FR-PUB-9** For each season, the page lists all contacts of the participant, in the order of time.
- **FR-PUB-9a** Each line of that list shows the reference of the contact. A contact that gives no point (R-OPR-5) shows that.
- **FR-PUB-10** Each line shows the operator that the participant contacted.
- **FR-PUB-10a** Each line shows the date, the time, the frequency and the mode.
- **FR-PUB-11** Each line gives a link to download the QSL card of that contact (FR-QSL-7).
- **FR-PUB-12** For each season, the page gives a link to download each available certificate.
- **FR-PUB-12a** A reached certificate without a template for the season shows that it is not available (FR-CER-7).
- **FR-PUB-13** The activity list shows the activities of the selected season, with the most recent activity first.
- **FR-PUB-13a** For each activity, the list shows the reference code, the reference name, the dates and the hours, and the number of contacts.
- **FR-PUB-13b** For a visitor, the list shows only the activities with at least one contact. The next activities are on the home page and in the calendar of the program page.
  For a signed-in user, the list shows all activities of the season, with 0 contacts for an activity without logs. Thus an operator finds the activity to upload a log.
  The administration of the activities shows all activities.
- **FR-PUB-13c** The activity list shows 25, 50 or 100 activities on each page, as the ranking (FR-PUB-4a).
- **FR-PUB-14** The page of an activity shows its data, its operators and the list of all its contacts.
- **FR-PUB-14a** The page shows the number of participants (different base call signs) and the number of contacts (QSOs).
- **FR-PUB-15** The list has all contacts, in the order of time. A participant with more than one contact, with the same operator or with different operators, has a line for each contact.
- **FR-PUB-15a** Each line shows the date and the time, the call sign as the log gives it, the operator, the frequency, the mode, and a link to download the QSL card of the contact.
- **FR-PUB-15b** For a signed-in user, the logs and the QSL card templates of the activity come before the list of contacts. The list can be long.

### 6.2 Sign-in and accounts

- **FR-AUT-1** A user signs in with a call sign and a password.
- **FR-AUT-2** The system stores only a hash of each password.
- **FR-AUT-3** A user must change the initial password at the first sign-in.
- **FR-AUT-4** A user can change the password of the user's own account.
- **FR-AUT-5** After five incorrect passwords, the system locks the account for 15 minutes.
- **FR-AUT-6** The system ends a session after two hours without activity.
- **FR-USR-1** An administrator creates an account with a call sign, a name, a role and an initial password.
- **FR-USR-2** An administrator can add an email address to an account. The email address is optional.
- **FR-USR-3** An administrator can change the name, the role and the email address of an account.
- **FR-USR-4** An administrator can set a new initial password for an account.
- **FR-USR-5** An administrator can deactivate an account and activate it again.
- **FR-USR-6** A user with a deactivated account cannot sign in. The logs of that account stay in the system.
- **FR-USR-7** The system does not delete an account that has logs.
- **FR-USR-8** The system always keeps one or more active administrators.
- **FR-USR-9** The account list shows the number of contacts in the logs of each account.

### 6.3 References and activities

- **FR-REF-1** An administrator creates a reference with a series, a number and a name.
- **FR-REF-2** The reference data also includes an optional description.
- **FR-REF-3** The system proposes the next free number of the series. The administrator can change it.
- **FR-REF-4** The system refuses a reference code that exists.
- **FR-REF-5** The system shows the number with a minimum of two digits.
- **FR-REF-6** An administrator can change the data of a reference.
- **FR-REF-7** An administrator can delete a reference only when it has no activities.
- **FR-REF-8** The series are DPS and EFE.
- **FR-ACT-1** An administrator creates an activity with a reference, a start date and an end date.
- **FR-ACT-2** The activity data also includes an optional description.
- **FR-ACT-2a** The operators of an activity are the operators who have a log in that activity.
  The activity page shows each operator with the station call sign of the log (FR-LOG-7b), such as LU2AOG/A for a DPS activity and LU2AOG for an EFE activity.
- **FR-ACT-3** The dates of an activity are in UTC.
- **FR-ACT-3a** The system calculates the season from the start date and shows it.
- **FR-ACT-3b** An activity has a start time and an end time in UTC, as well as its dates. Each activity indicates its hours: in the parks they depend on the Puesto de Salud.
  An activity of more than one day starts at the start time of the start date and ends at the end time of the end date. The end is after the start.
  The pages show the hours in UTC and also in the time of Argentina (UTC−3).
  An activity that the system had before this rule shows that its hours are not confirmed, until an administrator sets them.
- **FR-ACT-4** An administrator can create more than one activity for the same reference in the same season.
- **FR-ACT-5** The system identifies an activity by its reference code and its start date, such as "DPS-01 (2026-05-10)".
- **FR-ACT-5a** The system refuses two activities with the same reference and the same start date.
- **FR-ACT-7** An administrator can change the data of an activity.
- **FR-ACT-8** An administrator can delete an activity only when it has no logs.

### 6.4 Logs

- **FR-LOG-1** An operator selects an activity and uploads one ADIF file (`.adi` or `.adif`).
- **FR-LOG-2** An operator can upload a log to all activities.
- **FR-LOG-3** When an operator uploads a log, the log belongs to that operator.
- **FR-LOG-4** When an administrator uploads a log, the administrator selects the operator of the log.
- **FR-LOG-5** An operator can have more than one log in an activity.
- **FR-LOG-6** The system reads these mandatory fields from each record: `CALL`, `QSO_DATE`, `TIME_ON`, `MODE`.
- **FR-LOG-7** Each record must also have a `FREQ` field or a `BAND` field.
- **FR-LOG-7a** The system shows a frequency in MHz with three decimals or more, such as `7.130 MHz`. A log often gives `7.13` for this frequency. More decimals stay if they are not zero, such as `7.1305 MHz`.
- **FR-LOG-7b** The system keeps the `STATION_CALLSIGN` field of each record: the call sign of the operator with its suffix, such as LU2AOG/A.
  An operator can use a different suffix in each activity: LU2AOG at home, LU2AOG/A in a park, LU2AOG/D in the province of Buenos Aires.
  The pages show this call sign as the operator of the contact. A record without this field shows the call sign of the account of the operator.
- **FR-LOG-8** The system reads these optional fields from each record: `NAME`, `RST_SENT`, `RST_RCVD`.
- **FR-LOG-9** A record is not valid when a mandatory field is absent or has an incorrect value.
- **FR-LOG-10** Before the system saves a log, it shows a summary to the user.
- **FR-LOG-11** The summary shows the number of valid records and each record that is not valid, with the cause.
- **FR-LOG-12** The summary shows a warning for each record with a date out of the activity dates.
- **FR-LOG-13** The summary shows a warning when `STATION_CALLSIGN` is not the call sign of the log operator.
- **FR-LOG-14** The user accepts or cancels the upload after the summary.
- **FR-LOG-15** When the user accepts, the system saves the valid records as contacts of the activity.
- **FR-LOG-16** The system refuses a file that has no valid records.
- **FR-LOG-17** The system keeps all valid records of each log. Equal records in different logs do not change the points.
- **FR-LOG-18** The system keeps the original file. The operator and the administrators can download it.
- **FR-LOG-19** The page of each activity shows its logs to the signed-in users.
- **FR-LOG-20** For each log, the page shows the file name, the operator, the upload date and the number of contacts.
- **FR-LOG-21** A signed-in user can see the contacts of a log.
- **FR-LOG-22** An operator can delete a log that belongs to the same operator.
- **FR-LOG-23** An administrator can delete all logs.
- **FR-LOG-24** The system asks for a confirmation before it deletes a log.
- **FR-LOG-25** When the system deletes a log, it deletes all contacts of that log.
- **FR-LOG-26** After an upload or a deletion, the public pages immediately show the new points and certificates.
- **FR-LOG-27** After an upload or a deletion, the public pages immediately show the new operator of each participant.

### 6.5 QSL cards

Each operator has a different QSL card design for each activity.
All QSL cards have the same fields.

- **FR-QSL-1** Each QSL card template is for one operator in one activity. The template is a JPEG or PNG image.
- **FR-QSL-2** An operator can upload the templates of the same operator. An administrator can upload all templates.
- **FR-QSL-3** The system writes the fields of the table below on the template.
- **FR-QSL-3a** The name on the QSL card comes from the official lists of licensees of Argentina (ENACOM) and Uruguay (URSEC), by the base call sign of the participant.
  The name is the full name of the list, with a capital letter at the start of each word, such as "Juana Isabel Ejemplo" for "JUANA ISABEL EJEMPLO".
  A participant of a different country, or a participant who is not on these lists, gets the QSL card with an empty name.
  The `NAME` field of the log is not on the public QSL card. The QSL card is public, and the name of the log comes from the operator, not from the participant.
- **FR-QSL-3b** An administrator updates each list with a button. The system downloads the list from the site of ENACOM or URSEC.
  If the download fails, or the list is much smaller than usual, the system keeps the old list.
  The system keeps only the call sign and the name of each licensee.
- **FR-QSL-4** The user who uploads a template sets the position, the size and the colour of each field.
- **FR-QSL-5** The system shows a sample QSL card before the user saves the template.
- **FR-QSL-6** The system makes the QSL card when a visitor downloads it.
- **FR-QSL-7** A participant gets one QSL card for each contact (D-27).
- **FR-QSL-7a** This includes the contacts that give no point: a second contact with the same operator, a contact with a different operator, and a contact in a second activity of the reference (R-OPR-5).
- **FR-QSL-8** The QSL card uses the data of its contact.
- **FR-QSL-9** The QSL card uses the template of the operator of the contact, for the activity of that contact.
- **FR-QSL-10** The system supplies the QSL card as a JPEG image.
- **FR-QSL-11** If that operator has no template for the activity, the page shows that the QSL card is not available.
- **FR-QSL-12** When a user uploads the image of a new template, the system tries to find the field boxes of the image.
  Most templates have a box of one colour for each field. If the system finds one box for each field, it puts the fields in these boxes.
- **FR-QSL-12a** The system cannot read the labels of the boxes. It gives the boxes to the fields in the usual order of a QSL card: date, call sign, name, frequency, time, mode and RST.
  It tells the user this order, and the user checks it.
- **FR-QSL-12b** The user can exchange the boxes of two fields with one action.
- **FR-QSL-12c** The user can start the search again, for example after a change of the image.
  The user can also choose the colour of the boxes with a click on one box of the image. Thus the search also finds boxes of a grey or a light colour.
- **FR-QSL-12d** If the system does not find one box for each field, it tells the user the number of boxes that it found. The user puts the fields in their place as in FR-QSL-4.
- **FR-QSL-12e** The result of the search is only a proposal. The user can change each field before the save operation (FR-QSL-4).

| Field on the QSL card | Source in the log                                      |
| --------------------- | ------------------------------------------------------ |
| Call sign             | `CALL`, as the log gives it                            |
| Name                  | The official registries of licensees (FR-QSL-3a).      |
| Date                  | `QSO_DATE`                                             |
| Time (UTC)            | `TIME_ON`                                              |
| Frequency             | `FREQ`. If it is absent, the system writes `BAND`.     |
| Mode                  | `MODE`                                                 |
| RST                   | `RST_SENT`                                             |

### 6.6 Certificates

- **FR-CER-1** An administrator uploads one certificate template for each level of each season.
- **FR-CER-2** The system writes the call sign and the certificate date on the template.
- **FR-CER-2a** The certificate has no serial number.
- **FR-CER-2b** The call sign on the certificate is the base call sign of the participant (R-CALL-3). The date is in Spanish words, such as "4 de octubre de 2026".
- **FR-CER-3** An administrator sets the position, the size and the colour of each field on the template.
- **FR-CER-4** The system makes the certificate when a visitor downloads it.
- **FR-CER-5** The system supplies the certificate as a PDF file.
- **FR-CER-6** The system refuses the download of a certificate that the participant does not have.
- **FR-CER-7** If a level has no template for the season, the page shows that the certificate is not available.

### 6.8 QSL mailer

The system sends the QSL cards and the certificates by e-mail, on request of an administrator.
Until 2026, the group sent them by hand. Thus an administrator can also mark them as sent.

- **FR-MAIL-1** Only an administrator sends messages and marks them. An administrator knows when all logs of an activity are in the system.
- **FR-MAIL-2** The system keeps the `EMAIL` field of each record of a log. The logs are the only automatic source of addresses: the system has no connection with QRZ.com.
- **FR-MAIL-3** An administrator keeps an address book for each base call sign: an own address, a mark "no messages", and notes.
  The book also has a name, for a later version. The QSL cards use only the name of the official lists (FR-QSL-3a, D-28).
- **FR-MAIL-4** The address of a call sign is the address of the book. Without it, the address of the most recent contact of the call sign in the logs.
  A log with a different address does not delete the old address. If the address is different from the address of the last message, the system shows it.
- **FR-MAIL-5** An administrator marks an address that bounced. The system does not use it, and uses the next address of FR-MAIL-4.
- **FR-MAIL-6** A participant gets one message for each activity, with the QSL cards of all its contacts in the activity (FR-QSL-7).
  A contact whose operator has no template for the activity has no QSL card (FR-QSL-11).
- **FR-MAIL-7** The system records each message: the call sign, the address, the subject, the QSL cards or the certificate, the time, the administrator, and the result.
  A message goes once: the record prevents a second message by mistake.
- **FR-MAIL-8** A QSL card of a log that came after the message goes in a new message, with only the new QSL cards. An administrator can also send all QSL cards again.
- **FR-MAIL-9** The system sends at most 90 messages each hour: the host accepts 100 for each mailbox. At the limit, the sending waits and continues by itself.
  The host has no background processes. Thus the page of the administrator stays open during the sending.
- **FR-MAIL-10** The subject and the body of the messages are plain text with variables in Spanish, such as `{saludo}` and `{referencia}`.
  There is a general text for the QSL cards and a general text for the certificates. Each activity, and each season of certificates, can have its own text.
  The system refuses an unknown variable.
- **FR-MAIL-11** Before the sending, the administrator sees each message with a real participant, and can send a test message to the own address.
  The sender is a mailbox of the group in dxfondito.com.ar. The answers go to that mailbox.
- **FR-MAIL-12** A participant gets one message for each certificate, with the PDF file.
- **FR-MAIL-13** A certificate goes again only after a warning: the system shows the date of the earlier message and the date of the certificate then and now. The administrator decides.
  The system shows the certificates that changed after their message (a log that came later, or a deleted log), and the certificates that the participant does not have now.
  After the sign-in, an administrator sees how many certificates wait for a message.
- **FR-MAIL-14** An administrator marks QSL cards or certificates as sent, without a message: for a participant, for a selection, or for all participants of an activity.
  A mark of a certificate keeps the date of the certificate. An administrator can delete a mark. A sent message cannot be deleted.

### 6.7 Record of actions

- **FR-AUD-1** The system records each upload and each deletion of a log.
- **FR-AUD-2** The system records each change to a reference, an activity, an account or a template.
- **FR-AUD-3** Each entry of the record has the user, the date, the time and the action.
- **FR-AUD-4** An administrator can see the record.

## 7. Constraints

- **C-1** The system operates on the DonWeb shared host of the group ("Emprendedor" plan).
- **C-2** The developer installs the files on the host through FTP.
- **C-3** The server software is PHP 8.5 (FPM) with a MySQL 5.7 database.
- **C-3a** The host has the PDO MySQL, GD, mbstring and fileinfo extensions. GD has FreeType, JPEG and PNG support.
- **C-4** The browser software is TypeScript. The developer compiles it before the installation.
- **C-5** The host runs only PHP requests. The system cannot use a server process that runs continuously.
- **C-6** The text of the user interface is in Spanish.
- **C-7** The system stores and shows all contact times in UTC.
- **C-8** The pages are easy to read on a telephone and on a computer.
- **C-9** The system stores no personal data of participants other than the data in the logs.
- **C-10** The design must let a later version add series and certificate levels.
- **C-11** The design must let a later version add a ranking that includes all seasons.
- **C-12** The design must let a later version count the contacts of a participant with the same reference.
- **C-13** The site uses the main domain at first. A later move to a subdomain must need no software change.

## 8. Decisions

The group made these decisions on 2026-10-01.

| Decision | Result                                                                                        |
| -------- | --------------------------------------------------------------------------------------------- |
| D-1      | One point for each reference with a contact in a season (R-PTS-1, R-PTS-2).                   |
| D-2      | One ranking and one set of certificates for the two series (R-PTS-3).                         |
| D-3      | The system writes the participant data on the QSL cards and on the certificates.              |
| D-4      | The public pages need no account. Only operators and administrators sign in.                  |
| D-5      | Call signs with a portable suffix count as the same participant (R-CALL-5). This closes Q1.   |
| D-6      | An operator can get points (R-PTS-5). This closes Q2.                                         |
| D-7      | A contact with a date out of the activity dates gives a warning only (FR-LOG-12). This closes Q3. |
| D-8      | The system shows the operator that each participant contacted (R-OPR-4).                      |
| D-9      | If a participant contacted more than one operator, the first contact applies. This closes Q4. |
| D-10     | Each operator has a different QSL card design for each activity (FR-QSL-1). This closes Q5.   |
| D-11     | The QSL card fields are date, call sign, name, frequency, time, mode and RST (FR-QSL-3).      |
| D-12     | The operator of a contact is the operator of its log (R-OPR-2). This closes Q11.              |
| D-13     | The name on the QSL card is the participant name from the `NAME` field. This closes Q12. Replaced by D-28. |
| D-14     | The operator or an administrator uploads the QSL card template (FR-QSL-2). This closes Q13.   |
| D-15     | A certificate shows the call sign and the date. It has no serial number. It is a PDF file. This closes Q6. |
| D-16     | An operator can upload a log to all activities (FR-LOG-2). This closes Q7.                    |
| D-17     | Version 1 has two series and three levels. A later version can add more (C-10). This closes Q8. |
| D-18     | The ranking starts again each season. The system keeps all seasons (R-SEA-4, R-SEA-5). This closes Q9. |
| D-19     | The host has PHP 8.5.10 with the necessary extensions (C-3, C-3a). This closes Q10.           |
| D-20     | A season is a calendar year (R-SEA-2). This closes Q14.                                       |
| D-21     | A reference is permanent. The group uses the same reference code again in each season (R-SEA-6). This closes Q15. |
| D-22     | The certificates start again each season. Each season has its own templates. This closes Q16. |
| D-23     | A reference can have a second activity in a season. It gives no second point (R-PTS-2a). This closes Q17. |
| D-24     | A participant gets one QSL card for each reference in a season, for the first contact (FR-QSL-7). This closes Q18. Replaced by D-27. |
| D-25     | Each line of the ranking shows the list of the references of the participant, not the number for each series. A column for each certificate level shows the reached levels (FR-PUB-2, FR-PUB-3). The model is the ranking of the events of Log de Argentina. Decision of 2026-10-02. |
| D-26     | The template editor finds the field boxes of the image (FR-QSL-12). The search is in the browser, before the save operation, and uses only the colours of the image. The fields go in the boxes in the usual order of a QSL card. Decision of 2026-10-02. |
| D-28     | The name on the public QSL card comes from the official lists of licensees of Argentina and Uruguay, not from the log (FR-QSL-3a). These lists are public sources. A participant of a different country gets no name. Decision of 2026-10-02. |
| D-29     | The QSL mailer sends on request of an administrator, through the SMTP server of the host, from one mailbox of the group. One message for each participant and activity. The logs give the addresses, and an address book corrects them. Decision of 2026-10-05. |
| D-27     | Each contact gives a QSL card, with the template of its operator for its activity. Only the first contact with a reference in a season gives a point (FR-QSL-7, R-OPR-5). This replaces D-9 for the QSL cards, and D-24. Decision of 2026-10-02. |

## 9. Open questions

There are no open questions at this time.
