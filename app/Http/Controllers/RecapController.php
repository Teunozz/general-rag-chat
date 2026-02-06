<?php

namespace App\Http\Controllers;

use App\Models\Recap;
use Illuminate\View\View;

class RecapController extends Controller
{
    public function index(): View
    {
        $recaps = Recap::orderByDesc('period_end')->paginate(20);

        return view('recaps.index', compact('recaps'));
    }

    public function show(Recap $recap): View
    {
        return view('recaps.show', compact('recap'));
    }
}
