<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderPhoto;
use App\Support\Notifier;
use App\Support\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(Request $request, Order $order)
    {
        $request->validate(['body' => 'nullable|string|max:5000'] + Photos::rules());

        $body  = trim((string) $request->input('body'));
        $files = $request->file('photos', []);

        if ($body === '' && ! $files) {
            return back()->withErrors(['body' => __('Napište zprávu nebo přiložte fotku.')]);
        }

        // Fotky od kremace ve vláknu jsou zpravidla doplněné podklady
        // („ostřejší fotka tlapky“) – patří mezi podklady, ne jen do zprávy.
        $kind = $request->user()->isAdmin() ? OrderPhoto::KIND_MESSAGE : OrderPhoto::KIND_PAW;

        $message = DB::transaction(function () use ($order, $request, $body, $files, $kind) {
            $message = $order->messages()->create([
                'user_id' => $request->user()->id,
                'body'    => $body !== '' ? $body : null,
            ]);

            Photos::store($order, $files, $kind, $message);
            $order->touchActivity();

            return $message;
        });

        Notifier::message($order->load('woodType'), $message, $request->user());

        return redirect()->to(route('orders.show', $order) . '#zpravy')->with('success', __('Zpráva odeslána.'));
    }
}
