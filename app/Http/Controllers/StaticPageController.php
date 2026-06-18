<?php

namespace App\Http\Controllers;

use App\Services\SystemSettingService;
use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function about(SystemSettingService $settings): View
    {
        return $this->render('Tentang Kami', 'tentang-kami', $settings);
    }

    public function contact(SystemSettingService $settings): View
    {
        return $this->render('Kontak', 'kontak', $settings);
    }

    public function privacy(SystemSettingService $settings): View
    {
        return $this->render('Kebijakan Privasi', 'kebijakan-privasi', $settings);
    }

    public function terms(SystemSettingService $settings): View
    {
        return $this->render('Syarat & Ketentuan', 'syarat-ketentuan', $settings);
    }

    public function paymentPolicy(SystemSettingService $settings): View
    {
        return $this->render('Kebijakan Pembayaran & Penyelesaian Dana', 'kebijakan-pembayaran', $settings);
    }

    private function render(string $title, string $slug, SystemSettingService $settings): View
    {
        return view('pages.standard', [
            'title' => $title,
            'slug' => $slug,
            'settings' => $settings->all(),
        ]);
    }
}
