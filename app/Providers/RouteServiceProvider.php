<?php

namespace App\Providers;

use App\Classes\Hook;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductUnitQuantity;
use App\Services\BarcodeService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        /**
         * This will ensure a correct route binding for the driver.
         * As we're using a scope that apply a jointure, we would like to avoid
         * an ambiguous "id" column error.
         */
        Route::bind( 'driver', function ( $value ) {
            return Driver::where( 'nexopos_users.id', $value )
                ->firstOrFail();
        } );

        Route::get( '/storage/products/barcodes/{barcode}.png', function ( string $barcode ) {
            $decodedBarcode = urldecode( $barcode );
            $disk = Storage::disk( 'public' );
            $barcodePath = Hook::filter( 'ns-media-path', 'products/barcodes/' . $decodedBarcode . '.png' );

            if ( ! $disk->exists( $barcodePath ) ) {
                $productUnitQuantity = ProductUnitQuantity::with( [ 'product' ] )
                    ->where( 'barcode', $decodedBarcode )
                    ->first();

                $barcodeType = $productUnitQuantity?->product?->barcode_type
                    ?? Product::where( 'barcode', $decodedBarcode )->value( 'barcode_type' )
                    ?? BarcodeService::TYPE_CODE128;

                app( BarcodeService::class )->generateBarcode( $decodedBarcode, $barcodeType );
            }

            abort_unless( $disk->exists( $barcodePath ), 404 );

            return response( $disk->get( $barcodePath ), 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'public, max-age=86400',
            ] );
        } )->where( 'barcode', '.*' );
    }
}
