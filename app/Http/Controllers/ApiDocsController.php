<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class ApiDocsController extends Controller
{
    public function ui(): View
    {
        return view('docs');
    }

    public function spec(): Response
    {
        $path = base_path('docs/openapi.yaml');

        abort_unless(is_file($path), 404);

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
