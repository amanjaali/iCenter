<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\JournalSource;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Department;
use App\Models\Journal;
use App\Models\Project;
use App\Services\Accounting\JournalService;
use App\Services\Accounting\PostingException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The manual journal screen. Automatic journals (invoices, payroll, and so on)
 * are shown here read-only — they belong to the document that produced them.
 */
class JournalController extends Controller
{
    public function __construct(private readonly JournalService $journals) {}

    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $journals = Journal::query()
            ->with(['period', 'createdBy'])
            ->withSum('lines', 'debit')
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('source')->toString(), fn ($q, $s) => $q->where('source_type', $s))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('journal_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('journal_date', '<=', $request->date('to')))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('reference', 'like', "%{$term}%")->orWhere('memo', 'like', "%{$term}%")
            ))
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('accounting.journals.index', [
            'journals' => $journals,
            'sources' => JournalSource::cases(),
        ]);
    }

    public function create()
    {
        $this->authorize('post-entries');

        return view('accounting.journals.form', $this->formData());
    }

    public function store(Request $request)
    {
        $this->authorize('post-entries');

        $data = $this->validated($request);

        try {
            $journal = $request->boolean('post_now')
                ? $this->journals->post(
                    Carbon::parse($data['journal_date']),
                    $data['memo'],
                    $this->lines($data),
                    JournalSource::Manual,
                )
                : $this->journals->createDraft(
                    Carbon::parse($data['journal_date']),
                    $data['memo'],
                    $this->lines($data),
                    JournalSource::Manual,
                );
        } catch (PostingException $e) {
            return back()->withInput()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('journals.show', $journal)->with(
            'success',
            $journal->isPosted()
                ? "Journal {$journal->reference} has been posted."
                : "Journal {$journal->reference} has been saved as a draft."
        );
    }

    public function show(Journal $journal)
    {
        $this->authorize('view-financials');

        $journal->load([
            'lines.account', 'lines.department', 'lines.project', 'lines.partner', 'lines.busCompany',
            'period', 'createdBy', 'postedBy', 'reversalOf', 'reversedBy',
        ]);

        return view('accounting.journals.show', [
            'journal' => $journal,
            'source' => $journal->sourceDocument(),
        ]);
    }

    public function post(Journal $journal)
    {
        $this->authorize('post-entries');

        try {
            $this->journals->postDraft($journal);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Journal {$journal->reference} has been posted.");
    }

    /** Scope of work §9.3 — corrections are reversals, and a reason is kept. */
    public function reverse(Request $request, Journal $journal)
    {
        $this->authorize('post-entries');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $reversal = $this->journals->reverse(
                $journal,
                $data['reason'],
                isset($data['date']) ? Carbon::parse($data['date']) : null,
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('journals.show', $reversal)
            ->with('success', "Journal {$journal->reference} has been reversed by {$reversal->reference}.");
    }

    public function destroy(Journal $journal)
    {
        $this->authorize('post-entries');

        try {
            $this->journals->deleteDraft($journal);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('journals.index')->with('success', 'The draft journal has been deleted.');
    }

    private function formData(): array
    {
        return [
            'accounts' => Account::postable()->orderBy('code')->get(),
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'projects' => Project::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'journal_date' => ['required', 'date'],
            'memo' => ['required', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'lines.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ], [], [
            'lines.*.account_id' => 'account',
        ]);
    }

    /** Drop blank rows the form always renders a few of. */
    private function lines(array $data): array
    {
        return collect($data['lines'])
            ->filter(fn ($line) => ! empty($line['account_id'])
                && ((float) ($line['debit'] ?? 0) > 0 || (float) ($line['credit'] ?? 0) > 0))
            ->map(fn ($line) => [
                'account' => (int) $line['account_id'],
                'description' => $line['description'] ?? null,
                'debit' => (float) ($line['debit'] ?? 0),
                'credit' => (float) ($line['credit'] ?? 0),
                'department_id' => $line['department_id'] ?: null,
                'project_id' => $line['project_id'] ?: null,
            ])
            ->values()
            ->all();
    }
}
