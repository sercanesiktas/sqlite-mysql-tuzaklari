<?php

declare(strict_types=1);

namespace Tuzaklar;

/**
 * Her senaryo iki motorda çalıştırılır. 'kurulum' ve 'sorgu' iki motor için ortak SQL, 'sqlite' ve 'mysql'
 * anahtarları o motora özel SQL'dir. 'oturum' MySQL tarafında önce çalıştırılacak SET komutudur.
 * 'beklenen': her motor için ['ok' => değer] ya da ['hata' => mesajda geçen parça].
 */
final class Senaryolar
{
    /** @return list<array<string, mixed>> */
    public static function hepsi(): array
    {
        $uzun = str_repeat('a', 70000);

        return [
            [
                'ad' => 'like-escape-ters-bolu',
                'baslik' => "LIKE ... ESCAPE '\\'",
                'aciklama' => "Ters bölü kaçış karakteri olarak yazıldığında MySQL'de kapanış tırnağını kaçırır.",
                'sorgu' => "SELECT 'a_b' LIKE 'a\\_b' ESCAPE '\\'",
                'beklenen' => ['sqlite' => ['ok' => '1'], 'mysql' => ['hata' => 'syntax']],
            ],
            [
                'ad' => 'like-escape-unlem',
                'baslik' => "LIKE ... ESCAPE '!'",
                'aciklama' => 'Ters bölü yerine başka bir karakter her iki motorda aynı çalışır.',
                'sorgu' => "SELECT 'a_b' LIKE 'a!_b' ESCAPE '!'",
                'beklenen' => ['sqlite' => ['ok' => '1'], 'mysql' => ['ok' => '1']],
            ],
            [
                'ad' => 'like-escape-cift-ters-bolu',
                'baslik' => "LIKE ... ESCAPE '\\\\'",
                'aciklama' => "MySQL iki ters bölüyü tek karaktere çevirir, SQLite ise iki karakterlik ifadeyi reddeder.",
                'sorgu' => "SELECT 'a_b' LIKE 'a\\\\_b' ESCAPE '\\\\'",
                'beklenen' => ['sqlite' => ['hata' => 'ESCAPE'], 'mysql' => ['ok' => '1']],
            ],
            [
                'ad' => 'baglama-operatoru',
                'baslik' => "'a' || 'b'",
                'aciklama' => "SQLite'ta birleştirme, MySQL'de varsayılan olarak mantıksal VEYA.",
                'sorgu' => "SELECT 'a' || 'b'",
                'beklenen' => ['sqlite' => ['ok' => 'ab'], 'mysql' => ['ok' => '0']],
            ],
            [
                'ad' => 'concat-fonksiyonu',
                'baslik' => "CONCAT('a', 'b')",
                'aciklama' => "|| yerine CONCAT() iki motorda da aynı çalışır. SQLite'ta bu fonksiyon 3.44 sürümünden beri var.",
                'sorgu' => "SELECT CONCAT('a', 'b')",
                'beklenen' => ['sqlite' => ['ok' => 'ab'], 'mysql' => ['ok' => 'ab']],
            ],
            [
                'ad' => 'tam-sayi-bolme',
                'baslik' => '5 / 2',
                'aciklama' => "SQLite iki tam sayıyı bölünce tam sayı verir, MySQL ondalıklı.",
                'sorgu' => 'SELECT 5 / 2',
                'beklenen' => ['sqlite' => ['ok' => '2'], 'mysql' => ['ok' => '2.5000']],
            ],
            [
                'ad' => 'tur-denetimi',
                'baslik' => "INTEGER sütununa 'abc'",
                'aciklama' => "SQLite sütun türüne uymayan değeri olduğu gibi saklar, MySQL katı kipte reddeder.",
                'kurulum' => ['CREATE TABLE tur (sayi INTEGER)'],
                'sorgu' => "INSERT INTO tur (sayi) VALUES ('abc')",
                'beklenen' => ['sqlite' => ['ok' => ''], 'mysql' => ['hata' => 'Incorrect integer']],
            ],
            [
                'ad' => 'group-by',
                'baslik' => 'GROUP BY ile gruplanmamış sütun',
                'aciklama' => "SQLite hangi satırın geleceğini gelişigüzel seçer, ONLY_FULL_GROUP_BY açık MySQL hata verir.",
                'oturum' => "SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY'",
                'kurulum' => [
                    'CREATE TABLE urun (kategori VARCHAR(20), baslik VARCHAR(20))',
                    "INSERT INTO urun VALUES ('a', 'x'), ('a', 'y')",
                ],
                'sorgu' => 'SELECT kategori, baslik, COUNT(*) FROM urun GROUP BY kategori',
                'beklenen' => ['sqlite' => ['ok' => 'a'], 'mysql' => ['hata' => 'GROUP BY']],
            ],
            [
                'ad' => 'text-siniri-sessiz',
                'baslik' => 'TEXT sütununa 70.000 baytlık değer, katı olmayan kip',
                'aciklama' => "MySQL TEXT en fazla 65.535 bayttır. Katı olmayan kipte fazlası uyarısız kırpılır, SQLite tamamını saklar.",
                'oturum' => "SET SESSION sql_mode = ''",
                'kurulum' => ['CREATE TABLE metin (icerik TEXT)', "INSERT INTO metin (icerik) VALUES ('$uzun')"],
                'sorgu' => 'SELECT LENGTH(icerik) FROM metin',
                'beklenen' => ['sqlite' => ['ok' => '70000'], 'mysql' => ['ok' => '65535']],
            ],
            [
                'ad' => 'text-siniri-kati',
                'baslik' => 'TEXT sütununa 70.000 baytlık değer, katı kip',
                'aciklama' => 'Katı kipte (MySQL 5.7 ve sonrasının varsayılanı) aynı ekleme hata verir.',
                'oturum' => "SET SESSION sql_mode = 'STRICT_TRANS_TABLES'",
                'kurulum' => ['CREATE TABLE metin (icerik TEXT)'],
                'sorgu' => "INSERT INTO metin (icerik) VALUES ('$uzun')",
                'beklenen' => ['sqlite' => ['ok' => ''], 'mysql' => ['hata' => 'Data too long']],
            ],
            [
                'ad' => 'turkce-like-unicode-ci',
                'baslik' => "LIKE '%ş%', varsayılan utf8mb4_unicode_ci",
                'aciklama' => "Varsayılan collation aksana duyarsız: s ile ş eşit sayılır, 'sis' de 'ş içeriyor' çıkar. SQLite yalnızca birebir eşleşmeyi bulur.",
                'kurulum' => [
                    'CREATE TABLE kelime (ad VARCHAR(20))',
                    "INSERT INTO kelime VALUES ('ışık'), ('şeker'), ('sis')",
                ],
                'sorgu' => "SELECT COUNT(*) FROM kelime WHERE ad LIKE '%ş%'",
                'beklenen' => ['sqlite' => ['ok' => '2'], 'mysql' => ['ok' => '3']],
            ],
            [
                'ad' => 'turkce-like-turkish-ci',
                'baslik' => "LIKE '%ş%', utf8mb4_turkish_ci",
                'aciklama' => 'Sütunu Türkçe collation ile tanımlayınca s ve ş ayrılır, iki satır eşleşir. SQLite bu collation adını tanımaz.',
                'kurulum' => [
                    'CREATE TABLE kelime (ad VARCHAR(20) COLLATE utf8mb4_turkish_ci)',
                    "INSERT INTO kelime VALUES ('ışık'), ('şeker'), ('sis')",
                ],
                'sorgu' => "SELECT COUNT(*) FROM kelime WHERE ad LIKE '%ş%'",
                'beklenen' => ['sqlite' => ['hata' => 'collation'], 'mysql' => ['ok' => '2']],
            ],
            [
                'ad' => 'turkce-like-latin1-baglanti',
                'baslik' => "LIKE '%ş%', bağlantı latin1",
                'aciklama' => 'Aynı veri, aynı sorgu, ama bağlantı latin1: hata vermeden sıfır satır döner. SQLite bağlantı karakter kümesi tanımaz.',
                'kurulum' => [
                    'CREATE TABLE kelime (ad VARCHAR(20))',
                    "INSERT INTO kelime VALUES ('ışık'), ('şeker'), ('sis')",
                ],
                'sorguBaglantisi' => 'latin1',
                'sorgu' => "SELECT COUNT(*) FROM kelime WHERE ad LIKE '%ş%'",
                'beklenen' => ['sqlite' => ['ok' => '2'], 'mysql' => ['ok' => '0']],
            ],
            [
                'ad' => 'turkce-buyuk-kucuk-like',
                'baslik' => "'IŞIK' LIKE 'ışık'",
                'aciklama' => "SQLite LIKE yalnızca ASCII harflerde büyük/küçük ayırmaz. MySQL sonucu sütunun collation'ına bağlıdır.",
                'sorgu' => "SELECT 'IŞIK' LIKE 'ışık'",
                'beklenen' => ['sqlite' => ['ok' => '0'], 'mysql' => ['ok' => '1']],
            ],
            [
                'ad' => 'substr-sifir',
                'baslik' => "SUBSTR('abcdef', 0, 3)",
                'aciklama' => "Konum 0 verilince SQLite ilk karakterden önce başlar ve yalnızca 'ab' döner, MySQL boş metin döner. İkisinde de konum 1'den başlar.",
                'sorgu' => "SELECT SUBSTR('abcdef', 0, 3)",
                'beklenen' => ['sqlite' => ['ok' => 'ab'], 'mysql' => ['ok' => '']],
            ],
            [
                'ad' => 'metin-sayi-karsilastirma',
                'baslik' => "'abc' = 0",
                'aciklama' => "MySQL metni sayıya çevirir ('abc' 0 olur), bu yüzden eşit çıkar. SQLite türleri karşılaştırmaz, eşit saymaz.",
                'sorgu' => "SELECT 'abc' = 0",
                'beklenen' => ['sqlite' => ['ok' => '0'], 'mysql' => ['ok' => '1']],
            ],
            [
                'ad' => 'bos-metin-sayi-karsilastirma',
                'baslik' => "'' = 0",
                'aciklama' => "Boş metin MySQL'de 0'a eşittir, bu yüzden WHERE sutun = 0 boş metin satırlarını da getirir. SQLite getirmez.",
                'sorgu' => "SELECT '' = 0",
                'beklenen' => ['sqlite' => ['ok' => '0'], 'mysql' => ['ok' => '1']],
            ],
            [
                'ad' => 'insert-or-ignore',
                'baslik' => 'INSERT OR IGNORE',
                'aciklama' => "SQLite'ın çakışmayı yoksayan ekleme sözdizimi MySQL'de yoktur, MySQL'in karşılığı INSERT IGNORE'dur.",
                'kurulum' => ['CREATE TABLE t (id INT PRIMARY KEY)', 'INSERT INTO t VALUES (1)'],
                'sorgu' => 'INSERT OR IGNORE INTO t VALUES (1)',
                'beklenen' => ['sqlite' => ['ok' => ''], 'mysql' => ['hata' => 'syntax']],
            ],
            [
                'ad' => 'autoincrement',
                'baslik' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
                'aciklama' => "SQLite'ta AUTOINCREMENT, MySQL'de AUTO_INCREMENT yazılır. Sütun tanımı göçte olduğu gibi çalışmaz.",
                'sorgu' => 'CREATE TABLE a (id INTEGER PRIMARY KEY AUTOINCREMENT)',
                'beklenen' => ['sqlite' => ['ok' => ''], 'mysql' => ['hata' => 'syntax']],
            ],
            [
                'ad' => 'date-now-sessiz',
                'baslik' => "DATE('now')",
                'aciklama' => "SQLite 'now' ile bugünün tarihini verir. MySQL'de aynı çağrı hata vermez, NULL döner ve tarih kolonuna sessizce NULL yazılır.",
                'sorgu' => "SELECT DATE('now') IS NULL",
                'beklenen' => ['sqlite' => ['ok' => '0'], 'mysql' => ['ok' => '1']],
            ],
            [
                'ad' => 'strftime',
                'baslik' => 'STRFTIME()',
                'aciklama' => "SQLite'a özgü tarih biçimleme fonksiyonu MySQL'de yoktur (karşılığı DATE_FORMAT).",
                'sorgu' => "SELECT STRFTIME('%Y', '2026-11-23')",
                'beklenen' => ['sqlite' => ['ok' => '2026'], 'mysql' => ['hata' => 'does not exist']],
            ],
        ];
    }
}
