# ALLVA Accounting — local setup

Getting the system running on your own machine, from the terminal.

---

## 1. What you need

| | Minimum | Check with |
|---|---|---|
| PHP | 8.3 | `php -v` |
| Composer | 2.x | `composer -V` |
| MySQL | 8.0 (8.4 LTS recommended) | `mysql --version` |
| Node.js | 20 | `node -v` |

PHP needs these extensions — all are standard, but `intl` and `bcmath` are
sometimes missing:

```bash
php -m | grep -E 'pdo_mysql|mbstring|intl|bcmath|openssl|tokenizer|xml|ctype|json|fileinfo|curl'
```

<details>
<summary><strong>Installing the prerequisites</strong></summary>

**macOS (Homebrew)**
```bash
brew install php@8.3 composer mysql node
brew services start mysql
```

**Ubuntu / Debian**
```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-mysql php8.3-mbstring php8.3-intl \
  php8.3-bcmath php8.3-xml php8.3-curl php8.3-zip unzip mysql-server nodejs npm
sudo systemctl start mysql
```

**Windows** — easiest is [Laragon](https://laragon.org/) or XAMPP, which bundle
PHP, MySQL and Composer. Run the commands below in the Laragon terminal.
</details>

---

## 2. Unzip and install dependencies

```bash
unzip allva-accounting.zip
cd allva-accounting

composer install
npm install && npm run build
```

`public/build` is already included in the zip, so **`npm` is optional** for a
first look — skip it if you just want to see the system running. You need it
once you start changing Blade files, because Tailwind only compiles the classes
it can actually see in your views.

---

## 3. Create the database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE allva_accounting      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE allva_accounting_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'allva'@'localhost' IDENTIFIED BY 'change-this-password';
GRANT ALL PRIVILEGES ON allva_accounting.*      TO 'allva'@'localhost';
GRANT ALL PRIVILEGES ON allva_accounting_test.* TO 'allva'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

The second database is only for the test suite. Skip it if you are not running
tests.

---

## 4. Configure

```bash
cp .env.example .env
php artisan key:generate
```

Then open `.env` and set these four lines to match what you just created:

```ini
DB_HOST=127.0.0.1
DB_DATABASE=allva_accounting
DB_USERNAME=allva
DB_PASSWORD=change-this-password
```

Confirm the connection works before going further:

```bash
php artisan db:show
```

If that errors, it is almost always the password, or MySQL listening on a socket
rather than `127.0.0.1`. On macOS Homebrew, try `DB_HOST=localhost`.

---

## 5. Install the system

```bash
php artisan allva:install --demo
```

This creates the schema, installs the 73-account chart of accounts from §6 of the
scope of work, sets up the fiscal calendar, the five partners and the 4,000 IQD
rate, then loads four months of worked example data so there is something to look
at.

Leave off `--demo` for a clean system with no transactions.

It finishes by printing the logins. **All of them use the password `password`** —
fine for testing, change them before any real data goes in.

| Login | Role |
|---|---|
| `accountant@allva.iq` | Posts entries, runs billing, all reports |
| `admin@allva.iq` | Users, rate table, settings — cannot post to the ledger |
| `operations@allva.iq` | Registry only, no financial access at all |
| `p1@allva.iq` … `p5@allva.iq` | Partners, read-only |

---

## 6. Run it

```bash
php artisan serve
```

Open **http://127.0.0.1:8000** and sign in as `accountant@allva.iq`.

While developing, run Vite in a second terminal so CSS rebuilds as you edit:

```bash
npm run dev
```

---

## 7. Check it works

```bash
php artisan test
```

80 tests, run against MySQL rather than SQLite so they exercise the real decimal
handling and locking the posting engine depends on. They use
`allva_accounting_test` and leave your main database alone.

---

## Worth trying first

A quick tour that exercises the parts that matter:

1. **Dashboard** — cash position, receivables ageing, and what needs attention.
2. **Invoices → Generate month** — pick a month and see the dry run: every bus
   company, its per-bus breakdown, and the reason any company is being skipped,
   all before anything is written.
3. **Open an invoice** and expand a line — the per-student detail showing days
   billed and why a part month was charged.
4. **Settings** — the three open decisions from §10 of the scope of work. Change
   the mid-month rule, regenerate a month, and watch the figures move. Invoices
   already issued keep the rule they were raised under.
5. **Receipts → Record receipt** — pick a bus company, enter part of what an
   invoice is for, apply it, then **Print receipt**: the amount in figures and
   in words, which invoices it settled, and what is still owed.
6. **Sign in as `operations@allva.iq`** — a different dashboard, with no money on
   it anywhere, and every financial URL refused.
7. **Reports → Balance sheet** — assets equal liabilities plus equity, to the
   dinar.

---

## The documents in this zip

| Where | What |
| --- | --- |
| `http://localhost:8000/handbook.html` | The user handbook — every screen and rule, in English, العربية and کوردی. Needs no login. |
| `docs/scope-of-work.html` | Scope of work, second edition. Open it in a browser. |
| `docs/eTrackify-Accounting-Scope-of-Work-v2.docx` | The same document in Word, for circulating and signing off. |
| `README.md` | How each section of the scope of work maps onto the code. |

---

## Troubleshooting

**`SQLSTATE[HY000] [2002] Connection refused`**
MySQL is not running, or `DB_HOST` is wrong. Try `DB_HOST=localhost` instead of
`127.0.0.1` (this forces a socket connection, which Homebrew MySQL prefers).

**`could not find driver`**
`pdo_mysql` is not enabled. On Ubuntu: `sudo apt install php8.3-mysql`. On
macOS it is bundled. Restart your terminal afterwards.

**Pages render without styling**
The compiled assets are missing. Run `npm install && npm run build`.

**`Please provide a valid cache path` or permission errors**
```bash
chmod -R 775 storage bootstrap/cache
```

**Wiping and starting over**
```bash
php artisan allva:install --fresh --demo
```
This drops every table first. It refuses to run without confirmation when
`APP_ENV=production`.

---

## Deploying on-prem later

When you move past testing, the essentials are:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build

# in .env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://accounting.yourdomain

php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Point your web server's document root at **`public/`**, never at the project
root — everything above `public/` including `.env` must not be web-reachable.

Set up a scheduler entry if you want month-end tasks to run unattended:

```
* * * * * cd /path/to/allva-accounting && php artisan schedule:run >> /dev/null 2>&1
```

And back up the database. This is a general ledger — it is the record.
