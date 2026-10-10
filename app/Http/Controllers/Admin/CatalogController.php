<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WoodType;
use App\Support\Photos;
use Illuminate\Http\Request;

/**
 * Číselník dřev. Nemažou se – použitou volbu má
 * zakázka v historii; nepotřebná se jen vypne a přestane se nabízet.
 */
class CatalogController extends Controller
{
    public function index()
    {
        return view('admin.catalog', [
            'woodTypes' => WoodType::ordered()->withCount('orders')->get(),
        ]);
    }

    public function storeWood(Request $request)
    {
        $woodType = WoodType::create($this->woodData($request) + ['active' => true]);
        $this->saveDefault($request, $woodType);

        return $this->savePhoto($request, $woodType, __('Dřevo přidáno.'));
    }

    public function updateWood(Request $request, WoodType $woodType)
    {
        $woodType->update($this->woodData($request) + ['active' => $request->boolean('active')]);

        $this->saveDefault($request, $woodType);

        if ($request->boolean('remove_photo')) {
            $woodType->removePhoto();
        }

        return $this->savePhoto($request, $woodType, __('Dřevo uloženo.'));
    }

    private function saveDefault(Request $request, WoodType $woodType): void
    {
        if ($request->boolean('is_default')) {
            $woodType->makeDefault();
        } elseif ($woodType->is_default) {
            $woodType->forceFill(['is_default' => false])->save();
        }
    }

    private function savePhoto(Request $request, WoodType $woodType, string $message)
    {
        if ($request->hasFile('photo') && ! $woodType->replacePhoto($request->file('photo'))) {
            return back()->with('success', $message)
                ->withErrors(['photo' => __('Z fotky se nepodařilo udělat náhled – zkuste ji uložit jako JPG.')]);
        }

        return back()->with('success', $message);
    }

    private function woodData(Request $request): array
    {
        $data = $request->validate([
            'name'       => 'required|string|max:80',
            'name_sk'    => 'nullable|string|max:80',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'photo'      => 'nullable|file|mimes:' . implode(',', Photos::EXTENSIONS) . '|max:' . Photos::MAX_KB,
        ]);

        return ['name' => $data['name'], 'name_sk' => $data['name_sk'] ?? null, 'sort_order' => (int) ($data['sort_order'] ?? 0)];
    }
}
