<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderPhoto;
use App\Models\WoodType;
use App\Support\Notifier;
use App\Support\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** Záložky přehledu => [popisek (klíč překladu), stavy, které zahrnují]. */
    public const FILTERS = [
        'open'        => ['Rozpracované', [Order::STATUS_NEW, Order::STATUS_IN_PRODUCTION]],
        'finished'    => ['K předání', [Order::STATUS_FINISHED]],
        'handed_over' => ['Předané', [Order::STATUS_HANDED_OVER]],
        'all'         => ['Vše', null],
    ];

    public function index(Request $request)
    {
        // Výchozí záložka: dílna rozpracované (co je potřeba udělat), kremace všechno
        $default = $request->user()->isAdmin() ? 'open' : 'all';
        $filter = array_key_exists($request->query('stav'), self::FILTERS) ? $request->query('stav') : $default;
        $search = trim((string) $request->query('q'));

        $orders = Order::query()
            ->with(['woodType', 'pawPhotos'])
            ->withCount('messages')
            ->when(self::FILTERS[$filter][1], fn ($q, $statuses) => $q->whereIn('status', $statuses))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->whereLike('pet_name', "%{$search}%")
                        ->orWhereLike('case_number', "%{$search}%");

                    // „U-0042“ i „42“ najde zakázku podle čísla
                    if (preg_match('/^(?:U-?)?0*(\d+)$/i', $search, $m)) {
                        $q->orWhere('id', (int) $m[1]);
                    }
                });
            })
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $counts = Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('orders.index', compact('orders', 'filter', 'search', 'counts'));
    }

    public function create()
    {
        return view('orders.form', [
            // Výchozí dřevina předvybraná – kdo nezvolí jinou, má ji
            'order'     => (new Order)->forceFill(['wood_type_id' => WoodType::defaultId()]),
            'woodTypes' => WoodType::active()->ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        // Fotka tlapky je povinná jen u urny s otiskem
        $data = $this->normalize($request, $request->validate($this->rules() + Photos::rules(required: $request->boolean('with_paw', true)), [
            'photos.required' => __('Přiložte alespoň jednu fotku tlapky (nebo zrušte otisk tlapky).'),
        ]));

        $order = DB::transaction(function () use ($data, $request) {
            $order = Order::create([
                'case_number'      => $data['case_number'] ?? null,
                'pet_name'         => $data['pet_name'],
                'wood_type_id'     => $data['wood_type_id'],
                'volume_ml'        => $data['volume_ml'],
                'inner_width_cm'   => $data['inner_width_cm'] ?? null,
                'inner_height_cm'  => $data['inner_height_cm'] ?? null,
                'with_paw'         => $data['with_paw'],
                'urn_lines'        => $data['urn_lines'],
                'layout'           => $data['layout'],
                'note'             => $data['note'] ?? null,
                'notify'           => $data['notify'] ?? true,
                'status'           => Order::STATUS_NEW,
                'created_by'       => $request->user()->id,
                'last_activity_at' => now(),
            ]);

            $order->forceFill(['price_eur' => $order->calculatePrice()])->save();
            Photos::store($order, $request->file('photos', []), OrderPhoto::KIND_PAW);

            return $order;
        });

        Notifier::newOrder($order->load('woodType'), $request->user());

        return redirect()->route('orders.show', $order)
            ->with('success', __('Zakázka :number je zadaná. Dáme vědět, až se pustíme do výroby.', ['number' => $order->number]));
    }

    public function show(Order $order)
    {
        $order->load([
            'woodType', 'author',
            'messages.user', 'messages.photos',
            'pawPhotos.user', 'finishedPhotos',
        ]);

        return view('orders.show', [
            'order'    => $order,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function edit(Request $request, Order $order)
    {
        $this->authorizeEdit($request, $order);

        return view('orders.form', [
            'order'     => $order,
            // Neaktivní volba, kterou už zakázka má, musí zůstat v nabídce
            'woodTypes' => WoodType::active()->orWhere('id', $order->wood_type_id)->ordered()->get(),
        ]);
    }

    public function update(Request $request, Order $order)
    {
        $this->authorizeEdit($request, $order);

        $data = $this->normalize($request, $request->validate($this->rules($order)));

        $order->fill($data);

        // Cenu přepočítat jen při změně toho, co ji určuje – jinak zůstává ta ze zadání
        if ($order->isDirty(['wood_type_id', 'volume_ml', 'with_paw', 'urn_lines'])) {
            $order->price_eur = $order->calculatePrice();
        }

        $changes = array_keys($order->getDirty());

        if ($changes) {
            $order->save();
            OrderMessage::system($order, 'edited', ['name' => $request->user()->name]);
            $order->touchActivity();
        }

        return redirect()->route('orders.show', $order)->with('success', __('Zadání je uložené.'));
    }

    // ── Interní ─────────────────────────────────────────────────────────────

    private function rules(?Order $order = null): array
    {
        // Při úpravě smí zůstat i volba, která mezitím přestala být aktivní
        $activeOr = fn (string $table, ?int $current) => Rule::exists($table, 'id')
            ->where(fn ($q) => $q->where('active', true)->when($current, fn ($q) => $q->orWhere('id', $current)));

        return [
            'pet_name'     => 'required|string|max:120',
            'case_number'  => 'nullable|string|max:64',
            'wood_type_id' => ['required', 'integer', $activeOr('wood_types', $order?->wood_type_id)],
            'volume_ml'    => 'required|integer|min:' . Order::MIN_VOLUME_ML . '|max:' . Order::MAX_VOLUME_ML,
            // Rozměry jen jako dvojice – jeden bez druhého nic neříká
            'inner_width_cm'  => 'nullable|required_with:inner_height_cm|numeric|min:' . Order::MIN_INNER_CM . '|max:' . Order::MAX_INNER_CM,
            'inner_height_cm' => 'nullable|required_with:inner_width_cm|numeric|min:' . Order::MIN_INNER_CM . '|max:' . Order::MAX_INNER_CM,
            'with_paw'     => 'boolean',
            'urn_lines'        => 'nullable|array|max:' . Order::MAX_TEXT_LINES,
            'urn_lines.*.text' => 'nullable|string|max:100',
            'urn_lines.*.size' => ['nullable', Rule::in(array_keys(Order::TEXT_SIZES))],
            'layout'       => ['nullable', Rule::in(array_keys(Order::LAYOUTS))],
            'note'         => 'nullable|string|max:5000',
            'notify'       => 'boolean',
        ];
    }

    /** Rozložení dává smysl jen u tlapky s textem; jinak se neukládá. E-maily vypíná jen dílna. */
    private function normalize(Request $request, array $data): array
    {
        if ($request->user()->isAdmin() && array_key_exists('notify', $data)) {
            $data['notify'] = (bool) $data['notify'];
        } else {
            unset($data['notify']);
        }

        $data['with_paw'] = (bool) ($data['with_paw'] ?? true);
        // Prázdné řádky pryč; bez velikosti = střední
        $lines = collect($data['urn_lines'] ?? [])
            ->map(fn ($l) => ['text' => trim($l['text'] ?? ''), 'size' => $l['size'] ?? 'M'])
            ->filter(fn ($l) => $l['text'] !== '')
            ->values()->all();

        $data['urn_lines'] = $lines ?: null;
        $data['layout']    = $data['with_paw'] && $lines
            ? ($data['layout'] ?? Order::DEFAULT_LAYOUT)
            : null;

        return $data;
    }

    /** Kremace může zadání měnit, dokud se nezačalo vyrábět; dílna vždy. */
    private function authorizeEdit(Request $request, Order $order): void
    {
        abort_unless($request->user()->isAdmin() || $order->status === Order::STATUS_NEW, 403);
    }
}
