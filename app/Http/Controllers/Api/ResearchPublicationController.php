<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResearchPublication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResearchPublicationController extends Controller
{
    /** SQL expression for calendar year of published_at (cross-DB). */
    private function yearOfPublishedAt(): string
    {
        $c = 'research_publications.published_at';

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "cast(strftime('%Y', {$c}) as integer)",
            'pgsql' => "extract(year from {$c})::int",
            default => "YEAR({$c})",
        };
    }

    private function normalizeYearFilter(Request $request): ?int
    {
        if (! $request->filled('year')) {
            return null;
        }
        $y = (int) $request->query('year');
        $max = (int) date('Y') + 1;

        return ($y >= 1900 && $y <= $max) ? $y : null;
    }

    public function stats(Request $request): JsonResponse
    {
        $yearExpr = $this->yearOfPublishedAt();

        $byYearRows = ResearchPublication::query()
            ->published()
            ->selectRaw("{$yearExpr} as pub_year, research_publications.output_type as output_type, COUNT(*) as c")
            ->groupByRaw("{$yearExpr}, research_publications.output_type")
            ->orderByRaw("{$yearExpr}")
            ->get();

        $byYear = [];
        foreach ($byYearRows as $row) {
            $y = (string) (int) $row->pub_year;
            if (! isset($byYear[$y])) {
                $byYear[$y] = ['publication' => 0, 'research' => 0, 'total' => 0];
            }
            $count = (int) $row->c;
            if ($row->output_type === ResearchPublication::OUTPUT_PUBLICATION) {
                $byYear[$y]['publication'] = $count;
            } elseif ($row->output_type === ResearchPublication::OUTPUT_RESEARCH) {
                $byYear[$y]['research'] = $count;
            }
        }
        foreach (array_keys($byYear) as $y) {
            $byYear[$y]['total'] = $byYear[$y]['publication'] + $byYear[$y]['research'];
        }

        $years = array_keys($byYear);
        rsort($years, SORT_NUMERIC);

        $selectedYear = $this->normalizeYearFilter($request);

        $query = ResearchPublication::query()->published();
        if ($selectedYear !== null) {
            $query->whereYear('published_at', $selectedYear);
        }

        $rows = $query
            ->select([
                'academic_program_id',
                'output_type',
                DB::raw('COUNT(*) as c'),
            ])
            ->groupBy('academic_program_id', 'output_type')
            ->get();

        $byProgram = [];
        $totals = ['publication' => 0, 'research' => 0];

        foreach ($rows as $row) {
            $pid = $row->academic_program_id;
            $type = $row->output_type;
            $count = (int) $row->c;
            if (! isset($byProgram[$pid])) {
                $byProgram[$pid] = ['publication' => 0, 'research' => 0];
            }
            if ($type === ResearchPublication::OUTPUT_PUBLICATION) {
                $byProgram[$pid]['publication'] = $count;
                $totals['publication'] += $count;
            } elseif ($type === ResearchPublication::OUTPUT_RESEARCH) {
                $byProgram[$pid]['research'] = $count;
                $totals['research'] += $count;
            }
        }

        return response()->json([
            'by_year' => $byYear,
            'years' => $years,
            'by_program' => $byProgram,
            'totals' => $totals,
            'total' => $totals['publication'] + $totals['research'],
            'selected_year' => $selectedYear,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ResearchPublication::query()->published()->orderByDesc('published_at');

        $year = $this->normalizeYearFilter($request);
        if ($year !== null) {
            $query->whereYear('published_at', $year);
        }

        if ($request->filled('program')) {
            $query->where('academic_program_id', $request->query('program'));
        }
        if ($request->filled('output_type')) {
            $t = $request->query('output_type');
            if (in_array($t, [ResearchPublication::OUTPUT_PUBLICATION, ResearchPublication::OUTPUT_RESEARCH], true)) {
                $query->where('output_type', $t);
            }
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            if (strlen($q) > 120) {
                $q = substr($q, 0, 120);
            }
            if ($q !== '') {
                $needle = '%'.addcslashes($q, '%_\\').'%';
                $query->where(function (Builder $sub) use ($needle): void {
                    $sub->where('title', 'like', $needle)
                        ->orWhere('excerpt', 'like', $needle)
                        ->orWhere('authors', 'like', $needle)
                        ->orWhere('venue_or_journal', 'like', $needle);
                });
            }
        }

        $sdg = $this->normalizeSdgFilter($request);
        if ($sdg !== null) {
            $query->whereJsonContains('sdg_goals', $sdg);
        }

        $perPage = min((int) $request->query('per_page', 12), 50);

        return response()->json($query->paginate($perPage));
    }

    public function show(string $slug): JsonResponse
    {
        $item = ResearchPublication::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json($item);
    }

    private function normalizeSdgFilter(Request $request): ?int
    {
        if (! $request->filled('sdg')) {
            return null;
        }

        $sdg = (int) $request->query('sdg');

        return ($sdg >= 1 && $sdg <= 17) ? $sdg : null;
    }
}
