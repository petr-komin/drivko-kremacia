<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Evidence dokončených kusů po měsících – podklad k fakturaci.
 * Kus se počítá do měsíce, kdy byl dokončen, nebo (volitelně) předán.
 */
class ReportController extends Controller
{
    private const BASES = [
        'finished_at'    => 'Dokončeno',
        'handed_over_at' => 'Předáno',
    ];

    public function index(Request $request)
    {
        [$month, $basis] = $this->period($request);

        $orders = $this->orders($month, $basis);

        $summary = $orders
            ->groupBy(fn (Order $o) => $o->woodType->label . ' · ' . $o->volume_label)
            ->map->count()
            ->sortKeys();

        return view('admin.report', [
            'orders'  => $orders,
            'summary' => $summary,
            'month'   => $month,
            'basis'   => $basis,
            'bases'   => array_map(fn ($label) => __($label), self::BASES),
        ]);
    }

    public function csv(Request $request)
    {
        [$month, $basis] = $this->period($request);

        $orders = $this->orders($month, $basis);
        $name   = 'urny-' . $month->format('Y-m') . '-' . ($basis === 'finished_at' ? 'dokonceno' : 'predano') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            // BOM – Excel jinak otevře češtinu / slovenštinu rozsypanou
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(fn ($h) => __($h), ['Zakázka', 'Číslo případu', 'Název', 'Dřevo', 'Objem (ml)', 'Vnitřní rozměry', 'Vnější rozměry', 'Tlapka', 'Text na urnu', 'Rozložení', 'Zadáno', 'Dokončeno', 'Předáno']), ';');

            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->number,
                    $o->case_number,
                    $o->pet_name,
                    $o->woodType->label,
                    $o->volume_ml,
                    $o->dimensionsLabel(),
                    $o->dimensionsLabel(outer: true),
                    $o->with_paw ? __('ano') : __('ne'),
                    $o->urnTextWithSizes(),
                    $o->layout_label,
                    $o->created_at->format('j. n. Y'),
                    $o->finished_at?->format('j. n. Y'),
                    $o->handed_over_at?->format('j. n. Y'),
                ], ';');
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: string} */
    private function period(Request $request): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('mesic'))
            ? Carbon::createFromFormat('!Y-m', $request->query('mesic'))
            : now()->startOfMonth();

        $basis = array_key_exists($request->query('podle'), self::BASES) ? $request->query('podle') : 'finished_at';

        return [$month->startOfMonth(), $basis];
    }

    private function orders(Carbon $month, string $basis)
    {
        return Order::with('woodType')
            ->whereBetween($basis, [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            // Zrušená zakázka se nefakturuje, i kdyby předtím byla hotová
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->orderBy($basis)
            ->get();
    }
}
