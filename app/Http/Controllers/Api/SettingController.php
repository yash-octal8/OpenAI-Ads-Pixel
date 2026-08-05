<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ShopSetting;
use Illuminate\Support\Facades\Auth;

class SettingController extends Controller
{
    public function index()
    {
        $shop = Auth::user();
        
        $localeLabelMap = [
            'ar' => 'Arabic', 'ca' => 'Catalan', 'de' => 'German', 'en' => 'English', 'es' => 'Spanish',
            'fr' => 'French', 'hi' => 'Hindi', 'it' => 'Italian', 'ja' => 'Japanese', 'pt' => 'Portuguese',
            'zh-cn' => 'Chinese (Simplified)',
        ];
        
        $languageOptions = collect($localeLabelMap)->map(function ($label, $value) {
            return ['value' => $value, 'label' => $label];
        })->values();

        $settings = ShopSetting::firstOrCreate(
            ['user_id' => $shop->id],
            [
                'pixel_id' => '',
                'capi_key' => '',
                'advertiser_key' => '',
                'tracking_enabled' => true,
                'pixel_helper_enabled' => true,
            ]
        );

        return response()->json([
            'languageOptions' => $languageOptions,
            'settings' => $settings,
            'shop' => $shop->name,
        ]);
    }

    public function store(Request $request)
    {
        $shop = Auth::user();
        
        $settings = ShopSetting::updateOrCreate(
            ['user_id' => $shop->id],
            [
                'pixel_id' => $request->input('pixel_id', ''),
                'capi_key' => $request->input('capi_key'),
                'advertiser_key' => $request->input('advertiser_key'),
                'tracking_enabled' => $request->boolean('tracking_enabled', true),
                'pixel_helper_enabled' => $request->boolean('pixel_helper_enabled', true),
            ]
        );

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }
}
