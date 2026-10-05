<?php

declare(strict_types=1);

namespace AliDut\TurkishHelpers\Tests;

use AliDut\TurkishHelpers\PhoneNumber;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** Paketin varlık sebebi: beş farklı yazım, tek değer. */
    public function test_farkli_yazimlar_ayni_numaraya_duser(): void
    {
        $beklenen = '905372799324';

        foreach ([
            '5372799324',
            '0537 279 93 24',
            '+90 537 279 93 24',
            '905372799324',
            '(0537) 279-93-24',
        ] as $girdi) {
            self::assertSame($beklenen, PhoneNumber::normalize($girdi), "girdi: {$girdi}");
        }
    }

    public function test_ulke_kodu_verilmezse_varsayilan_uygulanir(): void
    {
        self::assertSame('905372799324', PhoneNumber::normalize('5372799324'));
    }

    public function test_secilen_ulke_kodu_uygulanir(): void
    {
        self::assertSame('447911123456', PhoneNumber::normalize('7911123456', '44'));
        self::assertSame('447911123456', PhoneNumber::normalize('07911123456', '44'));
    }

    /** Kullanıcı "+" yazdıysa seçiciyi ezer — kopyala-yapıştır senaryosu. */
    public function test_acik_ulke_kodu_secimi_ezer(): void
    {
        self::assertSame('447911123456', PhoneNumber::normalize('+44 7911 123456', '90'));
        self::assertSame('447911123456', PhoneNumber::normalize('0044 7911 123456', '90'));
    }

    public function test_gecersiz_girdiler_null_doner(): void
    {
        self::assertNull(PhoneNumber::normalize(null));
        self::assertNull(PhoneNumber::normalize(''));
        self::assertNull(PhoneNumber::normalize('   '));
        self::assertNull(PhoneNumber::normalize('abc'));
        self::assertNull(PhoneNumber::normalize('123'), 'cok kisa');
        self::assertNull(PhoneNumber::normalize('1234567890123456789'), 'cok uzun');
    }

    /**
     * En sinsi hata: wa.me yanlış numarada hata vermez, sessizce "geçersiz
     * numara" sayfası açar. Bu yüzden ülke kodu ikinci kez eklenmemeli.
     */
    public function test_whatsapp_yurt_disi_numaraya_90_eklemez(): void
    {
        self::assertSame('447911123456', PhoneNumber::whatsapp('447911123456'));
        self::assertSame('905372799324', PhoneNumber::whatsapp('905372799324'));
    }

    /** Ülke kodundan kaçmış eski 10 haneli kayıt yine çalışsın. */
    public function test_whatsapp_on_haneli_eski_kaydi_tamamlar(): void
    {
        self::assertSame('905372799324', PhoneNumber::whatsapp('5372799324'));
    }

    public function test_parcala_ekranda_gostermek_icin_ayirir(): void
    {
        self::assertSame(
            ['ulke' => '90', 'ulusal' => '5372799324'],
            PhoneNumber::parcala('905372799324')
        );

        self::assertSame(
            ['ulke' => '44', 'ulusal' => '7911123456'],
            PhoneNumber::parcala('447911123456')
        );
    }

    public function test_parcala_cozemezse_tamamini_ulusal_sayar(): void
    {
        $sonuc = PhoneNumber::parcala('');
        self::assertSame('', $sonuc['ulusal']);
    }

    /**
     * ⚠ Bu test listedeki sıralamayı koruyor. Kısa kod uzun kodun önüne
     * geçerse '994...' numarası '9' + '94...' diye yanlış bölünür.
     */
    public function test_ulke_kodlari_uzundan_kisaya_sirali(): void
    {
        $uzunluklar = array_map('strlen', PhoneNumber::ULKE_KODLARI);
        $sirali = $uzunluklar;
        rsort($sirali);

        self::assertSame($sirali, $uzunluklar, 'ULKE_KODLARI uzundan kisaya sirali olmali');
    }

    /** Azerbaycan (994) numarası Türkiye (90) ile karışmamalı. */
    public function test_uzun_ulke_kodu_dogru_cozuluyor(): void
    {
        self::assertSame(
            ['ulke' => '994', 'ulusal' => '501234567'],
            PhoneNumber::parcala('994501234567')
        );
    }

    /** Kanonik değer kendi üzerine tekrar uygulanınca değişmemeli. */
    public function test_kanonik_deger_sabit_kalir(): void
    {
        $kanonik = PhoneNumber::normalize('+90 537 279 93 24');

        self::assertSame($kanonik, PhoneNumber::normalize($kanonik));
    }
}
