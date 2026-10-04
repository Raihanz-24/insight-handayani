<?php

use Illuminate\Support\Facades\Route;

/*
| Halaman depan tidak menampilkan apa pun — langsung arahkan ke panel.
| Path panel berasal dari ANALYTICS_ADMIN_PATH (nilai acak di server),
| sehingga URL admin tidak mudah ditebak dan konsisten dari satu sumber.
*/
Route::get('/', function () {
    $path = trim((string) config('analytics.admin_path', 'admin'), '/');

    return redirect('/'.$path);
});
