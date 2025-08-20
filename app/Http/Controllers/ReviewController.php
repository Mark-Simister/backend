<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Video;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    /**
     * USER: Submit or update own review for a video (always resets to pending).
     * Route model binding: /videos/{video}/reviews
     */
    public function store(Request $request)
{
    $data = $request->validate([
        'video_id' => ['required','exists:videos,id'],
        'rating'   => ['required','integer','between:1,5'],
        'review'   => ['required','string'],
        'status'   => ['nullable','in:pending,approved,rejected'],
    ]);

    Review::create([
        'video_id' => $data['video_id'],
        'user_id'  => $request->user()->id, // force logged-in user
        'rating'   => $data['rating'],
        'review'   => $data['review'],
        'status'   => $data['status'] ?? 'pending',
    ]);

    return redirect()->route('admin.reviews.index')->with('success', 'Review submitted and awaiting approval.');
}



    /**
     * PUBLIC: Approved reviews for a video (JSON paginator by default).
     * If you prefer a Blade view, replace the return with a view().
     */
    public function publicIndex(Video $video)
    {
        $reviews = $video->reviews()
            ->where('status','approved')
            ->with('user:id,name')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return $reviews;
        // Or: return view('reviews.public-index', compact('video','reviews'));
    }

    /**
     * ADMIN: List/filter reviews
     * GET /admin/reviews?status=pending|approved|rejected&video_id=&user_id=&q=
     */
    public function index(Request $request)
{
    // $status = $request->get('status', 'pending');
    $status = $request->get('status'); 

    $reviews = Review::query()
        ->with(['video:id,title', 'user:id,name,email'])
        ->when(in_array($status, ['pending','approved','rejected']), fn($q) => $q->where('status', $status))
        ->when($request->filled('video_id'), fn($q) => $q->where('video_id', $request->video_id))
        ->when($request->filled('user_id'),  fn($q) => $q->where('user_id',  $request->user_id))
        ->when($request->filled('q'), function ($q) use ($request) {
            $s = $request->q;
            $q->where('review', 'like', "%{$s}%");
        })
        ->latest()
        ->paginate(20)
        ->withQueryString();

    return view('admin.reviews.index', compact('reviews','status'));
}


    /**
     * ADMIN: Edit form
     */
    public function edit(Review $review)
    {
        $videos = Video::select('id','title')->orderBy('id','desc')->get();
        $users  = User::select('id','name','email')->orderBy('id','desc')->get();

        return view('admin.reviews.edit', compact('review', 'videos', 'users'));
    }

    /**
     * ADMIN: Update review (including reassignment and status)
     * Ensures unique (video_id, user_id) pair.
     */
    public function update(Request $request, Review $review)
    {
        $validated = $request->validate([
            'video_id' => [
                'required',
                'exists:videos,id',
                Rule::unique('reviews', 'video_id')
                    ->where(fn($q) => $q->where('user_id', $request->user_id))
                    ->ignore($review->id),
            ],
            'rating'   => ['required','integer','min:1','max:5'],
            'review'   => ['required','string','max:1000'],
            'status'   => ['required','in:pending,approved,rejected'],
        ]);

        DB::transaction(function () use ($review, $validated) {
            $review->update($validated);
            $this->syncVideoAggregates($review->video); // recompute from approved reviews
        });

        return redirect()->route('admin.reviews.index')->with('success', 'Review updated successfully.');
    }

    /**
     * ADMIN: Approve
     */
    public function approve(Review $review)
    {
        DB::transaction(function () use ($review) {
            $review->update(['status' => 'approved']);
            $this->syncVideoAggregates($review->video);
        });

        return back()->with('success', 'Review approved.');
    }

    /**
     * ADMIN: Reject
     */
    public function reject(Review $review)
    {
        DB::transaction(function () use ($review) {
            $review->update(['status' => 'rejected']);
            $this->syncVideoAggregates($review->video);
        });

        return back()->with('success', 'Review rejected.');
    }

    /**
     * ADMIN: Delete
     */
    public function destroy(Review $review)
    {
        DB::transaction(function () use ($review) {
            $video = $review->video; // keep reference before delete
            $review->delete();
            $this->syncVideoAggregates($video);
        });

        return back()->with('success', 'Review deleted.');
    }

    /**
     * Update videos.public_rating and videos.review_details
     * based on APPROVED reviews for the given video.
     * - public_rating: average rounded to 1 decimal (or null if none)
     * - review_details: {"count": X, "avg": Y, "stars": {"1": n1, ... "5": n5}}
     */
    private function syncVideoAggregates(Video $video): void
    {
        // Aggregate over approved reviews only
        $approved = Review::where('video_id', $video->id)
            ->where('status', 'approved');

        $summary = $approved->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')->first();

        $countsByStar = Review::selectRaw('rating, COUNT(*) as c')
            ->where('video_id', $video->id)
            ->where('status', 'approved')
            ->groupBy('rating')
            ->pluck('c', 'rating')
            ->all();

        $stars = [];
        for ($i = 1; $i <= 5; $i++) {
            $stars[(string)$i] = $countsByStar[$i] ?? 0;
        }

        $avg = ($summary && $summary->cnt) ? round((float)$summary->avg, 1) : null;

        $video->update([
            'public_rating'  => $avg,
            'review_details' => json_encode([
                'count' => (int) ($summary->cnt ?? 0),
                'avg'   => $avg,
                'stars' => $stars,
            ]),
        ]);
    }

    // admin Create Reviews
    public function create()
    {
        $videos = Video::select('id','title')->orderBy('id','desc')->get();
        $users  = User::select('id','name','email')->orderBy('id','desc')->get();

        return view('admin.reviews.create', compact('videos','users'));
    }

    /**
     * Store a newly created review from admin panel
     */
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'video_id' => 'required|exists:videos,id',
            'user_id'  => 'required|exists:users,id',
            'rating'   => 'required|integer|min:1|max:5',
            'review'   => 'required|string|max:1000',
            'status'   => 'required|in:pending,approved,rejected',
        ]);

        Review::create($validated);

        return redirect()->route('admin.reviews.index')
                         ->with('success','Review created successfully.');
    }
}
