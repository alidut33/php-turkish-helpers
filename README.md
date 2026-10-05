# php-turkish-helpers

PHP'de Türkçe metin sıralama, büyük/küçük harf dönüşümü ve telefon numarası
normalleştirme. **`intl` eklentisi gerektirmez.**

[![Tests](https://github.com/alidut33/php-turkish-helpers/actions/workflows/tests.yml/badge.svg)](https://github.com/alidut33/php-turkish-helpers/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

İki sınıf, sıfır bağımlılık. Canlıda çalışan bir sistemde ortaya çıkan iki
sorunu çözmek için yazıldı, oradan ayıklanıp paket haline getirildi.

---

## Neden var

### 1. PHP, Türkçe `i` harfini yanlış büyütüyor

`mb_strtoupper()` Unicode'un dilden bağımsız eşlemesini kullanıyor ve Türkçede
tek bir harfi yanlış çeviriyor:

```php
mb_strtoupper('İli')    // 'İLI'     ← noktasız I, yanlış
mb_strtoupper('Şirin')  // 'ŞIRIN'   ← yanlış
mb_strtoupper('ilginç') // 'ILGINÇ'  ← yanlış
```

Geri kalan harfler doğru çalışıyor; sorun yalnızca `i` / `I` çiftinde. Sertifika,
fatura, etiket gibi büyük harf kullanan her çıktıda isimler bozuk basılıyor.

### 2. Türkçe harfler alfabetik sıralamada Z'den sonraya düşüyor

```php
$isimler = ['Zeynep', 'Çınar', 'Ibrahim', 'Ömer', 'İrfan', 'Şule', 'Ali'];
sort($isimler);
// Ali, Ibrahim, Zeynep, Çınar, Ömer, İrfan, Şule   ← Ç/Ö/Ş/İ sonda
```

Doğru çözüm `Collator('tr_TR')` ama o **`intl` eklentisine bağlı** ve paylaşımlı
barındırmaların çoğunda kurulu değil.

Asıl tehlikeli olan şu yaygın desen:

```php
$cmp = class_exists(\Collator::class)
    ? (new \Collator('tr_TR'))->compare($a, $b)
    : strcasecmp($a, $b);          // ← eklenti yoksa buraya düşer
```

Eklenti yoksa hata vermiyor, **sessizce yanlış sıralıyor.** Ç/Ö/Ş/İ ile başlayan
isimler listenin sonunda birikiyor ve kimse fark etmiyor.

### 3. Aynı telefon numarasının beş farklı yazımı

Telefon, kullanıcı girişinde veya benzersizlik kısıtında kullanılıyorsa, iki
farklı yazım iki farklı kayda düşer. Sonucu ya "kullanıcı bulunamadı" ya da aynı
kişi için ikinci bir hesap.

---

## Kurulum

```bash
composer require alidut/php-turkish-helpers
```

Gereksinim: PHP 8.1+ ve `ext-mbstring`. **`ext-intl` gerekmez.**

---

## Kullanım

### TurkishText

```php
use AliDut\TurkishHelpers\TurkishText;

TurkishText::upper('İli');       // 'İLİ'
TurkishText::upper('ilginç');    // 'İLGİNÇ'
TurkishText::lower('IŞIL');      // 'ışıl'

// usort ile doğrudan kullanılabilir
$isimler = ['Zeynep', 'Çınar', 'Ibrahim', 'Ömer', 'İrfan', 'Şule', 'Ali'];
usort($isimler, [TurkishText::class, 'compare']);
// Ali, Çınar, Ibrahim, İrfan, Ömer, Şule, Zeynep
```

Sıralama büyük/küçük harf ayırmaz, boş değer en başa gelir. Alfabede olmayan
`q`, `w`, `x` sona atılmaz; İngilizcedeki komşularının arasına yerleştirilmiştir
(aksi halde "Max" listede "Zeynep"ten sonra çıkardı).

### PhoneNumber

```php
use AliDut\TurkishHelpers\PhoneNumber;

// Beş farklı yazım, tek kanonik değer
PhoneNumber::normalize('5372799324');         // '905372799324'
PhoneNumber::normalize('0537 279 93 24');     // '905372799324'
PhoneNumber::normalize('+90 537 279 93 24');  // '905372799324'
PhoneNumber::normalize('905372799324');       // '905372799324'
PhoneNumber::normalize('(0537) 279-93-24');   // '905372799324'

// Başka ülke
PhoneNumber::normalize('7911123456', '44');   // '447911123456'

// Kullanıcı "+" yazdıysa seçiciyi EZER (kopyala-yapıştır senaryosu)
PhoneNumber::normalize('+44 7911 123456', '90'); // '447911123456'

// Çözülemeyen girdi
PhoneNumber::normalize('abc');                // null

// WhatsApp click-to-chat
'https://wa.me/' . PhoneNumber::whatsapp('447911123456');

// Ekranda göstermek için ayır
PhoneNumber::parcala('905372799324');
// ['ulke' => '90', 'ulusal' => '5372799324']
```

**Kanonik biçim:** ülke kodu + ulusal numara, artı ve boşluk yok. Tek kolonda
saklanabilir, benzersizlik kısıtı ve giriş sorgusu tek değere bakar.

---

## Bilinmesi gereken üç ayrıntı

**`whatsapp()` ülke kodu EKLEMEZ.** Ona veritabanındaki kanonik değer gelir.
`normalize()` çağırsaydı ülke kodunu görmediğini varsayıp başına bir tane daha
ekler ve `447911123456` → `90447911123456` olurdu. `wa.me` bu numarada **hata
vermez**, sessizce "numara geçersiz" sayfası açar — yani yanlış yurt dışı
bağlantıları fark edilmeden aylarca yaşayabilir.

**Ülke kodu listesi uzundan kısaya sıralıdır.** `90` ile `994` aynı listede ve
kısa olan önce denenirse `994...` numarası `9` + `94...` diye yanlış bölünür.
Listeye yeni kod eklerken sırayı bozmayın — bir test bunu koruyor.

**`mb_strtolower('İ')` tek karakter döndürmez.** Sonuç `i` + birleşik nokta
(U+0069 U+0307); ekranda `i` gibi görünür ama iki karakterdir ve karşılaştırma
sessizce tutmaz. `TurkishText::lower()` bunu düzeltiyor.

---

## Testler

```bash
composer install
composer test
```

CI PHP 8.1 – 8.4 üzerinde koşuyor ve **`intl` eklentisini bilerek kurmuyor** —
paketin varlık sebebi eklenti olmadan doğru çalışmak; kurulursa testler bir şeyi
kanıtlamaz.

Testlerin bir kısmı PHP'nin *yanlış* davranışını da sabitliyor
(`test_mb_strtoupper_yetersiz_kaliyor`). PHP bir gün bunu düzeltirse o testler
kırılır ve paketin hâlâ gerekli olup olmadığı yeniden değerlendirilir.

---

## Lisans

MIT — bkz. [LICENSE](LICENSE).
