<?php

declare(strict_types=1);

namespace AliDut\TurkishHelpers;

/**
 * Serbest biçimli telefon numarasını tek bir KANONİK değere indirger:
 * ülke kodu + ulusal numara, artı ve boşluk yok.
 *
 * ================== NEDEN TEK BİÇİM ŞART ==================
 * Telefonun kullanıcı girişinde ya da benzersizlik kısıtında kullanıldığı her
 * sistemde, aynı numaranın iki farklı yazımı iki farklı kayda düşer. Sonucu
 * ya "kullanıcı bulunamadı" ya da aynı kişi için ikinci bir hesaptır.
 *
 * Aşağıdaki beş yazım aynı değere iner:
 *
 *     normalize('5372799324')            -> 905372799324   (varsayılan +90)
 *     normalize('0537 279 93 24')        -> 905372799324
 *     normalize('+90 537 279 93 24')     -> 905372799324
 *     normalize('905372799324')          -> 905372799324
 *     normalize('(0537) 279-93-24')      -> 905372799324
 *
 * ================== ÜÇ KURAL ==================
 * 1. Kullanıcı "+" ya da "00" yazdıysa ülke kodunu KENDİSİ söylemiştir;
 *    arayüzdeki seçici başka bir ülkede kalmış olsa bile onun yazdığı kazanır.
 *    Numarayı WhatsApp'tan kopyalayıp yapıştıran herkes bu biçimi getiriyor.
 * 2. Ülke kodu yoksa varsayılan uygulanır. Mevcut kayıtların çoğunun ülke
 *    kodsuz tutulduğu bir sisteme bu özellik sonradan ekleniyorsa, varsayılan
 *    olmadan o kayıtların tamamı bir gecede giriş yapamaz hale gelir.
 * 3. Ulusal biçimdeki baştaki sıfır ülke kodunun yerini tutar; uluslararası
 *    biçimde yeri yoktur, atılır.
 *
 * Çözülemeyen girdide `null` döner; çağıran bunu "geçersiz" sayar.
 */
final class PhoneNumber
{
    /** Ülke kodu verilmediğinde varsayılan. */
    public const VARSAYILAN_ULKE = '90';

    /** E.164 üst sınırı 15 hane. Alt sınır en kısa ulusal numarayı kapsayacak kadar gevşek. */
    private const ASGARI_HANE = 8;
    private const AZAMI_HANE  = 15;

    /**
     * Desteklenen ülke kodları — UZUN OLAN ÖNCE sıralı.
     *
     * ⚠ SIRA KRİTİK. '90' ile '994' aynı listede ve kısa olan önce denenirse
     * '994...' ile başlayan bir numara '9' + '94...' diye yanlış bölünür.
     * Listeye yeni kod eklerken uzunluk sırasını bozmayın.
     */
    public const ULKE_KODLARI = [
        // 3 haneliler önce
        '994', '995', '971', '966', '964', '963', '961', '389', '383', '380',
        '359', '355', '353',
        // 2 haneliler
        '98', '92', '90', '49', '48', '47', '46', '45', '44', '43', '41', '40',
        '39', '34', '33', '32', '31', '30', '27', '20',
        // 1 haneliler en sonda
        '7', '1',
    ];

    /** Serbest biçimli girdiyi kanonik biçime çevirir. */
    public static function normalize(?string $raw, ?string $ulkeKodu = null): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);

        // Kural 1: açık uluslararası biçim seçiciyi ezer.
        $acikUluslararasi = str_starts_with($raw, '+') || str_starts_with($raw, '00');

        $haneler = preg_replace('/\D+/', '', $raw) ?? '';
        if ($haneler === '') {
            return null;
        }

        if ($acikUluslararasi) {
            $haneler = preg_replace('/^00/', '', $haneler) ?? $haneler;

            return self::gecerliyseDondur($haneler);
        }

        $ulke = self::ulkeKodunuTemizle($ulkeKodu) ?? self::VARSAYILAN_ULKE;

        // Kullanıcı ülke kodunu artı KOYMADAN yazmış olabilir ("905372799324").
        // Kalan kısım makul bir ulusal numara uzunluğundaysa kabul ediliyor.
        if (str_starts_with($haneler, $ulke)) {
            $kalan = ltrim(substr($haneler, strlen($ulke)), '0');
            if (strlen($kalan) >= 9) {
                return self::gecerliyseDondur($ulke . $kalan);
            }
        }

        // Kural 3: baştaki sıfır ülke kodunun yerini tutar.
        $ulusal = ltrim($haneler, '0');
        if ($ulusal === '') {
            return null;
        }

        return self::gecerliyseDondur($ulke . $ulusal);
    }

    /**
     * WhatsApp "click-to-chat" bağlantısının beklediği biçim: `905372799324`.
     *
     * ⚠ BURASI `normalize()` ÇAĞIRMAZ. Buraya veritabanındaki değer gelir, yani
     * zaten ülke kodludur. `normalize()` ülke kodu görmediğini varsayıp başına
     * bir tane daha ekler ve '447911123456' -> '90447911123456' olur. `wa.me`
     * bu numarada HATA VERMEZ, sessizce "numara geçersiz" sayfası açar — yani
     * yanlış yurt dışı bağlantıları fark edilmeden aylarca yaşayabilir.
     *
     * 10 hane istisnası: ülke kodu eklemesinden kaçmış eski bir TR kaydı
     * kalmışsa bağlantı yine çalışsın.
     */
    public static function whatsapp(?string $kanonik): ?string
    {
        $haneler = preg_replace('/\D+/', '', (string) $kanonik) ?? '';

        if (strlen($haneler) === 10) {
            $haneler = self::VARSAYILAN_ULKE . $haneler;
        }

        return self::gecerliyseDondur($haneler);
    }

    /**
     * Kanonik numarayı ekranda göstermek için ayırır:
     *
     *     parcala('905372799324')  -> ['ulke' => '90', 'ulusal' => '5372799324']
     *     parcala('447911123456')  -> ['ulke' => '44', 'ulusal' => '7911123456']
     *
     * Ülke kodu listeden çözülemezse tamamı ulusal kabul edilir — yanlış yerden
     * bölüp kullanıcıya bozuk numara göstermektense bölmemek iyidir.
     */
    public static function parcala(?string $kanonik): array
    {
        $haneler = preg_replace('/\D+/', '', (string) $kanonik) ?? '';
        if ($haneler === '') {
            return ['ulke' => self::VARSAYILAN_ULKE, 'ulusal' => ''];
        }

        foreach (self::ULKE_KODLARI as $kod) {
            if (str_starts_with($haneler, $kod) && strlen($haneler) > strlen($kod)) {
                return ['ulke' => $kod, 'ulusal' => substr($haneler, strlen($kod))];
            }
        }

        return ['ulke' => self::VARSAYILAN_ULKE, 'ulusal' => $haneler];
    }

    private static function ulkeKodunuTemizle(?string $kod): ?string
    {
        if ($kod === null) {
            return null;
        }

        $temiz = preg_replace('/\D+/', '', $kod) ?? '';

        return $temiz === '' ? null : $temiz;
    }

    private static function gecerliyseDondur(string $haneler): ?string
    {
        $uzunluk = strlen($haneler);

        return ($uzunluk >= self::ASGARI_HANE && $uzunluk <= self::AZAMI_HANE) ? $haneler : null;
    }
}
