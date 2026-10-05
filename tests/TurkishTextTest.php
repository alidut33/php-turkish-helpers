<?php

declare(strict_types=1);

namespace AliDut\TurkishHelpers\Tests;

use AliDut\TurkishHelpers\TurkishText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TurkishTextTest extends TestCase
{
    #[DataProvider('buyukHarfOrnekleri')]
    public function test_turkce_buyuk_harf(string $girdi, string $beklenen): void
    {
        self::assertSame($beklenen, TurkishText::upper($girdi));
    }

    public static function buyukHarfOrnekleri(): array
    {
        return [
            ['İli', 'İLİ'],
            ['Şirin', 'ŞİRİN'],
            ['Ümit', 'ÜMİT'],
            ['ilginç', 'İLGİNÇ'],
            ['ışıl', 'IŞIL'],
            ['çiğdem', 'ÇİĞDEM'],
            ['Ali Dut', 'ALİ DUT'],
        ];
    }

    public function test_kucuk_harf(): void
    {
        self::assertSame('ışıl', TurkishText::lower('IŞIL'));
        self::assertSame('ili', TurkishText::lower('İLİ'));
        self::assertSame('çiğdem', TurkishText::lower('ÇİĞDEM'));
    }

    /**
     * `mb_strtolower('İ')` düz `i` değil, `i` + birleşik nokta (U+0069 U+0307)
     * döndürür. Ekranda aynı görünür ama iki karakterdir; karşılaştırma tutmaz.
     */
    public function test_buyuk_i_kucultunce_tek_karakter_kalir(): void
    {
        self::assertSame(1, mb_strlen(TurkishText::lower('İ'), 'UTF-8'));
        self::assertSame('i', TurkishText::lower('İ'));
    }

    public function test_bos_deger(): void
    {
        self::assertSame('', TurkishText::upper(null));
        self::assertSame('', TurkishText::upper(''));
        self::assertSame('', TurkishText::lower(null));
    }

    /**
     * Paketin varlık sebebi. PHP davranışı bir gün değişirse bu test kırılır
     * ve durumu yeniden değerlendirmek gerekir.
     */
    public function test_mb_strtoupper_yetersiz_kaliyor(): void
    {
        self::assertSame('İLI', mb_strtoupper('İli', 'UTF-8'), 'PHP davranisi degistiyse bu test guncellenmeli');
        self::assertNotSame(mb_strtoupper('İli', 'UTF-8'), TurkishText::upper('İli'));
    }

    public function test_turkce_alfabe_sirasi(): void
    {
        $isimler = ['Zeynep', 'Çınar', 'Ibrahim', 'Ömer', 'İrfan', 'Şule', 'Ali'];
        usort($isimler, [TurkishText::class, 'compare']);

        self::assertSame(
            ['Ali', 'Çınar', 'Ibrahim', 'İrfan', 'Ömer', 'Şule', 'Zeynep'],
            $isimler
        );
    }

    /**
     * Yaygın yedek çözümün (`strcasecmp`) neden yetersiz olduğunu gösteriyor:
     * çok baytlı harfler Z'den sonraya düşüyor.
     */
    public function test_bayt_siralamasi_turkce_harfleri_sona_atiyor(): void
    {
        $isimler = ['Zeynep', 'Çınar', 'Şule'];
        usort($isimler, 'strcasecmp');
        self::assertSame(['Zeynep', 'Çınar', 'Şule'], $isimler, 'PHP davranisi degistiyse bu test guncellenmeli');

        usort($isimler, [TurkishText::class, 'compare']);
        self::assertSame(['Çınar', 'Şule', 'Zeynep'], $isimler);
    }

    public function test_siralama_buyuk_kucuk_harf_ayirmiyor(): void
    {
        self::assertSame(0, TurkishText::compare('ÇİĞDEM', 'çiğdem'));
        self::assertTrue(TurkishText::compare('ali', 'Bora') < 0);
        self::assertTrue(TurkishText::compare('Bora', 'ali') > 0);
    }

    public function test_siralamada_bos_deger_basa_gelir(): void
    {
        self::assertTrue(TurkishText::compare(null, 'Ali') < 0);
        self::assertSame(0, TurkishText::compare(null, ''));
    }

    /** Alfabede olmayan harfler sona atılmamalı. */
    public function test_q_w_x_komsularinin_arasinda(): void
    {
        $isimler = ['Zeynep', 'Max', 'Ali', 'William'];
        usort($isimler, [TurkishText::class, 'compare']);

        self::assertSame(['Ali', 'Max', 'William', 'Zeynep'], $isimler);
    }
}
