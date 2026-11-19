<?php

declare(strict_types=1);

namespace Tuzaklar;

use PDO;
use PDOException;

final class Calistirici
{
    public function __construct(private PDO $sqlite, private ?PDO $mysql, private ?PDO $mysqlLatin1 = null)
    {
    }

    public static function olustur(): self
    {
        $sqlite = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $dsn = getenv('TUZAK_MYSQL_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=tuzak;charset=utf8mb4';
        $kullanici = getenv('TUZAK_MYSQL_USER') ?: 'tuzak';
        $parola = getenv('TUZAK_MYSQL_PASS') ?: 'tuzak';
        $secenek = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];

        $mysql = $latin1 = null;
        try {
            $mysql = new PDO($dsn, $kullanici, $parola, $secenek);
            // aynı veritabanına bağlantı karakter kümesi latin1 olan ikinci bir bağlantı
            $latin1 = new PDO(str_replace('charset=utf8mb4', 'charset=latin1', $dsn), $kullanici, $parola, $secenek);
        } catch (PDOException) {
            // MySQL yoksa yalnızca SQLite tarafı çalışır
        }

        return new self($sqlite, $mysql, $latin1);
    }

    public function mysqlVar(): bool
    {
        return $this->mysql !== null;
    }

    public function mysqlSurum(): string
    {
        return $this->mysql ? (string) $this->mysql->query('SELECT VERSION()')->fetchColumn() : '-';
    }

    /** @param array<string, mixed> $senaryo @return array{durum: string, deger: string} */
    public function calistir(string $motor, array $senaryo): array
    {
        $db = $motor === 'sqlite' ? $this->sqlite : $this->mysql;
        if ($db === null) {
            return ['durum' => 'yok', 'deger' => ''];
        }

        $this->temizle($db, $motor);

        try {
            if ($motor === 'mysql' && isset($senaryo['oturum'])) {
                $db->exec($senaryo['oturum']);
            }
            foreach ($senaryo['kurulum'] ?? [] as $sql) {
                $db->exec($sql);
            }

            // sorgu başka karakter kümesiyle bağlanmış bir bağlantıdan gidebilir
            $sorguDb = $motor === 'mysql' && ($senaryo['sorguBaglantisi'] ?? '') === 'latin1' ? $this->mysqlLatin1 : $db;
            $sorgu = $sorguDb->query($senaryo['sorgu']);
            $satir = $sorgu && $sorgu->columnCount() > 0 ? $sorgu->fetch(PDO::FETCH_NUM) : false;

            return ['durum' => 'ok', 'deger' => $satir === false ? '' : (string) $satir[0]];
        } catch (PDOException $e) {
            return ['durum' => 'hata', 'deger' => $e->getMessage()];
        } finally {
            if ($motor === 'mysql') {
                // oturum ayarı sonraki senaryoya sızmasın
                $db->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES'");
            }
        }
    }

    public function beklentiyiKarsila(array $sonuc, array $beklenen): bool
    {
        if (isset($beklenen['ok'])) {
            return $sonuc['durum'] === 'ok' && $sonuc['deger'] === $beklenen['ok'];
        }

        return $sonuc['durum'] === 'hata' && stripos($sonuc['deger'], $beklenen['hata']) !== false;
    }

    private function temizle(PDO $db, string $motor): void
    {
        if ($motor === 'sqlite') {
            foreach ($db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
                $db->exec("DROP TABLE \"$t\"");
            }
            return;
        }
        foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $db->exec("DROP TABLE `$t`");
        }
    }
}
