<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgramPage;
use App\Support\ProgramOfferingMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramPageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProgramPage::query()->published()->orderByDesc('published_at');

        if ($request->filled('program')) {
            $keys = ProgramOfferingMap::filterKeys((string) $request->query('program'));
            if ($keys !== []) {
                $query->whereIn('academic_program_id', $keys);
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
                        ->orWhere('excerpt', 'like', $needle);
                });
            }
        }

        $perPage = min((int) $request->query('per_page', 12), 50);

        return response()->json($query->paginate($perPage));
    }

    public function show(string $slug): JsonResponse
    {
        $query = ProgramPage::query()->published();
        $item = (clone $query)->where('slug', $slug)->first();

        if (! $item && ProgramOfferingMap::isKnown($slug)) {
            $item = $query
                ->whereIn('academic_program_id', ProgramOfferingMap::filterKeys($slug))
                ->orderByDesc('updated_at')
                ->first();
        }

        abort_if(! $item, JsonResponse::HTTP_NOT_FOUND);

        return response()->json($item);
    }
}
