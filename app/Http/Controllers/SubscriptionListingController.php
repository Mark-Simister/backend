<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionListing;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use App\Models\Region;
use App\Models\SubscriptionRegion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SubscriptionListingController extends Controller
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth'),

            // web CRUD
            new Middleware('permission:subscription_list.view', only: ['index']),
            new Middleware('permission:subscription_list.create', only: ['create', 'store']),
            new Middleware('permission:subscription_list.edit', only: ['edit', 'update']),
            new Middleware('permission:subscription_list.delete', only: ['destroy']),

        ];
    }
    //     private function getRealtimeRate(string $from, string $to): float
// {
//     $from = strtoupper($from);
//     $to   = strtoupper($to);

    //     if ($from === $to) {
//         return 1.0;
//     }

    //     // Prefer config('services.exchangerate.key') if you added it; fallback to env()
//     $apiKey = config('services.exchangerate.key') ?? env('EXCHANGE_RATE_API_KEY');
//     if (!$apiKey) {
//         throw new \RuntimeException('Missing EXCHANGE_RATE_API_KEY');
//     }

    //     $cacheKey = "fx_{$from}_{$to}";
//     return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($from, $to, $apiKey) {
//         $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$from}/{$to}";
//         $resp = Http::timeout(8)->get($url);

    //         if (!$resp->successful()) {
//             throw new \RuntimeException('FX API error: HTTP '.$resp->status());
//         }

    //         $json = $resp->json();
//         if (
//             !isset($json['result']) || $json['result'] !== 'success' ||
//             !isset($json['conversion_rate']) || !is_numeric($json['conversion_rate'])
//         ) {
//             throw new \RuntimeException('FX API returned no rate for '.$from.'->'.$to);
//         }

    //         return (float) $json['conversion_rate'];
