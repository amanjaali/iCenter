<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Scope of work §9 — access and permissions.
 *
 * These are the rules the specification is most explicit about: partners see
 * everything and post nothing; operations register the registry and have no
 * access to financial entries at all.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installChartOfAccounts();
    }

    private function userWith(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            'partner_id' => $role === UserRole::Partner ? Partner::first()?->id : null,
        ]);
    }

    #[Test]
    public function a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/invoices')->assertRedirect('/login');
    }

    #[Test]
    public function a_partner_can_read_every_financial_screen(): void
    {
        // §9.1 — "A partner can view all accounting activity — every income
        // entry, every expense entry, and all profit calculations."
        $partner = $this->userWith(UserRole::Partner);

        foreach ([
            '/dashboard', '/invoices', '/payments', '/journals', '/accounts', '/expenses',
            '/payroll', '/partners', '/distributions', '/revenue-share', '/assets',
            '/reports', '/reports/income-statement', '/reports/balance-sheet',
            '/reports/partner-distribution', '/reports/receivables-ageing', '/audit',
        ] as $url) {
            $this->actingAs($partner)->get($url)->assertOk("A partner should be able to open {$url}");
        }
    }

    #[Test]
    public function a_partner_cannot_post_anything(): void
    {
        // §9.1 — "Partner access is read-only. Partners view the accounts;
        // they do not post entries."
        $partner = $this->userWith(UserRole::Partner);

        foreach ([
            '/journals/create', '/invoices/generate', '/expenses/create',
            '/payments/create', '/students/create', '/bus-companies/create',
        ] as $url) {
            $this->actingAs($partner)->get($url)->assertForbidden("A partner should not reach {$url}");
        }
    }

    #[Test]
    public function operations_has_no_access_to_financial_data_at_all(): void
    {
        // §9.2 — "Register bus companies, buses, and students only; no access
        // to financial entries."
        $operations = $this->userWith(UserRole::Operations);

        foreach ([
            '/invoices', '/payments', '/journals', '/accounts', '/expenses', '/payroll',
            '/partners', '/distributions', '/revenue-share', '/assets', '/periods',
            '/reports', '/reports/income-statement', '/audit', '/settings', '/users',
        ] as $url) {
            $this->actingAs($operations)->get($url)->assertForbidden("Operations should not reach {$url}");
        }
    }

    #[Test]
    public function operations_can_work_the_registry(): void
    {
        $operations = $this->userWith(UserRole::Operations);

        foreach ([
            '/dashboard', '/bus-companies', '/bus-companies/create',
            '/buses', '/buses/create', '/students', '/students/create',
        ] as $url) {
            $this->actingAs($operations)->get($url)->assertOk("Operations should be able to open {$url}");
        }
    }

    #[Test]
    public function the_operations_dashboard_shows_no_money(): void
    {
        $operations = $this->userWith(UserRole::Operations);

        $response = $this->actingAs($operations)->get('/dashboard');

        $response->assertOk();
        $response->assertDontSee('Net profit', false);
        $response->assertDontSee('Cash and bank', false);
        $response->assertDontSee('Outstanding receivables', false);
        $response->assertSee('Active students', false);
    }

    #[Test]
    public function only_the_accountant_posts_entries(): void
    {
        // §9.2 — the accountant has full rights to create, edit and post.
        $accountant = $this->userWith(UserRole::Accountant);

        foreach (['/journals/create', '/invoices/generate', '/expenses/create', '/payments/create'] as $url) {
            $this->actingAs($accountant)->get($url)->assertOk("An accountant should reach {$url}");
        }

        // An administrator configures the system but does not post to the
        // ledger — the separation is the point of having both roles.
        $administrator = $this->userWith(UserRole::Administrator);

        $this->actingAs($administrator)->get('/journals/create')->assertForbidden();
        $this->actingAs($administrator)->get('/settings')->assertOk();
        $this->actingAs($administrator)->get('/users')->assertOk();
        $this->actingAs($administrator)->get('/rates/create')->assertOk();
    }

    #[Test]
    public function only_an_administrator_manages_the_rate_table_and_users(): void
    {
        // §9.2 — "Administrator: user management, rate table configuration,
        // system settings."
        $accountant = $this->userWith(UserRole::Accountant);

        $this->actingAs($accountant)->get('/rates')->assertOk();
        $this->actingAs($accountant)->get('/rates/create')->assertForbidden();
        $this->actingAs($accountant)->get('/users')->assertForbidden();
        $this->actingAs($accountant)->get('/settings')->assertForbidden();
    }

    #[Test]
    public function a_deactivated_account_is_turned_away(): void
    {
        $user = $this->userWith(UserRole::Accountant);
        $user->update(['is_active' => false]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    }

    #[Test]
    public function signing_in_is_recorded_in_the_audit_trail(): void
    {
        $user = $this->userWith(UserRole::Accountant);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('audit_logs', ['event' => 'signed_in', 'user_id' => $user->id]);
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $user = $this->userWith(UserRole::Accountant);

        $this->post('/login', ['email' => $user->email, 'password' => 'not-the-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
