<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function loginForm()
    {
        if (Auth::check()) {
            return redirect()->route('orders.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials + ['active' => true], remember: true)) {
            $request->session()->regenerate();

            return redirect()->intended(route('orders.index'));
        }

        return back()->withErrors(['email' => __('auth.failed')])->onlyInput('email');
    }

    /**
     * Přihlášení přístupovým odkazem. Token se hned vymění za trvalé přihlášení
     * a přesměruje se na čistou adresu – v adresním řádku, historii ani na
     * snímku obrazovky pak token nezůstane.
     */
    public function access(Request $request, string $token)
    {
        // Messenger, WhatsApp a spol. si odkaz z chatu samy otevřou kvůli náhledu.
        // Takový robot nesmí dostat přihlášení ani nic z aplikace.
        if (self::isLinkPreviewBot($request)) {
            return response(view('auth.link-preview'))->header('X-Robots-Tag', 'noindex, nofollow');
        }

        $user = User::findByAccessToken($token);

        if (! $user) {
            return redirect()->route('login')->withErrors([
                'email' => __('Přístupový odkaz neplatí – byl zrušen nebo nahrazen novým. Požádejte dílnu o aktuální.'),
            ]);
        }

        if (Auth::id() !== $user->id) {
            Auth::login($user, remember: true);
            $request->session()->regenerate();
        }

        return redirect()->route('orders.index');
    }

    private static function isLinkPreviewBot(Request $request): bool
    {
        return (bool) preg_match(
            '/facebookexternalhit|facebookcatalog|meta-externalagent|WhatsApp|TelegramBot|Slackbot|Twitterbot|Discordbot|LinkedInBot|SkypeUriPreview|Viber|Iframely|Google-PageRenderer|bot\b|crawler|spider|preview/i',
            (string) $request->userAgent()
        );
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
