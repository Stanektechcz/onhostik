<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerSegmentTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerSegmentAnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $tags = CustomerSegmentTag::orderBy('name')->get();

        $pivotCounts = DB::table('customer_segment_tag_pivot')
            ->select('segment_tag_id', DB::raw('count(*) as customer_count'))
            ->groupBy('segment_tag_id')
            ->get()
            ->keyBy('segment_tag_id');

        return view('admin.customer-segment-analytics.index', compact('tags', 'pivotCounts'));
    }
}