//     });
// }

    private function getRealtimeRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        $cacheKey = "fx_{$from}_{$to}";
        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($from, $to) {
            // 1) Yahoo Finance (unofficial, near-realtime). Often 401s without headers.
            $rate = $this->fetchFromYahoo($from, $to);
            if ($rate !== null && $rate > 0) {
                return $rate;
            }

            // 2) exchangerate.host (free, no key, unlimited; ECB-backed, ~daily)
            $rate = $this->fetchFromExchangerateHost($from, $to);
            if ($rate !== null && $rate > 0) {
                return $rate;
            }

            // 3) ECB XML via EUR cross (daily). Free + unlimited.
            $rate = $this->fetchFromEcb($from, $to);
            if ($rate !== null && $rate > 0) {
                return $rate;
            }

            throw new \RuntimeException("Could not fetch FX rate for {$from}->{$to} from any free source.");
        });
    }

    private function fetchFromYahoo(string $from, string $to): ?float
    {
        try {
            $pair = $from . $to . '=X';
            $url = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$pair}";

            $resp = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'application/json, text/plain, */*',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Connection' => 'keep-alive',
                ])
                ->get($url);

            if ($resp->status() === 401 || !$resp->successful()) {
                $url2 = "https://query2.finance.yahoo.com/v7/finance/quote?symbols={$pair}";
                $resp = Http::timeout(8)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                        'Accept' => 'application/json, text/plain, */*',
                        'Accept-Language' => 'en-US,en;q=0.9',
                        'Connection' => 'keep-alive',
                    ])
                    ->get($url2);
            }

            if (!$resp->successful()) {
                return null;
            }

            $json = $resp->json();
            $price = $json['quoteResponse']['result'][0]['regularMarketPrice'] ?? null;

            if (is_numeric($price) && $price > 0) {
                return (float) $price;
            }

            // If direct pair missing, try reverse and invert.
            $revPair = $to . $from . '=X';
            $revUrl = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$revPair}";
            $revResp = Http::timeout(8)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Language' => 'en-US,en;q=0.9',
                'Connection' => 'keep-alive',
            ])->get($revUrl);

            if (!$revResp->successful()) {
                return null;
            }
            $revJson = $revResp->json();
            $revPrice = $revJson['quoteResponse']['result'][0]['regularMarketPrice'] ?? null;
            if (is_numeric($revPrice) && $revPrice > 0) {
                return 1.0 / (float) $revPrice;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchFromExchangerateHost(string $from, string $to): ?float
    {
        try {
            $url = "https://api.exchangerate.host/convert?from={$from}&to={$to}&amount=1";
            $resp = Http::timeout(8)->get($url);
            if (!$resp->successful()) {
                return null;
            }
            $json = $resp->json();
            $rate = $json['result'] ?? null; // numeric
            return (is_numeric($rate) && $rate > 0) ? (float) $rate : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function fetchFromEcb(string $from, string $to): ?float
    {
        try {
            $resp = Http::timeout(8)->get('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
            if (!$resp->successful()) {
                return null;
            }

            $xml = simplexml_load_string($resp->body());
            if (!$xml)
                return null;

            $ns = $xml->getNamespaces(true);
            $cube = $xml->xpath('//gesmes:Envelope/*[local-name()="Cube"]/*[local-name()="Cube"]/*[local-name()="Cube"]');
            $eurMap = ['EUR' => 1.0];
            foreach ($cube as $c) {
                $attr = $c->attributes();
                if (isset($attr['currency'], $attr['rate'])) {
                    $eurMap[(string) $attr['currency']] = (float) $attr['rate'];
                }
            }

            if (!isset($eurMap[$from]) || !isset($eurMap[$to])) {
                if ($from === 'EUR' && isset($eurMap[$to])) {
                    return (float) $eurMap[$to];
                }
                if ($to === 'EUR' && isset($eurMap[$from])) {
                    return 1.0 / (float) $eurMap[$from];
                }
                return null;
            }

            // EUR->TO divided by EUR->FROM
            return $eurMap[$to] / $eurMap[$from];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Return a currency symbol for nicer display.
     */
    private function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'GBP' => '£',
            'EUR' => '€',
            'INR' => '₹',
            'JPY' => '¥',
            default => $currency,
        };
    }

    /**
     * Format a money value with symbol and standard decimals.
     */
    private function formatMoney(float $amount, string $currency): string
    {
        $symbol = $this->currencySymbol($currency);
        $decimals = match (strtoupper($currency)) {
            'JPY' => 0,
            default => 2,
        };
        return $symbol . number_format($amount, $decimals, '.', ',');
    }
    public function index()
    {
        $subscriptionListings = SubscriptionListing::all();
        return view('admin.subscription_listing.index', compact('subscriptionListings'));
    }

    public function create()
    {
        $regions = Region::where('is_active', 1)->get();
        return view('admin.subscription_listing.create', compact('regions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'subscription_name' => 'required|string|max:255',
            'sub_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'duration_unit' => 'required|string',
            'type' => 'required|in:one_time,recurring',

            // NEW:
            'regions' => 'array|nullable',
            'regions.*' => 'integer|exists:regions,id',
        ]);

        DB::transaction(function () use ($request) {
            $subscription = SubscriptionListing::create([
                'subscription_name' => $request->subscription_name,
                'sub_description' => $request->sub_description,
                'price' => $request->price,
                'duration' => $request->duration,
                'duration_unit' => $request->duration_unit,
                'type' => $request->type,
            ]);

            // Insert pivot rows via SubscriptionRegion model (like ChannelRegion)
            $regionIds = collect($request->input('regions', []))
                ->filter()
                ->unique()
                ->values();

            if ($regionIds->isNotEmpty()) {
                $rows = $regionIds->map(fn($rid) => [
                    'subscription_id' => $subscription->id,
                    'region_id' => $rid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                SubscriptionRegion::insert($rows);
            }
        });

        return redirect()->route('admin.subscription_listing.index')
            ->with('success', 'Subscription Package created successfully!');
    }

    public function edit($id)
    {
        $subscriptionListing = SubscriptionListing::findOrFail($id);

        // Active regions or already selected ones
        $regions = Region::where('is_active', 1)
            ->orWhereIn('id', function ($q) use ($subscriptionListing) {
                $q->select('region_id')
                    ->from('subscription_region')
                    ->where('subscription_id', $subscriptionListing->id);
            })
            ->get();

        $selectedRegions = DB::table('subscription_region')
            ->where('subscription_id', $subscriptionListing->id)
            ->pluck('region_id')
            ->toArray();

        return view('admin.subscription_listing.edit', compact('subscriptionListing', 'regions', 'selectedRegions'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'subscription_name' => 'required|string|max:255',
            'sub_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:1',
            'duration_unit' => 'required|string',
            'type' => 'required|in:one_time,recurring',

            // NEW:
            'regions' => 'nullable|array',
            'regions.*' => 'integer|exists:regions,id',
        ]);

        DB::transaction(function () use ($request, $id) {
            $subscription = SubscriptionListing::findOrFail($id);

            $subscription->update([
                'subscription_name' => $request->subscription_name,
                'sub_description' => $request->sub_description,
                'price' => $request->price,
                'duration' => $request->duration,
                'duration_unit' => $request->duration_unit,
                'type' => $request->type,
            ]);

            SubscriptionRegion::where('subscription_id', $subscription->id)->delete();

            $regionIds = collect($request->input('regions', []))
                ->filter()
                ->unique()
                ->values();

            if ($regionIds->isNotEmpty()) {
                $rows = $regionIds->map(fn($rid) => [
                    'subscription_id' => $subscription->id,
                    'region_id' => $rid,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                SubscriptionRegion::insert($rows);
            }
        });

        return redirect()->route('admin.subscription_listing.index')
            ->with('success', 'Subscription Package updated successfully!');
    }

    public function destroy($id)
    {
        $subscription = SubscriptionListing::findOrFail($id);
        $subscription->delete();

        return redirect()->route('admin.subscription_listing.index')->with('success', 'Subscription Package deleted successfully!');
    }


    // APi's

    public function index_api()
    {
        $plans = DB::table('subscription_listing')
            ->select('id', 'subscription_name', 'sub_description', 'price', 'duration', 'duration_unit', 'type')
            ->orderBy('id')
            ->get();

        return response()->json($plans);
    }

    // public function index_region_api(Request $request, $region = null)
    // {
    //     try {
    //         $input = strtoupper($region ?? (string) $request->input('region', ''));
    //         $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL']; // include those you actually support
    //         $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

    //         $query = SubscriptionListing::select('id','subscription_name','sub_description','price','duration','duration_unit','type')
    //             ->whereHas('regions', function ($q) use ($regionCode) {
    //                 $q->where('region_code', $regionCode);
    //             })
    //             ->with(['regions:id,region_code'])
    //             ->orderBy('id');

    //         $plans = $query->get();

    //         $data = $plans->map(function ($plan) {
    //             if ($plan->relationLoaded('regions')) {
    //                 $plan->regions->each->makeHidden(['pivot']);
    //             }

    //             return [
    //                 'id'                => $plan->id,
    //                 'subscription_name' => $plan->subscription_name,
    //                 'sub_description'   => $plan->sub_description,
    //                 'price'             => $plan->price,
    //                 'duration'          => $plan->duration,
    //                 'duration_unit'     => $plan->duration_unit,
    //                 'type'              => $plan->type,
    //                 'regions'           => $plan->regions->map(fn($r) => [
    //                     'id'          => $r->id,
    //                     'region_code' => $r->region_code,
    //                 ]),
    //             ];
    //         });

    //         return response()->json([
    //             'status'  => true,
    //             'message' => "Subscriptions for region {$regionCode} fetched successfully",
    //             'data'    => $data,
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status'  => false,
    //             'message' => 'Failed to fetch subscriptions',
    //             'error'   => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function index_region_api(Request $request, $region)
{
    try {
        // Ensure the region is valid by converting it to uppercase
        $input = strtoupper($region);
        $allowed = ['AU', 'CA', 'UK', 'US', 'GLOBAL'];

        // Validate the region
        $regionCode = in_array($input, $allowed, true) ? $input : 'GLOBAL';

        // Set the currency based on the region
        $regionCurrency = match ($regionCode) {
            'AU' => 'AUD',
            'CA' => 'CAD',
            'UK' => 'GBP',
            'US' => 'USD',
            'GLOBAL' => 'INR',
            default => 'USD',
        };

        $baseCurrency = 'USD';

        // Query the subscription listings based on the region
        $query = SubscriptionListing::select('id', 'subscription_name', 'sub_description', 'price', 'duration', 'duration_unit', 'type')
            ->whereHas('regions', function ($q) use ($regionCode) {
                $q->where('region_code', $regionCode);
            })
            ->with(['regions:id,region_code'])
            ->orderBy('id');

        $plans = $query->get();

        // Get the real-time currency exchange rate
        $fxRate = $this->getRealtimeRate($baseCurrency, $regionCurrency);

        // Map the subscription listings with the converted price and other data
        $data = $plans->map(function ($plan) use ($fxRate, $regionCode, $regionCurrency, $baseCurrency) {
            if ($plan->relationLoaded('regions')) {
                $plan->regions->each->makeHidden(['pivot']);
            }

            // Convert the price to the local currency
            $priceLocal = round(((float) $plan->price) * $fxRate, 2);

            return [
                'id' => $plan->id,
                'subscription_name' => $plan->subscription_name,
                'sub_description' => $plan->sub_description,
                'price' => $priceLocal,
                'currency' => $regionCurrency,
                'currency_symbol' => $this->currencySymbol($regionCurrency),
                'display_price' => $this->formatMoney($priceLocal, $regionCurrency),
                'duration' => $plan->duration,
                'duration_unit' => $plan->duration_unit,
                'type' => $plan->type,
                'region' => $regionCode,
                'base_currency' => $baseCurrency,
                'fx_rate_used' => $fxRate,
                'regions' => $plan->regions->map(fn($r) => [
                    'id' => $r->id,
                    'region_code' => $r->region_code,
                ]),
            ];
        });

        return response()->json([
            'status' => true,
            'message' => "Subscriptions for region {$regionCode} fetched successfully",
            'data' => $data,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch subscriptions',
            'error' => $e->getMessage(),
        ], 500);
    }
}


}
