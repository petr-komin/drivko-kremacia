<?php

namespace App\Http\Controllers;

use App\Models\OrderPhoto;
use App\Models\WoodType;
use App\Support\Photos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends Controller
{
    /** Vydá fotku přihlášenému uživateli – originál ke stažení, nebo náhled. */
    public function show(OrderPhoto $photo, string $variant = 'large')
    {
        $disk = Storage::disk(Photos::DISK);

        if ($variant === 'original') {
            abort_unless($disk->exists($photo->path), 404);

            return $disk->download($photo->path, $this->downloadName($photo), [
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        abort_unless(array_key_exists($variant, Photos::VARIANTS), 404);

        $path = Photos::variantPath($photo->path, $variant);
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type'  => 'image/webp',
            'Cache-Control' => 'private, max-age=2592000',
        ]);
    }

    /** Ukázková fotka dřeva z číselníku – jen náhledy, originál se nikde nenabízí. */
    public function wood(WoodType $woodType, string $variant = 'thumb')
    {
        abort_unless($woodType->photo_path, 404);

        $path = Photos::variantPath($woodType->photo_path, $variant);
        abort_unless(Storage::disk(Photos::DISK)->exists($path), 404);

        return response()->file(Storage::disk(Photos::DISK)->path($path), [
            'Content-Type'  => 'image/webp',
            'Cache-Control' => 'private, max-age=2592000',
        ]);
    }

    /** Smazat smí autor fotky nebo dílna – na opravu omylem nahraného souboru. */
    public function destroy(Request $request, OrderPhoto $photo)
    {
        abort_unless($request->user()->isAdmin() || $photo->user_id === $request->user()->id, 403);

        $order = $photo->order;
        $photo->delete();

        return redirect()->route('orders.show', $order)->with('success', __('Fotka smazána.'));
    }

    /** U-0042-rex-tlapka-3.jpg – v dílně se pak soubory snadno dohledají. */
    private function downloadName(OrderPhoto $photo): string
    {
        $order = $photo->order;
        $ext   = pathinfo($photo->path, PATHINFO_EXTENSION);
        $kind  = $photo->kind === OrderPhoto::KIND_FINISHED ? 'hotovo' : 'tlapka';

        return sprintf('%s-%s-%s-%d.%s', $order->number, Str::slug($order->pet_name) ?: 'urna', $kind, $photo->id, $ext);
    }
}
