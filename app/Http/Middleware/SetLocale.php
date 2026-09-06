<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Langue de l'interface : ?lang=xx (mémorisé), puis préférence du compte, puis session, puis navigateur.
 * Langues servies : français (défaut), anglais, chinois simplifié.
 */
class SetLocale
{
    public const SUPPORTED = ['fr', 'en', 'zh'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = null;
        $asked = (string) $request->query('lang', '');
        if (in_array($asked, self::SUPPORTED, true)) {
            $locale = $asked;
            $request->session()->put('locale', $asked);
            if (Auth::check() && Auth::user()->locale !== $asked) {
                Auth::user()->forceFill(['locale' => $asked])->save();
            }
        }
        $locale = $locale
            ?? (Auth::check() && in_array(Auth::user()->locale, self::SUPPORTED, true) ? Auth::user()->locale : null)
            ?? $request->session()->get('locale')
            ?? $this->fromBrowser($request)
            ?? 'fr';

        app()->setLocale($locale);
        Carbon::setLocale($locale === 'zh' ? 'zh_CN' : $locale);

        return $next($request);
    }

    private function fromBrowser(Request $request): ?string
    {
        foreach (explode(',', (string) $request->header('Accept-Language', '')) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (in_array($code, self::SUPPORTED, true)) {
                return $code;
            }
        }

        return null;
    }

    /** Code de langue pour les instructions de navigation (Valhalla). */
    public static function routingLanguage(): string
    {
        return match (app()->getLocale()) {
            'en' => 'en-US',
            'zh' => 'zh-CN',
            default => 'fr-FR',
        };
    }

    /** Code BCP 47 pour la synthèse vocale. */
    public static function speechLanguage(): string
    {
        return match (app()->getLocale()) {
            'en' => 'en-US',
            'zh' => 'zh-CN',
            default => 'fr-FR',
        };
    }
}
