<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderMessage;
use App\Models\OrderPhoto;
use App\Support\Notifier;
use App\Support\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderAdminController extends Controller
{
    /**
     * Změna stavu, volitelně s fotkami hotového kusu a vzkazem pro kremaci.
     * Fotka hotového kusu je nepovinná – jde doplnit i později.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'body'   => 'nullable|string|max:5000',
        ] + Photos::rules());

        $files  = $request->file('photos', []);
        $status = $data['status'];

        $statusChanged = $status !== $order->status;
        $body = trim((string) ($data['body'] ?? ''));

        $message = DB::transaction(function () use ($order, $request, $status, $statusChanged, $files, $body) {
            if ($statusChanged) {
                $order->moveTo($status);
                OrderMessage::system($order, 'status', ['status' => $status]);
            }

            if ($body !== '' || $files) {
                $message = $order->messages()->create([
                    'user_id' => $request->user()->id,
                    'body'    => $body !== '' ? $body : null,
                ]);

                Photos::store($order, $files, OrderPhoto::KIND_FINISHED, $message);
                $order->touchActivity();

                return $message;
            }

            return null;
        });

        $order->load('woodType');

        // Změnu stavu ohlásí jeden e-mail (vzkaz je jeho součástí jako zpráva ve vláknu),
        // samotný vzkaz nebo fotky bez změny stavu se ohlásí jako zpráva.
        if ($statusChanged) {
            Notifier::statusChanged($order, $request->user(), $body !== '' ? $body : null);
        } elseif ($message) {
            Notifier::message($order, $message, $request->user());
        }

        return redirect()->route('orders.show', $order)->with('success', $statusChanged
            ? __('Zakázka je ve stavu „:status“.', ['status' => $order->status_label])
            : __('Uloženo.'));
    }

    /** Zapnutí / vypnutí e-mailů k zakázce (zpětný zápis starších zakázek). */
    public function updateNotify(Request $request, Order $order)
    {
        $order->forceFill(['notify' => $request->boolean('notify')])->save();

        return back()->with('success', $order->notify
            ? __('E-maily k zakázce jsou zapnuté.')
            : __('E-maily k zakázce jsou vypnuté.'));
    }

    /**
     * Zaplaceno – nezávisle na stavu výroby. Zaškrtnutí zapíše aktuální čas,
     * datum jde pak opravit; odškrtnutí platbu smaže. Změna se zapíše do vlákna.
     */
    public function updatePaid(Request $request, Order $order)
    {
        $data = $request->validate([
            'paid'    => 'boolean',
            'paid_at' => 'nullable|date',
        ]);

        $order->paid_at = match (true) {
            ! $request->boolean('paid')  => null,
            ! empty($data['paid_at'])    => Carbon::parse($data['paid_at']),
            default                      => $order->paid_at ?? now(),
        };

        if ($order->isDirty('paid_at')) {
            $change = ['paid_at' => [$order->getOriginal('paid_at')?->toIso8601String(), $order->paid_at?->toIso8601String()]];
            $order->save();
            OrderMessage::system($order, 'edited', ['name' => $request->user()->name, 'changes' => $change]);
        }

        return back()->with('success', $order->isPaid()
            ? __('Zakázka je zaplacená (:date).', ['date' => $order->paid_at->format('j. n. Y H:i')])
            : __('Platba je zrušená.'));
    }

    /** Všechny fotky zakázky v jednom ZIPu – podklady i hotový kus. */
    public function downloadZip(Order $order)
    {
        $photos = $order->photos()->get();
        abort_if($photos->isEmpty(), 404);

        $disk = Storage::disk(Photos::DISK);
        $slug = Str::slug($order->pet_name) ?: 'urna';
        $tmp  = tempnam(sys_get_temp_dir(), 'zip');

        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);

        foreach ($photos as $photo) {
            if (! $disk->exists($photo->path)) {
                continue;
            }

            $folder = $photo->kind === OrderPhoto::KIND_FINISHED ? 'hotovo' : 'podklady';
            $ext    = pathinfo($photo->path, PATHINFO_EXTENSION);

            $zip->addFile($disk->path($photo->path), "{$folder}/{$slug}-{$photo->id}.{$ext}");
        }

        $zip->close();

        return response()->download($tmp, "{$order->number}-{$slug}.zip")->deleteFileAfterSend();
    }
}
