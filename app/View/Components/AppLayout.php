<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Dashboard shell (sidebar + header) used by the dashboard and profile pages.
     */
    public function __construct(public string $title = 'Dashboard') {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
