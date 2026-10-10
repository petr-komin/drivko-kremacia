<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /** Přepnutí jazyka prostředí (a e-mailů) pro přihlášeného uživatele. */
    public function locale(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(config('kremacia.locales')))],
        ]);

        $request->user()->forceFill(['locale' => $data['locale']])->save();

        return back();
    }
}
