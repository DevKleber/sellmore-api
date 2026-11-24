<?php

namespace App\Providers;

use App\Console\BloqueioDeComandosDestrutivos;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Contador proprio da pagina publica de indicacao, separado do limite geral da API.
     */
    public const LIMITE_DA_PAGINA_DE_INDICACAO = 'indicacao';
    private const INDICACOES_POR_MINUTO = 20;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(CommandStarting::class, BloqueioDeComandosDestrutivos::class);

        RateLimiter::for(self::LIMITE_DA_PAGINA_DE_INDICACAO, function (Request $request) {
            return Limit::perMinute(self::INDICACOES_POR_MINUTO)->by(self::LIMITE_DA_PAGINA_DE_INDICACAO.'|'.$request->ip());
        });
    }
}
