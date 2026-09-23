<?php

namespace App\Providers;

use App\Quotes\Parsing\QuoteParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phone numbers said without a country code belong to the business's country.
        $this->app->bind(QuoteParser::class, fn () => new QuoteParser((string) config('product.default_country_code', '355')));
    }
}
