<div align="center">
  <img src="public/brand/logo/allva-accounting-primary.svg" alt="ALLVA Accounting" height="52">
  <p><strong>eTrackify accounting and financial management system</strong></p>
</div>

---

A complete general ledger system for ALLVA Company and its eTrackify school bus
tracking project, built to the *eTrackify Accounting System — Scope of Work*,
version 1.0.

The objective the scope of work sets is that **no item of income or expense can
be lost, misrecorded, or left untracked**, and that all five partners have full
visibility of the company's financial activity. Everything below serves that.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | MySQL 8.4 LTS or later |
| Front end | Blade + Livewire 3 + Alpine, Tailwind CSS 4, built with Vite |
| Design | ALLVA brand kit — tokens, logo lockups and icons in `public/brand/` |

Livewire and Blade were chosen over a separate SPA deliberately: this is a dense,
form-and-table accounting application where server-rendered pages keep the money
logic on the server, where it can be tested, rather than duplicating it in a
client that could disagree with the ledger.

## Installation

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate

# set DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env, then:
php artisan allva:install            # schema, chart of accounts, core setup
php artisan allva:install --demo     # …and four months of worked example data

php artisan serve
```

`allva:install --fresh` drops and recreates every table first. It refuses to do
so in production without confirmation.

The installer prints one login per role. **Every one uses the password
`password` — change them all before the system holds real accounting data.**

During development run `npm run dev` alongside `php artisan serve`; Tailwind
compiles only the classes it can see in the Blade files, so a production build
must be re-run after any view changes.

## The handbook

`public/handbook.html` is the user handbook — every screen, every rule the
ledger enforces, the month-end routine, and a glossary for the partners who
read the figures without being accountants. It is a single self-contained
page in three complete editions, switched from the sidebar:

| Edition | Script | Direction |
| --- | --- | --- |
| English | Latin | LTR |
| العربية | Arabic | RTL |
| کوردی (سۆرانی) | Arabic | RTL |

Serve the app and open <http://localhost:8000/handbook.html>; it needs no
routing, no authentication and no build step. The chosen language and theme
are remembered per reader in `localStorage`.

## How the scope of work maps onto the system

### §2 Company structure — `Partner`, `DistributionService`

Five partners at 20% each. The percentage is a column, never a constant: a change
in shareholding goes through `DistributionService::changeOwnership()`, which
requires a reason and writes an `ownership_change_logs` row. A distribution that
has already been posted keeps the percentage in force when it was declared.

### §3 The operational registry — `BusCompany` → `Bus` → `Student`

The chain that feeds billing. The key table is `student_enrollments`: billing
counts days from enrollment records, not from `students.status`, which is what
makes a mid-month join, transfer or exit billable and a past invoice
reproducible. Changing a student's bus closes the open enrollment and opens a new
one rather than editing history.

### §4 Revenue model — `RateResolver`, `BillingService`, `InvoiceService`

The rate lives in an effective-dated `rate_cards` table and is read only through
`RateResolver`. A company-specific rate beats the general one; a historical month
always resolves the rate that applied at the time, so a future price rise never
restates a past invoice. Every rate change is logged with the user, the date and
a mandatory reason (§4.2).

Invoices are generated from the registry, never typed: bus company → buses →
students present in the month → days → amount. The generation screen previews
every company, and the reason any company is being skipped, before writing
anything.

Revenue recognition follows §4.4: a month already served is earned
(`DR 1030 / CR 4010`); a month billed in advance is deferred
(`DR 1030 / CR 2060`) and released to 4010 when the month arrives.

### §5 Revenue split and distributions — `RevenueShareService`, `DistributionService`

Cyber Gate's share posts `DR 5010 / CR 2040`; partner distributions post
`DR 3060 / CR 2050`. Partner shares are allocated by largest remainder so five
20% shares of an odd amount still total the amount declared, to the dinar.

Distributions are booked to **3060 partner current accounts and drawings**, never
against a partner's 3010–3050 capital account: capital is what a partner put in,
and a profit distribution must not read as a reduction of it.

### §6 Chart of accounts — `ChartOfAccountsSeeder`

All 73 accounts, reproduced exactly, with the class numbering fixed. Accounts the
posting engine resolves by code (1030, 2060, 4010, 5010 and so on) are marked
`is_system` and cannot be renumbered — the seeder fails loudly if any code the
engine needs is missing.

### §7 Expense classification — `JournalService`, `ExpenseService`

Every expense carries an account, a cost centre and a project. This is enforced
in the ledger, not merely requested by the form: an expense account is flagged
`requires_department` / `requires_project`, and `JournalService` refuses the
entry without them. Fixed/variable and capex/opex are carried onto the journal
line so the reports do not have to re-derive them.

Overhead that belongs to no single project is booked to the overhead pool and
spread across projects **at reporting time** by the configured method — the
ledger keeps the cost where it was incurred.

### §8 Reporting — `app/Services/Reports/`

All seventeen required reports, sharing one `ReportFilters` object so every one
filters by date range, project and department and exports to CSV identically.
Reports read **only posted journals**; drafts and reversed entries are invisible
to reporting by construction.

### §9 Access and permissions — `AuthServiceProvider`

| Role | May do |
|---|---|
| Accountant | Create, edit and post entries; run all reports |
| Operations | Register bus companies, buses and students only — **no financial access at all**, including a separate dashboard with no money on it |
| Partner | Read-only across every financial screen and report |
| Administrator | Users, rate table, settings — but **not** posting to the ledger |

Abilities are derived from the role in one place, so "what may a partner do" has
a single answer. An administrator is deliberately not an accountant: separating
configuring the system from posting to it is the point of having both roles.

The audit trail (§9.3) is append-only — the application never updates or deletes
an audit row. Nothing posted is ever deleted; corrections are reversals, which
create a mirror journal and leave both on the record.

### §10 The three open decisions

The scope of work leaves three decisions open, each of which changes the
accounting logic. All three are implemented as **settings**, not constants, so
whichever way the client decides the system does not need rebuilding — and each
is stamped onto the documents it affects, so changing the decision later never
restates a period already posted.

| | Decision | Where | Default |
|---|---|---|---|
| §10.1 | Cyber Gate takes 50% of gross revenue, or 50% of net profit after expenses | Settings → Revenue share basis | Gross (as the document assumes) |
| §10.2 | Mid-month joins and exits: pro-rate by day, full month, or half month | Settings → Mid-month rule | Pro-rate by day |
| §10.3 | Billing pauses over holidays and the summer, or runs all twelve months | Academic years, **per year** | Continue |

These defaults are assumptions, not answers. They are the first thing to confirm
with the client, and each can be changed in the interface without a code change.

## Tests

```bash
php artisan test
```

72 tests run against MySQL — the database this is deployed on — rather than an
in-memory SQLite, so they exercise the real decimal handling, unique indexes and
row locking the posting engine depends on. Create the test database once:

```sql
CREATE DATABASE allva_accounting_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

The suite covers the money arithmetic (shares always total the amount declared),
all three proration rules, the posting invariants (balance, closed periods, the
§7 tags, reversal instead of deletion), billing generated from the registry,
both §10.1 revenue-share treatments, the distribution maths, the §9 permission
model, and the main workflows through the HTTP layer.

## Layout

```
app/
  Enums/          Domain vocabulary — roles, statuses, the §10 decisions
  Models/         Eloquent models; Auditable writes the §9.3 trail
  Services/
    Accounting/   The posting engine: JournalService, LedgerService, PeriodService
    Billing/      RateResolver, BillingService, InvoiceService, PaymentService
    Partners/     RevenueShareService, DistributionService
    Expenses/     ExpenseService  ·  Payroll/  ·  Assets/
    Reports/      The seventeen reports of §8
  Support/Money   Rounding and largest-remainder allocation
public/brand/     The supplied ALLVA brand kit, unmodified
```

The money logic lives in the service layer, not in controllers or models:
controllers validate and delegate, and every automatic posting goes through
`JournalService`, so the ledger's rules are enforced in one place.
