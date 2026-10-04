<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\HomeModel;
use PHPAML\Http\Request;
use PHPAML\Http\Response;
use PHPAML\Mvc\Controller;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->json(['name' => (new HomeModel())->getName(), 'status' => 'ok']);
    }
}
