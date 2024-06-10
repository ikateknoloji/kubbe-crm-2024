<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Http;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureHttpClient();
    }

    protected function configureHttpClient()
    {
        // Sabiti tanımla
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6); // 6, CURLOPT_SSLVERSION_TLSv1_2'nin karşılığıdır.
        }

        // Özelleştirilmiş HTTP istemcisini tanımla
        Http::macro('custom', function () {
            return Http::withOptions([
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                ],
            ]);
        });
    }
    
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
