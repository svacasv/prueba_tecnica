<?php

use Illuminate\Support\Facades\Route;

// La única página del proyecto: la documentación interactiva de la API.
Route::view('/', 'docs');
