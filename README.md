# sunstech/laravel-ops

Laravel uygulamalarının [SUNS CMS Merkez](https://hub.sunstech.com.tr) tarafından izlenmesi için iki uç ekler:

| Uç | Erişim | Ne döner |
|---|---|---|
| `GET /api/health` | Açık | `{"status":"ok"}` (200) ya da `{"status":"degraded"}` (503); veritabanı, önbellek ve depolama kontrolleri |
| `GET /api/v1/ops/summary` | `Authorization: Bearer OPS_TOKEN` | Sürüm, sağlık, kuyruk, zamanlayıcı, yedek, disk ve sunucu (yük, bellek) özeti |

Özetin biçimi SUNS CMS'inkiyle aynıdır. Merkez her 5 dakikada bir yoklar ve sorun olunca uyarı açar:

- kuyruk takıldıysa ya da başarısız iş çoksa,
- zamanlayıcı çalışmıyorsa,
- yedek eskiyse,
- disk doluysa.

Laravel 11 ve 12, PHP 8.2 ve üstü desteklenir.

## Kurulum

```bash
composer require sunstech/laravel-ops
```

Paket Packagist'te değilse önce uygulamanın `composer.json` dosyasına depoyu ekleyin:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/sunstech/laravel-ops" }
]
```

`.env` dosyasına bir anahtar ekleyin (en az 32 karakter):

```dotenv
OPS_TOKEN=uzun-rastgele-bir-anahtar
```

Anahtar üretmek için: `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`

Merkezde kurulum eklerken aynı anahtarı **Ops anahtarı** alanına girin. Ürün ayarlarında sağlık ucu `/api/health` olmalı, sağlıklı sayılma koşulu JSON seçilmeli ve ops özeti ucu `/api/v1/ops/summary` girilmelidir.

Merkez projeyi kendisi kurduysa (Yeni proje sihirbazı, "Laravel uygulaması" şablonu) `OPS_TOKEN` ve `APP_VERSION` zaten yazılmıştır. Projede yalnızca paketin kurulu olması yeterlidir.

Kontrol etmek için:

```bash
php artisan ops:summary
```

Bu komut merkezin göreceği özeti yazdırır.

## Zamanlayıcı

Paket her dakika bir "nabız" kaydeder. Bu kayıt 10 dakikadan eskiyse merkez "zamanlayıcı çalışmıyor" uyarısı açar. Zamanlayıcı çalışıyor olmalıdır; ikisinden birini kullanın:

- `php artisan schedule:work` (Docker'da ayrı bir servis olarak)
- cron: `* * * * * php /yol/artisan schedule:run`

Uygulama zamanlayıcı kullanmıyorsa `OPS_SCHEDULER=false` yapın. Bu durumda özette `scheduler` alanı `null` döner ve merkez bu konuda uyarı açmaz.

Nabız önbelleğe yazılır. Önbellek sürücüsü `array` olmamalıdır: `file`, `database` ya da `redis` kullanın.

## Yedek

[spatie/laravel-backup](https://github.com/spatie/laravel-backup) kuruluysa, yapılandırılmış hedef disklerdeki en yeni yedeğin tarihi ve boyutu raporlanır. 48 saatten eski yedek "sağlıksız" sayılır. Paket kurulu değilse ya da `OPS_BACKUP=false` ise `backup` alanı `null` döner ve merkez yedek uyarısı açmaz.

## Ayarlar

Varsayılanlar çoğu proje için yeterlidir. Değiştirmek için ayar dosyasını yayımlayın:

```bash
php artisan vendor:publish --tag=ops-config
```

| `.env` | Varsayılan | Açıklama |
|---|---|---|
| `OPS_TOKEN` | — | Özet ucunun anahtarı. Anahtar değiştirirken `yeni,eski` yazılabilir; ikisi de kabul edilir. |
| `OPS_ROUTE_PREFIX` | `api` | Uçların öneki |
| `OPS_HEALTH_ROUTE` | `true` | Uygulamada zaten `/api/health` varsa `false` yapın |
| `OPS_SCHEDULER` | `true` | Zamanlayıcı nabzı ve raporu |
| `OPS_BACKUP` | `true` | spatie/laravel-backup raporu |
| `OPS_STORAGE_DISK` | `local` | Depolama kontrolünün yazıp sileceği disk |
| `APP_VERSION`, `APP_RELEASE` | — | Merkezde görünen sürüm ve derleme |

Kuyruk özeti `database` sürücüsünde bekleyen ve takılan işleri de gösterir. Diğer sürücülerde yalnızca başarısız işler sayılır.

## Güvenlik

- Özet ucu yalnızca `Authorization` başlığındaki anahtarla açılır.
- Anahtarlar sabit sürede karşılaştırılır.
- Hata mesajları ve ortam değişkenleri dışarı verilmez.
- `/api/health` yalnızca durum döner.

## Geliştirme

```bash
composer install
composer test
composer format
```

## Lisans

MIT. Ayrıntılar için [LICENSE](LICENSE) dosyasına bakın.
