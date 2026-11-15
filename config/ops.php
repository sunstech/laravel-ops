<?php

return [

    /*
    | Merkezin ops özetini okuduğu anahtar (Authorization: Bearer). En az 32 karakter.
    | Anahtar değiştirirken "yeni,eski" yazılabilir; ikisi de kabul edilir. Boşsa özet
    | ucu her isteğe 401 döner.
    */
    'token' => env('OPS_TOKEN'),

    /*
    | Uçlar: GET /{prefix}/health (açık) ve GET /{prefix}/{summary_path} (anahtarlı).
    | Uygulamada zaten bir /api/health varsa OPS_HEALTH_ROUTE=false yapın.
    */
    'route_prefix' => env('OPS_ROUTE_PREFIX', 'api'),
    'summary_path' => 'v1/ops/summary',
    'health_route' => (bool) env('OPS_HEALTH_ROUTE', true),

    /*
    | Sürüm ve derleme bilgisi (merkezde "Sürüm" olarak görünür). Merkez SSH ile kurduğu
    | projelerde APP_VERSION'ı kendisi yazar.
    */
    'version' => env('APP_VERSION'),
    'release' => env('APP_RELEASE'),

    /*
    | Zamanlayıcı nabzı: paket her dakika önbelleğe bir zaman yazar; bu kadar dakikadır
    | yazılmadıysa zamanlayıcı (schedule:work ya da cron) çalışmıyor sayılır. Uygulama
    | zamanlayıcı kullanmıyorsa OPS_SCHEDULER=false (özette null döner, uyarı açılmaz).
    */
    'scheduler' => (bool) env('OPS_SCHEDULER', true),
    'scheduler_stale_minutes' => 10,

    /*
    | Kuyruk: database sürücüsünde hiç alınmamış bir iş bu kadar beklerse işçi duruyor sayılır.
    */
    'queue_stale_minutes' => 10,

    /*
    | Yedek: spatie/laravel-backup kuruluysa en yeni yedek okunur; değilse ya da false ise
    | özette null döner (merkez yedek uyarısı açmaz).
    */
    'backup' => (bool) env('OPS_BACKUP', true),
    'backup_max_age_hours' => 48,

    /*
    | Depolama kontrolünün yazıp sileceği disk.
    */
    'storage_disk' => env('OPS_STORAGE_DISK', 'local'),

];
