<?php

declare(strict_types=1);

namespace AliDut\TurkishHelpers;

/**
 * Türkçeye duyarlı harf dönüşümleri ve alfabetik sıralama.
 *
 * ================== BÜYÜK/KÜÇÜK HARF ==================
 * PHP'nin `mb_strtoupper()` fonksiyonu Unicode'un dilden bağımsız eşlemesini
 * kullanır ve Türkçede TEK bir harfi yanlış çevirir:
 *
 *     i  ->  I    (olması gereken: İ)
 *
 * Ölçüldü (PHP 8.3, mbstring):
 *
 *     mb_strtoupper('İli')    => 'İLI'      doğrusu 'İLİ'
 *     mb_strtoupper('Şirin')  => 'ŞIRIN'    doğrusu 'ŞİRİN'
 *     mb_strtoupper('ilginç') => 'ILGINÇ'   doğrusu 'İLGİNÇ'
 *
 * Diğerleri zaten doğru: `ı` -> `I` çalışıyor, `ş/ğ/ç/ö/ü` de doğru büyüyor.
 * Yani sorun yalnızca `i` harfinde; küçültmede de simetriği (`I` -> `ı`) gerekli.
 *
 * `setlocale(LC_CTYPE, 'tr_TR')` ile çözmek CAZİP AMA YANLIŞ: locale sunucuda
 * kurulu olmayabilir, süreç genelini etkiler ve `strtoupper()` yine bayt bazlı
 * çalıştığı için çok baytlı harfleri bozar. Bu yüzden eşleme elle yapılıyor.
 *
 * ================== SIRALAMA ==================
 * Doğru çözüm `Collator('tr_TR')` ama o `intl` eklentisine bağlı ve paylaşımlı
 * barındırmaların çoğunda kurulu değil. Eklenti yokken yaygın yedek olan
 * `strcasecmp()` bayt bazlı çalıştığı için çok baytlı Türkçe harfleri
 * Z'DEN SONRAYA atıyor:
 *
 *     sort()  : Ali, Ibrahim, Zeynep, Çınar, Ömer, İrfan, Şule
 *     doğrusu : Ali, Çınar, Ibrahim, İrfan, Ömer, Şule, Zeynep
 *
 * Sinsi tarafı şu: kod "Collator varsa onu, yoksa strcasecmp" diye yazıldığında
 * hata vermiyor, sadece sessizce yanlış sıralıyor. Ç/Ö/Ş/İ ile başlayan isimler
 * listenin sonunda birikiyor ve kimse fark etmiyor.
 *
 * Buradaki `compare()` eklentiye bağlı değil: her harfi alfabedeki sırasına
 * karşılık gelen iki haneli bir ağırlığa çevirip düz `strcmp` ile karşılaştırıyor.
 */
final class TurkishText
{
    /** Büyütmeden ÖNCE değiştirilmesi gerekenler. */
    private const UPPER_MAP = ['i' => 'İ'];

    /**
     * Küçültmeden ÖNCE değiştirilmesi gerekenler.
     *
     * `İ` de listede olmak ZORUNDA: `mb_strtolower('İ')` sonucu düz `i` değil,
     * `i` + BİRLEŞİK NOKTA (U+0069 U+0307) oluyor. Ekranda `i` gibi görünür ama
     * iki karakterdir — karşılaştırma ve arama sessizce tutmaz.
     */
    private const LOWER_MAP = ['I' => 'ı', 'İ' => 'i'];

    /**
     * Türk alfabesi sırası. Dizideki konum = sıralama ağırlığı.
     *
     * `q`, `w`, `x` alfabede yok ama yabancı isimlerde geçiyor. Sona koymak
     * yanlış olurdu ("Max" listede "Zeynep"ten sonra çıkardı), bu yüzden
     * İngilizcedeki komşularının arasına yerleştirildiler.
     */
    private const ALFABE = [
        'a', 'b', 'c', 'ç', 'd', 'e', 'f', 'g', 'ğ', 'h', 'ı', 'i', 'j', 'k',
        'l', 'm', 'n', 'o', 'ö', 'p', 'q', 'r', 's', 'ş', 't', 'u', 'ü', 'v',
        'w', 'x', 'y', 'z',
    ];

    /** @var array<string,string>|null Harf -> iki haneli sıra anahtarı. */
    private static ?array $agirlik = null;

    /** Türkçe kurallarına göre BÜYÜK harfe çevirir. */
    public static function upper(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return mb_strtoupper(strtr($value, self::UPPER_MAP), 'UTF-8');
    }

    /** Türkçe kurallarına göre küçük harfe çevirir. */
    public static function lower(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return mb_strtolower(strtr($value, self::LOWER_MAP), 'UTF-8');
    }

    /**
     * Türkçe alfabeye göre iki metni karşılaştırır. `usort` ile doğrudan
     * kullanılabilir:
     *
     *     usort($isimler, [TurkishText::class, 'compare']);
     *
     * Büyük/küçük harf ayırmaz. Boş değer en başa gelir.
     */
    public static function compare(?string $a, ?string $b): int
    {
        return strcmp(self::siralamaAnahtari($a), self::siralamaAnahtari($b));
    }

    /**
     * Metni, düz `strcmp` ile doğru sıralanacak bir anahtara çevirir.
     *
     * Tanınan her harf iki haneli sırasıyla değiştiriliyor; alfabede olmayan
     * karakterler (rakam, boşluk, kesme işareti) olduğu gibi kalıyor ve iki
     * haneli sayılardan küçük oldukları için harflerden önce geliyor —
     * "Ali Veli" ile "Alican" karşılaştırmasında boşluğun harften önce gelmesi
     * doğru davranış.
     */
    private static function siralamaAnahtari(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (self::$agirlik === null) {
            self::$agirlik = [];
            foreach (self::ALFABE as $sira => $harf) {
                self::$agirlik[$harf] = str_pad((string) ($sira + 10), 2, '0', STR_PAD_LEFT);
            }
        }

        $anahtar = '';
        foreach (mb_str_split(self::lower($value), 1, 'UTF-8') as $harf) {
            $anahtar .= self::$agirlik[$harf] ?? $harf;
        }

        return $anahtar;
    }
}
