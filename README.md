# sqlite-mysql-tuzaklari

Yerelde SQLite ile geliştirip canlıda MySQL'e çıkan projeler için: aynı sorgunun iki motorda farklı davrandığı yerlerin çalıştırılabilir listesi. Her senaryo iki motorda gerçekten koşturulur ve sonuç testle sabitlenir.

Tipik hikâye: test takımı SQLite'ta yeşildir, canlıda MySQL'de HTTP 500 çıkar ya da hata vermeden yanlış sonuç döner.

## Sonuçlar

SQLite 3.45.1 ve MariaDB 10.11.14 üzerinde ölçüldü.

| Senaryo | SQLite | MySQL/MariaDB |
|---|---|---|
| `LIKE 'a\_b' ESCAPE '\'` | `1` | **söz dizimi hatası** |
| `LIKE 'a!_b' ESCAPE '!'` | `1` | `1` |
| `LIKE 'a\\_b' ESCAPE '\\'` | **hata** (`ESCAPE` tek karakter olmalı) | `1` |
| `'a' \|\| 'b'` | `ab` | `0` (mantıksal VEYA) |
| `5 / 2` | `2` | `2.5000` |
| `INTEGER` sütununa `'abc'` ekleme | sessizce saklar | **hata** (katı kip) |
| `GROUP BY` ile gruplanmamış sütun | çalışır, satır gelişigüzel | **hata** (`ONLY_FULL_GROUP_BY`) |
| `TEXT` sütununa 70.000 bayt, katı olmayan kip | `70000` bayt saklanır | `65535` bayta **sessizce kırpılır** |
| `TEXT` sütununa 70.000 bayt, katı kip | saklanır | **hata** (`Data too long`) |
| `LIKE '%ş%'`, varsayılan `utf8mb4_unicode_ci` | `2` satır | **`3` satır** (`s` ile `ş` eşit sayılır) |
| `LIKE '%ş%'`, `utf8mb4_turkish_ci` | collation adı tanınmaz | `2` satır |
| `LIKE '%ş%'`, bağlantı `latin1` | `2` satır | **`0` satır**, hata yok |
| `'IŞIK' LIKE 'ışık'` | `0` | `1` |

## Ne yapmalı

- **`ESCAPE`:** Ters bölü yerine başka bir karakter kullan, örneğin `ESCAPE '!'`. İki motorda da aynı çalışır. `'\'` MySQL'de, `'\\'` SQLite'ta sorun çıkarır.
- **Birleştirme:** `||` yerine `CONCAT()` MySQL'de çalışır ama SQLite'ta yoktur. Taşınabilir bir yol uygulama katmanında birleştirmektir. Laravel gibi bir sorgu oluşturucu kullanıyorsan ham SQL'den kaçın.
- **Bölme:** Tam sayı bölmesine güvenme, bir tarafı `1.0` ile çarp ya da uygulama katmanında böl.
- **Türler ve `GROUP BY`:** Test ortamını da katı kipte çalıştır. MySQL tarafında `sql_mode` değerini üretimle aynı yap ya da testleri MySQL'e karşı koştur.
- **`TEXT` sınırı:** Büyük metin için `MEDIUMTEXT` kullan ve yazmadan önce bayt uzunluğunu doğrula (`strlen`, karakter sayısı değil). Üretimde `sql_mode` içinde `STRICT_TRANS_TABLES` açık olsun ki kırpma hata olarak görünsün.
- **Türkçe arama:** Bağlantıyı `utf8mb4` yap. `s` ile `ş`, `c` ile `ç` ayrımı gerekiyorsa sütunu `utf8mb4_turkish_ci` ile tanımla. Varsayılan `utf8mb4_unicode_ci` aksana duyarsızdır, aramada beklenmedik sonuç getirir.

## Çalıştırma

PHP 8.1 veya üzeri, `pdo_sqlite` ve `pdo_mysql` eklentileri gerekir. SQLite bellek içi çalışır. MySQL tarafı için bir veritabanı ve kullanıcı ver:

```bash
export TUZAK_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=tuzak;charset=utf8mb4'
export TUZAK_MYSQL_USER=tuzak
export TUZAK_MYSQL_PASS=tuzak

php bin/fark       # sonuç tablosunu yazdırır
php tests/run.php  # beklentilerle karşılaştırır
```

Kullanıcının `tuzak` veritabanında tablo oluşturup silme yetkisi olmalıdır. Betik, her senaryo öncesi veritabanındaki **bütün tabloları siler**, bu yüzden üretim veritabanına yöneltme. MySQL'e bağlanılamazsa yalnızca SQLite tarafı çalışır, MySQL senaryoları atlanır.

## Sınırlar

- Ölçümler MariaDB 10.11 üzerinde yapıldı, gerçek MySQL 8 üzerinde ayrıca doğrulanmadı. Burada gösterilen farkların MySQL'de de geçerli olması beklenir ama kanıtlanmış değildir. `GROUP BY` senaryosunda MariaDB'nin varsayılanında `ONLY_FULL_GROUP_BY` kapalıdır, bu yüzden oturum ayarıyla elle açılır. Kendi MySQL'inde `php bin/fark` çalıştırıp farklı bir sonuç görürsen issue aç.
- `utf8mb4_turkish_ci` MariaDB'de vardır. MySQL 8'de de bu adla vardır, ama Türkçe için ayrıca `utf8mb4_tr_0900_ai_ci` seçeneği sunar ve sıralama kuralları farklı olabilir.
- Liste kapsamlı değildir, yalnızca sahada karşılaşılan farkları içerir. Tarih fonksiyonları, `AUTOINCREMENT`, tür yakınlığı gibi konular yoktur.

## Lisans

MIT, bkz. [LICENSE](LICENSE).
