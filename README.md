# dxfondito-website

Website of the DX Fondito group for the "Diploma Puestos de Salud" programme.
The site shows a ranking of the participants and supplies QSL cards and certificates.

- [Functional specification](docs/spec/functional-specification.md)
- [System design](docs/design/system-design.md)

## Structure

| Folder               | Content                                               |
| -------------------- | ----------------------------------------------------- |
| `web/`               | Browser program: TypeScript, React, Vite, pnpm.       |
| `api/`               | API: PHP 8.5, MySQL 5.7.                              |
| `api/migrations/`    | Numbered SQL files that change the database.          |
| `docker/`            | Image of the PHP container for the local environment. |
| `tools/`             | Scripts for the host check, the build and the installation. |
| `.github/workflows/` | Tests and installation on the host.                   |

## Local environment

Necessary tools: Docker, Node.js 24 and pnpm.

Do these steps one time:

```sh
cp api/config.example.php api/config.php
docker compose run --rm --no-deps api composer install
(cd web && pnpm install)
```

Start the environment:

```sh
docker compose up -d                              # API on port 8080, MySQL 5.7 on port 3307
docker compose exec api php bin/migrate.php       # Apply the pending migrations
(cd web && pnpm dev)                              # Browser program on http://localhost:5173
```

The browser program sends each `api/` request to the PHP container.

## Tests

```sh
docker compose run --rm --no-deps api vendor/bin/phpunit
(cd web && pnpm lint && pnpm test)
```

## Migrations

- Each migration is one SQL file in `api/migrations/`, with a number at the start of its name.
- Each statement ends with a semicolon at the end of a line.
- All statements must operate on MySQL 5.7.
- Do not change a migration after its installation on the host. Add a new file.

MySQL cannot undo a `CREATE TABLE` or an `ALTER TABLE` statement.
If a migration fails, the message gives the number of the statement that failed.
Correct the database manually before you apply the migration again.

## Installation on the host

The GitHub workflow installs each push to `main` on the host through FTP.
The workflow uploads files only when the `DEPLOY_ENABLED` variable is `true`.

### First installation

1. Make a database and a database user in the control panel of the host.
2. Add the secrets and the variables of the table below to the GitHub repository.
3. Set the `DEPLOY_ENABLED` variable to `true` and push a commit to `main`.
4. Through FTP, copy `dxfondito-app/config.example.php` to `dxfondito-app/config.php` on the host.
5. In `config.php`, replace the database values and set a long random value for `migration_secret`.
6. Open `https://<domain>/api/index.php?r=/migrate` and enter the migration secret.
7. While there are no accounts, the page then shows a form for the first administrator.
   Enter the migration secret again, the call sign, the name and the password of the administrator.
   The form is not available after the first account exists.
8. Open `https://<domain>/#/estado`. The page shows the status of the API and of the database.

CAUTION: The workflow replaces `index.html` in the public folder.
If the public folder has a different site, set `FTP_PUBLIC_DIR` to a subfolder before step 3.

### Secrets and variables

| Name               | Type     | Value                                                                       |
| ------------------ | -------- | --------------------------------------------------------------------------- |
| `FTP_SERVER`       | Secret   | Name of the FTP server, without `ftp://`. Example: `c1234567.ferozo.com`.   |
| `FTP_USERNAME`     | Secret   | FTP user name.                                                              |
| `FTP_PASSWORD`     | Secret   | FTP password.                                                               |
| `DEPLOY_ENABLED`   | Variable | `true` starts the installation after each push to `main`.                   |
| `FTP_PROTOCOL`     | Variable | `ftps` (default) or `ftp`.                                                  |
| `FTP_PUBLIC_DIR`   | Variable | Public folder on the host. Default: `public_html/`.                         |
| `FTP_PRIVATE_DIR`  | Variable | Private folder on the host. Default: `dxfondito-app/`.                       |
| `PRIVATE_PATH`     | Variable | Path from the `api/` folder on the host to the private folder. Default: `../../dxfondito-app`. |

If the public folder is a subfolder, such as `public_html/dps/`, set `PRIVATE_PATH` to `../../../dxfondito-app`.

### After each installation

If the push has new files in `api/migrations/`, open the migration page and enter the migration secret.

### Installation without the workflow

```sh
tools/build.sh
FTP_SERVER=... FTP_USERNAME=... FTP_PASSWORD=... tools/deploy-ftp.sh
```

`tools/deploy-ftp.sh` needs `lftp`.
