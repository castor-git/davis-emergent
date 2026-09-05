<?php
namespace App\Controllers;

use App\Core\View;
use App\Support\Preferences;

class PrefsController {
    public function pin(): void {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['slug'] ?? ''));
        if ($slug) Preferences::togglePin($slug);
        View::json(['ok'=>true,'pins'=>Preferences::pins(),'hides'=>Preferences::hides()]);
    }
    public function hide(): void {
        $slug = preg_replace('/[^a-z0-9\-]/', '', (string)($_POST['slug'] ?? ''));
        if ($slug) Preferences::toggleHide($slug);
        View::json(['ok'=>true,'pins'=>Preferences::pins(),'hides'=>Preferences::hides()]);
    }
    public function state(): void {
        View::json(['pins'=>Preferences::pins(),'hides'=>Preferences::hides()]);
    }
}
