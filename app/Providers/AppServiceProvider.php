<?php

namespace App\Providers;

use App\Console\BloqueioDeComandosDestrutivos;
use App\Mail\NaoEnviaParaEnderecoDeTeste;
use App\Mail\VerificadorDeDominio;
use App\Mail\VerificadorDeDominioPorDns;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
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
        $this->app->singleton(VerificadorDeDominio::class, VerificadorDeDominioPorDns::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(CommandStarting::class, BloqueioDeComandosDestrutivos::class);
        Event::listen(MessageSending::class, NaoEnviaParaEnderecoDeTeste::class);

        RateLimiter::for(self::LIMITE_DA_PAGINA_DE_INDICACAO, function (Request $request) {
            return Limit::perMinute(self::INDICACOES_POR_MINUTO)->by(self::LIMITE_DA_PAGINA_DE_INDICACAO.'|'.$request->ip());
        });
    }
}
