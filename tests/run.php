<?php

declare(strict_types=1);

require __DIR__ . '/../src/Senaryolar.php';
require __DIR__ . '/../src/Calistirici.php';

use Tuzaklar\Calistirici;
use Tuzaklar\Senaryolar;

$c = Calistirici::olustur();
$basarili = 0;
$hatalar = [];
$atlanan = 0;

foreach (Senaryolar::hepsi() as $s) {
    foreach (['sqlite', 'mysql'] as $motor) {
        $sonuc = $c->calistir($motor, $s);
        if ($sonuc['durum'] === 'yok') {
            $atlanan++;
            continue;
        }
        if ($c->beklentiyiKarsila($sonuc, $s['beklenen'][$motor])) {
            $basarili++;
        } else {
            $hatalar[] = sprintf(
                "%s [%s]\n    beklenen: %s\n    gerçek:   %s: %s",
                $s['ad'],
                $motor,
                json_encode($s['beklenen'][$motor], JSON_UNESCAPED_UNICODE),
                $sonuc['durum'],
                mb_substr($sonuc['deger'], 0, 120),
            );
        }
    }
}

printf("%d başarılı, %d hata, %d atlandı (MySQL: %s)\n", $basarili, count($hatalar), $atlanan, $c->mysqlSurum());
foreach ($hatalar as $h) {
    echo "  ✗ $h\n";
}
exit($hatalar ? 1 : 0);
